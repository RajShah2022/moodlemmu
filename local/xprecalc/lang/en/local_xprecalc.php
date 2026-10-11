<?php
// This program is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Strings.
 *
 * @package    local_xprecalc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Level Up XP recalculation';
$string['taskrecalculate'] = 'Recalculate XP for a course';
$string['taskreconcile'] = 'Reconcile XP totals nightly';

$string['enabled'] = 'Enable nightly reconciliation';
$string['enabled_desc'] = 'When enabled, the nightly task recalculates XP totals for every XP-enabled course. This rewrites student totals and students can level down. Leave this off until a dry run has been reviewed.';

$string['dryrun'] = 'Dry run';
$string['dryrun_desc'] = 'Compute and report what would change, but write nothing. Turn this off only once the reported changes look correct.';

$string['allowincrease'] = 'Allow totals to increase';
$string['allowincrease_desc'] = 'The replay applies the current rules to all of history, so activity that happened before a rule was added earns under it retroactively, and a student with no XP record can be granted points they were never awarded. Leave this off unless you intend that.';

$string['dedup'] = 'Repeat handling';
$string['dedup_desc'] = 'What counts as a repeat of the same action when replaying history.';
$string['dedup_once'] = 'Once per activity, ever';
$string['dedup_cooldown'] = 'Once per cooldown window';
$string['dedup_raw'] = 'No de-duplication (reproduces current behaviour)';

$string['cooldown'] = 'Cooldown window';
$string['cooldown_desc'] = 'Used only when repeat handling is set to the cooldown option.';

$string['residual'] = 'Unaccounted points';
$string['residual_desc'] = 'Event replay cannot explain every stored point. Grade-based rules award the grade value itself and cannot be replayed, and manually awarded points leave no event. Preserving keeps those points; dropping discards them.';
$string['residual_preserve'] = 'Preserve them';
$string['residual_drop'] = 'Discard them';

$string['budget'] = 'Time budget per run';
$string['budget_desc'] = 'The nightly run stops starting new courses once this is spent. Courses are processed least-recently-reconciled first, so deferred courses are picked up on later runs.';

$string['privacy:metadata'] = 'The Level Up XP recalculation plugin does not store any personal data itself; it rewrites totals held by block_xp.';
