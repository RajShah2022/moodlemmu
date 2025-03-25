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
 * Definition of the grade_user_report class is defined
 *
 * @package gradereport_user
 * @copyright 2007 Nicolas Connault
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use core_user\output\myprofile\tree;

require_once($CFG->dirroot . '/grade/report/lib.php');
require_once($CFG->libdir . '/tablelib.php');

define("GRADE_REPORT_USER_HIDE_HIDDEN", 0);
define("GRADE_REPORT_USER_HIDE_UNTIL", 1);
define("GRADE_REPORT_USER_SHOW_HIDDEN", 2);

define("GRADE_REPORT_USER_VIEW_SELF", 1);
define("GRADE_REPORT_USER_VIEW_USER", 2);

function grade_report_user_settings_definition(&$mform)
{
    global $CFG;

    $options = [
        -1 => get_string('default', 'grades'),
        0 => get_string('hide'),
        1 => get_string('show')
    ];

    if (empty($CFG->grade_report_user_showrank)) {
        $options[-1] = get_string('defaultprev', 'grades', $options[0]);
    } else {
        $options[-1] = get_string('defaultprev', 'grades', $options[1]);
    }

    $mform->addElement('select', 'report_user_showrank', get_string('showrank', 'grades'), $options);
    $mform->addHelpButton('report_user_showrank', 'showrank', 'grades');

    if (empty($CFG->grade_report_user_showpercentage)) {
        $options[-1] = get_string('defaultprev', 'grades', $options[0]);
    } else {
        $options[-1] = get_string('defaultprev', 'grades', $options[1]);
    }

    $mform->addElement('select', 'report_user_showpercentage', get_string('showpercentage', 'grades'), $options);
    $mform->addHelpButton('report_user_showpercentage', 'showpercentage', 'grades');

    if (empty($CFG->grade_report_user_showgrade)) {
        $options[-1] = get_string('defaultprev', 'grades', $options[0]);
    } else {
        $options[-1] = get_string('defaultprev', 'grades', $options[1]);
    }

    $mform->addElement('select', 'report_user_showgrade', get_string('showgrade', 'grades'), $options);

    if (empty($CFG->grade_report_user_showfeedback)) {
        $options[-1] = get_string('defaultprev', 'grades', $options[0]);
    } else {
        $options[-1] = get_string('defaultprev', 'grades', $options[1]);
    }

    $mform->addElement('select', 'report_user_showfeedback', get_string('showfeedback', 'grades'), $options);

    if (empty($CFG->grade_report_user_showweight)) {
        $options[-1] = get_string('defaultprev', 'grades', $options[0]);
    } else {
        $options[-1] = get_string('defaultprev', 'grades', $options[1]);
    }

    $mform->addElement('select', 'report_user_showweight', get_string('showweight', 'grades'), $options);

    if (empty($CFG->grade_report_user_showaverage)) {
        $options[-1] = get_string('defaultprev', 'grades', $options[0]);
    } else {
        $options[-1] = get_string('defaultprev', 'grades', $options[1]);
    }

    $mform->addElement('select', 'report_user_showaverage', get_string('showaverage', 'grades'), $options);
    $mform->addHelpButton('report_user_showaverage', 'showaverage', 'grades');

    if (empty($CFG->grade_report_user_showlettergrade)) {
        $options[-1] = get_string('defaultprev', 'grades', $options[0]);
    } else {
        $options[-1] = get_string('defaultprev', 'grades', $options[1]);
    }

    $mform->addElement('select', 'report_user_showlettergrade', get_string('showlettergrade', 'grades'), $options);
    if (empty($CFG->grade_report_user_showcontributiontocoursetotal)) {
        $options[-1] = get_string('defaultprev', 'grades', $options[0]);
    } else {
        $options[-1] = get_string('defaultprev', 'grades', $options[$CFG->grade_report_user_showcontributiontocoursetotal]);
    }

    $mform->addElement('select', 'report_user_showcontributiontocoursetotal', get_string('showcontributiontocoursetotal', 'grades'), $options);
    $mform->addHelpButton('report_user_showcontributiontocoursetotal', 'showcontributiontocoursetotal', 'grades');

    if (empty($CFG->grade_report_user_showrange)) {
        $options[-1] = get_string('defaultprev', 'grades', $options[0]);
    } else {
        $options[-1] = get_string('defaultprev', 'grades', $options[1]);
    }

    $mform->addElement('select', 'report_user_showrange', get_string('showrange', 'grades'), $options);

    $options = [
        0 => 0,
        1 => 1,
        2 => 2,
        3 => 3,
        4 => 4,
        5 => 5
    ];

    if (!empty($CFG->grade_report_user_rangedecimals)) {
        $options[-1] = $options[$CFG->grade_report_user_rangedecimals];
    }
    $mform->addElement('select', 'report_user_rangedecimals', get_string('rangedecimals', 'grades'), $options);

    $options = [
        -1 => get_string('default', 'grades'),
        0 => get_string('shownohidden', 'grades'),
        1 => get_string('showhiddenuntilonly', 'grades'),
        2 => get_string('showallhidden', 'grades')
    ];

    if (empty($CFG->grade_report_user_showhiddenitems)) {
        $options[-1] = get_string('defaultprev', 'grades', $options[0]);
    } else {
        $options[-1] = get_string('defaultprev', 'grades', $options[$CFG->grade_report_user_showhiddenitems]);
    }

    $mform->addElement('select', 'report_user_showhiddenitems', get_string('showhiddenitems', 'grades'), $options);
    $mform->addHelpButton('report_user_showhiddenitems', 'showhiddenitems', 'grades');

    $options = [
        -1 => get_string('default', 'grades'),
        GRADE_REPORT_HIDE_TOTAL_IF_CONTAINS_HIDDEN => get_string('hide'),
        GRADE_REPORT_SHOW_TOTAL_IF_CONTAINS_HIDDEN => get_string('hidetotalshowexhiddenitems', 'grades'),
        GRADE_REPORT_SHOW_REAL_TOTAL_IF_CONTAINS_HIDDEN => get_string('hidetotalshowinchiddenitems', 'grades')
    ];

    if (empty($CFG->grade_report_user_showtotalsifcontainhidden)) {
        $options[-1] = get_string('defaultprev', 'grades', $options[0]);
    } else {
        $options[-1] = get_string('defaultprev', 'grades', $options[$CFG->grade_report_user_showtotalsifcontainhidden]);
    }

    $mform->addElement('select', 'report_user_showtotalsifcontainhidden', get_string('hidetotalifhiddenitems', 'grades'), $options);
    $mform->addHelpButton('report_user_showtotalsifcontainhidden', 'hidetotalifhiddenitems', 'grades');
}

/**
 * Profile report callback.
 *
 * @param object $course The course.
 * @param object $user The user.
 * @param boolean $viewasuser True when we are viewing this as the targetted user sees it.
 */
function grade_report_user_profilereport(object $course, object $user, bool $viewasuser = false)
{
    if (!empty($course->showgrades)) {

        $context = context_course::instance($course->id);

        // Fetch the return tracking object.
        $gpr = new grade_plugin_return(
            ['type' => 'report', 'plugin' => 'user', 'courseid' => $course->id, 'userid' => $user->id]
        );
        // Create a report instance.
        $report = new gradereport_user\report\user($course->id, $gpr, $context, $user->id, $viewasuser);

        // Print the page.
        // A css fix to share styles with real report page.
        echo '<div class="grade-report-user">';
        if ($report->fill_table()) {
            echo $report->print_table(true);
        }
        echo '</div>';
    }
}

/**
 * Add nodes to myprofile page.
 *
 * @param tree $tree Tree object
 * @param stdClass $user user object
 * @param bool $iscurrentuser
 * @param null|stdClass $course Course object
 */
function gradereport_user_myprofile_navigation(tree $tree, stdClass $user, bool $iscurrentuser, ?stdClass $course)
{
    if (empty($course)) {
        // We want to display these reports under the site context.
        $course = get_fast_modinfo(SITEID)->get_course();
    }
    $usercontext = context_user::instance($user->id);
    $anyreport = has_capability('moodle/user:viewuseractivitiesreport', $usercontext);

    // Start capability checks.
    if ($anyreport || $iscurrentuser) {
        // Add grade hardcoded grade report if necessary.
        $gradeaccess = false;
        $coursecontext = context_course::instance($course->id);
        if (has_capability('moodle/grade:viewall', $coursecontext)) {
            // Can view all course grades.
            $gradeaccess = true;
        } else if ($course->showgrades) {
            if ($iscurrentuser && has_capability('moodle/grade:view', $coursecontext)) {
                // Can view own grades.
                $gradeaccess = true;
            } else if (has_capability('moodle/grade:viewall', $usercontext)) {
                // Can view grades of this user - parent most probably.
                $gradeaccess = true;
            } else if ($anyreport) {
                // Can view grades of this user - parent most probably.
                $gradeaccess = true;
            }
        }
        if ($gradeaccess) {
            $url = new moodle_url('/course/user.php', array('mode' => 'grade', 'id' => $course->id, 'user' => $user->id));
            $node = new core_user\output\myprofile\node('reports', 'grade', get_string('grades'), null, $url);
            $tree->add_node($node);
        }
    }
}

/**
 * Returns link to user report for the current element
 *
 * @param context_course $context Course context
 * @param int $courseid Course ID
 * @param array  $element An array representing an element in the grade_tree
 * @param grade_plugin_return $gpr A grade_plugin_return object
 * @param string $mode Mode - gradeitem or user
 * @param ?stdClass $templatecontext Template context
 * @return stdClass|null
 */
function gradereport_user_get_report_link(
    context_course $context,
    int $courseid,
    array $element,
    grade_plugin_return $gpr,
    string $mode,
    ?stdClass $templatecontext
): ?stdClass {
    global $CFG;

    if ($mode == 'user') {
        $reportstring = get_string('userreport_' . $mode, 'gradereport_user');

        if (!isset($templatecontext)) {
            $templatecontext = new stdClass();
        }

        // FIXME: MDL-52678 This get_capability_info is hacky and we should have an API for inserting grade row links instead.
        $canseeuserreport = false;
        if (get_capability_info('gradereport/' . $CFG->grade_profilereport . ':view')) {
            $canseeuserreport = has_capability('gradereport/' . $CFG->grade_profilereport . ':view', $context);
        }

        if ($canseeuserreport) {
            $url = new moodle_url(
                '/grade/report/' . $CFG->grade_profilereport . '/index.php',
                ['userid' => $element['userid'], 'id' => $courseid]
            );
            $gpr->add_url_params($url);
            $templatecontext->reporturl1 = html_writer::link(
                $url,
                $reportstring,
                ['class' => 'dropdown-item', 'aria-label' => $reportstring, 'role' => 'menuitem']
            );
            return $templatecontext;
        }
    }
    return null;
}


/**
 * Calculates student ranks based on performance across all quizzes in the course structure.
 * 
 * This function:
 * - Considers all quizzes in the course structure
 * - Counts non-attempted quizzes as 0 performance
 * - Includes all students in ranking, even those with no attempts
 * - Handles ties with dense ranking (same score = same rank)
 * 
 * @param array $allStudentQuizData Associative array of student quiz data keyed by student ID
 * @param array $filteredCourseStructure The course structure containing all sections and quizzes
 * @param array $allStudentData All student records (keyed by student ID)
 * @return array Associative array of student rankings
 */
function calculateEnhancedStudentRanks($allStudentQuizData, $filteredCourseStructure, $allStudentData)
{
    // Extract all quizzes from the course structure
    $allCourseQuizzes = [];

    // Helper function to recursively extract quizzes from course structure
    function extractQuizzes($sections, &$quizzes)
    {
        foreach ($sections as $section) {
            // Add direct quizzes from this section
            if (isset($section['quizzes']) && is_array($section['quizzes'])) {
                foreach ($section['quizzes'] as $quiz) {
                    if (isset($quiz['quiz_id'])) {
                        $quizzes[$quiz['quiz_id']] = [
                            'id' => $quiz['quiz_id'],
                            'name' => $quiz['name'] ?? $quiz['quiz_name'] ?? "Quiz {$quiz['quiz_id']}"
                        ];
                    }
                }
            }

            // Process child sections recursively
            if (isset($section['children']) && is_array($section['children'])) {
                extractQuizzes($section['children'], $quizzes);
            }
        }
    }

    // Extract all quizzes from the course structure
    extractQuizzes($filteredCourseStructure['sections'], $allCourseQuizzes);

    // Initialize performance data for all students
    $studentPerformances = [];

    // Process each student
    foreach ($allStudentData as $student) {
        $studentId = $student['studentid'];

        // Initialize performance tracking for this student
        $studentPerformances[$studentId] = [
            'student_id' => $studentId,
            'name' => $student['firstname'] . ' ' . $student['lastname'],
            'total_performance' => 0,
            'quiz_count' => count($allCourseQuizzes), // Count of all quizzes in course
            'attempted_quizzes' => 0,
            'quiz_performances' => [] // To store individual quiz performances
        ];
    }

    // Process quiz data for each student
    foreach ($allStudentQuizData as $studentId => $quizData) {
        $resultJson = transformQuizResults($quizData);
        $result = json_decode($resultJson, true);

        if (isset($result['quiz_analysis']) && is_array($result['quiz_analysis'])) {
            // Track which quizzes the student has attempted
            $attemptedQuizIds = [];

            // Process each quiz the student has attempted
            foreach ($result['quiz_analysis'] as $quiz) {
                if (isset($quiz['id']) && isset($quiz['performance_percentage'])) {
                    $quizId = $quiz['id'];
                    $performance = floatval($quiz['performance_percentage']);

                    // Add to student's total performance
                    $studentPerformances[$studentId]['total_performance'] += $performance;
                    $studentPerformances[$studentId]['attempted_quizzes']++;
                    $studentPerformances[$studentId]['quiz_performances'][$quizId] = $performance;

                    // Mark this quiz as attempted
                    $attemptedQuizIds[] = $quizId;
                }
            }

            // Add zeros for quizzes not attempted
            foreach ($allCourseQuizzes as $quizId => $quizInfo) {
                if (!in_array($quizId, $attemptedQuizIds)) {
                    $studentPerformances[$studentId]['quiz_performances'][$quizId] = 0;
                }
            }
        }
    }

    // Calculate average performance for each student
    foreach ($studentPerformances as &$student) {
        // Calculate average across all quizzes (including zeros for non-attempts)
        $student['average_performance'] = round($student['total_performance'] / $student['quiz_count'], 2);
    }

    // Sort students by performance (descending)
    uasort($studentPerformances, function ($a, $b) {
        return $b['average_performance'] <=> $a['average_performance'];
    });

    // Assign ranks (handling ties with dense ranking)
    $rank = 1;
    $prevScore = null;
    $sameRankCount = 0;

    foreach ($studentPerformances as &$student) {
        if ($prevScore !== null && $student['average_performance'] < $prevScore) {
            $rank += $sameRankCount;
            $sameRankCount = 1;
        } else if ($prevScore !== null && $student['average_performance'] === $prevScore) {
            $sameRankCount++;
        } else {
            $sameRankCount = 1;
        }

        $student['rank'] = $rank;
        $prevScore = $student['average_performance'];

        // Add performance category (color) based on the existing function
        $student['performance_category'] = getColorForPerformance($student['average_performance']);
    }

    return $studentPerformances;
}
/**
 * Calculates section-wise student rankings for level 2 sections.
 * 
 * This function:
 * - Identifies all level 2 sections in the course structure
 * - For each section, considers all quizzes in that section and its child sections
 * - Calculates student rankings for each section using a dense ranking approach
 * - Includes all students, assigning 0% for non-attempted quizzes
 * 
 * @param array $allStudentQuizData Associative array of student quiz data keyed by student ID.
 * @param array $filteredCourseStructure The course structure containing all sections and quizzes.
 * @param array $allStudentData All student records (keyed by student id).
 * @return array Array of section IDs mapped to their student rankings.
 */
function calculateSectionWiseStudentRanks($allStudentQuizData, $filteredCourseStructure, $allStudentData)
{
    $sectionRankings = [];

    // Closure to find all level 2 sections recursively.
    $level2Sections = [];
    $findLevel2Sections = function ($sections, $currentLevel) use (&$findLevel2Sections, &$level2Sections) {
        foreach ($sections as $section) {
            if ($currentLevel == 2) {
                $level2Sections[] = $section;
            }
            if (isset($section['children']) && is_array($section['children'])) {
                $findLevel2Sections($section['children'], $currentLevel + 1);
            }
        }
    };
    $findLevel2Sections($filteredCourseStructure['sections'], 1);

    // For each level 2 section, calculate student rankings.
    foreach ($level2Sections as $section) {
        // Closure to extract all quiz IDs from a section and its children.
        $sectionQuizIds = [];
        $extractQuizIds = function ($section) use (&$extractQuizIds, &$sectionQuizIds) {
            if (isset($section['quizzes']) && is_array($section['quizzes'])) {
                foreach ($section['quizzes'] as $quiz) {
                    if (isset($quiz['quiz_id'])) {
                        $sectionQuizIds[] = $quiz['quiz_id'];
                    }
                }
            }
            if (isset($section['children']) && is_array($section['children'])) {
                foreach ($section['children'] as $child) {
                    $extractQuizIds($child);
                }
            }
        };
        $extractQuizIds($section);

        // Skip section if there are no quizzes.
        if (empty($sectionQuizIds)) {
            continue;
        }

        // Initialize student performance array for this section.
        $studentPerformances = [];
        foreach ($allStudentData as $student) {
            $studentId = $student['studentid'];
            $studentPerformances[$studentId] = [
                'student_id'         => $studentId,
                'name'               => $student['firstname'] . ' ' . $student['lastname'],
                'total_performance'  => 0,
                'quiz_count'         => count($sectionQuizIds), // Total quizzes in this section.
                'attempted_quizzes'  => 0,
                'quiz_performances'  => []  // Will hold individual quiz performances.
            ];
            // Initialize each quiz performance with 0.
            foreach ($sectionQuizIds as $quizId) {
                $studentPerformances[$studentId]['quiz_performances'][$quizId] = 0;
            }
        }

        // Process each student's quiz data.
        foreach ($allStudentQuizData as $studentId => $quizData) {
            // Assume transformQuizResults() is defined elsewhere.
            $resultJson = transformQuizResults($quizData);
            $result = json_decode($resultJson, true);
            if (isset($result['quiz_analysis']) && is_array($result['quiz_analysis'])) {
                foreach ($result['quiz_analysis'] as $quiz) {
                    if (isset($quiz['id']) && isset($quiz['performance_percentage'])) {
                        $quizId = $quiz['id'];
                        // Only consider quizzes in this section.
                        if (!in_array($quizId, $sectionQuizIds)) {
                            continue;
                        }
                        $performance = floatval($quiz['performance_percentage']);
                        $studentPerformances[$studentId]['total_performance'] += $performance;
                        $studentPerformances[$studentId]['attempted_quizzes']++;
                        $studentPerformances[$studentId]['quiz_performances'][$quizId] = $performance;
                    }
                }
            }
        }

        // Calculate the average performance for each student in this section.
        foreach ($studentPerformances as &$student) {
            $student['average_performance'] = round($student['total_performance'] / $student['quiz_count'], 2);
        }
        unset($student); // clear reference

        // Sort students by average performance in descending order.
        uasort($studentPerformances, function ($a, $b) {
            return $b['average_performance'] <=> $a['average_performance'];
        });

        // Assign dense ranks.
        $rank = 1;
        $prevScore = null;
        $sameRankCount = 0;
        foreach ($studentPerformances as &$student) {
            if ($prevScore !== null && $student['average_performance'] < $prevScore) {
                $rank += $sameRankCount;
                $sameRankCount = 1;
            } else if ($prevScore !== null && $student['average_performance'] === $prevScore) {
                $sameRankCount++;
            } else {
                $sameRankCount = 1;
            }
            $student['rank'] = $rank;
            $prevScore = $student['average_performance'];
            // Assume getColorForPerformance() is defined elsewhere.
            $student['performance_category'] = getColorForPerformance($student['average_performance']);
        }
        unset($student);

        // Store this section's rankings.
        $sectionRankings[$section['id']] = [
            'section_id'   => $section['id'],
            'section_name' => $section['name'],
            'rankings'     => array_values($studentPerformances)
        ];
    }

    return $sectionRankings;
}




/**
 * Renders the teacher extended report page with an improved UI.
 * 
 * This function takes report data and generates a formatted HTML page with an enhanced UI
 * for teachers to view student performance data. The report includes:
 * - Student rankings and performance categories
 * - Topic and subtopic analysis with detailed statistics
 * - Interactive charts and graphs showing performance trends
 * - Detailed quiz attempt information
 * - Responsive design with modern styling
 *
 * @param mixed $reportData Array or JSON string containing:
 *                         - hierarchy: Nested topic/subtopic structure with performance data
 *                         - student_statistics: Overall statistics across all students
 *                         - chart_data: Data for rendering various performance charts
 *                         - detailed_topic_stats: In-depth statistics for each topic
 *                         - detailed_subtopic_stats: In-depth statistics for each subtopic
 *                         - student_rankings: Student performance rankings and categories
 *                         - hasQuizAttempts: Boolean indicating if there are quiz attempts
 * @return string HTML content of the formatted teacher report
 */
