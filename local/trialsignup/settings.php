<?php
/**
 * Page 1: Trial Signup (Invitation Links).
 *
 * The main settings entry under Site admin → Plugins → Local plugins.
 * Shows ONLY the invitation links table with a partner dropdown.
 * All actual configuration lives on the separate config page (partners.php).
 *
 * @package    local_trialsignup
 * @copyright  2026 MathMeUp
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {

    // ============ Page 1: Invitation links (default landing) ============
    $settings = new admin_settingpage('local_trialsignup',
        get_string('pluginname', 'local_trialsignup'));
    $ADMIN->add('localplugins', $settings);

    // Link to the configuration page, reports page, and extend page.
    $configurl = new \moodle_url('/local/trialsignup/partners.php');
    $reportsurl = new \moodle_url('/local/trialsignup/reports.php');
    $extendurl = new \moodle_url('/local/trialsignup/extend.php');
    $toplinks = \html_writer::link($configurl,
        '⚙ ' . get_string('config_pagename', 'local_trialsignup'),
        ['class' => 'btn btn-secondary mr-2', 'style' => 'margin-bottom: 0.5rem;']);
    $toplinks .= \html_writer::link($reportsurl,
        '📊 ' . get_string('reports_pagename', 'local_trialsignup'),
        ['class' => 'btn btn-info mr-2', 'style' => 'margin-bottom: 0.5rem;']);
    $toplinks .= \html_writer::link($extendurl,
        '🔄 ' . get_string('extend_pagename', 'local_trialsignup'),
        ['class' => 'btn btn-success', 'style' => 'margin-bottom: 0.5rem;']);

    $settings->add(new admin_setting_heading(
        'local_trialsignup/links_topnote',
        '',
        \html_writer::div($toplinks, 'clearfix mb-3')
    ));

    // The actual links table.
    $settings->add(new \local_trialsignup\admin\setting_links_table());

    // ============ Page 2: Configuration + Partners (separate external page) ============
    $ADMIN->add('localplugins', new admin_externalpage(
        'local_trialsignup_config',
        get_string('config_pagename', 'local_trialsignup'),
        new \moodle_url('/local/trialsignup/partners.php'),
        'moodle/site:config'
    ));

    // ============ Page 3: Reports (separate external page) ============
    $ADMIN->add('localplugins', new admin_externalpage(
        'local_trialsignup_reports',
        get_string('reports_pagename', 'local_trialsignup'),
        new \moodle_url('/local/trialsignup/reports.php'),
        'moodle/site:config'
    ));

    // ============ Page 4: Extend Trials (separate external page) ============
    $ADMIN->add('localplugins', new admin_externalpage(
        'local_trialsignup_extend',
        get_string('extend_pagename', 'local_trialsignup'),
        new \moodle_url('/local/trialsignup/extend.php'),
        'moodle/site:config'
    ));
}
