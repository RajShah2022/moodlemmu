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
 * Copy a course with all its user data from one site (source, e.g. prod) into an
 * existing course on another site (target).
 *
 * Source run: makes a full backup with user data (attempts, grades, completion, logs,
 * groups, enrolments) plus the things a course backup does not carry: cohorts,
 * cohort enrolment methods and mod_supervideo watch progress.
 *
 * Target run: wipes the existing course, restores the backup into it, then
 * re-creates cohorts/members, cohort enrolment methods and supervideo watch progress.
 *
 * @package    core
 * @subpackage cli
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');
require_once($CFG->dirroot . '/cohort/lib.php');

[$options, $unrecognized] = cli_get_params([
    'courseid' => '',
    'target' => false,
    'dir' => '',
    'keep-target-settings' => false,
    'skip-restore' => false,
    'yes' => false,
    'help' => false,
], [
    'c' => 'courseid',
    't' => 'target',
    'd' => 'dir',
    'y' => 'yes',
    'h' => 'help',
]);

if ($unrecognized) {
    cli_error(get_string('cliunknowoption', 'admin', implode("\n  ", $unrecognized)));
}

$help = <<<EOL
Copy a course with all user data from a source site (prod) into an existing course on a target site.

Options:
-c, --courseid=INT          Course ID on THIS site. On the source: the course to export.
                            On the target: the existing course that will be overwritten.
-t, --target                This is the TARGET site (restore/import). Without it, this
                            run is treated as the SOURCE site (export only, read-only).
-d, --dir=PATH              Source: directory where the export folder is created.
                            Target: the export folder copied over from the source.
    --keep-target-settings  Target only: keep this course's own settings (name, format,
                            dates...) instead of taking them from the source.
    --skip-restore          Target only: do not restore the course, only re-import cohorts,
                            cohort enrolments and supervideo progress (for re-runs).
-y, --yes                   Target only: do not ask for confirmation.
-h, --help                  Print this help.

Examples:
  # 1. On PROD (source):
  sudo -u www-data php admin/cli/course_sync.php --courseid=12 --dir=/tmp
  # 2. Copy the printed folder to the target server (scp -r ...)
  # 3. On TARGET:
  sudo -u www-data php admin/cli/course_sync.php --target --courseid=34 --dir=/tmp/course_12_20261007_101500

EOL;

if ($options['help'] || !$options['courseid'] || !$options['dir']) {
    echo $help;
    exit($options['help'] ? 0 : 1);
}

$courseid = (int)$options['courseid'];
if ($courseid <= 0 || $courseid == SITEID || !$DB->record_exists('course', ['id' => $courseid])) {
    cli_error("Course id {$courseid} does not exist on this site ({$CFG->wwwroot}).");
}

$admin = get_admin();
if (!$admin) {
    cli_error('No admin account found.');
}
\core\session\manager::set_user($admin);

if ($options['target']) {
    cs_import($courseid, rtrim($options['dir'], '/'), $options, $admin);
} else {
    cs_export($courseid, rtrim($options['dir'], '/'), $admin);
}
exit(0);

// -------------------------------------------------------------------------------------------------
// Source (export).
// -------------------------------------------------------------------------------------------------

/**
 * Export the course backup and the extra data into a new folder under $dir.
 *
 * @param int $courseid
 * @param string $dir
 * @param stdClass $admin
 */