function renderTeacherReport($reportData)
{
    // If not already a JSON string, encode it.
    $reportJson = is_string($reportData) ? $reportData : json_encode($reportData);
    ob_start(); // Start output buffering
?>
    <!-- Include Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');

        :root {
            --primary-color: #0D9488;
            --secondary-color: #7b1fa2;
            --text-color: #334155;
            --text-light: #64748B;
            --background-color: #F8FAFC;
            --card-bg: #ffffff;
            --border-color: #E2E8F0;
            --shadow-color: rgba(0, 0, 0, 0.05);
            --success-bg: #DCFCE7;
            --success-text: #16A34A;
            --warning-bg: #FEF3C7;
            --warning-text: #D97706;
            --error-bg: #FEE2E2;
            --error-text: #EF4444;
            --transition-speed: 0.3s;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--background-color);
            color: var(--text-color);
            line-height: 1.5;
        }

        /* Rankings Section Styles */
        .rankings-section {
            background: var(--card-bg);
            border-radius: 16px;
            padding: 2rem;
            box-shadow: 0 4px 16px var(--shadow-color);
            border: 1px solid var(--border-color);
            transition: all var(--transition-speed) cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
            margin-bottom: 2.5rem;
        }

        .rankings-section:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 24px var(--shadow-color);
        }

        .rankings-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            opacity: 0;
            transition: opacity var(--transition-speed) ease;
        }

        .rankings-section:hover::before {
            opacity: 1;
        }

        .rankings-table-container {
            margin-top: 1.5rem;
            border-radius: 12px;
            overflow: hidden;
            border: 1px solid var(--border-color);
        }

        .rankings-table-header {
            display: grid;
            grid-template-columns: 0.5fr 1fr 1fr 1fr 1fr;
            gap: 1rem;
            padding: 1rem;
            background: linear-gradient(to right, #F0F5FB, #F8FAFD);
            font-weight: 600;
            color: var(--text-color);
            border-bottom: 1px solid var(--border-color);
        }

        .rankings-table-body {
            max-height: 500px;
            overflow-y: auto;
        }

        .ranking-row {
            display: grid;
            grid-template-columns: 0.5fr 1fr 1fr 1fr 1fr;
            gap: 1rem;
            padding: 1rem;
            border-bottom: 1px solid var(--border-color);
            transition: all var(--transition-speed) ease;
            align-items: center;
        }

        .ranking-row:hover {
            background-color: #F8FAFD;
        }

        .rank-cell {
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .rank-medal {
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            color: white;
            font-size: 0.875rem;
        }

        .rank-1 {
            background: linear-gradient(135deg, #FFD700, #FFA500);
        }

        .rank-2 {
            background: linear-gradient(135deg, #C0C0C0, #A0A0A0);
        }

        .rank-3 {
            background: linear-gradient(135deg, #CD7F32, #8B4513);
        }

        .performance-badge {
            padding: 0.375rem 0.75rem;
            border-radius: 20px;
            font-size: 0.875rem;
            font-weight: 500;
        }

        .attempts-cell {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .status-cell {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .performance-dashboard {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 1rem;
        }

        /* Header Section */
        .header {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            padding: 2rem 1rem;
            border-radius: 16px;
            margin-bottom: 2rem;
            text-align: center;
            box-shadow: 0 4px 6px var(--shadow-color);
        }

        /* Widget Cards */
        /* Widgets Grid Layout */
        .widgets-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 2rem;
            margin-bottom: 2.5rem;
            padding: 0.5rem;
        }

        /* Widget Card */
        .widget {
            background: var(--card-bg);
            border-radius: 16px;
            box-shadow: 0 4px 16px var(--shadow-color);
            padding: 1.75rem;
            cursor: default;
            transition: all var(--transition-speed) cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            border: 1px solid var(--border-color);
            overflow: hidden;
        }

        .widget::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: linear-gradient(to right, var(--primary-color), var(--secondary-color));
            opacity: 0;
            transition: opacity var(--transition-speed) ease;
        }

        .widget:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 24px var(--shadow-color);
        }

        .widget:hover::before {
            opacity: 1;
        }

        /* Widget Header */
        .widget-header {
            display: flex;
            align-items: center;
            gap: 1.25rem;
            margin-bottom: 1.25rem;
        }

        /* Widget Icon */
        .widget-icon {
            width: 56px;
            height: 56px;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5rem;
            transition: all var(--transition-speed) ease;
            position: relative;
            box-shadow: 0 4px 12px rgba(var(--primary-color-rgb), 0.2);
        }

        .widget:hover .widget-icon {
            transform: scale(1.05);
            box-shadow: 0 6px 16px rgba(var(--primary-color-rgb), 0.25);
        }

        /* Widget Content */
        .widget-content {
            flex: 1;
        }

        .widget-content h3 {
            font-size: 1rem;
            color: var(--text-light);
            margin-bottom: 0.5rem;
            font-weight: 500;
            letter-spacing: -0.01em;
        }

        .widget-content p {
            font-size: 2rem;
            font-weight: 600;
            color: var(--text-color);
            letter-spacing: -0.02em;
            line-height: 1.2;
            margin: 0;
            display: flex;
            align-items: baseline;
            gap: 0.5rem;
        }

        /* Optional: Add trend indicators */
        .widget-trend {
            font-size: 0.875rem;
            font-weight: 500;
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
        }

        .widget-trend.positive {
            background-color: var(--success-bg);
            color: var(--success-text);
        }

        .widget-trend.negative {
            background-color: var(--error-bg);
            color: var(--error-text);
        }

        /* Responsive Adjustments */
        @media (max-width: 768px) {
            .widgets-grid {
                grid-template-columns: 1fr;
                gap: 1.5rem;
                padding: 0.25rem;
            }

            .widget {
                padding: 1.5rem;
            }

            .widget-icon {
                width: 48px;
                height: 48px;
                font-size: 1.25rem;
            }

            .widget-content p {
                font-size: 1.75rem;
            }
        }

        /* Chart Container */
        .chart-container {
            background: var(--card-bg);
            border-radius: 16px;
            padding: 2rem;
            box-shadow: 0 4px 16px var(--shadow-color);
            border: 1px solid var(--border-color);
            transition: all var(--transition-speed) cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
            margin-bottom: 2.5rem;
        }

        .chart-container:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 24px var(--shadow-color);
        }

        /* Gradient bar animation */
        .chart-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            opacity: 0;
            transition: opacity var(--transition-speed) ease;
        }

        .chart-container:hover::before {
            opacity: 1;
        }

        /* Widget Header Styling */
        .widget-header {
            display: flex;
            align-items: center;

        }

        .widget-icon {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.25rem;
            box-shadow: 0 4px 12px rgba(var(--primary-color-rgb), 0.2);
            transition: all var(--transition-speed) ease;
        }

        .chart-container:hover .widget-icon {
            transform: scale(1.05);
            box-shadow: 0 6px 16px rgba(var(--primary-color-rgb), 0.25);
        }

        .widget-content h3 {
            font-size: 1rem;
            color: var(--text-light);
            margin-bottom: 0.5rem;
            font-weight: 500;
            letter-spacing: -0.01em;
        }

        .widget-content span {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--text-color);
            letter-spacing: -0.02em;
        }

        /* Scroll Container */
        .chart-scroll-container {
            width: 100%;
            overflow-x: auto;
            padding: 0.5rem 0;
            position: relative;
            background: transparent;
        }

        /* Scrollbar styling */
        .chart-scroll-container::-webkit-scrollbar {
            height: 8px;
            background: transparent;
        }

        .chart-scroll-container::-webkit-scrollbar-track {
            background: rgba(var(--primary-color-rgb), 0.1);
            border-radius: 8px;
        }

        .chart-scroll-container::-webkit-scrollbar-thumb {
            background: var(--primary-color);
            border-radius: 8px;
            border: 2px solid var(--card-bg);
        }

        .chart-scroll-container::-webkit-scrollbar-thumb:hover {
            background: var(--secondary-color);
        }

        /* Chart Wrapper */
        .chart-wrapper {
            position: relative;
            height: 420px;
            min-width: 800px;
            transform: translateZ(0);
            overflow: visible;
        }

        /* Responsive Adjustments */
        @media (max-width: 768px) {
            .chart-container {
                padding: 1.5rem;
            }

            .widget-header {
                margin-bottom: 1.5rem;
            }

            .widget-icon {
                width: 40px;
                height: 40px;
                font-size: 1rem;
            }

            .widget-content span {
                font-size: 1.125rem;
            }

            .chart-wrapper {
                height: 360px;
                min-width: 600px;
            }
        }

        /* Legend Styles */
        .color-scheme-legend {
            margin-bottom: 2.5rem;
            background: var(--card-bg);
            padding: 1.5rem;
            border-radius: 16px;
            box-shadow: 0 4px 16px var(--shadow-color);
            border: 1px solid var(--border-color);
            transition: all var(--transition-speed) ease;
        }

        .color-scheme-legend:hover {
            box-shadow: 0 6px 20px var(--shadow-color);
        }

        .color-scheme-legend h2 {
            margin-bottom: 1rem;
            font-size: 1.125rem;
            color: var(--text-color);
            font-weight: 600;
            padding-bottom: 0.75rem;
            border-bottom: 1px solid var(--border-color);
        }

        .color-scheme-legend ul {
            list-style: none;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            padding: 0.5rem;
        }

        .color-scheme-legend li {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.5rem;
            border-radius: 8px;
            transition: background-color var(--transition-speed) ease;
        }

        .color-scheme-legend li:hover {
            background-color: #F8FAFD;
        }

        .legend-swatch {
            display: inline-block;
            width: 20px;
            height: 20px;
            border-radius: 6px;
            transition: transform var(--transition-speed) ease;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .legend-swatch.success {
            background-color: var(--success-bg);
        }

        .legend-swatch.warning {
            background-color: var(--warning-bg);
        }

        .legend-swatch.error {
            background-color: var(--error-bg);
        }

        .color-scheme-legend li:hover .legend-swatch {
            transform: scale(1.1);
        }

        /* Legend Label */
        .legend-label {
            font-size: 0.95rem;
            color: var(--text-color);
            font-weight: 500;
        }

        /* Responsive Adjustments */
        @media (max-width: 768px) {
            .chart-grid {
                grid-template-columns: 1fr;
                gap: 1.5rem;
            }

            .chart-container {
                height: 380px;
                padding: 1.25rem;
            }

            .color-scheme-legend ul {
                grid-template-columns: 1fr;
            }
        }

        /* Hierarchy Section Container */
        .hierarchy-section {
            background: var(--card-bg);
            border-radius: 16px;
            padding: 1.5rem;
            margin-bottom: 2.5rem;
            box-shadow: 0 4px 16px var(--shadow-color);
            border: 1px solid var(--border-color);
            transition: all var(--transition-speed) ease;
        }

        .hierarchy-section:hover {
            box-shadow: 0 6px 24px var(--shadow-color);
        }

        .hierarchy-title {
            font-size: 1.375rem;
            font-weight: 600;
            margin-bottom: 1.5rem;
            padding-bottom: 0.75rem;
            border-bottom: 2px solid var(--border-color);
            color: var(--text-color);
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        /* Table Header and Rows */
        .hierarchy-table-header,
        .hierarchy-row {
            display: flex;
            align-items: center;
            border-radius: 8px;
            margin-bottom: 0.5rem;
            transition: all var(--transition-speed) ease;
        }

        .hierarchy-table-header {
            padding: 1rem;
            font-weight: 600;
            background: linear-gradient(to right, #F0F5FB, #F8FAFD);
            border: 1px solid var(--border-color);
            color: var(--text-color);
        }

        .hierarchy-row {
            padding: 0.875rem 1rem;
            cursor: pointer;
            border: 1px solid transparent;
            background-color: var(--card-bg);
            position: relative;
        }

        .hierarchy-row::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            width: 4px;
            height: 100%;
            background: linear-gradient(to bottom, var(--primary-color), var(--secondary-color));
            border-radius: 4px 0 0 4px;
            opacity: 0;
            transition: opacity var(--transition-speed) ease;
        }

        .hierarchy-row:hover {
            background-color: #F8FAFD;
            border-color: var(--border-color);
            transform: translateX(2px);
            box-shadow: 0 2px 8px var(--shadow-color);
        }

        .hierarchy-row:hover::before {
            opacity: 1;
        }

        .hierarchy-row>div,
        .hierarchy-table-header>div {
            padding: 0 0.75rem;
            flex: 1;
            display: flex;
            align-items: center;
        }

        /* Chip Styles */
        .chip {
            padding: 0.375rem 0.875rem;
            border-radius: 20px;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 0.375rem;
            font-size: 0.875rem;
            transition: all var(--transition-speed) ease;
        }

        /* Standardize all green indicators */
        .chip.green,
        .chip.success,
        .legend-swatch.success {
            background-color: var(--success-bg);
            color: var(--success-text);
        }

        /* Remove green1 class entirely and replace with success */
        .chip.yellow {
            background-color: var(--warning-bg);
            color: var(--warning-text);
        }

        .chip.red {
            background-color: var(--error-bg);
            color: var(--error-text);
        }

        .chip:hover {
            filter: brightness(0.95);
            transform: translateY(-1px);
        }

        /* Child Container */
        .hierarchy-child-container {
            margin: 0.75rem 0 0.75rem 1.5rem;
            display: none;
            flex-direction: column;
            gap: 0.5rem;
            animation: slideDown var(--transition-speed) ease forwards;
            padding-left: 1rem;
            border-left: 2px dashed var(--border-color);
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Expanded State */
        .hierarchy-row.expanded {
            background-color: #F8FAFD;
            border-color: var(--border-color);
            margin-bottom: 0;
        }

        .hierarchy-row.expanded::before {
            opacity: 1;
        }

        /* Responsive Adjustments */
        @media (max-width: 768px) {
            .hierarchy-section {
                padding: 1rem;
            }

            .hierarchy-table-header,
            .hierarchy-row {
                padding: 0.75rem;
            }

            .hierarchy-child-container {
                margin-left: 1rem;
            }

            .chip {
                padding: 0.25rem 0.625rem;
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

        /* Quiz Toggle Row – now clickable as a whole row */
        /* Quiz Toggle Row Styles */
        .quiz-toggle-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 1rem 1.25rem;
            margin: 0.75rem 1.5rem;
            background-color: #F8FAFD;
            border: 1px solid var(--border-color);
            border-radius: 12px;
            cursor: pointer;
            transition: all var(--transition-speed) ease;
            position: relative;
            overflow: hidden;
        }

        .quiz-toggle-row::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            width: 4px;
            height: 100%;
            background: linear-gradient(to bottom, var(--primary-color), var(--secondary-color));
            opacity: 0;
            transition: opacity var(--transition-speed) ease;
        }

        .quiz-toggle-row:hover {
            background-color: #F0F5FB;
            transform: translateX(2px);
            box-shadow: 0 2px 8px var(--shadow-color);
        }

        .quiz-toggle-row:hover::before {
            opacity: 1;
        }

        .quiz-toggle-text {
            flex: 1;
            font-size: 0.95rem;
            font-weight: 500;
            color: var(--text-color);
            margin-right: 1rem;
        }

        .quiz-toggle-icon {
            color: var(--primary-color);
            transition: transform var(--transition-speed) ease;
            width: 20px;
            height: 20px;
        }

        .quiz-toggle-row:hover .quiz-toggle-icon {
            transform: translateX(-2px);
        }

        /* Quiz Dropdown Container */
        .quiz-dropdown-container {
            margin: 0.5rem 1.5rem 1.5rem;
            display: none;
            flex-direction: column;
            animation: fadeIn var(--transition-speed) ease forwards;
            border: 1px solid var(--border-color);
            border-radius: 12px;
            overflow: hidden;
            background: var(--card-bg);
            box-shadow: 0 4px 12px var(--shadow-color);
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Quiz Row Styles */
        .quiz-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 1rem 1.25rem;
            background: var(--card-bg);
            border-bottom: 1px solid var(--border-color);
            transition: background-color var(--transition-speed) ease;
        }

        .quiz-row:hover {
            background-color: #F8FAFD;
        }

        .quiz-row:last-child {
            border-bottom: none;
        }

        .quiz-info {
            display: flex;
            align-items: center;
            gap: 1rem;
            font-size: 0.95rem;
            color: var(--text-color);
        }

        /* View Quiz Button */
        .view-quiz {
            background-color: var(--primary-color);
            color: white;
            border: none;
            padding: 0.5rem 1.125rem;
            border-radius: 20px;
            cursor: pointer;
            font-size: 0.875rem;
            font-weight: 500;
            transition: all var(--transition-speed) cubic-bezier(0.4, 0, 0.2, 1);
            display: flex;
            align-items: center;
            gap: 0.5rem;
            position: relative;
            overflow: hidden;
        }

        .view-quiz:hover {
            background-color: #8DB3D6;
            /* Slightly darker shade */
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(157, 195, 230, 0.25);
        }

        .view-quiz:active {
            transform: translateY(0);
            box-shadow: 0 2px 6px rgba(157, 195, 230, 0.15);
        }

        /* Optional: Add focus state for accessibility */
        .view-quiz:focus {
            outline: 2px solid var(--primary-color);
            outline-offset: 2px;
        }

        /* Optional: Add disabled state */
        .view-quiz:disabled {
            background-color: #CBD5E1;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        /* Quiz Status Indicators */
        .quiz-status {
            display: inline-flex;
            align-items: center;
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-size: 0.875rem;
            font-weight: 500;
        }

        .quiz-status.completed {
            background-color: var(--success-bg);
            color: var(--success-text);
        }

        .quiz-status.in-progress {
            background-color: var(--warning-bg);
            color: var(--warning-text);
        }

        .quiz-status.not-started {
            background-color: var(--error-bg);
            color: var(--error-text);
        }

        /* Modal styles */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            opacity: 0;
            transition: opacity var(--transition-speed);
        }

        .modal-content {
            background: var(--card-bg);
            width: 90%;
            max-width: 600px;
            margin: 5% auto;
            padding: 2rem;
            border-radius: 12px;
            position: relative;
            box-shadow: 0 4px 6px var(--shadow-color);
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--border-color);
        }

        .modal-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--text-color);
        }

        .close-modal {
            background: none;
            border: none;
            font-size: 1.5rem;
            color: var(--text-light);
            cursor: pointer;
            padding: 0.5rem;
        }

        .student-list {
            background: var(--background-color);
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1rem;
        }

        .student-list h4 {
            color: var(--text-color);
            margin-bottom: 0.75rem;
        }

        .student-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem;
            border-radius: 4px;
        }

        .chip {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            /* full pill shape */
            font-weight: 600;
            font-size: 0.9rem;
            /* adjust as needed */
            margin-left: 0.5rem;
            /* small spacing if needed */
        }

        /* Example color classes (already in your code, but you can refine them): */
        .chip.green,
        .chip.green1 {
            background-color: var(--success-bg);
            /* #DCFCE7 */
            color: var(--success-text);
            /* #16A34A */
        }

        .chip.yellow {
            background-color: var(--warning-bg);
            /* #FEF3C7 */
            color: var(--warning-text);
            /* #D97706 */
        }

        .chip.red {
            background-color: var(--error-bg);
            /* #FEE2E2 */
            color: var(--error-text);
            /* #EF4444 */
        }


        @media (max-width: 768px) {
            .widgets-grid {
                grid-template-columns: 1fr;
            }

            .chart-grid {
                grid-template-columns: 1fr;
            }

            .modal-content {
                width: 95%;
                margin: 10% auto;
            }
        }
    </style>


    <div class="performance-dashboard">
        <!-- Header Section -->
        <div style="display: flex; align-items: center; justify-content: center; font-weight: bold;">
            <p style="font-size: 1.2rem; color: var(--text-color); margin: 0;">Total Class Strength:</p>
            <p id="totalStudentsWidget" style="font-size: 1.5rem; color: var(--primary-color); margin: 0 0 0 0.5rem;">0</p>
        </div>

        <div class="header">
            <h1>Course Performance Report</h1>
        </div>

        <!-- Widgets Section -->
        <!-- <div class="widgets-grid">
            <div class="widget">
                <div class="widget-header">
                    <div class="widget-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="widget-content">
                        <h3>Total Students</h3>
                        <p id="totalStudentsWidget">0</p>
                    </div>
                </div>
            </div>
            <div class="widget">
                <div class="widget-header">
                    <div class="widget-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="widget-content">
                        <h3>Attempted</h3>
                        <p id="attemptedStudentsWidget">0</p>
                    </div>
                </div>
            </div>
            <div class="widget">
                <div class="widget-header">
                    <div class="widget-icon">
                        <i class="fas fa-times-circle"></i>
                    </div>
                    <div class="widget-content">
                        <h3>Not Attempted</h3>
                        <p id="notAttemptedWidget">0</p>
                    </div>
                </div>
            </div>
        </div> -->

        <!-- Charts Section -->
        <!-- <div class="chart-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(450px, 1fr)); gap: 2rem; margin-bottom: 2.5rem; padding: 0.5rem;">
            <div class="chart-container" style="background: var(--card-bg); border-radius: 16px; padding: 1.75rem; height: 420px; box-shadow: 0 4px 16px var(--shadow-color); border: 1px solid var(--border-color); transition: all var(--transition-speed) cubic-bezier(0.4, 0, 0.2, 1); position: relative; overflow: hidden;">
                <h3 class="chart-title">Topic Performance</h3>
                <canvas id="topicChart"></canvas>
            </div>
            <div class="chart-container" style="background: var(--card-bg); border-radius: 16px; padding: 1.75rem; height: 420px; box-shadow: 0 4px 16px var(--shadow-color); border: 1px solid var(--border-color); transition: all var(--transition-speed) cubic-bezier(0.4, 0, 0.2, 1); position: relative; overflow: hidden;">
                <h3 class="chart-title">Subtopic Performance</h3>
                <canvas id="subtopicChart"></canvas>
            </div>
        </div> -->
        <!-- Charts Section: Subtopic Bar Chart with Horizontal Scroll -->
        <div class="chart-container">
            <div class="widget-header">
                <div class="widget-icon">
                    📊
                </div>
                <div class="widget-content">
                    <h3>Performance Analysis</h3>
                    <span>Topic Wise Average Performance</span>
                </div>
            </div>

            <div class="chart-scroll-container">
                <div class="chart-wrapper" id="subtopicBarChartWrapper">
                    <canvas id="subtopicBarChart"></canvas>
                </div>
            </div>
        </div>
        <!-- Legend Section -->
        <div class="color-scheme-legend">
            <h2>Performance Color Scheme</h2>
            <ul>
                <li>
                    <span class="legend-swatch success"></span>
                    <span>Excellent (80% - 100%)</span>
                </li>
                <li>
                    <span class="legend-swatch warning"></span>
                    <span>Average (40% - 79%)</span>
                </li>
                <li>
                    <span class="legend-swatch error"></span>
                    <span>Needs Improvement (0% - 39%)</span>
                </li>
            </ul>
        </div>
        <!-- Student Rankings Section -->
        <div class="rankings-section">
            <div class="widget-header">
                <div class="widget-icon">
                    <i class="fas fa-trophy"></i>
                </div>
                <div class="widget-content">
                    <h3>Student Performance</h3>
                    <span>Overall Rankings</span>
                </div>
            </div>

            <div class="rankings-table-container">
                <div class="rankings-table-header">
                    <div class="rank-cell">Rank</div>
                    <div class="student-cell">Student Name</div>
                    <div class="performance-cell">Performance</div>
                    <div class="attempts-cell">Attempted/Total</div>
                    <div class="status-cell">Status</div>
                </div>
                <div id="rankingsTableBody" class="rankings-table-body" style="max-height: 400px; overflow-y: auto;">
                    <!-- Rankings will be dynamically inserted here -->
                </div>
            </div>
        </div>


        <!-- Integrated Collapsible Course Hierarchy Table -->
        <div class="hierarchy-section">
            <h3 class="hierarchy-title">Topic wise class performance</h3>
            <div id="hierarchyTableContainer"></div>
        </div>

        <!-- Modal for Quiz Details -->
        <div id="quizStudentsModal" class="modal">
            <div class="modal-content">
                <div class="modal-header">
                    <h3 class="modal-title" id="modalQuizName">Quiz Details</h3>
                    <button class="close-modal" id="modalClose">&times;</button>
                </div>
                <div class="student-list">
                    <h4>Attempted Students</h4>
                    <div id="attemptedStudentsList"></div>
                </div>
                <div class="student-list">
                    <h4>Not Attempted Students</h4>
                    <div id="notAttemptedStudentsList"></div>
                </div>
            </div>
        </div>
    </div>

    <div id="sectionRankingsModal" class="modal">
        <div class="modal-content" style="max-width: 800px; width: 90%;">
            <div class="modal-header">
                <h3 class="modal-title" id="modalSectionName">Section Student Rankings</h3>
                <button class="close-modal" id="sectionModalClose">&times;</button>
            </div>
            <div class="rankings-table-container">
                <div class="rankings-table-header">
                    <div class="rank-cell">Rank</div>
                    <div class="student-cell">Student Name</div>
                    <div class="performance-cell">Performance</div>
                    <div class="attempts-cell">Attempted/Total</div>
                    <div class="status-cell">Status</div>
                </div>
                <div id="sectionRankingsTableBody" class="rankings-table-body">
                    <!-- Section rankings will be inserted here -->
                </div>
            </div>
        </div>
    </div>
    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        // Parse the injected JSON data
        const reportData = <?php echo $reportJson; ?>;

        // Animate numbers in widgets
        function animateNumber(element, target, duration = 1000) {
            const steps = 60;
            const stepTime = duration / steps;
            const stepValue = target / steps;
            let current = 0,
                step = 0;
            const timer = setInterval(() => {
                current += stepValue;
                step++;
                if (step >= steps) {
                    clearInterval(timer);
                    current = target;
                }
                element.textContent = Math.round(current);
            }, stepTime);
        }

        function initializeRankings() {
            if (!reportData.student_rankings) return;

            const rankingsTableBody = document.getElementById('rankingsTableBody');
            rankingsTableBody.innerHTML = '';

            reportData.student_rankings.forEach((student) => {
                const row = document.createElement('div');
                row.className = 'ranking-row';

                // Rank cell with medal for top 3
                const rankCell = document.createElement('div');
                rankCell.className = 'rank-cell';
                if (student.rank <= 3) {
                    rankCell.innerHTML = `
                <div class="rank-medal rank-${student.rank}">
                    <i class="fas fa-medal"></i>
                </div>
                ${student.rank}`;
                } else {
                    rankCell.textContent = student.rank;
                }

                // Student Name cell (using name instead of just ID)
                const studentCell = document.createElement('div');
                studentCell.className = 'student-cell';
                studentCell.textContent = student.name || student.student_id;

                // Performance cell with badge
                const performanceCell = document.createElement('div');
                performanceCell.className = 'performance-cell';
                performanceCell.innerHTML = `
            <span class="performance-badge chip ${student.performance_category}">
                ${student.average_performance}%
            </span>`;

                // Attempts cell - now showing attempted/total ratio
                const attemptsCell = document.createElement('div');
                attemptsCell.className = 'attempts-cell';
                attemptsCell.innerHTML = `
            <i class="fas fa-clipboard-check"></i>
            ${student.attempted_quizzes || 0}/${student.quiz_count || 0}`;

                // Status cell
                const statusCell = document.createElement('div');
                statusCell.className = 'status-cell';
                const status = determineStatus(student.average_performance);
                statusCell.innerHTML = `<span class="chip ${status.color}">${status.label}</span>`;

                // Append all cells
                row.appendChild(rankCell);
                row.appendChild(studentCell);
                row.appendChild(performanceCell);
                row.appendChild(attemptsCell);
                row.appendChild(statusCell);

                rankingsTableBody.appendChild(row);
            });
        }
        // Function to populate the section rankings modal
        function showSectionRankingsModal(sectionId) {
            // Find section data in report
            const sectionData = reportData.section_rankings[sectionId];
            if (!sectionData) {
                console.error('Section data not found for ID:', sectionId);
                return;
            }

            const modal = document.getElementById('sectionRankingsModal');
            const modalTitle = document.getElementById('modalSectionName');
            const rankingsTableBody = document.getElementById('sectionRankingsTableBody');

            // Set section name in modal title
            modalTitle.textContent = `Section Rankings: ${sectionData.section_name}`;

            // Clear existing rankings
            rankingsTableBody.innerHTML = '';

            // Populate rankings table
            sectionData.rankings.forEach((student) => {
                const row = document.createElement('div');
                row.className = 'ranking-row';

                // Rank cell with medal for top 3
                const rankCell = document.createElement('div');
                rankCell.className = 'rank-cell';
                if (student.rank <= 3) {
                    rankCell.innerHTML = `
                <div class="rank-medal rank-${student.rank}">
                    <i class="fas fa-medal"></i>
                </div>
                ${student.rank}`;
                } else {
                    rankCell.textContent = student.rank;
                }

                // Student Name cell
                const studentCell = document.createElement('div');
                studentCell.className = 'student-cell';
                studentCell.textContent = student.name || student.student_id;

                // Performance cell with badge
                const performanceCell = document.createElement('div');
                performanceCell.className = 'performance-cell';
                performanceCell.innerHTML = `<span class="performance-badge chip ${student.performance_category}">
                                                ${student.average_performance}%
                                            </span>`;

                // Attempts cell
                const attemptsCell = document.createElement('div');
                attemptsCell.className = 'attempts-cell';
                attemptsCell.innerHTML = `
            <i class="fas fa-clipboard-check"></i>
            ${student.attempted_quizzes || 0}/${student.quiz_count || 0}`;

                // Status cell
                const statusCell = document.createElement('div');
                statusCell.className = 'status-cell';
                const status = determineStatus(student.average_performance);
                statusCell.innerHTML = `<span class="chip ${status.color}" style="font-size: 0.75rem;">${status.label}</span>`;

                // Append all cells
                row.appendChild(rankCell);
                row.appendChild(studentCell);
                row.appendChild(performanceCell);
                row.appendChild(attemptsCell);
                row.appendChild(statusCell);

                rankingsTableBody.appendChild(row);
            });

            // Show the modal
            modal.style.display = 'block';
            requestAnimationFrame(() => {
                modal.style.opacity = '1';
            });
        }


        function determineStatus(performance) {
            if (performance >= 80) {
                return {
                    label: 'Excellent',
                    color: 'success'
                };
            } else if (performance >= 40) {
                return {
                    label: 'Average',
                    color: 'yellow'
                };
            } else {
                return {
                    label: 'Needs Improvement',
                    color: 'red' // Changed from 'error' to 'red'
                };
            }
        }
        // Create quiz dropdown container for a node
        function createQuizDropdown(node, level) {
            const container = document.createElement('div');
            container.className = 'quiz-dropdown-container';

            // Header for quiz table
            const headerRow = document.createElement('div');
            headerRow.className = 'hierarchy-table-header';
            headerRow.style.paddingLeft = ((level + 1) * 20) + 'px';
            const quizHeaders = ['Quiz Name', 'Average', 'Action'];
            const quizFlexValues = [2, 1, 1, 1, 1, 1];
            quizHeaders.forEach((text, index) => {
                const cell = document.createElement('div');
                cell.textContent = text;
                cell.style.flex = quizFlexValues[index];
                headerRow.appendChild(cell);
            });
            container.appendChild(headerRow);

            // Quiz rows
            node.quizzes.forEach(quiz => {
                const quizRow = document.createElement('div');
                quizRow.className = 'quiz-row';
                quizRow.style.paddingLeft = ((level + 1) * 20) + 'px';

                // Quiz Name
                const nameCell = document.createElement('div');
                nameCell.textContent = quiz.name || 'Quiz';
                nameCell.style.flex = '2';
                quizRow.appendChild(nameCell);

                // Median Performance
                // const medianCell = document.createElement('div');
                // medianCell.textContent = quiz.median_performance + '%';
                // medianCell.style.flex = '1';
                // quizRow.appendChild(medianCell);

                // Below Median
                // const belowMedianCell = document.createElement('div');
                // belowMedianCell.textContent = quiz.percent_below_median + '%';
                // belowMedianCell.style.flex = '1';
                // quizRow.appendChild(belowMedianCell);

                // Above Median
                // const aboveMedianCell = document.createElement('div');
                // aboveMedianCell.textContent = quiz.percent_above_median + '%';
                // aboveMedianCell.style.flex = '1';
                // quizRow.appendChild(aboveMedianCell);

                // Average Performance: using a colored chip
                const avgCell = document.createElement('div');
                avgCell.style.flex = '1';

                const avgChip = document.createElement('span');
                // If you used "green1" for "green", convert it here:
                // New code: determine color based solely on quiz.average_performance
                const quizStatus = determineStatus(quiz.average_performance);
                const chipColorClass = quizStatus.color; // 'success', 'warning', or 'error'
                avgChip.className = 'chip ' + chipColorClass;
                avgChip.textContent = quiz.average_performance + '%';


                avgCell.appendChild(avgChip);
                quizRow.appendChild(avgCell);

                // Action Button (View Quiz)
                const actionCell = document.createElement('div');
                actionCell.style.flex = '1';
                const viewButton = document.createElement('button');
                viewButton.className = 'view-quiz';
                viewButton.innerHTML = '<i class="fas fa-eye"></i> View';
                viewButton.onclick = (e) => {
                    e.stopPropagation(); // Prevent triggering the row toggle
                    showQuizModal(quiz);
                };
                actionCell.appendChild(viewButton);
                quizRow.appendChild(actionCell);

                container.appendChild(quizRow);
            });

            return container;
        }


        // Create a row for a hierarchy node (section)
        function createHierarchyRow(node, level) {
            const rowWrapper = document.createElement('div');
            rowWrapper.className = 'hierarchy-row-wrapper';
            rowWrapper.style.border = '1px solid var(--border-color)'; // Add border to the row wrapper

            // Main row for the node
            const row = document.createElement('div');
            row.className = 'hierarchy-row';
            row.style.paddingLeft = (level * 20) + 'px';

            // Toggle cell for child nodes
            const toggleCell = document.createElement('div');
            toggleCell.style.flex = '0 0 20px';
            row.appendChild(toggleCell);

            // If children exist, add an icon
            let childContainer = null;
            if (node.children && node.children.length > 0) {
                const toggleIcon = document.createElement('i');
                toggleIcon.className = 'fas fa-chevron-down';
                toggleIcon.style.marginRight = '8px';
                toggleCell.appendChild(toggleIcon);

                childContainer = document.createElement('div');
                childContainer.className = 'hierarchy-child-container';

                // Clicking anywhere on the row toggles child nodes
                row.addEventListener('click', function(e) {
                    // Avoid toggling if clicking on quiz-toggle or action button
                    if (e.target.closest('.quiz-toggle-row') || e.target.closest('.view-quiz')) return;

                    if (!childContainer.style.display || childContainer.style.display === 'none') {
                        childContainer.style.display = 'flex';
                        toggleIcon.classList.replace('fa-chevron-down', 'fa-chevron-up');
                    } else {
                        childContainer.style.display = 'none';
                        toggleIcon.classList.replace('fa-chevron-up', 'fa-chevron-down');
                    }
                });
            }

            const nameCell = document.createElement('div');
            nameCell.style.flex = '2';
            nameCell.style.display = 'flex';
            nameCell.style.alignItems = 'center';
            nameCell.style.gap = '10px';

            // Section name
            const sectionName = document.createElement('span');
            sectionName.textContent = node.name;
            // Allow the section name to take up remaining space and truncate if too long:
            sectionName.style.flex = '1';
            sectionName.style.overflow = 'hidden';
            sectionName.style.textOverflow = 'ellipsis';
            sectionName.style.whiteSpace = 'nowrap';
            nameCell.appendChild(sectionName);

            // Add "View Rankings" button for level 1 sections
            // Inside the createHierarchyRow function, update the section rankings button creation:
            if (level === 1) {
                console.log("Processing level 2 section:", node.id);
                if (reportData.section_rankings && reportData.section_rankings[node.id]) {
                    console.log("Creating rankings button for section:", node.id);
                    const viewRankingsBtn = document.createElement('button');
                    viewRankingsBtn.className = 'view-quiz view-rankings-btn';
                    viewRankingsBtn.innerHTML = '<i class="fas fa-users"></i> Rankings';
                    viewRankingsBtn.style.fontSize = '0.8rem';
                    viewRankingsBtn.style.padding = '4px 8px';
                    // Prevent the button from shrinking:
                    viewRankingsBtn.style.flex = '0 0 auto';
                    // Optionally push the button to the right if desired:
                    viewRankingsBtn.style.marginLeft = 'auto';
                    viewRankingsBtn.onclick = (e) => {
                        e.stopPropagation(); // Prevent triggering the row toggle
                        showSectionRankingsModal(node.id);
                    };
                    nameCell.appendChild(viewRankingsBtn);
                } else {
                    console.log("No rankings found for section:", node.id);
                }
            }

            row.appendChild(nameCell);

            // 2) Median Performance
            // const medianCell = document.createElement('div');
            // medianCell.textContent = node.median_performance + '%';
            // medianCell.style.flex = '1';
            // row.appendChild(medianCell);

            // 3) Below Median
            // const belowCell = document.createElement('div');
            // belowCell.textContent = node.percent_below_median + '%';
            // belowCell.style.flex = '1';
            // row.appendChild(belowCell);

            // 4) Above Median
            // const aboveCell = document.createElement('div');
            // aboveCell.textContent = node.percent_above_median + '%';
            // aboveCell.style.flex = '1';
            // row.appendChild(aboveCell);

            // 5) Average Performance: now a single chip with the percentage
            const averageCell = document.createElement('div');
            averageCell.style.flex = '1';

            // Create a chip and set the entire percentage text inside it
            const avgChip = document.createElement('span');
            // If you used "green1" for "green", convert it here:
            const chipColorClass = (node.color === 'green1' || node.color === 'green') ? 'success' : node.color;
            avgChip.className = 'chip ' + chipColorClass;
            avgChip.textContent = node.average_performance + '%';

            averageCell.appendChild(avgChip);
            row.appendChild(averageCell);

            rowWrapper.appendChild(row);

            // If the node has quizzes, create a dedicated quiz-toggle row
            if (node.quizzes && node.quizzes.length > 0) {
                const quizToggleRow = document.createElement('div');
                quizToggleRow.className = 'quiz-toggle-row';
                quizToggleRow.style.marginTop = '4px';

                // Toggle text + icon
                const toggleText = document.createElement('div');
                toggleText.className = 'quiz-toggle-text';
                toggleText.textContent = 'Show Quizzes';
                quizToggleRow.appendChild(toggleText);

                const toggleIcon = document.createElement('i');
                toggleIcon.className = 'fas fa-chevron-down quiz-toggle-icon';
                quizToggleRow.appendChild(toggleIcon);

                rowWrapper.appendChild(quizToggleRow);

                // Create quiz dropdown container
                const quizDropdown = createQuizDropdown(node, level);
                rowWrapper.appendChild(quizDropdown);

                // Toggle event
                quizToggleRow.addEventListener('click', () => {
                    if (!quizDropdown.style.display || quizDropdown.style.display === 'none') {
                        quizDropdown.style.setProperty('display', 'flex', 'important');
                        toggleIcon.style.transform = 'rotate(180deg)';
                        toggleText.textContent = 'Hide Quizzes';
                    } else {
                        quizDropdown.style.setProperty('display', 'none', 'important');
                        toggleIcon.style.transform = 'rotate(0deg)';
                        toggleText.textContent = 'Show Quizzes';
                    }
                });
            }

            // Append child rows
            if (childContainer) {
                node.children.forEach(child => {
                    childContainer.appendChild(createHierarchyRow(child, level + 1));
                });
                rowWrapper.appendChild(childContainer);
            }

            return rowWrapper;
        }

        // Render the hierarchy table view with 5 columns (no separate color column).
        function renderHierarchyTableView(hierarchyData) {
            const container = document.getElementById('hierarchyTableContainer');
            container.innerHTML = '';

            // Global header row
            const globalHeaderRow = document.createElement('div');
            globalHeaderRow.className = 'hierarchy-table-header';

            // Toggle icon cell (empty)
            const globalToggleCell = document.createElement('div');
            globalToggleCell.style.flex = '0 0 20px';
            globalHeaderRow.appendChild(globalToggleCell);

            // The other 5 columns
            const headers = ['Topic Name', 'Average'];
            const flexValues = [2, 1, 1, 1, 1];
            headers.forEach((text, index) => {
                const cell = document.createElement('div');
                cell.textContent = text;
                cell.style.flex = flexValues[index];
                globalHeaderRow.appendChild(cell);
            });

            container.appendChild(globalHeaderRow);

            // Render each top-level node
            hierarchyData.forEach(node => {
                container.appendChild(createHierarchyRow(node, 0));
            });
        }




        // Modal functionality for quiz details
        const modal = document.getElementById('quizStudentsModal');
        const modalClose = document.getElementById('modalClose');

        function showQuizModal(quiz) {
            const modalTitle = document.getElementById('modalQuizName');
            const attemptedList = document.getElementById('attemptedStudentsList');
            const notAttemptedList = document.getElementById('notAttemptedStudentsList');

            // Set the quiz name
            modalTitle.textContent = `Quiz: ${quiz.name || 'Quiz'}`;

            // Clear existing data
            attemptedList.innerHTML = '';
            notAttemptedList.innerHTML = '';

            // Render students who attempted the quiz
            function renderStudentList(students, container, type) {
                if (students && students.length > 0) {
                    students.forEach(student => {
                        const item = document.createElement('div');
                        item.className = 'student-item';
                        if (typeof student === 'object') {
                            if (type === 'attempted') {
                                item.innerHTML = `<i class="fas fa-check-circle"></i>
                                      <span>${student.name} (ID: ${student.studentid}) - ${student.performance_percentage}%</span>`;
                            } else if (type === 'not-attempted') {
                                item.innerHTML = `<i class="fas fa-times-circle"></i>
                                      <span>${student.name} (ID: ${student.studentid})</span>`;
                            }
                        } else {
                            // Fallback in case student data is not an object.
                            item.innerHTML = `<i class="fas fa-${type === 'attempted' ? 'check' : 'times'}-circle"></i>
                                  <span>Student ID: ${student}</span>`;
                        }
                        container.appendChild(item);
                    });
                } else {
                    const message = document.createElement('div');
                    message.className = 'student-item';
                    message.textContent = type === 'attempted' ? 'No students attempted.' : 'All students attempted.';
                    container.appendChild(message);
                }
            }



            renderStudentList(quiz.attempted_students, attemptedList, 'attempted');
            renderStudentList(quiz.not_attempted_students, notAttemptedList, 'not-attempted');

            // Show the modal
            modal.style.display = 'block';
            requestAnimationFrame(() => {
                modal.style.opacity = '1';
            });
        }
        // Add close button handler for section rankings modal
        document.getElementById('sectionModalClose').onclick = () => {
            const modal = document.getElementById('sectionRankingsModal');
            modal.style.opacity = '0';
            setTimeout(() => {
                modal.style.display = 'none';
            }, 300);
        };
        window.onclick = (event) => {
            const quizModal = document.getElementById('quizStudentsModal');
            const sectionModal = document.getElementById('sectionRankingsModal');

            if (event.target === quizModal) {
                quizModal.style.opacity = '0';
                setTimeout(() => {
                    quizModal.style.display = 'none';
                }, 300);
            } else if (event.target === sectionModal) {
                sectionModal.style.opacity = '0';
                setTimeout(() => {
                    sectionModal.style.display = 'none';
                }, 300);
            }
        };

        modalClose.onclick = () => {
            modal.style.opacity = '0';
            setTimeout(() => {
                modal.style.display = 'none';
            }, 300);
        };




        // Initialize after DOM content is loaded
        document.addEventListener('DOMContentLoaded', () => {
            const stats = reportData.student_statistics;
            animateNumber(document.getElementById('totalStudentsWidget'), stats.total_students);
            animateNumber(document.getElementById('attemptedStudentsWidget'), stats.attempted_students);
            animateNumber(document.getElementById('notAttemptedWidget'), stats.not_attempted_students);
            initializeRankings();

            // Render the hierarchy table view  
            renderHierarchyTableView(reportData.hierarchy);
            // Initialize charts if quiz attempts exist
            if (reportData.hasQuizAttempts) {

                // Subtopic Bar Chart
                // Function to calculate chart width based on labels
                function calculateChartWidth(labels, minWidth = 800) {
                    const minWidthPerLabel = 150; // Minimum width per label in pixels
                    return Math.max(minWidth, labels.length * minWidthPerLabel);
                }

                // Function to set chart wrapper width
                function setChartWrapperWidth(wrapperId, labels) {
                    const wrapper = document.getElementById(wrapperId);
                    if (wrapper) {
                        const width = calculateChartWidth(labels);
                        wrapper.style.width = `${width}px`;
                    }
                }
                // Subtopic Bar Chart Initialization with Horizontal Labels and Scroll
                const subtopicBarLabels = reportData.chart_data.subtopic_bar_chart.labels;
                setChartWrapperWidth('subtopicBarChartWrapper', subtopicBarLabels);
                const subtopicBarCtx = document.getElementById('subtopicBarChart').getContext('2d');
                new Chart(subtopicBarCtx, {
                    type: 'bar',
                    data: {
                        labels: subtopicBarLabels,
                        datasets: [{
                            label: 'Average performance in each topic (%)',
                            data: reportData.chart_data.subtopic_bar_chart.data,
                            backgroundColor: 'rgba(0, 123, 255, 0.8)',
                            borderColor: 'rgba(0, 123, 255, 1)',
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                display: false
                            },
                            title: {
                                display: true,
                                text: 'Average performance in each topic',
                                font: {
                                    family: "'Inter', sans-serif",
                                    size: 16,
                                    weight: 'bold'
                                },
                                padding: {
                                    top: 10,
                                    bottom: 30
                                }
                            }
                        },
                        scales: {
                            x: {
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
                                title: {
                                    display: true,
                                    text: 'Topics',
                                    font: {
                                        family: "'Inter', sans-serif",
                                        size: 14,
                                        weight: '600',
                                        lineHeight: 1.2
                                    },
                                    color: '#334155',
                                    padding: {
                                        top: 10,
                                        bottom: 10
                                    }
                                }
                            },
                            y: {
                                beginAtZero: true,
                                max: 100
                            }
                        },
                        animation: {
                            duration: 2000
                        }
                    }
                });

            } else {
                // If no quiz attempts, show a message for the chart
                const chartContainer = document.querySelector('.chart-container');
                if (chartContainer) {
                    chartContainer.innerHTML = '<p style="color: #00000; font-size: 16px; font-style: italic; text-align: center; padding: 20px; border: 1px solid #ddd; border-radius: 8px;">No quiz attempts found for any student. No chart available.</p>';
                }
            }

        });
    </script>
