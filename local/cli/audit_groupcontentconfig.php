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
 * Read-only audit: finds classes whose student gating has lost its teacher toggles.
 *
 * Store A (group conditions in course_modules.availability) is what students are
 * gated on. Store B (local_coursegroupmodules, keyed by cohort id in its groupid
 * column) is what the toggle form and the user grade report read. Derivation only
 * goes B -> A, so when B rows disappear students keep access while every toggle
 * reads off. This script reports each class/course pair that has quiz or supervideo
 * modules gated to its group but no matching B row.
 *
 * Writes nothing. Exits 1 when any pair is broken, so it can run from cron.
 * Repair with rebuild_groupcontentconfig.php.
 *
 * @package    local_courses
 * @copyright  2026 Skryptech
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/clilib.php');

list($options, $unrecognised) = cli_get_params([
    'help' => false,
    'cohortid' => '',
    'courseid' => '',
    'quiet' => false,
], ['h' => 'help']);

if ($unrecognised) {
    cli_error(get_string('cliunknowoption', 'core_admin', implode(PHP_EOL . '  ', $unrecognised)));
}

if ($options['help']) {
    cli_writeln(<<<EOT
Report class/course pairs whose student access has no teacher-toggle rows.

Read-only. Exit code 1 if any pair is broken, 0 otherwise.

Options:
  -h, --help              Print this help.
      --cohortid=1,2      Restrict to these cohort ids.
      --courseid=89,98    Restrict to these course ids.
      --quiet             Print only broken pairs and the summary.

Fix a broken pair with:
  php local/cli/rebuild_groupcontentconfig.php --cohortid=N --courseid=M [--execute]

Examples:
  php audit_groupcontentconfig.php
  php audit_groupcontentconfig.php --courseid=95 --quiet
EOT);
    exit(0);
}

$cohortfilter = array_filter(array_map('intval', explode(',', (string)$options['cohortid'])));
$coursefilter = array_filter(array_map('intval', explode(',', (string)$options['courseid'])));

/**
 * Collect every group id named anywhere in an availability tree.
 *
 * Walks the decoded tree rather than pattern matching the JSON: a LIKE on
 * '"id":296' also matches group 2960.
 *
 * @param mixed $node
 * @return int[]
 */
function local_courses_audit_group_ids($node): array {
    $ids = [];
    if (!is_object($node)) {
        return $ids;
    }
    if (($node->type ?? '') === 'group') {
        $ids[] = (int)$node->id;
    }
    foreach (($node->c ?? []) as $child) {
        $ids = array_merge($ids, local_courses_audit_group_ids($child));
    }
    return $ids;
}

// Cohort enrolment is the authoritative cohort -> group mapping (customint1 -> customint2).
$where = "enrol = 'cohort' AND customint2 > 0";
$params = [];
if ($coursefilter) {
    list($in, $p) = $DB->get_in_or_equal($coursefilter, SQL_PARAMS_NAMED, 'crs');
    $where .= " AND courseid $in";
    $params += $p;
}
if ($cohortfilter) {
    list($in, $p) = $DB->get_in_or_equal($cohortfilter, SQL_PARAMS_NAMED, 'coh');
    $where .= " AND customint1 $in";
    $params += $p;
}
$pairs = $DB->get_records_sql(
    "SELECT id, courseid, customint1 AS cohortid, customint2 AS groupid
       FROM {enrol} WHERE $where ORDER BY courseid, customint1", $params);

$gated = [];
$broken = 0;
$healthy = 0;
$unconfigured = 0;

foreach ($pairs as $pair) {
    if (!isset($gated[$pair->courseid])) {
        $gated[$pair->courseid] = [];
        $cms = $DB->get_records_sql(
            "SELECT cm.id, cm.availability
               FROM {course_modules} cm
               JOIN {modules} m ON m.id = cm.module
              WHERE cm.course = :course AND m.name IN ('quiz', 'supervideo')
                AND cm.deletioninprogress = 0 AND cm.availability <> ''",
            ['course' => $pair->courseid]);
        foreach ($cms as $cm) {
            $gated[$pair->courseid][$cm->id] = local_courses_audit_group_ids(json_decode($cm->availability));
        }
    }

    $rows = $DB->get_records(
        'local_coursegroupmodules',
        ['courseid' => $pair->courseid, 'groupid' => $pair->cohortid], '', 'moduleid');

    $reachable = 0;
    $missing = 0;
    foreach ($gated[$pair->courseid] as $cmid => $groups) {
        if (in_array((int)$pair->groupid, $groups, true)) {
            $reachable++;
            if (!isset($rows[$cmid])) {
                $missing++;
            }
        }
    }

    $label = sprintf('course %d cohort %d group %d', $pair->courseid, $pair->cohortid, $pair->groupid);
    if ($missing > 0) {
        $broken++;
        $kind = $missing === $reachable ? 'NO TOGGLE ROWS' : 'PARTIAL';
        cli_writeln(sprintf('BROKEN  %s: %d of %d reachable module(s) have no toggle row (%s)',
            $label, $missing, $reachable, $kind));
    } else if ($reachable === 0 && empty($rows)) {
        $unconfigured++;
    } else {
        $healthy++;
        if (empty($options['quiet'])) {
            cli_writeln(sprintf('ok      %s: %d reachable module(s), %d toggle row(s)',
                $label, $reachable, count($rows)));
        }
    }
}

cli_writeln(sprintf('%d pair(s): %d broken, %d healthy, %d unconfigured.',
    count($pairs), $broken, $healthy, $unconfigured));
if ($broken) {
    cli_writeln('Repair: php local/cli/rebuild_groupcontentconfig.php --cohortid=N --courseid=M (dry run), then --execute.');
}
exit($broken ? 1 : 0);