function cs_export(int $courseid, string $dir, stdClass $admin): void {
    global $CFG;

    $course = get_course($courseid);
    if (!is_dir($dir) || !is_writable($dir)) {
        cli_error("Directory {$dir} does not exist or is not writable.");
    }
    $outdir = $dir . '/course_' . $courseid . '_' . date('Ymd_His');
    check_dir_exists($outdir, true, true);

    cli_heading("Backing up course {$courseid} ({$course->shortname}) with all user data");
    $bc = new backup_controller(backup::TYPE_1COURSE, $courseid, backup::FORMAT_MOODLE,
        backup::INTERACTIVE_YES, backup::MODE_GENERAL, $admin->id);
    // Order matters: 'users' first so the settings depending on it get unlocked.
    cs_force_settings($bc->get_plan(), [
        'users' => 1,
        'anonymize' => 0,
        'role_assignments' => 1,
        'permissions' => 1,
        'activities' => 1,
        'blocks' => 1,
        'files' => 1,
        'filters' => 1,
        'comments' => 1,
        'badges' => 1,
        'calendarevents' => 1,
        'userscompletion' => 1,
        'logs' => 1,
        'grade_histories' => 1,
        'questionbank' => 1,
        'groups' => 1,
        'competencies' => 1,
        'customfield' => 1,
        'contentbankcontent' => 1,
        'xapistate' => 1,
        'legacyfiles' => 1,
    ]);
    $bc->get_plan()->get_setting('filename')->set_value('course.mbz');
    $bc->finish_ui();
    $bc->execute_plan();
    $results = $bc->get_results();
    $file = $results['backup_destination'] ?? null;
    if (!$file) {
        $bc->destroy();
        cli_error('Backup finished but no backup file was produced.');
    }
    if (!$file->copy_content_to($outdir . '/course.mbz')) {
        $bc->destroy();
        cli_error("Could not write {$outdir}/course.mbz");
    }
    $file->delete();
    $bc->destroy();
    mtrace("Backup written to {$outdir}/course.mbz");

    cli_heading('Exporting data not included in course backups');
    $extras = [
        'manifest' => [
            'wwwroot' => $CFG->wwwroot,
            'siteidentifier' => get_site_identifier(),
            'release' => $CFG->release,
            'courseid' => $courseid,
            'shortname' => $course->shortname,
            'fullname' => $course->fullname,
            'exportedat' => time(),
        ],
        'cohorts' => cs_export_cohorts($courseid),
        'cohort_enrols' => cs_export_cohort_enrols($courseid),
        'supervideo_views' => cs_export_supervideo_views($courseid),
    ];
    $json = json_encode($extras, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false || file_put_contents($outdir . '/extras.json', $json) === false) {
        cli_error("Could not write {$outdir}/extras.json");
    }
    mtrace('Cohorts: ' . count($extras['cohorts']) .
        ', cohort enrolment methods: ' . count($extras['cohort_enrols']) .
        ', supervideo view rows: ' . count($extras['supervideo_views']));

    cli_heading('Done');
    mtrace("Export folder: {$outdir}");
    mtrace("Copy this folder to the target server, then run there:");
    mtrace("  sudo -u www-data php admin/cli/course_sync.php --target --courseid=<TARGET_COURSE_ID> --dir=<copied folder>");
}

/**
 * Cohorts that have a member enrolled in the course, or are used by a cohort enrolment in it.
 *
 * @param int $courseid
 * @return array
 */