<?php
    return ob_get_clean();
}



/**
 * Get complete course structure with sections hierarchy and activities
 * 
 * @param int $courseid Course ID
 * @return array Structured course data
 */
function get_course_structure($courseid)
{
    global $DB, $CFG;

    require_once($CFG->dirroot . '/course/lib.php');
    require_once($CFG->dirroot . '/course/format/lib.php');

    // Get course and validate
    $course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
    $modinfo = get_fast_modinfo($course);

    // Get all course sections
    $allsections = $modinfo->get_section_info_all();

    // Organize sections with hierarchy data
    $structured = [];
    foreach ($allsections as $section) {
        if ($section->section == 0) continue; // Skip general section

        $structured[$section->id] = [
            'id' => $section->id,
            'section' => $section->section,
            'name' => $section->name ?: get_string('topic', 'course') . ' ' . $section->section,
            'parent' => $section->parent ?? null,
            'activities' => [],
            'children' => []
        ];
    }

    // Add activities to each section
    foreach ($modinfo->get_cms() as $cm) {
        if (isset($structured[$cm->section])) {
            $structured[$cm->section]['activities'][] = [
                'id' => $cm->id,
                'name' => $cm->name,
                'modname' => $cm->modname,
                'instance' => $cm->instance,
                'visible' => $cm->visible
            ];
        }
    }

    // Build nested hierarchy
    $nested = [];
    foreach ($structured as $section) {
        if (empty($section['parent'])) {
            $nested[] = build_nested_children($section, $structured);
        }
    }

    return [
        'course' => [
            'id' => $course->id,
            'fullname' => $course->fullname,
            'shortname' => $course->shortname
        ],
        'sections' => $nested
    ];
}

