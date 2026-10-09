<?php
// This program is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Scheduled tasks.
 *
 * @package    local_xprecalc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$tasks = [
    [
        'classname' => 'local_xprecalc\task\reconcile_task',
        'blocking' => 0,
        // Nightly, off-peak. The task is additionally gated by the
        // local_xprecalc/enabled setting, which is off until switched on.
        'minute' => '20',
        'hour' => '3',
        'day' => '*',
        'dayofweek' => '*',
        'month' => '*',
        'disabled' => 0,
    ],
];
