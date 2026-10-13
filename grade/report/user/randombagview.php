<?php
/**
 * Random Bag Test report.
 *
 * Students see the report of their own latest attempt. Teachers see the average of every
 * student in the selected class, and can open any one student's report from there.
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Course IDs where teachers get the class-average Random Bag report. Add a course ID here to enable it.
 */
const GRADEREPORT_USER_RANDOM_BAG_TEACHER_COURSES = [380];

/**
 * Whether teachers can generate the class-average Random Bag report in this course.
 *
 * @param int $courseid
 * @return bool
 */
function gradereport_user_random_bag_teacher_enabled(int $courseid): bool {
    return in_array($courseid, GRADEREPORT_USER_RANDOM_BAG_TEACHER_COURSES, true);
}

/**
 * Students of a class (group) in the course, excluding anyone who also has a non-student role.
 *
 * @param int $courseid
 * @param int $groupid
 * @return array user records keyed by user id
 */
function gradereport_user_get_class_students(int $courseid, int $groupid): array {
    global $DB;

    $sql = "SELECT DISTINCT u.id, u.firstname, u.lastname, u.email, u.username
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
               AND u.deleted = 0
               AND NOT EXISTS (
                   SELECT 1
                     FROM {role_assignments} ra2
                     JOIN {role} r2 ON r2.id = ra2.roleid
                    WHERE ra2.userid = u.id
                      AND ra2.contextid = ctx.id
                      AND r2.archetype != 'student'
               )
          ORDER BY u.firstname, u.lastname";
    return $DB->get_records_sql($sql, ['groupid' => $groupid, 'courseid1' => $courseid, 'courseid2' => $courseid]);
}

/**
 * Print the CSS shared by the student and class quiz performance reports.
 */