/**
 * Recursively build nested section structure (standalone helper)
 */
function build_nested_children($parentsection, $allsections)
{
    $children = [];

    foreach ($allsections as $section) {
        if ($section['parent'] == $parentsection['section']) {
            $child = $section;
            $child['children'] = build_nested_children($child, $allsections);
            $children[] = $child;
        }
    }

    $parentsection['children'] = $children;
    return $parentsection;
}
function filter_course_structure($course_structure, $allowed_section_ids, $allowed_quiz_cmids)
{
    // Recursive helper function. Note: $processed_sections is passed by reference.
    function filter_section($section, $allowed_section_ids, $allowed_quiz_cmids, &$processed_sections)
    {
        // Skip section if it's not allowed.
        if (!in_array($section['id'], $allowed_section_ids)) {
            return null;
        }

        // Skip if this section has already been processed.
        if (in_array($section['id'], $processed_sections)) {
            return null;
        }

        // Mark this section as processed.
        $processed_sections[] = $section['id'];

        // Build the filtered section array.
        $filtered_section = [
            'id'       => $section['id'],
            'section'  => $section['section'],
            'name'     => $section['name'],
            'parent'   => $section['parent'],
            // New key for enriched quiz data
            'quizzes'  => [],
            'children' => []
        ];

        // Process activities, specifically for quiz activities.
        if (isset($section['activities']) && is_array($section['activities'])) {
            foreach ($section['activities'] as $activity) {
                if (
                    $activity['modname'] === 'quiz' &&
                    in_array($activity['id'], $allowed_quiz_cmids)
                ) {
                    // Access the global DB.
                    global $DB;
                    // Fetch the quiz record based on the course module's instance.
                    $quiz = $DB->get_record('quiz', ['id' => $activity['instance']]);
                    if ($quiz) {
                        $filtered_quiz = [
                            'cmid'          => $activity['id'],
                            'quiz_id'       => $quiz->id,
                            'name'          => format_string($quiz->name),
                            'total_marks'   => (float)$quiz->sumgrades,
                            'attempt_count' => 0,
                            'average_score' => 0,
                            'grades'        => []
                        ];
                        // Add the enriched quiz info.
                        $filtered_section['quizzes'][] = $filtered_quiz;
                    }
                }
            }
        }

        // Process children sections.
        if (isset($section['children']) && !empty($section['children'])) {
            $children = $section['children'];
            // Normalize to an array if a single child is provided.
            if (isset($children['id'])) {
                $children = [$children];
            }

            foreach ($children as $child) {
                // If a child has the same id as the parent, process its children instead.
                if ($child['id'] === $section['id']) {
                    if (isset($child['children']) && !empty($child['children'])) {
                        $grandchildren = $child['children'];
                        if (isset($grandchildren['id'])) {
                            $grandchildren = [$grandchildren];
                        }
                        foreach ($grandchildren as $grandchild) {
                            $filtered_child = filter_section($grandchild, $allowed_section_ids, $allowed_quiz_cmids, $processed_sections);
                            if ($filtered_child !== null) {
                                $filtered_section['children'][] = $filtered_child;
                            }
                        }
                    }
                    continue;
                }

                $filtered_child = filter_section($child, $allowed_section_ids, $allowed_quiz_cmids, $processed_sections);
                if ($filtered_child !== null) {
                    $filtered_section['children'][] = $filtered_child;
                }
            }
        }

        return $filtered_section;
    }

    // Build the overall filtered course structure.
    $filtered_structure = [
        'course'   => $course_structure['course'],
        'sections' => []
    ];

    $processed_sections = [];
    foreach ($course_structure['sections'] as $section) {
        $filtered_section = filter_section($section, $allowed_section_ids, $allowed_quiz_cmids, $processed_sections);
        if ($filtered_section !== null) {
            $filtered_structure['sections'][] = $filtered_section;
        }
    }

    return $filtered_structure;
}

/**
 * Transforms raw quiz results data into an organized analysis structure.
 * 
 * This function takes an array of quiz question results and transforms them into
 * a structured analysis containing:
 * - Quiz-level analysis (performance per quiz)
 * - Section-level analysis (performance per course section) 
 * - Overall performance metrics
 * - Pie chart data for correct vs incorrect answers
 *
 * The transformed data includes deduplication of repeated questions within the same
 * quiz attempt to avoid double-counting.
 *
 * @param array $results Array of quiz question results, each containing:
 *                      - student_id: The student's ID
 *                      - course_name: Name of the course
 *                      - quiz_id: ID of the quiz
 *                      - quiz_name: Name of the quiz
 *                      - section_name: Name of the course section
 *                      - question_id: ID of the question
 *                      - question_state: State/status of the answer
 *                      - attempt_number: Quiz attempt number
 * @return array Transformed data structure with quiz, section and overall analysis
 * @throws InvalidArgumentException If input is invalid or missing required fields
 */
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
        $sectionId = $question["section_id"];
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
                "id" => $quizId,
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
                "id" => $sectionId,
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

function calculateStudentRanks($allStudentQuizData)
{
    $studentPerformances = [];

    // Calculate average performance for each student across all quizzes
    foreach ($allStudentQuizData as $studentId => $quizData) {
        $resultJson = transformQuizResults($quizData);
        $result = json_decode($resultJson, true);

        if (isset($result['quiz_analysis']) && is_array($result['quiz_analysis'])) {
            $totalPerformance = 0;
            $quizCount = 0;

            foreach ($result['quiz_analysis'] as $quiz) {
                if (isset($quiz['performance_percentage'])) {
                    $totalPerformance += floatval($quiz['performance_percentage']);
                    $quizCount++;
                }
            }

            // Calculate average performance for this student
            $avgPerformance = $quizCount > 0 ? $totalPerformance / $quizCount : 0;

            $studentPerformances[$studentId] = [
                'student_id' => $studentId,
                'average_performance' => round($avgPerformance, 2),
                'quizzes_attempted' => $quizCount
            ];
        }
    }

    // Sort students by performance (descending)
    uasort($studentPerformances, function ($a, $b) {
        return $b['average_performance'] <=> $a['average_performance'];
    });

    // Assign ranks (handling ties)
    $rank = 1;
    $prevScore = null;
    $sameRankCount = 0;

    foreach ($studentPerformances as &$student) {
        if ($prevScore !== null && $student['average_performance'] < $prevScore) {
            $rank += $sameRankCount;
            $sameRankCount = 1;
        } else if ($prevScore !== null && $student['average_performance'] === $prevScore) {
            $sameRankCount++;
        } else {
            $sameRankCount = 1;
        }

        $student['rank'] = $rank;
        $prevScore = $student['average_performance'];
    }

    return $studentPerformances;
}
/**
 * Computes the median of an array.
 */
function computeMedian(array $values)
{
    if (empty($values)) {
        return 0;
    }
    sort($values);
    $count = count($values);
    $middle = floor($count / 2);
    if ($count % 2) {
        return $values[$middle];
    } else {
        return ($values[$middle - 1] + $values[$middle]) / 2;
    }
}

/**
 * Returns a color string based on performance percentage.
 */
function getColorForPerformance($performance)
{
    if ($performance < 40) {
        return 'red';
    } elseif ($performance < 80) {
        return 'yellow';
    } else {
        return 'green1';
    }
}

/**
 * Generates an extended aggregate report from all students' quiz data and
 * then merges the results into a hierarchical course structure.
 *
 * The final JSON report contains:
 *  - hierarchy: an array of course sections (with nested children and quizzes),
 *    where each section node includes aggregated performance stats.
 *  - student_statistics: overall counts,
 *  - chart_data: arrays for building charts (derived from the hierarchical structure).
 *  - detailed_topic_stats: detailed stats for each root topic (avg, median, % below median, % above median, colour)
 *  - detailed_subtopic_stats: detailed stats for each subtopic (level >=2)
 *  - hasQuizAttempts: a flag indicating if any student attempted any quiz.
 *
 * @param array $allStudentQuizData  Associative array keyed by student id.
 * @param array $filteredCourseStructure  The hierarchical course structure.
 * @param array $allStudentData  All student records (keyed by student id).
 * @return string  JSON encoded report.
 */
function generateDataForTeacherReport($allStudentQuizData, $filteredCourseStructure, $allStudentData)
{

    // Validate input parameters
    if (!is_array($allStudentQuizData)) {
        throw new InvalidArgumentException("Invalid input parameter type for allStudentQuizData: expected array, got " . gettype($allStudentQuizData));
    }
    if (!is_array($filteredCourseStructure)) {
        throw new InvalidArgumentException("Invalid input parameter type for filteredCourseStructure: expected array, got " . gettype($filteredCourseStructure));
    }
    if (!is_array($allStudentData)) {
        throw new InvalidArgumentException("Invalid input parameter type for allStudentData: expected array, got " . gettype($allStudentData));
    }

    if (!isset($filteredCourseStructure['sections']) || !is_array($filteredCourseStructure['sections'])) {
        throw new InvalidArgumentException("Invalid course structure format");
    }
    error_log("Starting generateDataForTeacherReport");

    // ============================================================
    // Helper function for statistics.
    // ============================================================
    $computeStats = function (array $values) {
        if (count($values) === 0) {
            return [
                'average' => 0,
                'median' => 0,
                'percent_below_median' => 0,
                'percent_above_median' => 0,
            ];
        }

        // Sanitize input values
        $values = array_map(function ($v) {
            return is_numeric($v) ? max(0, min(100, floatval($v))) : 0;
        }, $values);

        $avg = array_sum($values) / count($values);
        $median = computeMedian($values);

        // Handle case where all values are equal
        if (count(array_unique($values)) === 1) {
            return [
                'average' => round($avg, 2),
                'median' => round($median, 2),
                'percent_below_median' => 0,
                'percent_above_median' => 0,
            ];
        }

        $below = $above = $equal = 0;
        foreach ($values as $v) {
            if ($v < $median) {
                $below++;
            } elseif ($v > $median) {
                $above++;
            } else {
                $equal++;
            }
        }

        $total = count($values);
        // Distribute equal values proportionally
        $below += $equal / 2;
        $above += $equal / 2;

        return [
            'average' => round($avg, 2),
            'median' => round($median, 2),
            'percent_below_median' => round(($below / $total) * 100, 2),
            'percent_above_median' => round(($above / $total) * 100, 2),
        ];
    };

    // ============================================================
    // STEP 1: Process student quiz data to build aggregated data.
    // ============================================================
    $studentSectionPerformances = [];
    $quizAggregates = [];
    $quizAttemptStudents = [];
    $sectionQuizCounts = [];
    $quizToSection = [];
    $buildSectionQuizzesMap = function ($sections, &$sectionQuizzes) use (&$buildSectionQuizzesMap) {
        foreach ($sections as $section) {
            $secId = $section['id'];
            $sectionQuizzes[$secId] = [];

            // Add direct quizzes
            if (isset($section['quizzes'])) {
                foreach ($section['quizzes'] as $quiz) {
                    $sectionQuizzes[$secId][] = $quiz['quiz_id'];
                }
            }

            // Add child quizzes
            if (isset($section['children'])) {
                foreach ($section['children'] as $child) {
                    $buildSectionQuizzesMap([$child], $sectionQuizzes);
                    $sectionQuizzes[$secId] = array_merge(
                        $sectionQuizzes[$secId],
                        $sectionQuizzes[$child['id']]
                    );
                }
            }
            $sectionQuizzes[$secId] = array_unique($sectionQuizzes[$secId]);
        }
    };


    $sectionQuizzes = [];
    $buildSectionQuizzesMap($filteredCourseStructure['sections'], $sectionQuizzes);
    function buildMappings(array $sections, &$sectionQuizCounts, &$quizToSection, $depth = 0)
    {
        if ($depth >= 10) {
            throw new RuntimeException("Maximum section nesting depth exceeded");
        }

        foreach ($sections as $section) {
            if (!isset($section['id'])) {
                throw new InvalidArgumentException("Section missing required 'id' field");
            }

            $secId = $section['id'];
            $sectionQuizCounts[$secId] = isset($section['quizzes']) && is_array($section['quizzes'])
                ? count($section['quizzes'])
                : 0;

            if (isset($section['quizzes']) && is_array($section['quizzes'])) {
                foreach ($section['quizzes'] as $quiz) {
                    if (!isset($quiz['quiz_id'])) {
                        throw new InvalidArgumentException("Quiz missing required 'quiz_id' field");
                    }
                    $quizToSection[$quiz['quiz_id']] = $secId;
                }
            }

            if (isset($section['children']) && is_array($section['children'])) {
                buildMappings($section['children'], $sectionQuizCounts, $quizToSection, $depth + 1);
            }
        }
    }

    buildMappings($filteredCourseStructure['sections'], $sectionQuizCounts, $quizToSection);

    // Process each student's quiz data
    foreach ($allStudentQuizData as $studentId => $quizData) {
        $resultJson = transformQuizResults($quizData);
        $result = json_decode($resultJson, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException("Failed to decode quiz results for student $studentId");
        }

        // Process quiz-level analysis
        if (isset($result['quiz_analysis']) && is_array($result['quiz_analysis'])) {
            foreach ($result['quiz_analysis'] as $quiz) {
                if (!isset($quiz['id'])) {
                    continue; // Skip invalid quiz entries
                }
                $quizId = $quiz['id'];
                if (!isset($quizAggregates[$quizId])) {
                    $quizAggregates[$quizId] = [
                        'id' => $quizId,
                        'quiz_name' => $quiz['quiz_name'] ?? "Quiz $quizId",
                        'total_questions' => 0,
                        'correct_answers' => 0,
                        'incorrect_answers' => 0,
                        'performance_percentages' => [],
                        'student_performances' => [] // NEW: to track per-student performance
                    ];
                }
                // Validate and sanitize quiz data
                $totalQuestions = max(0, intval($quiz['total_questions'] ?? 0));
                $correctAnswers = max(0, min($totalQuestions, intval($quiz['correct_answers'] ?? 0)));
                $incorrectAnswers = max(0, min($totalQuestions - $correctAnswers, intval($quiz['incorrect_answers'] ?? 0)));

                $quizAggregates[$quizId]['total_questions'] += $totalQuestions;
                $quizAggregates[$quizId]['correct_answers'] += $correctAnswers;
                $quizAggregates[$quizId]['incorrect_answers'] += $incorrectAnswers;

                $performance = isset($quiz['performance_percentage'])
                    ? max(0, min(100, floatval($quiz['performance_percentage'])))
                    : 0;
                $quizAggregates[$quizId]['performance_percentages'][] = $performance;
                $quizAggregates[$quizId]['student_performances'][$studentId] = $performance;

                if (!isset($quizAttemptStudents[$quizId])) {
                    $quizAttemptStudents[$quizId] = [];
                }
                if (!in_array($studentId, $quizAttemptStudents[$quizId])) {
                    $quizAttemptStudents[$quizId][] = $studentId;
                }
            }
        }

        // Process section-level analysis
        if (isset($result['section_analysis']) && is_array($result['section_analysis'])) {
            foreach ($result['section_analysis'] as $sectionAnalysis) {
                if (!isset($sectionAnalysis['id'])) {
                    continue; // Skip invalid section entries
                }
                $secId = $sectionAnalysis['id'];
                $performance = isset($sectionAnalysis['performance_percentage'])
                    ? max(0, min(100, floatval($sectionAnalysis['performance_percentage'])))
                    : 0;
                if (!isset($studentSectionPerformances[$secId])) {
                    $studentSectionPerformances[$secId] = [];
                }
                $studentSectionPerformances[$secId][] = $performance;
            }
        }
    }
    // --- NEW STEP: Adjust quiz aggregates to include missing attempts ---
    // Now that $quizAggregates is fully populated, add zeros for missing quiz attempts.
    $totalStudents = count($allStudentData);
    foreach ($quizAggregates as $quizId => &$aggQuiz) {
        $attemptedCount = isset($quizAttemptStudents[$quizId]) ? count($quizAttemptStudents[$quizId]) : 0;
        $missingCount = $totalStudents - $attemptedCount;
        for ($i = 0; $i < $missingCount; $i++) {
            $aggQuiz['performance_percentages'][] = 0;
        }
    }
    unset($aggQuiz); // Good practice after using a reference

    // ============================================================
    // STEP 2: Build a hierarchical report from the course structure.
    // ============================================================

    /**
     * Recursively gathers performance percentages for a section and its children.
     */
    $gatherPerformancesRecursive = function ($section, $studentSectionPerformances, $quizAggregates, $totalStudents) use (&$gatherPerformancesRecursive) {
        $performances = [];
        $secId = $section['id'];

        // Include existing section performances
        if (isset($studentSectionPerformances[$secId])) {
            $performances = $studentSectionPerformances[$secId];
        }

        // Include quiz performances, including zeros for non-attempted quizzes
        if (isset($section['quizzes']) && is_array($section['quizzes'])) {
            foreach ($section['quizzes'] as $quiz) {
                $quizId = $quiz['quiz_id'];
                if (isset($quizAggregates[$quizId])) {
                    // Add all performance percentages, including zeros for non-attempts
                    $performances = array_merge($performances, $quizAggregates[$quizId]['performance_percentages']);
                } else {
                    // If the quiz has no aggregates at all, add zeros for all students
                    $performances = array_merge($performances, array_fill(0, $totalStudents, 0));
                }
            }
        }

        // Recursively gather performances from child sections
        if (isset($section['children']) && is_array($section['children'])) {
            foreach ($section['children'] as $child) {
                $childPerformances = $gatherPerformancesRecursive($child, $studentSectionPerformances, $quizAggregates, $totalStudents);
                $performances = array_merge($performances, $childPerformances);
            }
        }

        return $performances;
    };

    /**
     * Recursively processes a section node.
     */
    $processSection = function ($section, $studentSectionPerformances, $quizAggregates, $quizAttemptStudents, $allStudentData, $parentId = null) use ($computeStats, $gatherPerformancesRecursive, &$processSection) {
        $node = [];
        $node['id'] = $section['id'];
        $node['section'] = isset($section['section']) ? $section['section'] : null;
        $node['name'] = $section['name'];
        $node['parent'] = $parentId;

        // Process quizzes attached to this section.
        $node['quizzes'] = [];
        if (isset($section['quizzes']) && is_array($section['quizzes'])) {
            foreach ($section['quizzes'] as $quiz) {
                $quizId = $quiz['quiz_id'];
                // Ensure a valid quiz name is set.
                // Ensure a valid quiz name is set.
                if (!isset($quiz['name'])) {
                    $quiz['name'] = isset($quiz['quiz_name']) ? $quiz['quiz_name'] : "Quiz " . $quizId;
                }
                if (isset($quizAggregates[$quizId])) {
                    $aggQuiz = $quizAggregates[$quizId];
                    $overallPerformance = $aggQuiz['total_questions'] > 0
                        ? round(($aggQuiz['correct_answers'] / $aggQuiz['total_questions']) * 100, 2)
                        : 0;
                    $stats = $computeStats($aggQuiz['performance_percentages']);
                    $quiz['total_questions'] = $aggQuiz['total_questions'];
                    $quiz['correct_answers'] = $aggQuiz['correct_answers'];
                    $quiz['incorrect_answers'] = $aggQuiz['incorrect_answers'];
                    $quiz['average_performance'] = $stats['average'];
                    $quiz['median_performance'] = $stats['median'];
                    $quiz['percent_below_median'] = $stats['percent_below_median'];
                    $quiz['percent_above_median'] = $stats['percent_above_median'];
                    $quiz['performance_percentage'] = $overallPerformance;
                    $quiz['color'] = getColorForPerformance($stats['average']);

                    // Build detailed attempted students list
                    $attemptedStudents = isset($quizAttemptStudents[$quizId]) ? $quizAttemptStudents[$quizId] : [];
                    $attemptedStudentsDetailed = [];
                    foreach ($attemptedStudents as $studId) {
                        $studentName = '';
                        foreach ($allStudentData as $stud) {
                            if ($stud['studentid'] == $studId) {
                                $studentName = $stud['name'];
                                break;
                            }
                        }
                        if ($studentName === '') {
                            $studentName = "Unknown";
                        }
                        $studentPerformance = isset($aggQuiz['student_performances'][$studId])
                            ? $aggQuiz['student_performances'][$studId]
                            : 0;
                        $attemptedStudentsDetailed[] = [
                            'studentid' => $studId,
                            'name' => $stud['firstname'] . ' ' . $stud['lastname'],
                            'performance_percentage' => $studentPerformance
                        ];
                    }

                    // Sort attempted students by performance in descending order
                    usort($attemptedStudentsDetailed, function ($a, $b) {
                        return $b['performance_percentage'] <=> $a['performance_percentage'];
                    });

                    $quiz['attempted_students'] = $attemptedStudentsDetailed;

                    // Build detailed not attempted students list
                    $attemptedStudentIds = array_map(function ($student) {
                        return $student['studentid'];
                    }, $attemptedStudentsDetailed);
                    $notAttemptedStudentsDetailed = [];
                    foreach ($allStudentData as $stud) {
                        if (!in_array($stud['studentid'], $attemptedStudentIds)) {
                            $notAttemptedStudentsDetailed[] = [
                                'studentid' => $stud['studentid'],
                                'name' => $stud['firstname'] . ' ' . $stud['lastname']
                            ];
                        }
                    }
                    $quiz['not_attempted_students'] = $notAttemptedStudentsDetailed;
                } else {
                    // Defaults if no quiz aggregate is available.
                    $quiz['average_performance'] = 0;
                    $quiz['median_performance'] = 0;
                    $quiz['percent_below_median'] = 0;
                    $quiz['percent_above_median'] = 0;
                    $quiz['performance_percentage'] = 0;
                    $quiz['color'] = getColorForPerformance(0);
                    $quiz['attempted_students'] = [];
                    $notAttemptedStudentsDetailed = [];
                    foreach ($allStudentData as $stud) {
                        $notAttemptedStudentsDetailed[] = [
                            'studentid' => $stud['studentid'],
                            'name' => $stud['firstname'] . ' ' . $stud['lastname']
                        ];
                    }
                    $quiz['not_attempted_students'] = $notAttemptedStudentsDetailed;
                }

                $node['quizzes'][] = $quiz;
            }
        }

        // Recursively process child sections.
        $node['children'] = [];
        if (isset($section['children']) && is_array($section['children'])) {
            foreach ($section['children'] as $childSection) {
                $node['children'][] = $processSection($childSection, $studentSectionPerformances, $quizAggregates, $quizAttemptStudents, $allStudentData, $section['id']);
            }
        }

        // Compute aggregated performance for this node.
        $totalStudents = count($allStudentData);
        $rawPerformances = $gatherPerformancesRecursive($section, $studentSectionPerformances, $quizAggregates, $totalStudents);
        if (!empty($rawPerformances)) {
            $stats = $computeStats($rawPerformances);
            $node['average_performance'] = $stats['average'];
            $node['median_performance'] = $stats['median'];
            $node['percent_below_median'] = $stats['percent_below_median'];
            $node['percent_above_median'] = $stats['percent_above_median'];
            $node['color'] = getColorForPerformance($stats['average']);
        } else {
            $node['average_performance'] = 0;
            $node['median_performance'] = 0;
            $node['percent_below_median'] = 0;
            $node['percent_above_median'] = 0;
            $node['color'] = getColorForPerformance(0);
        }

        return $node;
    };

    $hierarchy = [];
    if (isset($filteredCourseStructure['sections']) && is_array($filteredCourseStructure['sections'])) {
        foreach ($filteredCourseStructure['sections'] as $section) {
            $hierarchy[] = $processSection($section, $studentSectionPerformances, $quizAggregates, $quizAttemptStudents, $allStudentData);
        }
    }

    // ============================================================
    // STEP 3: Additional statistics and chart data.
    // ============================================================


    $attemptedStudentCount = count(array_keys($allStudentQuizData));
    $notAttemptedStudentCount = $totalStudents - $attemptedStudentCount;

    $studentStatistics = [
        'total_students'         => $totalStudents,
        'attempted_students'     => $attemptedStudentCount,
        'not_attempted_students' => $notAttemptedStudentCount
    ];

    // --- Existing chart data computations ---
    $flattenQuizzes = function ($nodes) use (&$flattenQuizzes) {
        $quizzes = [];
        foreach ($nodes as $node) {
            if (!empty($node['quizzes'])) {
                foreach ($node['quizzes'] as $quiz) {
                    $quizzes[] = [
                        'quiz_name'   => $quiz['name'],
                        'performance' => $quiz['average_performance']
                    ];
                }
            }
            if (!empty($node['children'])) {
                $quizzes = array_merge($quizzes, $flattenQuizzes($node['children']));
            }
        }
        return $quizzes;
    };
    $quizChart = $flattenQuizzes($hierarchy);

    // Build topic chart: root sections only
    $topicChart = array_map(function ($node) {
        return [
            'topic_name'  => $node['name'],
            'performance' => $node['average_performance']
        ];
    }, $hierarchy);

    // Build subtopic chart: all nodes with level >= 2
    function flattenSubtopicsLevel2(array $nodes, $level = 1)
    {
        $result = [];
        foreach ($nodes as $node) {
            // Only include nodes exactly at level 2
            if ($level === 2) {
                $result[] = [
                    'subtopic_name' => $node['name'],
                    'performance'   => $node['average_performance']
                ];
            }
            // Only traverse children if we haven't reached level 2 yet
            if ($level < 2 && !empty($node['children'])) {
                $result = array_merge($result, flattenSubtopicsLevel2($node['children'], $level + 1));
            }
        }
        return $result;
    }
    $subtopicChart = flattenSubtopicsLevel2($hierarchy, 1);

    // --- NEW STEP: Detailed Topic Statistics ---
    $detailedTopicStats = array_map(function ($node) {
        return [
            'topic_name'           => $node['name'],
            'average'              => $node['average_performance'],
            'median'               => $node['median_performance'],
            'percent_below_median' => $node['percent_below_median'],
            'percent_above_median' => $node['percent_above_median'],
            'color'                => $node['color']
        ];
    }, $hierarchy);

    // --- NEW STEP: Detailed Subtopic Statistics (level >= 2) ---
    function flattenSubtopicsDetailed(array $nodes, $level = 1)
    {
        $result = [];
        foreach ($nodes as $node) {
            if ($level >= 2) {
                $result[] = [
                    'subtopic_name'        => $node['name'],
                    'average'              => $node['average_performance'],
                    'median'               => $node['median_performance'],
                    'percent_below_median' => $node['percent_below_median'],
                    'percent_above_median' => $node['percent_above_median'],
                    'color'                => $node['color']
                ];
            }
            if (!empty($node['children'])) {
                $result = array_merge($result, flattenSubtopicsDetailed($node['children'], $level + 1));
            }
        }
        return $result;
    }
    $detailedSubtopicStats = flattenSubtopicsDetailed($hierarchy, 1);
    // --- NEW STEP: Prepare Bar Chart Data for Subtopics ---
    // This will be used to render a bar graph for all subtopics (level >=2)
    $subtopicBarChart = [
        'labels' => array_map(function ($stat) {
            return $stat['subtopic_name'];
        }, $subtopicChart),
        'data' => array_map(function ($stat) {
            return $stat['performance'];
        }, $subtopicChart)
    ];

    $chartData = [
        'quiz_chart'     => $quizChart,
        'topic_chart'    => $topicChart,
        'subtopic_chart' => $subtopicChart,
        'subtopic_bar_chart' => $subtopicBarChart

    ];

    // ============================================================
    // STEP 4: Build final report.
    // ============================================================
    $studentRankings = calculateEnhancedStudentRanks($allStudentQuizData, $filteredCourseStructure, $allStudentData);
    $sectionRankings = calculateSectionWiseStudentRanks($allStudentQuizData, $filteredCourseStructure, $allStudentData);

    foreach ($studentRankings as &$student) {
        $student['performance_category'] = getColorForPerformance($student['average_performance']);
    }

    // Sort by rank for final display
    uasort($studentRankings, function ($a, $b) {
        return $a['rank'] <=> $b['rank'];
    });
    $report = [
        'hierarchy'               => $hierarchy,
        'student_statistics'      => $studentStatistics,
        'chart_data'              => $chartData,
        'detailed_topic_stats'    => $detailedTopicStats,
        'detailed_subtopic_stats' => $detailedSubtopicStats,
        'hasQuizAttempts'         => ($attemptedStudentCount > 0),
        'student_rankings' => array_values($studentRankings), // Convert to indexed array
        'section_rankings'        => $sectionRankings  // Add this line to include section rankings

    ];
    return json_encode($report);
}

