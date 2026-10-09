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
 * Rebuilds local_coursegrouptopics / local_coursegroupmodules from the group
 * availability already stored on course sections and modules.
 *
 * An unscoped delete_records() in api::insert_groupsconfig_instance() wiped a
 * cohort's content configuration across every course when the class was
 * unchecked in a single course, without clearing the availability it had
 * written. Students kept their access, the teacher's toggles all went off and
 * the user grade report reported "No content has been enabled for this class."
 *
 * The surviving availability JSON is the only faithful record of what was
 * actually enabled per class, so it is the source of truth here. This script
 * writes to the two plugin tables ONLY - it never touches course_modules or
 * course_sections, so student access cannot shift as a result of running it.
 *
 * @package    local_courses
 * @copyright  2026 Skryptech
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

list($options, $unrecognised) = cli_get_params([
    'help' => false,
    'execute' => false,
    'cohortid' => '',
    'courseid' => '',
    'create-coursetopics' => false,
    'clean-stale-sections' => false,
], ['h' => 'help']);

if ($unrecognised) {
    cli_error(get_string('cliunknowoption', 'core_admin', implode(PHP_EOL . '  ', $unrecognised)));
}

if ($options['help']) {
    cli_writeln(<<<EOT
Rebuild per-class content configuration from live group availability.

Reports every class/course pair that has modules restricted to the class's
group but no rows in local_coursegroupmodules, and recreates those rows.
Dry run unless --execute is given. Never writes to course_modules.

Options:
  -h, --help              Print this help.
      --execute           Actually write the rows. Omit for a dry run.
      --cohortid=1,2      Restrict to these cohort ids (the plugin's groupid).
      --courseid=89,98    Restrict to these course ids.
      --create-coursetopics
                          Also create any missing local_coursetopics row. A
                          section with no row there renders no checkbox at all,
                          so the rebuilt rows stay invisible without it. Off by
                          default because it widens what the course offers.
      --clean-stale-sections
                          Second pass: strip group conditions from sections that
                          name a class but have no topic row for it. These are
                          leftovers from the availability clean-up that used to
                          skip its write when the last condition went, and they
                          leave empty topics on show to students.

Examples:
  php rebuild_groupcontentconfig.php --cohortid=19
  php rebuild_groupcontentconfig.php --cohortid=19 --courseid=89 --execute
EOT);
    exit(0);
}

$dryrun = empty($options['execute']);
$cohortfilter = array_filter(array_map('intval', explode(',', (string)$options['cohortid'])));
$coursefilter = array_filter(array_map('intval', explode(',', (string)$options['courseid'])));

/**
 * Is this availability tree restricted to any of the given groups?
 *
 * Walks the whole tree rather than pattern matching the JSON: a LIKE on
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
 * The course groups a cohort is synced into.
 *
 * The cohort enrolment instance is authoritative - customint1 is the cohort and
 * customint2 the group it feeds, which is the pairing update_section_availability()
 * itself relies on. Falls back to cohort/group membership overlap for courses
 * enrolled some other way.
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

// Every class configured in a course that has lost its module rows there.
$sql = "SELECT cg.id, cg.courseid, cg.groupid AS cohortid, cg.open_path, cg.usercreated,
               c.name AS cohortname, co.shortname AS courseshortname
          FROM {local_coursegroups} cg
          JOIN {cohort} c ON c.id = cg.groupid
          JOIN {course} co ON co.id = cg.courseid
         WHERE NOT EXISTS (SELECT 1
                             FROM {local_coursegroupmodules} m
                            WHERE m.courseid = cg.courseid AND m.groupid = cg.groupid)";
$params = [];
if ($cohortfilter) {
    list($insql, $inparams) = $DB->get_in_or_equal($cohortfilter, SQL_PARAMS_NAMED, 'coh');
    $sql .= " AND cg.groupid $insql";
    $params += $inparams;
}
if ($coursefilter) {
    list($insql, $inparams) = $DB->get_in_or_equal($coursefilter, SQL_PARAMS_NAMED, 'crs');
    $sql .= " AND cg.courseid $insql";
    $params += $inparams;
}
$sql .= " ORDER BY cg.groupid, cg.courseid";

$pairs = $DB->get_records_sql($sql, $params);

cli_heading($dryrun ? 'DRY RUN - no rows will be written' : 'EXECUTING - rows will be written');

if (empty($pairs)) {
    cli_writeln('No class/course pair is missing its module rows.');
} else {
    cli_writeln(sprintf('%d class/course pair(s) to examine.', count($pairs)));
}
cli_writeln('');

$now = time();
$totaltopics = 0;
$totalmodules = 0;
$totalskipped = 0;
$warnings = [];

foreach ($pairs as $pair) {
    $groupids = local_courses_class_groupids($pair->courseid, $pair->cohortid);

    $label = sprintf('cohort %d (%s) in course %d (%s)',
        $pair->cohortid, $pair->cohortname, $pair->courseid, $pair->courseshortname);

    if (empty($groupids)) {
        $warnings[] = "$label: no course group resolved, skipped.";
        continue;
    }

    if (empty($pair->open_path)) {
        $warnings[] = "$label: local_coursegroups row has an empty open_path, skipped - "
            . 'the form filters on it, so rows written without one stay invisible.';
        continue;
    }

    // Modules students can currently reach because of this class's group.
    $cms = $DB->get_records('course_modules', ['course' => $pair->courseid], '', 'id, section, availability');

    $matchedcms = [];
    foreach ($cms as $cm) {
        if (local_courses_availability_has_group($cm->availability, $groupids)) {
            $matchedcms[$cm->id] = (int)$cm->section;
        }
    }

    // Topic rows are derived from the sections holding an enabled module, NOT from
    // section availability. Section conditions are stale: update_deleted_section_availability()
    // leaks the same way the module one does, so a section disabled long ago still
    // names the group. On the reference data 47 sections carried the condition while
    // only 6 held an enabled module. Deriving from modules can only under-restore,
    // which is recoverable by ticking a box; over-restoring silently re-enables
    // topics somebody deliberately turned off.
    $sections = $DB->get_records('course_sections', ['course' => $pair->courseid], '', 'id, section, availability');
    $matchedsections = [];
    foreach ($matchedcms as $sectionid) {
        if (isset($sections[$sectionid])) {
            $matchedsections[$sectionid] = true;
        }
    }

    // A topic enabled with no activity inside it leaves nothing to key off, so it
    // cannot be restored automatically - report it rather than guessing.
    $staleonly = [];
    foreach ($sections as $section) {
        if (!isset($matchedsections[(int)$section->id])
                && local_courses_availability_has_group($section->availability, $groupids)) {
            $staleonly[] = (int)$section->id;
        }
    }

    if (empty($matchedsections) && empty($matchedcms)) {
        cli_writeln(sprintf('  %-60s nothing restricted to group(s) %s - never configured, skipping.',
            $label, implode(',', $groupids)));
        continue;
    }

    cli_writeln(sprintf('  %s', $label));
    cli_writeln(sprintf('    group(s) %s, open_path %s', implode(',', $groupids), $pair->open_path));
    cli_writeln(sprintf('    %d section(s), %d module(s) to restore',
        count($matchedsections), count($matchedcms)));
    if ($staleonly) {
        cli_writeln(sprintf('    %d section(s) carry the group condition but hold no enabled '
            . 'module - not restored (stale, or a topic enabled with no activities): %s',
            count($staleonly), implode(',', array_slice($staleonly, 0, 20))));
    }

    $usercreated = !empty($pair->usercreated) ? (int)$pair->usercreated : 0;
    $topicswritten = 0;
    $moduleswritten = 0;
    $skipped = 0;

    $transaction = $dryrun ? null : $DB->start_delegated_transaction();

    foreach (array_keys($matchedsections) as $sectionid) {
        // The checkbox itself comes from local_coursetopics, filtered by the
        // viewer's open_path. Without that row the rebuilt group row renders
        // nothing at all, so flag it.
        $hascoursetopic = $DB->record_exists('local_coursetopics',
            ['courseid' => $pair->courseid, 'topic' => $sectionid, 'open_path' => $pair->open_path]);
        if (!$hascoursetopic) {
            if (!empty($options['create-coursetopics'])) {
                if (!$dryrun) {
                    $DB->insert_record('local_coursetopics', (object)[
                        'courseid' => $pair->courseid,
                        'topic' => $sectionid,
                        'open_path' => $pair->open_path,
                        'timecreated' => $now,
                        'usercreated' => $usercreated,
                    ]);
                }
                cli_writeln(sprintf('      + local_coursetopics section %d', $sectionid));
            } else {
                $warnings[] = "$label: section $sectionid has no local_coursetopics row for "
                    . "{$pair->open_path} - toggle will not render; rerun with --create-coursetopics.";
            }
        }

        $exists = $DB->record_exists('local_coursegrouptopics', [
            'courseid' => $pair->courseid,
            'groupid' => $pair->cohortid,
            'topic' => $sectionid,
            'open_path' => $pair->open_path,
        ]);
        if ($exists) {
            $skipped++;
            continue;
        }
        if (!$dryrun) {
            $DB->insert_record('local_coursegrouptopics', (object)[
                'courseid' => $pair->courseid,
                'groupid' => $pair->cohortid,
                'topic' => $sectionid,
                'open_path' => $pair->open_path,
                'timecreated' => $now,
                'usercreated' => $usercreated,
            ]);
        }
        $topicswritten++;
    }

    foreach ($matchedcms as $cmid => $sectionid) {
        $exists = $DB->record_exists('local_coursegroupmodules', [
            'courseid' => $pair->courseid,
            'groupid' => $pair->cohortid,
            'moduleid' => $cmid,
            'open_path' => $pair->open_path,
        ]);
        if ($exists) {
            $skipped++;
            continue;
        }
        if (!$dryrun) {
            // topicid is the module's real section. The save path derives it from
            // whichever section_* key the loop happened to see last, which is how
            // rows end up filed under a foreign section.
            $DB->insert_record('local_coursegroupmodules', (object)[
                'courseid' => $pair->courseid,
                'groupid' => $pair->cohortid,
                'topicid' => $sectionid,
                'moduleid' => $cmid,
                'open_path' => $pair->open_path,
                'timecreated' => $now,
                'usercreated' => $usercreated,
            ]);
        }
        $moduleswritten++;
    }

    if ($transaction) {
        $transaction->allow_commit();
    }

    cli_writeln(sprintf('    %s %d topic row(s), %d module row(s), %d already present',
        $dryrun ? 'would write' : 'wrote', $topicswritten, $moduleswritten, $skipped));
    cli_writeln('');

    $totaltopics += $topicswritten;
    $totalmodules += $moduleswritten;
    $totalskipped += $skipped;
}

cli_heading('Summary');
cli_writeln(sprintf('%s %d topic row(s) and %d module row(s). %d row(s) already present.',
    $dryrun ? 'Would write' : 'Wrote', $totaltopics, $totalmodules, $totalskipped));

if ($warnings) {
    cli_writeln('');
    cli_heading('Warnings');
    foreach (array_unique($warnings) as $warning) {
        cli_writeln('  ' . $warning);
    }
}

// ---------------------------------------------------------------------------
// Second pass: sections still naming a class that has no topic row for them.
// ---------------------------------------------------------------------------
if (!empty($options['clean-stale-sections'])) {
    cli_writeln('');
    cli_heading('Stale section conditions');

    $sql = "SELECT cg.id, cg.courseid, cg.groupid AS cohortid, c.name AS cohortname,
                   co.shortname AS courseshortname
              FROM {local_coursegroups} cg
              JOIN {cohort} c ON c.id = cg.groupid
              JOIN {course} co ON co.id = cg.courseid";
    $where = [];
    $params = [];
    if ($cohortfilter) {
        list($insql, $inparams) = $DB->get_in_or_equal($cohortfilter, SQL_PARAMS_NAMED, 'scoh');
        $where[] = "cg.groupid $insql";
        $params += $inparams;
    }
    if ($coursefilter) {
        list($insql, $inparams) = $DB->get_in_or_equal($coursefilter, SQL_PARAMS_NAMED, 'scrs');
        $where[] = "cg.courseid $insql";
        $params += $inparams;
    }
    if ($where) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }
    $sql .= ' ORDER BY cg.groupid, cg.courseid';

    $stalecleared = 0;
    $staleskipped = 0;

    foreach ($DB->get_records_sql($sql, $params) as $pair) {
        $groupids = local_courses_class_groupids($pair->courseid, $pair->cohortid);
        if (empty($groupids)) {
            continue;
        }

        $enabled = array_map('intval', $DB->get_fieldset_select('local_coursegrouptopics',
            'DISTINCT topic', 'courseid = ? AND groupid = ?', [$pair->courseid, $pair->cohortid]));

        $sections = $DB->get_records('course_sections', ['course' => $pair->courseid], '', 'id, availability');
        foreach ($sections as $section) {
            $sectionid = (int)$section->id;
            if (in_array($sectionid, $enabled, true)) {
                continue;
            }
            if (!local_courses_availability_has_group($section->availability, $groupids)) {
                continue;
            }
            // An activity row filed under this section means the data disagrees with
            // itself. Clearing could hide content somebody still has enabled, so leave
            // it and say so.
            if ($DB->record_exists('local_coursegroupmodules',
                    ['courseid' => $pair->courseid, 'topicid' => $sectionid])) {
                cli_writeln(sprintf('  course %d cohort %d section %d: has activity rows, skipped',
                    $pair->courseid, $pair->cohortid, $sectionid));
                $staleskipped++;
                continue;
            }

            cli_writeln(sprintf('  %s course %d (%s) cohort %d (%s) section %d',
                $dryrun ? 'would clear' : 'cleared',
                $pair->courseid, $pair->courseshortname, $pair->cohortid, $pair->cohortname, $sectionid));

            if (!$dryrun) {
                \local_courses\api::update_deleted_section_availability(
                    $pair->courseid, $sectionid, $pair->cohortid);
            }
            $stalecleared++;
        }
    }

    cli_writeln('');
    cli_writeln(sprintf('%s %d stale section condition(s). %d skipped.',
        $dryrun ? 'Would clear' : 'Cleared', $stalecleared, $staleskipped));
}

if ($dryrun) {
    cli_writeln('');
    cli_writeln('Dry run only. Rerun with --execute to write.');
}

exit(0);
