<?php


require_once('../../config.php');
require_once($CFG->libdir.'/adminlib.php');
require_once('./locallib.php');

// Require login and capability check
require_login();
require_capability('grade/ai:view', context_system::instance());

// Page setup
admin_externalpage_setup('report_ai');

// Fetch user reports
$userid = optional_param('userid', $USER->id, PARAM_INT);
$reports = get_user_ai_report($userid);

// Start page output
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('pluginname', 'report_ai'));

// Display reports
if (!empty($reports)) {
    echo html_writer::start_div('ai-reports-container');
    foreach ($reports as $report) {
        echo html_writer::start_div('ai-report');
        echo html_writer::tag('h3', $report->report_type);
        echo html_writer::tag('pre', print_r($report->ai_report_data, true));
        echo html_writer::end_div();
    }
    echo html_writer::end_div();
} else {
    echo $OUTPUT->notification(get_string('no_reports', 'report_ai'), 'info');
}

// Finish page
echo $OUTPUT->footer();