function cs_export_cohorts(int $courseid): array {
    global $DB;

    $ids = $DB->get_fieldset_sql(
        "SELECT DISTINCT cm.cohortid
           FROM {cohort_members} cm
           JOIN {user_enrolments} ue ON ue.userid = cm.userid
           JOIN {enrol} e ON e.id = ue.enrolid
          WHERE e.courseid = ?", [$courseid]);
    $ids = array_merge($ids, $DB->get_fieldset_select('enrol', 'customint1',
        "enrol = 'cohort' AND courseid = ?", [$courseid]));
    $ids = array_unique(array_filter(array_map('intval', $ids)));
    if (!$ids) {
        return [];
    }

    $out = [];
    foreach ($DB->get_records_list('cohort', 'id', $ids, 'id') as $cohort) {
        $context = context::instance_by_id($cohort->contextid, IGNORE_MISSING);
        $categoryidnumber = '';
        if ($context && $context->contextlevel == CONTEXT_COURSECAT) {
            $categoryidnumber = (string)$DB->get_field('course_categories', 'idnumber', ['id' => $context->instanceid]);
        }
        $out[] = [
            'name' => $cohort->name,
            'idnumber' => (string)$cohort->idnumber,
            'description' => (string)$cohort->description,
            'descriptionformat' => (int)$cohort->descriptionformat,
            'visible' => (int)$cohort->visible,
            'categoryidnumber' => $categoryidnumber,
            'members' => array_values($DB->get_fieldset_sql(
                "SELECT u.username
                   FROM {cohort_members} cm
                   JOIN {user} u ON u.id = cm.userid
                  WHERE cm.cohortid = ? AND u.deleted = 0", [$cohort->id])),
        ];
    }
    return $out;
}

/**
 * Cohort enrolment methods of the course. A restore on another site drops these.
 *
 * @param int $courseid
 * @return array
 */
function cs_export_cohort_enrols(int $courseid): array {
    global $DB;

    $out = [];
    foreach ($DB->get_records('enrol', ['enrol' => 'cohort', 'courseid' => $courseid], 'sortorder') as $e) {
        $cohort = $DB->get_record('cohort', ['id' => $e->customint1]);
        if (!$cohort) {
            continue;
        }
        $out[] = [
            'name' => (string)$e->name,
            'status' => (int)$e->status,
            'cohortname' => $cohort->name,
            'cohortidnumber' => (string)$cohort->idnumber,
            'role' => (string)$DB->get_field('role', 'shortname', ['id' => $e->roleid]),
            'groupname' => $e->customint2 > 0 ? (string)$DB->get_field('groups', 'name', ['id' => $e->customint2]) : '',
        ];
    }
    return $out;
}

/**
 * Supervideo watch progress of the course, keyed by video and username instead of ids.
 *
 * @param int $courseid
 * @return array
 */
function cs_export_supervideo_views(int $courseid): array {
    global $DB;

    if (!$DB->get_manager()->table_exists('supervideo_view')) {
        return [];
    }
    $keys = cs_supervideo_keys($courseid);
    if (!$keys) {
        return [];
    }
    $videobycm = array_flip($keys);

    [$insql, $params] = $DB->get_in_or_equal(array_values($keys));
    $rs = $DB->get_recordset_sql(
        "SELECT v.*, u.username
           FROM {supervideo_view} v
           JOIN {user} u ON u.id = v.user_id
          WHERE v.cm_id $insql
       ORDER BY v.id", $params);
    $out = [];
    foreach ($rs as $v) {
        $out[] = [
            'video' => $videobycm[$v->cm_id],
            'username' => $v->username,
            'currenttime' => $v->currenttime,
            'duration' => $v->duration,
            'percent' => $v->percent,
            'mapa' => $v->mapa,
            'timecreated' => $v->timecreated,
            'timemodified' => $v->timemodified,
        ];
    }
    $rs->close();
    return $out;
}

// -------------------------------------------------------------------------------------------------
// Target (import).
// -------------------------------------------------------------------------------------------------

/**
 * Restore the export over the existing course and re-import the extra data.
 *
 * @param int $courseid
 * @param string $dir
 * @param array $options
 * @param stdClass $admin
 */
function cs_import(int $courseid, string $dir, array $options, stdClass $admin): void {
    global $CFG;

    $course = get_course($courseid);
    $mbz = $dir . '/course.mbz';
    $extrasfile = $dir . '/extras.json';
    if (!is_readable($extrasfile) || (!$options['skip-restore'] && !is_readable($mbz))) {
        cli_error("{$dir} must contain course.mbz and extras.json produced by the source run.");
    }
    $extras = json_decode(file_get_contents($extrasfile), true);
    if (!is_array($extras) || empty($extras['manifest'])) {
        cli_error("{$extrasfile} is not a valid export.");
    }
    $m = $extras['manifest'];

    // Never let the target run overwrite the site the data came from.
    if (rtrim($m['wwwroot'], '/') === rtrim($CFG->wwwroot, '/')) {
        cli_error("This export was made on this same site ({$CFG->wwwroot}). Refusing to overwrite the source course. " .
            "Did you mean to run without --target?");
    }

    cli_heading('About to overwrite a course');
    mtrace("Source : {$m['wwwroot']}  course {$m['courseid']} [{$m['shortname']}] {$m['fullname']}");
    mtrace("         exported " . userdate($m['exportedat']));
    mtrace("Target : {$CFG->wwwroot}  course {$course->id} [{$course->shortname}] {$course->fullname}");
    if ($options['skip-restore']) {
        mtrace("Mode   : --skip-restore, only cohorts, cohort enrolments and supervideo progress are imported.");
    } else {
        mtrace("ALL content, enrolments, groups, attempts and grades of the target course will be DELETED and");
        mtrace("replaced with the source data. Activity (course module) ids will change.");
    }
    if ($m['shortname'] !== $course->shortname) {
        mtrace("WARNING: source and target course shortnames differ.");
    }
    if (empty($CFG->noemailever)) {
        mtrace('WARNING: $CFG->noemailever is not set; real users restored here may receive emails.');
    }
    if (!$options['yes']) {
        $answer = cli_input("Type the target course shortname ({$course->shortname}) to continue");
        if ($answer !== $course->shortname) {
            cli_error('Aborted.');
        }
    }

    if (!$options['skip-restore']) {
        cs_restore($courseid, $mbz, !$options['keep-target-settings'], $admin);
    }
    rebuild_course_cache($courseid, true);

    cs_import_cohorts($extras['cohorts'] ?? []);
    cs_import_cohort_enrols($courseid, $extras['cohort_enrols'] ?? []);
    cs_import_supervideo_views($courseid, $extras['supervideo_views'] ?? []);

    cli_heading('Done');
    mtrace("Course {$courseid} now holds the data exported from {$m['wwwroot']} on " . userdate($m['exportedat']) . '.');
}

/**
 * Delete the course content and restore the backup into it.
 *
 * @param int $courseid
 * @param string $mbz
 * @param bool $overwriteconf
 * @param stdClass $admin
 */
function cs_restore(int $courseid, string $mbz, bool $overwriteconf, stdClass $admin): void {
    cli_heading('Extracting backup');
    $tempname = restore_controller::get_tempdir_name($courseid, $admin->id);
    $path = make_backup_temp_directory($tempname);
    if (!get_file_packer('application/vnd.moodle.backup')->extract_to_pathname($mbz, $path)) {
        fulldelete($path);
        cli_error("Could not extract {$mbz}");
    }

    try {
        $rc = new restore_controller($tempname, $courseid, backup::INTERACTIVE_YES,
            backup::MODE_GENERAL, $admin->id, backup::TARGET_EXISTING_DELETING);
        cs_force_settings($rc->get_plan(), [
            'users' => 1,
            'enrolments' => backup::ENROL_WITHUSERS,
            'role_assignments' => 1,
            'permissions' => 1,
            'activities' => 1,
            'blocks' => 1,
            'filters' => 1,
            'comments' => 1,
            'badges' => 1,
            'calendarevents' => 1,
            'userscompletion' => 1,
            'logs' => 1,
            'grade_histories' => 1,
            'groups' => 1,
            'competencies' => 1,
            'customfield' => 1,
            'contentbankcontent' => 1,
            'xapistate' => 1,
            'legacyfiles' => 1,
            'overwrite_conf' => $overwriteconf ? 1 : 0,
            'keep_roles_and_enrolments' => 0,
            'keep_groups_and_groupings' => 0,
        ]);
        $rc->finish_ui();

        cli_heading('Checking backup');
        $rc->execute_precheck();
        $precheck = $rc->get_precheck_results();
        foreach ($precheck['warnings'] ?? [] as $warning) {
            mtrace("Warning: {$warning}");
        }
        if (!empty($precheck['errors'])) {
            foreach ($precheck['errors'] as $error) {
                mtrace("Error: {$error}");
            }
            $rc->destroy();
            cli_error('Precheck failed, nothing was changed on this site.');
        }

        cli_heading('Deleting target course content and restoring (this can take a while)');
        $rc->execute_plan();
        $rc->destroy();
        mtrace('Restore completed.');
    } finally {
        fulldelete($path);
    }
}

/**
 * Create missing cohorts and add missing members. Never removes members.
 *
 * @param array $cohorts
 */
function cs_import_cohorts(array $cohorts): void {
    global $DB;

    cli_heading('Importing cohorts');
    foreach ($cohorts as $c) {
        $cohort = cs_find_cohort($c['idnumber'], $c['name']);
        if (!$cohort) {
            $contextid = context_system::instance()->id;
            if ($c['categoryidnumber'] !== '' &&
                    ($catid = $DB->get_field('course_categories', 'id', ['idnumber' => $c['categoryidnumber']]))) {
                $contextid = context_coursecat::instance($catid)->id;
            }
            $id = cohort_add_cohort((object)[
                'contextid' => $contextid,
                'name' => $c['name'],
                'idnumber' => $c['idnumber'],
                'description' => $c['description'],
                'descriptionformat' => $c['descriptionformat'],
                'visible' => $c['visible'],
            ]);
            $cohort = $DB->get_record('cohort', ['id' => $id], '*', MUST_EXIST);
            mtrace("Created cohort '{$c['name']}'");
        }

        $userids = cs_userids_by_username($c['members']);
        $existing = array_flip($DB->get_fieldset_select('cohort_members', 'userid', 'cohortid = ?', [$cohort->id]));
        $added = 0;
        foreach ($userids as $userid) {
            if (!isset($existing[$userid])) {
                cohort_add_member($cohort->id, $userid);
                $added++;
            }
        }
        $missing = count($c['members']) - count($userids);
        mtrace("Cohort '{$c['name']}': {$added} members added" .
            ($missing ? ", {$missing} members skipped (user does not exist on this site)" : ''));
    }
    if (!$cohorts) {
        mtrace('No cohorts in export.');
    }
}

/**
 * Re-create the cohort enrolment methods (dropped by a cross-site restore) and sync them.
 *
 * @param int $courseid
 * @param array $enrols
 */
function cs_import_cohort_enrols(int $courseid, array $enrols): void {
    global $CFG, $DB;

    cli_heading('Importing cohort enrolment methods');
    if (!$enrols) {
        mtrace('No cohort enrolment methods in export.');
        return;
    }
    if (!enrol_is_enabled('cohort')) {
        mtrace('WARNING: cohort sync enrolment plugin is disabled on this site, skipping ' . count($enrols) . ' methods.');
        return;
    }
    $plugin = enrol_get_plugin('cohort');
    $course = get_course($courseid);

    foreach ($enrols as $e) {
        $cohort = cs_find_cohort($e['cohortidnumber'], $e['cohortname']);
        $roleid = $DB->get_field('role', 'id', ['shortname' => $e['role']]);
        if (!$cohort || !$roleid) {
            mtrace("Skipped cohort enrolment for '{$e['cohortname']}': cohort or role '{$e['role']}' not found.");
            continue;
        }
        if ($DB->record_exists('enrol', ['enrol' => 'cohort', 'courseid' => $courseid,
                'customint1' => $cohort->id, 'roleid' => $roleid])) {
            mtrace("Cohort enrolment for '{$e['cohortname']}' already exists.");
            continue;
        }
        $groupid = 0;
        if ($e['groupname'] !== '') {
            $groupid = (int)$DB->get_field('groups', 'id', ['courseid' => $courseid, 'name' => $e['groupname']]);
        }
        $plugin->add_instance($course, [
            'name' => $e['name'],
            'status' => $e['status'],
            'customint1' => $cohort->id,
            'roleid' => $roleid,
            'customint2' => $groupid,
        ]);
        mtrace("Added cohort enrolment for '{$e['cohortname']}' as {$e['role']}" .
            ($groupid ? " into group '{$e['groupname']}'" : ''));
    }

    require_once($CFG->dirroot . '/enrol/cohort/locallib.php');
    enrol_cohort_sync(new text_progress_trace(), $courseid);
}

/**
 * Replace the supervideo watch progress of the course with the exported rows.
 *
 * @param int $courseid
 * @param array $views
 */
function cs_import_supervideo_views(int $courseid, array $views): void {
    global $DB;

    cli_heading('Importing supervideo watch progress');
    if (!$DB->get_manager()->table_exists('supervideo_view')) {
        mtrace('mod_supervideo is not installed on this site, skipping.');
        return;
    }
    $keys = cs_supervideo_keys($courseid);
    if (!$keys) {
        mtrace('No supervideo activities in the target course, skipping ' . count($views) . ' rows.');
        return;
    }

    $userids = cs_userids_by_username(array_unique(array_column($views, 'username')));
    $rows = [];
    $missingvideos = [];
    $missingusers = 0;
    foreach ($views as $v) {
        if (!isset($keys[$v['video']])) {
            $missingvideos[$v['video']] = true;
            continue;
        }
        if (!isset($userids[$v['username']])) {
            $missingusers++;
            continue;
        }
        $rows[] = (object)[
            'cm_id' => $keys[$v['video']],
            'user_id' => $userids[$v['username']],
            'currenttime' => $v['currenttime'],
            'duration' => $v['duration'],
            'percent' => $v['percent'],
            'mapa' => $v['mapa'],
            'timecreated' => $v['timecreated'],
            'timemodified' => $v['timemodified'],
        ];
    }

    $transaction = $DB->start_delegated_transaction();
    [$insql, $params] = $DB->get_in_or_equal(array_values($keys));
    $DB->delete_records_select('supervideo_view', "cm_id $insql", $params);
    $DB->insert_records('supervideo_view', $rows);
    $transaction->allow_commit();

    mtrace(count($rows) . ' view rows imported.');
    if ($missingusers) {
        mtrace("{$missingusers} rows skipped: user does not exist on this site.");
    }
    foreach (array_keys($missingvideos) as $video) {
        mtrace("Rows skipped: no matching video '{$video}' in the target course.");
    }
}

// -------------------------------------------------------------------------------------------------
// Helpers.
// -------------------------------------------------------------------------------------------------

/**
 * Unlock and set backup/restore plan settings, skipping ones this site does not have.
 *
 * @param base_plan $plan
 * @param array $values setting name => value
 */
function cs_force_settings(base_plan $plan, array $values): void {
    foreach ($values as $name => $value) {
        if (!$plan->setting_exists($name)) {
            continue;
        }
        $setting = $plan->get_setting($name);
        if ($setting->get_status() != base_setting::NOT_LOCKED) {
            $setting->set_status(base_setting::NOT_LOCKED);
        }
        $setting->set_value($value);
    }
}

/**
 * Site-independent keys for the supervideo activities of a course: "<name>#<n>", where n
 * counts activities with the same name in course order.
 *
 * @param int $courseid
 * @return array key => cmid
 */
function cs_supervideo_keys(int $courseid): array {
    $modinfo = get_fast_modinfo($courseid);
    $keys = [];
    $seen = [];
    foreach ($modinfo->get_sections() as $cmids) {
        foreach ($cmids as $cmid) {
            $cm = $modinfo->get_cm($cmid);
            if ($cm->modname !== 'supervideo') {
                continue;
            }
            $seen[$cm->name] = ($seen[$cm->name] ?? 0) + 1;
            $keys[$cm->name . '#' . $seen[$cm->name]] = (int)$cm->id;
        }
    }
    return $keys;
}

/**
 * Find a cohort by idnumber, falling back to name.
 *
 * @param string $idnumber
 * @param string $name
 * @return stdClass|null
 */
function cs_find_cohort(string $idnumber, string $name): ?stdClass {
    global $DB;

    $field = $idnumber !== '' ? 'idnumber' : 'name';
    $matches = $DB->get_records('cohort', [$field => $idnumber !== '' ? $idnumber : $name], 'id');
    if (count($matches) > 1) {
        mtrace("WARNING: several cohorts match {$field} '" . ($idnumber ?: $name) . "', using the oldest.");
    }
    return $matches ? reset($matches) : null;
}

/**
 * Map usernames to local user ids (local, non-deleted accounts only).
 *
 * @param array $usernames
 * @return array username => userid
 */
function cs_userids_by_username(array $usernames): array {
    global $CFG, $DB;

    $map = [];
    foreach (array_chunk(array_values($usernames), 1000) as $chunk) {
        [$insql, $params] = $DB->get_in_or_equal($chunk);
        $params[] = $CFG->mnet_localhost_id;
        $map += $DB->get_records_select_menu('user', "username $insql AND mnethostid = ? AND deleted = 0",
            $params, '', 'username, id');
    }
    return $map;
}
