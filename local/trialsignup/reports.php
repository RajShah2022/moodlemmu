<?php
/**
 * Trial Signup Reports — download-only page.
 *
 * Shows a date range picker and a download button. No data displayed on page.
 * The Excel file contains 5 sheets, all filtered by the selected date range.
 *
 * @package    local_trialsignup
 * @copyright  2026 MathMeUp
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once(__DIR__ . '/lib.php');

admin_externalpage_setup('local_trialsignup_reports');

$context = context_system::instance();
require_capability('moodle/site:config', $context);

$trialpath = '/1/18';

// Read date params. Default: last 7 days.
$defaultfrom = date('Y-m-d', strtotime('-7 days'));
$defaultto   = date('Y-m-d');
$fromstr = optional_param('from', $defaultfrom, PARAM_TEXT);
$tostr   = optional_param('to', $defaultto, PARAM_TEXT);
$download = optional_param('download', 0, PARAM_INT);

// Convert to timestamps. From = start of day, To = end of day.
$fromts = strtotime($fromstr . ' 00:00:00');
$tots   = strtotime($tostr . ' 23:59:59');

// Sanity.
if (!$fromts) $fromts = 0;
if (!$tots) $tots = time();
if ($fromts > $tots) {
    $tmp = $fromts;
    $fromts = $tots;
    $tots = $tmp;
}

// =====================================================================
// DOWNLOAD — generate multi-sheet Excel
// =====================================================================
if ($download) {
    require_once($CFG->libdir . '/excellib.class.php');

    // Prevent timeouts and memory issues on large datasets.
    \core_php_time_limit::raise(300);
    raise_memory_limit(MEMORY_HUGE);

    $filename = 'trial_report_' . $fromstr . '_to_' . $tostr;
    $workbook = new \MoodleExcelWorkbook($filename);

    $hfmt = $workbook->add_format([
        'bold' => 1,
        'bg_color' => '#4472C4',
        'color' => 'white',
        'border' => 1,
    ]);

    $baseparams = ['path' => $trialpath, 'fromts' => $fromts, 'tots' => $tots];

    // ================================================================
    // Sheet 1: All Users
    // ================================================================
    $sheet = $workbook->add_worksheet('All Users');
    $headers = ['ID', 'Username', 'Name', 'Email', 'Mobile', 'School',
                'Signed Up', 'Last Seen', 'Partner', 'Cohort', 'Trial Status', 'Expires'];
    write_header_row($sheet, $headers, $hfmt);
    set_col_widths($sheet, [8, 15, 25, 30, 15, 20, 18, 18, 15, 25, 12, 18]);

    $rows = $DB->get_records_sql("
        SELECT u.id, u.username, u.firstname, u.lastname, u.email, u.phone1,
               u.institution, u.timecreated, u.lastaccess,
               COALESCE(p.name, '') AS partnername,
               COALESCE(co.name, '') AS cohortname,
               COALESCE(a.status, 'no record') AS trialstatus,
               a.expires
        FROM {user} u
        LEFT JOIN {local_trialsignup_assignments} a ON a.userid = u.id
        LEFT JOIN {local_trialsignup_partners} p ON p.id = u.trialsignup_partnerid
        LEFT JOIN {cohort} co ON co.id = a.cohortid
        WHERE u.deleted = 0 AND u.open_path = :path
          AND u.timecreated BETWEEN :fromts AND :tots
        ORDER BY u.timecreated DESC
    ", $baseparams);

    $r = 1;
    foreach ($rows as $u) {
        $sheet->write_number($r, 0, $u->id);
        $sheet->write_string($r, 1, $u->username);
        $sheet->write_string($r, 2, $u->firstname . ' ' . $u->lastname);
        $sheet->write_string($r, 3, $u->email);
        $sheet->write_string($r, 4, $u->phone1 ?? '');
        $sheet->write_string($r, 5, $u->institution ?? '');
        $sheet->write_string($r, 6, ts($u->timecreated));
        $sheet->write_string($r, 7, $u->lastaccess ? ts($u->lastaccess) : 'Never');
        $sheet->write_string($r, 8, $u->partnername ?: '— direct —');
        $sheet->write_string($r, 9, $u->cohortname ?: '—');
        $sheet->write_string($r, 10, $u->trialstatus);
        $sheet->write_string($r, 11, $u->expires ? ts($u->expires) : '—');
        $r++;
    }

    // ================================================================
    // Sheet 2: Courses Visited
    // ================================================================
    $sheet = $workbook->add_worksheet('Courses Visited');
    $headers = ['User ID', 'Username', 'Name', 'Course', 'Course Name',
                'First Visit', 'Last Visit', 'Days Active', 'Total Actions'];
    write_header_row($sheet, $headers, $hfmt);
    set_col_widths($sheet, [8, 15, 25, 15, 30, 18, 18, 12, 12]);

    $rows = $DB->get_records_sql("
        SELECT CONCAT(u.id, '-', c.id) AS uid_cid,
               u.id AS userid, u.username, u.firstname, u.lastname,
               c.shortname, c.fullname,
               MIN(l.timecreated) AS firstvisit,
               MAX(l.timecreated) AS lastvisit,
               COUNT(DISTINCT DATE(FROM_UNIXTIME(l.timecreated))) AS daysactive,
               COUNT(*) AS totalactions
        FROM {user} u
        JOIN {logstore_standard_log} l ON l.userid = u.id AND l.courseid > 1
        JOIN {course} c ON c.id = l.courseid
        WHERE u.deleted = 0 AND u.open_path = :path
          AND l.timecreated BETWEEN :fromts AND :tots
        GROUP BY u.id, u.username, u.firstname, u.lastname, c.id, c.shortname, c.fullname
        ORDER BY u.username, totalactions DESC
    ", $baseparams);

    $r = 1;
    foreach ($rows as $cv) {
        $sheet->write_number($r, 0, $cv->userid);
        $sheet->write_string($r, 1, $cv->username);
        $sheet->write_string($r, 2, $cv->firstname . ' ' . $cv->lastname);
        $sheet->write_string($r, 3, $cv->shortname);
        $sheet->write_string($r, 4, $cv->fullname);
        $sheet->write_string($r, 5, ts($cv->firstvisit));
        $sheet->write_string($r, 6, ts($cv->lastvisit));
        $sheet->write_number($r, 7, $cv->daysactive);
        $sheet->write_number($r, 8, $cv->totalactions);
        $r++;
    }

    // ================================================================
    // Sheet 3: Quiz Attempts
    // ================================================================
    $sheet = $workbook->add_worksheet('Quiz Attempts');
    $headers = ['User ID', 'Username', 'Name', 'Course', 'Quiz',
                'Attempt #', 'Score %', 'Started', 'Finished', 'Status'];
    write_header_row($sheet, $headers, $hfmt);
    set_col_widths($sheet, [8, 15, 25, 15, 30, 10, 10, 18, 18, 12]);

    $rows = $DB->get_records_sql("
        SELECT CONCAT(u.id, '-', qa.id) AS uid_qaid,
               u.id AS userid, u.username, u.firstname, u.lastname,
               c.shortname AS courseshort, q.name AS quizname,
               qa.attempt, qa.sumgrades, q.sumgrades AS qmaxgrade,
               qa.timestart, qa.timefinish, qa.state
        FROM {user} u
        JOIN {quiz_attempts} qa ON qa.userid = u.id AND qa.preview = 0
        JOIN {quiz} q ON q.id = qa.quiz
        JOIN {course} c ON c.id = q.course
        WHERE u.deleted = 0 AND u.open_path = :path
          AND qa.timestart BETWEEN :fromts AND :tots
        ORDER BY u.username, qa.timestart DESC
    ", $baseparams);

    $r = 1;
    foreach ($rows as $qa) {
        $scorepct = ($qa->qmaxgrade > 0 && $qa->sumgrades !== null)
            ? round($qa->sumgrades / $qa->qmaxgrade * 100, 1) : null;
        $status = $qa->state;
        if ($status === 'finished') $status = 'completed';
        if ($status === 'inprogress') $status = 'in progress';

        $sheet->write_number($r, 0, $qa->userid);
        $sheet->write_string($r, 1, $qa->username);
        $sheet->write_string($r, 2, $qa->firstname . ' ' . $qa->lastname);
        $sheet->write_string($r, 3, $qa->courseshort);
        $sheet->write_string($r, 4, $qa->quizname);
        $sheet->write_number($r, 5, $qa->attempt);
        if ($scorepct !== null) {
            $sheet->write_number($r, 6, $scorepct);
        } else {
            $sheet->write_string($r, 6, '—');
        }
        $sheet->write_string($r, 7, ts($qa->timestart));
        $sheet->write_string($r, 8, $qa->timefinish ? ts($qa->timefinish) : '—');
        $sheet->write_string($r, 9, $status);
        $r++;
    }

    // ================================================================
    // Sheet 4: Quiz Summary (per user)
    // ================================================================
    $sheet = $workbook->add_worksheet('Quiz Summary');
    $headers = ['User ID', 'Username', 'Name', 'Trial Status',
                'Quizzes Attempted', 'Total Attempts', 'Completed',
                'In Progress', 'Avg Score %', 'Best Score %'];
    write_header_row($sheet, $headers, $hfmt);
    set_col_widths($sheet, [8, 15, 25, 12, 15, 15, 12, 12, 12, 12]);

    $rows = $DB->get_records_sql("
        SELECT u.id AS userid, u.username, u.firstname, u.lastname,
               COALESCE(a.status, 'no record') AS trialstatus,
               COUNT(DISTINCT qa.quiz) AS quizzesattempted,
               COUNT(qa.id) AS totalattempts,
               SUM(CASE WHEN qa.state = 'finished' THEN 1 ELSE 0 END) AS completed,
               SUM(CASE WHEN qa.state = 'inprogress' THEN 1 ELSE 0 END) AS inprogress,
               AVG(CASE WHEN qa.state = 'finished' AND q.sumgrades > 0
                   THEN qa.sumgrades / q.sumgrades * 100 ELSE NULL END) AS avgscore,
               MAX(CASE WHEN qa.state = 'finished' AND q.sumgrades > 0
                   THEN qa.sumgrades / q.sumgrades * 100 ELSE NULL END) AS bestscore
        FROM {user} u
        LEFT JOIN {local_trialsignup_assignments} a ON a.userid = u.id
        LEFT JOIN {quiz_attempts} qa ON qa.userid = u.id AND qa.preview = 0
            AND qa.timestart BETWEEN :fromts AND :tots
        LEFT JOIN {quiz} q ON q.id = qa.quiz
        WHERE u.deleted = 0 AND u.open_path = :path
          AND u.timecreated BETWEEN :fromts2 AND :tots2
        GROUP BY u.id, u.username, u.firstname, u.lastname, a.status
        ORDER BY totalattempts DESC
    ", ['path' => $trialpath, 'fromts' => $fromts, 'tots' => $tots,
        'fromts2' => $fromts, 'tots2' => $tots]);

    $r = 1;
    foreach ($rows as $qs) {
        $sheet->write_number($r, 0, $qs->userid);
        $sheet->write_string($r, 1, $qs->username);
        $sheet->write_string($r, 2, $qs->firstname . ' ' . $qs->lastname);
        $sheet->write_string($r, 3, $qs->trialstatus);
        $sheet->write_number($r, 4, $qs->quizzesattempted);
        $sheet->write_number($r, 5, $qs->totalattempts);
        $sheet->write_number($r, 6, $qs->completed);
        $sheet->write_number($r, 7, $qs->inprogress);
        $sheet->write_string($r, 8, $qs->avgscore !== null ? round($qs->avgscore, 1) . '%' : '—');
        $sheet->write_string($r, 9, $qs->bestscore !== null ? round($qs->bestscore, 1) . '%' : '—');
        $r++;
    }

    // ================================================================
    // Sheet 5: Partner Summary
    // ================================================================
    $sheet = $workbook->add_worksheet('Partner Summary');
    $headers = ['Partner', 'Partner ID', 'Total Signups', 'Active Trials',
                'Expired Trials', 'Active Last 7 Days', 'First Signup', 'Latest Signup'];
    write_header_row($sheet, $headers, $hfmt);
    set_col_widths($sheet, [20, 10, 15, 15, 15, 15, 18, 18]);

    $rows = $DB->get_records_sql("
        SELECT COALESCE(p.id, 0) AS partnerid,
               COALESCE(p.name, '— direct —') AS partnername,
               COUNT(*) AS totalsignups,
               SUM(CASE WHEN a.status = 'active' THEN 1 ELSE 0 END) AS activet,
               SUM(CASE WHEN a.status = 'expired' THEN 1 ELSE 0 END) AS expiredt,
               SUM(CASE WHEN u.lastaccess > UNIX_TIMESTAMP() - 7*86400 THEN 1 ELSE 0 END) AS active7d,
               MIN(u.timecreated) AS firstsignup,
               MAX(u.timecreated) AS latestsignup
        FROM {user} u
        LEFT JOIN {local_trialsignup_assignments} a ON a.userid = u.id
        LEFT JOIN {local_trialsignup_partners} p ON p.id = u.trialsignup_partnerid
        WHERE u.deleted = 0 AND u.open_path = :path
          AND u.timecreated BETWEEN :fromts AND :tots
        GROUP BY p.id, p.name
        ORDER BY totalsignups DESC
    ", $baseparams);

    $r = 1;
    foreach ($rows as $ps) {
        $sheet->write_string($r, 0, $ps->partnername);
        $sheet->write_number($r, 1, $ps->partnerid);
        $sheet->write_number($r, 2, $ps->totalsignups);
        $sheet->write_number($r, 3, $ps->activet);
        $sheet->write_number($r, 4, $ps->expiredt);
        $sheet->write_number($r, 5, $ps->active7d);
        $sheet->write_string($r, 6, $ps->firstsignup ? ts($ps->firstsignup) : '—');
        $sheet->write_string($r, 7, $ps->latestsignup ? ts($ps->latestsignup) : '—');
        $r++;
    }

    $workbook->close();
    exit;
}

// =====================================================================
// PAGE RENDER — date picker + download button only
// =====================================================================

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('reports_pagetitle', 'local_trialsignup'));

$downloadurl = new moodle_url('/local/trialsignup/reports.php');

echo '<form method="get" action="' . $downloadurl->out(false) . '" class="my-4">';
echo '<input type="hidden" name="download" value="1">';

echo '<div class="form-inline mb-3">';
echo '<label class="mr-2 font-weight-bold" for="from">' .
    get_string('reports_from', 'local_trialsignup') . '</label>';
echo '<input type="date" name="from" id="from" value="' . s($fromstr) .
    '" class="form-control mr-4">';
echo '<label class="mr-2 font-weight-bold" for="to">' .
    get_string('reports_to', 'local_trialsignup') . '</label>';
echo '<input type="date" name="to" id="to" value="' . s($tostr) .
    '" class="form-control">';
echo '</div>';

echo '<button type="submit" class="btn btn-primary btn-lg">' .
    '📥 ' . get_string('reports_download', 'local_trialsignup') . '</button>';
echo '<p class="text-muted mt-2">' .
    get_string('reports_download_desc', 'local_trialsignup') . '</p>';

echo '</form>';

echo $OUTPUT->footer();

// =====================================================================
// Helpers
// =====================================================================

/**
 * Format unix timestamp as readable date string.
 */
function ts(int $timestamp): string {
    return $timestamp ? userdate($timestamp, '%Y-%m-%d %H:%M') : '';
}

/**
 * Write a header row with formatting.
 */
function write_header_row($sheet, array $headers, $format): void {
    foreach ($headers as $col => $text) {
        $sheet->write_string(0, $col, $text, $format);
    }
}

/**
 * Set column widths from an array.
 */
function set_col_widths($sheet, array $widths): void {
    foreach ($widths as $col => $width) {
        $sheet->set_column($col, $col, $width);
    }
}