/**
 * Generates a formatted HTML report for an individual student based on their quiz performance data.
 * 
 * This function takes JSON content containing a student's quiz performance data and generates
 * a formatted HTML report with charts and analysis. The report includes:
 * - Overall performance metrics
 * - Quiz-by-quiz analysis
 * - Section-by-section analysis 
 * - Performance summaries
 * - Visual charts showing correct vs incorrect answers
 *
 * @param string $jsonContent JSON string containing the student's performance data with:
 *                           - student_id: The student's ID
 *                           - course_name: Name of the course
 *                           - total_attempts: Total number of quiz attempts
 *                           - quiz_analysis: Analysis of individual quiz performance
 *                           - section_analysis: Analysis by course section
 *                           - overall_performance: Overall performance metrics
 *                           - performance_summary: Summary of student's performance
 *                           - sectionsAndQuizes: Mapping of sections to quizzes
 * @return string HTML content of the formatted student report
 */
function generateStudentReport($jsonContent, $courseid, $studentid)
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
    $performanceSummary = isset($responseContent['performance_summary']) ? $responseContent['performance_summary'] : '';
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

    global $DB, $USER;

    // Validate inputs
    if (empty($courseid) || empty($studentid)) {
        throw new moodle_exception('missingparameter');
    }

    // If no student ID is provided, use current user
    if (!$studentid) {
        $studentid = $USER->id;
    }


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

    // Get cohort of the student
    $cohorts = cohort_get_user_cohorts($studentid);

    // Get the first cohort id
    $cohortid = array_key_first($cohorts);

    $course_structure =  get_course_structure($courseid);
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
    $student_ids = [intval($studentid)];

    $recordset = $DB->get_recordset_sql("
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
                                           u.id, c.id, qz.id, qa.attempt, qatt.slot;", ['student_id' => $studentid, 'course_id' => $courseid]);
    // Organize the data by student ID
    $studentQuizData = [];

    foreach ($recordset as $record) {
        $student_id = $record->student_id;

        // Initialize the array for each student if not already set
        if (!isset($studentQuizData[$student_id])) {
            $studentQuizData[$student_id] = [];
        }

        // Add the quiz data for the student
        $studentQuizData[$student_id][] = (array) $record;
    }

    // Close the recordset
    $recordset->close();

    $studentIdsArray = json_encode($student_ids);

    $params = [
        'studentid' => $studentid,
        'courseid' => $courseid
    ];
    $sqlForGroupId = "
        SELECT 
            g.id AS groupid, 
            g.name AS groupname
        FROM 
            {groups} g
        JOIN 
            {groups_members} gm ON g.id = gm.groupid
        WHERE 
            gm.userid = :studentid
            AND g.courseid = :courseid
    ";
    $groups = $DB->get_records_sql($sqlForGroupId, $params);

    foreach ($groups as $group) {
        $groupid = $group->groupid;
    }



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
        'groupid' => $groupid,
        'courseid' => $courseid
    ];

    $recordset = $DB->get_records_sql($students_sql_query, $params);
    $students = [];
    foreach ($recordset as $record) {
        $students[] = (array) $record;
    }
    $current_student_id = $studentid; // Assuming $studentid is defined in the surrounding context
    $filtered_students = array_filter($students, function ($student) use ($current_student_id) {
        return $student['studentid'] == $current_student_id;
    });
    $agg_report_data = generateDataForStudentReport($studentQuizData, $filtered_course_structure, $filtered_students);

    $allStudentsId = array_map(function ($student) {
        return (int)$student['studentid'];
    }, $students);

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
            WHERE u.id IN (" . implode(',', $allStudentsId) . ")
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

    $studentRankings = calculateEnhancedStudentRanks($allStudentQuizData, $filtered_course_structure, $students);

    ob_start();
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
        <?php if (!empty($performanceSummary)): ?>
            <div class="performance-summary card slide-in">
                <h2 class="report_h2"><i class="fas fa-chart-line"></i> Performance Summary</h2>
                <div class="summary-content">
                    <p class="text-large"><strong>Key Insights:</strong> <?php echo htmlspecialchars($performanceSummary['key_insights']); ?></p>
                    <p class="text-large"><strong>Areas of Improvement:</strong> <?php echo htmlspecialchars($performanceSummary['areas_of_improvement']); ?></p>
                </div>
            </div>
        <?php endif; ?>
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
        <?php echo renderStudentRankCard($studentRankings, $studentId); ?>

        <!-- Charts -->
        <div class="charts-container card slide-in">
            <h2 class="report_h2"><i class="fas fa-chart-pie"></i> Overall Performance Graph</h2>
            <div style="width: 400px; height: 400px; display: flex; justify-content: center; align-items: center; align-self: center;">
                <canvas id="overallPerformanceChart"></canvas>
            </div>
        </div>
        <?php echo renderHierarchyStudentReport($agg_report_data); ?>
        <!-- Section Performance Table -->
        <!-- <div class="performance-table card slide-in">
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
        </div> -->
        <!-- Section Performance Chart -->
        <!-- <div class="charts-container card slide-in">
            <h2 class="report_h2"><i class="fas fa-chart-bar"></i> Section Performance Graph</h2>
            <div class="chart-scroll-container">
                <div class="chart-wrapper" id="sectionChartWrapper">
                    <canvas id="sectionPerformanceChart"></canvas>
                </div>
            </div>
        </div> -->



        <!-- 
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
        </div> -->
        <!-- <div class="charts-container card slide-in">
            <h2 class="report_h2"><i class="fas fa-chart-bar"></i> Quiz Performance Graph</h2>
            <div class="chart-scroll-container">
                <div class="chart-wrapper" id="quizChartWrapper">
                    <canvas id="quizPerformanceChart"></canvas>
                </div>
            </div>
        </div> -->

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
            // const sectionLabels = <?php echo json_encode($sectionNames); ?>;
            // setChartWrapperWidth('sectionChartWrapper', sectionLabels);
            // const sectionCtx = document.getElementById("sectionPerformanceChart").getContext("2d");
            // new Chart(sectionCtx, {
            //     type: "bar",
            //     data: {
            //         labels: <?php echo json_encode($sectionNames); ?>,
            //         datasets: [{
            //                 label: "Correct Answers",
            //                 data: <?php echo json_encode($sectionCorrect); ?>,
            //                 backgroundColor: '#16A085',
            //                 borderWidth: 1
            //             },
            //             {
            //                 label: "Incorrect Answers",
            //                 data: <?php echo json_encode($sectionIncorrect); ?>,
            //                 backgroundColor: '#FFFFD1',
            //                 borderWidth: 1
            //             }
            //         ]
            //     },
            //     options: {
            //         maintainAspectRatio: false,
            //         responsive: true,
            //         interaction: {
            //             mode: "index",
            //             intersect: false
            //         },
            //         plugins: {
            //             legend: {
            //                 position: "top",
            //                 labels: {
            //                     font: {
            //                         size: 14,
            //                         weight: "bold"
            //                     }
            //                 }
            //             },
            //             title: {
            //                 display: true,
            //                 text: "Section Performance",
            //                 font: {
            //                     size: 18,
            //                     weight: "bold"
            //                 }
            //             }
            //         },
            //         scales: {
            //             x: {
            //                 title: {
            //                     display: true,
            //                     text: "Sections",
            //                     font: {
            //                         weight: "bold"
            //                     }
            //                 },
            //                 ticks: {
            //                     maxRotation: 0,
            //                     minRotation: 0,
            //                     autoSkip: false,
            //                     callback: function(value) {
            //                         const label = this.getLabelForValue(value);
            //                         const words = label.split(' ');
            //                         const lines = [];
            //                         let currentLine = '';

            //                         words.forEach(word => {
            //                             if (currentLine.length + word.length > 15) {
            //                                 lines.push(currentLine);
            //                                 currentLine = word;
            //                             } else {
            //                                 currentLine += (currentLine === '' ? '' : ' ') + word;
            //                             }
            //                         });
            //                         if (currentLine) lines.push(currentLine);

            //                         return lines;
            //                     },
            //                     font: {
            //                         size: 12 // Adjust font size if needed
            //                     }
            //                 },
            //                 barPercentage: 0.8, // Increase bar width
            //                 categoryPercentage: 0.8 // Increase bar width
            //             },
            //             y: {
            //                 title: {
            //                     display: true,
            //                     text: "Answers",
            //                     font: {
            //                         weight: "bold"
            //                     }
            //                 }
            //             }
            //         }
            //     }
            // });

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
            // renderFirstSection();
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
            max-width: 1200px;
            /* margin: 0 auto; */
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
            width: 100% !important;
            height: 100% !important;

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



function getCourseData($courseid, $studentid)
{
    global $DB, $USER;

    // Validate inputs
    if (empty($courseid) || empty($studentid)) {
        throw new moodle_exception('missingparameter');
    }

    // If no student ID is provided, use current user
    if (!$studentid) {
        $studentid = $USER->id;
    }

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

    // Get cohort of the student
    $cohorts = cohort_get_user_cohorts($studentid);

    // Get the first cohort id
    $cohortid = array_key_first($cohorts);

    $course_structure =  get_course_structure($courseid);
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
    $student_ids = [intval($studentid)];

    $recordset = $DB->get_recordset_sql("
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
                                           u.id, c.id, qz.id, qa.attempt, qatt.slot;", ['student_id' => $studentid, 'course_id' => $courseid]);
    // Organize the data by student ID
    $studentQuizData = [];

    foreach ($recordset as $record) {
        $student_id = $record->student_id;

        // Initialize the array for each student if not already set
        if (!isset($studentQuizData[$student_id])) {
            $studentQuizData[$student_id] = [];
        }

        // Add the quiz data for the student
        $studentQuizData[$student_id][] = (array) $record;
    }

    // Close the recordset
    $recordset->close();

    $studentIdsArray = json_encode($student_ids);

    $params = [
        'studentid' => $studentid,
        'courseid' => $courseid
    ];
    $sqlForGroupId = "
        SELECT 
            g.id AS groupid, 
            g.name AS groupname
        FROM 
            {groups} g
        JOIN 
            {groups_members} gm ON g.id = gm.groupid
        WHERE 
            gm.userid = :studentid
            AND g.courseid = :courseid
    ";
    $groups = $DB->get_records_sql($sqlForGroupId, $params);

    foreach ($groups as $group) {
        $groupid = $group->groupid;
    }



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
        'groupid' => $groupid,
        'courseid' => $courseid
    ];

    $recordset = $DB->get_records_sql($students_sql_query, $params);
    $students = [];
    foreach ($recordset as $record) {
        $students[] = (array) $record;
    }
    $current_student_id = $studentid; // Assuming $studentid is defined in the surrounding context
    $filtered_students = array_filter($students, function ($student) use ($current_student_id) {
        return $student['studentid'] == $current_student_id;
    });
    $agg_report_data = generateDataForStudentReport($studentQuizData, $filtered_course_structure, $filtered_students);

    echo renderHierarchyStudentReport($agg_report_data);
    $allStudentsId = array_map(function ($student) {
        return (int)$student['studentid'];
    }, $students);

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
            WHERE u.id IN (" . implode(',', $allStudentsId) . ")
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

    $studentRankings = calculateEnhancedStudentRanks($allStudentQuizData, $filtered_course_structure, $students);
    echo renderStudentRankCard($studentRankings, $studentid);
}



/**
 * Generates an extended aggregate report from all students' quiz data and
 * then merges the results into a hierarchical course structure.
 *
 * The final JSON report contains:
 *  - hierarchy: an array of course sections (with nested children and quizzes),
 *    where each section node includes aggregated performance stats.
 *  - student_statistics: overall counts,
 *  - chart_data: arrays for building charts (derived from the hierarchical structure).
 *  - detailed_topic_stats: detailed stats for each root topic (avg, median, % below median, % above median, colour)
 *  - detailed_subtopic_stats: detailed stats for each subtopic (level >=2)
 *  - hasQuizAttempts: a flag indicating if any student attempted any quiz.
 *
 * @param array $allStudentQuizData  Associative array keyed by student id.
 * @param array $filteredCourseStructure  The hierarchical course structure.
 * @param array $allStudentData  All student records (keyed by student id).
 * @return string  JSON encoded report.
 */
