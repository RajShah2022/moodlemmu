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

// Teachers will see all student reports.
if (has_capability('moodle/grade:viewall', $context)) {
    // Verify if we are using groups or not.
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
                    $sql = "
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
                               u.id, c.id, qz.id, qa.attempt, qatt.slot;
               ";
                    // Execute the query with the retrieved student ID and course ID
                    $recordset = $DB->get_recordset_sql($sql, ['student_id' => $student_id, 'course_id' => $courseid]);
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
                    echo '<script>';
                    echo 'console.log("This is transformedData: ", ' . $transformedData . ');';
                    echo '</script>';
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

                        echo '<script>';
                        echo 'console.log("This is final newData: ", ' . $jsonNewData . ');';
                        echo '</script>';
                        // Extract necessary data for database entry
                        $userid = $USER->id;
                        $contextid = context_system::instance()->id;
                        $tokensUsage = $responseData['usage'];

                        // Create a new AI-generated report entry in the database
                        $reportId = create_user_ai_report($userid, $courseid, $jsonNewData, $tokensUsage);
                        if ($reportId === false) {
                            debugging('Failed to create AI report for user ID: ' . $userid, DEBUG_NORMAL);
                        }

                        echo generatePerformanceDashboardFromJson($jsonNewData);
                        exit;
                    } catch (Exception $e) {
                        echo '<div class="alert alert-danger" role="alert">';
                        echo '<strong>Error:</strong> There was an issue processing the response. Please try again later.';
                        echo '<br><strong>Details:</strong> ' . htmlspecialchars($e->getMessage());
                        echo '</div>';
                        exit;
                    }
                }
            } else {
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
                echo generatePerformanceDashboardFromJson(json_encode($studentReportDetails['ai_report_data']));
                exit;
            }
        }
        if ($studentReportDetails) {
            $currentDate = new DateTime();
            $reportDate = new DateTime($studentReportDetails['created_at']);
            $interval = $currentDate->diff($reportDate);
            if ($interval->days >= 7) {
                // Allow to create a new report
                echo '<div class="alert alert-info" role="alert" style="display: flex; align-items: center; justify-content: space-between; padding: 15px; border-radius: 5px; background-color: #d9edf7; color: #31708f;">';
                echo '<span style="font-size: 16px; font-weight: bold;">A week has passed. You can create a new report.</span>';
                echo '<button onclick="location.href=\'?fetch=1&id=' . $courseid . '&generate=1\'" class="btn btn-success" style="padding: 8px 16px; font-size: 14px; border-radius: 5px; color: white; border: none; cursor: pointer; transition: background-color 0.3s ease;">Create New Report</button>';
                echo '</div>';
                echo '<div class="report-date-heading" style="font-size: small; text-align: center; margin: 20px 0;">';
                echo '<h2 style="font-size: medium; color: #333;">Report Date: ' . $reportDate->format('F j, Y') . '</h2>';
                echo '</div>';
                echo generatePerformanceDashboardFromJson(json_encode($studentReportDetails['ai_report_data']));
            } else {
                // Show old report
                echo '<div class="report-date-heading" style="font-size: small; text-align: center; margin: 20px 0;">';
                echo '<h2 style="font-size: medium; color: #333;">Report Date: ' . $reportDate->format('F j, Y') . '</h2>';
                $daysLeft = 7 - $interval->days;
                echo '<p style="font-size: small; color: #666;">Note: You can generate the next report in ' . $daysLeft . ' days.</p>';
                echo '</div>';
                echo generatePerformanceDashboardFromJson(json_encode($studentReportDetails['ai_report_data']));
            }
            exit;
        }
        // Debugging log for troubleshooting
        if ($student_id && $courseid) {
            $sql = "
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
                            u.id, c.id, qz.id, qa.attempt, qatt.slot;
            ";
            // Execute the query with the retrieved student ID and course ID
            $recordset = $DB->get_recordset_sql($sql, ['student_id' => $student_id, 'course_id' => $courseid]);
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

                echo generatePerformanceDashboardFromJson($jsonNewData);
                exit;
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



function transformQuizResults($results)
{
    // Validate input
    if (empty($results) || !is_array($results)) {
        throw new InvalidArgumentException("Input must be a non-empty array");
    }

    // Initialize with validation of required fields
    if (!isset($results[0]["student_id"]) || !isset($results[0]["course_name"])) {
        throw new InvalidArgumentException("Missing required fields: student_id or course_name");
    }

    $transformedData = [
        "student_id" => $results[0]["student_id"],
        "course_name" => $results[0]["course_name"],
        "quiz_analysis" => [],
        "section_analysis" => [],
        "overall_performance" => [
            "total_questions" => 0,
            "correct_answers" => 0,
            "incorrect_answers" => 0,
            "overall_performance_percentage" => 0,
            "pie_chart_data" => [
                "correct" => 0,
                "incorrect" => 0
            ]
        ]
    ];

    // Track questions per quiz
    $processedQuestions = [];
    $quizAnalysis = [];
    $sectionAnalysis = [];
    $totalQuestions = 0;
    $correctAnswers = 0;
    $incorrectAnswers = 0;

    foreach ($results as $question) {
        // Validate required fields for each question
        $requiredFields = ["quiz_id", "quiz_name", "section_name", "question_id", "question_state"];
        foreach ($requiredFields as $field) {
            if (!isset($question[$field])) {
                continue 2; // Skip this question if missing required fields
            }
        }

        // Create unique key for question within THIS quiz
        $questionKey = $question["quiz_id"] . "-" . $question["question_id"] . "-" . $question["attempt_number"];

        // Skip if we've already processed this specific question in this quiz and attempt
        if (isset($processedQuestions[$questionKey])) {
            continue;
        }
        $processedQuestions[$questionKey] = true;

        $quizId = $question["quiz_id"];
        $quizName = $question["quiz_name"];
        $sectionName = $question["section_name"];

        // Enhanced grading logic to handle partial grading
        switch ($question["question_state"]) {
            case "gradedright":
                $correctIncrement = 1;
                $incorrectIncrement = 0;
                break;
            case "gradedpartial":
                $correctIncrement = 0.5;  // Count partial as half correct
                $incorrectIncrement = 0.5; // and half incorrect
                break;
            case "gradedwrong":
            default:
                $correctIncrement = 0;
                $incorrectIncrement = 1;
                break;
        }

        // Quiz-level analysis
        if (!isset($quizAnalysis[$quizId])) {
            $quizAnalysis[$quizId] = [
                "quiz_name" => $quizName,
                "total_questions" => 0,
                "correct_answers" => 0,
                "incorrect_answers" => 0,
                "performance_percentage" => 0
            ];
        }
        $quizAnalysis[$quizId]["total_questions"]++;
        $quizAnalysis[$quizId]["correct_answers"] += $correctIncrement;
        $quizAnalysis[$quizId]["incorrect_answers"] += $incorrectIncrement;

        // Section-level analysis
        if (!isset($sectionAnalysis[$sectionName])) {
            $sectionAnalysis[$sectionName] = [
                "section_name" => $sectionName,
                "total_questions" => 0,
                "correct_answers" => 0,
                "performance_percentage" => 0
            ];
        }
        $sectionAnalysis[$sectionName]["total_questions"]++;
        $sectionAnalysis[$sectionName]["correct_answers"] += $correctIncrement;

        // Overall analysis
        $totalQuestions++;
        $correctAnswers += $correctIncrement;
        $incorrectAnswers += $incorrectIncrement;
    }

    // Calculate performance percentages safely
    foreach ($quizAnalysis as &$quiz) {
        $quiz["performance_percentage"] = $quiz["total_questions"] > 0
            ? round(($quiz["correct_answers"] / $quiz["total_questions"]) * 100, 2)
            : 0;
    }

    foreach ($sectionAnalysis as &$section) {
        $section["performance_percentage"] = $section["total_questions"] > 0
            ? round(($section["correct_answers"] / $section["total_questions"]) * 100, 2)
            : 0;
    }

    // Transform to final format
    $transformedData["quiz_analysis"] = array_values($quizAnalysis);
    $transformedData["section_analysis"] = array_values($sectionAnalysis);
    $transformedData["overall_performance"] = [
        "total_questions" => $totalQuestions,
        "correct_answers" => $correctAnswers,
        "incorrect_answers" => $incorrectAnswers,
        "overall_performance_percentage" => $totalQuestions > 0
            ? round(($correctAnswers / $totalQuestions) * 100, 2)
            : 0,
        "pie_chart_data" => [
            "correct" => $correctAnswers,
            "incorrect" => $incorrectAnswers
        ]
    ];

    // Return JSON-encoded data
    return json_encode($transformedData);
}



function generatePerformanceDashboardFromJson($jsonContent)
{
    // Parse the JSON content
    $responseContent = json_decode($jsonContent, true);

    // Extract performance data
    $studentId = $responseContent['student_id'];
    $courseName = $responseContent['course_name'];
    $totalAttempts = $responseContent['total_attempts'];
    $quizAnalysis = $responseContent['quiz_analysis'];
    $sectionAnalysis = $responseContent['section_analysis'];
    $overallPerformance = $responseContent['overall_performance'];
    $performanceSummary = $responseContent['performance_summary'];
    $sectionAndQuizes = $responseContent['sectionsAndQuizes'];
    // Prepare data for charts
    $quizNames = array_column($quizAnalysis, 'quiz_name');
    $quizCorrect = array_column($quizAnalysis, 'correct_answers');
    $quizIncorrect = array_column($quizAnalysis, 'incorrect_answers');

    $sectionNames = array_column($sectionAnalysis, 'section_name');
    $sectionCorrect = array_column($sectionAnalysis, 'correct_answers');
    $sectionIncorrect = array_map(
        function ($section) {
            return $section['total_questions'] - $section['correct_answers'];
        },
        $sectionAnalysis
    );


    // Group quiz analysis by section
    $quizzesBySection = [];
    foreach ($quizAnalysis as $quiz) {
        foreach ($sectionAndQuizes as $section => $quizzes) {
            if (in_array($quiz['quiz_name'], $quizzes)) {
                $quizzesBySection[$section][] = $quiz;
                break;
            }
        }
    }

    $data = [];

    // Loop through each section
    foreach ($sectionAnalysis as $section) {
        $sectionName = $section['section_name'];

        // Check if quizzes exist for this section
        if (isset($quizzesBySection[$sectionName])) {
            $quizzes = [];

            // Loop through each quiz for the section
            foreach ($quizzesBySection[$sectionName] as $quiz) {
                $quizzes[] = [
                    'quiz_name' => $quiz['quiz_name'],
                    'total_questions' => $quiz['total_questions'],
                    'correct_answers' => $quiz['correct_answers'],
                    'incorrect_answers' => $quiz['incorrect_answers'],
                    'performance_percentage' => number_format($quiz['performance_percentage'], 2)
                ];
            }

            // Add section and its quizzes to the data array
            $data[] = [
                'section_name' => $sectionName,
                'quizzes' => $quizzes
            ];
        }
    }
    ob_start(); // Start output buffering to capture HTML
?>
    <div class="performance-dashboard">
        <!-- Header -->
        <div class="header gradient-bg" style="margin-bottom: 10px; border-radius: 16px;">
            <div class="header-content">
                <h1 class="" data-text="Student Performance Report">AI Generated Student Performance Report</h1>
            </div>
        </div>

        <!-- Course and Student Information -->
        <div class="course-info card slide-in" style="border-top-left-radius: 0; border-top-right-radius: 0;">
            <h2 class="report_h2"><i class="fas fa-graduation-cap"></i> Course Details</h2>
            <div class="info-grid">
                <div class="info-item"><strong>Course:</strong> <?php echo htmlspecialchars($courseName); ?></div>
                <div class="info-item"><strong>Student ID:</strong> <?php echo htmlspecialchars($studentId); ?></div>
            </div>
        </div>

        <!-- Performance Summary -->
        <div class="performance-summary card slide-in">
            <h2 class="report_h2"><i class="fas fa-chart-line"></i> Performance Summary</h2>
            <div class="summary-content">
                <p class="text-large"><strong>Key Insights:</strong> <?php echo htmlspecialchars($performanceSummary['key_insights']); ?></p>
                <p class="text-large"><strong>Areas of Improvement:</strong> <?php echo htmlspecialchars($performanceSummary['areas_of_improvement']); ?></p>
            </div>
        </div>
        <div class="performance-info card slide-in">
            <h2 class="report_h2"><i class="fas fa-info-circle"></i> Performance Color Code</h2>
            <div class="info-grid">
                <div class="info-item" style="display: flex; align-items: center; padding: 10px; border-radius: 5px; background-color: #FEE2E2;">
                    <span style="margin: auto; font-weight: bold; color:'#EF4444'">0-40%</span>
                </div>
                <div class="info-item" style="display: flex; align-items: center; padding: 10px; border-radius: 5px; background-color: #FEF3C7;">
                    <span style="margin: auto; font-weight: bold;">40-80%</span>
                </div>
                <div class="info-item" style="display: flex; align-items: center; padding: 10px; border-radius: 5px; background-color: #DCFCE7;">
                    <span style="margin: auto; font-weight: bold;">80-100%</span>
                </div>
            </div>
        </div>
        <!-- Charts -->
        <div class="charts-container card slide-in">
            <h2 class="report_h2"><i class="fas fa-chart-pie"></i> Overall Performance Graph</h2>
            <div style="width: 400px; height: 400px; display: flex; justify-content: center; align-items: center; align-self: center;">
                <canvas id="overallPerformanceChart"></canvas>
            </div>
        </div>
        <!-- Section Performance Table -->
        <div class="performance-table card slide-in">
            <h2 class="report_h2"><i class="fas fa-layer-group"></i> Section Performance Breakdown</h2>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Section Name</th>
                            <th>Total Questions</th>
                            <th>Correct Answers</th>
                            <th>Incorrect Answers</th>
                            <th>Performance (%)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($sectionAnalysis as $section): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($section['section_name']); ?></td>
                                <td><?php echo $section['total_questions']; ?></td>
                                <td><?php echo $section['correct_answers']; ?></td>
                                <td><?php echo $section['total_questions'] - $section['correct_answers']; ?></td>
                                <?php
                                $performancePercentage = number_format($section['performance_percentage'], 2);
                                $color = '#FEE2E2'; // Default color
                                $textColor = '#EF4444'; // Default text color for red background

                                if ($performancePercentage >= 80) {
                                    $color = '#DCFCE7';
                                    $textColor = '#16A34A';
                                } elseif ($performancePercentage >= 40) {
                                    $color = '#FEF3C7';
                                    $textColor = '#D97706';
                                }
                                ?>
                                <td>
                                    <span class="chip" style="background-color: <?php echo $color; ?>; color: <?php echo $textColor; ?>; padding: 5px 10px; border-radius: 12px; display: inline-block; font-weight: bold; width:100%; text-align: center;">
                                        <?php echo $performancePercentage; ?>%
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <!-- Section Performance Chart -->
        <div class="charts-container card slide-in">
            <h2 class="report_h2"><i class="fas fa-chart-bar"></i> Section Performance Graph</h2>
            <div class="chart-scroll-container">
                <div class="chart-wrapper" id="sectionChartWrapper">
                    <canvas id="sectionPerformanceChart"></canvas>
                </div>
            </div>
        </div>




        <div class="performance-table card slide-in">
            <h2 class="report_h2"><i class="fas fa-list-alt"></i> Quiz Performance by Section</h2>

            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Section / Quiz Name</th>
                            <th>Total Questions</th>
                            <th>Correct Answers</th>
                            <th>Incorrect Answers</th>
                            <th>Performance (%)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($sectionAnalysis as $section): ?>
                            <?php
                            $sectionColor = '#FEE2E2';
                            $sectionTextColor = '#EF4444';

                            if ($section['performance_percentage'] >= 80) {
                                $sectionColor = '#DCFCE7';
                                $sectionTextColor = '#16A34A';
                            } elseif ($section['performance_percentage'] >= 40) {
                                $sectionColor = '#FEF3C7';
                                $sectionTextColor = '#D97706';
                            }
                            ?>
                            <tr class="section-row" data-section="<?php echo htmlspecialchars($section['section_name']); ?>">
                                <td>
                                    <div class="section-name">
                                        <i class="fas fa-chevron-right section-icon"></i>
                                        <?php echo htmlspecialchars($section['section_name']); ?>
                                        <span class="show-graph" style="display: none; margin-left: 10px; color: var(--primary-color); cursor: pointer; text-decoration: underline; font-weight: bold;">Show Graph</span>
                                    </div>
                                </td>
                                <td><?php echo $section['total_questions']; ?></td>
                                <td><?php echo $section['correct_answers']; ?></td>
                                <td><?php echo ($section['total_questions'] - $section['correct_answers']); ?></td>
                                <td>
                                    <span class="chip" style="background-color: <?php echo $sectionColor; ?>; color: <?php echo $sectionTextColor; ?>; padding: 5px 10px; border-radius: 12px; display: inline-block; font-weight: bold; width:100%; text-align: center;">
                                        <?php echo number_format($section['performance_percentage'], 2); ?>%
                                    </span>
                                </td>
                            </tr>
                            <?php if (isset($quizzesBySection[$section['section_name']])): ?>
                                <?php foreach ($quizzesBySection[$section['section_name']] as $quiz): ?>
                                    <?php
                                    $quizColor = '#FEE2E2';
                                    $quizTextColor = '#EF4444';

                                    if ($quiz['performance_percentage'] >= 80) {
                                        $quizColor = '#DCFCE7';
                                        $quizTextColor = '#16A34A';
                                    } elseif ($quiz['performance_percentage'] >= 40) {
                                        $quizColor = '#FEF3C7';
                                        $quizTextColor = '#D97706';
                                    }
                                    ?>
                                    <tr class="quiz-row" data-section="<?php echo htmlspecialchars($section['section_name']); ?>">
                                        <td><?php echo htmlspecialchars($quiz['quiz_name']); ?></td>
                                        <td><?php echo $quiz['total_questions']; ?></td>
                                        <td><?php echo $quiz['correct_answers']; ?></td>
                                        <td><?php echo $quiz['incorrect_answers']; ?></td>
                                        <td>
                                            <span class="chip" style="background-color: <?php echo $quizColor; ?>; color: <?php echo $quizTextColor; ?>; padding: 5px 10px; border-radius: 12px; display: inline-block; font-weight: bold; width:100%; text-align: center;">
                                                <?php echo number_format($quiz['performance_percentage'], 2); ?>%
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="charts-container card slide-in">
            <h2 class="report_h2"><i class="fas fa-chart-bar"></i> Quiz Performance Graph</h2>
            <div class="chart-scroll-container">
                <div class="chart-wrapper" id="quizChartWrapper">
                    <canvas id="quizPerformanceChart"></canvas>
                </div>
            </div>
        </div>

    </div>
    <!-- Include Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            // Custom chart color palette
            const colors = {
                correct: "#4CAF50",
                incorrect: "#F44336",
                blue: "#2196F3",
                yellow: "#FFC107"
            };
            // Function to calculate chart width based on labels
            function calculateChartWidth(labels, minWidth = 800) {
                const minWidthPerLabel = 150; // Minimum width per label in pixels
                const calculatedWidth = Math.max(minWidth, labels.length * minWidthPerLabel);
                return calculatedWidth;
            }

            // Function to set chart wrapper width
            function setChartWrapperWidth(wrapperId, labels) {
                const wrapper = document.getElementById(wrapperId);
                if (wrapper) {
                    const width = calculateChartWidth(labels);
                    wrapper.style.width = `${width}px`;
                }
            }
            // Section Performance Chart
            const sectionLabels = <?php echo json_encode($sectionNames); ?>;
            setChartWrapperWidth('sectionChartWrapper', sectionLabels);
            const sectionCtx = document.getElementById("sectionPerformanceChart").getContext("2d");
            new Chart(sectionCtx, {
                type: "bar",
                data: {
                    labels: <?php echo json_encode($sectionNames); ?>,
                    datasets: [{
                            label: "Correct Answers",
                            data: <?php echo json_encode($sectionCorrect); ?>,
                            backgroundColor: '#16A085',
                            borderWidth: 1
                        },
                        {
                            label: "Incorrect Answers",
                            data: <?php echo json_encode($sectionIncorrect); ?>,
                            backgroundColor: '#FFFFD1',
                            borderWidth: 1
                        }
                    ]
                },
                options: {
                    maintainAspectRatio: false,
                    responsive: true,
                    interaction: {
                        mode: "index",
                        intersect: false
                    },
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
                            text: "Section Performance",
                            font: {
                                size: 18,
                                weight: "bold"
                            }
                        }
                    },
                    scales: {
                        x: {
                            title: {
                                display: true,
                                text: "Sections",
                                font: {
                                    weight: "bold"
                                }
                            },
                            ticks: {
                                maxRotation: 0,
                                minRotation: 0,
                                autoSkip: false,
                                callback: function(value) {
                                    const label = this.getLabelForValue(value);
                                    const words = label.split(' ');
                                    const lines = [];
                                    let currentLine = '';

                                    words.forEach(word => {
                                        if (currentLine.length + word.length > 15) {
                                            lines.push(currentLine);
                                            currentLine = word;
                                        } else {
                                            currentLine += (currentLine === '' ? '' : ' ') + word;
                                        }
                                    });
                                    if (currentLine) lines.push(currentLine);

                                    return lines;
                                },
                                font: {
                                    size: 12 // Adjust font size if needed
                                }
                            },
                            barPercentage: 0.8, // Increase bar width
                            categoryPercentage: 0.8 // Increase bar width
                        },
                        y: {
                            title: {
                                display: true,
                                text: "Answers",
                                font: {
                                    weight: "bold"
                                }
                            }
                        }
                    }
                }
            });

            // Overall Performance Chart
            const overallCtx = document.getElementById("overallPerformanceChart").getContext("2d");
            new Chart(overallCtx, {
                type: "pie",
                data: {
                    labels: ["Correct", "Incorrect"],
                    datasets: [{
                        data: [<?php echo json_encode($overallPerformance['pie_chart_data']['correct']); ?>, <?php echo json_encode($overallPerformance['pie_chart_data']['incorrect']); ?>],
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
            const sectionRows = document.querySelectorAll(".section-row");
            let quizChart; // Hold the Chart.js instance

            // Function to fetch quiz data for a section
            function getQuizDataForSection(sectionName) {
                // Replace this with your dynamic data fetching logic
                const sectionQuizData = <?php echo json_encode($quizzesBySection); ?>;
                const quizzes = sectionQuizData[sectionName] || [];

                const labels = quizzes.map((quiz) => quiz.quiz_name);
                const correct = quizzes.map((quiz) => quiz.correct_answers);
                const incorrect = quizzes.map((quiz) => quiz.incorrect_answers);

                return {
                    labels,
                    correct,
                    incorrect
                };
            }

            function updateQuizChart(labels, correct, incorrect) {
                const quizCtx = document.getElementById("quizPerformanceChart").getContext("2d");
                setChartWrapperWidth('quizChartWrapper', labels);

                if (quizChart) {
                    // Update existing chart
                    quizChart.data.labels = labels;
                    quizChart.data.datasets[0].data = correct;
                    quizChart.data.datasets[1].data = incorrect;
                    quizChart.update();

                } else {
                    // Create a new chart
                    quizChart = new Chart(quizCtx, {
                        type: "bar",
                        data: {
                            labels: labels,
                            datasets: [{
                                    label: "Correct Answers",
                                    data: <?php echo json_encode($quizCorrect); ?>,
                                    backgroundColor: '#415B76',
                                    borderWidth: 1
                                },
                                {
                                    label: "Incorrect Answers",
                                    data: <?php echo json_encode($quizIncorrect); ?>,
                                    backgroundColor: '#FFE4CC',
                                    borderWidth: 1
                                }
                            ]
                        },
                        options: {
                            maintainAspectRatio: false,
                            responsive: true,
                            interaction: {
                                mode: "index",
                                intersect: false
                            },
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
                                    text: "Quiz Performance",
                                    font: {
                                        size: 18,
                                        weight: "bold"
                                    }
                                }
                            },
                            scales: {
                                x: {
                                    title: {
                                        display: true,
                                        text: "Quizzes",
                                        font: {
                                            weight: "bold"
                                        }
                                    },
                                    ticks: {
                                        maxRotation: 0,
                                        minRotation: 0,
                                        autoSkip: false,
                                        callback: function(value) {
                                            const label = this.getLabelForValue(value);
                                            const words = label.split(' ');
                                            const lines = [];
                                            let currentLine = '';

                                            words.forEach(word => {
                                                if (currentLine.length + word.length > 15) {
                                                    lines.push(currentLine);
                                                    currentLine = word;
                                                } else {
                                                    currentLine += (currentLine === '' ? '' : ' ') + word;
                                                }
                                            });
                                            if (currentLine) lines.push(currentLine);

                                            return lines;
                                        },
                                        font: {
                                            size: 12 // Adjust font size if needed
                                        }
                                    },
                                    barPercentage: 0.8, // Increase bar width
                                    categoryPercentage: 0.8 // Increase bar width
                                },
                                y: {
                                    title: {
                                        display: true,
                                        text: "Answers",
                                        font: {
                                            weight: "bold"
                                        }
                                    }
                                }
                            }
                        }
                    });
                }
            }

            // Function to render the first section by default
            function renderFirstSection() {
                if (sectionRows.length > 0) {
                    const firstSectionRow = sectionRows[0];
                    const sectionName = firstSectionRow.dataset.section;

                    // Mark the first section as active
                    firstSectionRow.classList.add("active");

                    // Show its quiz rows
                    const quizRows = document.querySelectorAll(`.quiz-row[data-section="${sectionName}"]`);
                    quizRows.forEach((quizRow) => {
                        quizRow.style.display = "table-row";
                        quizRow.classList.add("visible");
                    });

                    // Show the "Show Graph" text for the first section
                    const showGraphText = firstSectionRow.querySelector(".show-graph");
                    if (showGraphText) {
                        showGraphText.style.display = "inline";
                    }
                    // Render the chart for the first section
                    const quizData = getQuizDataForSection(sectionName);
                    updateQuizChart(quizData.labels, quizData.correct, quizData.incorrect);
                }
            }

            // Add click listeners to section rows
            sectionRows.forEach((row) => {
                row.addEventListener("click", function() {
                    const sectionName = this.dataset.section; // Get the section name
                    const quizRows = document.querySelectorAll(`.quiz-row[data-section="${sectionName}"]`);
                    const isExpanded = this.classList.contains("active");
                    const showGraphText = this.querySelector(".show-graph"); // Locate the "Show Graph" span

                    // Close all other sections
                    sectionRows.forEach((otherRow) => {
                        if (otherRow !== this) {
                            const otherSectionName = otherRow.dataset.section;
                            const otherQuizRows = document.querySelectorAll(`.quiz-row[data-section="${otherSectionName}"]`);

                            // Collapse other quiz rows
                            otherRow.classList.remove("active");
                            otherQuizRows.forEach((quizRow) => {
                                quizRow.classList.remove("visible");
                                setTimeout(() => {
                                    quizRow.style.display = "none";
                                }, 300);
                            });

                            // Hide "Show Graph" text in other sections
                            const otherShowGraphText = otherRow.querySelector(".show-graph");
                            if (otherShowGraphText) {
                                otherShowGraphText.style.display = "none";
                            }
                        }
                    });

                    // Toggle current section
                    this.classList.toggle("active");

                    // Toggle quiz rows with animation
                    quizRows.forEach((quizRow, index) => {
                        if (!isExpanded) {
                            // Show rows
                            quizRow.style.display = "table-row";
                            setTimeout(() => {
                                quizRow.classList.add("visible");
                            }, index * 50);
                        } else {
                            // Hide rows
                            quizRow.classList.remove("visible");
                            setTimeout(() => {
                                quizRow.style.display = "none";
                            }, 300);
                        }
                    });

                    // Show or hide "Show Graph" text
                    if (!isExpanded) {
                        if (showGraphText) {
                            showGraphText.style.display = "inline";
                        }
                        const quizData = getQuizDataForSection(sectionName);
                        updateQuizChart(quizData.labels, quizData.correct, quizData.incorrect);
                    } else {
                        if (showGraphText) {
                            showGraphText.style.display = "none";
                        }
                    }
                });

                // Add click event for "Show Graph"
                const showGraphText = row.querySelector(".show-graph");
                if (showGraphText) {
                    showGraphText.addEventListener("click", function(event) {
                        event.stopPropagation(); // Prevent the section toggle when clicking "Show Graph"
                        const graphElement = document.getElementById("quizPerformanceChart"); // Replace with your graph element's ID
                        if (graphElement) {
                            graphElement.scrollIntoView({
                                behavior: "smooth"
                            });
                        }
                    });
                }
            });

            // Render the first section by default
            renderFirstSection();
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

    <!-- Add your styles -->
    <style>
        @import url("https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap");


        :root {
            /* Option 1: Teal Theme */
            --primary-color: #0D9488;
            /* Modern teal */

            /* Status Colors from Image */
            --status-error: #FEE2E2;
            /* Soft pink background */
            --status-error-text: #EF4444;
            /* Red text */
            --status-success: #DCFCE7;
            /* Mint green background */
            --status-success-text: #16A34A;
            /* Green text */
            --status-warning: #FEF3C7;
            /* Warm yellow background */
            --status-warning-text: #D97706;
            /* Gold text */

            /* Base Colors */
            --text-color: #334155;
            /* Slate gray */
            --text-light: #64748B;
            /* Light slate */
            --background-color: #F8FAFC;
            /* Light background */
            --border-color: #E2E8F0;
            /* Subtle border */

            /* Spacing */
            --space-xs: 0.25rem;
            --space-sm: 0.5rem;
            --space-md: 1rem;
            --space-lg: 1.5rem;
            --space-xl: 2rem;

            /* Typography */
            --font-primary: system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
        }

        body {
            background-color: var(--background-color);
            font-family: var(--font-primary);
            color: var(--text-color);
            line-height: 1.5;
        }


        .performance-dashboard {
            max-width: 1000px;
            margin: 0 auto;
        }

        .gradient-bg {
            background: linear-gradient(135deg, var(--primary-color), #7b1fa2);
            color: white;
            padding: 30px 0;
            text-align: center;
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
        }


        .report_logo {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid white;
            box-shadow: 0 8px 15px rgba(0, 0, 0, 0.2);
        }

        .title {
            margin-top: 15px;
            font-size: 2.5rem;
            font-weight: 700;
        }

        .card {
            background: white;
            border-radius: 16px;
            box-shadow:
                0 1px 3px rgba(0, 0, 0, 0.05),
                0 4px 6px rgba(0, 0, 0, 0.02);
            padding: var(--space-xl);
            margin-bottom: var(--space-xl);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .card:hover {
            transform: translateY(-2px);
            box-shadow:
                0 4px 6px rgba(0, 0, 0, 0.05),
                0 10px 15px rgba(0, 0, 0, 0.02);
        }


        .table-responsive {
            overflow-x: auto;
            margin: var(--space-lg) 0;
            border-radius: 12px;
        }



        .table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }

        .table th {
            background-color: var(--primary-color);
            color: white;
            padding: var(--space-md);
            text-align: left;
            font-weight: 600;
            white-space: nowrap;
        }

        .table th:first-child {
            border-top-left-radius: 8px;
        }

        .table th:last-child {
            border-top-right-radius: 8px;
        }

        .table td {
            padding: var(--space-md);
            border-bottom: 1px solid var(--border-color);
        }

        .table tr:last-child td {
            border-bottom: none;
        }


        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }

        .slide-in {
            opacity: 0;
            transform: translateY(20px);
            transition: all 0.8s ease;
        }

        .slide-in.visible {
            opacity: 1;
            transform: translateY(0);
        }

        .charts-container {
            position: relative;
        }


        .small-pie canvas {
            width: 200px !important;
            height: 100px !important;
            margin: 0 auto;
        }

        .text-large {
            font-size: 1rem;
            line-height: 1.6;
            color: var(--text-light);
        }

        .text-large strong {
            color: var(--text-color);
            font-weight: 600;
        }

        .report_h2 {
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


        h2 i {
            color: var(--secondary-color);
            margin-right: 10px;
        }

        .info-item {
            background-color: #f0f4f8;
            padding: 12px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            transition: all 0.3s ease;
        }

        .info-item:hover {
            background-color: #e6eaf0;
            transform: translateX(5px);
        }

        .summary-content {
            background-color: white;
            border: 1px solid var(--border-color);
            border-left: 4px solid var(--primary-color);
            padding: var(--space-lg);
            border-radius: 8px;
            margin: var(--space-lg) 0;
        }


        .summary-content p {
            margin: 10px 0;
            line-height: 1.6;
        }

        .charts-container canvas {
            max-width: 100%;
            height: auto !important;
            border-radius: 12px;
        }

        .chart-scroll-container {
            width: 100%;
            overflow-x: auto;
            padding: 15px 0;
        }

        .chart-wrapper {
            position: relative;
            height: 400px;
            min-width: 100%;
        }

        /* Hide scrollbar for Chrome, Safari and Opera */
        .chart-scroll-container::-webkit-scrollbar {
            height: 8px;
        }

        /* Track */
        .chart-scroll-container::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 4px;
        }

        /* Handle */
        .chart-scroll-container::-webkit-scrollbar-thumb {
            background: #888;
            border-radius: 4px;
        }

        /* Handle on hover */
        .chart-scroll-container::-webkit-scrollbar-thumb:hover {
            background: #555;
        }

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

        .quiz-row {
            display: none;
            background-color: #ffffff !important;
            transition: all 0.3s ease-out;
        }

        .quiz-row.visible {
            display: table-row;
        }

        .quiz-row td {
            padding-left: 2.5rem !important;
            opacity: 0;
            transform: translateY(-10px);
            transition: opacity 0.3s ease-out, transform 0.3s ease-out;
        }

        .quiz-row.visible td {
            opacity: 1;
            transform: translateY(0);
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

        .performance-chip {
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

        .performance-chip:hover {
            transform: translateY(-1px);
        }


        @media (max-width: 768px) {
            .performance-dashboard {
                margin: 15px;
                padding: 0 10px;
            }

            .header-content {
                text-align: center;
            }

            .title {
                font-size: 1.8rem;
            }

            .info-grid {
                grid-template-columns: 1fr;
            }

            .table {
                font-size: 0.9rem;
            }

            h2 {
                flex-direction: column;
                align-items: flex-start;
            }
        }

        /* Additional Visual Enhancements */
        .performance-summary strong,
        .info-item strong {
            color: var(--primary-color);
            font-weight: 600;
        }

        /* Print Styles */
        @media print {
            body {
                background: white;
            }

            .card {
                box-shadow: none;
                border: 1px solid #ddd;
            }

            .gradient-bg {
                background: white;
                color: black;
            }
        }
    </style>
<?php
    return ob_get_clean(); // Return the buffered HTML content
}
