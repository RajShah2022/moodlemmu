<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * The gradebook user report
 *
 * @package   gradereport_user
 * @copyright 2007 Moodle Pty Ltd (http://moodle.com)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once '../../../config.php';
require_once $CFG->libdir . '/gradelib.php';
require_once $CFG->dirroot . '/grade/lib.php';
require_once $CFG->dirroot . '/grade/report/user/lib.php';
require_once '../../../vendor/autoload.php'; // Ensure OpenAI SDK is loaded
require_once($CFG->dirroot . '/grade/report/ai/locallib.php');
require_once('randombagreport.php');
require_once('randombagview.php');
require_once('quizdataprocessor.php');

$openai_api_key = $CFG->openai_api_key;

$courseid = required_param('id', PARAM_INT);
$userid   = optional_param('userid', null, PARAM_INT);
$userview = optional_param('userview', 0, PARAM_INT);

$PAGE->set_url(new moodle_url('/grade/report/user/index.php', ['id' => $courseid]));

if ($userview == 0) {
    $userview = get_user_preferences('gradereport_user_view_user', GRADE_REPORT_USER_VIEW_USER);
} else {
    set_user_preference('gradereport_user_view_user', $userview);
}

// Basic access checks.
if (!$course = $DB->get_record('course', ['id' => $courseid])) {
    throw new \moodle_exception('invalidcourseid');
}
require_login($course);
$PAGE->set_pagelayout('report');

$openai = OpenAI::client($openai_api_key);
$context = context_course::instance($course->id);
require_capability('gradereport/user:view', $context);

if ($userid === 0) {
    require_capability('moodle/grade:viewall', $context);
} else if ($userid) {
    if (!$DB->get_record('user', ['id' => $userid, 'deleted' => 0]) || isguestuser($userid)) {
        throw new \moodle_exception('invaliduser');
    }
}

$access = false;
if (has_capability('moodle/grade:viewall', $context)) {
    // User can view all course grades.
    $access = true;
} else if (($userid == $USER->id || is_null($userid)) && has_capability('moodle/grade:view', $context) && $course->showgrades) {
    // User can view own grades.
    $access = true;
} else if (has_capability('moodle/grade:viewall', context_user::instance($userid)) && $course->showgrades) {
    // User can view grades of this user, The user is an parent most probably.
    $access = true;
}

if (!$access) {
    // The user has no access to grades.
    throw new \moodle_exception('nopermissiontoviewgrades', 'error',  $CFG->wwwroot . '/course/view.php?id=' . $courseid);
}

// Initialise the grade tracking object.
$gpr = new grade_plugin_return(['type' => 'report', 'plugin' => 'user', 'courseid' => $courseid, 'userid' => $userid]);

// Infer the users previously selected report via session tracking.
if (!isset($USER->grade_last_report)) {
    $USER->grade_last_report = [];
}
$USER->grade_last_report[$course->id] = 'user';

// First make sure we have proper final grades.
grade_regrade_final_grades_if_required($course);

$gradesrenderer = $PAGE->get_renderer('core_grades');

// Check if user is HOD
$is_hod = false;
$hod_role = $DB->get_record('role', ['shortname' => 'hod_course']);
if ($hod_role) {
    $roles = get_user_roles($context, $USER->id);
    foreach ($roles as $role) {
        if ($role->roleid == $hod_role->id) {
            $is_hod = true;
            break;
        }
    }
}
$student_quiz_data_sql = "
                     SELECT 
                                u.id AS student_id,
                                u.firstname AS student_firstname,
                                u.lastname AS student_lastname,
                                c.id AS course_id,
                                c.fullname AS course_name,
                                cs.name AS section_name,
                                qz.id AS quiz_id,
                                qz.name AS quiz_name,
                                qa.attempt AS attempt_number,
                                qatt.slot AS question_position,
                                qatt.questionid AS question_id,
                                q.questiontext AS question_text,
                                qatt.maxmark AS max_question_grade, -- Maximum grade for each question
                                (qattstep.fraction * qatt.maxmark) AS grade_achieved_for_question, -- Grade achieved for the question
                                qattstep.state AS question_state,
                                qattstep.fraction AS answer_fraction, -- Fraction achieved for each question
                                ti.itemid AS tagged_question_id,
                                tg.name AS tag_name
                            FROM 
                                mdl_user u
                            JOIN 
                                mdl_user_enrolments ue ON ue.userid = u.id
                            JOIN 
                                mdl_enrol e ON e.id = ue.enrolid
                            JOIN 
                                mdl_course c ON c.id = e.courseid
                            JOIN 
                                mdl_course_sections cs ON cs.course = c.id
                            JOIN 
                                mdl_course_modules cm ON cm.course = c.id AND cm.section = cs.id
                            JOIN 
                                mdl_modules m ON m.id = cm.module AND m.name = 'quiz'
                            JOIN 
                                mdl_quiz qz ON qz.id = cm.instance
                            JOIN     
                                (
                                    SELECT 
                                        qa1.userid, 
                                        qa1.quiz, 
                                        MAX(qa1.attempt) AS latest_attempt
                                    FROM 
                                        mdl_quiz_attempts qa1
                                    GROUP BY 
                                        qa1.userid, qa1.quiz
                                ) AS latest_attempts ON latest_attempts.userid = u.id 
                                                      AND latest_attempts.quiz = qz.id
                            JOIN 
                                mdl_quiz_attempts qa ON qa.userid = latest_attempts.userid 
                                                  AND qa.quiz = latest_attempts.quiz 
                                                  AND qa.attempt = latest_attempts.latest_attempt
                            JOIN 
                                mdl_question_usages qu ON qu.id = qa.uniqueid
                            JOIN 
                                mdl_question_attempts qatt ON qatt.questionusageid = qu.id
                            JOIN 
                                mdl_question q ON q.id = qatt.questionid
                            JOIN 
                                (
                                    SELECT 
                                        qattstep1.questionattemptid, 
                                        MAX(qattstep1.id) AS final_step_id
                                    FROM 
                                        mdl_question_attempt_steps qattstep1
                                    WHERE 
                                        qattstep1.state IN ('graded', 'gradedwrong', 'gradedright','gradedpartial') -- Only final graded states
                                    GROUP BY 
                                        qattstep1.questionattemptid
                                ) AS final_steps ON final_steps.questionattemptid = qatt.id
                            JOIN 
                                mdl_question_attempt_steps qattstep ON qattstep.id = final_steps.final_step_id
                            LEFT JOIN 
                                mdl_tag_instance ti ON ti.itemid = qatt.questionid AND ti.itemtype = 'question'
                            LEFT JOIN 
                                mdl_tag tg ON tg.id = ti.tagid
                            WHERE 
                                u.id = :student_id AND c.id = :course_id
                            ORDER BY 
                                u.id, c.id, qz.id, qa.attempt, qatt.slot;";
// Permission check if the user has report view access as teacher or hod.
if (has_capability('moodle/grade:viewall', $context)) {

    // If user is HOD, show moodle's default report
    if ($is_hod) {
        $groupmode = groups_get_course_groupmode($course);
        $currentgroup = $gpr->groupid;
        // Conditionally add the group JS if we have groups enabled.
        if ($groupmode) {
            $PAGE->requires->js_call_amd('gradereport_user/group', 'init');
        }

        // To make some other functions work better later.
        if (!$currentgroup) {
            $currentgroup = null;
        }

        $isseparategroups = ($course->groupmode == SEPARATEGROUPS && !has_capability('moodle/site:accessallgroups', $context));

        if ($isseparategroups && (!$currentgroup)) {
            // No separate group access, The user can view only themselves.
            $userid = $USER->id;
        }

        // If there is a stored (last viewed) user in a session variable, bypass the user select zero state and display the
        // report for that user.
        $lastvieweduserid = $SESSION->gradereport_user["useritem-{$context->id}"] ?? null;
        if (is_null($userid) && !is_null($lastvieweduserid)) {
            $userid = $lastvieweduserid;
        }

        $gradableusers = grade_report::get_gradable_users($courseid, $currentgroup);
        // Validate whether the requested user is a valid gradable user in this course. If, not display the user select
        // zero state.
        if (empty($gradableusers) || ($userid && !array_key_exists($userid, $gradableusers))) {
            $userid = null;
        }

        $defaultgradeshowactiveenrol = !empty($CFG->grade_report_showonlyactiveenrol);
        $showonlyactiveenrol = get_user_preferences('grade_report_showonlyactiveenrol', $defaultgradeshowactiveenrol);
        $showonlyactiveenrol = $showonlyactiveenrol || !has_capability('moodle/course:viewsuspendedusers', $context);

        if ($userview == GRADE_REPORT_USER_VIEW_USER) {
            $viewasuser = true;
        } else {
            $viewasuser = false;
        }

        $gui = new graded_users_iterator($course, null, $currentgroup);
        $gui->require_active_enrolment($showonlyactiveenrol);
        $gui->init();

        if (is_null($userid)) { // Zero state.
            $actionbar = new \gradereport_user\output\action_bar($context, $userview, null, $currentgroup);
            // Print header.
            print_grade_page_head(
                $courseid,
                'report',
                'user',
                false,
                false,
                null,
                true,
                null,
                null,
                null,
                $actionbar
            );

            if (empty($gradableusers)) { // There are no available gradable users, display a notification.
                $message = $currentgroup ? get_string('nostudentsingroup') : get_string('nostudentsyet');
                echo $OUTPUT->notification($message, 'warning', false);
            } else { // Otherwise, display the zero state template.
                $report = new gradereport_user\report\user($courseid, $gpr, $context, $USER->id, $viewasuser);
                echo $report->output_report_zerostate();
            }
        } else if ($userid == 0) { // Show all reports.
            // Store the id of the current user item in a session variable which represents the last viewed item.
            $SESSION->gradereport_user["useritem-{$context->id}"] = $userid;

            $actionbar = new \gradereport_user\output\action_bar($context, $userview, 0, $currentgroup);
            print_grade_page_head(
                $courseid,
                'report',
                'user',
                false,
                false,
                null,
                true,
                null,
                null,
                null,
                $actionbar
            );

            while ($userdata = $gui->next_user()) {
                $user = $userdata->user;
                $report = new gradereport_user\report\user($courseid, $gpr, $context, $user->id, $viewasuser);
                $userheading = $gradesrenderer->user_heading($report->user, $courseid, false);

                echo $userheading;

                if ($report->fill_table()) {
                    echo $report->print_table(true);
                }
            }
            $gui->close();
        } else { // Show one user's report.
            // Store the id of the current user item in a session variable which represents the last viewed item.
            $SESSION->gradereport_user["useritem-{$context->id}"] = $userid;

            $report = new gradereport_user\report\user($courseid, $gpr, $context, $userid, $viewasuser);
            $actionbar = new \gradereport_user\output\action_bar($context, $userview, $report->user->id, $currentgroup);

            print_grade_page_head($courseid, 'report', 'user', false, false, false, true, null, null, $report->user, $actionbar);

            if ($currentgroup && !groups_is_member($currentgroup, $userid)) {
                echo $OUTPUT->notification(get_string('groupusernotmember', 'error'));
            } else {
                if ($report->fill_table()) {
                    echo $report->print_table(true);
                }
            }
            $userreportrenderer = $PAGE->get_renderer('gradereport_user');
            // Render the user report (previous/next) navigation in a sticky footer.
            $stickyfooter = new core\output\sticky_footer($userreportrenderer->user_navigation($gui, $userid, $courseid));
            echo $OUTPUT->render($stickyfooter);
        }
        echo $OUTPUT->footer();
        exit;
    }
    // Show report for teacher
    echo $OUTPUT->header();
    // Show student report to teacher
    if (isset($_GET['student_report_id']) && isset($_GET['id'])) {
        $student_id  = $_GET['student_report_id'];
        $courseid = $_GET['id'];
        if ($student_id && $courseid) {
            // Execute the query with the retrieved student ID and course ID
            $recordset = $DB->get_recordset_sql($student_quiz_data_sql, ['student_id' => $student_id, 'course_id' => $courseid]);
            $results = [];
            foreach ($recordset as $record) {
                $results[] = (array) $record;
            }
            $sectionsAndQuizes = [];
            foreach ($results as $record) {
                if (!isset($sectionsAndQuizes[$record['section_name']])) {
                    $sectionsAndQuizes[$record['section_name']] = [];
                }
                if (!in_array($record['quiz_name'], $sectionsAndQuizes[$record['section_name']])) {
                    $sectionsAndQuizes[$record['section_name']][] = $record['quiz_name'];
                }
            }
            $recordset->close();
            if (empty($results)) {
                echo '<div class="alert alert-warning" role="alert">';
                echo 'Student has not performed any quiz in the course.';
                echo '</div>';
                exit;
            }

            $transformedData = transformQuizResults($results);
            try {
                $responseData = is_string($response) ? json_decode($response, true) : $response;
                $rawContent = $responseData['choices'][0]['message']['content'];
                $jsonContent = trim(str_replace(['```json', '```'], '', $rawContent));
                $responseContent = json_decode($jsonContent, true);
                $newData = [
                    'student_id' => json_decode($transformedData, true)['student_id'],
                    'course_name' => json_decode($transformedData, true)['course_name'],
                    'quiz_analysis' => json_decode($transformedData, true)['quiz_analysis'],
                    'section_analysis' => json_decode($transformedData, true)['section_analysis'],
                    'overall_performance' => json_decode($transformedData, true)['overall_performance'],
                    'sectionsAndQuizes' => $sectionsAndQuizes
                ];
                $jsonNewData = json_encode($newData);
                echo generateStudentReport($jsonNewData, $courseid, $student_id);
                exit;
            } catch (Exception $e) {
                echo '<div class="alert alert-danger" role="alert">';
                echo '<strong>Error:</strong> There was an issue . Please try again later.';
                echo '<br><strong>Details:</strong> ' . htmlspecialchars($e->getMessage());
                echo '</div>';
                exit;
            }
        }
    }
    $teacher_id = $USER->id;
    $courseid = $course->id;
    // Get all the contents for a this course.
    $course = get_course($courseid);

    $modinfo = get_fast_modinfo($courseid); // Structured course data

    $sections = [];
    foreach ($modinfo->get_section_info_all() as $section) {
        $section_data = [
            'id' => $section->id,
            'name' => format_string($section->name),
            'quizzes' => []
        ];

        foreach ($modinfo->sections[$section->section] as $cmid) {
            $cm = $modinfo->get_cm($cmid);
            if ($cm->modname == 'quiz') {
                $quiz = $DB->get_record('quiz', ['id' => $cm->instance]);
                $section_data['quizzes'][] = [
                    'cmid' => $cm->id,
                    'quiz_id' => $quiz->id,
                    'name' => format_string($quiz->name),
                    'total_marks' => (float)$quiz->sumgrades,
                    'attempt_count' => 0,
                    'average_score' => 0,
                    'grades' => []
                ];
            }
        }

        $sections[] = $section_data;
    }
    //Query to get all classes of a teacher for a particular course
    // The cohort is resolved separately, from {enrol}, so this query no longer joins
    // cohort_members - that join silently dropped classes whenever the teacher was not
    // himself a member of the class cohort.
    $classes_sql_query = '
        SELECT DISTINCT
            g.id AS groupid,
            g.name AS groupname
        FROM {groups} g
        JOIN {groups_members} gm ON g.id = gm.groupid
        JOIN {role_assignments} ra ON ra.userid = gm.userid
        JOIN {role} r ON r.id = ra.roleid
        WHERE g.courseid = :courseid
        AND ra.userid = :teacherid
        AND r.shortname = :role
    ';

    $classes = $DB->get_records_sql($classes_sql_query, [
        'courseid' => $courseid,
        'teacherid' => $teacher_id,
        'role' => 'teacher'
    ]);

    // Map each class group to the cohort that is actually cohort-synced into it.
    // {enrol} holds the authoritative mapping for the cohort enrolment plugin:
    //   customint1 = cohortid, customint2 = groupid.
    // The previous approach matched cohort names against group names, which broke as soon as
    // Moodle appended a collision suffix to a group name (e.g. "Foo cohort (2)" vs "Foo"), and
    // left an arbitrary teacher cohort in place when no name matched - producing an empty
    // filtered structure and "No content has been enabled for this class."
    // Where a group somehow has more than one cohort synced, prefer the cohort with the most
    // enabled content, mirroring gradereport_user_resolve_course_cohortid() in lib.php.
    $syncrows = $DB->get_records_sql("
        SELECT e.id,
               e.customint2 AS groupid,
               e.customint1 AS cohortid,
               (SELECT COUNT(*)
                  FROM {local_coursegroupmodules} lcgm
                 WHERE lcgm.courseid = e.courseid
                   AND lcgm.groupid = e.customint1) AS enabledcount
          FROM {enrol} e
          JOIN {cohort} c ON c.id = e.customint1
         WHERE e.courseid = :courseid
           AND e.enrol = 'cohort'
           AND e.customint2 > 0
           AND c.visible = 1
      ORDER BY enabledcount DESC, e.customint1 ASC
    ", ['courseid' => $courseid]);

    $cohortbygroup = [];
    foreach ($syncrows as $row) {
        if (!isset($cohortbygroup[$row->groupid])) {
            $cohortbygroup[$row->groupid] = (int) $row->cohortid;
        }
    }

    // Attach the resolved cohort to each class. Null means no cohort is synced to that group.
    foreach ($classes as $groupid => $class) {
        $classes[$groupid]->cohortid = isset($cohortbygroup[$groupid])
            ? $cohortbygroup[$groupid]
            : null;
    }
    
    // Random Bag Test report for one of the teacher's classes: the class average, or one student of the class.
    if (optional_param('fetch', '', PARAM_ALPHANUMEXT) === 'random' && gradereport_user_random_bag_teacher_enabled($courseid)) {
        $groupid = optional_param('class_id', 0, PARAM_INT);
        if (!isset($classes[$groupid])) {
            echo $OUTPUT->notification('Select one of your classes first.', 'notifyerror');
            echo $OUTPUT->footer();
            exit;
        }
        $class_students = gradereport_user_get_class_students($courseid, $groupid);
        $rbstudentid = optional_param('rbstudent', 0, PARAM_INT);
        if ($rbstudentid) {
            if (!isset($class_students[$rbstudentid])) {
                echo $OUTPUT->notification('This student is not in the selected class.', 'notifyerror');
                echo $OUTPUT->footer();
                exit;
            }
            gradereport_user_random_bag_page($courseid, [$rbstudentid => $class_students[$rbstudentid]]);
        } else {
            gradereport_user_random_bag_page($courseid, $class_students, $classes[$groupid]->groupname, ['class_id' => $groupid]);
        }
        exit;
    }

    // Dropdown to select class
    if (!isset($_GET['class_id']) || empty($_GET['id'])): ?>
        <style>
            @keyframes slideDown {
                from {
                    opacity: 0;
                    transform: translateY(-20px);
                }

                to {
                    opacity: 1;
                    transform: translateY(0);
                }
            }

            @keyframes fadeIn {
                from {
                    opacity: 0;
                }

                to {
                    opacity: 1;
                }
            }

            @keyframes pulse {
                0% {
                    transform: scale(1);
                }

                50% {
                    transform: scale(1.02);
                }

                100% {
                    transform: scale(1);
                }
            }

            .class-selection-container {
                max-width: 800px;
                margin: 2rem auto;
                padding: 2rem;
                background: white;
                border-radius: 16px;
                box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
                animation: slideDown 0.6s ease-out;
            }

            .notification-banner {
                background: linear-gradient(135deg, #0D9488 0%, #0891B2 100%);
                border: none;
                padding: 1.25rem;
                margin-bottom: 1.5rem;
                border-radius: 12px;
                display: flex;
                align-items: center;
                gap: 0.75rem;
                animation: fadeIn 0.8s ease-out 0.3s both;
                transition: transform 0.3s ease;
            }

            .notification-banner:hover {
                transform: translateY(-2px);
            }

            .notification-icon {
                color: white;
                font-size: 1.5rem;
            }

            .notification-text {
                color: white;
                font-size: 1rem;
                margin: 0;
                font-weight: 500;
            }

            .class-selection-form {
                background: #F8FAFC;
                padding: 2rem;
                border-radius: 12px;
                border: 2px solid #E2E8F0;
                animation: fadeIn 0.8s ease-out 0.6s both;
                transition: all 0.3s ease;
            }

            .class-selection-form:hover {
                border-color: #0D9488;
                box-shadow: 0 4px 12px rgba(13, 148, 136, 0.1);
            }

            .form-group {
                display: flex;
                align-items: center;
                gap: 1.5rem;
                flex-wrap: wrap;
            }

            .form-label {
                font-weight: 600;
                color: #334155;
                min-width: 120px;
                font-size: 1.1rem;
            }

            .form-select {
                flex: 1;
                min-width: 250px;
                padding: 0.75rem 1rem;
                border: 2px solid #E2E8F0;
                border-radius: 8px;
                background-color: white;
                color: #334155;
                font-size: 1rem;
                transition: all 0.3s ease;
                cursor: pointer;
            }

            .form-select:hover {
                border-color: #0D9488;
            }

            .form-select:focus {
                outline: none;
                border-color: #0D9488;
                box-shadow: 0 0 0 4px rgba(13, 148, 136, 0.1);
            }

            .form-select option {
                padding: 0.75rem;
            }

            .quick-tips {
                margin-top: 2rem;
                padding: 1.5rem;
                background: linear-gradient(to right, #F0FDFA, #ECFEFF);
                border-radius: 12px;
                border: none;
                animation: fadeIn 0.8s ease-out 0.9s both;
                transition: transform 0.3s ease;
            }

            .quick-tips:hover {
                transform: translateY(-2px);
            }

            @media (max-width: 768px) {
                .class-selection-container {
                    margin: 1rem;
                    padding: 1.5rem;
                }

                .form-group {
                    flex-direction: column;
                    align-items: stretch;
                    gap: 0.75rem;
                }

                .form-label {
                    min-width: auto;
                }

                .form-select {
                    min-width: auto;
                }
            }
        </style>

        <div class="class-selection-container">
            <!-- Notification Banner -->
            <div class="notification-banner">
                <i class="fas fa-graduation-cap notification-icon"></i>
                <p class="notification-text">Welcome! Please select a class to view the detailed performance report.</p>
            </div>

            <!-- Selection Form -->
            <form method="get" action="" id="classForm" class="class-selection-form">
                <input type="hidden" name="id" value="<?php echo htmlspecialchars($courseid); ?>">

                <div class="form-group">
                    <label for="class_id" class="form-label">Select Class:</label>
                    <select
                        name="class_id"
                        id="class_id"
                        class="form-select"
                        onchange="document.getElementById('classForm').submit();">
                        <option value="">Choose a class...</option>
                        <?php foreach ($classes as $class): ?>
                            <option value="<?php echo htmlspecialchars($class->groupid); ?>">
                                <?php echo htmlspecialchars($class->groupname); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </form>
        </div>

        <!-- Add Font Awesome if not already included -->
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <?php endif;
    // If class is already selected, show the report
    if (isset($_GET['class_id']) && $_GET['id']) {
        $course_structure =  get_course_structure($courseid);

        if (!isset($classes[$_GET['class_id']])) {
            echo $OUTPUT->notification('That class is not available for your account.', 'notifyproblem');
            echo $OUTPUT->footer();
            exit;
        }

        $selected_class = $classes[$_GET['class_id']];
        $cohortid = $selected_class->cohortid;

        if (empty($cohortid)) {
            echo $OUTPUT->notification('No cohort is synced to this class, so no content can be resolved for it.', 'notifyproblem');
            echo $OUTPUT->footer();
            exit;
        } 

	$teacher_enabled_quiz_sql_query = "
            SELECT 
                cm.id AS cmid,
                m.name AS module_type,
                q.name AS activity_name,
                cm.section,
                cm.visible,
                lcgm.groupid
            FROM {course_modules} cm
            INNER JOIN {local_coursegroupmodules} lcgm ON lcgm.moduleid = cm.id
            INNER JOIN {modules} m ON m.id = cm.module
            INNER JOIN {quiz} q ON q.id = cm.instance
            WHERE lcgm.courseid = :courseid
            AND lcgm.groupid = :cohortid
            AND m.name = 'quiz'
            ORDER BY cm.section;
        ";
        $quiz_enables_by_teacher = $DB->get_records_sql($teacher_enabled_quiz_sql_query, [
            'courseid' => $courseid,
            'cohortid' => $cohortid,
        ]);
        echo '<script>console.log("checking updates other")</script>';

        $sections_enabled_by_teacher_sql_query = "
            SELECT 
                cs.id AS section_id,
                cs.name AS section_name,
                cs.summary,
                cs.sequence,
                cs.visible,
                lct.courseid,
                lct.topic
            FROM 
                mdl_course_sections cs
            INNER JOIN 
                mdl_local_coursegrouptopics lct ON lct.topic = cs.id
            WHERE 
                lct.courseid = :courseid
                AND lct.groupid = :cohortid
            ORDER BY 
                cs.section;
        ";

        $sections_enabled_by_teacher = $DB->get_records_sql($sections_enabled_by_teacher_sql_query, [
            'courseid' => $courseid,
            'cohortid' => $cohortid,
        ]);
        $allowed_section_ids = array_column($sections_enabled_by_teacher, 'section_id');
        $allowed_quiz_cmids = array_column($quiz_enables_by_teacher, 'cmid');
        $filtered_course_structure = filter_course_structure($course_structure, $allowed_section_ids, $allowed_quiz_cmids);

        //Filter sections
        $filtered_sections = [];
        foreach ($sections as $section) {
            // Check if the section ID is allowed
            if (in_array($section['id'], $allowed_section_ids)) {
                $filtered_quizzes = [];

                // Filter quizzes within this section
                foreach ($section['quizzes'] as $quiz) {
                    // Check if the quiz cmid is allowed
                    if (in_array($quiz['cmid'], $allowed_quiz_cmids)) {
                        $filtered_quizzes[] = $quiz;
                    }
                }

                // Only keep the section if it has quizzes (or if empty sections are allowed)
                if (!empty($filtered_quizzes)) {
                    $section['quizzes'] = $filtered_quizzes;
                    $filtered_sections[] = $section;
                }
            }
        }

        // Replace the original sections with the filtered ones
        $sections = $filtered_sections;
        echo '<div style="margin: 20px 0;">';
        echo '<form method="get" action="" id="classForm">';
        echo '<input type="hidden" name="id" value="' . $courseid . '">';
        echo '<label for="class_id">Select Class:</label>';
        echo '<select name="class_id" id="class_id" class="form-control" style="display: inline-block; width: auto; margin-left: 10px;" onchange="document.getElementById(\'classForm\').submit();">';
        echo '<option value="">Select</option>';
        foreach ($classes as $class) {
            $selected = ($class->groupid == $_GET['class_id']) ? 'selected' : '';
            echo '<option value="' . $class->groupid . '" ' . $selected . '>' . $class->groupname . '</option>';
        }
        echo '</select>';
        if (gradereport_user_random_bag_teacher_enabled($courseid)) {
            echo '<button type="submit" name="fetch" value="random" class="btn btn-secondary" style="margin-left: 10px;">Generate Random Report</button>';
        }
        echo '</form>';
        echo '</div>';


        if (empty($sections)) {
            echo $OUTPUT->notification('No content has been enabled for this class.', 'notifyinfo');
            echo $OUTPUT->footer();
            exit;
        }

        // Query to get all the students in the class.
        $excluded_roles = "('teacher', 'editingteacher', 'hod_course', 'administrator', 'manager')";                                                 
                                          
        $students_sql_query = "                                                       
            SELECT DISTINCT
                u.id AS studentid,
                u.firstname,
                u.lastname,
                u.email,
                u.username
            FROM {user} u
            JOIN {groups_members} gm ON gm.userid = u.id
            JOIN {groups} g ON g.id = gm.groupid
            JOIN {role_assignments} ra ON ra.userid = u.id
            JOIN {context} ctx ON ctx.id = ra.contextid
            JOIN {role} r ON r.id = ra.roleid
            WHERE g.id = :groupid
            AND g.courseid = :courseid1
            AND r.archetype = 'student'
            AND ctx.contextlevel = 50
            AND ctx.instanceid = :courseid2
            AND NOT EXISTS (
                SELECT 1
                FROM {role_assignments} ra2
                JOIN {role} r2 ON r2.id = ra2.roleid
                WHERE ra2.userid = u.id
                AND ra2.contextid = ctx.id
                AND r2.archetype != 'student'
            )
            ORDER BY u.id                                                           
        ";

        $params = [
            'groupid' => $_GET['class_id'],
            'courseid1' => $courseid,
            'courseid2' => $courseid,                                                 
            'courseid3' => $courseid,
        ];

        $recordset = $DB->get_records_sql($students_sql_query, $params);
        $students = [];
        foreach ($recordset as $record) {
            $students[] = (array) $record;
        }

        // Get all students quiz data
        // Prepare a list of student IDs for the query
        $student_ids = array_column($students, 'studentid');

        // Build the SQL query to fetch data for all students in one go
        $sql = "
            SELECT 
                u.id AS student_id,
                u.firstname AS student_firstname,
                u.lastname AS student_lastname,
                c.id AS course_id,
                c.fullname AS course_name,
                cs.name AS section_name,
                cs.id AS section_id,
                qz.id AS quiz_id,
                qz.name AS quiz_name,
                qa.attempt AS attempt_number,
                qatt.slot AS question_position,
                qatt.questionid AS question_id,
                q.questiontext AS question_text,
                qatt.maxmark AS max_question_grade,
                (qattstep.fraction * qatt.maxmark) AS grade_achieved_for_question,
                qattstep.state AS question_state,
                qattstep.fraction AS answer_fraction,
                ti.itemid AS tagged_question_id,
                tg.name AS tag_name
            FROM mdl_user u
            JOIN mdl_user_enrolments ue ON ue.userid = u.id
            JOIN mdl_enrol e ON e.id = ue.enrolid
            JOIN mdl_course c ON c.id = e.courseid
            JOIN mdl_course_sections cs ON cs.course = c.id
            JOIN mdl_course_modules cm ON cm.course = c.id AND cm.section = cs.id
            JOIN mdl_modules m ON m.id = cm.module AND m.name = 'quiz'
            JOIN mdl_quiz qz ON qz.id = cm.instance
            JOIN (
                SELECT 
                    qa1.userid, 
                    qa1.quiz, 
                    MAX(qa1.attempt) AS latest_attempt
                FROM mdl_quiz_attempts qa1
                GROUP BY qa1.userid, qa1.quiz
            ) AS latest_attempts ON latest_attempts.userid = u.id AND latest_attempts.quiz = qz.id
            JOIN mdl_quiz_attempts qa ON qa.userid = latest_attempts.userid 
                AND qa.quiz = latest_attempts.quiz 
                AND qa.attempt = latest_attempts.latest_attempt
            JOIN mdl_question_usages qu ON qu.id = qa.uniqueid
            JOIN mdl_question_attempts qatt ON qatt.questionusageid = qu.id
            JOIN mdl_question q ON q.id = qatt.questionid
            JOIN (
                SELECT 
                    qattstep1.questionattemptid, 
                    MAX(qattstep1.id) AS final_step_id
                FROM mdl_question_attempt_steps qattstep1
                WHERE qattstep1.state IN ('graded', 'gradedwrong', 'gradedright', 'gradedpartial')
                GROUP BY qattstep1.questionattemptid
            ) AS final_steps ON final_steps.questionattemptid = qatt.id
            JOIN mdl_question_attempt_steps qattstep ON qattstep.id = final_steps.final_step_id
            LEFT JOIN mdl_tag_instance ti ON ti.itemid = qatt.questionid AND ti.itemtype = 'question'
            LEFT JOIN mdl_tag tg ON tg.id = ti.tagid
            WHERE u.id IN (" . implode(',', $student_ids) . ")
                AND c.id = :course_id
            ORDER BY 
                u.id,
                cs.id, 
                c.id, 
                qz.id, 
                qa.attempt, 
                qatt.slot
        ";

        // Execute the query with parameters
        $params = ['course_id' => $courseid];
        $recordset = $DB->get_recordset_sql($sql, $params);

        // Organize the data by student ID
        $allStudentQuizData = [];
        foreach ($recordset as $record) {
            $student_id = $record->student_id;
            $allStudentQuizData[$student_id][] = (array) $record;
        }
        $recordset->close();

        // Notify if no quiz attempts were found
        if (empty($allStudentQuizData)) {
            echo $OUTPUT->notification('No quiz attempts found for any students in this class.', 'notifywarning');
        }

        // // Ensure filtered_course_structure is in the correct format
        // function flattenCourseStructure($course_structure)
        // {
        //     $flat_structure = [];

        //     // Process sections array
        //     if (isset($course_structure['sections'])) {
        //         foreach ($course_structure['sections'] as $section) {
        //             // Add sections with quizzes
        //             if (!empty($section['quizzes'])) {
        //                 $flat_structure[] = [
        //                     'id' => $section['id'],
        //                     'name' => $section['name'],
        //                     'quizzes' => $section['quizzes']
        //                 ];
        //             }

        //             // Add child sections with quizzes
        //             if (!empty($section['children'])) {
        //                 foreach ($section['children'] as $child) {
        //                     if (!empty($child['quizzes'])) {
        //                         $flat_structure[] = [
        //                             'id' => $child['id'],
        //                             'name' => $child['name'],
        //                             'quizzes' => $child['quizzes']
        //                         ];
        //                     }

        //                     // Process deeper nesting if needed
        //                     if (!empty($child['children'])) {
        //                         foreach ($child['children'] as $grandchild) {
        //                             if (!empty($grandchild['quizzes'])) {
        //                                 $flat_structure[] = [
        //                                     'id' => $grandchild['id'],
        //                                     'name' => $grandchild['name'],
        //                                     'quizzes' => $grandchild['quizzes']
        //                                 ];
        //                             }
        //                         }
        //                     }
        //                 }
        //             }
        //         }
        //     }

        //     echo '<script>console.log("Flattened structure:", ' . json_encode($flat_structure) . ')</script>';
        //     return $flat_structure;
        // }

        // // If filtered_course_structure is not already flattened, do it now
        // if (isset($filtered_course_structure['sections'])) {
        //     $filtered_course_structure = flattenCourseStructure($filtered_course_structure);
        // }

        // // Check if it's still empty
        // if (empty($filtered_course_structure)) {
        //     echo '<script>console.error("filtered_course_structure is still empty after processing")</script>';
        // }

        // /**
        //  * Generate dummy data for allStudentQuizData and students
        //  * 
        //  * @param array $filtered_course_structure The flat course structure with sections and quizzes
        //  * @return array Array containing allStudentQuizData and students
        //  */
        // function generateDummyData($filtered_course_structure)
        // {
        //     // Debug output to check the input
        //     echo '<script>console.log("Structure length:", ' . count($filtered_course_structure) . ')</script>';

        //     // Create dummy student data
        //     $students = [
        //         [
        //             "studentid" => "112",
        //             "firstname" => "A Student",
        //             "lastname" => "1",
        //             "email" => "astudent1@gmail.com",
        //             "username" => "astudent1"
        //         ],
        //         [
        //             "studentid" => "133",
        //             "firstname" => "A Student",
        //             "lastname" => "8",
        //             "email" => "astudent8@gmail.com",
        //             "username" => "as8"
        //         ],
        //         [
        //             "studentid" => "131",
        //             "firstname" => "A Student",
        //             "lastname" => "6",
        //             "email" => "astudent6@gmail.com",
        //             "username" => "astudent6"
        //         ],
        //         [
        //             "studentid" => "145",
        //             "firstname" => "A Student",
        //             "lastname" => "9",
        //             "email" => "astudent9@gmail.com",
        //             "username" => "astudent9"
        //         ],
        //         [
        //             "studentid" => "158",
        //             "firstname" => "A Student",
        //             "lastname" => "12",
        //             "email" => "astudent12@gmail.com",
        //             "username" => "astudent12"
        //         ]
        //     ];

        //     // Initialize allStudentQuizData
        //     $allStudentQuizData = [];

        //     // Check if filtered_course_structure is empty or not an array
        //     if (empty($filtered_course_structure) || !is_array($filtered_course_structure)) {
        //         echo '<script>console.error("filtered_course_structure is empty or not an array")</script>';

        //         // Provide a fallback simple structure to generate some data
        //         $filtered_course_structure = [
        //             [
        //                 "id" => "749",
        //                 "name" => "Integers",
        //                 "quizzes" => [
        //                     [
        //                         "cmid" => "4987",
        //                         "quiz_id" => "2860",
        //                         "name" => "Identification of Positive and Negative Numbers",
        //                         "total_marks" => 3
        //                     ]
        //                 ]
        //             ],
        //             [
        //                 "id" => "770",
        //                 "name" => "Fundamental Concepts of Geometry",
        //                 "quizzes" => [
        //                     [
        //                         "cmid" => "5366",
        //                         "quiz_id" => "3081",
        //                         "name" => "Dot or Line",
        //                         "total_marks" => 3
        //                     ]
        //                 ]
        //             ]
        //         ];
        //     }

        //     // For each student
        //     foreach ($students as $student) {
        //         $studentid = $student["studentid"];
        //         $allStudentQuizData[$studentid] = [];

        //         // For each section
        //         foreach ($filtered_course_structure as $section) {
        //             // Debug output
        //             echo '<script>console.log("Processing section:", ' . json_encode($section) . ')</script>';

        //             if (!isset($section["id"]) || !isset($section["name"]) || !isset($section["quizzes"])) {
        //                 echo '<script>console.error("Section is missing required fields:", ' . json_encode($section) . ')</script>';
        //                 continue;
        //             }

        //             $section_id = $section["id"];
        //             $section_name = $section["name"];

        //             // For each quiz in the section
        //             if (empty($section["quizzes"])) {
        //                 echo '<script>console.log("No quizzes in section ' . $section_name . '")</script>';
        //                 continue;
        //             }

        //             foreach ($section["quizzes"] as $quiz) {
        //                 // Debug output
        //                 echo '<script>console.log("Processing quiz:", ' . json_encode($quiz) . ')</script>';

        //                 if (!isset($quiz["quiz_id"]) || !isset($quiz["name"])) {
        //                     echo '<script>console.error("Quiz is missing required fields:", ' . json_encode($quiz) . ')</script>';
        //                     continue;
        //                 }

        //                 $quiz_id = $quiz["quiz_id"];
        //                 $quiz_name = $quiz["name"];
        //                 $total_marks = isset($quiz["total_marks"]) ? $quiz["total_marks"] : 3;

        //                 // Generate random number of questions (1-3) for each quiz attempt
        //                 $num_questions = rand(1, 3);
        //                 $attempt_number = rand(1, 2); // Some quizzes have multiple attempts

        //                 for ($i = 1; $i <= $num_questions; $i++) {
        //                     // Generate a unique question ID
        //                     $question_id = "1" . rand(10000, 99999);

        //                     // Determine if answer is correct (70% chance of being correct)
        //                     $is_correct = (rand(1, 10) <= 7);

        //                     // Set state based on correctness
        //                     if ($is_correct) {
        //                         $question_state = "gradedright";
        //                         $answer_fraction = "1.0000000";
        //                         $grade_achieved = "1.00000000000000";
        //                     } else {
        //                         // Sometimes have partial credit
        //                         if (rand(1, 10) <= 3) {
        //                             $question_state = "gradedpartial";
        //                             $partial_values = ["0.2500000", "0.3333333", "0.5000000"];
        //                             $answer_fraction = $partial_values[array_rand($partial_values)];
        //                             $grade_achieved = str_pad(substr($answer_fraction, 0, -5), 17, '0', STR_PAD_RIGHT);
        //                         } else {
        //                             $question_state = "gradedwrong";
        //                             $answer_fraction = "0.0000000";
        //                             $grade_achieved = "0.00000000000000";
        //                         }
        //                     }

        //                     // Generate sample question text - sometimes with HTML
        //                     $html_formats = [
        //                         "<p>Sample question for " . $quiz_name . " question " . $i . "</p>",
        //                         "<div class=\"page\" title=\"Page 1\">\r\n<div class=\"layoutArea\">\r\n<div class=\"column\">\r\n<p>Question for " . $quiz_name . "</p>\r\n</div>\r\n</div>\r\n</div>",
        //                         "<p class=\"MsoListParagraph\" style=\"text-indent: -.25in; mso-list: l0 level1 lfo1;\"><!-- [if !supportLists]--><span style=\"font-family: 'Cambria',serif; mso-fareast-font-family: Cambria; mso-bidi-font-family: Cambria; mso-ansi-language: EN-US;\"><span style=\"mso-list: Ignore;\">1.<span style=\"font: 7.0pt 'Times New Roman';\">     </span></span></span><!--[endif]--><span style=\"font-family: 'Cambria',serif; mso-ansi-language: EN-US;\">Question for " . $quiz_name . "</span></p>",
        //                         "<p><img src=\"@@PLUGINFILE@@/image.png\" width=\"500\" height=\"50\"></p>"
        //                     ];

        //                     // Create question data
        //                     $question_data = [
        //                         "student_id" => $studentid,
        //                         "student_firstname" => $student["firstname"],
        //                         "student_lastname" => $student["lastname"],
        //                         "course_id" => "93",
        //                         "course_name" => "ICSE Gr 7 - Final",
        //                         "section_name" => $section_name,
        //                         "section_id" => $section_id,
        //                         "quiz_id" => $quiz_id,
        //                         "quiz_name" => $quiz_name,
        //                         "attempt_number" => $attempt_number,
        //                         "question_position" => $i,
        //                         "question_id" => $question_id,
        //                         "question_text" => $html_formats[array_rand($html_formats)],
        //                         "max_question_grade" => "1.0000000",
        //                         "grade_achieved_for_question" => $grade_achieved,
        //                         "question_state" => $question_state,
        //                         "answer_fraction" => $answer_fraction,
        //                         "tagged_question_id" => $question_id,
        //                         "tag_name" => strtolower(substr(preg_replace('/[^a-zA-Z0-9\s]/', '', $quiz_name), 0, 30))
        //                     ];

        //                     // Add data to the student's quiz data
        //                     $allStudentQuizData[$studentid][] = $question_data;

        //                     // Add duplicate entries (as seen in the example data) with 30% probability
        //                     if (rand(1, 10) <= 3) {
        //                         $allStudentQuizData[$studentid][] = $question_data;
        //                     }
        //                 }
        //             }
        //         }
        //     }

        //     // Debug output to check the output
        //     echo '<script>console.log("Generated data for student count:", ' . count($allStudentQuizData) . ')</script>';
        //     foreach ($allStudentQuizData as $studentid => $data) {
        //         echo '<script>console.log("Student ' . $studentid . ' has entries:", ' . count($data) . ')</script>';
        //     }

        //     return [
        //         'allStudentQuizData' => $allStudentQuizData,
        //         'students' => $students
        //     ];
        // }
        // Example usage:
        // $dummy_data = generateDummyData($filtered_course_structure);
        // $allStudentQuizDataa = $dummy_data['allStudentQuizData'];
        // $studentss = $dummy_data['students'];

        // echo '<script>console.log("This is studentss",' . json_encode($studentss) . ')</script>';
        // echo '<script>console.log("This is allStudentQuizDataa",' . json_encode($allStudentQuizDataa) . ')</script>';

        // $agg_report_data = generateAggregateReportExtended($allStudentQuizData, $filtered_structure, $students);
        // echo '<script>console.log("This is filtered_course_structuree",' . json_encode($filtered_course_structuree) . ')</script>';

        $agg_report_data = generateDataForTeacherReport($allStudentQuizData, $filtered_course_structure, $students);
                echo '<script>console.log("This is agg_report_data",' . json_encode($agg_report_data) . ')</script>';

        echo renderTeacherReport($agg_report_data);

        // First output the required CSS
        echo '<style>
                :root {
                    --primary-color: #0D9488;
                    --text-color: #334155;
                    --text-light: #64748B;
                    --background-color: #F8FAFC;
                    --border-color: #E2E8F0;
                    --status-error: #FEE2E2;
                    --status-error-text: #EF4444;
                    --status-success: #DCFCE7;
                    --status-success-text: #16A34A;
                    --status-warning: #FEF3C7;
                    --status-warning-text: #D97706;
                }
                * {
                    margin: 0;
                    padding: 0;
                    box-sizing: border-box;
                }
                .students-container {
                    max-width: 1200px;
                    margin: 1rem auto;
                    padding: 0 1rem;
                    display: grid;
                    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
                    gap: 1rem;
                }
                .student-item {
                    display: flex;
                    align-items: center;
                    padding: 0.75rem;
                    border: 1px solid var(--border-color);
                    border-radius: 8px;
                    transition: all 0.2s ease;
                    cursor: pointer;
                    background: white;
                }

                .student-item:hover {
                    transform: translateY(-2px);
                    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
                    background: #f8fafc;
                    border-color: var(--primary-color);
                }

                .student-avatar {
                    width: 36px;
                    height: 36px;
                    border-radius: 50%;
                    background: var(--primary-color);
                    color: white;
                    font-weight: 600;
                    font-size: 0.9rem;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    text-transform: uppercase;
                    margin-right: 12px;
                    flex-shrink: 0;
                }

                .student-info {
                    min-width: 0; /* Helps with text overflow */
                }

                .student-info h3 {
                    font-size: 0.95rem;
                    font-weight: 600;
                    color: var(--text-color);
                    margin: 0;
                    white-space: nowrap;
                    overflow: hidden;
                    text-overflow: ellipsis;
                }

                .student-info p {
                    font-size: 0.8rem;
                    color: var(--text-light);
                    margin: 2px 0;
                    white-space: nowrap;
                    overflow: hidden;
                    text-overflow: ellipsis;
                }

                .students-list h2 {
                    grid-column: 1 / -1;
                    color: var(--text-color);
                    font-size: 1.25rem;
                    margin-bottom: 1rem;
                    padding: 0 0.5rem;
                }

                /* Add responsive padding */
                @media (max-width: 768px) {
                    .students-container {
                        grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
                        padding: 0 0.5rem;
                    }
                }
                </style>';

        // The PHP part remains mostly the same
        echo '<div class="students-list slide-in">';
        echo '<div class="students-container">';
        echo '<h2>Student wise performance</h2>';

        foreach ($students as $student) {
            $initials = strtoupper(substr($student['firstname'], 0, 1) . substr($student['lastname'], 0, 1));

            echo '<div class="student-item">';
            echo '<form method="get" action="" target="_blank">';
            echo '<input type="hidden" name="id" value="' . $courseid . '">';
            echo '<input type="hidden" name="student_report_id" value="' . $student['studentid'] . '">';
            echo '<div class="student-avatar" onclick="this.parentNode.submit();">' . $initials . '</div>';
            echo '<div class="student-info" onclick="this.parentNode.submit();">';
            echo '<h3>' . htmlspecialchars($student['firstname'] . ' ' . $student['lastname']) . '</h3>';
            echo '<p>' . htmlspecialchars($student['email']) . '</p>';
            echo '<p>' . htmlspecialchars($student['username']) . '</p>';
            echo '</div>';
            echo '</form>';
            echo '</div>';
        }

        echo '</div>'; // End students-container
        echo '</div>'; // End students-list
    }
} else {
    // Students will see just their own report.
    // Create a report instance.
    $report = new gradereport_user\report\user($courseid, $gpr, $context, $userid ?? $USER->id);

    // Print the page.
    print_grade_page_head($courseid, 'report', 'user', false, false, false, true, null, null, $report->user);

    //Generate Ai INsignts Button
    if (!isset($_GET['fetch']) || !$_GET['fetch'] === '1' || empty($_GET['id'])) {
        echo '<div style="margin: 20px 0;">';
        echo '<form method="get" action="">';
        echo '<input type="hidden" name="id" value="' . $courseid . '">'; // Pass the course ID
        echo '<button type="submit" name="fetch" value="1" class="btn btn-primary">Generate AI Insights</button>';
        echo '<button type="submit" name="fetch" value="past-year" class="btn btn-secondary" style="margin-left: 10px;">Generate Past Year Paper Report</button>';
        echo '<button type="submit" name="fetch" value="random" class="btn btn-secondary" style="margin-left: 10px;">Generate Random Report</button>';
        if (strpos($report->course->fullname, 'Olympiad') == false) {
            echo '<button type="submit" name="fetch" value="olympiad" class="btn btn-secondary" style="margin-left: 10px;">Generate Olympiad Report</button>';
        }
        echo '</form>';
        echo '</div>';
    }

    // Only call the API if the fetch parameter is explicitly set AI Insights
    if (isset($_GET['fetch']) && $_GET['fetch'] === '1' && !empty($_GET['id'])) {
        $student_id = $USER->id;
        // Attempt to retrieve the AI-generated report for the student from DB
        $studentReportDetails = get_user_ai_report($student_id, $courseid);
        //Check is this request is to generate report again(after lockdown of 1 week)
        $generateNewReport = isset($_GET['generate']) && $_GET['generate'] === '1';
        if ($generateNewReport) {
            if (can_generate_report($student_id, $courseid)) {
                if ($student_id && $courseid) {
                    // Execute the query with the retrieved student ID and course ID
                    $recordset = $DB->get_recordset_sql($student_quiz_data_sql, ['student_id' => $student_id, 'course_id' => $courseid]);
                    $results = [];
                    foreach ($recordset as $record) {
                        $results[] = (array) $record;
                    }
                    $sectionsAndQuizes = [];
                    foreach ($results as $record) {
                        if (!isset($sectionsAndQuizes[$record['section_name']])) {
                            $sectionsAndQuizes[$record['section_name']] = [];
                        }
                        if (!in_array($record['quiz_name'], $sectionsAndQuizes[$record['section_name']])) {
                            $sectionsAndQuizes[$record['section_name']][] = $record['quiz_name'];
                        }
                    }
                    $recordset->close();
                    if (empty($results)) {
                        echo '<div class="alert alert-warning" role="alert">';
                        echo 'You have not performed any quiz in the course. Please complete a quiz to get detailed AI generated report.';
                        echo '</div>';

                        if ($report->fill_table()) {
                            echo $report->print_table(true);
                        }
                        exit;
                    }
                    $transformedData = transformQuizResults($results);
                    try {
                        $response = $openai->chat()->create([
                            'model' => 'gpt-4o-mini',
                            'messages' => [
                                [
                                    'role' => 'system',
                                    'content' => 'You are a data analysis expert specializing in educational performance metrics. Your task is to analyze student quiz performance data and return a structured JSON response.'
                                ],
                                [
                                    'role' => 'user',
                                    'content' => 'Analyze the following student quiz performance dataset and generate a JSON response with the following strict structure:
                                   
                                                   {
                                                     "performance_summary": {
                                                       "key_insights": "string",
                                                       "areas_of_improvement": "string"
                                                     }
                                                   }
                                   
                                                   ### IMPORTANT INSTRUCTIONS ###
                                                   1. **STRICTLY FOLLOW THE PROVIDED STRUCTURE**: The JSON response must exactly match the keys and structure provided above. No extra keys, no omissions, and no key name deviations are allowed.
                                                   2. Give detailed analysis using the below data for the respective keys of json.
                                                   3. Use the following dataset for analysis:
                                                      ' . $transformedData . '
                                   
                                                   Respond only with the JSON response in the specified structure. Do not provide any explanation, extra text, or deviations from the required structure.'
                                ]
                            ]
                        ]);
                        // Parse the OpenAI response
                        $responseData = is_string($response) ? json_decode($response, true) : $response;
                        $rawContent = $responseData['choices'][0]['message']['content'];
                        $jsonContent = trim(str_replace(['```json', '```'], '', $rawContent));
                        $responseContent = json_decode($jsonContent, true);

                        echo '<script>';
                        echo 'console.log("This is responseContent: ", ' . json_encode($responseContent) . ');';
                        echo '</script>';

                        $newData = [
                            'student_id' => json_decode($transformedData, true)['student_id'],
                            'course_name' => json_decode($transformedData, true)['course_name'],
                            'quiz_analysis' => json_decode($transformedData, true)['quiz_analysis'],
                            'section_analysis' => json_decode($transformedData, true)['section_analysis'],
                            'overall_performance' => json_decode($transformedData, true)['overall_performance'],
                            'performance_summary' => $responseContent['performance_summary'],
                            'sectionsAndQuizes' => $sectionsAndQuizes
                        ];
                        $jsonNewData = json_encode($newData);
                        // Extract necessary data for database entry
                        $userid = $USER->id;
                        $contextid = context_system::instance()->id;
                        $tokensUsage = $responseData['usage'];

                        // Create a new AI-generated report entry in the database
                        $reportId = create_user_ai_report($userid, $courseid, $jsonNewData, $tokensUsage);
                        if ($reportId === false) {
                            debugging('Failed to create AI report for user ID: ' . $userid, DEBUG_NORMAL);
                        }
                        echo generateStudentReport($jsonNewData, $courseid, $student_id);                        exit;
                    } catch (Exception $e) {
                        echo '<div class="alert alert-danger" role="alert">';
                        echo '<strong>Error:</strong> There was an issue processing the response. Please try again later.';
                        echo '<br><strong>Details:</strong> ' . htmlspecialchars($e->getMessage());
                        echo '</div>';
                        exit;
                    }
                }
            } else {
                $recordset = $DB->get_recordset_sql($student_quiz_data_sql, ['student_id' => $student_id, 'course_id' => $courseid]);
                $results = [];
                foreach ($recordset as $record) {
                    $results[] = (array) $record;
                }
                $sectionsAndQuizes = [];
                foreach ($results as $record) {
                    if (!isset($sectionsAndQuizes[$record['section_name']])) {
                        $sectionsAndQuizes[$record['section_name']] = [];
                    }
                    if (!in_array($record['quiz_name'], $sectionsAndQuizes[$record['section_name']])) {
                        $sectionsAndQuizes[$record['section_name']][] = $record['quiz_name'];
                    }
                }
                $recordset->close();
                if (empty($results)) {
                    echo '<div class="alert alert-warning" role="alert">';
                    echo 'You have not performed any quiz in the course. Please complete a quiz to get detailed AI generated report.';
                    echo '</div>';

                    if ($report->fill_table()) {
                        echo $report->print_table(true);
                    }
                    exit;
                }
                $transformedData = transformQuizResults($results);
                $newData = [
                    'student_id' => json_decode($transformedData, true)['student_id'],
                    'course_name' => json_decode($transformedData, true)['course_name'],
                    'quiz_analysis' => json_decode($transformedData, true)['quiz_analysis'],
                    'section_analysis' => json_decode($transformedData, true)['section_analysis'],
                    'overall_performance' => json_decode($transformedData, true)['overall_performance'],
                    'performance_summary' => $studentReportDetails['ai_report_data']['performance_summary'],
                    'sectionsAndQuizes' => $sectionsAndQuizes
                ];
                $jsonNewData = json_encode($newData);
                $currentDate = new DateTime();
                $reportDate = new DateTime($studentReportDetails[0]->created_at);
                $interval = $currentDate->diff($reportDate);
                echo '<div class="alert alert-warning" role="alert">';
                echo 'You have already generated a report recently. Please wait until the cooldown period is over.';
                echo '</div>';
                echo '<div class="report-date-heading" style="font-size: small; text-align: center; margin: 20px 0;">';
                echo '<h2 style="font-size: medium; color: #333;">Report Date: ' . $reportDate->format('F j, Y') . '</h2>';
                $daysLeft = 7 - $interval->days;
                echo '<p style="font-size: small; color: #666;">Note: You can generate the next report in ' . $daysLeft . ' days.</p>';
                echo '</div>';
                echo generateStudentReport($jsonNewData, $courseid, $student_id);
                exit;
            }
        }
        if ($studentReportDetails) {
            $currentDate = new DateTime();
            $reportDate = new DateTime($studentReportDetails['created_at']);
            $interval = $currentDate->diff($reportDate);
            if ($interval->days >= 7) {
                // Allow to create a new report
                $recordset = $DB->get_recordset_sql($student_quiz_data_sql, ['student_id' => $student_id, 'course_id' => $courseid]);
                $results = [];
                foreach ($recordset as $record) {
                    $results[] = (array) $record;
                }
                $sectionsAndQuizes = [];
                foreach ($results as $record) {
                    if (!isset($sectionsAndQuizes[$record['section_name']])) {
                        $sectionsAndQuizes[$record['section_name']] = [];
                    }
                    if (!in_array($record['quiz_name'], $sectionsAndQuizes[$record['section_name']])) {
                        $sectionsAndQuizes[$record['section_name']][] = $record['quiz_name'];
                    }
                }
                $recordset->close();
                if (empty($results)) {
                    echo '<div class="alert alert-warning" role="alert">';
                    echo 'You have not performed any quiz in the course. Please complete a quiz to get detailed AI generated report.';
                    echo '</div>';

                    if ($report->fill_table()) {
                        echo $report->print_table(true);
                    }
                    exit;
                }
                $transformedData = transformQuizResults($results);
                $newData = [
                    'student_id' => json_decode($transformedData, true)['student_id'],
                    'course_name' => json_decode($transformedData, true)['course_name'],
                    'quiz_analysis' => json_decode($transformedData, true)['quiz_analysis'],
                    'section_analysis' => json_decode($transformedData, true)['section_analysis'],
                    'overall_performance' => json_decode($transformedData, true)['overall_performance'],
                    'performance_summary' => $studentReportDetails['ai_report_data']['performance_summary'],
                    'sectionsAndQuizes' => $sectionsAndQuizes
                ];
                $jsonNewData = json_encode($newData);

                echo '<div class="alert alert-info" role="alert" style="display: flex; align-items: center; justify-content: space-between; padding: 15px; border-radius: 5px; background-color: #d9edf7; color: #31708f;">';
                echo '<span style="font-size: 16px; font-weight: bold;">A week has passed. You can create a new report.</span>';
                echo '<button onclick="location.href=\'?fetch=1&id=' . $courseid . '&generate=1\'" class="btn btn-success" style="padding: 8px 16px; font-size: 14px; border-radius: 5px; color: white; border: none; cursor: pointer; transition: background-color 0.3s ease;">Create New Report</button>';
                echo '</div>';
                echo '<div class="report-date-heading" style="font-size: small; text-align: center; margin: 20px 0;">';
                echo '<h2 style="font-size: medium; color: #333;">Report Date: ' . $reportDate->format('F j, Y') . '</h2>';
                echo '</div>';
                echo generateStudentReport($jsonNewData, $courseid, $student_id);
            } else {
                // Show old report
                $recordset = $DB->get_recordset_sql($student_quiz_data_sql, ['student_id' => $student_id, 'course_id' => $courseid]);
                $results = [];
                foreach ($recordset as $record) {
                    $results[] = (array) $record;
                }
                $sectionsAndQuizes = [];
                foreach ($results as $record) {
                    if (!isset($sectionsAndQuizes[$record['section_name']])) {
                        $sectionsAndQuizes[$record['section_name']] = [];
                    }
                    if (!in_array($record['quiz_name'], $sectionsAndQuizes[$record['section_name']])) {
                        $sectionsAndQuizes[$record['section_name']][] = $record['quiz_name'];
                    }
                }
                $recordset->close();
                if (empty($results)) {
                    echo '<div class="alert alert-warning" role="alert">';
                    echo 'You have not performed any quiz in the course. Please complete a quiz to get detailed AI generated report.';
                    echo '</div>';

                    if ($report->fill_table()) {
                        echo $report->print_table(true);
                    }
                    exit;
                }
                $transformedData = transformQuizResults($results);
                $newData = [
                    'student_id' => json_decode($transformedData, true)['student_id'],
                    'course_name' => json_decode($transformedData, true)['course_name'],
                    'quiz_analysis' => json_decode($transformedData, true)['quiz_analysis'],
                    'section_analysis' => json_decode($transformedData, true)['section_analysis'],
                    'overall_performance' => json_decode($transformedData, true)['overall_performance'],
                    'performance_summary' => $studentReportDetails['ai_report_data']['performance_summary'],
                    'sectionsAndQuizes' => $sectionsAndQuizes
                ];
                $jsonNewData = json_encode($newData);
                echo '<div class="report-date-heading" style="font-size: small; text-align: center; margin: 20px 0;">';
                echo '<h2 style="font-size: medium; color: #333;">Report Date: ' . $reportDate->format('F j, Y') . '</h2>';
                $daysLeft = 7 - $interval->days;
                echo '<p style="font-size: small; color: #666;">Note: You can generate the next report in ' . $daysLeft . ' days.</p>';
                echo '</div>';
                echo generateStudentReport($jsonNewData, $courseid, $student_id);
            }
            exit;
        }
        // Debugging log for troubleshooting
        if ($student_id && $courseid) {
            // Execute the query with the retrieved student ID and course ID
            $recordset = $DB->get_recordset_sql($student_quiz_data_sql, ['student_id' => $student_id, 'course_id' => $courseid]);
            $results = [];
            foreach ($recordset as $record) {
                $results[] = (array) $record;
            }
            $sectionsAndQuizes = [];
            foreach ($results as $record) {
                if (!isset($sectionsAndQuizes[$record['section_name']])) {
                    $sectionsAndQuizes[$record['section_name']] = [];
                }
                if (!in_array($record['quiz_name'], $sectionsAndQuizes[$record['section_name']])) {
                    $sectionsAndQuizes[$record['section_name']][] = $record['quiz_name'];
                }
            }
            $recordset->close();
            if (empty($results)) {
                echo '<div class="alert alert-warning" role="alert">';
                echo 'You have not performed any quiz in the course. Please complete a quiz to get detailed AI generated report.';
                echo '</div>';

                if ($report->fill_table()) {
                    echo $report->print_table(true);
                }
                exit;
            }
            $transformedData = transformQuizResults($results);
            try {
                $response = $openai->chat()->create([
                    'model' => 'gpt-4o-mini',
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => 'You are a data analysis expert specializing in educational performance metrics. Your task is to analyze student quiz performance data and return a structured JSON response.'
                        ],
                        [
                            'role' => 'user',
                            'content' => 'Analyze the following student quiz performance dataset and generate a JSON response with the following strict structure:
                                                {
                                                  "performance_summary": {
                                                    "key_insights": "string",
                                                    "areas_of_improvement": "string"
                                                  }
                                                }
                                
                                                ### IMPORTANT INSTRUCTIONS ###
                                                1. **STRICTLY FOLLOW THE PROVIDED STRUCTURE**: The JSON response must exactly match the keys and structure provided above. No extra keys, no omissions, and no key name deviations are allowed.
                                                2. Give detailed analysis using the below data for the respective keys of json.
                                                3. Use the following dataset for analysis:
                                                   ' . $transformedData . '
                                
                                                Respond only with the JSON response in the specified structure. Do not provide any explanation, extra text, or deviations from the required structure.'
                        ]
                    ]
                ]);
                // Parse the OpenAI response
                $responseData = is_string($response) ? json_decode($response, true) : $response;
                $rawContent = $responseData['choices'][0]['message']['content'];
                $jsonContent = trim(str_replace(['```json', '```'], '', $rawContent));
                $responseContent = json_decode($jsonContent, true);
                $newData = [
                    'student_id' => json_decode($transformedData, true)['student_id'],
                    'course_name' => json_decode($transformedData, true)['course_name'],
                    'quiz_analysis' => json_decode($transformedData, true)['quiz_analysis'],
                    'section_analysis' => json_decode($transformedData, true)['section_analysis'],
                    'overall_performance' => json_decode($transformedData, true)['overall_performance'],
                    'performance_summary' => $responseContent['performance_summary'],
                    'sectionsAndQuizes' => $sectionsAndQuizes
                ];
                $jsonNewData = json_encode($newData);
                // Extract necessary data for database entry
                $userid = $USER->id;
                $contextid = context_system::instance()->id;
                $tokensUsage = $responseData['usage'];

                // Create a new AI-generated report entry in the database
                $reportId = create_user_ai_report($userid, $courseid, $jsonNewData, $tokensUsage);
                if ($reportId === false) {
                    debugging('Failed to create AI report for user ID: ' . $userid, DEBUG_NORMAL);
                }
                echo generateStudentReport($jsonNewData, $courseid, $student_id);                exit;
            } catch (Exception $e) {
                echo '<div class="alert alert-danger" role="alert">';
                echo '<strong>Error:</strong> There was an issue processing the OpenAI response. Please try again later.';
                echo '<br><strong>Details:</strong> ' . htmlspecialchars($e->getMessage());
                echo '</div>';
                exit;
            }
        }
    }

    if (isset($_GET['fetch']) && $_GET['fetch'] === 'random' && !empty($_GET['id'])) {
        gradereport_user_random_bag_page($courseid, [$USER->id => $USER]);
    }
    
    // Recursively gather every quiz activity under a section, at any depth, regardless of
    // whether the section holds quizzes directly, only inside subsections, or both. Dedupes
    // by activity id so a section that appears twice in its own "children" (a pre-existing
    // quirk of build_nested_children() in lib.php) doesn't produce duplicate papers.
    function collect_quiz_activities_recursive($node, &$seenids) {
        $found = array();
        foreach ($node['activities'] as $activity) {
            if ($activity['modname'] !== 'quiz') continue;
            if (isset($seenids[$activity['id']])) continue;
            $seenids[$activity['id']] = true;
            $found[] = $activity;
        }
        $children = $node['children'];
        if (!empty($children)) {
            foreach ((is_array($children) && array_key_exists(0, $children) ? $children : array($children)) as $child) {
                $found = array_merge($found, collect_quiz_activities_recursive($child, $seenids));
            }
        }
        return $found;
    }

    if (isset($_GET['fetch']) && $_GET['fetch'] === 'past-year' && !empty($_GET['id'])) {
        $student_id = $USER->id;
        $courseid = $_GET['id'];
        $course_structure = get_course_structure($courseid);

        // Find all top-level sections whose name starts with "Past Paper" (e.g. "Past Papers",
        // "Past Paper - Physics"), so multiple such sections can coexist in one course.
        $pastYearSections = array();
        foreach ($course_structure["sections"] as $section) {
            if (stripos($section["name"], "Past Paper") === 0) {
                $pastYearSections[] = $section;
            }
        }
        if (empty($pastYearSections)) die("Past Year Papers section not found.");

        // Build one flat group per matched section, containing every quiz found anywhere in
        // its subtree (its own activities and, recursively, every level of subsections) -
        // this works uniformly whether the section holds quizzes directly, only through
        // subsections, or both.
        $pastYearChildren = array();
        foreach ($pastYearSections as $pastYearSection) {
            $seenids = array();
            $quizzes = collect_quiz_activities_recursive($pastYearSection, $seenids);
            if (empty($quizzes)) continue;
            $pastYearChildren[] = array(
                'id' => $pastYearSection['id'],
                'name' => $pastYearSection['name'],
                'activities' => $quizzes,
                'children' => array(),
            );
        }

        // Check if we need to process quiz data
        if (isset($_GET['selected_quizzes']) && !empty($_GET['selected_quizzes'])) {
            $selected_quiz_ids = explode(',', $_GET['selected_quizzes']);
            
            // Initialize components with the enhanced renderer
            require_once('integrated_quiz_renderer.php'); // Include the enhanced renderer
            require_once('quizdataprocessor.php');
            $renderer = new enhanced_quiz_question_renderer($PAGE, '');
            $processor = new quiz_attempt_data_processor();
            
            // Get student information
            $student = $DB->get_record('user', ['id' => $student_id]);
            if (!$student) {
                echo $OUTPUT->notification('Student not found.', 'notifyerror');
                exit;
            }
            
            // Collect all quiz attempts data with tag information
            $all_questions = [];
            $quiz_names = [];
            // Full quiz records (name + cmid + quiz_id), so paper names can link to the quiz.
            $quiz_papers = [];
            
            foreach ($selected_quiz_ids as $quiz_cmid) {
                // Get quiz information
                $quiz_sql = "
                    SELECT 
                        q.id as quiz_id,
                        q.name as quiz_name,
                        q.intro,
                        q.grade as max_grade,
                        q.sumgrades,
                        cm.id as cmid
                    FROM {quiz} q
                    JOIN {course_modules} cm ON cm.instance = q.id AND cm.module = (SELECT id FROM {modules} WHERE name = 'quiz')
                    WHERE cm.id = :quiz_cmid AND cm.course = :courseid
                ";
                
                $quiz_info = $DB->get_record_sql($quiz_sql, [
                    'quiz_cmid' => $quiz_cmid,
                    'courseid' => $courseid
                ]);
                
                if ($quiz_info) {
                    $quiz_names[] = $quiz_info->quiz_name;
                    $quiz_papers[] = $quiz_info;
                    
                    // Get student's latest quiz attempt
                    $latest_attempt = $DB->get_record_sql("
                        SELECT id as attempt_id, attempt, state, timestart, timefinish, sumgrades
                        FROM {quiz_attempts}
                        WHERE quiz = :quiz_id AND userid = :student_id AND preview = 0
                        ORDER BY attempt DESC
                        LIMIT 1
                    ", [
                        'quiz_id' => $quiz_info->quiz_id,
                        'student_id' => $student_id
                    ]);
                    
                    if ($latest_attempt) {
                        // Get comprehensive question data with tags for this attempt
                        $questions = $processor->get_attempt_questions_with_tags($latest_attempt->attempt_id);
                        
                        foreach ($questions as $question) {
                            // Add quiz context to each question
                            $question->source_quiz_name = $quiz_info->quiz_name;
                            $question->source_quiz_id = $quiz_info->quiz_id;
                            $question->source_quiz_cmid = $quiz_info->cmid;
                            $all_questions[] = $question;
                        }
                    }
                }
            }
            
            if (empty($all_questions)) {
                echo '<div class="quiz-dashboard"><div class="no-data-message card">No quiz attempts found for the selected past year papers.</div></div>';
                exit;
            }
            
            // Group questions by tag combinations
            $tag_groups = $processor->group_by_tag_combinations($all_questions);
            
            // Calculate overall statistics
            $overall_stats = $processor->calculate_overall_stats_from_tag_groups($tag_groups);

            // Determine color based on percentage
            $percentage = $overall_stats['percentage'];
            $score_color = '#EF4444'; // Default poor color

            if ($percentage >= 80) {
                $score_color = '#16A34A'; // Excellent (green)
            } elseif ($percentage >= 40) {
                $score_color = '#D97706'; // Average (orange)
            }
            echo '<script>console.log("tag_groups:", ' . json_encode($tag_groups) . ')</script>';
            // Calculate best and worst performing topics
            $performance_highlights = [];

            foreach ($tag_groups as $tag_combination => $tag_data) {
                $performance_highlights[] = [
                    'name' => $tag_combination,
                    'percentage' => $tag_data['percentage'],
                    'correct_answers' => $tag_data['correct_answers'],
                    'total_questions' => $tag_data['total_questions'],
                    'wrong_answers' => $tag_data['wrong_answers']
                ];
            }

            // Sort by performance percentage
            usort($performance_highlights, function($a, $b) {
                return $b['percentage'] - $a['percentage'];
            });

            // Get top 3 best and bottom 3 worst (but only if we have enough data).
            // A 0% topic can't be a "best" area and a 100% topic can't be a "worst" area.
            $best_topics = array_slice(array_filter($performance_highlights, function($t) {
                return $t['percentage'] > 0;
            }), 0, 3);
            $worst_topics = array_slice(array_reverse(array_filter($performance_highlights, function($t) {
                return $t['percentage'] < 100;
            })), 0, 3);
            ?>
            <!DOCTYPE html>
            <html lang="en">
            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <title>Past Year Papers Performance Report - <?= htmlspecialchars($student->firstname . ' ' . $student->lastname) ?></title>
                <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
                <style>
                    @import url("https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap");
    
                    :root {
                        --primary-color: #0D9488;
                        --secondary-color: #7b1fa2;
                        --status-error: #FEE2E2;
                        --status-error-text: #EF4444;
                        --status-success: #DCFCE7;
                        --status-success-text: #16A34A;
                        --status-warning: #FEF3C7;
                        --status-warning-text: #D97706;
                        --text-color: #334155;
                        --text-light: #64748B;
                        --background-color: #F8FAFC;
                        --border-color: #E2E8F0;
                        --space-xs: 0.25rem;
                        --space-sm: 0.5rem;
                        --space-md: 1rem;
                        --space-lg: 1.5rem;
                        --space-xl: 2rem;
                        --font-primary: 'Inter', system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                    }
    
                    * {
                        margin: 0;
                        padding: 0;
                        box-sizing: border-box;
                    }
                    
                    body {
                        background-color: var(--background-color);
                        font-family: var(--font-primary);
                        color: var(--text-color);
                        line-height: 1.5;
                        min-height: 100vh;
                    }
                    
                    .quiz-dashboard {
                        max-width: 1200px;
                        margin: 0 auto;
                        padding: var(--space-xl);
                    }
    
                    /* Header */
                    .header {
                        background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
                        color: white;
                        padding: 30px 0;
                        text-align: center;
                        border-radius: 16px;
                        margin-bottom: 10px;
                        box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
                    }
    
                    .header h1 {
                        font-size: 2.5rem;
                        font-weight: 700;
                        margin-bottom: 10px;
                    }
    
                    .header p {
                        font-size: 1.1rem;
                        opacity: 0.9;
                        margin: 5px 0;
                    }

                    /* Cards */
                    .card {
                        background: white;
                        border-radius: 16px;
                        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05), 0 4px 6px rgba(0, 0, 0, 0.02);
                        padding: var(--space-xl);
                        margin-bottom: var(--space-xl);
                        transition: transform 0.2s ease, box-shadow 0.2s ease;
                    }

                    .card:hover {
                        transform: translateY(-2px);
                        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05), 0 10px 15px rgba(0, 0, 0, 0.02);
                    }

                    /* Course Info Card */
                    .course-info {
                        border-top-left-radius: 0;
                        border-top-right-radius: 0;
                    }

                    .report-h2 {
                        display: flex;
                        align-items: center;
                        gap: var(--space-sm);
                        color: var(--primary-color);
                        border-bottom: 2px solid var(--border-color);
                        padding-bottom: var(--space-md);
                        margin-bottom: var(--space-lg);
                        font-size: 1.5rem;
                        font-weight: 600;
                    }

                    .info-grid {
                        display: grid;
                        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
                        gap: 15px;
                    }

                    .info-item {
                        background-color: #f0f4f8;
                        padding: 12px;
                        border-radius: 8px;
                        display: flex;
                        align-items: center;
                        justify-content: space-between;
                        transition: all 0.3s ease;
                        text-transform: capitalize;
                    }

                    .info-item:hover {
                        background-color: #e6eaf0;
                        transform: translateX(5px);
                    }

                    .info-item strong {
                        color: var(--primary-color);
                        font-weight: 600;
                    }

                    /* Stats Grid */
                    .stats-grid {
                        display: grid;
                        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
                        gap: 20px;
                        margin-bottom: 30px;
                    }
                    
                    .stat-card {
                        background: white;
                        border-radius: 16px;
                        padding: 24px;
                        text-align: center;
                        box-shadow: 0 4px 16px rgba(0, 0, 0, 0.08);
                        transition: transform 0.2s ease, box-shadow 0.2s ease;
                    }

                    .stat-card:hover {
                        transform: translateY(-2px);
                        box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
                    }
                    
                    .stat-number {
                        font-size: 2.5rem;
                        font-weight: 700;
                        margin-bottom: 8px;
                    }
                    
                    .stat-label {
                        color: var(--text-light);
                        font-weight: 500;
                        text-transform: uppercase;
                        font-size: 0.85rem;
                        letter-spacing: 0.5px;
                    }

                    /* Performance Color Guide */
                    .performance-info .info-grid {
                        grid-template-columns: repeat(3, 1fr);
                        gap: 10px;
                    }

                    .performance-info .info-item {
                        text-align: center;
                        padding: 15px;
                        border-radius: 8px;
                        font-weight: bold;
                        justify-content: center;
                    }

                    /* Tag Groups Table */
                    .tag-groups-table {
                        background: white;
                        border-radius: 16px;
                        overflow: hidden;
                        box-shadow: 0 4px 16px rgba(0, 0, 0, 0.08);
                        margin-bottom: 20px;
                    }

                    .tag-groups-table table {
                        width: 100%;
                        border-collapse: collapse;
                    }

                    .tag-groups-table th {
                        background: var(--primary-color);
                        color: white;
                        padding: 20px;
                        text-align: left;
                        font-weight: 600;
                        font-size: 1rem;
                    }

                    .tag-groups-table td {
                        padding: 16px 20px;
                        border-bottom: 1px solid var(--border-color);
                        vertical-align: middle;
                    }

                    .tag-row {
                        cursor: pointer;
                        transition: background-color 0.2s ease;
                    }

                    .tag-row:hover {
                        background-color: #f9fafb;
                    }

                    .tag-name {
                        font-weight: 600;
                        color: var(--text-color);
                        display: flex;
                        justify-content: space-between;
                        align-items: center;
                        width: 100%;
                    }

                    .stat-number-table {
                        font-weight: 700;
                        font-size: 1.1rem;
                    }

                    .percentage-cell {
                        font-weight: 700;
                        font-size: 1.1rem;
                    }

                    .percentage-excellent { color: var(--status-success-text); }
                    .percentage-good { color: #0284c7; }
                    .percentage-average { color: var(--status-warning-text); }
                    .percentage-poor { color: var(--status-error-text); }

                    .expand-icon {
                        transition: transform 0.3s ease;
                        font-size: 1.2rem;
                        color: var(--text-light);
                    }

                    .tag-row.expanded .expand-icon {
                        transform: rotate(90deg);
                    }

                    /* Questions Container */
                    .questions-container {
                        display: none;
                        background: var(--background-color);
                        border-top: 1px solid var(--border-color);
                    }

                    .questions-container.show {
                        display: block;
                    }

                    .questions-content {
                        padding: 20px;
                    }

                    /* Enhanced Question Items */
                    .enhanced-question {
                        background: white;
                        border-radius: 12px;
                        padding: 20px;
                        margin-bottom: 16px;
                        border-left: 4px solid transparent;
                        transition: all 0.3s ease;
                        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
                    }
                    
                    .enhanced-question.correct {
                        border-left-color: var(--status-success-text);
                        background: linear-gradient(135deg, var(--status-success), #f0fdf4);
                    }
                    
                    .enhanced-question.wrong {
                        border-left-color: var(--status-error-text);
                        background: linear-gradient(135deg, var(--status-error), #fef7f7);
                    }

                    /* Question metadata styles */
                    .question-metadata {
                        background: rgba(0,0,0,0.03);
                        padding: 8px 12px;
                        border-radius: 6px;
                        margin-bottom: 12px;
                        font-size: 0.85rem;
                        color: var(--text-light);
                    }

                    .question-type, .question-category, .question-source {
                        font-weight: 500;
                    }

                    /* Core question content wrapper */
                    .core-question-content {
                        margin: 15px 0;
                    }

                    /* Score analysis */
                    .score-analysis {
                        background: rgba(13, 148, 136, 0.1);
                        padding: 8px 12px;
                        border-radius: 6px;
                        margin: 10px 0;
                        font-size: 0.9rem;
                    }

                    .score-details {
                        font-weight: 600;
                        color: var(--primary-color);
                    }
                    
                    .status-badge {
                        display: inline-block;
                        padding: 6px 12px;
                        border-radius: 20px;
                        font-size: 0.75rem;
                        font-weight: 600;
                        text-transform: uppercase;
                        letter-spacing: 0.5px;
                        margin-top: 12px;
                    }
                    
                    .status-correct {
                        background: var(--status-success);
                        color: var(--status-success-text);
                    }
                    
                    .status-wrong {
                        background: var(--status-error);
                        color: var(--status-error-text);
                    }

                    /* Charts Container */
                    .charts-container {
                        background: white;
                        border-radius: 16px;
                        padding: 30px;
                        margin-bottom: 20px;
                        box-shadow: 0 4px 16px rgba(0, 0, 0, 0.08);
                        text-align: center;
                    }

                    .charts-container canvas {
                        max-width: 400px;
                        max-height: 400px;
                        margin: 0 auto;
                        display: block;
                    }

                    /* Animation */
                    .slide-in {
                        opacity: 0;
                        transform: translateY(20px);
                        transition: all 0.8s ease;
                    }
                    
                    .slide-in.visible {
                        opacity: 1;
                        transform: translateY(0);
                    }

                    /* Section Row Styles */
                    .section-row {
                        background-color: #f8fafc !important;
                        cursor: pointer;
                        border-left: 3px solid transparent;
                        transition: background-color 0.2s ease, border-left 0.2s ease;
                    }

                    .section-row:hover {
                        background-color: #f1f5f9 !important;
                    }

                    .section-row.active {
                        background-color: #f1f5f9 !important;
                        border-left: 3px solid var(--primary-color);
                    }

                    .section-name {
                        font-weight: 600;
                        display: flex;
                        align-items: center;
                        gap: 0.75rem;
                        padding: 0.5rem 0;
                    }

                    .section-icon {
                        color: var(--primary-color);
                        transition: transform 0.3s ease;
                    }

                    .section-row.active .section-icon {
                        transform: rotate(90deg);
                    }

                    /* Performance Chip */
                    .chip {
                        padding: 0.5rem 1rem;
                        border-radius: 999px;
                        display: inline-flex;
                        align-items: center;
                        justify-content: center;
                        font-weight: 600;
                        width: 100%;
                        text-align: center;
                        box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
                        transition: transform 0.2s ease;
                    }

                    .chip:hover {
                        transform: translateY(-1px);
                    }

                    /* Tag Indicator Styles */
                    .tag-combination {
                        display: flex;
                        flex-wrap: wrap;
                        gap: 0.5rem;
                        align-items: center;
                    }

                    .tag-badge {
                        background: linear-gradient(135deg, #f0f9ff, #e0f2fe);
                        color: #0369a1;
                        padding: 0.25rem 0.75rem;
                        border-radius: 20px;
                        font-size: 0.8rem;
                        font-weight: 600;
                        border: 1px solid #bae6fd;
                        text-transform: capitalize;
                    }

                    .no-tags {
                        color: var(--text-light);
                        font-style: italic;
                        font-size: 0.9rem;
                    }

                    /* Source quiz indicator */
                    .question-source-badge {
                        background: linear-gradient(135deg, #fefce8, #fef3c7);
                        color: #92400e;
                        padding: 0.2rem 0.6rem;
                        border-radius: 12px;
                        font-size: 0.75rem;
                        font-weight: 600;
                        border: 1px solid #fde68a;
                        margin-left: 8px;
                    }

                    /* Responsive Design */
                    @media (max-width: 768px) {
                        .quiz-dashboard {
                            margin: 15px;
                            padding: 20px;
                        }
                        
                        .header h1 {
                            font-size: 1.8rem;
                        }
                        
                        .info-grid {
                            grid-template-columns: 1fr;
                        }
                        
                        .stats-grid {
                            grid-template-columns: repeat(2, 1fr);
                        }
                        
                        .tag-groups-table th,
                        .tag-groups-table td {
                            padding: 12px 15px;
                        }

                        .tag-combination {
                            flex-direction: column;
                            align-items: flex-start;
                        }
                    }

                    @media (max-width: 480px) {
                        .stats-grid {
                            grid-template-columns: 1fr;
                        }
                        
                        .performance-info .info-grid {
                            grid-template-columns: 1fr;
                        }
                    }
                    .enhanced-question.partial-correct {
                        border-left-color: #F59E0B;
                        background: linear-gradient(135deg, var(--status-warning), #fefce8);
                    }

                    .status-partial {
                        background: var(--status-warning);
                        color: var(--status-warning-text);
                    }

                    /* Quiz name rendered as a link through to the quiz activity */
                    .quiz-name-link { color: inherit; text-decoration: underline; text-underline-offset: 2px; }
                    .quiz-name-link:hover, .quiz-name-link:focus { opacity: 0.85; }
                </style>
            </head>
            <body>
                <div class="quiz-dashboard">
                    <!-- Header -->
                    <div class="header slide-in">
                        <h1>Past Year Papers Performance Report</h1>
                        <p>Comprehensive Analysis from Past Year Papers</p>
                        <p>Student: <?= htmlspecialchars($student->firstname . ' ' . $student->lastname) ?></p>
                        <?php if (count($quiz_names) == 1): ?>
                            <p>Paper Analyzed: <?= gradereport_user_quiz_name_link($quiz_papers[0]->quiz_name, $quiz_papers[0]->cmid, $quiz_papers[0]->quiz_id) ?></p>
                        <?php else: ?>
                            <p>Papers Analyzed: <?= count($quiz_names) ?> past papers</p>
                            <details style="margin-top: 15px; max-width: 900px; margin-left: auto; margin-right: auto;">
                                <summary style="cursor: pointer; font-size: 0.95rem; opacity: 0.9; font-weight: 600;">
                                    <strong>View Papers List</strong>
                                </summary>
                                <div style="margin-top: 10px; font-size: 0.9rem; opacity: 0.9; line-height: 1.4; text-align: left; background: rgba(255,255,255,0.1); padding: 15px; border-radius: 8px; margin-top: 10px;">
                                    <?php 
                                    $formatted_names = array_map(function ($paper) {
                                        return gradereport_user_quiz_name_link($paper->quiz_name, $paper->cmid, $paper->quiz_id);
                                    }, $quiz_papers);
                                    echo implode(' • ', $formatted_names);
                                    ?>
                                </div>
                            </details>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Course and Student Information -->
                    <div class="course-info card slide-in">
                        <h2 class="report-h2"><i class="fas fa-file-alt"></i> Report Details</h2>
                        <div class="info-grid">
                            <div class="info-item"><strong>Section:</strong> Past Year Papers</div>
                            <div class="info-item"><strong>Student ID:</strong> <?= htmlspecialchars($student_id) ?></div>
                            <div class="info-item"><strong>Course ID:</strong> <?= htmlspecialchars($courseid) ?></div>
                            <div class="info-item"><strong>Total Papers:</strong> <?= count($quiz_names) ?></div>
                        </div>
                    </div>
    
                    <!-- Performance Color Guide -->
                    <div class="performance-info card slide-in">
                        <h2 class="report-h2"><i class="fas fa-info-circle"></i> Performance Color Code</h2>
                        <div class="info-grid">
                            <div class="info-item" style="background-color: #FEE2E2; color: #EF4444;">
                                0-39% Poor
                            </div>
                            <div class="info-item" style="background-color: #FEF3C7; color: #D97706;">
                                40-79% Average
                            </div>
                            <div class="info-item" style="background-color: #DCFCE7; color: #16A34A;">
                                80-100% Excellent
                            </div>
                        </div>
                    </div>
                    
                    <!-- Overall Statistics -->
                    <div class="stats-grid slide-in">
                        <div class="stat-card">
                            <div class="stat-number"><?= $overall_stats['total_questions'] ?></div>
                            <div class="stat-label">Total Questions</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-number" style="color: var(--status-success-text);"><?= $overall_stats['total_correct'] ?></div>
                            <div class="stat-label">Correct</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-number" style="color: var(--status-error-text);"><?= $overall_stats['total_wrong'] ?></div>
                            <div class="stat-label">Wrong</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-number" style="color: <?= $score_color ?>;"><?= $overall_stats['percentage'] ?>%</div>
                            <div class="stat-label">Overall Score</div>
                        </div>
                    </div>

                    <!-- Performance Highlights -->
                    <div class="performance-highlights card slide-in">
                        <h2 class="report-h2">
                            <i class="fas fa-chart-line"></i> 
                            Performance Highlights
                        </h2>
                        
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px; margin-top: 20px;">
                            
                            <!-- Best Performing Topics -->
                            <div class="best-topics">
                                <h3 style="color: #059669; margin-bottom: 15px; display: flex; align-items: center; gap: var(--space-sm); font-size: 1.2rem; font-weight: 600;">
                                    <i class="fas fa-trophy" style="color: #059669;"></i>
                                    Top Performing Topics
                                </h3>
                                
                                <?php if (!empty($best_topics)): ?>
                                    <div class="topics-list">
                                        <?php foreach ($best_topics as $index => $topic): ?>
                                            <div class="info-item" style="background-color: #F0FDF4; border-left: 4px solid #059669; display: block; margin-bottom: var(--space-md);">
                                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: var(--space-sm);">
                                                    <div style="display: flex; align-items: center; gap: var(--space-sm);">
                                                        <span style="background: #059669; color: white; width: 28px; height: 28px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 0.9em; font-weight: 700;">
                                                            <?php echo ($index + 1); ?>
                                                        </span>
                                                        <span style="font-weight: 600; color: var(--text-color);">
                                                            <?php if ($topic['name'] === 'No Tags'): ?>
                                                                <span style="font-style: italic; color: var(--text-light);">Untagged Questions</span>
                                                            <?php else: ?>
                                                                <?php 
                                                                $tags = explode(' ; ', $topic['name']);
                                                                foreach ($tags as $tag_index => $tag): 
                                                                    if ($tag_index > 0) echo ' • ';
                                                                    echo htmlspecialchars(trim($tag));
                                                                endforeach; 
                                                                ?>
                                                            <?php endif; ?>
                                                        </span>
                                                    </div>
                                                    <span style="background-color: #D1FAE5; color: #065F46; padding: 6px 12px; border-radius: 16px; font-size: 0.9em; font-weight: 600;">
                                                        <?php echo number_format($topic['percentage'], 1); ?>%
                                                    </span>
                                                </div>
                                                <div style="font-size: 0.85rem; color: var(--text-light); display: flex; gap: var(--space-lg); margin-left: 36px;">
                                                    <span><i class="fas fa-check-circle" style="color: #059669;"></i> <?php echo $topic['correct_answers']; ?> correct</span>
                                                    <span><i class="fas fa-question-circle"></i> <?php echo $topic['total_questions']; ?> total</span>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="info-item" style="background-color: var(--background-color); border: 1px solid var(--border-color); color: var(--text-light); font-style: italic; justify-content: center;">
                                        <i class="fas fa-info-circle"></i> Not enough data to show top topics yet.
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Worst Performing Topics -->
                            <div class="worst-topics">
                                <h3 style="color: #DC2626; margin-bottom: 15px; display: flex; align-items: center; gap: var(--space-sm); font-size: 1.2rem; font-weight: 600;">
                                    <i class="fas fa-exclamation-triangle" style="color: #DC2626;"></i>
                                    Areas Needing Improvement
                                </h3>
                                
                                <?php if (!empty($worst_topics)): ?>
                                    <div class="topics-list">
                                        <?php foreach ($worst_topics as $index => $topic): ?>
                                            <div class="info-item" style="background-color: #FEF2F2; border-left: 4px solid #DC2626; display: block; margin-bottom: var(--space-md);">
                                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: var(--space-sm);">
                                                    <div style="display: flex; align-items: center; gap: var(--space-sm);">
                                                        <span style="background: #DC2626; color: white; width: 28px; height: 28px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 0.9em; font-weight: 700;">
                                                            <?php echo ($index + 1); ?>
                                                        </span>
                                                        <span style="font-weight: 600; color: var(--text-color);">
                                                            <?php if ($topic['name'] === 'No Tags'): ?>
                                                                <span style="font-style: italic; color: var(--text-light);">Untagged Questions</span>
                                                            <?php else: ?>
                                                                <?php 
                                                                $tags = explode(' ; ', $topic['name']);
                                                                foreach ($tags as $tag_index => $tag): 
                                                                    if ($tag_index > 0) echo ' • ';
                                                                    echo htmlspecialchars(trim($tag));
                                                                endforeach; 
                                                                ?>
                                                            <?php endif; ?>
                                                        </span>
                                                    </div>
                                                    <span style="background-color: #FECACA; color: #991B1B; padding: 6px 12px; border-radius: 16px; font-size: 0.9em; font-weight: 600;">
                                                        <?php echo number_format($topic['percentage'], 1); ?>%
                                                    </span>
                                                </div>
                                                <div style="font-size: 0.85rem; color: var(--text-light); display: flex; gap: var(--space-lg); margin-left: 36px;">
                                                    <span><i class="fas fa-times-circle" style="color: #DC2626;"></i> <?php echo $topic['wrong_answers']; ?> incorrect</span>
                                                    <span><i class="fas fa-question-circle"></i> <?php echo $topic['total_questions']; ?> total</span>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="info-item" style="background-color: var(--background-color); border: 1px solid var(--border-color); color: var(--text-light); font-style: italic; justify-content: center;">
                                        <i class="fas fa-info-circle"></i> Not enough data to show improvement areas yet.
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Action Items -->
                        <div style="background-color: var(--background-color); border-radius: 8px; padding: var(--space-xl); margin-top: var(--space-xl); border: 1px solid var(--border-color);">
                            <h3 style="color: var(--primary-color); margin-bottom: var(--space-lg); display: flex; align-items: center; gap: var(--space-sm); font-size: 1.2rem; font-weight: 600;">
                                <i class="fas fa-lightbulb"></i>
                                Quick Action Items
                            </h3>
                            
                            <div class="info-grid">
                                <?php if (!empty($best_topics)): ?>
                                    <div class="info-item" style="background: white; border-left: 4px solid #059669; display: block;">
                                        <h4 style="margin: 0 0 var(--space-sm) 0; color: var(--text-color); font-size: 0.95rem; font-weight: 600;">
                                            <i class="fas fa-star" style="color: #059669;"></i>
                                            Build on Strengths
                                        </h4>
                                        <p style="margin: 0; font-size: 0.85rem; color: var(--text-light); line-height: 1.4;">
                                            Continue practicing your strong areas to maintain high performance.
                                        </p>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($worst_topics)): ?>
                                    <div class="info-item" style="background: white; border-left: 4px solid #DC2626; display: block;">
                                        <h4 style="margin: 0 0 var(--space-sm) 0; color: var(--text-color); font-size: 0.95rem; font-weight: 600;">
                                            <i class="fas fa-target" style="color: #DC2626;"></i>
                                            Focus on Weak Areas
                                        </h4>
                                        <p style="margin: 0; font-size: 0.85rem; color: var(--text-light); line-height: 1.4;">
                                            Dedicate extra study time to your lowest performing topics.
                                        </p>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="info-item" style="background: white; border-left: 4px solid var(--primary-color); display: block;">
                                    <h4 style="margin: 0 0 var(--space-sm) 0; color: var(--text-color); font-size: 0.95rem; font-weight: 600;">
                                        <i class="fas fa-redo" style="color: var(--primary-color);"></i>
                                        Regular Practice
                                    </h4>
                                    <p style="margin: 0; font-size: 0.85rem; color: var(--text-light); line-height: 1.4;">
                                        Take practice quizzes regularly to improve consistently.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Add responsive styles for mobile -->
                    <style>
                    @media (max-width: 768px) {
                        .performance-highlights div[style*="grid-template-columns: 1fr 1fr"] {
                            display: grid !important;
                            grid-template-columns: 1fr !important;
                            gap: 20px !important;
                        }
                    }
                    </style>
                    <!-- Performance Chart -->
                    <div class="charts-container card slide-in">
                        <h2 class="report-h2"><i class="fas fa-chart-pie"></i> Overall Performance Graph</h2>
                        <div style="width: 400px; height: 400px; display: flex; justify-content: center; align-items: center; margin: 0 auto;">
                            <canvas id="overallPerformanceChart"></canvas>
                        </div>
                    </div>
                    
                    <!-- Tag Groups Table -->
                    <div class="tag-groups-table card slide-in">
                        <h2 class="report-h2" style="margin: 0 0 20px 0; padding: 20px 20px 0 20px;"><i class="fas fa-tags"></i> Topic-wise Performance Analysis</h2>
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Topic/Tag Combination</th>
                                        <th>Total Questions</th>
                                        <th>Correct Answers</th>
                                        <th>Incorrect Answers</th>
                                        <th>Performance (%)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($tag_groups as $tag_combination => $tag_data): ?>
                                        <?php 
                                        $tag_id = 'tag_' . md5($tag_combination);
                                        $performancePercentage = $tag_data['percentage'];
                                        $color = '#FEE2E2'; // Default color (red background)
                                        $textColor = '#EF4444'; // Default text color for red background

                                        if ($performancePercentage >= 80) {
                                            $color = '#DCFCE7';
                                            $textColor = '#16A34A';
                                        } elseif ($performancePercentage >= 40) {
                                            $color = '#FEF3C7';
                                            $textColor = '#D97706';
                                        }
                                        ?>
                                        <tr class="section-row tag-row" data-tag-combination="<?= htmlspecialchars($tag_combination) ?>" onclick="toggleTagRow('<?= $tag_id ?>')" id="row-<?= $tag_id ?>">
                                            <td>
                                                <div class="section-name">
                                                    <i class="fas fa-chevron-right section-icon"></i>
                                                    <div class="tag-combination">
                                                        <?php if (empty($tag_combination) || $tag_combination === 'No Tags'): ?>
                                                            <span class="no-tags">Untagged Questions</span>
                                                        <?php else: ?>
                                                            <?php 
                                                            $tags = explode(' ; ', $tag_combination);
                                                            foreach ($tags as $tag): 
                                                            ?>
                                                                <span class="tag-badge"><?= htmlspecialchars(trim($tag)) ?></span>
                                                            <?php endforeach; ?>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </td>
                                            <td><?= $tag_data['total_questions'] ?></td>
                                            <td><?= $tag_data['correct_answers'] ?></td>
                                            <td><?= $tag_data['wrong_answers'] ?></td>
                                            <td>
                                                <span class="chip" style="background-color: <?php echo $color; ?>; color: <?php echo $textColor; ?>; padding: 5px 10px; border-radius: 12px; display: inline-block; font-weight: bold; width:100%; text-align: center;">
                                                    <?= $performancePercentage ?>%
                                                </span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td colspan="5" style="padding: 0;">
                                                <div class="questions-container" id="<?= $tag_id ?>">
                                                    <div class="questions-content">
                                                        <?php foreach ($tag_data['questions'] as $question): ?>
                                                            <?php
                                                            // Prepare attempt data for enhanced renderer
                                                            $attempt_data = (object)[
                                                                'fraction' => $question->fraction,
                                                                'stepdata_value' => $processor->extract_primary_answer($question),
                                                                'state' => $question->state
                                                            ];
                                                            
                                                            // Add source quiz information to question data
                                                            $question->source_quiz_info = $question->source_quiz_name ?? 'Unknown Quiz';
                                                            
                                                            // Render question using enhanced renderer
                                                            echo $renderer->render_question_report($question, $attempt_data, true);
                                                            ?>
                                                        <?php endforeach; ?>
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                
                <!-- Include Chart.js --><script>window.__amdDefine=window.define;window.define=undefined;</script>
                <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
                <script>window.define=window.__amdDefine;delete window.__amdDefine;</script>
                <script>
                    document.addEventListener("DOMContentLoaded", function() {
                        // Overall Performance Chart
                        const overallCtx = document.getElementById("overallPerformanceChart").getContext("2d");
                        new Chart(overallCtx, {
                            type: "pie",
                            data: {
                                labels: ["Correct", "Incorrect"],
                                datasets: [{
                                    data: [<?= $overall_stats['total_correct'] ?>, <?= $overall_stats['total_wrong'] ?>],
                                    backgroundColor: ['#A78BFA', '#FB7185'],
                                    borderWidth: 2,
                                    borderColor: "#ffffff"
                                }]
                            },
                            options: {
                                responsive: true,
                                plugins: {
                                    legend: {
                                        position: "top",
                                        labels: {
                                            font: {
                                                size: 14,
                                                weight: "bold"
                                            }
                                        }
                                    },
                                    title: {
                                        display: true,
                                        text: "Overall Performance Across All Past Papers",
                                        font: {
                                            size: 18,
                                            weight: "bold"
                                        }
                                    }
                                }
                            }
                        });

                        // Tag toggle functionality
                        function toggleTagRow(tagId) {
                            const row = document.getElementById('row-' + tagId);
                            const container = document.getElementById(tagId);
                            const icon = row.querySelector('.section-icon');
                            
                            if (container.classList.contains('show')) {
                                container.classList.remove('show');
                                container.style.display = 'none';
                                row.classList.remove('active');
                            } else {
                                // Close other open tag groups
                                document.querySelectorAll('.questions-container.show').forEach(el => {
                                    el.classList.remove('show');
                                    el.style.display = 'none';
                                });
                                document.querySelectorAll('.section-row.active').forEach(el => {
                                    el.classList.remove('active');
                                });
                                
                                container.style.display = 'block';
                                setTimeout(() => {
                                    container.classList.add('show');
                                }, 10);
                                row.classList.add('active');
                            }
                        }

                        // Make function globally available
                        window.toggleTagRow = toggleTagRow;

                        // Scroll animations
                        const observer = new IntersectionObserver((entries) => {
                            entries.forEach(entry => {
                                if (entry.isIntersecting) {
                                    entry.target.classList.add("visible");
                                }
                            });
                        }, {
                            threshold: 0.1
                        });

                        document.querySelectorAll(".slide-in").forEach(elem => {
                            observer.observe(elem);
                        });
                    });
                </script>
            </body>
            </html>
            
            <?php
            exit;
        }
        
        // Show selection interface if no quizzes selected
        if ($pastYearChildren): ?>
            <style>
                @import url("https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap");

                :root {
                    --primary-color: #0D9488;
                    --secondary-color: #7b1fa2;
                    --status-error: #FEE2E2;
                    --status-error-text: #EF4444;
                    --status-success: #DCFCE7;
                    --status-success-text: #16A34A;
                    --status-warning: #FEF3C7;
                    --status-warning-text: #D97706;
                    --text-color: #334155;
                    --text-light: #64748B;
                    --background-color: #F8FAFC;
                    --border-color: #E2E8F0;
                    --space-xs: 0.25rem;
                    --space-sm: 0.5rem;
                    --space-md: 1rem;
                    --space-lg: 1.5rem;
                    --space-xl: 2rem;
                    --font-primary: 'Inter', system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                }

                * {
                    margin: 0;
                    padding: 0;
                    box-sizing: border-box;
                }

                body {
                    background-color: var(--background-color);
                    font-family: var(--font-primary);
                    color: var(--text-color);
                    line-height: 1.5;
                    min-height: 100vh;
                }

                @keyframes slideDown {
                    from {
                        opacity: 0;
                        transform: translateY(-20px);
                    }
                    to {
                        opacity: 1;
                        transform: translateY(0);
                    }
                }

                @keyframes fadeIn {
                    from {
                        opacity: 0;
                    }
                    to {
                        opacity: 1;
                    }
                }

                .quiz-dashboard {
                    max-width: 1200px;
                    margin: 0 auto;
                    padding: var(--space-xl);
                }

                .class-selection-container {
                    max-width: 800px;
                    margin: 2rem auto;
                    padding: 2rem;
                    animation: slideDown 0.6s ease-out;
                }

                /* Header */
                .header {
                    background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
                    color: white;
                    padding: 30px 0;
                    text-align: center;
                    border-radius: 16px;
                    margin-bottom: 10px;
                    box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
                }

                .header h1 {
                    font-size: 2.5rem;
                    font-weight: 700;
                    margin-bottom: 10px;
                }

                .header p {
                    font-size: 1.1rem;
                    opacity: 0.9;
                    margin: 5px 0;
                }

                /* Cards */
                .card {
                    background: white;
                    border-radius: 16px;
                    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05), 0 4px 6px rgba(0, 0, 0, 0.02);
                    padding: var(--space-xl);
                    margin-bottom: var(--space-xl);
                    transition: transform 0.2s ease, box-shadow 0.2s ease;
                }

                .card:hover {
                    transform: translateY(-2px);
                    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05), 0 10px 15px rgba(0, 0, 0, 0.02);
                }

                .class-selection-form {
                    background: white;
                    padding: 2rem;
                    border-radius: 16px;
                    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.08);
                    animation: fadeIn 0.8s ease-out 0.6s both;
                    transition: all 0.3s ease;
                }

                .class-selection-form:hover {
                    transform: translateY(-2px);
                    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
                }

                .report-h2 {
                    display: flex;
                    align-items: center;
                    gap: var(--space-sm);
                    color: var(--primary-color);
                    border-bottom: 2px solid var(--border-color);
                    padding-bottom: var(--space-md);
                    margin-bottom: var(--space-lg);
                    font-size: 1.5rem;
                    font-weight: 600;
                }

                .form-group {
                    display: flex;
                    align-items: center;
                    gap: 1.5rem;
                    flex-wrap: wrap;
                    margin-bottom: 1.5rem;
                }

                .form-label {
                    font-weight: 600;
                    color: var(--text-color);
                    min-width: 120px;
                    font-size: 1.1rem;
                }

                .form-select {
                    flex: 1;
                    min-width: 250px;
                    padding: 0.75rem 1rem;
                    border: 2px solid var(--border-color);
                    border-radius: 8px;
                    background-color: white;
                    color: var(--text-color);
                    font-size: 1rem;
                    transition: all 0.3s ease;
                    cursor: pointer;
                    font-family: var(--font-primary);
                }

                .form-select:hover {
                    border-color: var(--primary-color);
                }

                .form-select:focus {
                    outline: none;
                    border-color: var(--primary-color);
                    box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.1);
                }

                .form-select option {
                    padding: 0.75rem;
                }

                .output-box {
                    margin-top: 2rem;
                    padding: 1.5rem;
                    background: var(--status-success);
                    border-radius: 12px;
                    border: 2px solid var(--status-success-text);
                    color: var(--text-color);
                    font-weight: 500;
                    font-size: 1rem;
                    display: none;
                    animation: fadeIn 0.8s ease-out;
                    transition: transform 0.3s ease;
                }

                .output-box:hover {
                    transform: translateY(-2px);
                }

                .submit-button {
                    background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
                    color: white;
                    border: none;
                    padding: 0.875rem 2rem;
                    border-radius: 8px;
                    font-size: 1rem;
                    font-weight: 600;
                    cursor: pointer;
                    transition: all 0.3s ease;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    gap: 0.5rem;
                    min-width: 200px;
                    margin: 0 auto;
                    font-family: var(--font-primary);
                }
    
                .submit-button:hover:not(:disabled) {
                    transform: translateY(-2px);
                    box-shadow: 0 8px 15px rgba(13, 148, 136, 0.3);
                }
    
                .submit-button:disabled {
                    background: var(--text-light);
                    cursor: not-allowed;
                    transform: none;
                    box-shadow: none;
                }
    
                .submit-button:active:not(:disabled) {
                    transform: translateY(0);
                }
    
                /* Animation */
                .slide-in {
                    opacity: 0;
                    transform: translateY(20px);
                    transition: all 0.8s ease;
                }
                
                .slide-in.visible {
                    opacity: 1;
                    transform: translateY(0);
                }
    
                @media (max-width: 768px) {
                    .quiz-dashboard {
                        margin: 15px;
                        padding: 20px;
                    }
                    
                    .class-selection-container {
                        margin: 1rem;
                        padding: 1.5rem;
                    }
    
                    .form-group {
                        flex-direction: column;
                        align-items: stretch;
                        gap: 0.75rem;
                    }
    
                    .form-label {
                        min-width: auto;
                    }
    
                    .form-select {
                        min-width: auto;
                    }
    
                    .header h1 {
                        font-size: 1.8rem;
                    }
                }
            </style>
            <div class="quiz-dashboard">
                <!-- Header -->
                <div class="header slide-in">
                    <h1>Past Year Papers Performance Report</h1>
                    <p>Select exam years and papers to analyze performance across multiple topics grouped by tags</p>
                </div>
    
                <div class="class-selection-container">
                    <!-- Main Form Container -->
                    <form method="get" action="" id="pastYearReportForm" class="class-selection-form card slide-in">
                        <input type="hidden" name="id" value="<?php echo htmlspecialchars($courseid); ?>">
                        <input type="hidden" name="fetch" value="past-year">
                        
                        <h2 class="report-h2">
                            <i class="fas fa-file-alt"></i>
                            Generate Tag-based Past Year Papers Report
                        </h2>
                        <!-- Year/Category Selection -->
                        <div class="form-group">
                            <label for="subsectionSelect" class="form-label">Choose Exam Year:</label>
                            <select id="subsectionSelect" class="form-select">
                                <option value="">-- Select Exam Year --</option>
                                <option value="all">Select All Past Year Papers</option>
                                <?php foreach ($pastYearChildren as $child): ?>
                                    <option value="<?= htmlspecialchars($child['id']) ?>">
                                        <?= htmlspecialchars($child['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Paper Selection (Initially Hidden) -->
                        <div class="form-group" id="quizSelectionGroup" style="display: none;">
                            <label for="quizSelect" class="form-label">Choose Paper:</label>
                            <select id="quizSelect" class="form-select">
                                <option value="">-- Select Paper --</option>
                            </select>
                        </div>

                        <!-- Hidden input to store selected quiz IDs -->
                        <input type="hidden" name="selected_quizzes" id="selectedQuizzes">

                        <!-- Submit Button -->
                        <div class="form-group">
                            <button type="submit" id="submitBtn" class="submit-button" disabled>
                                <i class="fas fa-chart-bar"></i>
                                Generate Past Year Papers Performance Report
                            </button>
                        </div>

                        <!-- Output Display -->
                        <div id="output" class="output-box"></div>
                    </form>
                </div>
            </div>
            <!-- Add Font Awesome if not already included -->
            <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
            <script>
                const pastYearChildrenData = <?= json_encode($pastYearChildren) ?>;
                const subsectionSelect = document.getElementById("subsectionSelect");
                const quizSelect = document.getElementById("quizSelect");
                const quizSelectionGroup = document.getElementById("quizSelectionGroup");
                const output = document.getElementById("output");
                const submitBtn = document.getElementById("submitBtn");
                const form = document.getElementById("pastYearReportForm");
                const selectedQuizzesInput = document.getElementById("selectedQuizzes");
    
                let selectedQuizIds = [];
    
                subsectionSelect.addEventListener("change", function() {
                    // Reset quiz selection
                    quizSelect.innerHTML = '<option value="">-- Select Paper --</option>';
                    quizSelectionGroup.style.display = "none";
                    output.style.display = "none";
                    submitBtn.disabled = true;
                    selectedQuizIds = [];
                    
                    const selectedValue = this.value;
                    
                    if (selectedValue === "all") {
                        // Collect all quiz IDs from all exam years
                        pastYearChildrenData.forEach(examYear => {
                            if (examYear.activities && Array.isArray(examYear.activities)) {
                                const papers = examYear.activities.filter(a => 
                                    a.modname === 'quiz' && a.visible === '1'
                                );
                                papers.forEach(paper => {
                                    selectedQuizIds.push(paper.id);
                                });
                            }
                        });
                        
                        if (selectedQuizIds.length > 0) {
                            selectedQuizzesInput.value = selectedQuizIds.join(',');
                            output.innerHTML = `
                                <div style="display: flex; align-items: center; gap: 0.75rem;">
                                    <i class="fas fa-check-circle" style="color: #16A34A; font-size: 1.25rem;"></i>
                                    <div>
                                        <strong>All Past Year Papers Selected</strong><br>
                                        <small style="color: #64748B;">Found ${selectedQuizIds.length} papers across all exam years - Will be grouped by topic tags</small>
                                    </div>
                                </div>
                            `;
                            output.style.display = "block";
                            submitBtn.disabled = false;
                        } else {
                            output.innerHTML = `
                                <div style="display: flex; align-items: center; gap: 0.75rem; background: #FEE2E2; color: #EF4444; padding: 1rem; border-radius: 8px;">
                                    <i class="fas fa-exclamation-circle" style="font-size: 1.25rem;"></i>
                                    <div>No visible papers found in any exam year sections</div>
                                </div>
                            `;
                            output.style.display = "block";
                        }
                    } else if (selectedValue) {
                        // Show paper selection for specific exam year
                        const selectedExamYear = pastYearChildrenData.find(s => s.id === selectedValue);
                        if (selectedExamYear && selectedExamYear.activities) {
                            const papers = selectedExamYear.activities.filter(a => 
                                a.modname === 'quiz' && a.visible === '1'
                            );
                            
                            if (papers.length > 0) {
                                // Add "Select All" option for this exam year
                                const selectAllOption = document.createElement("option");
                                selectAllOption.value = "all_examyear";
                                selectAllOption.textContent = `Select All Papers from ${selectedExamYear.name}`;
                                quizSelect.appendChild(selectAllOption);
                                
                                // Add individual paper options
                                papers.forEach(paper => {
                                    const opt = document.createElement("option");
                                    opt.value = paper.id;
                                    opt.textContent = paper.name;
                                    quizSelect.appendChild(opt);
                                });
                                
                                quizSelectionGroup.style.display = "flex";
                            } else {
                                output.innerHTML = `
                                    <div style="display: flex; align-items: center; gap: 0.75rem; background: #FEE2E2; color: #EF4444; padding: 1rem; border-radius: 8px;">
                                        <i class="fas fa-exclamation-circle" style="font-size: 1.25rem;"></i>
                                        <div>No visible papers found in ${selectedExamYear.name}</div>
                                    </div>
                                `;
                                output.style.display = "block";
                            }
                        }
                    }
                });

                quizSelect.addEventListener("change", function() {
                    const selectedValue = this.value;
                    const subsectionValue = subsectionSelect.value;
                    
                    if (selectedValue === "all_examyear") {
                        // Select all papers in the current exam year
                        const selectedExamYear = pastYearChildrenData.find(s => s.id === subsectionValue);
                        if (selectedExamYear && selectedExamYear.activities) {
                            const papers = selectedExamYear.activities.filter(a => 
                                a.modname === 'quiz' && a.visible === '1'
                            );
                            
                            selectedQuizIds = papers.map(q => q.id);
                            selectedQuizzesInput.value = selectedQuizIds.join(',');
                            
                            output.innerHTML = `
                                <div style="display: flex; align-items: center; gap: 0.75rem;">
                                    <i class="fas fa-check-circle" style="color: #16A34A; font-size: 1.25rem;"></i>
                                    <div>
                                        <strong>All Papers Selected from ${selectedExamYear.name}</strong><br>
                                        <small style="color: #64748B;">${selectedQuizIds.length} papers will be analyzed and grouped by topic tags</small>
                                    </div>
                                </div>
                            `;
                            output.style.display = "block";
                            submitBtn.disabled = false;
                        }
                    } else if (selectedValue) {
                        // Single paper selected
                        selectedQuizIds = [selectedValue];
                        selectedQuizzesInput.value = selectedValue;
                        
                        const paperName = this.options[this.selectedIndex].text;
                        submitBtn.disabled = false;
                    } else {
                        output.style.display = "none";
                        submitBtn.disabled = true;
                        selectedQuizIds = [];
                        selectedQuizzesInput.value = '';
                    }
                });

                // Handle form submission
                form.addEventListener("submit", function(e) {
                    if (selectedQuizIds.length === 0) {
                        e.preventDefault();
                        alert("Please select at least one paper before submitting.");
                        return;
                    }

                    // Form will submit with the selected_quizzes parameter
                });

                // Scroll animations
                const observer = new IntersectionObserver((entries) => {
                    entries.forEach(entry => {
                        if (entry.isIntersecting) {
                            entry.target.classList.add("visible");
                        }
                    });
                }, {
                    threshold: 0.1
                });

                document.querySelectorAll(".slide-in").forEach(elem => {
                    observer.observe(elem);
                });
            </script>
        <?php else: ?>
            <div style="max-width: 1200px; margin: 40px auto; padding: 0 20px; font-family: 'Inter', system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; text-align: center; color: #64748B;">
                No past year papers are available for this course yet.
            </div>
        <?php endif;

        exit;
    }
    
    if (isset($_GET['fetch']) && $_GET['fetch'] === 'olympiad' && !empty($_GET['id'])) {
        require_once('quizdataprocessor.php');
        $student_id = $USER->id;
        $courseid = $_GET['id'];
        $course_structure = get_course_structure($courseid);
        $mainSection = null;
        echo '<script>console.log("course_structure:", ' . json_encode($course_structure) . ')</script>';

        // Build an array of sections that have visible activities (directly or in children)
        $sections_with_activities = [];

        if (isset($course_structure['sections']) && is_array($course_structure['sections'])) {
            foreach ($course_structure['sections'] as $section) {
                // Check if the section has at least one visible activity
                $sectionHasVisibleActivity = false;
                if (isset($section['activities']) && is_array($section['activities'])) {
                    foreach ($section['activities'] as $activity) {
                        if (isset($activity['visible']) && $activity['visible'] === '1' && isset($activity['modname']) && $activity['modname'] === 'quiz') {
                            $sectionHasVisibleActivity = true;
                            break;
                        }
                    }
                }
                // If not found in this section, check if any child has at least one visible activity
                // if (!$sectionHasVisibleActivity && isset($section['children']) && is_array($section['children'])) {
                //     foreach ($section['children'] as $child) {
                //         if (isset($child['activities']) && is_array($child['activities'])) {
                //             foreach ($child['activities'] as $activity) {
                //                 if (isset($activity['visible']) && $activity['visible'] === '1' && isset($activity['modname']) && $activity['modname'] === 'quiz') {
                //                     $sectionHasVisibleActivity = true;
                //                     break 2; // Found in a child, no need to keep searching
                //                 }
                //             }
                //         }
                //     }
                // }
                if ($sectionHasVisibleActivity) {
                    $sections_with_activities[] = $section;
                } else if (isset($section['children']) && is_array($section['children'])) {
                    // Add the child itself if it has at least one visible activity and wasn't already added via parent
                    foreach ($section['children'] as $child) {
                        $childHasVisibleActivity = false;
                        if (isset($child['activities']) && is_array($child['activities'])) {
                            foreach ($child['activities'] as $activity) {
                                if (isset($activity['visible']) && $activity['visible'] === '1') {
                                    $childHasVisibleActivity = true;
                                    break;
                                }
                            }
                        }
                        if ($childHasVisibleActivity) {
                            $sections_with_activities[] = $child; // Add child individually if it has at least one visible activity
                        }
                    }
                }
            }
        }

        echo '<script>console.log("sections_with_activities:", ' . json_encode($sections_with_activities) . ')</script>';

        // foreach ($course_structure["sections"] as $section) {
        //     if (strtolower($section["name"]) === strtolower("Statistics")) { ///change this
        //         $mainSection = $section;
        //         break;
        //     }
        // }
        // if (!$mainSection) die("Random Bag Test not found.");
        // $children = $mainSection["children"];
        
        if(isset($_GET['fetch']) && $_GET['fetch'] === 'olympiad' && !empty($_GET['id']) && $_GET['quiz_id']){
            $student_id = $USER->id;
            $quiz_cmid = $_GET['quiz_id'];
            $courseid = $_GET['id'];
            
            // Initialize components with the enhanced renderer
            require_once('integrated_quiz_renderer.php'); // Include the new integrated renderer
            $renderer = new enhanced_quiz_question_renderer($PAGE, '');
            $processor = new quiz_attempt_data_processor();
            
            // Get student information
            $student = $DB->get_record('user', ['id' => $student_id]);
            if (!$student) {
                echo $OUTPUT->notification('Student not found.', 'notifyerror');
                exit;
            }
            
            // Get quiz information  
            $quiz_sql = "
                SELECT 
                    q.id as quiz_id,
                    q.name as quiz_name,
                    q.intro,
                    q.grade as max_grade,
                    q.sumgrades,
                    cm.id as cmid
                FROM {quiz} q
                JOIN {course_modules} cm ON cm.instance = q.id AND cm.module = (SELECT id FROM {modules} WHERE name = 'quiz')
                WHERE cm.id = :quiz_cmid AND cm.course = :courseid
            ";
            
            $quiz_info = $DB->get_record_sql($quiz_sql, [
                'quiz_cmid' => $quiz_cmid,
                'courseid' => $courseid
            ]);
            
            if (!$quiz_info) {
                echo $OUTPUT->notification('Quiz not found.', 'notifyerror');
                exit;
            }
            
            // Get student's latest quiz attempt
            $latest_attempt = $DB->get_record_sql("
                SELECT id as attempt_id, attempt, state, timestart, timefinish, sumgrades
                FROM {quiz_attempts}
                WHERE quiz = :quiz_id AND userid = :student_id AND preview = 0
                ORDER BY attempt DESC
                LIMIT 1
            ", [
                'quiz_id' => $quiz_info->quiz_id,
                'student_id' => $student_id
            ]);
            
            if (!$latest_attempt) {
                echo '<div class="quiz-dashboard"><div class="no-data-message card">No quiz attempts found for this student.</div></div>';
                exit;
            }

            // Use data processor to get comprehensive question data
            $questions = $processor->get_attempt_questions($latest_attempt->attempt_id);

            // Group all questions directly by tags (no categories)
            $tag_groups = $processor->group_all_by_tags($questions);
            echo '<script>console.log("tag_groups:", ' . json_encode($tag_groups) . ')</script>';
            // Calculate overall statistics from tag groups
            $overall_stats = $processor->calculate_overall_stats_from_tags($tag_groups);


            // Determine color based on percentage
            $percentage = $overall_stats['percentage'];
            $score_color = '#EF4444'; // Default poor color

            if ($percentage >= 80) {
                $score_color = '#16A34A'; // Excellent (green)
            } elseif ($percentage >= 40) {
                $score_color = '#D97706'; // Average (orange)
            }
            // Calculate best and worst performing tag groups
            $tag_performance = [];

            foreach ($tag_groups as $tag_name => $tag_data) {
                if ($tag_name === 'No Tags') continue;
                $tag_performance[] = [
                    'name' => $tag_name,
                    'percentage' => $tag_data['percentage'],
                    'correct_answers' => $tag_data['correct_answers'],
                    'total_questions' => $tag_data['total_questions'],
                    'wrong_answers' => $tag_data['wrong_answers']
                ];
            }

            usort($tag_performance, function($a, $b) {
                return $b['percentage'] <=> $a['percentage'];
            });

            $total_tags = count($tag_performance);

            if ($total_tags <= 1) {
                if ($total_tags === 1) {
                    if ($tag_performance[0]['percentage'] >= 40) {
                        $best_categories = $tag_performance;
                        $worst_categories = [];
                    } else {
                        $best_categories = [];
                        $worst_categories = $tag_performance;
                    }
                } else {
                    $best_categories = [];
                    $worst_categories = [];
                }
            } elseif ($total_tags <= 4) {
                $mid = ceil($total_tags / 2);
                $best_categories = array_slice($tag_performance, 0, $mid);
                $worst_categories = array_reverse(array_slice($tag_performance, $mid));
            } else {
                $best_categories = array_slice($tag_performance, 0, 3);
                $worst_categories = array_slice(array_reverse($tag_performance), 0, 3);
            }

            // A 0% topic can't be a "best" area and a 100% topic can't be a "worst" area.
            $best_categories = array_values(array_filter($best_categories, function($t) {
                return $t['percentage'] > 0;
            }));
            $worst_categories = array_values(array_filter($worst_categories, function($t) {
                return $t['percentage'] < 100;
            }));
            ?>
            <!DOCTYPE html>
            <html lang="en">
            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <title>Quiz Performance Report - <?= htmlspecialchars($student->firstname . ' ' . $student->lastname) ?></title>
                <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
                <style>
                    @import url("https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap");

                    :root {
                        --primary-color: #0D9488;
                        --secondary-color: #7b1fa2;
                        --status-error: #FEE2E2;
                        --status-error-text: #EF4444;
                        --status-success: #DCFCE7;
                        --status-success-text: #16A34A;
                        --status-warning: #FEF3C7;
                        --status-warning-text: #D97706;
                        --text-color: #334155;
                        --text-light: #64748B;
                        --background-color: #F8FAFC;
                        --border-color: #E2E8F0;
                        --space-xs: 0.25rem;
                        --space-sm: 0.5rem;
                        --space-md: 1rem;
                        --space-lg: 1.5rem;
                        --space-xl: 2rem;
                        --font-primary: 'Inter', system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                    }
                    * { margin: 0; padding: 0; box-sizing: border-box; }
                    body {
                        background-color: var(--background-color);
                        font-family: var(--font-primary);
                        color: var(--text-color);
                        line-height: 1.5;
                        min-height: 100vh;
                    }
                    .quiz-dashboard {
                        max-width: 1200px;
                        margin: 0 auto;
                        padding: var(--space-xl);
                    }

                    /* Header */
                    .header {
                        background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
                        color: white;
                        padding: 30px 0;
                        text-align: center;
                        border-radius: 16px;
                        margin-bottom: 10px;
                        box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
                    }

                    .header h1 {
                        font-size: 2.5rem;
                        font-weight: 700;
                        margin-bottom: 10px;
                    }

                    .header p {
                        font-size: 1.1rem;
                        opacity: 0.9;
                        margin: 5px 0;
                    }

                    
                    .tag-badge {
                        background: linear-gradient(135deg, #f0f9ff, #e0f2fe);
                        color: #0369a1;
                        padding: 0.25rem 0.75rem;
                        border-radius: 20px;
                        font-size: 0.8rem;
                        font-weight: 600;
                        border: 1px solid #bae6fd;
                        text-transform: capitalize;
                    }

                    /* Cards */
                    .card {
                        background: white;
                        border-radius: 16px;
                        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05), 0 4px 6px rgba(0, 0, 0, 0.02);
                        padding: var(--space-xl);
                        margin-bottom: var(--space-xl);
                        transition: transform 0.2s ease, box-shadow 0.2s ease;
                    }

                    .card:hover {
                        transform: translateY(-2px);
                        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05), 0 10px 15px rgba(0, 0, 0, 0.02);
                    }

                    /* Course Info Card */
                    .course-info {
                        border-top-left-radius: 0;
                        border-top-right-radius: 0;
                    }

                    .report-h2 {
                        display: flex;
                        align-items: center;
                        gap: var(--space-sm);
                        color: var(--primary-color);
                        border-bottom: 2px solid var(--border-color);
                        padding-bottom: var(--space-md);
                        margin-bottom: var(--space-lg);
                        font-size: 1.5rem;
                        font-weight: 600;
                    }

                    .info-grid {
                        display: grid;
                        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
                        gap: 15px;
                    }

                    .info-item {
                        background-color: #f0f4f8;
                        padding: 12px;
                        border-radius: 8px;
                        display: flex;
                        align-items: center;
                        justify-content: space-between;
                        transition: all 0.3s ease;
                        text-transform: capitalize;
                    }

                    .info-item:hover {
                        background-color: #e6eaf0;
                        transform: translateX(5px);
                    }

                    .info-item strong {
                        color: var(--primary-color);
                        font-weight: 600;
                    }

                    /* Performance Summary Card */
                    .performance-summary .summary-content {
                        background: white;
                        border: 1px solid var(--border-color);
                        border-left: 4px solid var(--primary-color);
                        padding: var(--space-lg);
                        border-radius: 8px;
                        margin: var(--space-lg) 0;
                    }

                    .performance-summary .summary-content p {
                        margin: 10px 0;
                        line-height: 1.6;
                        color: var(--text-light);
                    }

                    .performance-summary .summary-content strong {
                        color: var(--text-color);
                        font-weight: 600;
                    }

                    .text-large {
                        font-size: 1rem;
                        line-height: 1.6;
                        color: var(--text-light);
                    }

                    /* Stats Grid */
                    .stats-grid {
                        display: grid;
                        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
                        gap: 20px;
                        margin-bottom: 30px;
                    }
                    
                    .stat-card {
                        background: white;
                        border-radius: 16px;
                        padding: 24px;
                        text-align: center;
                        box-shadow: 0 4px 16px rgba(0, 0, 0, 0.08);
                        transition: transform 0.2s ease, box-shadow 0.2s ease;
                    }

                    .stat-card:hover {
                        transform: translateY(-2px);
                        box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
                    }
                    
                    .stat-number {
                        font-size: 2.5rem;
                        font-weight: 700;
                        margin-bottom: 8px;
                    }
                    
                    .stat-label {
                        color: var(--text-light);
                        font-weight: 500;
                        text-transform: uppercase;
                        font-size: 0.85rem;
                        letter-spacing: 0.5px;
                    }

                    /* Performance Color Guide */
                    .performance-info .info-grid {
                        grid-template-columns: repeat(3, 1fr);
                        gap: 10px;
                    }

                    .performance-info .info-item {
                        text-align: center;
                        padding: 15px;
                        border-radius: 8px;
                        font-weight: bold;
                        justify-content: center;
                    }

                    /* Categories Table */
                    .categories-table {
                        background: white;
                        border-radius: 16px;
                        overflow: hidden;
                        box-shadow: 0 4px 16px rgba(0, 0, 0, 0.08);
                        margin-bottom: 20px;
                    }

                    .categories-table table {
                        width: 100%;
                        border-collapse: collapse;
                        table-layout: fixed;
                    }
                    .categories-table th,
                    .categories-table td {
                        /* Set explicit widths for columns to preserve layout, can adjust as needed */
                    }
                    .categories-table th:nth-child(1),
                    .categories-table td:nth-child(1) { width: 30%; min-width: 180px; max-width: 350px; }
                    .categories-table th:nth-child(2),
                    .categories-table td:nth-child(2) { width: 15%; min-width: 90px; text-align: right;}
                    .categories-table th:nth-child(3),
                    .categories-table td:nth-child(3) { width: 15%; min-width: 90px; text-align: right;}
                    .categories-table th:nth-child(4),
                    .categories-table td:nth-child(4) { width: 15%; min-width: 90px; text-align: right;}
                    .categories-table th:nth-child(5),
                    .categories-table td:nth-child(5) { width: 15%; min-width: 90px; text-align: right;}

                    .categories-table th {
                        background: var(--primary-color);
                        color: white;
                        padding: 20px;
                        text-align: left;
                        font-weight: 600;
                        font-size: 1rem;
                        overflow: hidden;
                        text-overflow: ellipsis;
                        white-space: nowrap;
                    }
                    .categories-table td {
                        padding: 16px 20px;
                        border-bottom: 1px solid var(--border-color);
                        vertical-align: middle;
                    }
                    .categories-table td:first-child {
                        white-space: normal;
                        overflow: visible;
                    }
                    .categories-table td:not(:first-child) {
                        overflow: hidden;
                        text-overflow: ellipsis;
                        white-space: nowrap;
                    }
                    /* Nested row UI improvements */
                    .expandable-row {
                        cursor: pointer;
                        transition: background 0.16s;
                    }
                    .expandable-row:hover {
                        background-color: #f1f5f9 !important;
                    }
                    .expandable-row.expanded {
                        background-color: #f1f5f9 !important;
                    }
                    .section-name {
                        font-weight: 600;
                        display: flex;
                        align-items: center;
                        gap: 0.75rem;
                        padding: 0.5rem 0;
                    }
                    .expand-arrow {
                        transition: transform 0.3s ease;
                        display: inline-block;
                        width: 1.25em;
                        text-align: center;
                        margin-right: 0.5em;
                        color: var(--primary-color);
                    }
                    .expandable-row.expanded .expand-arrow {
                        transform: rotate(90deg);
                    }
                    /* Tag group visual indent */
                    .tag-group-row > td {
                        padding-left: 56px !important;
                        background-color: #f9fafc !important;
                        border-bottom: 1px solid var(--border-color);
                    }
                    /* Question container visuals */
                    .questions-container {
                        display: none;
                        background: var(--background-color);
                        border-top: 1px solid var(--border-color);
                        padding: 0;
                    }
                    .questions-container.show {
                        display: block;
                        animation: fadeInOpen 0.25s;
                    }
                    @keyframes fadeInOpen {
                        from { opacity: 0; transform: translateY(-15px);}
                        to { opacity: 1; transform: none;}
                    }
                    .questions-content {
                        padding: 0;
                    }
                    .chip {
                        display: inline-block;
                        min-width: 2.5em;
                        text-align: center;
                        padding: 0.4em 0.9em;
                        border-radius: 999px;
                        font-size: 0.93em;
                        font-weight: 600;
                        margin-right: 0.35em;
                    }
                    .no-tags { color: #888; font-style: italic; }
                    .enhanced-question {
                        background: white;
                        border-radius: 12px;
                        padding: 20px;
                        margin-bottom: 16px;
                        border-left: 4px solid transparent;
                        transition: all 0.3s ease;
                        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
                    }
                    .enhanced-question.correct {
                        border-left-color: var(--status-success-text);
                        background: linear-gradient(135deg, var(--status-success), #f0fdf4);
                    }
                    .enhanced-question.wrong {
                        border-left-color: var(--status-error-text);
                        background: linear-gradient(135deg, var(--status-error), #fef7f7);
                    }
                    .enhanced-question.partial-correct {
                        border-left-color: #F59E0B;
                        background: linear-gradient(135deg, var(--status-warning), #fefce8);
                    }
                    .question-metadata {
                        background: rgba(0,0,0,0.03);
                        padding: 8px 12px;
                        border-radius: 6px;
                        margin-bottom: 12px;
                        font-size: 0.85rem;
                        color: var(--text-light);
                    }
                    .status-badge {
                        display: inline-block;
                        padding: 6px 12px;
                        border-radius: 20px;
                        font-size: 0.75rem;
                        font-weight: 600;
                        text-transform: uppercase;
                        letter-spacing: 0.5px;
                        margin-top: 12px;
                    }
                    .status-correct { background: var(--status-success); color: var(--status-success-text); }
                    .status-wrong { background: var(--status-error); color: var(--status-error-text); }
                    .status-partial { background: var(--status-warning); color: var(--status-warning-text); }
                    /* Responsive tweaks */
                     /* Charts Container */
                     .charts-container {
                        background: white;
                        border-radius: 16px;
                        padding: 30px;
                        margin-bottom: 20px;
                        box-shadow: 0 4px 16px rgba(0, 0, 0, 0.08);
                        text-align: center;
                    }

                    .charts-container canvas {
                        max-width: 400px;
                        max-height: 400px;
                        margin: 0 auto;
                        display: block;
                    }
                    @media (max-width: 768px) {
                        .quiz-dashboard { margin: 15px; padding: 20px; }
                        .header h1 { font-size: 1.8rem; }
                        .categories-table th, .categories-table td { padding: 12px 8px; }
                        .tag-group-row > td { padding-left: 30px !important; }
                    }
                    @media (max-width: 480px) {
                        .categories-table th, .categories-table td { font-size: 0.98em; }
                        .tag-group-row > td { padding-left: 14px !important; }
                    }

                    /* Quiz name rendered as a link through to the quiz activity */
                    .quiz-name-link { color: inherit; text-decoration: underline; text-underline-offset: 2px; }
                    .quiz-name-link:hover, .quiz-name-link:focus { opacity: 0.85; }
                </style>
            </head>
            <body>
                <div class="quiz-dashboard">
                    <!-- Header -->
                    <div class="header slide-in">
                        <h1>Quiz Performance Report</h1>
                        <p><?= gradereport_user_quiz_name_link($quiz_info->quiz_name, $quiz_info->cmid, $quiz_info->quiz_id) ?></p>
                        <p>Student: <?= htmlspecialchars($student->firstname . ' ' . $student->lastname) ?></p>
                    </div>
                    
                    <!-- Course and Student Information -->
                    <div class="course-info card slide-in">
                        <h2 class="report-h2"><i class="fas fa-graduation-cap"></i> Course Details</h2>
                        <div class="info-grid">
                            <div class="info-item"><strong>Quiz:</strong> <?= gradereport_user_quiz_name_link($quiz_info->quiz_name, $quiz_info->cmid, $quiz_info->quiz_id) ?></div>
                            <div class="info-item"><strong>Student ID:</strong> <?= htmlspecialchars($student_id) ?></div>
                            <div class="info-item"><strong>Course ID:</strong> <?= htmlspecialchars($courseid) ?></div>
                            <div class="info-item "><strong>Status:</strong> <?= htmlspecialchars($latest_attempt->state) ?></div>
                        </div>
                    </div>

                   <!-- Performance Color Guide -->
                    <div class="performance-info card slide-in">
                        <h2 class="report-h2"><i class="fas fa-info-circle"></i> Performance Color Code</h2>
                        <div class="info-grid">
                            <div class="info-item" style="background-color: #FEE2E2; color: #EF4444;">
                                0-39% Poor
                            </div>
                            <div class="info-item" style="background-color: #FEF3C7; color: #D97706;">
                                40-79% Average
                            </div>
                            <div class="info-item" style="background-color: #DCFCE7; color: #16A34A;">
                                80-100% Excellent
                            </div>
                        </div>
                    </div>
                    
                    <!-- Overall Statistics -->
                    <div class="stats-grid slide-in">
                        <div class="stat-card">
                            <div class="stat-number"><?= $overall_stats['total_questions'] ?></div>
                            <div class="stat-label">Total Questions</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-number" style="color: var(--status-success-text);"><?= $overall_stats['total_correct'] ?></div>
                            <div class="stat-label">Correct</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-number" style="color: var(--status-error-text);"><?= $overall_stats['total_wrong'] ?></div>
                            <div class="stat-label">Wrong</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-number" style="color: <?= $score_color ?>;"><?= $overall_stats['percentage'] ?>%</div>
                            <div class="stat-label">Score</div>
                        </div>
                    </div>
                    <!-- Performance Highlights -->
                    <div class="performance-highlights card slide-in">
                        <h2 class="report-h2">
                            <i class="fas fa-chart-line"></i> 
                            Performance Highlights
                        </h2>
                        
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px; margin-top: 20px;">
                            
                            <!-- Best Performing Categories -->
                            <div class="best-categories">
                                <h3 style="color: #059669; margin-bottom: 15px; display: flex; align-items: center; gap: var(--space-sm); font-size: 1.2rem; font-weight: 600;">
                                    <i class="fas fa-trophy" style="color: #059669;"></i>
                                    Top Performing Tags
                                </h3>
                                
                                <?php if (!empty($best_categories)): ?>
                                    <div class="categories-list">
                                        <?php foreach ($best_categories as $index => $category): ?>
                                            <div class="info-item" style="background-color: #F0FDF4; border-left: 4px solid #059669; display: block; margin-bottom: var(--space-md);">
                                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: var(--space-sm);">
                                                    <div style="display: flex; align-items: center; gap: var(--space-sm);">
                                                        <span style="background: #059669; color: white; width: 28px; height: 28px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 0.9em; font-weight: 700;">
                                                            <?php echo ($index + 1); ?>
                                                        </span>
                                                        <span style="font-weight: 600; color: var(--text-color); display: flex; flex-wrap: wrap; gap: 4px; align-items: center;">
                                                            <?php
                                                                $tags = explode(' ; ', $category['name']);
                                                                foreach ($tags as $tag) {
                                                                    $clean_tag = trim($tag);
                                                                    echo '<span class="tag-badge" style="margin-bottom: 2px;">' . htmlspecialchars($clean_tag) . '</span>';
                                                                }
                                                            ?>
                                                        </span>
                                                    </div>
                                                    <span style="background-color: #D1FAE5; color: #065F46; padding: 6px 12px; border-radius: 16px; font-size: 0.9em; font-weight: 600;">
                                                        <?php echo number_format($category['percentage'], 1); ?>%
                                                    </span>
                                                </div>
                                                <div style="font-size: 0.85rem; color: var(--text-light); display: flex; gap: var(--space-lg); margin-left: 36px;">
                                                    <span><i class="fas fa-check-circle" style="color: #059669;"></i> <?php echo $category['correct_answers']; ?> correct</span>
                                                    <span><i class="fas fa-question-circle"></i> <?php echo $category['total_questions']; ?> total</span>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="info-item" style="background-color: var(--background-color); border: 1px solid var(--border-color); color: var(--text-light); font-style: italic; justify-content: center;">
                                        <i class="fas fa-info-circle"></i> Not enough data to show top categories yet.
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Worst Performing Categories -->
                            <div class="worst-categories">
                                <h3 style="color: #DC2626; margin-bottom: 15px; display: flex; align-items: center; gap: var(--space-sm); font-size: 1.2rem; font-weight: 600;">
                                    <i class="fas fa-exclamation-triangle" style="color: #DC2626;"></i>
                                    Areas Needing Improvement
                                </h3>
                                
                                <?php if (!empty($worst_categories)): ?>
                                    <div class="categories-list">
                                        <?php foreach ($worst_categories as $index => $category): ?>
                                            <div class="info-item" style="background-color: #FEF2F2; border-left: 4px solid #DC2626; display: block; margin-bottom: var(--space-md);">
                                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: var(--space-sm);">
                                                    <div style="display: flex; align-items: center; gap: var(--space-sm);">
                                                        <span style="background: #DC2626; color: white; width: 28px; height: 28px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 0.9em; font-weight: 700;">
                                                            <?php echo ($index + 1); ?>
                                                        </span>
                                                        <span style="font-weight: 600; color: var(--text-color); display: flex; flex-wrap: wrap; gap: 4px; align-items: center;">
                                                            <?php
                                                                $tags = explode(' ; ', $category['name']);
                                                                foreach ($tags as $tag) {
                                                                    $clean_tag = trim($tag);
                                                                    echo '<span class="tag-badge" style="margin-bottom: 2px;">' . htmlspecialchars($clean_tag) . '</span>';
                                                                }
                                                            ?>
                                                        </span>
                                                    </div>
                                                    <span style="background-color: #FECACA; color: #991B1B; padding: 6px 12px; border-radius: 16px; font-size: 0.9em; font-weight: 600;">
                                                        <?php echo number_format($category['percentage'], 1); ?>%
                                                    </span>
                                                </div>
                                                <div style="font-size: 0.85rem; color: var(--text-light); display: flex; gap: var(--space-lg); margin-left: 36px;">
                                                    <span><i class="fas fa-times-circle" style="color: #DC2626;"></i> <?php echo $category['wrong_answers']; ?> incorrect</span>
                                                    <span><i class="fas fa-question-circle"></i> <?php echo $category['total_questions']; ?> total</span>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="info-item" style="background-color: var(--background-color); border: 1px solid var(--border-color); color: var(--text-light); font-style: italic; justify-content: center;">
                                        <i class="fas fa-info-circle"></i> Not enough data to show improvement areas yet.
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Action Items -->
                        <div style="background-color: var(--background-color); border-radius: 8px; padding: var(--space-xl); margin-top: var(--space-xl); border: 1px solid var(--border-color);">
                            <h3 style="color: var(--primary-color); margin-bottom: var(--space-lg); display: flex; align-items: center; gap: var(--space-sm); font-size: 1.2rem; font-weight: 600;">
                                <i class="fas fa-lightbulb"></i>
                                Quick Action Items
                            </h3>
                            
                            <div class="info-grid">
                                <?php if (!empty($best_categories)): ?>
                                    <div class="info-item" style="background: white; border-left: 4px solid #059669; display: block;">
                                        <h4 style="margin: 0 0 var(--space-sm) 0; color: var(--text-color); font-size: 0.95rem; font-weight: 600;">
                                            <i class="fas fa-star" style="color: #059669;"></i>
                                            Build on Strengths
                                        </h4>
                                        <p style="margin: 0; font-size: 0.85rem; color: var(--text-light); line-height: 1.4;">
                                            Continue practicing your strong categories to maintain high performance.
                                        </p>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($worst_categories)): ?>
                                    <div class="info-item" style="background: white; border-left: 4px solid #DC2626; display: block;">
                                        <h4 style="margin: 0 0 var(--space-sm) 0; color: var(--text-color); font-size: 0.95rem; font-weight: 600;">
                                            <i class="fas fa-target" style="color: #DC2626;"></i>
                                            Focus on Weak Areas
                                        </h4>
                                        <p style="margin: 0; font-size: 0.85rem; color: var(--text-light); line-height: 1.4;">
                                            Dedicate extra study time to your lowest performing categories.
                                        </p>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="info-item" style="background: white; border-left: 4px solid var(--primary-color); display: block;">
                                    <h4 style="margin: 0 0 var(--space-sm) 0; color: var(--text-color); font-size: 0.95rem; font-weight: 600;">
                                        <i class="fas fa-redo" style="color: var(--primary-color);"></i>
                                        Regular Practice
                                    </h4>
                                    <p style="margin: 0; font-size: 0.85rem; color: var(--text-light); line-height: 1.4;">
                                        Take practice quizzes regularly to improve consistently.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Performance Chart -->
                    <div class="charts-container card slide-in">
                        <h2 class="report-h2"><i class="fas fa-chart-pie"></i> Overall Performance Graph</h2>
                        <div style="width: 400px; height: 400px; display: flex; justify-content: center; align-items: center; margin: 0 auto;">
                            <canvas id="overallPerformanceChart"></canvas>
                        </div>
                    </div>
                    
                    <!-- Categories Table -->
                    <div class="categories-table card slide-in">
                        <h2 class="report-h2" style="margin: 0 0 20px 0; padding: 20px 20px 0 20px;">
                            <i class="fas fa-tags"></i> Tag-wise Performance Breakdown
                        </h2>
                        <div class="table-responsive">
                            <table class="table">
                                <colgroup>
                                    <col style="width: 30%; min-width:180px; max-width:350px;">
                                    <col style="width: 15%; min-width:90px;">
                                    <col style="width: 15%; min-width:90px;">
                                    <col style="width: 15%; min-width:90px;">
                                    <col style="width: 15%; min-width:90px;">
                                </colgroup>
                                <thead>
                                    <tr>
                                        <th>Tag</th>
                                        <th>Total Questions</th>
                                        <th>Correct Answers</th>
                                        <th>Incorrect Answers</th>
                                        <th>Performance (%)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($tag_groups as $tag_name => $tag_data): ?>
                                        <?php
                                            $tag_id = 'tg_' . md5($tag_name);
                                            $performancePercentage = $tag_data['percentage'];
                                            $color = '#FEE2E2';
                                            $textColor = '#EF4444';
                                            if ($performancePercentage >= 80) {
                                                $color = '#DCFCE7';
                                                $textColor = '#16A34A';
                                            } elseif ($performancePercentage >= 40) {
                                                $color = '#FEF3C7';
                                                $textColor = '#D97706';
                                            }
                                        ?>
                                        <tr class="expandable-row tag-group-row" onclick="toggleTagGroupRow('<?= $tag_id ?>')" id="row-<?= $tag_id ?>">
                                            <td>
                                                <div class="section-name">
                                                    <span class="expand-arrow"><i class="fas fa-chevron-right"></i></span>
                                                    <div class="tag-combination" style="display: flex; flex-wrap: wrap; gap: 6px;">
                                                        <?php if (empty($tag_name) || $tag_name === 'No Tags'): ?>
                                                            <span class="no-tags">Untagged Questions</span>
                                                        <?php else: ?>
                                                            <?php 
                                                            $tags = explode(' ; ', $tag_name);
                                                            foreach ($tags as $tag): 
                                                                $clean_tag = trim($tag);
                                                            ?>
                                                                <span class="tag-badge" style="margin-bottom: 4px;"><?= htmlspecialchars($clean_tag) ?></span>
                                                            <?php endforeach; ?>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </td>
                                            <td style="text-align:right;"><?= $tag_data['total_questions'] ?></td>
                                            <td style="text-align:right;"><?= $tag_data['correct_answers'] ?></td>
                                            <td style="text-align:right;"><?= $tag_data['wrong_answers'] ?></td>
                                            <td style="text-align:right;">
                                                <span class="chip" style="background-color: <?= $color ?>; color: <?= $textColor ?>;">
                                                    <?= $performancePercentage ?>%
                                                </span>
                                            </td>
                                        </tr>
                                        <tr class="tag-group-nested" id="nested-<?= $tag_id ?>">
                                            <td colspan="5" style="padding:0;">
                                                <div class="questions-container" id="<?= $tag_id ?>">
                                                    <div class="questions-content" style="padding:40px 40px 10px 40px;">
                                                        <?php foreach ($tag_data['questions'] as $question): ?>
                                                            <?php
                                                                $attempt_data = (object)[
                                                                    'fraction' => $question->fraction,
                                                                    'stepdata_value' => $processor->extract_primary_answer($question),
                                                                    'state' => $question->state
                                                                ];
                                                                echo $renderer->render_question_report($question, $attempt_data, true);
                                                            ?>
                                                        <?php endforeach; ?>
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                
                <!-- Include Chart.js --><script>window.__amdDefine=window.define;window.define=undefined;</script>
                <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
                <script>window.define=window.__amdDefine;delete window.__amdDefine;</script>
                <script>
                    function toggleTagGroupRow(tagGroupId) {
                        const row = document.getElementById('row-' + tagGroupId);
                        const container = document.getElementById(tagGroupId);
                        const nestedRow = document.getElementById('nested-' + tagGroupId);
                        if (!container) return;
                        const isOpen = container.classList.contains('show');
                        // Close open siblings (other tag_groups at this level)
                        let siblingRows = row.parentElement.querySelectorAll('.expandable-row.tag-group-row');
                        siblingRows.forEach(function(tr){
                            if(tr.id !== row.id){ tr.classList.remove('expanded');}
                        });
                        let siblingContainers = container.parentElement.querySelectorAll('.questions-container');
                        siblingContainers.forEach(function(div){
                            if(div.id !== tagGroupId){ div.classList.remove('show'); }
                        });
                        if (isOpen) {
                            container.classList.remove('show');
                            row.classList.remove('expanded');
                        } else {
                            container.classList.add('show');
                            row.classList.add('expanded');
                        }
                    }
                    document.addEventListener("DOMContentLoaded", function() {
                        // Overall Performance Chart
                        const overallCtx = document.getElementById("overallPerformanceChart").getContext("2d");
                        new Chart(overallCtx, {
                            type: "pie",
                            data: {
                                labels: ["Correct", "Incorrect"],
                                datasets: [{
                                    data: [<?= $overall_stats['total_correct'] ?>, <?= $overall_stats['total_wrong'] ?>],
                                    backgroundColor: ['#BAE1FF', '#FF8B94'],
                                    borderWidth: 2,
                                    borderColor: "#ffffff"
                                }]
                            },
                            options: {
                                responsive: true,
                                plugins: {
                                    legend: {
                                        position: "top",
                                        labels: {
                                            font: {
                                                size: 14,
                                                weight: "bold"
                                            }
                                        }
                                    },
                                    title: {
                                        display: true,
                                        text: "Overall Performance",
                                        font: {
                                            size: 18,
                                            weight: "bold"
                                        }
                                    }
                                }
                            }
                        });

                        // Category toggle functionality
                        function toggleCategoryRow(categoryId) {
                            const row = document.getElementById('row-' + categoryId);
                            const container = document.getElementById(categoryId);
                            
                            if (container.classList.contains('show')) {
                                container.classList.remove('show');
                                container.style.display = 'none';
                                row.classList.remove('expanded');
                            } else {
                                // Close other open categories
                                document.querySelectorAll('.questions-container.show').forEach(el => {
                                    el.classList.remove('show');
                                    el.style.display = 'none';
                                });
                                document.querySelectorAll('.section-row.active').forEach(el => {
                                    el.classList.remove('active');
                                });
                                
                                container.style.display = 'block';
                                setTimeout(() => {
                                    container.classList.add('show');
                                }, 10);
                                row.classList.add('active');
                            }
                        }

                        // Make function globally available
                        window.toggleCategoryRow = toggleCategoryRow;

                        // Scroll animations
                        const observer = new IntersectionObserver((entries) => {
                            entries.forEach(entry => {
                                if (entry.isIntersecting) {
                                    entry.target.classList.add("visible");
                                }
                            });
                        }, {
                            threshold: 0.1
                        });

                        document.querySelectorAll(".slide-in").forEach(elem => {
                            observer.observe(elem);
                        });
                    });
                </script>
                </div>
            </body>
            </html>
            
            <?php
            exit;
        }
        
        
        if($sections_with_activities): ?>
            <style>
                @import url("https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap");

                :root {
                    --primary-color: #0D9488;
                    --secondary-color: #7b1fa2;
                    --status-error: #FEE2E2;
                    --status-error-text: #EF4444;
                    --status-success: #DCFCE7;
                    --status-success-text: #16A34A;
                    --status-warning: #FEF3C7;
                    --status-warning-text: #D97706;
                    --text-color: #334155;
                    --text-light: #64748B;
                    --background-color: #F8FAFC;
                    --border-color: #E2E8F0;
                    --space-xs: 0.25rem;
                    --space-sm: 0.5rem;
                    --space-md: 1rem;
                    --space-lg: 1.5rem;
                    --space-xl: 2rem;
                    --font-primary: 'Inter', system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                }

                * {
                    margin: 0;
                    padding: 0;
                    box-sizing: border-box;
                }

                body {
                    background-color: var(--background-color);
                    font-family: var(--font-primary);
                    color: var(--text-color);
                    line-height: 1.5;
                    min-height: 100vh;
                }

                @keyframes slideDown {
                    from {
                        opacity: 0;
                        transform: translateY(-20px);
                    }
                    to {
                        opacity: 1;
                        transform: translateY(0);
                    }
                }
    
                @keyframes fadeIn {
                    from {
                        opacity: 0;
                    }
                    to {
                        opacity: 1;
                    }
                }
    
                .quiz-dashboard {
                    max-width: 1200px;
                    margin: 0 auto;
                    padding: var(--space-xl);
                }

                .class-selection-container {
                    max-width: 800px;
                    margin: 2rem auto;
                    padding: 2rem;
                    animation: slideDown 0.6s ease-out;
                }

                /* Header */
                .header {
                    background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
                    color: white;
                    padding: 30px 0;
                    text-align: center;
                    border-radius: 16px;
                    margin-bottom: 10px;
                    box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
                }

                .header h1 {
                    font-size: 2.5rem;
                    font-weight: 700;
                    margin-bottom: 10px;
                }

                .header p {
                    font-size: 1.1rem;
                    opacity: 0.9;
                    margin: 5px 0;
                }

                /* Cards */
                .card {
                    background: white;
                    border-radius: 16px;
                    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05), 0 4px 6px rgba(0, 0, 0, 0.02);
                    padding: var(--space-xl);
                    margin-bottom: var(--space-xl);
                    transition: transform 0.2s ease, box-shadow 0.2s ease;
                }

                .card:hover {
                    transform: translateY(-2px);
                    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05), 0 10px 15px rgba(0, 0, 0, 0.02);
                }

                .class-selection-form {
                    background: white;
                    padding: 2rem;
                    border-radius: 16px;
                    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.08);
                    animation: fadeIn 0.8s ease-out 0.6s both;
                    transition: all 0.3s ease;
                }

                .class-selection-form:hover {
                    transform: translateY(-2px);
                    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
                }

                .report-h2 {
                    display: flex;
                    align-items: center;
                    gap: var(--space-sm);
                    color: var(--primary-color);
                    border-bottom: 2px solid var(--border-color);
                    padding-bottom: var(--space-md);
                    margin-bottom: var(--space-lg);
                    font-size: 1.5rem;
                    font-weight: 600;
                }

                .form-group {
                    display: flex;
                    align-items: center;
                    gap: 1.5rem;
                    flex-wrap: wrap;
                    margin-bottom: 1.5rem;
                }

                .form-label {
                    font-weight: 600;
                    color: var(--text-color);
                    min-width: 120px;
                    font-size: 1.1rem;
                }

                .form-select {
                    flex: 1;
                    min-width: 250px;
                    padding: 0.75rem 1rem;
                    border: 2px solid var(--border-color);
                    border-radius: 8px;
                    background-color: white;
                    color: var(--text-color);
                    font-size: 1rem;
                    transition: all 0.3s ease;
                    cursor: pointer;
                    font-family: var(--font-primary);
                }

                .form-select:hover {
                    border-color: var(--primary-color);
                }

                .form-select:focus {
                    outline: none;
                    border-color: var(--primary-color);
                    box-shadow: 0 0 0 4px rgba(13, 148, 136, 0.1);
                }

                .form-select option {
                    padding: 0.75rem;
                }

                .output-box {
                    margin-top: 2rem;
                    padding: 1.5rem;
                    background: var(--status-success);
                    border-radius: 12px;
                    border: 2px solid var(--status-success-text);
                    color: var(--text-color);
                    font-weight: 500;
                    font-size: 1rem;
                    display: none;
                    animation: fadeIn 0.8s ease-out;
                    transition: transform 0.3s ease;
                }

                .output-box:hover {
                    transform: translateY(-2px);
                }

                .section-title {
                    color: var(--text-color);
                    font-size: 1.5rem;
                    font-weight: 600;
                    margin-bottom: 2rem;
                    text-align: center;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    gap: 0.75rem;
                }

                .submit-button {
                    background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
                    color: white;
                    border: none;
                    padding: 0.875rem 2rem;
                    border-radius: 8px;
                    font-size: 1rem;
                    font-weight: 600;
                    cursor: pointer;
                    transition: all 0.3s ease;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    gap: 0.5rem;
                    min-width: 200px;
                    margin: 0 auto;
                    font-family: var(--font-primary);
                }

                .submit-button:hover:not(:disabled) {
                    transform: translateY(-2px);
                    box-shadow: 0 8px 15px rgba(13, 148, 136, 0.3);
                }

                .submit-button:disabled {
                    background: var(--text-light);
                    cursor: not-allowed;
                    transform: none;
                    box-shadow: none;
                }

                .submit-button:active:not(:disabled) {
                    transform: translateY(0);
                }

                /* Animation */
                .slide-in {
                    opacity: 0;
                    transform: translateY(20px);
                    transition: all 0.8s ease;
                }
                
                .slide-in.visible {
                    opacity: 1;
                    transform: translateY(0);
                }

                @media (max-width: 768px) {
                    .quiz-dashboard {
                        margin: 15px;
                        padding: 20px;
                    }
                    
                    .class-selection-container {
                        margin: 1rem;
                        padding: 1.5rem;
                    }

                    .form-group {
                        flex-direction: column;
                        align-items: stretch;
                        gap: 0.75rem;
                    }

                    .form-label {
                        min-width: auto;
                    }

                    .form-select {
                        min-width: auto;
                    }

                    .section-title {
                        font-size: 1.25rem;
                    }

                    .header h1 {
                        font-size: 1.8rem;
                    }
                }
            </style>

            <div class="quiz-dashboard">
                <!-- Header -->
                <div class="header slide-in">
                    <h1>Olympiad Quiz Performance Report</h1>
                    <p>Select a section and quiz to view detailed performance analytics</p>
                </div>

                <div class="class-selection-container">
                    <!-- Main Form Container -->
                    <form method="get" action="" id="olympiadForm" class="class-selection-form card slide-in">
                        <input type="hidden" name="id" value="<?php echo htmlspecialchars($courseid); ?>">
                        <input type="hidden" name="fetch" value="olympiad">
                        
                        <h2 class="report-h2">
                            <i class="fas fa-chart-line"></i>
                            Generate Olympiad Quiz Report
                        </h2>

                        <!-- Section Selection -->
                        <div class="form-group">
                            <label for="childSection" class="form-label">Choose Section:</label>
                            <select id="childSection" class="form-select">
                                <option value="">-- Select Section --</option>
                                <?php foreach ($sections_with_activities as $child): ?>
                                    <option value="<?= htmlspecialchars($child['id']) ?>">
                                        <?= htmlspecialchars($child['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Quiz Selection -->
                        <div class="form-group">
                            <label for="quizSelect" class="form-label">Choose Quiz:</label>
                            <select name="quiz_id" id="quizSelect" class="form-select">
                                <option value="">-- Select Quiz --</option>
                            </select>
                        </div>

                        <!-- Submit Button -->
                        <div class="form-group">
                            <button type="submit" id="submitBtn" class="submit-button" disabled>
                                <i class="fas fa-chart-bar"></i>
                                Generate Quiz Report
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Add Font Awesome if not already included -->
            <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

            <script>
                const childrenData = <?= json_encode($sections_with_activities) ?>;
                const childSelect = document.getElementById("childSection");
                const quizSelect = document.getElementById("quizSelect");
                const submitBtn = document.getElementById("submitBtn");
                const form = document.getElementById("randomBagForm");

                childSelect.addEventListener("change", function() {
                    quizSelect.innerHTML = '<option value="">-- Select Quiz --</option>';
                    if (submitBtn) submitBtn.disabled = true;

                    console.log(childrenData);
                    console.log(this.value);
                    
                    const selectedChild = childrenData.find(c => c.id === this.value);
                    if (!selectedChild) return;
                    
                    // Get quizzes from the activities array and filter only visible quizzes
                    let quizzes = [];
                    
                    console.log(selectedChild);

                    if (selectedChild.activities && Array.isArray(selectedChild.activities)) {
                        quizzes = selectedChild.activities.filter(a => 
                            a.modname === 'quiz' && a.visible === '1'
                        );
                    }
                    
                    if (quizzes.length === 0) {
                        const noQuizOption = document.createElement("option");
                        noQuizOption.value = "";
                        noQuizOption.textContent = "No quizzes available in this section";
                        quizSelect.appendChild(noQuizOption);
                        return;
                    }
                    
                    // Add quiz options to dropdown
                    quizzes.forEach(quiz => {
                        const opt = document.createElement("option");
                        opt.value = quiz.id;
                        opt.textContent = quiz.name;
                        quizSelect.appendChild(opt);
                    });
                });

                quizSelect.addEventListener("change", function() {
                    if (!this.value) {
                        if (submitBtn) submitBtn.disabled = true;
                        return;
                    }
                    if (submitBtn) submitBtn.disabled = false;
                });

                // Handle form submission
                if (form) {
                    form.addEventListener("submit", function(e) {
                        e.preventDefault();
                        
                        const quizId = quizSelect.value;
                        
                        if (!quizId) {
                            alert("Please select a quiz before submitting.");
                            return;
                        }
                        
                        // Create URL with only quiz_id
                        const currentUrl = new URL(window.location.href);
                        currentUrl.searchParams.set('id', '<?php echo htmlspecialchars($courseid); ?>');
                        currentUrl.searchParams.set('fetch', 'random');
                        currentUrl.searchParams.set('quiz_id', quizId);
                        
                        // Redirect to the new URL
                        window.location.href = currentUrl.toString();
                    });
                }

                // Scroll animations
                const observer = new IntersectionObserver((entries) => {
                    entries.forEach(entry => {
                        if (entry.isIntersecting) {
                            entry.target.classList.add("visible");
                        }
                    });
                }, {
                    threshold: 0.1
                });

                document.querySelectorAll(".slide-in").forEach(elem => {
                    observer.observe(elem);
                });
            </script>
        <?php endif;

        exit;
    }

    if ($report->fill_table()) {
        echo $report->print_table(true);
    }
}

if (isset($report)) {
    // Trigger report viewed event.
    $report->viewed();
}

echo $OUTPUT->footer();