function generateDataForStudentReport($allStudentQuizData, $filteredCourseStructure, $allStudentData)
{

    // Validate input parameters
    if (!is_array($allStudentQuizData)) {
        throw new InvalidArgumentException("Invalid input parameter type for allStudentQuizData: expected array, got " . gettype($allStudentQuizData));
    }
    if (!is_array($filteredCourseStructure)) {
        throw new InvalidArgumentException("Invalid input parameter type for filteredCourseStructure: expected array, got " . gettype($filteredCourseStructure));
    }
    if (!is_array($allStudentData)) {
        throw new InvalidArgumentException("Invalid input parameter type for allStudentData: expected array, got " . gettype($allStudentData));
    }

    if (!isset($filteredCourseStructure['sections']) || !is_array($filteredCourseStructure['sections'])) {
        throw new InvalidArgumentException("Invalid course structure format");
    }
    error_log("Starting generateDataForTeacherReport");

    // ============================================================
    // Helper function for statistics.
    // ============================================================
    $computeStats = function (array $values) {
        if (count($values) === 0) {
            return [
                'average' => 0,
                'median' => 0,
                'percent_below_median' => 0,
                'percent_above_median' => 0,
            ];
        }

        // Sanitize input values
        $values = array_map(function ($v) {
            return is_numeric($v) ? max(0, min(100, floatval($v))) : 0;
        }, $values);

        $avg = array_sum($values) / count($values);
        $median = computeMedian($values);

        // Handle case where all values are equal
        if (count(array_unique($values)) === 1) {
            return [
                'average' => round($avg, 2),
                'median' => round($median, 2),
                'percent_below_median' => 0,
                'percent_above_median' => 0,
            ];
        }

        $below = $above = $equal = 0;
        foreach ($values as $v) {
            if ($v < $median) {
                $below++;
            } elseif ($v > $median) {
                $above++;
            } else {
                $equal++;
            }
        }

        $total = count($values);
        // Distribute equal values proportionally
        $below += $equal / 2;
        $above += $equal / 2;

        return [
            'average' => round($avg, 2),
            'median' => round($median, 2),
            'percent_below_median' => round(($below / $total) * 100, 2),
            'percent_above_median' => round(($above / $total) * 100, 2),
        ];
    };

    // ============================================================
    // STEP 1: Process student quiz data to build aggregated data.
    // ============================================================
    $studentSectionPerformances = [];
    $quizAggregates = [];
    $quizAttemptStudents = [];
    $sectionQuizCounts = [];
    $quizToSection = [];
    $buildSectionQuizzesMap = function ($sections, &$sectionQuizzes) use (&$buildSectionQuizzesMap) {
        foreach ($sections as $section) {
            $secId = $section['id'];
            $sectionQuizzes[$secId] = [];

            // Add direct quizzes
            if (isset($section['quizzes'])) {
                foreach ($section['quizzes'] as $quiz) {
                    $sectionQuizzes[$secId][] = $quiz['quiz_id'];
                }
            }

            // Add child quizzes
            if (isset($section['children'])) {
                foreach ($section['children'] as $child) {
                    $buildSectionQuizzesMap([$child], $sectionQuizzes);
                    $sectionQuizzes[$secId] = array_merge(
                        $sectionQuizzes[$secId],
                        $sectionQuizzes[$child['id']]
                    );
                }
            }
            $sectionQuizzes[$secId] = array_unique($sectionQuizzes[$secId]);
        }
    };


    $sectionQuizzes = [];
    $buildSectionQuizzesMap($filteredCourseStructure['sections'], $sectionQuizzes);
    function buildMappings(array $sections, &$sectionQuizCounts, &$quizToSection, $depth = 0)
    {
        if ($depth >= 10) {
            throw new RuntimeException("Maximum section nesting depth exceeded");
        }

        foreach ($sections as $section) {
            if (!isset($section['id'])) {
                throw new InvalidArgumentException("Section missing required 'id' field");
            }

            $secId = $section['id'];
            $sectionQuizCounts[$secId] = isset($section['quizzes']) && is_array($section['quizzes'])
                ? count($section['quizzes'])
                : 0;

            if (isset($section['quizzes']) && is_array($section['quizzes'])) {
                foreach ($section['quizzes'] as $quiz) {
                    if (!isset($quiz['quiz_id'])) {
                        throw new InvalidArgumentException("Quiz missing required 'quiz_id' field");
                    }
                    $quizToSection[$quiz['quiz_id']] = $secId;
                }
            }

            if (isset($section['children']) && is_array($section['children'])) {
                buildMappings($section['children'], $sectionQuizCounts, $quizToSection, $depth + 1);
            }
        }
    }

    buildMappings($filteredCourseStructure['sections'], $sectionQuizCounts, $quizToSection);

    // Process each student's quiz data
    foreach ($allStudentQuizData as $studentId => $quizData) {
        $resultJson = transformQuizResults($quizData);
        $result = json_decode($resultJson, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException("Failed to decode quiz results for student $studentId");
        }

        // Process quiz-level analysis
        if (isset($result['quiz_analysis']) && is_array($result['quiz_analysis'])) {
            foreach ($result['quiz_analysis'] as $quiz) {
                if (!isset($quiz['id'])) {
                    continue; // Skip invalid quiz entries
                }
                $quizId = $quiz['id'];
                if (!isset($quizAggregates[$quizId])) {
                    $quizAggregates[$quizId] = [
                        'id' => $quizId,
                        'quiz_name' => $quiz['quiz_name'] ?? "Quiz $quizId",
                        'total_questions' => 0,
                        'correct_answers' => 0,
                        'incorrect_answers' => 0,
                        'performance_percentages' => [],
                        'student_performances' => [] // NEW: to track per-student performance
                    ];
                }
                // Validate and sanitize quiz data
                $totalQuestions = max(0, intval($quiz['total_questions'] ?? 0));
                $correctAnswers = max(0, min($totalQuestions, intval($quiz['correct_answers'] ?? 0)));
                $incorrectAnswers = max(0, min($totalQuestions - $correctAnswers, intval($quiz['incorrect_answers'] ?? 0)));

                $quizAggregates[$quizId]['total_questions'] += $totalQuestions;
                $quizAggregates[$quizId]['correct_answers'] += $correctAnswers;
                $quizAggregates[$quizId]['incorrect_answers'] += $incorrectAnswers;

                $performance = isset($quiz['performance_percentage'])
                    ? max(0, min(100, floatval($quiz['performance_percentage'])))
                    : 0;
                $quizAggregates[$quizId]['performance_percentages'][] = $performance;
                $quizAggregates[$quizId]['student_performances'][$studentId] = $performance;

                if (!isset($quizAttemptStudents[$quizId])) {
                    $quizAttemptStudents[$quizId] = [];
                }
                if (!in_array($studentId, $quizAttemptStudents[$quizId])) {
                    $quizAttemptStudents[$quizId][] = $studentId;
                }
            }
        }

        // Process section-level analysis
        if (isset($result['section_analysis']) && is_array($result['section_analysis'])) {
            foreach ($result['section_analysis'] as $sectionAnalysis) {
                if (!isset($sectionAnalysis['id'])) {
                    continue; // Skip invalid section entries
                }
                $secId = $sectionAnalysis['id'];
                $performance = isset($sectionAnalysis['performance_percentage'])
                    ? max(0, min(100, floatval($sectionAnalysis['performance_percentage'])))
                    : 0;
                if (!isset($studentSectionPerformances[$secId])) {
                    $studentSectionPerformances[$secId] = [];
                }
                $studentSectionPerformances[$secId][] = $performance;
            }
        }
    }
    // --- NEW STEP: Adjust quiz aggregates to include missing attempts ---
    // Now that $quizAggregates is fully populated, add zeros for missing quiz attempts.
    $totalStudents = count($allStudentData);
    foreach ($quizAggregates as $quizId => &$aggQuiz) {
        $attemptedCount = isset($quizAttemptStudents[$quizId]) ? count($quizAttemptStudents[$quizId]) : 0;
        $missingCount = $totalStudents - $attemptedCount;
        for ($i = 0; $i < $missingCount; $i++) {
            $aggQuiz['performance_percentages'][] = 0;
        }
    }
    unset($aggQuiz); // Good practice after using a reference

    // ============================================================
    // STEP 2: Build a hierarchical report from the course structure.
    // ============================================================

    /**
     * Recursively gathers performance percentages for a section and its children.
     */
    $gatherPerformancesRecursive = function ($section, $studentSectionPerformances, $quizAggregates, $totalStudents) use (&$gatherPerformancesRecursive) {
        $performances = [];
        $secId = $section['id'];

        // Include existing section performances
        if (isset($studentSectionPerformances[$secId])) {
            $performances = $studentSectionPerformances[$secId];
        }

        // Include quiz performances, including zeros for non-attempted quizzes
        if (isset($section['quizzes']) && is_array($section['quizzes'])) {
            foreach ($section['quizzes'] as $quiz) {
                $quizId = $quiz['quiz_id'];
                if (isset($quizAggregates[$quizId])) {
                    // Add all performance percentages, including zeros for non-attempts
                    $performances = array_merge($performances, $quizAggregates[$quizId]['performance_percentages']);
                } else {
                    // If the quiz has no aggregates at all, add zeros for all students
                    $performances = array_merge($performances, array_fill(0, $totalStudents, 0));
                }
            }
        }

        // Recursively gather performances from child sections
        if (isset($section['children']) && is_array($section['children'])) {
            foreach ($section['children'] as $child) {
                $childPerformances = $gatherPerformancesRecursive($child, $studentSectionPerformances, $quizAggregates, $totalStudents);
                $performances = array_merge($performances, $childPerformances);
            }
        }

        return $performances;
    };

    /**
     * Recursively processes a section node.
     */
    $processSection = function ($section, $studentSectionPerformances, $quizAggregates, $quizAttemptStudents, $allStudentData, $parentId = null) use ($computeStats, $gatherPerformancesRecursive, &$processSection) {
        $node = [];
        $node['id'] = $section['id'];
        $node['section'] = isset($section['section']) ? $section['section'] : null;
        $node['name'] = $section['name'];
        $node['parent'] = $parentId;

        // Process quizzes attached to this section.
        $node['quizzes'] = [];
        if (isset($section['quizzes']) && is_array($section['quizzes'])) {
            foreach ($section['quizzes'] as $quiz) {
                $quizId = $quiz['quiz_id'];
                // Ensure a valid quiz name is set.
                if (!isset($quiz['name'])) {
                    $quiz['name'] = isset($quiz['quiz_name']) ? $quiz['quiz_name'] : "Quiz " . $quizId;
                }
                if (isset($quizAggregates[$quizId])) {
                    $aggQuiz = $quizAggregates[$quizId];
                    $overallPerformance = $aggQuiz['total_questions'] > 0
                        ? round(($aggQuiz['correct_answers'] / $aggQuiz['total_questions']) * 100, 2)
                        : 0;
                    $stats = $computeStats($aggQuiz['performance_percentages']);
                    $quiz['total_questions'] = $aggQuiz['total_questions'];
                    $quiz['correct_answers'] = $aggQuiz['correct_answers'];
                    $quiz['incorrect_answers'] = $aggQuiz['incorrect_answers'];
                    $quiz['average_performance'] = $stats['average'];
                    $quiz['median_performance'] = $stats['median'];
                    $quiz['percent_below_median'] = $stats['percent_below_median'];
                    $quiz['percent_above_median'] = $stats['percent_above_median'];
                    $quiz['performance_percentage'] = $overallPerformance;
                    $quiz['color'] = getColorForPerformance($stats['average']);

                    // Build detailed attempted students list
                    $attemptedStudents = isset($quizAttemptStudents[$quizId]) ? $quizAttemptStudents[$quizId] : [];
                    $attemptedStudentsDetailed = [];
                    foreach ($attemptedStudents as $studId) {
                        $studentName = '';
                        foreach ($allStudentData as $stud) {
                            if ($stud['studentid'] == $studId) {
                                $studentName = $stud['name'];
                                break;
                            }
                        }
                        if ($studentName === '') {
                            $studentName = "Unknown";
                        }
                        $studentPerformance = isset($aggQuiz['student_performances'][$studId])
                            ? $aggQuiz['student_performances'][$studId]
                            : 0;
                        $attemptedStudentsDetailed[] = [
                            'studentid' => $studId,
                            'name' => $stud['firstname'] . ' ' . $stud['lastname'],
                            'performance_percentage' => $studentPerformance
                        ];
                    }
                    $quiz['attempted_students'] = $attemptedStudentsDetailed;

                    // Build detailed not attempted students list
                    $attemptedStudentIds = array_map(function ($student) {
                        return $student['studentid'];
                    }, $attemptedStudentsDetailed);
                    $notAttemptedStudentsDetailed = [];
                    foreach ($allStudentData as $stud) {
                        if (!in_array($stud['studentid'], $attemptedStudentIds)) {
                            $notAttemptedStudentsDetailed[] = [
                                'studentid' => $stud['studentid'],
                                'name' => $stud['firstname'] . ' ' . $stud['lastname']
                            ];
                        }
                    }
                    $quiz['not_attempted_students'] = $notAttemptedStudentsDetailed;
                } else {
                    // Defaults if no quiz aggregate is available.
                    $quiz['average_performance'] = 0;
                    $quiz['median_performance'] = 0;
                    $quiz['percent_below_median'] = 0;
                    $quiz['percent_above_median'] = 0;
                    $quiz['performance_percentage'] = 0;
                    $quiz['color'] = getColorForPerformance(0);
                    $quiz['attempted_students'] = [];
                    $notAttemptedStudentsDetailed = [];
                    foreach ($allStudentData as $stud) {
                        $notAttemptedStudentsDetailed[] = [
                            'studentid' => $stud['studentid'],
                            'name' => $stud['firstname'] . ' ' . $stud['lastname']
                        ];
                    }
                    $quiz['not_attempted_students'] = $notAttemptedStudentsDetailed;
                }

                $node['quizzes'][] = $quiz;
            }
        }

        // Recursively process child sections.
        $node['children'] = [];
        if (isset($section['children']) && is_array($section['children'])) {
            foreach ($section['children'] as $childSection) {
                $node['children'][] = $processSection($childSection, $studentSectionPerformances, $quizAggregates, $quizAttemptStudents, $allStudentData, $section['id']);
            }
        }

        // Compute aggregated performance for this node.
        $totalStudents = count($allStudentData);
        $rawPerformances = $gatherPerformancesRecursive($section, $studentSectionPerformances, $quizAggregates, $totalStudents);
        if (!empty($rawPerformances)) {
            $stats = $computeStats($rawPerformances);
            $node['average_performance'] = $stats['average'];
            $node['median_performance'] = $stats['median'];
            $node['percent_below_median'] = $stats['percent_below_median'];
            $node['percent_above_median'] = $stats['percent_above_median'];
            $node['color'] = getColorForPerformance($stats['average']);
        } else {
            $node['average_performance'] = 0;
            $node['median_performance'] = 0;
            $node['percent_below_median'] = 0;
            $node['percent_above_median'] = 0;
            $node['color'] = getColorForPerformance(0);
        }

        return $node;
    };

    $hierarchy = [];
    if (isset($filteredCourseStructure['sections']) && is_array($filteredCourseStructure['sections'])) {
        foreach ($filteredCourseStructure['sections'] as $section) {
            $hierarchy[] = $processSection($section, $studentSectionPerformances, $quizAggregates, $quizAttemptStudents, $allStudentData);
        }
    }

    // ============================================================
    // STEP 3: Additional statistics and chart data.
    // ============================================================


    $attemptedStudentCount = count(array_keys($allStudentQuizData));
    $notAttemptedStudentCount = $totalStudents - $attemptedStudentCount;

    $studentStatistics = [
        'total_students'         => $totalStudents,
        'attempted_students'     => $attemptedStudentCount,
        'not_attempted_students' => $notAttemptedStudentCount
    ];

    // --- Existing chart data computations ---
    $flattenQuizzes = function ($nodes) use (&$flattenQuizzes) {
        $quizzes = [];
        foreach ($nodes as $node) {
            if (!empty($node['quizzes'])) {
                foreach ($node['quizzes'] as $quiz) {
                    $quizzes[] = [
                        'quiz_name'   => $quiz['name'],
                        'performance' => $quiz['average_performance']
                    ];
                }
            }
            if (!empty($node['children'])) {
                $quizzes = array_merge($quizzes, $flattenQuizzes($node['children']));
            }
        }
        return $quizzes;
    };
    $quizChart = $flattenQuizzes($hierarchy);

    // Build topic chart: root sections only
    $topicChart = array_map(function ($node) {
        return [
            'topic_name'  => $node['name'],
            'performance' => $node['average_performance']
        ];
    }, $hierarchy);

    // Build subtopic chart: all nodes with level >= 2
    function flattenSubtopicsLevel2(array $nodes, $level = 1)
    {
        $result = [];
        foreach ($nodes as $node) {
            // Only include nodes exactly at level 2
            if ($level === 2) {
                $result[] = [
                    'subtopic_name' => $node['name'],
                    'performance'   => $node['average_performance']
                ];
            }
            // Only traverse children if we haven't reached level 2 yet
            if ($level < 2 && !empty($node['children'])) {
                $result = array_merge($result, flattenSubtopicsLevel2($node['children'], $level + 1));
            }
        }
        return $result;
    }
    $subtopicChart = flattenSubtopicsLevel2($hierarchy, 1);

    // --- NEW STEP: Detailed Topic Statistics ---
    $detailedTopicStats = array_map(function ($node) {
        return [
            'topic_name'           => $node['name'],
            'average'              => $node['average_performance'],
            'median'               => $node['median_performance'],
            'percent_below_median' => $node['percent_below_median'],
            'percent_above_median' => $node['percent_above_median'],
            'color'                => $node['color']
        ];
    }, $hierarchy);

    // --- NEW STEP: Detailed Subtopic Statistics (level >= 2) ---
    function flattenSubtopicsDetailed(array $nodes, $level = 1)
    {
        $result = [];
        foreach ($nodes as $node) {
            if ($level >= 2) {
                $result[] = [
                    'subtopic_name'        => $node['name'],
                    'average'              => $node['average_performance'],
                    'median'               => $node['median_performance'],
                    'percent_below_median' => $node['percent_below_median'],
                    'percent_above_median' => $node['percent_above_median'],
                    'color'                => $node['color']
                ];
            }
            if (!empty($node['children'])) {
                $result = array_merge($result, flattenSubtopicsDetailed($node['children'], $level + 1));
            }
        }
        return $result;
    }
    $detailedSubtopicStats = flattenSubtopicsDetailed($hierarchy, 1);
    // --- NEW STEP: Prepare Bar Chart Data for Subtopics ---
    // This will be used to render a bar graph for all subtopics (level >=2)
    $subtopicBarChart = [
        'labels' => array_map(function ($stat) {
            return $stat['subtopic_name'];
        }, $subtopicChart),
        'data' => array_map(function ($stat) {
            return $stat['performance'];
        }, $subtopicChart)
    ];

    $chartData = [
        'quiz_chart'     => $quizChart,
        'topic_chart'    => $topicChart,
        'subtopic_chart' => $subtopicChart,
        'subtopic_bar_chart' => $subtopicBarChart

    ];

    // ============================================================
    // STEP 4: Build final report.
    // ============================================================
    $report = [
        'hierarchy'               => $hierarchy,
        'student_statistics'      => $studentStatistics,
        'chart_data'              => $chartData,
        'detailed_topic_stats'    => $detailedTopicStats,
        'detailed_subtopic_stats' => $detailedSubtopicStats,
        'hasQuizAttempts'         => ($attemptedStudentCount > 0),
    ];
    return json_encode($report);
}


/**
 * Renders the teacher extended report page with an improved UI.
 * 
 * This function takes report data and generates a formatted HTML page with an enhanced UI
 * for teachers to view student performance data. The report includes:
 * - Student rankings and performance categories
 * - Topic and subtopic analysis with detailed statistics
 * - Interactive charts and graphs showing performance trends
 * - Detailed quiz attempt information
 * - Responsive design with modern styling
 *
 * @param mixed $reportData Array or JSON string containing:
 *                         - hierarchy: Nested topic/subtopic structure with performance data
 *                         - student_statistics: Overall statistics across all students
 *                         - chart_data: Data for rendering various performance charts
 *                         - detailed_topic_stats: In-depth statistics for each topic
 *                         - detailed_subtopic_stats: In-depth statistics for each subtopic
 *                         - student_rankings: Student performance rankings and categories
 *                         - hasQuizAttempts: Boolean indicating if there are quiz attempts
 * @return string HTML content of the formatted teacher report
 */
