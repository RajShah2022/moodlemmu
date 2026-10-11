<?php
// This program is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Admin settings.
 *
 * @package    local_xprecalc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

use local_xprecalc\recalculator;

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_xprecalc', get_string('pluginname', 'local_xprecalc'));

    // Off by default: this task rewrites student XP, so installing it must not start
    // changing data on its own.
    $settings->add(new admin_setting_configcheckbox('local_xprecalc/enabled',
        get_string('enabled', 'local_xprecalc'),
        get_string('enabled_desc', 'local_xprecalc'), 0));

    $settings->add(new admin_setting_configcheckbox('local_xprecalc/dryrun',
        get_string('dryrun', 'local_xprecalc'),
        get_string('dryrun_desc', 'local_xprecalc'), 1));

    $settings->add(new admin_setting_configcheckbox('local_xprecalc/allowincrease',
        get_string('allowincrease', 'local_xprecalc'),
        get_string('allowincrease_desc', 'local_xprecalc'), 0));

    $settings->add(new admin_setting_configselect('local_xprecalc/dedup',
        get_string('dedup', 'local_xprecalc'),
        get_string('dedup_desc', 'local_xprecalc'),
        recalculator::DEDUP_ONCE, [
            recalculator::DEDUP_ONCE => get_string('dedup_once', 'local_xprecalc'),
            recalculator::DEDUP_COOLDOWN => get_string('dedup_cooldown', 'local_xprecalc'),
            recalculator::DEDUP_RAW => get_string('dedup_raw', 'local_xprecalc'),
        ]));

    $settings->add(new admin_setting_configduration('local_xprecalc/cooldown',
        get_string('cooldown', 'local_xprecalc'),
        get_string('cooldown_desc', 'local_xprecalc'), 86400));

    $settings->add(new admin_setting_configselect('local_xprecalc/residual',
        get_string('residual', 'local_xprecalc'),
        get_string('residual_desc', 'local_xprecalc'),
        recalculator::RESIDUAL_PRESERVE, [
            recalculator::RESIDUAL_PRESERVE => get_string('residual_preserve', 'local_xprecalc'),
            recalculator::RESIDUAL_DROP => get_string('residual_drop', 'local_xprecalc'),
        ]));

    $settings->add(new admin_setting_configduration('local_xprecalc/budget',
        get_string('budget', 'local_xprecalc'),
        get_string('budget_desc', 'local_xprecalc'), 1800));

    $ADMIN->add('localplugins', $settings);
}
