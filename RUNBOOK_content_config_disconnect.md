# Runbook: teacher toggles disconnected from student access

Use when a teacher says content is on for their class (students can open it) but the
toggles in the content-configuration form are all off, or the user grade report says
**"No content has been enabled for this class."**

Background: see `PROJECT_CONTEXT.md` §4. Short version: students are gated by group
conditions in `course_modules.availability` (Store A); the toggle form and the report
read `local_coursegroupmodules` / `local_coursegrouptopics` (Store B), keyed by **cohort**
id in a column called `groupid`. A is derived from B, never the reverse, so if B rows
vanish students keep access and the toggles read off.

## Known cause (2026-10 incident)

`api::insert_groupsconfig_instance()` used to delete B rows by cohort id only
(`['groupid' => $groupid]`, no `courseid`). Unchecking a class in one course wiped
that cohort's toggle rows in every course. Availability was left alone, so students kept
access. Fixed by scoping every delete to `courseid` (and `open_path` for non-admins) and
by revoking the class's availability when a class is removed. If this recurs, first
check that `api.php` still has `'courseid' => $courseid` on each
`COURSEGROUPTOPICS_TABLE` / `COURSEGROUPMODULES_TABLE` delete: a redeploy of an old file
brings the bug back.

## 1. Detect

```
php local/cli/audit_groupcontentconfig.php --quiet
```

Read-only. Lists every cohort/course pair where students can reach quiz or supervideo
modules that have no toggle row, and exits 1 if any exist. Narrow with
`--courseid=95` or `--cohortid=19`.

- `NO TOGGLE ROWS`: every reachable module lacks a row. The classic wipe. Go to step 3.
- `PARTIAL`: some rows survive. Go to step 5 before repairing.
- Pairs not listed are healthy, or unconfigured on both sides (nothing to repair).

## 2. Back up the plugin tables for the affected courses

Repair writes only to these two tables, so a row export is enough. Example for course 95:

```php
// save as a CLI script outside the web root and run with php
define('CLI_SCRIPT', true);
require('/var/www/html/config.php');
$out = [];
foreach (['local_coursegrouptopics', 'local_coursegroupmodules'] as $t) {
    $out[$t] = array_values($DB->get_records($t, ['courseid' => 95]));
}
file_put_contents('/path/to/backup_course95_' . date('Ymd_His') . '.json', json_encode($out));
```

Or `mysqldump` those two tables with `--where="courseid IN (...)"`.

## 3. Repair: dry run first

```
php local/cli/rebuild_groupcontentconfig.php --cohortid=19 --courseid=95
```

Dry run is the default. It rebuilds B rows from the surviving availability, which is the
only faithful record of what was enabled. Read the output:

- `module(s) to restore` should be close to the audit's reachable count (it also counts
  module types the audit skips, so it can be a few higher).
- `open_path` should be the class owner's path (e.g. `/1/14`). Toggles only show for
  users with that path (admins see all).
- `section(s) carry the group condition but hold no enabled module - not restored`:
  stale conditions or empty topics. Not an error, see step 6.

Execute only when it looks right:

```
php local/cli/rebuild_groupcontentconfig.php --cohortid=19 --courseid=95 --execute
```

It never writes to `course_modules` or `course_sections`, so student access cannot change.

## 4. Verify

```
php local/cli/audit_groupcontentconfig.php --courseid=95 --quiet
```

Expect no `BROKEN` line for that course. Then open the toggle form and the grade report for
that course as a teacher in the class.

If the section toggles above the activities are still off:

```
php local/cli/fix_missing_section_toggles.php --cohortid=19 --courseid=95        # dry run
php local/cli/fix_missing_section_toggles.php --cohortid=19 --courseid=95 --execute
```

If a section never shows a checkbox at all, it has no `local_coursetopics` row for that
`open_path`; the rebuild script's `--create-coursetopics` creates it (off by default
because it widens what the course offers).

## 5. PARTIAL results

Rebuild only restores pairs with **zero** module rows. A partial pair has some rows, so
the cause is different (e.g. a row filed under a wrong section, a different `open_path`,
or a module added to availability outside the form). Do not run the rebuild blindly:
inspect which modules lack rows and how they differ from the ones that have them first.
As of 2026-10-07 course 346 / cohort 71 is in this state and has not been investigated.

## 6. Stale section conditions

Sections carrying a class's group condition but no enabled module are left alone by the
rebuild. They can leave empty topics visible to students. To strip them:
`--clean-stale-sections` on the rebuild script. That changes student-facing availability,
so dry-run it and review the section ids first.

## Gotchas

- Match groups by decoding the availability JSON, never `LIKE '%"id":296%'` (collides with
  group 2960).
- Cohort id (Store B `groupid`) and group id (availability) are different numbers. The
  mapping is per course: `mdl_enrol` where `enrol='cohort'`, `customint1` = cohort,
  `customint2` = group. The same cohort has a different group in each course.
- The report only counts `quiz`. A class whose enabled content is only videos reports
  "no content" by design.
- Back up before `--execute`; the scripts do not keep what they replace.