function renderHierarchyStudentReport($reportData)
{
    // If not already a JSON string, encode it.
    $reportJson = is_string($reportData) ? $reportData : json_encode($reportData);
    ob_start(); // Start output buffering
?>
    <!-- Include Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');

        :root {
            --primary-color: #0D9488;
            --secondary-color: #7b1fa2;
            --text-color: #334155;
            --text-light: #64748B;
            --background-color: #F8FAFC;
            --card-bg: #ffffff;
            --border-color: #E2E8F0;
            --shadow-color: rgba(0, 0, 0, 0.05);
            --success-bg: #DCFCE7;
            --success-text: #16A34A;
            --warning-bg: #FEF3C7;
            --warning-text: #D97706;
            --error-bg: #FEE2E2;
            --error-text: #EF4444;
            --transition-speed: 0.3s;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--background-color);
            color: var(--text-color);
            line-height: 1.5;
        }

        /* Rankings Section Styles */
        .rankings-section {
            background: var(--card-bg);
            border-radius: 16px;
            padding: 2rem;
            box-shadow: 0 4px 16px var(--shadow-color);
            border: 1px solid var(--border-color);
            transition: all var(--transition-speed) cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
            margin-bottom: 2.5rem;
        }

        .rankings-section:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 24px var(--shadow-color);
        }

        .rankings-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            opacity: 0;
            transition: opacity var(--transition-speed) ease;
        }

        .rankings-section:hover::before {
            opacity: 1;
        }

        .rankings-table-container {
            margin-top: 1.5rem;
            border-radius: 12px;
            overflow: hidden;
            border: 1px solid var(--border-color);
        }

        .rankings-table-header {
            display: grid;
            grid-template-columns: 0.5fr 1fr 1fr 1fr 1fr;
            gap: 1rem;
            padding: 1rem;
            background: linear-gradient(to right, #F0F5FB, #F8FAFD);
            font-weight: 600;
            color: var(--text-color);
            border-bottom: 1px solid var(--border-color);
        }

        .rankings-table-body {
            max-height: 500px;
            overflow-y: auto;
        }

        .ranking-row {
            display: grid;
            grid-template-columns: 0.5fr 1fr 1fr 1fr 1fr;
            gap: 1rem;
            padding: 1rem;
            border-bottom: 1px solid var(--border-color);
            transition: all var(--transition-speed) ease;
            align-items: center;
        }

        .ranking-row:hover {
            background-color: #F8FAFD;
        }

        .rank-cell {
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .rank-medal {
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            color: white;
            font-size: 0.875rem;
        }

        .rank-1 {
            background: linear-gradient(135deg, #FFD700, #FFA500);
        }

        .rank-2 {
            background: linear-gradient(135deg, #C0C0C0, #A0A0A0);
        }

        .rank-3 {
            background: linear-gradient(135deg, #CD7F32, #8B4513);
        }

        .performance-badge {
            padding: 0.375rem 0.75rem;
            border-radius: 20px;
            font-size: 0.875rem;
            font-weight: 500;
        }

        .attempts-cell {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .status-cell {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .performance-dashboard {
            max-width: 1200px;
            margin: 2rem auto;
            /* padding: 0 1rem; */
        }

        /* Header Section */
        .header {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            padding: 2rem 1rem;
            border-radius: 16px;
            margin-bottom: 2rem;
            text-align: center;
            box-shadow: 0 4px 6px var(--shadow-color);
        }

        /* Widget Cards */
        /* Widgets Grid Layout */
        .widgets-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 2rem;
            margin-bottom: 2.5rem;
            padding: 0.5rem;
        }

        /* Widget Card */
        .widget {
            background: var(--card-bg);
            border-radius: 16px;
            box-shadow: 0 4px 16px var(--shadow-color);
            padding: 1.75rem;
            cursor: default;
            transition: all var(--transition-speed) cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            border: 1px solid var(--border-color);
            overflow: hidden;
        }

        .widget::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: linear-gradient(to right, var(--primary-color), var(--secondary-color));
            opacity: 0;
            transition: opacity var(--transition-speed) ease;
        }

        .widget:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 24px var(--shadow-color);
        }

        .widget:hover::before {
            opacity: 1;
        }

        /* Widget Header */
        .widget-header {
            display: flex;
            align-items: center;
            gap: 1.25rem;
            margin-bottom: 1.25rem;
        }

        /* Widget Icon */
        .widget-icon {
            width: 56px;
            height: 56px;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5rem;
            transition: all var(--transition-speed) ease;
            position: relative;
            box-shadow: 0 4px 12px rgba(var(--primary-color-rgb), 0.2);
        }

        .widget:hover .widget-icon {
            transform: scale(1.05);
            box-shadow: 0 6px 16px rgba(var(--primary-color-rgb), 0.25);
        }

        /* Widget Content */
        .widget-content {
            flex: 1;
        }

        .widget-content h3 {
            font-size: 1rem;
            color: var(--text-light);
            margin-bottom: 0.5rem;
            font-weight: 500;
            letter-spacing: -0.01em;
        }

        .widget-content p {
            font-size: 2rem;
            font-weight: 600;
            color: var(--text-color);
            letter-spacing: -0.02em;
            line-height: 1.2;
            margin: 0;
            display: flex;
            align-items: baseline;
            gap: 0.5rem;
        }

        /* Optional: Add trend indicators */
        .widget-trend {
            font-size: 0.875rem;
            font-weight: 500;
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
        }

        .widget-trend.positive {
            background-color: var(--success-bg);
            color: var(--success-text);
        }

        .widget-trend.negative {
            background-color: var(--error-bg);
            color: var(--error-text);
        }

        /* Responsive Adjustments */
        @media (max-width: 768px) {
            .widgets-grid {
                grid-template-columns: 1fr;
                gap: 1.5rem;
                padding: 0.25rem;
            }

            .widget {
                padding: 1.5rem;
            }

            .widget-icon {
                width: 48px;
                height: 48px;
                font-size: 1.25rem;
            }

            .widget-content p {
                font-size: 1.75rem;
            }
        }

        /* Chart Container */
        .chart-container {
            background: var(--card-bg);
            border-radius: 16px;
            padding: 2rem;
            box-shadow: 0 4px 16px var(--shadow-color);
            border: 1px solid var(--border-color);
            transition: all var(--transition-speed) cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
            margin-bottom: 2.5rem;
        }

        .chart-container:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 24px var(--shadow-color);
        }

        /* Gradient bar animation */
        .chart-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            opacity: 0;
            transition: opacity var(--transition-speed) ease;
        }

        .chart-container:hover::before {
            opacity: 1;
        }

        /* Widget Header Styling */
        .widget-header {
            display: flex;
            align-items: center;

        }

        .widget-icon {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.25rem;
            box-shadow: 0 4px 12px rgba(var(--primary-color-rgb), 0.2);
            transition: all var(--transition-speed) ease;
        }

        .chart-container:hover .widget-icon {
            transform: scale(1.05);
            box-shadow: 0 6px 16px rgba(var(--primary-color-rgb), 0.25);
        }

        .widget-content h3 {
            font-size: 1rem;
            color: var(--text-light);
            margin-bottom: 0.5rem;
            font-weight: 500;
            letter-spacing: -0.01em;
        }

        .widget-content span {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--text-color);
            letter-spacing: -0.02em;
        }

        /* Scroll Container */
        .chart-scroll-container {
            width: 100%;
            overflow-x: auto;
            padding: 0.5rem 0;
            position: relative;
            background: transparent;
        }

        /* Scrollbar styling */
        .chart-scroll-container::-webkit-scrollbar {
            height: 8px;
            background: transparent;
        }

        .chart-scroll-container::-webkit-scrollbar-track {
            background: rgba(var(--primary-color-rgb), 0.1);
            border-radius: 8px;
        }

        .chart-scroll-container::-webkit-scrollbar-thumb {
            background: var(--primary-color);
            border-radius: 8px;
            border: 2px solid var(--card-bg);
        }

        .chart-scroll-container::-webkit-scrollbar-thumb:hover {
            background: var(--secondary-color);
        }

        /* Chart Wrapper */
        .chart-wrapper {
            position: relative;
            height: 420px;
            min-width: 800px;
            transform: translateZ(0);
            overflow: visible;
        }

        /* Responsive Adjustments */
        @media (max-width: 768px) {
            .chart-container {
                padding: 1.5rem;
            }

            .widget-header {
                margin-bottom: 1.5rem;
            }

            .widget-icon {
                width: 40px;
                height: 40px;
                font-size: 1rem;
            }

            .widget-content span {
                font-size: 1.125rem;
            }

            .chart-wrapper {
                height: 360px;
                min-width: 600px;
            }
        }

        /* Legend Styles */
        .color-scheme-legend {
            margin-bottom: 2.5rem;
            background: var(--card-bg);
            padding: 1.5rem;
            border-radius: 16px;
            box-shadow: 0 4px 16px var(--shadow-color);
            border: 1px solid var(--border-color);
            transition: all var(--transition-speed) ease;
        }

        .color-scheme-legend:hover {
            box-shadow: 0 6px 20px var(--shadow-color);
        }

        .color-scheme-legend h2 {
            margin-bottom: 1rem;
            font-size: 1.125rem;
            color: var(--text-color);
            font-weight: 600;
            padding-bottom: 0.75rem;
            border-bottom: 1px solid var(--border-color);
        }

        .color-scheme-legend ul {
            list-style: none;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            padding: 0.5rem;
        }

        .color-scheme-legend li {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.5rem;
            border-radius: 8px;
            transition: background-color var(--transition-speed) ease;
        }

        .color-scheme-legend li:hover {
            background-color: #F8FAFD;
        }

        .legend-swatch {
            display: inline-block;
            width: 20px;
            height: 20px;
            border-radius: 6px;
            transition: transform var(--transition-speed) ease;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .legend-swatch.success {
            background-color: var(--success-bg);
        }

        .legend-swatch.warning {
            background-color: var(--warning-bg);
        }

        .legend-swatch.error {
            background-color: var(--error-bg);
        }

        .color-scheme-legend li:hover .legend-swatch {
            transform: scale(1.1);
        }

        /* Legend Label */
        .legend-label {
            font-size: 0.95rem;
            color: var(--text-color);
            font-weight: 500;
        }

        /* Responsive Adjustments */
        @media (max-width: 768px) {
            .chart-grid {
                grid-template-columns: 1fr;
                gap: 1.5rem;
            }

            .chart-container {
                height: 380px;
                padding: 1.25rem;
            }

            .color-scheme-legend ul {
                grid-template-columns: 1fr;
            }
        }

        /* Hierarchy Section Container */
        .hierarchy-section {
            background: var(--card-bg);
            border-radius: 16px;
            padding: 1.5rem;
            margin-bottom: 2.5rem;
            box-shadow: 0 4px 16px var(--shadow-color);
            border: 1px solid var(--border-color);
            transition: all var(--transition-speed) ease;
        }

        .hierarchy-section:hover {
            box-shadow: 0 6px 24px var(--shadow-color);
        }

        .hierarchy-title {
            font-size: 1.375rem;
            font-weight: 600;
            margin-bottom: 1.5rem;
            padding-bottom: 0.75rem;
            border-bottom: 2px solid var(--border-color);
            color: var(--text-color);
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        /* Table Header and Rows */
        .hierarchy-table-header,
        .hierarchy-row {
            display: flex;
            align-items: center;
            border-radius: 8px;
            margin-bottom: 0.5rem;
            transition: all var(--transition-speed) ease;
        }

        .hierarchy-table-header {
            padding: 1rem;
            font-weight: 600;
            background: linear-gradient(to right, #F0F5FB, #F8FAFD);
            border: 1px solid var(--border-color);
            color: var(--text-color);
        }

        .hierarchy-row {
            padding: 0.875rem 1rem;
            cursor: pointer;
            border: 1px solid transparent;
            background-color: var(--card-bg);
            position: relative;
        }

        .hierarchy-row::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            width: 4px;
            height: 100%;
            background: linear-gradient(to bottom, var(--primary-color), var(--secondary-color));
            border-radius: 4px 0 0 4px;
            opacity: 0;
            transition: opacity var(--transition-speed) ease;
        }

        .hierarchy-row:hover {
            background-color: #F8FAFD;
            border-color: var(--border-color);
            transform: translateX(2px);
            box-shadow: 0 2px 8px var(--shadow-color);
        }

        .hierarchy-row:hover::before {
            opacity: 1;
        }

        .hierarchy-row>div,
        .hierarchy-table-header>div {
            padding: 0 0.75rem;
            flex: 1;
            display: flex;
            align-items: center;
        }

        /* Chip Styles */
        .chip {
            padding: 0.375rem 0.875rem;
            border-radius: 20px;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 0.375rem;
            font-size: 0.875rem;
            transition: all var(--transition-speed) ease;
        }

        /* Standardize all green indicators */
        .chip.green,
        .chip.success,
        .legend-swatch.success {
            background-color: var(--success-bg);
            color: var(--success-text);
        }

        /* Remove green1 class entirely and replace with success */
        .chip.yellow {
            background-color: var(--warning-bg);
            color: var(--warning-text);
        }

        .chip.red {
            background-color: var(--error-bg);
            color: var(--error-text);
        }

        .chip:hover {
            filter: brightness(0.95);
            transform: translateY(-1px);
        }

        /* Child Container */
        .hierarchy-child-container {
            margin: 0.75rem 0 0.75rem 1.5rem;
            display: none;
            flex-direction: column;
            gap: 0.5rem;
            animation: slideDown var(--transition-speed) ease forwards;
            padding-left: 1rem;
            border-left: 2px dashed var(--border-color);
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Expanded State */
        .hierarchy-row.expanded {
            background-color: #F8FAFD;
            border-color: var(--border-color);
            margin-bottom: 0;
        }

        .hierarchy-row.expanded::before {
            opacity: 1;
        }

        /* Responsive Adjustments */
        @media (max-width: 768px) {
            .hierarchy-section {
                padding: 1rem;
            }

            .hierarchy-table-header,
            .hierarchy-row {
                padding: 0.75rem;
            }

            .hierarchy-child-container {
                margin-left: 1rem;
            }

            .chip {
                padding: 0.25rem 0.625rem;
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

        /* Quiz Toggle Row – now clickable as a whole row */
        /* Quiz Toggle Row Styles */
        .quiz-toggle-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 1rem 1.25rem;
            margin: 0.75rem 1.5rem;
            background-color: #F8FAFD;
            border: 1px solid var(--border-color);
            border-radius: 12px;
            cursor: pointer;
            transition: all var(--transition-speed) ease;
            position: relative;
            overflow: hidden;
        }

        .quiz-toggle-row::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            width: 4px;
            height: 100%;
            background: linear-gradient(to bottom, var(--primary-color), var(--secondary-color));
            opacity: 0;
            transition: opacity var(--transition-speed) ease;
        }

        .quiz-toggle-row:hover {
            background-color: #F0F5FB;
            transform: translateX(2px);
            box-shadow: 0 2px 8px var(--shadow-color);
        }

        .quiz-toggle-row:hover::before {
            opacity: 1;
        }

        .quiz-toggle-text {
            flex: 1;
            font-size: 0.95rem;
            font-weight: 500;
            color: var(--text-color);
            margin-right: 1rem;
        }

        .quiz-toggle-icon {
            color: var(--primary-color);
            transition: transform var(--transition-speed) ease;
            width: 20px;
            height: 20px;
        }

        .quiz-toggle-row:hover .quiz-toggle-icon {
            transform: translateX(-2px);
        }

        /* Quiz Dropdown Container */
        .quiz-dropdown-container {
            margin: 0.5rem 1.5rem 1.5rem;
            display: none;
            flex-direction: column;
            animation: fadeIn var(--transition-speed) ease forwards;
            border: 1px solid var(--border-color);
            border-radius: 12px;
            overflow: hidden;
            background: var(--card-bg);
            box-shadow: 0 4px 12px var(--shadow-color);
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Quiz Row Styles */
        .quiz-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 1rem 1.25rem;
            background: var(--card-bg);
            border-bottom: 1px solid var(--border-color);
            transition: background-color var(--transition-speed) ease;
        }

        .quiz-row:hover {
            background-color: #F8FAFD;
        }

        .quiz-row:last-child {
            border-bottom: none;
        }

        .quiz-info {
            display: flex;
            align-items: center;
            gap: 1rem;
            font-size: 0.95rem;
            color: var(--text-color);
        }

        /* View Quiz Button */
        .view-quiz {
            background-color: var(--primary-color);
            color: white;
            border: none;
            padding: 0.5rem 1.125rem;
            border-radius: 20px;
            cursor: pointer;
            font-size: 0.875rem;
            font-weight: 500;
            transition: all var(--transition-speed) cubic-bezier(0.4, 0, 0.2, 1);
            display: flex;
            align-items: center;
            gap: 0.5rem;
            position: relative;
            overflow: hidden;
        }

        .view-quiz:hover {
            background-color: #8DB3D6;
            /* Slightly darker shade */
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(157, 195, 230, 0.25);
        }

        .view-quiz:active {
            transform: translateY(0);
            box-shadow: 0 2px 6px rgba(157, 195, 230, 0.15);
        }

        /* Optional: Add focus state for accessibility */
        .view-quiz:focus {
            outline: 2px solid var(--primary-color);
            outline-offset: 2px;
        }

        /* Optional: Add disabled state */
        .view-quiz:disabled {
            background-color: #CBD5E1;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        /* Quiz Status Indicators */
        .quiz-status {
            display: inline-flex;
            align-items: center;
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-size: 0.875rem;
            font-weight: 500;
        }

        .quiz-status.completed {
            background-color: var(--success-bg);
            color: var(--success-text);
        }

        .quiz-status.in-progress {
            background-color: var(--warning-bg);
            color: var(--warning-text);
        }

        .quiz-status.not-started {
            background-color: var(--error-bg);
            color: var(--error-text);
        }

        /* Modal styles */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            opacity: 0;
            transition: opacity var(--transition-speed);
        }

        .modal-content {
            background: var(--card-bg);
            width: 90%;
            max-width: 600px;
            margin: 5% auto;
            padding: 2rem;
            border-radius: 12px;
            position: relative;
            box-shadow: 0 4px 6px var(--shadow-color);
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--border-color);
        }

        .modal-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--text-color);
        }

        .close-modal {
            background: none;
            border: none;
            font-size: 1.5rem;
            color: var(--text-light);
            cursor: pointer;
            padding: 0.5rem;
        }

        .student-list {
            background: var(--background-color);
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1rem;
        }

        .student-list h4 {
            color: var(--text-color);
            margin-bottom: 0.75rem;
        }

        .student-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem;
            border-radius: 4px;
        }

        .chip {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            /* full pill shape */
            font-weight: 600;
            font-size: 0.9rem;
            /* adjust as needed */
            margin-left: 0.5rem;
            /* small spacing if needed */
        }

        /* Example color classes (already in your code, but you can refine them): */
        .chip.green,
        .chip.green1 {
            background-color: var(--success-bg);
            /* #DCFCE7 */
            color: var(--success-text);
            /* #16A34A */
        }

        .chip.yellow {
            background-color: var(--warning-bg);
            /* #FEF3C7 */
            color: var(--warning-text);
            /* #D97706 */
        }

        .chip.red {
            background-color: var(--error-bg);
            /* #FEE2E2 */
            color: var(--error-text);
            /* #EF4444 */
        }


        @media (max-width: 768px) {
            .widgets-grid {
                grid-template-columns: 1fr;
            }

            .chart-grid {
                grid-template-columns: 1fr;
            }

            .modal-content {
                width: 95%;
                margin: 10% auto;
            }
        }

        /* Add these new class selectors to match the renamed classes in your JavaScript */

        /* Update for hierarchy-quiz-row (previously quiz-row) */
        .hierarchy-quiz-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 1rem 1.25rem;
            background: var(--card-bg);
            border-bottom: 1px solid var(--border-color);
            transition: background-color var(--transition-speed) ease;
        }

        .hierarchy-quiz-row:hover {
            background-color: #F8FAFD;
        }

        .hierarchy-quiz-row:last-child {
            border-bottom: none;
        }

        /* Update for hierarchy-quiz-toggle-row (previously quiz-toggle-row) */
        .hierarchy-quiz-toggle-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 1rem 1.25rem;
            margin: 0.75rem 1.5rem;
            background-color: #F8FAFD;
            border: 1px solid var(--border-color);
            border-radius: 12px;
            cursor: pointer;
            transition: all var(--transition-speed) ease;
            position: relative;
            overflow: hidden;
        }

        .hierarchy-quiz-toggle-row::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            width: 4px;
            height: 100%;
            background: linear-gradient(to bottom, var(--primary-color), var(--secondary-color));
            opacity: 0;
            transition: opacity var(--transition-speed) ease;
        }

        .hierarchy-quiz-toggle-row:hover {
            background-color: #F0F5FB;
            transform: translateX(2px);
            box-shadow: 0 2px 8px var(--shadow-color);
        }

        .hierarchy-quiz-toggle-row:hover::before {
            opacity: 1;
        }

        /* Update for hierarchy-quiz-toggle-text (previously quiz-toggle-text) */
        .hierarchy-quiz-toggle-text {
            flex: 1;
            font-size: 0.95rem;
            font-weight: 500;
            color: var(--text-color);
            margin-right: 1rem;
        }

        /* Update for hierarchy-quiz-toggle-icon (previously quiz-toggle-icon) */
        .hierarchy-quiz-toggle-icon {
            color: var(--primary-color);
            transition: transform var(--transition-speed) ease;
            width: 20px;
            height: 20px;
        }

        .hierarchy-quiz-toggle-row:hover .hierarchy-quiz-toggle-icon {
            transform: translateX(-2px);
        }
    </style>

    <div class="performance-dashboard">
        <div class="chart-container">
            <div class="widget-header">
                <div class="widget-icon">
                    📊
                </div>
                <div class="widget-content">
                    <h3>Performance Analysis</h3>
                    <span>Topic Average Performance</span>
                </div>
            </div>

            <div class="chart-scroll-container">
                <div class="chart-wrapper" id="hierarchySubtopicBarChartWrapper">
                    <canvas id="hierarchySubtopicBarChart"></canvas>
                </div>
            </div>
        </div>


        <!-- Integrated Collapsible Course Hierarchy Table -->
        <div class="hierarchy-section">
            <h3 class="hierarchy-title">Course Hierarchy with Stats & Quizzes</h3>
            <div id="hierarchyTableContainer"></div>
        </div>


    </div>


    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script id="hierarchyViewScript">
        // Parse the injected JSON data
        const hierarchyReportData = <?php echo $reportJson; ?>;

        function hierarchyDetermineStatus(performance) {
            if (performance >= 80) {
                return {
                    label: 'Excellent',
                    color: 'success'
                };
            } else if (performance >= 40) {
                return {
                    label: 'Average',
                    color: 'yellow'
                };
            } else {
                return {
                    label: 'Needs Improvement',
                    color: 'red' // Changed from 'error' to 'red'
                };
            }
        }
        // Create quiz dropdown container for a node
        function hierarchyCreateQuizDropdown(node, level) {
            const container = document.createElement('div');
            container.className = 'quiz-dropdown-container';

            // Header for quiz table
            const headerRow = document.createElement('div');
            headerRow.className = 'hierarchy-table-header';
            headerRow.style.paddingLeft = ((level + 1) * 20) + 'px';
            const quizHeaders = ['Quiz Name', 'Percentage'];
            const quizFlexValues = [2, 1, 1, 1, 1, 1];
            quizHeaders.forEach((text, index) => {
                const cell = document.createElement('div');
                cell.textContent = text;
                cell.style.flex = quizFlexValues[index];
                headerRow.appendChild(cell);
            });
            container.appendChild(headerRow);

            // Quiz rows
            node.quizzes.forEach(quiz => {
                const quizRow = document.createElement('div');
                quizRow.className = 'hierarchy-quiz-row';
                quizRow.style.paddingLeft = ((level + 1) * 20) + 'px';

                // Quiz Name
                const nameCell = document.createElement('div');
                nameCell.textContent = quiz.name || 'Quiz';
                nameCell.style.flex = '2';
                quizRow.appendChild(nameCell);

                // Average Performance: using a colored chip
                const avgCell = document.createElement('div');
                avgCell.style.flex = '1';

                const avgChip = document.createElement('span');
                // If you used "green1" for "green", convert it here:
                // New code: determine color based solely on quiz.average_performance
                const quizStatus = hierarchyDetermineStatus(quiz.average_performance);
                const chipColorClass = quizStatus.color; // 'success', 'warning', or 'error'
                avgChip.className = 'chip ' + chipColorClass;
                avgChip.textContent = quiz.average_performance + '%';

                avgCell.appendChild(avgChip);
                quizRow.appendChild(avgCell);

                container.appendChild(quizRow);
            });

            return container;
        }
        // Add a container for the section charts below the hierarchy table
        function addSectionChartContainer() {
            const hierarchySection = document.querySelector('.hierarchy-section');
            if (hierarchySection) {
                const chartContainer = document.createElement('div');
                chartContainer.className = 'section-chart-container';
                chartContainer.innerHTML = `
            <div class="widget-header" style="padding: 10px;">
                <div class="widget-icon" style="padding-right: 10px;">📊</div>
                <div class="widget-content" style="padding-left: 10px;">
                    <h3>Section Performance</h3>
                    <span id="sectionChartTitle">Select a section to view quiz performance</span>
                </div>
            </div>
            <div class="chart-scroll-container" style="padding: 10px;">
                <div class="chart-wrapper" id="sectionQuizChartWrapper" style="padding: 10px;">
                    <canvas id="sectionQuizChart"></canvas>
                </div>
            </div>
        `;
                hierarchySection.appendChild(chartContainer);

                // Initially hide the chart container
                chartContainer.style.display = 'none';
            }
        }

        // Collect all quiz data for a section including its children
        function collectQuizDataForSection(node) {
            let quizzes = [];

            // Add quizzes from current node
            if (node.quizzes && node.quizzes.length > 0) {
                quizzes = quizzes.concat(node.quizzes);
            }

            // Recursively add quizzes from children
            if (node.children && node.children.length > 0) {
                node.children.forEach(child => {
                    quizzes = quizzes.concat(collectQuizDataForSection(child));
                });
            }

            return quizzes;
        }

        // Format quiz data for chart display
        function formatQuizDataForChart(quizzes) {
            const labels = quizzes.map(quiz => quiz.name || 'Quiz');
            const data = quizzes.map(quiz => quiz.average_performance);

            return {
                labels,
                data
            };
        }

        // Update or create section quiz chart
        let sectionQuizChart = null; // Global variable to hold chart instance

        function updateSectionQuizChart(sectionName, quizData) {
            const chartWrapper = document.getElementById('sectionQuizChartWrapper');
            const chartCanvas = document.getElementById('sectionQuizChart');
            const chartTitle = document.getElementById('sectionChartTitle');

            if (!chartCanvas) return;

            // Set chart title
            if (chartTitle) {
                chartTitle.textContent = `${sectionName} - Quiz Performance`;
            }

            // Set chart wrapper width
            if (chartWrapper) {
                const width = Math.max(800, quizData.labels.length * 150);
                chartWrapper.style.width = `${width}px`;
            }

            // Create color array based on performance
            const barColors = quizData.data.map(value => {
                if (value >= 80) return 'rgba(22, 160, 133, 0.8)'; // Green
                if (value >= 40) return 'rgba(243, 156, 18, 0.8)'; // Yellow
                return 'rgba(231, 76, 60, 0.8)'; // Red
            });

            // Create or update chart
            if (sectionQuizChart) {
                // Update existing chart
                sectionQuizChart.data.labels = quizData.labels;
                sectionQuizChart.data.datasets[0].data = quizData.data;
                sectionQuizChart.data.datasets[0].backgroundColor = barColors;
                sectionQuizChart.update();
            } else {
                // Create new chart
                sectionQuizChart = new Chart(chartCanvas.getContext('2d'), {
                    type: 'bar',
                    data: {
                        labels: quizData.labels,
                        datasets: [{
                            label: 'Average Performance (%)',
                            data: quizData.data,
                            backgroundColor: barColors,
                            borderColor: 'rgba(0, 0, 0, 0.1)',
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                display: false
                            },
                            title: {
                                display: true,
                                text: 'Quiz Performance',
                                font: {
                                    family: "'Inter', sans-serif",
                                    size: 16,
                                    weight: 'bold'
                                },
                                padding: {
                                    top: 10,
                                    bottom: 30
                                }
                            }
                        },
                        scales: {
                            x: {
                                title: {
                                    display: true,
                                    text: 'Quizzes',
                                    font: {
                                        weight: 'bold'
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
                                        size: 12
                                    }
                                },
                                barPercentage: 0.8,
                                categoryPercentage: 0.8
                            },
                            y: {
                                title: {
                                    display: true,
                                    text: 'Performance (%)',
                                    font: {
                                        weight: 'bold'
                                    }
                                },
                                beginAtZero: true,
                                max: 100
                            }
                        },
                        animation: {
                            duration: 1000
                        }
                    }
                });
            }

            // Show the chart container
            const chartContainer = document.querySelector('.section-chart-container');
            if (chartContainer) {
                chartContainer.style.display = 'block';

                // Scroll to chart with a slight delay to ensure it's visible
                setTimeout(() => {
                    chartContainer.scrollIntoView({
                        behavior: 'smooth'
                    });
                }, 300);
            }
        }

        // Modify the hierarchyCreateRow function to add "Show Graph" functionality
        function enhancedHierarchyCreateRow(node, level, flattenedHierarchy) {
            const rowWrapper = document.createElement('div');
            rowWrapper.className = 'hierarchy-row-wrapper';
            rowWrapper.style.border = '1px solid var(--border-color)';

            // Store node data for later use
            rowWrapper.dataset.nodeName = node.name;

            // Main row for the node
            const row = document.createElement('div');
            row.className = 'hierarchy-row';
            row.style.paddingLeft = (level * 20) + 'px';

            // Toggle cell for child nodes
            const toggleCell = document.createElement('div');
            toggleCell.style.flex = '0 0 20px';
            row.appendChild(toggleCell);

            // If children exist, add an icon
            let childContainer = null;
            if (node.children && node.children.length > 0) {
                const toggleIcon = document.createElement('i');
                toggleIcon.className = 'fas fa-chevron-down';
                toggleIcon.style.marginRight = '8px';
                toggleCell.appendChild(toggleIcon);

                childContainer = document.createElement('div');
                childContainer.className = 'hierarchy-child-container';

                // Clicking on the row toggles child nodes
                row.addEventListener('click', function(e) {
                    // Avoid toggling if clicking on quiz-toggle or action button
                    if (e.target.closest('.hierarchy-quiz-toggle-row') ||
                        e.target.closest('.view-quiz') ||
                        e.target.closest('.show-graph-btn')) return;

                    if (!childContainer.style.display || childContainer.style.display === 'none') {
                        childContainer.style.display = 'flex';
                        toggleIcon.classList.replace('fa-chevron-down', 'fa-chevron-up');
                    } else {
                        childContainer.style.display = 'none';
                        toggleIcon.classList.replace('fa-chevron-up', 'fa-chevron-down');
                    }
                });
            }

            // Section name cell
            const nameCell = document.createElement('div');
            nameCell.style.flex = '2';
            nameCell.style.display = 'flex';
            nameCell.style.alignItems = 'center';
            nameCell.style.gap = '10px';

            const sectionName = document.createElement('span');
            sectionName.textContent = node.name;
            sectionName.style.flex = '1';
            sectionName.style.overflow = 'hidden';
            sectionName.style.textOverflow = 'ellipsis';
            sectionName.style.whiteSpace = 'nowrap';
            nameCell.appendChild(sectionName);

            // Add "Show Graph" button if node has quizzes or children with quizzes
            const hasQuizzes = (node.quizzes && node.quizzes.length > 0) ||
                (node.children && node.children.some(child =>
                    child.quizzes && child.quizzes.length > 0));

            if (hasQuizzes) {
                const showGraphBtn = document.createElement('span');
                showGraphBtn.className = 'show-graph-btn';
                showGraphBtn.innerHTML = '<i class="fas fa-chart-bar"></i> Show Graph';
                showGraphBtn.style.fontSize = '12px';
                showGraphBtn.style.padding = '2px 6px';
                showGraphBtn.style.background = '#f0f0f0';
                showGraphBtn.style.borderRadius = '4px';
                showGraphBtn.style.cursor = 'pointer';
                showGraphBtn.style.color = '#0066cc';
                showGraphBtn.style.marginLeft = '10px';
                showGraphBtn.style.display = 'none'; // Initially hidden

                // Show Graph click event
                showGraphBtn.addEventListener('click', function(e) {
                    e.stopPropagation(); // Prevent row toggle

                    // Highlight active section
                    document.querySelectorAll('.hierarchy-row-wrapper').forEach(row => {
                        row.classList.remove('active-section');
                    });
                    rowWrapper.classList.add('active-section');

                    // Get all quizzes for this section including children
                    const allQuizzes = collectQuizDataForSection(node);

                    if (allQuizzes.length > 0) {
                        const chartData = formatQuizDataForChart(allQuizzes);
                        updateSectionQuizChart(node.name, chartData);
                    } else {
                        // Handle case with no quizzes
                        const chartContainer = document.querySelector('.section-chart-container');
                        if (chartContainer) {
                            chartContainer.style.display = 'block';
                            chartContainer.innerHTML = `
                        <div class="no-quizzes-message" style="padding: 30px; text-align: center; border: 1px dashed #ccc; border-radius: 8px; margin: 20px 0;">
                            <i class="fas fa-info-circle" style="font-size: 24px; color: #0066cc; margin-bottom: 10px;"></i>
                            <p style="margin: 0; font-size: 16px;">No quiz data available for ${node.name}</p>
                        </div>
                    `;
                        }
                    }
                });

                nameCell.appendChild(showGraphBtn);

                // Store the show graph button reference for later use
                rowWrapper.querySelector = function(selector) {
                    if (selector === '.show-graph-btn') {
                        return showGraphBtn;
                    }
                    return Element.prototype.querySelector.call(this, selector);
                };
            }

            row.appendChild(nameCell);

            // Performance percentage cell
            const averageCell = document.createElement('div');
            averageCell.style.flex = '1';

            const avgChip = document.createElement('span');
            const chipColorClass = (node.color === 'green1' || node.color === 'green') ? 'success' : node.color;
            avgChip.className = 'chip ' + chipColorClass;
            avgChip.textContent = node.average_performance + '%';

            averageCell.appendChild(avgChip);
            row.appendChild(averageCell);

            rowWrapper.appendChild(row);

            // If the node has quizzes, create quiz-toggle row
            if (node.quizzes && node.quizzes.length > 0) {
                const quizToggleRow = document.createElement('div');
                quizToggleRow.className = 'hierarchy-quiz-toggle-row';
                quizToggleRow.style.marginTop = '4px';

                // Toggle text + icon
                const toggleText = document.createElement('div');
                toggleText.className = 'hierarchy-quiz-toggle-text';
                toggleText.textContent = 'Show Quizzes';
                quizToggleRow.appendChild(toggleText);

                const toggleIcon = document.createElement('i');
                toggleIcon.className = 'fas fa-chevron-down hierarchy-quiz-toggle-icon';
                quizToggleRow.appendChild(toggleIcon);

                rowWrapper.appendChild(quizToggleRow);

                // Create quiz dropdown container
                const quizDropdown = hierarchyCreateQuizDropdown(node, level);
                rowWrapper.appendChild(quizDropdown);

                // Quiz toggle event
                quizToggleRow.addEventListener('click', () => {
                    if (!quizDropdown.style.display || quizDropdown.style.display === 'none') {
                        quizDropdown.style.setProperty('display', 'flex', 'important');
                        toggleIcon.style.transform = 'rotate(180deg)';
                        toggleText.textContent = 'Hide Quizzes';

                        // Show the graph button when quizzes are expanded
                        const showGraphBtn = rowWrapper.querySelector('.show-graph-btn');
                        if (showGraphBtn) {
                            showGraphBtn.style.display = 'inline';
                        }
                    } else {
                        quizDropdown.style.display = 'none';
                        toggleIcon.style.transform = 'rotate(0deg)';
                        toggleText.textContent = 'Show Quizzes';

                        // Hide the graph button when quizzes are collapsed
                        const showGraphBtn = rowWrapper.querySelector('.show-graph-btn');
                        if (showGraphBtn) {
                            showGraphBtn.style.display = 'none';
                        }
                    }
                });
            }

            // Append child rows
            if (childContainer) {
                node.children.forEach(child => {
                    childContainer.appendChild(enhancedHierarchyCreateRow(child, level + 1, flattenedHierarchy));
                });
                rowWrapper.appendChild(childContainer);
            }

            // Add the node to flattened hierarchy for easier data access
            if (flattenedHierarchy) {
                flattenedHierarchy[node.name] = node;
            }

            return rowWrapper;
        }

        // Enhanced render table view function
        function enhancedHierarchyRenderTableView(hierarchyData) {
            const container = document.getElementById('hierarchyTableContainer');
            container.innerHTML = '';

            // Create a flattened hierarchy for easier data access
            const flattenedHierarchy = {};

            // Global header row
            const globalHeaderRow = document.createElement('div');
            globalHeaderRow.className = 'hierarchy-table-header';

            // Toggle icon cell (empty)
            const globalToggleCell = document.createElement('div');
            globalToggleCell.style.flex = '0 0 20px';
            globalHeaderRow.appendChild(globalToggleCell);

            // Headers
            const headers = ['Section Name', 'Percentage'];
            const flexValues = [2, 1];
            headers.forEach((text, index) => {
                const cell = document.createElement('div');
                cell.textContent = text;
                cell.style.flex = flexValues[index];
                globalHeaderRow.appendChild(cell);
            });

            container.appendChild(globalHeaderRow);

            // Render each top-level node
            hierarchyData.forEach(node => {
                container.appendChild(enhancedHierarchyCreateRow(node, 0, flattenedHierarchy));
            });

            return flattenedHierarchy;
        }
        // Function to calculate chart width based on labels
        function hierarchyCalculateChartWidth(labels, minWidth = 800) {
            const minWidthPerLabel = 150; // Minimum width per label in pixels
            return Math.max(minWidth, labels.length * minWidthPerLabel);
        }

        // Function to set chart wrapper width
        function hierarchySetChartWrapperWidth(wrapperId, labels) {
            const wrapper = document.getElementById(wrapperId);
            if (wrapper) {
                const width = hierarchyCalculateChartWidth(labels);
                wrapper.style.width = `${width}px`;
            }
        }

        function enhancedInitializeHierarchyView() {
            try {
                console.log("Initializing enhanced hierarchy view...");

                // Add the section chart container
                addSectionChartContainer();

                // Render the hierarchy table view with enhanced functionality
                let flattenedHierarchy = {};
                if (hierarchyReportData && hierarchyReportData.hierarchy) {
                    flattenedHierarchy = enhancedHierarchyRenderTableView(hierarchyReportData.hierarchy);
                    console.log("Enhanced hierarchy view initialized successfully");
                } else {
                    console.error("No hierarchy data found");
                }

                // Initialize existing charts if quiz attempts exist
                if (hierarchyReportData.hasQuizAttempts) {
                    const subtopicBarCtx = document.getElementById('hierarchySubtopicBarChart');
                    if (subtopicBarCtx && hierarchyReportData.chart_data && hierarchyReportData.chart_data.subtopic_bar_chart) {
                        const subtopicBarLabels = hierarchyReportData.chart_data.subtopic_bar_chart.labels;
                        hierarchySetChartWrapperWidth('hierarchySubtopicBarChartWrapper', subtopicBarLabels);

                        new Chart(subtopicBarCtx.getContext('2d'), {
                            type: 'bar',
                            data: {
                                labels: subtopicBarLabels,
                                datasets: [{
                                    label: 'Average Performance (%)',
                                    data: hierarchyReportData.chart_data.subtopic_bar_chart.data,
                                    backgroundColor: 'rgba(0, 123, 255, 0.8)',
                                    borderColor: 'rgba(0, 123, 255, 1)',
                                    borderWidth: 1
                                }]
                            },
                            options: {
                                responsive: true,
                                maintainAspectRatio: false,
                                plugins: {
                                    legend: {
                                        display: false
                                    },
                                    title: {
                                        display: true,
                                        text: 'Topic Average Performance',
                                        font: {
                                            family: "'Inter', sans-serif",
                                            size: 16,
                                            weight: 'bold'
                                        },
                                        padding: {
                                            top: 10,
                                            bottom: 30
                                        }
                                    }
                                },
                                scales: {
                                    x: {
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
                                                size: 12
                                            }
                                        },
                                    },
                                    y: {
                                        beginAtZero: true,
                                        max: 100
                                    }
                                },
                                animation: {
                                    duration: 2000
                                }
                            }
                        });
                    }
                } else {
                    // If no quiz attempts, show a message for the chart
                    const chartContainer = document.querySelector('.chart-container');
                    if (chartContainer) {
                        chartContainer.innerHTML = '<p style="color: #000000; font-size: 16px; font-style: italic; text-align: center; padding: 20px; border: 1px solid #ddd; border-radius: 8px;">No quiz attempts found for any student. No chart available.</p>';
                    }
                }

                // Add section highlighting styles
                const style = document.createElement('style');
                style.textContent = `
            .active-section {
                background-color: rgba(13, 110, 253, 0.05);
                box-shadow: 0 0 0 1px rgba(13, 110, 253, 0.1);
            }
            
            .hierarchy-row-wrapper:hover .show-graph-btn {
                display: inline !important;
            }
            
            .section-chart-container {
                margin-top: 30px;
                border: 1px solid var(--border-color);
                border-radius: 8px;
                overflow: hidden;
                background: white;
                box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            }
            
            .chart-scroll-container {
                overflow-x: auto;
                padding: 15px;
            }
            
            .chart-wrapper {
                min-height: 350px;
                position: relative;
            }
        `;
                document.head.appendChild(style);

            } catch (error) {
                console.error("Error initializing enhanced hierarchy view:", error);
            }
        }

        // Replace the original initialization with our enhanced version
        window.addEventListener('load', enhancedInitializeHierarchyView);

        // Also initialize on DOMContentLoaded as a backup with slight delay
        document.addEventListener('DOMContentLoaded', function() {
            // Wait a short time to ensure other scripts run first
            setTimeout(enhancedInitializeHierarchyView, 100);
        });
    </script>
