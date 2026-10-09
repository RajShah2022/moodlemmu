<?php
/**
 * Read-only admin setting: invitation links table with partner dropdown.
 *
 * @package    local_trialsignup
 * @copyright  2026 MathMeUp
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_trialsignup\admin;

defined('MOODLE_INTERNAL') || die();

class setting_links_table extends \admin_setting {

    public function __construct() {
        parent::__construct(
            'local_trialsignup/links_table',
            get_string('settings_links_heading', 'local_trialsignup'),
            '',
            ''
        );
        $this->nosave = true;
    }

    public function get_setting() { return true; }
    public function get_defaultsetting() { return true; }
    public function write_setting($data) { return ''; }

    public function output_html($data, $query = '') {
        global $DB, $CFG;

        require_once($CFG->dirroot . '/local/trialsignup/lib.php');

        $eligibleids = local_trialsignup_get_eligible_cohort_ids();

        if (empty($eligibleids)) {
            $empty = \html_writer::div(
                get_string('links_table_empty', 'local_trialsignup'),
                'alert alert-info'
            );
            return format_admin_setting($this, $this->visiblename, $empty,
                get_string('settings_links_heading_desc', 'local_trialsignup'));
        }

        // Selected partner from URL (default: 0 = Self).
        $selectedpartner = optional_param('tspartner', 0, PARAM_INT);

        // Build dropdown: Self first, then active partners alphabetical.
        $partners = local_trialsignup_get_partners(true);
        $dropdownoptions = ['0' => get_string('links_filter_self', 'local_trialsignup')];
        foreach ($partners as $p) {
            $dropdownoptions[$p->id] = format_string($p->name);
        }

        if ($selectedpartner > 0 && !isset($dropdownoptions[$selectedpartner])) {
            $selectedpartner = 0;
        }

        // Use a plain div (not a form) to avoid nested-form "Leave site?" warnings.
        // The dropdown navigates via window.location directly.
        $filterhtml = '<div class="form-inline mb-4 pb-3" style="border-bottom: 1px solid #dee2e6;">';
        $filterhtml .= '<label class="mr-2 font-weight-bold" for="tspartner">' .
            get_string('links_filter_label', 'local_trialsignup') . '</label>';
        $filterhtml .= \html_writer::select(
            $dropdownoptions, 'tspartner', $selectedpartner, false,
            ['id' => 'tspartner', 'class' => 'custom-select mr-2',
             'onchange' => 'window.location.href=\'?section=local_trialsignup&tspartner=\'+this.value']);
        $filterhtml .= '</div>';

        // Fetch cohorts.
        list($insql, $params) = $DB->get_in_or_equal($eligibleids, SQL_PARAMS_NAMED);
        $cohorts = $DB->get_records_sql("
            SELECT c.id, c.name, c.idnumber, c.visible,
                   (SELECT COUNT(*) FROM {cohort_members} WHERE cohortid = c.id) AS membercount
              FROM {cohort} c
             WHERE c.id $insql
          ORDER BY c.name ASC
        ", $params);

        // Build the table.
        $table = new \html_table();
        $table->head = [
            get_string('links_table_cohort', 'local_trialsignup'),
            get_string('links_table_idnumber', 'local_trialsignup'),
            get_string('links_table_members', 'local_trialsignup'),
            get_string('links_table_url', 'local_trialsignup'),
            get_string('links_table_action', 'local_trialsignup'),
        ];
        $table->attributes['class'] = 'admintable generaltable local-trialsignup-links-table';

        foreach ($cohorts as $cohort) {
            $urlparams = ['c' => $cohort->id];
            if ($selectedpartner > 0) {
                $urlparams['p'] = $selectedpartner;
            }
            $url = new \moodle_url('/local/trialsignup/signup.php', $urlparams);
            $urlstr = $url->out(false);

            $urlinputid = 'tslink_url_' . $cohort->id;
            $copybtnid  = 'tslink_copy_' . $cohort->id;

            $urlinput = \html_writer::empty_tag('input', [
                'type'     => 'text',
                'value'    => $urlstr,
                'readonly' => 'readonly',
                'class'    => 'form-control form-control-sm',
                'id'       => $urlinputid,
                'style'    => 'min-width: 320px;',
                'onclick'  => 'this.select();',
            ]);

            $copybtn = \html_writer::tag('button',
                get_string('links_table_copy', 'local_trialsignup'),
                [
                    'type'        => 'button',
                    'class'       => 'btn btn-secondary btn-sm tslink-copy',
                    'id'          => $copybtnid,
                    'data-target' => $urlinputid,
                ]
            );

            $row = new \html_table_row([
                format_string($cohort->name),
                $cohort->idnumber ? s($cohort->idnumber) : '<em class="text-muted">—</em>',
                (int) $cohort->membercount,
                $urlinput,
                $copybtn,
            ]);

            if (empty($cohort->visible)) {
                $row->attributes['class'] = 'dimmed_text';
            }

            $table->data[] = $row;
        }

        $js = <<<'JS'
<script>
(function() {
    document.addEventListener('click', function(e) {
        var btn = e.target.closest('.tslink-copy');
        if (!btn) return;
        e.preventDefault();
        var input = document.getElementById(btn.dataset.target);
        if (!input) return;
        input.select();
        input.setSelectionRange(0, 99999);
        if (navigator.clipboard) {
            navigator.clipboard.writeText(input.value).then(function() {
                var orig = btn.textContent;
                btn.textContent = 'Copied!';
                setTimeout(function() { btn.textContent = orig; }, 1500);
            });
        } else {
            try {
                document.execCommand('copy');
                var orig = btn.textContent;
                btn.textContent = 'Copied!';
                setTimeout(function() { btn.textContent = orig; }, 1500);
            } catch (err) {}
        }
    });
})();
</script>
JS;

        $output = $filterhtml . \html_writer::table($table) . $js;

        return format_admin_setting($this, $this->visiblename, $output,
            get_string('settings_links_heading_desc', 'local_trialsignup'));
    }
}