function gradereport_user_random_bag_styles(): void {
    ?>
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
                    }

                    .categories-table th {
                        background: var(--primary-color);
                        color: white;
                        padding: 20px;
                        text-align: left;
                        font-weight: 600;
                        font-size: 1rem;
                    }

                    .categories-table td {
                        padding: 16px 20px;
                        border-bottom: 1px solid var(--border-color);
                        vertical-align: middle;
                    }

                    .category-row {
                        cursor: pointer;
                        transition: background-color 0.2s ease;
                    }

                    .category-row:hover {
                        background-color: #f9fafb;
                    }

                    .category-name {
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

                    .category-row.expanded .expand-icon {
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
        
                    .question-type, .question-category {
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

                    /* No Data Message */
                    .no-data-message {
                        text-align: center;
                        padding: 40px;
                        color: var(--text-light);
                        font-size: 1.1rem;
                    }
        
                    /* Table Responsive Wrapper */
                    .table-responsive {
                        overflow-x: auto;
                        margin: var(--space-lg) 0;
                        border-radius: 12px;
                    }

                    /* Section Row Styles (matching Performance Dashboard) */
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
                        
                        .categories-table th,
                        .categories-table td {
                            padding: 12px 15px;
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
                    @media (max-width: 768px) {
                        .performance-highlights div[style*="grid-template-columns: 1fr 1fr"] {
                            display: grid !important;
                            grid-template-columns: 1fr !important;
                            gap: 20px !important;
                        }
                    }

                    /* Quiz name rendered as a link through to the quiz activity */
                    .quiz-name-link { color: inherit; text-decoration: underline; text-underline-offset: 2px; }
                    .quiz-name-link:hover, .quiz-name-link:focus { opacity: 0.85; }
                </style>
    <?php
}

/**
 * Best (top 3, above 0%) and worst (bottom 3, below 100%) categories of a report, without repeating a
 * category in both: a shared one is a strength above 50%, otherwise a weakness.
 *
 * @param array $categories category name => ['percentage', 'correct_answers', 'total_questions', 'wrong_answers', ...]
 * @return array [best, worst]
 */
function gradereport_user_random_bag_highlights(array $categories): array {
    $performance_highlights = [];
    foreach ($categories as $category_name => $category_data) {
        $performance_highlights[] = [
            'name' => $category_name,
            'percentage' => $category_data['percentage'],
            'correct_answers' => $category_data['correct_answers'],
            'total_questions' => $category_data['total_questions'],
            'wrong_answers' => $category_data['wrong_answers']
        ];
    }
    usort($performance_highlights, function($a, $b) {
        return $b['percentage'] <=> $a['percentage'];
    });
    $best = array_slice(array_filter($performance_highlights, function($t) {
        return $t['percentage'] > 0;
    }), 0, 3);
    $worst = array_slice(array_reverse(array_filter($performance_highlights, function($t) {
        return $t['percentage'] < 100;
    })), 0, 3);
    // A category in both lists goes under strengths above 50%, otherwise under weaknesses.
    $best_names = array_column($best, 'name');
    $worst_names = array_column($worst, 'name');
    $both = array_intersect($best_names, $worst_names);
    $best = array_values(array_filter($best, function($t) use ($both) {
        return !in_array($t['name'], $both, true) || $t['percentage'] > 50;
    }));
    $worst = array_values(array_filter($worst, function($t) use ($both) {
        return !in_array($t['name'], $both, true) || $t['percentage'] <= 50;
    }));
    return [$best, $worst];
}

/**
 * Colour for a score percentage: red below 40, orange below 80, green otherwise.
 *
 * @param float $percentage
 * @return string
 */
function gradereport_user_random_bag_score_color($percentage): string {
    if ($percentage >= 80) {
        return '#16A34A';
    }
    return $percentage >= 40 ? '#D97706' : '#EF4444';
}

/**
 * Random Bag Test report page: quiz selection, or the report for the selected quiz. Always exits.
 *
 * @param int $courseid
 * @param array $students user records (id, firstname, lastname) keyed by id
 * @param string|null $classname null for one student's report, the class name for the class average
 * @param array $urlparams extra params kept on report links (e.g. class_id)
 * @param bool $teacherview true to let the picker choose a quiz from any section, false for Random Bag Tests only
 * @param string $fetch the `fetch` value of the report: 'random', or 'cajcs' for the CAJCS Tests report
 */
function gradereport_user_random_bag_page(int $courseid, array $students, ?string $classname = null, array $urlparams = [],
        bool $teacherview = false, string $fetch = 'random'): void {
    global $CFG, $DB, $PAGE, $OUTPUT, $USER;

    // CAJCS Tests report for a student: every section, but only quizzes the student can access and only their own data.
    $studentcajcs = $fetch === 'cajcs' && !$teacherview && $classname === null;

    $course_structure = get_course_structure($courseid);
    $children = [];
    if ($teacherview || $studentcajcs) {
        // Flatten every section and subsection (in course order) so a quiz can be picked from anywhere.
        $flatten = function (array $sections, int $depth) use (&$flatten, &$children) {
            foreach ($sections as $section) {
                $section['depth'] = $depth;
                $subsections = $section['children'];
                unset($section['children']);
                $children[] = $section;
                $flatten($subsections, $depth + 1);
            }
        };
        $flatten($course_structure["sections"], 0);
        if ($studentcajcs) {
            $modinfo = get_fast_modinfo($courseid, $USER->id);
            foreach ($children as &$child) {
                $child['activities'] = array_values(array_filter($child['activities'], function($activity) use ($modinfo) {
                    return isset($modinfo->cms[$activity['id']]) && $modinfo->cms[$activity['id']]->uservisible;
                }));
            }
            unset($child);
        }
    } else {
        // Students: only the sections inside "Random Bag Tests".
        $mainSection = null;
        foreach ($course_structure["sections"] as $section) {
            if (strtolower($section["name"]) === strtolower("Random Bag Tests")) { ///change this
                $mainSection = $section;
                break;
            }
        }
        if (!$mainSection) die("Random Bag Test not found.");
        foreach ($mainSection["children"] as $child) {
            $child['depth'] = 0;
            $children[] = $child;
        }
    }

    $quiz_cmid = optional_param('quiz_id', 0, PARAM_INT);
    if ($quiz_cmid && $studentcajcs) {
        $allowed = false;
        foreach ($children as $child) {
            foreach ($child['activities'] as $activity) {
                if ($activity['modname'] === 'quiz' && (int)$activity['id'] === $quiz_cmid) {
                    $allowed = true;
                }
            }
        }
        if (!$allowed) {
            echo $OUTPUT->notification('You cannot view this quiz.', 'notifyerror');
            echo $OUTPUT->footer();
            exit;
        }
    }
    if ($quiz_cmid) {
        require_once(__DIR__ . '/integrated_quiz_renderer.php');
        require_once(__DIR__ . '/quizdataprocessor.php');
        $processor = new quiz_attempt_data_processor();
        if ($studentcajcs) {
            // Always the logged-in student, whatever was passed in.
            gradereport_user_random_bag_class_report($courseid, [$USER->id => $USER], fullname($USER), $quiz_cmid, $processor,
                [], true);
        } else if ($classname === null) {
            $renderer = new enhanced_quiz_question_renderer($PAGE, '');
            gradereport_user_random_bag_student_report($courseid, (int)reset($students)->id, $quiz_cmid, $processor, $renderer);
        } else {
            gradereport_user_random_bag_class_report($courseid, $students, $classname, $quiz_cmid, $processor, $urlparams);
        }
        exit;
    }

        if($children): ?>
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
                    <h1>Quiz Performance Report</h1>
                    <p>Select a section and quiz to view detailed performance analytics</p>
                </div>

                <div class="class-selection-container">
                    <!-- Main Form Container -->
                    <form method="get" action="" id="randomBagForm" class="class-selection-form card slide-in">
                        <input type="hidden" name="id" value="<?php echo htmlspecialchars($courseid); ?>">
                        <input type="hidden" name="fetch" value="<?= htmlspecialchars($fetch) ?>">
                        
                        <h2 class="report-h2">
                            <i class="fas fa-chart-line"></i>
                            Generate Quiz Report
                        </h2>

                        <!-- Section Selection -->
                        <div class="form-group">
                            <label for="childSection" class="form-label">Choose Section:</label>
                            <select id="childSection" class="form-select">
                                <option value="">-- Select Section --</option>
                                <?php foreach ($children as $child): ?>
                                    <option value="<?= htmlspecialchars($child['id']) ?>">
                                        <?= str_repeat('&nbsp;&nbsp;&nbsp;&nbsp;', $child['depth']) . ($child['depth'] ? '&#8627; ' : '') . htmlspecialchars($child['name']) ?>
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
                const childrenData = <?= json_encode($children) ?>;
                const childSelect = document.getElementById("childSection");
                const quizSelect = document.getElementById("quizSelect");
                const submitBtn = document.getElementById("submitBtn");
                const form = document.getElementById("randomBagForm");

                childSelect.addEventListener("change", function() {
                    quizSelect.innerHTML = '<option value="">-- Select Quiz --</option>';
                    if (submitBtn) submitBtn.disabled = true;
                    
                    const selectedChild = childrenData.find(c => c.id === this.value);
                    if (!selectedChild) return;
                    
                    // Get quizzes from the activities array and filter only visible quizzes
                    let quizzes = [];
                    
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
                        currentUrl.searchParams.set('fetch', '<?= htmlspecialchars($fetch) ?>');
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

/**
 * Full preview of a question as a student would first see it: no answer, correctness, marks or feedback.
 * Teacher class report only.
 *
 * The preview is a fresh, unanswered copy of the question, but it is wired to the id, slot and quiz context
 * of a real attempt so image/file links in the question resolve through the quiz's pluginfile access check.
 *
 * @param int $questionid
 * @param int $usageid question usage of a real attempt containing this question
 * @param int $slot slot of the question in that usage
 * @param int $cmid course module of the quiz
 * @param string $fallbacktext plain-text question text shown if the question cannot be rendered
 * @return string HTML
 */
function gradereport_user_random_bag_question_preview(int $questionid, int $usageid, int $slot, int $cmid,
        string $fallbacktext): string {
    global $CFG;
    require_once($CFG->dirroot . '/question/engine/lib.php');

    static $cache = [];
    if (isset($cache[$questionid])) {
        return $cache[$questionid];
    }
    try {
        $question = question_bank::load_question($questionid);
        $quba = question_engine::make_questions_usage_by_activity('mod_quiz', context_module::instance($cmid));
        $quba->set_preferred_behaviour('deferredfeedback');
        // Slots are sequential, so pad up to the real slot to get matching file URLs.
        for ($i = 1; $i < $slot; $i++) {
            $quba->add_question($question, $question->defaultmark);
        }
        $previewslot = $quba->add_question($question, $question->defaultmark);
        $quba->start_question($previewslot);
        $quba->set_id_from_database($usageid);

        $options = new question_display_options();
        $options->readonly = true;
        $options->marks = question_display_options::HIDDEN;
        $options->correctness = question_display_options::HIDDEN;
        $options->feedback = question_display_options::HIDDEN;
        $options->generalfeedback = question_display_options::HIDDEN;
        $options->rightanswer = question_display_options::HIDDEN;
        $options->numpartscorrect = question_display_options::HIDDEN;
        $options->manualcomment = question_display_options::HIDDEN;
        $options->flags = question_display_options::HIDDEN;
        $options->history = question_display_options::HIDDEN;
        $html = $quba->render_question($previewslot, $options);
    } catch (Throwable $e) {
        debugging('Random bag question preview failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
        $html = '<div>' . s($fallbacktext) . '</div>';
    }
    return $cache[$questionid] = $html;
}

/**
 * One student's report for their latest attempt of the quiz.
 *
 * @param int $courseid
 * @param int $student_id
 * @param int $quiz_cmid
 * @param quiz_attempt_data_processor $processor
 * @param enhanced_quiz_question_renderer $renderer
 */
function gradereport_user_random_bag_student_report(int $courseid, int $student_id, int $quiz_cmid,
        quiz_attempt_data_processor $processor, enhanced_quiz_question_renderer $renderer): void {
    global $DB, $OUTPUT;

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

            foreach ($questions as $question) {
                if (empty($question->category_name)) {
                    $question->category_name = 'Default Category';
                }
            }
            
            // Group questions by category
            $categories = $processor->group_by_category($questions);
            
            // Calculate overall statistics
            $overall_stats = $processor->calculate_overall_stats($categories);

            // Determine color based on percentage
            $percentage = $overall_stats['percentage'];
            $score_color = '#EF4444'; // Default poor color

            if ($percentage >= 80) {
                $score_color = '#16A34A'; // Excellent (green)
            } elseif ($percentage >= 40) {
                $score_color = '#D97706'; // Average (orange)
            }
            // Best and worst performing categories
            [$best_categories, $worst_categories] = gradereport_user_random_bag_highlights($categories);
            ?>
            <!DOCTYPE html>
            <html lang="en">
            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <title>Quiz Performance Report - <?= htmlspecialchars($student->firstname . ' ' . $student->lastname) ?></title>
                <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
                <?php gradereport_user_random_bag_styles(); ?>
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
                                    Top Performing Categories
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
                                                        <span style="font-weight: 600; color: var(--text-color);">
                                                            <?php echo htmlspecialchars($category['name']); ?>
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
                                                        <span style="font-weight: 600; color: var(--text-color);">
                                                            <?php echo htmlspecialchars($category['name']); ?>
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
                        <h2 class="report-h2" style="margin: 0 0 20px 0; padding: 20px 20px 0 20px;"><i class="fas fa-layer-group"></i> Category Performance Breakdown</h2>
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Category Name</th>
                                        <th>Total Questions</th>
                                        <th>Correct Answers</th>
                                        <th>Incorrect Answers</th>
                                        <th>Performance (%)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($categories as $category_name => $category_data): ?>
                                        <?php 
                                        $category_id = 'cat_' . md5($category_name);
                                        $performancePercentage = $category_data['percentage'];
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
                                        <tr class="section-row" data-section="<?= htmlspecialchars($category_name) ?>" onclick="toggleCategoryRow('<?= $category_id ?>')" id="row-<?= $category_id ?>">
                                            <td>
                                                <div class="section-name">
                                                    <i class="fas fa-chevron-right section-icon"></i>
                                                    <?= htmlspecialchars($category_name) ?>
                                                </div>
                                            </td>
                                            <td><?= $category_data['total_questions'] ?></td>
                                            <td><?= $category_data['correct_answers'] ?></td>
                                            <td><?= $category_data['wrong_answers'] ?></td>
                                            <td>
                                                <span class="chip" style="background-color: <?php echo $color; ?>; color: <?php echo $textColor; ?>; padding: 5px 10px; border-radius: 12px; display: inline-block; font-weight: bold; width:100%; text-align: center;">
                                                    <?= $performancePercentage ?>%
                                                </span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td colspan="5" style="padding: 0;">
                                                <div class="questions-container" id="<?= $category_id ?>">
                                                    <div class="questions-content">
                                                        <?php foreach ($category_data['questions'] as $question): ?>
                                                            <?php
                                                            // Prepare attempt data for enhanced renderer
                                                            $attempt_data = (object)[
                                                                'fraction' => $question->fraction,
                                                                'stepdata_value' => $processor->extract_primary_answer($question),
                                                                'state' => $question->state
                                                            ];
                                                            
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
            </body>
            </html>
            <?php
}

/**
 * Class report: every student's latest finished attempt of the quiz, averaged.
 *
 * @param int $courseid
 * @param array $students user records keyed by id
 * @param string $classname
 * @param bool $studentmode one student viewing their own data: no class wording, columns or student list
 * @param int $quiz_cmid
 * @param quiz_attempt_data_processor $processor
 * @param array $urlparams extra params kept on the per-student report links
 */
function gradereport_user_random_bag_class_report(int $courseid, array $students, string $classname, int $quiz_cmid,
        quiz_attempt_data_processor $processor, array $urlparams, bool $studentmode = false): void {
    global $DB, $OUTPUT, $PAGE;

    $quiz_info = $DB->get_record_sql("
        SELECT q.id as quiz_id, q.name as quiz_name, cm.id as cmid
          FROM {quiz} q
          JOIN {course_modules} cm ON cm.instance = q.id AND cm.module = (SELECT id FROM {modules} WHERE name = 'quiz')
         WHERE cm.id = :quiz_cmid AND cm.course = :courseid
    ", ['quiz_cmid' => $quiz_cmid, 'courseid' => $courseid]);
    if (!$quiz_info) {
        echo $OUTPUT->notification('Quiz not found.', 'notifyerror');
        exit;
    }

    // Latest attempt of each student (what "View report" shows), and their latest finished attempt
    // (what the averages use, so a re-attempt in progress doesn't hide a completed one).
    $latest_attempts = [];
    $latest_finished = [];
    if ($students) {
        [$insql, $params] = $DB->get_in_or_equal(array_keys($students), SQL_PARAMS_NAMED);
        $params['quiz_id'] = $quiz_info->quiz_id;
        $rs = $DB->get_recordset_sql("
            SELECT id AS attempt_id, userid, attempt, state
              FROM {quiz_attempts}
             WHERE quiz = :quiz_id AND preview = 0 AND userid $insql
          ORDER BY userid, attempt", $params);
        foreach ($rs as $attempt) {
            $latest_attempts[$attempt->userid] = $attempt;
            if ($attempt->state === 'finished') {
                $latest_finished[$attempt->userid] = $attempt;
            }
        }
        $rs->close();
    }

    // Per-student results, plus running totals per category and per question.
    $student_rows = [];
    $category_totals = [];
    $overall_totals = ['total_questions' => 0, 'total_correct' => 0, 'total_wrong' => 0, 'percentage' => 0];
    $finished_count = 0;
    foreach ($students as $sid => $s) {
        $attempt = $latest_attempts[$sid] ?? null;
        $finished = $latest_finished[$sid] ?? null;
        $row = ['student' => $s, 'attempt' => $attempt, 'stats' => null];
        if ($finished) {
            $questions = $processor->get_attempt_questions($finished->attempt_id);
            foreach ($questions as $question) {
                if (empty($question->category_name)) {
                    $question->category_name = 'Default Category';
                }
            }
            $student_categories = $processor->group_by_category($questions);
            $stats = $processor->calculate_overall_stats($student_categories);
            $row['stats'] = $stats;
            $finished_count++;
            foreach (array_keys($overall_totals) as $key) {
                $overall_totals[$key] += $stats[$key];
            }

            foreach ($student_categories as $category_name => $category_data) {
                if (!isset($category_totals[$category_name])) {
                    $category_totals[$category_name] = ['students' => 0, 'total_questions' => 0, 'correct_answers' => 0,
                        'wrong_answers' => 0, 'percentage' => 0, 'questions' => []];
                }
                $totals = &$category_totals[$category_name];
                $totals['students']++;
                foreach (['total_questions', 'correct_answers', 'wrong_answers', 'percentage'] as $key) {
                    $totals[$key] += $category_data[$key];
                }
                foreach ($category_data['questions'] as $question) {
                    if (!isset($totals['questions'][$question->questionid])) {
                        $totals['questions'][$question->questionid] = [
                            'name' => $question->question_name,
                            'text' => shorten_text(trim(html_entity_decode(strip_tags($question->questiontext), ENT_QUOTES, 'UTF-8')), 160),
                            'tags' => array_filter(array_map(function($tag) {
                                // Capitalise the first letter of each word, keeping acronyms (e.g. HCF) as they are.
                                return preg_replace_callback('/(^|[\s\-\/(])(\p{Ll})/u', function($m) {
                                    return $m[1] . core_text::strtoupper($m[2]);
                                }, trim($tag));
                            }, explode(' ; ', (string)($question->tag_combination ?? '')))),
                            'attempts' => 0,
                            'correct' => 0,
                            // A real attempt of this question, so its image links resolve for the teacher.
                            'usageid' => (int)$question->question_usage_id,
                            'slot' => (int)$question->slot,
                            // The student's own attempt, shown with their answer when they are the only one in the report.
                            'attempt' => $studentmode ? $question : null,
                        ];
                    }
                    $totals['questions'][$question->questionid]['attempts']++;
                    if (($question->fraction ?? 0) >= 0.5) {
                        $totals['questions'][$question->questionid]['correct']++;
                    }
                }
                unset($totals);
            }
        }
        $student_rows[] = $row;
    }

    // Averages per student who finished: per category over the students who got that category.
    $categories = [];
    foreach ($category_totals as $category_name => $totals) {
        $n = $totals['students'];
        $questions = $totals['questions'];
        uasort($questions, function($a, $b) {
            return ($a['correct'] / $a['attempts']) <=> ($b['correct'] / $b['attempts']);
        });
        $categories[$category_name] = [
            'students' => $n,
            'total_questions' => round($totals['total_questions'] / $n, 1),
            'correct_answers' => round($totals['correct_answers'] / $n, 1),
            'wrong_answers' => round($totals['wrong_answers'] / $n, 1),
            'percentage' => round($totals['percentage'] / $n, 1),
            'questions' => $questions,
        ];
    }
    $overall_stats = [];
    foreach ($overall_totals as $key => $sum) {
        $overall_stats[$key] = $finished_count ? round($sum / $finished_count, 1) : 0;
    }
    $score_color = gradereport_user_random_bag_score_color($overall_stats['percentage']);
    [$best_categories, $worst_categories] = gradereport_user_random_bag_highlights($categories);
    $avg = $studentmode ? '' : 'Avg ';
    // Renders a student's own answers, correct/incorrect status and score, as in the single-student report.
    $renderer = $studentmode ? new enhanced_quiz_question_renderer($PAGE, '') : null;

    // Lowest scores first so the teacher sees who needs help.
    usort($student_rows, function($a, $b) {
        $pa = $a['stats'] ? $a['stats']['percentage'] : 101;
        $pb = $b['stats'] ? $b['stats']['percentage'] : 101;
        return $pa <=> $pb;
    });
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?= $studentmode ? '' : 'Class ' ?>Quiz Performance Report - <?= htmlspecialchars($classname) ?></title>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
        <?php gradereport_user_random_bag_styles(); ?>
    </head>
    <body>
        <div class="quiz-dashboard">
            <!-- Header -->
            <div class="header slide-in">
                <h1><?= $studentmode ? '' : 'Class ' ?>Quiz Performance Report</h1>
                <p><?= gradereport_user_quiz_name_link($quiz_info->quiz_name, $quiz_info->cmid, $quiz_info->quiz_id) ?></p>
                <p><?= $studentmode ? 'Student' : 'Class' ?>: <?= htmlspecialchars($classname) ?></p>
            </div>

            <!-- Course and Class Information -->
            <div class="course-info card slide-in">
                <h2 class="report-h2"><i class="fas fa-graduation-cap"></i> Course Details</h2>
                <div class="info-grid">
                    <div class="info-item"><strong>Quiz:</strong> <?= gradereport_user_quiz_name_link($quiz_info->quiz_name, $quiz_info->cmid, $quiz_info->quiz_id) ?></div>
                    <div class="info-item"><strong><?= $studentmode ? 'Student' : 'Class' ?>:</strong> <?= htmlspecialchars($classname) ?></div>
                    <div class="info-item"><strong>Course ID:</strong> <?= htmlspecialchars($courseid) ?></div>
                    <?php if ($studentmode): ?>
                        <div class="info-item"><strong>Status:</strong> <?= $finished_count ? 'Completed' : 'Not completed' ?></div>
                    <?php else: ?>
                        <div class="info-item"><strong>Completed:</strong> <?= $finished_count ?> / <?= count($students) ?> students</div>
                    <?php endif; ?>
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

            <?php if (!$finished_count): ?>
                <div class="no-data-message card"><?= $studentmode ? 'You have not completed this quiz yet.' : 'No student in this class has completed this quiz yet.' ?></div>
            <?php else: ?>

            <!-- Overall Statistics (average per student) -->
            <div class="stats-grid slide-in">
                <div class="stat-card">
                    <div class="stat-number"><?= $overall_stats['total_questions'] ?></div>
                    <div class="stat-label"><?= $avg ?>Questions</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number" style="color: var(--status-success-text);"><?= $overall_stats['total_correct'] ?></div>
                    <div class="stat-label"><?= $avg ?>Correct</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number" style="color: var(--status-error-text);"><?= $overall_stats['total_wrong'] ?></div>
                    <div class="stat-label"><?= $avg ?>Wrong</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number" style="color: <?= $score_color ?>;"><?= $overall_stats['percentage'] ?>%</div>
                    <div class="stat-label"><?= $studentmode ? 'Overall Score' : 'Class Average Score' ?></div>
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
                            <?= $studentmode ? 'Your Strengths' : 'Class Strengths' ?>
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
                                                <span style="font-weight: 600; color: var(--text-color);">
                                                    <?php echo htmlspecialchars($category['name']); ?>
                                                </span>
                                            </div>
                                            <span style="background-color: #D1FAE5; color: #065F46; padding: 6px 12px; border-radius: 16px; font-size: 0.9em; font-weight: 600;">
                                                <?php echo number_format($category['percentage'], 1); ?>%
                                            </span>
                                        </div>
                                        <div style="font-size: 0.85rem; color: var(--text-light); display: flex; gap: var(--space-lg); margin-left: 36px;">
                                            <span><i class="fas fa-check-circle" style="color: #059669;"></i> <?php echo $category['correct_answers']; ?> <?= $studentmode ? '' : 'avg ' ?>correct</span>
                                            <span><i class="fas fa-question-circle"></i> <?php echo $category['total_questions']; ?> <?= $studentmode ? '' : 'avg ' ?>total</span>
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
                                                <span style="font-weight: 600; color: var(--text-color);">
                                                    <?php echo htmlspecialchars($category['name']); ?>
                                                </span>
                                            </div>
                                            <span style="background-color: #FECACA; color: #991B1B; padding: 6px 12px; border-radius: 16px; font-size: 0.9em; font-weight: 600;">
                                                <?php echo number_format($category['percentage'], 1); ?>%
                                            </span>
                                        </div>
                                        <div style="font-size: 0.85rem; color: var(--text-light); display: flex; gap: var(--space-lg); margin-left: 36px;">
                                            <span><i class="fas fa-times-circle" style="color: #DC2626;"></i> <?php echo $category['wrong_answers']; ?> <?= $studentmode ? '' : 'avg ' ?>incorrect</span>
                                            <span><i class="fas fa-question-circle"></i> <?php echo $category['total_questions']; ?> <?= $studentmode ? '' : 'avg ' ?>total</span>
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
                <h2 class="report-h2" style="margin: 0 0 20px 0; padding: 20px 20px 0 20px;"><i class="fas fa-layer-group"></i> Category Performance Breakdown<?= $studentmode ? '' : ' (class average)' ?></h2>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Category Name</th>
                                <?php if (!$studentmode): ?><th>Students</th><?php endif; ?>
                                <th><?= $avg ?>Questions</th>
                                <th><?= $avg ?>Correct</th>
                                <th><?= $avg ?>Incorrect</th>
                                <th><?= $avg ?>Performance (%)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($categories as $category_name => $category_data): ?>
                                <?php
                                $category_id = 'cat_' . md5($category_name);
                                $performancePercentage = $category_data['percentage'];
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
                                <tr class="section-row" data-section="<?= htmlspecialchars($category_name) ?>" onclick="toggleCategoryRow('<?= $category_id ?>')" id="row-<?= $category_id ?>">
                                    <td>
                                        <div class="section-name">
                                            <i class="fas fa-chevron-right section-icon"></i>
                                            <?= htmlspecialchars($category_name) ?>
                                        </div>
                                    </td>
                                    <?php if (!$studentmode): ?><td><?= $category_data['students'] ?></td><?php endif; ?>
                                    <td><?= $category_data['total_questions'] ?></td>
                                    <td><?= $category_data['correct_answers'] ?></td>
                                    <td><?= $category_data['wrong_answers'] ?></td>
                                    <td>
                                        <span class="chip" style="background-color: <?php echo $color; ?>; color: <?php echo $textColor; ?>; padding: 5px 10px; border-radius: 12px; display: inline-block; font-weight: bold; width:100%; text-align: center;">
                                            <?= $performancePercentage ?>%
                                        </span>
                                    </td>
                                </tr>
                                <tr>
                                    <td colspan="<?= $studentmode ? 5 : 6 ?>" style="padding: 0;">
                                        <div class="questions-container" id="<?= $category_id ?>">
                                            <div class="questions-content">
                                                <table class="table" style="margin: 0;">
                                                    <thead>
                                                        <tr>
                                                            <th><?= $studentmode ? 'Question' : 'Question (hardest first)' ?></th>
                                                            <?php if (!$studentmode): ?>
                                                                <th>Answered by</th>
                                                                <th>Correct</th>
                                                                <th>% Correct</th>
                                                            <?php endif; ?>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php foreach ($category_data['questions'] as $question_id => $question): ?>
                                                            <?php $qpercent = round($question['correct'] / $question['attempts'] * 100, 1); ?>
                                                            <tr>
                                                                <td>
                                                                    <details>
                                                                        <summary style="cursor: pointer;">
                                                                            <?php foreach ($question['tags'] as $tag): ?>
                                                                                <span style="display: inline-block; margin: 0 4px 4px 0; padding: 2px 8px; border-radius: 10px; background: #E0F2F1; color: #0D9488; font-size: 0.8rem; font-weight: 600;"><i class="fas fa-tag"></i> <?= htmlspecialchars($tag) ?></span>
                                                                            <?php endforeach; ?>
                                                                            <?php if (!$question['tags']): ?>
                                                                                <strong>Untagged question</strong>
                                                                            <?php endif; ?>
                                                                            <div style="font-size: 0.85rem; color: var(--text-light);"><?= htmlspecialchars($question['text']) ?></div>
                                                                        </summary>
                                                                        <div style="margin-top: 10px; padding: 12px; border: 1px solid var(--border-color); border-radius: 8px; background: #fff;">
                                                                            <?php if ($studentmode): ?>
                                                                                <?php
                                                                                $attempt_question = $question['attempt'];
                                                                                $attempt_data = (object)[
                                                                                    'fraction' => $attempt_question->fraction,
                                                                                    'stepdata_value' => $processor->extract_primary_answer($attempt_question),
                                                                                    'state' => $attempt_question->state,
                                                                                ];
                                                                                echo $renderer->render_question_report($attempt_question, $attempt_data, true);
                                                                                ?>
                                                                            <?php else: ?>
                                                                                <?= gradereport_user_random_bag_question_preview((int)$question_id, $question['usageid'], $question['slot'], $quiz_info->cmid, $question['text']) ?>
                                                                            <?php endif; ?>
                                                                        </div>
                                                                    </details>
                                                                </td>
                                                                <?php if (!$studentmode): ?>
                                                                    <td><?= $question['attempts'] ?></td>
                                                                    <td><?= $question['correct'] ?></td>
                                                                    <td style="color: <?= gradereport_user_random_bag_score_color($qpercent) ?>; font-weight: 700;"><?= $qpercent ?>%</td>
                                                                <?php endif; ?>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>

            <?php if (!$studentmode): ?>
            <!-- Student-wise results -->
            <div class="categories-table card slide-in">
                <h2 class="report-h2" style="margin: 0 0 20px 0; padding: 20px 20px 0 20px;"><i class="fas fa-users"></i> Student-wise Results</h2>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Student</th>
                                <th>Status</th>
                                <th>Correct / Total</th>
                                <th>Score (%)</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($student_rows as $row): ?>
                                <?php
                                $s = $row['student'];
                                $state = $row['attempt']->state ?? null;
                                if ($row['stats']) {
                                    $status = $state === 'finished' ? 'Completed' : 'Completed (re-attempt in progress)';
                                } else if ($state === 'abandoned') {
                                    $status = 'Never submitted';
                                } else if ($state) {
                                    $status = 'In progress';
                                } else {
                                    $status = 'Not attempted';
                                }
                                $reporturl = new moodle_url('/grade/report/user/index.php', $urlparams + [
                                    'id' => $courseid, 'fetch' => 'random', 'quiz_id' => $quiz_info->cmid, 'rbstudent' => $s->id]);
                                ?>
                                <tr>
                                    <td><?= htmlspecialchars($s->firstname . ' ' . $s->lastname) ?></td>
                                    <td><?= $status ?></td>
                                    <?php if ($row['stats']): ?>
                                        <td><?= $row['stats']['total_correct'] ?> / <?= $row['stats']['total_questions'] ?></td>
                                        <td style="color: <?= gradereport_user_random_bag_score_color($row['stats']['percentage']) ?>; font-weight: 700;"><?= $row['stats']['percentage'] ?>%</td>
                                    <?php else: ?>
                                        <td>-</td>
                                        <td>-</td>
                                    <?php endif; ?>
                                    <td>
                                        <?php if ($row['attempt']): ?>
                                            <a href="<?= $reporturl->out() ?>" target="_blank">View report</a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <script>window.__amdDefine=window.define;window.define=undefined;</script>
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        <script>window.define=window.__amdDefine;delete window.__amdDefine;</script>
        <script>
            document.addEventListener("DOMContentLoaded", function() {
                const chartCanvas = document.getElementById("overallPerformanceChart");
                if (chartCanvas) {
                    new Chart(chartCanvas.getContext("2d"), {
                        type: "pie",
                        data: {
                            labels: ["<?= $avg ?>Correct", "<?= $avg ?>Incorrect"],
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
                                legend: {position: "top", labels: {font: {size: 14, weight: "bold"}}},
                                title: {display: true, text: "<?= $studentmode ? 'Overall Performance' : 'Class Average Performance' ?>", font: {size: 18, weight: "bold"}}
                            }
                        }
                    });
                }

                function toggleCategoryRow(categoryId) {
                    const row = document.getElementById('row-' + categoryId);
                    const container = document.getElementById(categoryId);

                    if (container.classList.contains('show')) {
                        container.classList.remove('show');
                        container.style.display = 'none';
                        row.classList.remove('expanded');
                    } else {
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
                window.toggleCategoryRow = toggleCategoryRow;

                const observer = new IntersectionObserver((entries) => {
                    entries.forEach(entry => {
                        if (entry.isIntersecting) {
                            entry.target.classList.add("visible");
                        }
                    });
                }, {threshold: 0.1});
                document.querySelectorAll(".slide-in").forEach(elem => {
                    observer.observe(elem);
                });
            });
        </script>
    </body>
    </html>
    <?php
}
