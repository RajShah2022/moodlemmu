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
 * Turn on the section toggles above activities that are already enabled.
 *
 * Finds every activity a class can currently reach, and makes sure the section
 * holding it - and every section above that, up to the top of the course - has a
 * row in local_coursegrouptopics. Without those rows the content configuration
 * form has nothing to nest the activity's checkbox under, so an activity students
 * are using reads as off.
 *
 * Course formats with a hierarchy (flexsections) nest sections as
 * Number > Estimation > Rounding off > quiz. Enabling only the section that
 * directly holds the quiz is not enough; the whole chain above it has to be on.
 *
 * Writes to local_coursegrouptopics only. It never touches course_modules,
 * course_sections or any availability, so what students can reach cannot change
 * as a result of running it.
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
    'execute' => false,
    'cohortid' => '',
    'courseid' => '',
    'from-rows' => false,
    'create-coursetopics' => false,
], ['h' => 'help']);

if ($unrecognised) {
    cli_error(get_string('cliunknowoption', 'core_admin', implode(PHP_EOL . '  ', $unrecognised)));
}

if ($options['help']) {
    cli_writeln(<<<EOT
Enable the section toggles above activities that are already enabled.

For every class, finds the activities it can reach, then adds a
local_coursegrouptopics row for each section holding one and for every section
above it in the course hierarchy. Dry run unless --execute is given.

Only ever inserts section rows. Never writes availability, so student access
cannot change.

Options:
  -h, --help              Print this help.
      --execute           Actually write the rows. Omit for a dry run.
      --cohortid=1,2      Restrict to these cohort ids (the plugin's groupid).
      --courseid=89,98    Restrict to these course ids.
      --from-rows         Take the enabled activities from local_coursegroupmodules
                          instead of from what students can actually reach. Use
                          this if the config rows are trusted and availability is
                          not. The default reads availability, which is what
                          students are actually gated on.
      --create-coursetopics
                          Also create any missing local_coursetopics row. A
                          section with no row there renders no checkbox at all,
                          so the section row stays invisible without it. Off by
                          default because it widens what the course offers.

Examples:
  php fix_missing_section_toggles.php
  php fix_missing_section_toggles.php --cohortid=19
  php fix_missing_section_toggles.php --cohortid=19 --courseid=89 --execute
EOT);
    exit(0);
}

$dryrun = empty($options['execute']);
$cohortfilter = array_filter(array_map('intval', explode(',', (string)$options['cohortid'])));
$coursefilter = array_filter(array_map('intval', explode(',', (string)$options['courseid'])));
$fromrows = !empty($options['from-rows']);

/**
 * Is this availability tree restricted to any of the given groups?
 *
 * Walks the decoded tree rather than pattern matching the JSON: a LIKE on
 * '"id":296' also matches group 2960.
 *
 * @param string|null $json
 * @param int[] $groupids
 * @return bool
 */
function local_courses_availability_has_group($json, array $groupids): bool {
    if (empty($json)) {
        return false;
    }
    $tree = json_decode($json, true);
    if (!is_array($tree)) {
        return false;
    }
    return local_courses_tree_has_group($tree, $groupids);
}

/**
 * Recursive helper for local_courses_availability_has_group().
 *
 * @param mixed $node
 * @param int[] $groupids
 * @return bool
 */
function local_courses_tree_has_group($node, array $groupids): bool {
    if (!is_array($node)) {
        return false;
    }
    if (isset($node['type'], $node['id']) && $node['type'] === 'group'
            && in_array((int)$node['id'], $groupids, true)) {
        return true;
    }
    foreach ($node as $child) {
        if (local_courses_tree_has_group($child, $groupids)) {
            return true;
        }
    }
    return false;
}

/**
 * The course groups a class (cohort) is synced into.
 *
 * The cohort enrolment instance is authoritative - customint1 is the cohort and
 * customint2 the group it feeds. Falls back to cohort/group membership overlap
 * for courses enrolled some other way.
 *
 * @param int $courseid
 * @param int $cohortid
 * @return int[]
 */
function local_courses_class_groupids(int $courseid, int $cohortid): array {
    global $DB;

    $groupids = $DB->get_fieldset_select('enrol', 'DISTINCT customint2',
        'courseid = :courseid AND enrol = :enrol AND customint1 = :cohortid AND customint2 > 0',
        ['courseid' => $courseid, 'enrol' => 'cohort', 'cohortid' => $cohortid]);

    if (empty($groupids)) {
        $groupids = $DB->get_fieldset_sql("
            SELECT DISTINCT g.id
              FROM {groups} g
              JOIN {groups_members} gm ON gm.groupid = g.id
              JOIN {cohort_members} chm ON chm.userid = gm.userid
             WHERE g.courseid = :courseid AND chm.cohortid = :cohortid",
            ['courseid' => $courseid, 'cohortid' => $cohortid]);
    }

    return array_values(array_unique(array_map('intval', $groupids)));
}

/**
 * Map each section to its parent section, for formats with a hierarchy.
 *
 * flexsections keeps the tree in course_format_options under the name "parent",
 * whose value is the parent's section NUMBER, not its id. Formats without a
 * hierarchy yield an empty map and every section is treated as top level.
 *
 * @param int $courseid
 * @param array $sections Section records keyed by id, each with ->section.
 * @return array [sectionid => parent sectionid]
 */
function local_courses_section_parents(int $courseid, array $sections): array {
    global $DB;

    $numbertoid = [];
    foreach ($sections as $section) {
        $numbertoid[(int)$section->section] = (int)$section->id;
    }

    $parentof = [];
    $records = $DB->get_records('course_format_options',
        ['courseid' => $courseid, 'name' => 'parent'], '', 'id, sectionid, value');
    foreach ($records as $record) {
        $parentnumber = (int)$record->value;
        // 0 is the course root - nothing to record.
        if ($parentnumber > 0 && isset($numbertoid[$parentnumber])
                && isset($sections[(int)$record->sectionid])) {
            $parentof[(int)$record->sectionid] = $numbertoid[$parentnumber];
        }
    }

    return $parentof;
}

/**
 * The chain of sections from this one up to the top of the course.
 *
 * @param int $sectionid
 * @param array $parentof [sectionid => parent sectionid]
 * @return int[] The section itself first, then each ancestor.
 */
function local_courses_section_chain(int $sectionid, array $parentof): array {
    $chain = [$sectionid];
    $current = $sectionid;
    $guard = 0;
    // The guard stops a malformed tree from looping forever.
    while (isset($parentof[$current]) && $guard++ < 100) {
        $current = $parentof[$current];
        if (in_array($current, $chain, true)) {
            break;
        }
        $chain[] = $current;
    }
    return $chain;
}

// Every class configured in a course, plus any pair that has content rows without
// one, so nothing is missed either way.
$sql = "SELECT DISTINCT " . $DB->sql_concat('cg.courseid', "'-'", 'cg.groupid') . " AS pairkey,
               cg.courseid, cg.groupid AS cohortid
          FROM {local_coursegroups} cg
         UNION
        SELECT DISTINCT " . $DB->sql_concat('m.courseid', "'-'", 'm.groupid') . " AS pairkey,
               m.courseid, m.groupid AS cohortid
          FROM {local_coursegroupmodules} m";
$pairs = $DB->get_records_sql($sql);

// Filters and names are applied in PHP: the UNION above makes a WHERE clause on
// both arms clumsier than it is worth for a handful of rows.
foreach ($pairs as $key => $pair) {
    $pair->courseid = (int)$pair->courseid;
    $pair->cohortid = (int)$pair->cohortid;
    if ($cohortfilter && !in_array($pair->cohortid, $cohortfilter, true)) {
        unset($pairs[$key]);
        continue;
    }
    if ($coursefilter && !in_array($pair->courseid, $coursefilter, true)) {
        unset($pairs[$key]);
        continue;
    }
    $pair->cohortname = $DB->get_field('cohort', 'name', ['id' => $pair->cohortid]) ?: '?';
    $pair->courseshortname = $DB->get_field('course', 'shortname', ['id' => $pair->courseid]) ?: '?';
}

if (empty($pairs)) {
    cli_writeln('No class/course pair matched.');
    exit(0);
}

cli_heading($dryrun ? 'DRY RUN - no rows will be written' : 'EXECUTING - rows will be written');
cli_writeln(sprintf('%d class/course pair(s) to examine. Enabled activities read from %s.',
    count($pairs), $fromrows ? 'local_coursegroupmodules' : 'student availability'));
cli_writeln('');

$now = time();
$totalinserted = 0;
$pairsfixed = 0;
$pairsok = 0;
$warnings = [];

// Sort for readable output.
$ordered = array_values($pairs);
usort($ordered, function($a, $b) {
    return [$a->cohortid, $a->courseid] <=> [$b->cohortid, $b->courseid];
});

foreach ($ordered as $pair) {
    $label = sprintf('cohort %d (%s) course %d (%s)',
        $pair->cohortid, $pair->cohortname, $pair->courseid, $pair->courseshortname);

    $sections = $DB->get_records('course_sections', ['course' => $pair->courseid], 'section',
        'id, section, name, availability');
    if (empty($sections)) {
        continue;
    }
    $parentof = local_courses_section_parents($pair->courseid, $sections);

    // Which sections hold an activity this class has enabled.
    $leafsections = [];
    if ($fromrows) {
        $rows = $DB->get_recordset_sql("
            SELECT m.id, cm.section
              FROM {local_coursegroupmodules} m
              JOIN {course_modules} cm ON cm.id = m.moduleid AND cm.course = m.courseid
             WHERE m.courseid = :courseid AND m.groupid = :cohortid",
            ['courseid' => $pair->courseid, 'cohortid' => $pair->cohortid]);
        foreach ($rows as $row) {
            $leafsections[(int)$row->section] = true;
        }
        $rows->close();
    } else {
        $groupids = local_courses_class_groupids($pair->courseid, $pair->cohortid);
        if (empty($groupids)) {
            $warnings[] = "$label: no course group resolved, skipped.";
            continue;
        }
        $cms = $DB->get_records('course_modules', ['course' => $pair->courseid], '',
            'id, section, availability');
        foreach ($cms as $cm) {
            if (local_courses_availability_has_group($cm->availability, $groupids)) {
                $leafsections[(int)$cm->section] = true;
            }
        }
    }

    // Only sections that still exist.
    $leafsections = array_intersect_key($leafsections, $sections);

    if (empty($leafsections)) {
        continue;
    }

    // Sections already switched on for this class.
    $enabled = array_map('intval', $DB->get_fieldset_select('local_coursegrouptopics',
        'DISTINCT topic', 'courseid = ? AND groupid = ?', [$pair->courseid, $pair->cohortid]));

    // Walk each leaf up to the top of the course, collecting what is not on yet.
    // Recorded against the leaf it was reached from so the output explains itself.
    $missing = [];
    foreach (array_keys($leafsections) as $leafid) {
        foreach (local_courses_section_chain($leafid, $parentof) as $depth => $sectionid) {
            if (!isset($sections[$sectionid]) || in_array($sectionid, $enabled, true)) {
                continue;
            }
            if (!isset($missing[$sectionid])) {
                $missing[$sectionid] = ($depth === 0) ? 'holds activities' : 'ancestor';
            }
        }
    }

    if (empty($missing)) {
        $pairsok++;
        continue;
    }

    // The open_path to write. The pair's own content rows are the best guide;
    // local_coursegroups is the fallback. The form filters on it for non-admins,
    // so a row written under the wrong path would still render as off.
    $openpath = $DB->get_field_sql("
        SELECT open_path
          FROM {local_coursegroupmodules}
         WHERE courseid = ? AND groupid = ? AND open_path IS NOT NULL AND open_path <> ''
         ORDER BY id", [$pair->courseid, $pair->cohortid], IGNORE_MULTIPLE);
    if (empty($openpath)) {
        $openpath = $DB->get_field_sql("
            SELECT open_path
              FROM {local_coursegrouptopics}
             WHERE courseid = ? AND groupid = ? AND open_path IS NOT NULL AND open_path <> ''
             ORDER BY id", [$pair->courseid, $pair->cohortid], IGNORE_MULTIPLE);
    }
    if (empty($openpath)) {
        $openpath = $DB->get_field_sql("
            SELECT open_path
              FROM {local_coursegroups}
             WHERE courseid = ? AND groupid = ? AND open_path IS NOT NULL AND open_path <> ''
             ORDER BY id", [$pair->courseid, $pair->cohortid], IGNORE_MULTIPLE);
    }
    if (empty($openpath)) {
        $warnings[] = "$label: no open_path found on any existing row, skipped - "
            . 'a section row written without one would stay invisible in the form.';
        continue;
    }

    $usercreated = (int)($DB->get_field_sql("
        SELECT usercreated
          FROM {local_coursegroups}
         WHERE courseid = ? AND groupid = ?
         ORDER BY id", [$pair->courseid, $pair->cohortid], IGNORE_MULTIPLE) ?: 0);

    cli_writeln(sprintf('  %s', $label));
    cli_writeln(sprintf('    %d section(s) hold enabled activities, %d already on, %d to switch on, open_path %s',
        count($leafsections), count($enabled), count($missing), $openpath));

    $inserted = 0;
    $transaction = $dryrun ? null : $DB->start_delegated_transaction();

    // Shallowest first, so a parent is written before its children.
    $order = array_keys($missing);
    usort($order, function($a, $b) use ($parentof) {
        return count(local_courses_section_chain($a, $parentof))
            <=> count(local_courses_section_chain($b, $parentof));
    });

    foreach ($order as $sectionid) {
        $name = $sections[$sectionid]->name ?: ('Topic ' . $sections[$sectionid]->section);
        cli_writeln(sprintf('      %s section %-6d %-40s (%s)',
            $dryrun ? '+' : 'wrote', $sectionid, $name, $missing[$sectionid]));

        // The checkbox itself comes from local_coursetopics, filtered by the
        // viewer's open_path. Without that row the section row renders nothing.
        $hascoursetopic = $DB->record_exists('local_coursetopics',
            ['courseid' => $pair->courseid, 'topic' => $sectionid, 'open_path' => $openpath]);
        if (!$hascoursetopic) {
            if (!empty($options['create-coursetopics'])) {
                if (!$dryrun) {
                    $DB->insert_record('local_coursetopics', (object)[
                        'courseid' => $pair->courseid,
                        'topic' => $sectionid,
                        'open_path' => $openpath,
                        'timecreated' => $now,
                        'usercreated' => $usercreated,
                    ]);
                }
                cli_writeln(sprintf('        + local_coursetopics row for section %d', $sectionid));
            } else {
                $warnings[] = "$label: section $sectionid has no local_coursetopics row for "
                    . "$openpath - the checkbox will not render; rerun with --create-coursetopics.";
            }
        }

        if (!$dryrun) {
            $DB->insert_record('local_coursegrouptopics', (object)[
                'courseid' => $pair->courseid,
                'groupid' => $pair->cohortid,
                'topic' => $sectionid,
                'open_path' => $openpath,
                'timecreated' => $now,
                'usercreated' => $usercreated,
            ]);
        }
        $inserted++;
    }

    if ($transaction) {
        $transaction->allow_commit();
    }

    cli_writeln('');
    $totalinserted += $inserted;
    $pairsfixed++;
}

cli_heading('Summary');
cli_writeln(sprintf('%s %d section row(s) across %d class/course pair(s). %d pair(s) already correct.',
    $dryrun ? 'Would write' : 'Wrote', $totalinserted, $pairsfixed, $pairsok));

if ($warnings) {
    cli_writeln('');
    cli_heading('Warnings');
    foreach (array_unique($warnings) as $warning) {
        cli_writeln('  ' . $warning);
    }
}

if ($dryrun) {
    cli_writeln('');
    cli_writeln('Dry run only. Rerun with --execute to write.');
}

exit(0);
