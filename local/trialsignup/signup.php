<?php
/**
 * Trial signup page.
 *
 * URL formats accepted:
 *   /local/trialsignup/signup.php?c=<cohortid>                (no partner)
 *   /local/trialsignup/signup.php?c=<cohortid>&p=<partnerid>  (with partner)
 *   /local/trialsignup/signup.php?cohortid=N                  (v0.4 backward compat)
 *
 * @package    local_trialsignup
 * @copyright  2026 MathMeUp
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/authlib.php');
require_once($CFG->libdir . '/moodlelib.php');
require_once(__DIR__ . '/lib.php');

$PAGE->set_url(new moodle_url('/local/trialsignup/signup.php'));
$PAGE->set_context(context_system::instance());
$PAGE->set_pagelayout('login');
$PAGE->set_title(get_string('signup_pagetitle', 'local_trialsignup'));
$PAGE->set_heading(get_string('signup_pagetitle', 'local_trialsignup'));
$PAGE->requires->css('/local/trialsignup/styles/signup.css');

if (isloggedin() && !isguestuser()) {
    redirect(new moodle_url('/my/'));
}

// =====================================================================
// Read URL params (with backward compat)
// =====================================================================
$cohortid = optional_param('c', 0, PARAM_INT);
if (!$cohortid) {
    $cohortid = optional_param('cohortid', 0, PARAM_INT);
}
$partnerid = optional_param('p', 0, PARAM_INT);

// =====================================================================
// Validate
// =====================================================================
$eligible = local_trialsignup_get_eligible_cohort_ids();
$costcenterid = (int) get_config('local_trialsignup', 'costcenter');

$fatalerror = null;
$cohort = null;
$partner = null;

$invalidmsg = get_string('error_invalid_invitation', 'local_trialsignup');

if (empty($costcenterid) || empty($eligible)) {
    $fatalerror = get_string('error_notconfigured', 'local_trialsignup');
} else if (!$DB->record_exists('local_costcenter', ['id' => $costcenterid])) {
    $fatalerror = get_string('error_costcenter_missing', 'local_trialsignup');
} else if (!$cohortid) {
    $fatalerror = $invalidmsg;
} else if (!in_array($cohortid, $eligible, true)) {
    $fatalerror = $invalidmsg;
} else {
    $cohort = $DB->get_record('cohort', ['id' => $cohortid, 'visible' => 1],
        'id, name, idnumber', IGNORE_MISSING);
    if (!$cohort) {
        $fatalerror = $invalidmsg;
    }
}

// Partner validation (optional).
if ($fatalerror === null && $partnerid > 0) {
    $partner = local_trialsignup_get_partner($partnerid);
    if (!$partner || !$partner->active) {
        $fatalerror = $invalidmsg;
    }
}

// =====================================================================
// Fatal error path
// =====================================================================
if ($fatalerror !== null) {
    echo $OUTPUT->header();
    echo html_writer::div($fatalerror, 'alert alert-danger text-center my-4');
    echo html_writer::div(
        html_writer::link(new moodle_url('/login/index.php'),
            get_string('login'), ['class' => 'btn btn-secondary']),
        'text-center'
    );
    echo $OUTPUT->footer();
    exit;
}

// =====================================================================
// Render and process form
// =====================================================================
$formtitle = get_config('local_trialsignup', 'formtitle');
if (empty($formtitle)) {
    $formtitle = get_string('setting_formtitle_default', 'local_trialsignup');
}

$formurlparams = ['c' => $cohortid];
if ($partnerid > 0) {
    $formurlparams['p'] = $partnerid;
}

$form = new \local_trialsignup\form\signup_form(
    new moodle_url('/local/trialsignup/signup.php', $formurlparams),
    [
        'cohortid'   => $cohortid,
        'partnerid'  => $partnerid,
        'cohortname' => $cohort->name,
        'title'      => $formtitle,
    ]
);

if ($form->is_cancelled()) {
    redirect(new moodle_url('/login/index.php'));
}

$data = $form->get_data();

if ($data) {
    if ((int) $data->cohortid !== $cohortid || !in_array((int) $data->cohortid, $eligible, true)) {
        echo $OUTPUT->header();
        echo html_writer::div($invalidmsg, 'alert alert-danger');
        echo $OUTPUT->footer();
        exit;
    }

    $submittedpartnerid = (int) ($data->partnerid ?? 0);
    if ($submittedpartnerid > 0) {
        $checkpartner = local_trialsignup_get_partner($submittedpartnerid);
        if (!$checkpartner || !$checkpartner->active) {
            echo $OUTPUT->header();
            echo html_writer::div($invalidmsg, 'alert alert-danger');
            echo $OUTPUT->footer();
            exit;
        }
    }

    // Stash for the user_created observer.
    $SESSION->local_trialsignup_cohortid = $cohortid;
    $SESSION->local_trialsignup_partnerid = $submittedpartnerid;

    $user = new stdClass();
    $user->username    = strtolower(trim($data->username));
    $user->password    = $data->password;
    $user->email       = strtolower(trim($data->email));
    $user->firstname   = trim($data->firstname);
    $user->lastname    = trim($data->lastname);
    $user->phone1      = trim($data->phone1);
    $user->institution = trim($data->institution ?? '');
    $user->city        = trim($data->city ?? '');
    $user->country     = trim($data->country ?? '');
    $user->lang        = current_language();
    $user->confirmed   = 0;
    $user->auth        = 'email';
    $user->secret      = random_string(15);
    $user->mailformat  = 1;
    $user->mnethostid  = $CFG->mnet_localhost_id;

    $authplugin = get_auth_plugin('email');

    try {
        $authplugin->user_signup($user, false);
    } catch (\moodle_exception $e) {
        echo $OUTPUT->header();
        echo html_writer::div($e->getMessage(), 'alert alert-danger');
        echo $OUTPUT->footer();
        exit;
    }

    // Confirmation page.
    echo $OUTPUT->header();
    echo html_writer::start_div('local-trialsignup-confirm text-center my-5');
    echo html_writer::tag('h2', get_string('confirm_title', 'local_trialsignup'),
        ['class' => 'mb-3']);
    echo html_writer::div(
        get_string('confirm_message', 'local_trialsignup', s($user->email)),
        'lead mb-3'
    );
    echo html_writer::div(
        get_string('confirm_emailtrouble', 'local_trialsignup'),
        'text-muted mb-4'
    );
    echo html_writer::link(new moodle_url('/login/index.php'),
        get_string('confirm_loginlink', 'local_trialsignup'),
        ['class' => 'btn btn-primary']);
    echo html_writer::end_div();
    echo $OUTPUT->footer();
    exit;
}

// Initial render.
echo $OUTPUT->header();
$form->display();
echo $OUTPUT->footer();
