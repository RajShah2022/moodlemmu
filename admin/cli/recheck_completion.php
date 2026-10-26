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
 * Recalculate automatic activity completion for all users.
 *
 * Deletes and recomputes course_modules_completion rows via completion_info::reset_all_state(),
 * same as the bulk edit activity completion UI. Manual completion activities are skipped.
 *
 * @package    core_completion
 * @copyright  2026 MathMeUp
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->libdir . '/completionlib.php');

[$options, $unrecognized] = cli_get_params(
    ['courseid' => 0, 'all' => false, 'modname' => '', 'run' => false, 'help' => false],
    ['h' => 'help']
);

if ($unrecognized) {
    cli_error(get_string('cliunknowoption', 'admin', implode("\n  ", $unrecognized)));
}

if ($options['help'] || (!$options['courseid'] && !$options['all'])) {
    echo "Recalculate automatic activity completion for all users.

Options:
--courseid=N      Single course
--all             All courses with completion enabled
--modname=quiz    Only this module type (optional)
--run             Actually do it (default is dry run)
-h, --help        Print out this help

Example:
\$ sudo -u www-data php admin/cli/recheck_completion.php --courseid=12 --modname=quiz --run
";
    exit(0);
}

if ($options['all']) {
    $courseids = $DB->get_fieldset_select('course', 'id', 'id <> ? AND enablecompletion = 1', [SITEID]);
} else {
    $courseids = [(int)$options['courseid']];
}

foreach ($courseids as $courseid) {
    $modinfo = get_fast_modinfo($courseid);
    $completion = new completion_info($modinfo->get_course());
    if (!$completion->is_enabled()) {
        mtrace("Course {$courseid}: completion disabled, skipping");
        continue;
    }
    foreach ($modinfo->get_cms() as $cm) {
        if ($cm->completion != COMPLETION_TRACKING_AUTOMATIC) {
            continue;
        }
        if ($options['modname'] && $cm->modname !== $options['modname']) {
            continue;
        }
        mtrace("Course {$courseid} cm {$cm->id} [{$cm->modname}] {$cm->name}" . ($options['run'] ? '' : ' (dry run)'));
        if ($options['run']) {
            $completion->reset_all_state($cm);
        }
    }
}

mtrace('Done.');
