<?php
/**
 * Page 2: Trial Signup Configuration.
 *
 * Hosts:
 *   - All trial settings (duration, cost-centre, role, eligible cohorts, form title, contact email)
 *   - Partners CRUD (list table + add form, with active/inactive toggle action)
 *
 * @package    local_trialsignup
 * @copyright  2026 MathMeUp
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once(__DIR__ . '/lib.php');

admin_externalpage_setup('local_trialsignup_config');

$action = optional_param('action', '', PARAM_ALPHA);
$partnerid = optional_param('id', 0, PARAM_INT);

$context = context_system::instance();
require_capability('moodle/site:config', $context);

// =====================================================================
// Handle actions (toggle / add)
// =====================================================================

if ($action === 'toggle' && $partnerid > 0) {
    require_sesskey();

    $partner = $DB->get_record('local_trialsignup_partners', ['id' => $partnerid], '*', MUST_EXIST);
    $partner->active = $partner->active ? 0 : 1;
    $DB->update_record('local_trialsignup_partners', $partner);

    $msgkey = $partner->active ? 'partner_activated' : 'partner_deactivated';
    redirect(new moodle_url('/local/trialsignup/partners.php'),
        get_string($msgkey, 'local_trialsignup'),
        null, \core\output\notification::NOTIFY_SUCCESS);
}

if ($action === 'add') {
    require_sesskey();

    $name = trim(required_param('name', PARAM_TEXT));
    if ($name === '') {
        redirect(new moodle_url('/local/trialsignup/partners.php'),
            get_string('partner_name_required', 'local_trialsignup'),
            null, \core\output\notification::NOTIFY_ERROR);
    }

    $DB->insert_record('local_trialsignup_partners', (object) [
        'name'        => $name,
        'active'      => 1,
        'timecreated' => time(),
    ]);

    redirect(new moodle_url('/local/trialsignup/partners.php'),
        get_string('partner_added', 'local_trialsignup'),
        null, \core\output\notification::NOTIFY_SUCCESS);
}

if ($action === 'savesettings') {
    require_sesskey();

    set_config('trialdays',        required_param('trialdays', PARAM_INT),     'local_trialsignup');
    set_config('costcenter',       required_param('costcenter', PARAM_INT),    'local_trialsignup');
    set_config('roleid',           required_param('roleid', PARAM_INT),        'local_trialsignup');
    set_config('formtitle',        optional_param('formtitle', '', PARAM_TEXT),'local_trialsignup');
    set_config('contactphone',     optional_param('contactphone', '', PARAM_TEXT), 'local_trialsignup');

    $cohorts = optional_param_array('eligiblecohorts', [], PARAM_INT);
    set_config('eligiblecohorts', implode(',', array_map('intval', $cohorts)), 'local_trialsignup');

    redirect(new moodle_url('/local/trialsignup/partners.php'),
        get_string('changessaved'),
        null, \core\output\notification::NOTIFY_SUCCESS);
}

// =====================================================================
// Render page
// =====================================================================

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('config_pagename', 'local_trialsignup'));

// ---- Section 1: Settings form ----
echo $OUTPUT->heading(get_string('settings_heading', 'local_trialsignup'), 3);
echo html_writer::div(get_string('settings_heading_desc', 'local_trialsignup'),
    'text-muted mb-3');

echo render_settings_form();

// ---- Section 2: Partners ----
echo $OUTPUT->heading(get_string('partners_heading', 'local_trialsignup'), 3, 'mt-5');
echo html_writer::div(get_string('partners_heading_desc', 'local_trialsignup'),
    'text-muted mb-3');

echo render_add_partner_form();
echo render_partners_table();

echo $OUTPUT->footer();

// =====================================================================
// Renderers (file-local helpers)
// =====================================================================

function render_settings_form(): string {
    global $DB, $OUTPUT;

    $action = new \moodle_url('/local/trialsignup/partners.php');
    $sesskey = sesskey();

    // Cost-centre options.
    $costcenteroptions = [];
    try {
        $dbman = $DB->get_manager();
        if ($dbman->table_exists('local_costcenter')) {
            $centres = $DB->get_records('local_costcenter', ['visible' => 1],
                'parentid, fullname ASC', 'id, fullname, shortname, path');
            foreach ($centres as $c) {
                $costcenteroptions[$c->id] = format_string($c->fullname) .
                    ' (' . $c->shortname . ' — ' . $c->path . ')';
            }
        }
    } catch (\Throwable $e) {
        debugging($e->getMessage(), DEBUG_DEVELOPER);
    }
    if (empty($costcenteroptions)) {
        $costcenteroptions = [0 => '-- No cost-centres found --'];
    }

    // Role options.
    $roleoptions = [];
    $roles = $DB->get_records('role', null, 'sortorder ASC', 'id, shortname, name');
    foreach ($roles as $r) {
        $label = !empty($r->name) ? format_string($r->name) : $r->shortname;
        $roleoptions[$r->id] = $label . ' (' . $r->shortname . ')';
    }

    // Cohort options.
    $cohortoptions = [];
    $cohorts = $DB->get_records_sql("
        SELECT DISTINCT co.id, co.name, co.idnumber
          FROM {cohort} co
          JOIN {enrol} e ON e.customint1 = co.id AND e.enrol = 'cohort' AND e.status = 0
          JOIN {course} c ON c.id = e.courseid AND c.visible = 1 AND c.id != :siteid
         WHERE co.visible = 1
      ORDER BY co.name ASC
    ", ['siteid' => SITEID]);
    foreach ($cohorts as $co) {
        $label = format_string($co->name);
        if (!empty($co->idnumber)) {
            $label .= ' — ' . $co->idnumber;
        }
        $cohortoptions[$co->id] = $label;
    }

    // Current values.
    $current_trialdays    = get_config('local_trialsignup', 'trialdays') ?: 7;
    $current_costcenter   = get_config('local_trialsignup', 'costcenter') ?: 0;
    $current_roleid       = get_config('local_trialsignup', 'roleid') ?: 0;
    $current_formtitle    = get_config('local_trialsignup', 'formtitle')
        ?: get_string('setting_formtitle_default', 'local_trialsignup');
    $current_contact      = get_config('local_trialsignup', 'contactphone') ?: '';
    $current_cohorts_raw  = get_config('local_trialsignup', 'eligiblecohorts') ?: '';
    $current_cohorts = array_filter(array_map('intval', explode(',', $current_cohorts_raw)));

    $html  = '<form method="post" action="' . $action->out(false) . '" class="mb-5">';
    $html .= '<input type="hidden" name="action" value="savesettings">';
    $html .= '<input type="hidden" name="sesskey" value="' . $sesskey . '">';

    $html .= field_row('trialdays',
        get_string('setting_trialdays', 'local_trialsignup'),
        '<input type="number" min="1" max="365" name="trialdays" value="' .
        s($current_trialdays) . '" class="form-control" style="max-width: 120px;">',
        get_string('setting_trialdays_desc', 'local_trialsignup'));

    $html .= field_row('costcenter',
        get_string('setting_costcenter', 'local_trialsignup'),
        \html_writer::select($costcenteroptions, 'costcenter', $current_costcenter, false,
            ['class' => 'form-control', 'style' => 'max-width: 480px;']),
        get_string('setting_costcenter_desc', 'local_trialsignup'));

    $html .= field_row('roleid',
        get_string('setting_role', 'local_trialsignup'),
        \html_writer::select($roleoptions, 'roleid', $current_roleid, false,
            ['class' => 'form-control', 'style' => 'max-width: 480px;']),
        get_string('setting_role_desc', 'local_trialsignup'));

    $html .= field_row('eligiblecohorts',
        get_string('setting_eligiblecohorts', 'local_trialsignup'),
        \html_writer::select($cohortoptions, 'eligiblecohorts[]', $current_cohorts, false,
            ['class' => 'form-control', 'multiple' => 'multiple', 'size' => 8,
             'style' => 'max-width: 480px;']),
        get_string('setting_eligiblecohorts_desc', 'local_trialsignup'));

    $html .= field_row('formtitle',
        get_string('setting_formtitle', 'local_trialsignup'),
        '<input type="text" name="formtitle" value="' . s($current_formtitle) .
        '" class="form-control" maxlength="200">',
        get_string('setting_formtitle_desc', 'local_trialsignup'));

    $html .= field_row('contactphone',
        get_string('setting_contactemail', 'local_trialsignup'),
        '<input type="email" name="contactphone" value="' . s($current_contact) .
        '" class="form-control" style="max-width: 360px;" placeholder="e.g. info@mathmeup.com">',
        get_string('setting_contactemail_desc', 'local_trialsignup'));

    $html .= '<div class="mt-3"><button type="submit" class="btn btn-primary">' .
        get_string('savechanges') . '</button></div>';
    $html .= '</form>';

    return $html;
}

function field_row(string $name, string $label, string $input, string $help): string {
    $html  = '<div class="form-group row mb-3">';
    $html .= '<label for="' . s($name) . '" class="col-md-3 col-form-label font-weight-bold">' .
        s($label) . '</label>';
    $html .= '<div class="col-md-9">';
    $html .= $input;
    if ($help !== '') {
        $html .= '<small class="form-text text-muted">' . $help . '</small>';
    }
    $html .= '</div></div>';
    return $html;
}

function render_add_partner_form(): string {
    $url = new \moodle_url('/local/trialsignup/partners.php');
    $sesskey = sesskey();

    $html  = '<form method="post" action="' . $url->out(false) . '" class="form-inline mb-3">';
    $html .= '<input type="hidden" name="action" value="add">';
    $html .= '<input type="hidden" name="sesskey" value="' . $sesskey . '">';
    $html .= '<label class="mr-2 font-weight-bold">' .
        get_string('partner_name', 'local_trialsignup') . ':</label>';
    $html .= '<input type="text" name="name" required class="form-control mr-2" ' .
        'placeholder="' . get_string('partner_add', 'local_trialsignup') .
        '" style="min-width: 240px;">';
    $html .= '<button type="submit" class="btn btn-primary">' .
        get_string('partner_add_button', 'local_trialsignup') . '</button>';
    $html .= '</form>';
    return $html;
}

function render_partners_table(): string {
    $partners = local_trialsignup_get_partners();

    if (empty($partners)) {
        return \html_writer::div(
            get_string('partner_none', 'local_trialsignup'),
            'alert alert-info'
        );
    }

    $table = new \html_table();
    $table->head = [
        get_string('partner_id', 'local_trialsignup'),
        get_string('partner_name', 'local_trialsignup'),
        get_string('partner_active', 'local_trialsignup'),
        get_string('partner_actions', 'local_trialsignup'),
    ];
    $table->attributes['class'] = 'admintable generaltable';

    foreach ($partners as $p) {
        $statuslabel = $p->active
            ? '<span class="badge badge-success">' . get_string('partner_status_active', 'local_trialsignup') . '</span>'
            : '<span class="badge badge-secondary">' . get_string('partner_status_inactive', 'local_trialsignup') . '</span>';

        $togglebtnlabel = $p->active
            ? get_string('partner_deactivate_btn', 'local_trialsignup')
            : get_string('partner_activate_btn', 'local_trialsignup');

        $togglebtnclass = $p->active ? 'btn-warning' : 'btn-success';

        $toggleurl = new \moodle_url('/local/trialsignup/partners.php', [
            'action'  => 'toggle',
            'id'      => $p->id,
            'sesskey' => sesskey(),
        ]);

        $togglebtn = \html_writer::link($toggleurl, $togglebtnlabel,
            ['class' => 'btn btn-sm ' . $togglebtnclass]);

        $row = new \html_table_row([
            $p->id,
            format_string($p->name),
            $statuslabel,
            $togglebtn,
        ]);
        if (!$p->active) {
            $row->attributes['class'] = 'dimmed_text';
        }
        $table->data[] = $row;
    }

    return \html_writer::table($table);
}
