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
    $classes_sql_query = '
        SELECT DISTINCT 
            g.id AS groupid, 
            g.name AS groupname, 
            c.id AS cohortid 
        FROM {groups} g
        JOIN {groups_members} gm ON g.id = gm.groupid
        JOIN {role_assignments} ra ON ra.userid = gm.userid
        JOIN {role} r ON r.id = ra.roleid
        JOIN {cohort_members} cm ON cm.userid = gm.userid
        JOIN {cohort} c ON c.id = cm.cohortid
        WHERE g.courseid = :courseid 
        AND ra.userid = :teacherid
        AND r.shortname = :role
    ';
    $classes = $DB->get_records_sql($classes_sql_query, [
        'courseid' => $courseid,
        'teacherid' => $teacher_id,
        'role' => 'teacher'
    ]);
    $cohorts = cohort_get_user_cohorts($teacher_id);
    // Add cohort id detail to classes array
    foreach ($classes as $groupid => $class) {
        // Remove " cohort" from groupname to match with cohort name
        $class_name = str_replace(" cohort", "", $class->groupname);
        // Find matching cohort by name
        foreach ($cohorts as $cohortid => $cohort) {
            if ($cohort->name === $class_name) {
                $classes[$groupid]->cohortid = $cohort->id;
                break;
            }
        }
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
        $selected_class = $classes[$_GET['class_id']];
        $cohortid = $selected_class->cohortid;
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
        echo '</form>';
        echo '</div>';


        if (empty($sections)) {
            echo $OUTPUT->notification('No content has been enabled for this class.', 'notifyinfo');
            echo $OUTPUT->footer();
            exit;
        }

        // Query to get all the students in the class.
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
            AND g.courseid = :courseid
            AND r.name = 'Student'
            AND ctx.contextlevel = 50
        ";

        $params = [
            'groupid' => $_GET['class_id'],
            'courseid' => $courseid
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
        echo '</form>';
        echo '</div>';
    }


    // Only call the API if the fetch parameter is explicitly set
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


    if ($report->fill_table()) {
        echo $report->print_table(true);
    }
}

if (isset($report)) {
    // Trigger report viewed event.
    $report->viewed();
}

echo $OUTPUT->footer();