<?php
    return ob_get_clean();
}



/**
 * Renders the student ranking card with anonymized data for other students
 * 
 * This function displays only the top rank student and the current student.
 * All other students' rows are hidden. Original UI is maintained.
 * 
 * @param array $studentRankings The array of student rankings with performance data
 * @param int|string $studentId The ID of the student whose report is being viewed
 * @return string HTML content of the ranking card
 */
function renderStudentRankCard($studentRankings, $studentId)
{
    // Start output buffering
    ob_start();

    // Convert student ID to string for array access if it's a number
    $studentIdKey = (string)$studentId;

    // Get this student's information
    $currentStudent = $studentRankings[$studentIdKey] ?? null;

    // Exit if student not found
    if (!$currentStudent) {
        return '<div class="card"><p>No ranking data available for this student.</p></div>';
    }

    // Get total number of students
    $totalStudents = count($studentRankings);

    // Sort students by rank
    uasort($studentRankings, function ($a, $b) {
        return $a['rank'] - $b['rank'];
    });

    // Find the top-ranked student
    $topStudent = null;
    foreach ($studentRankings as $student) {
        if ($student['rank'] === 1) {
            $topStudent = $student;
            break;
        }
    }

    // Generate HTML
?>
    <div class="stud_rank_mainCard stud_rank_slideEffect stud_rank_visible">
        <h2 class="stud_rank_heading">
            <i class="fas fa-trophy"></i>
            Position of Performance
        </h2>

        <div class="stud_rank_headerSection">
            <div class="stud_rank_badgeCircle">
                <span class="stud_rank_numberDisplay"><?php echo $currentStudent['rank']; ?></span>
                <span class="stud_rank_labelText">out of <?php echo $totalStudents; ?></span>
            </div>
            <div class="stud_rank_detailsPanel">
                <div class="stud_rank_infoGrid">
                    <div class="stud_rank_infoBox">
                        <span>Your average:</span>
                        <strong><?php echo number_format($currentStudent['average_performance'], 1); ?>%</strong>
                    </div>
                    <div class="stud_rank_infoBox">
                        <span>Attempted:</span>
                        <strong><?php echo $currentStudent['attempted_quizzes']; ?>/<?php echo $currentStudent['quiz_count']; ?> quizzes</strong>
                    </div>
                </div>
            </div>
        </div>

        <div class="stud_rank_table_container">
            <table class="stud_rank_dataTable">
                <thead>
                    <tr>
                        <th>Rank</th>
                        <th>Student</th>
                        <th>Performance</th>
                        <th>Quizzes</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    foreach ($studentRankings as $id => $student):
                        // Only display top student and current student
                        $isTopStudent = ($student['rank'] === 1);
                        $isCurrentStudent = ($id === $studentIdKey);

                        if (!$isTopStudent && !$isCurrentStudent) {
                            continue; // Skip this student
                        }

                        // Determine performance class for styling
                        $performanceClass = '';
                        if (isset($student['performance_category'])) {
                            if ($student['performance_category'] == 'green') {
                                $performanceClass = 'success';
                            } elseif ($student['performance_category'] == 'yellow') {
                                $performanceClass = 'warning';
                            } elseif ($student['performance_category'] == 'red') {
                                $performanceClass = 'error';
                            }
                        }
                    ?>
                        <tr class="stud_rank_tableRow <?php echo $isCurrentStudent ? 'stud_rank_activeRow' : ''; ?>">
                            <td class="stud_rank_rankCell"><?php echo $student['rank']; ?></td>
                            <td class="stud_rank_nameCell">
                                <?php if ($isCurrentStudent): ?>
                                    <strong><?php echo htmlspecialchars($student['name']); ?></strong>
                                <?php else: ?>
                                    Top Student
                                <?php endif; ?>
                            </td>
                            <td class="stud_rank_perfCell">
                                <div class="stud_rank_perfChip" style="background-color: var(--stud-rank-<?php echo $performanceClass; ?>); color: var(--stud-rank-<?php echo $performanceClass; ?>-text);">
                                    <?php echo number_format($student['average_performance'], 1); ?>%
                                </div>
                            </td>
                            <td class="stud_rank_quizCell"><?php echo $student['attempted_quizzes']; ?>/<?php echo $student['quiz_count']; ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ($currentStudent['rank'] > 1): ?>
            <div class="stud_rank_summaryBox">
                <i class="fas fa-lightbulb"></i>
                You need to improve by <strong><?php
                                                // Find the performance of rank 1
                                                $topPerformance = $topStudent['average_performance'];
                                                $improvementNeeded = $topPerformance - $currentStudent['average_performance'];
                                                echo number_format($improvementNeeded, 1);
                                                ?>%</strong> to reach the top position.
            </div>
        <?php else: ?>
            <div class="stud_rank_summaryBox stud_rank_topAchievement">
                <i class="fas fa-crown"></i> Congratulations! You are at the top of your class.
            </div>
        <?php endif; ?>
    </div>

    <style>
        /* Modern Student Rank Card Styling - Enhanced with unique class names */
        @import url("https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap");

        :root {
            --stud-rank-primary: #0D9488;

            /* Status Colors with unique names */
            --stud-rank-error: #FEE2E2;
            --stud-rank-error-text: #EF4444;
            --stud-rank-success: #DCFCE7;
            --stud-rank-success-text: #16A34A;
            --stud-rank-warning: #FEF3C7;
            --stud-rank-warning-text: #D97706;

            /* Base Colors */
            --stud-rank-text: #334155;
            --stud-rank-text-light: #64748B;
            --stud-rank-bg: #F8FAFC;
            --stud-rank-border: #E2E8F0;

            /* Spacing */
            --stud-rank-xs: 0.25rem;
            --stud-rank-sm: 0.5rem;
            --stud-rank-md: 1rem;
            --stud-rank-lg: 1.5rem;
            --stud-rank-xl: 2rem;
        }

        /* Main card container */
        .stud_rank_mainCard {
            background: white;
            border-radius: 16px;
            box-shadow:
                0 1px 3px rgba(0, 0, 0, 0.05),
                0 4px 6px rgba(0, 0, 0, 0.02);
            padding: var(--stud-rank-xl);
            margin-bottom: var(--stud-rank-xl);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            max-width: 1200px;
            margin: 0 auto 2rem auto;
            font-family: 'Inter', system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            color: var(--stud-rank-text);
            border: 1px solid var(--stud-rank-border);
        }

        .stud_rank_mainCard:hover {
            transform: translateY(-2px);
            box-shadow:
                0 4px 6px rgba(0, 0, 0, 0.05),
                0 10px 15px rgba(0, 0, 0, 0.02);
        }

        /* Heading styles */
        .stud_rank_heading {
            display: flex;
            align-items: center;
            gap: var(--stud-rank-sm);
            color: var(--stud-rank-primary);
            border-bottom: 2px solid var(--stud-rank-border);
            padding-bottom: var(--stud-rank-md);
            margin-bottom: var(--stud-rank-lg);
            font-size: 1.5rem;
            font-weight: 600;
        }

        /* Header with badge and details */
        .stud_rank_headerSection {
            display: flex;
            align-items: center;
            margin-bottom: 28px;
            gap: 24px;
        }

        .stud_rank_badgeCircle {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-width: 90px;
            height: 90px;
            background: linear-gradient(135deg, var(--stud-rank-primary), #7b1fa2);
            border-radius: 50%;
            color: white;
            box-shadow: 0 4px 12px rgba(13, 148, 136, 0.2);
        }

        .stud_rank_numberDisplay {
            font-size: 2.5rem;
            font-weight: bold;
            line-height: 1;
        }

        .stud_rank_labelText {
            font-size: 0.8rem;
            opacity: 0.9;
            margin-top: 3px;
        }

        .stud_rank_detailsPanel {
            flex: 1;
        }

        /* Info grid and boxes */
        .stud_rank_infoGrid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }

        .stud_rank_infoBox {
            background-color: #f0f4f8;
            padding: 12px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            transition: all 0.3s ease;
        }

        .stud_rank_infoBox:hover {
            background-color: #e6eaf0;
            transform: translateX(5px);
        }

        .stud_rank_infoBox strong {
            color: var(--stud-rank-primary);
            font-weight: 600;
        }

        /* Table styles */
        .stud_rank_table_container {
            overflow-x: auto;
            margin: var(--stud-rank-lg) 0;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }

        .stud_rank_dataTable {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            overflow: hidden;
        }

        .stud_rank_dataTable th {
            background-color: var(--stud-rank-primary);
            color: white;
            padding: var(--stud-rank-md);
            text-align: left;
            font-weight: 600;
            white-space: nowrap;
        }

        .stud_rank_dataTable th:first-child {
            border-top-left-radius: 8px;
        }

        .stud_rank_dataTable th:last-child {
            border-top-right-radius: 8px;
        }

        .stud_rank_dataTable td {
            padding: var(--stud-rank-md);
            border-bottom: 1px solid var(--stud-rank-border);
        }

        .stud_rank_tableRow:last-child td {
            border-bottom: none;
        }

        /* Performance chip */
        .stud_rank_perfChip {
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

        .stud_rank_perfChip:hover {
            transform: translateY(-1px);
        }

        /* Highlight current student */
        .stud_rank_activeRow {
            background-color: #EEF2FF;
            position: relative;
        }

        .stud_rank_activeRow::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 4px;
            background-color: var(--stud-rank-primary);
            border-radius: 0 2px 2px 0;
        }

        /* Summary box */
        .stud_rank_summaryBox {
            background-color: white;
            border: 1px solid var(--stud-rank-border);
            border-left: 4px solid var(--stud-rank-primary);
            padding: var(--stud-rank-lg);
            border-radius: 8px;
            margin: var(--stud-rank-lg) 0;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .stud_rank_topAchievement {
            border-left: 4px solid var(--stud-rank-success-text);
            background-color: rgba(220, 252, 231, 0.3);
        }

        /* Animation effects */
        .stud_rank_slideEffect {
            opacity: 0;
            transform: translateY(20px);
            transition: all 0.8s ease;
        }

        .stud_rank_visible {
            opacity: 1;
            transform: translateY(0);
        }

        /* Mobile responsiveness */
        @media (max-width: 768px) {
            .stud_rank_headerSection {
                flex-direction: column;
                align-items: flex-start;
            }

            .stud_rank_badgeCircle {
                margin-bottom: 15px;
            }

            .stud_rank_dataTable th,
            .stud_rank_dataTable td {
                padding: 10px 12px;
            }

            .stud_rank_dataTable {
                font-size: 0.9rem;
            }

            .stud_rank_infoGrid {
                grid-template-columns: 1fr;
            }
        }

        /* Print Styles */
        @media print {
            .stud_rank_mainCard {
                box-shadow: none;
                border-radius: 16px;
                border: 1px solid #ddd;
            }
        }
    </style>
<?php

    // Return the buffered content
    return ob_get_clean();
}
