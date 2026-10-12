# PROJECT_CONTEXT.md
> Living context document — read before touching any code.
> Last updated: 2026-10-12

---

## 1. Project

| Field        | Value |
|---|---|
| **Name**     | openLMS (Skryptech Moodle customisation) |
| **Summary**  | Moodle 4.3.2+ install with custom per-class ("cohort") content gating, custom grade reports, org-hierarchy scoping via `open_path` |
| **Status**   | Active — mid-incident remediation |
| **Repo**     | `/var/www/html` (branches: `pk-dev` = dev work, `prod` = production; main branch `master`) |
| **Entry point** | Moodle core `index.php`; custom work lives in `local/courses`, `blocks/course_config`, `grade/report/user` |

---

## 2. Stack

| Layer       | Technology |
|---|---|
| Language    | PHP (Moodle 4.3.2+, `$branch = '403'`) |
| Framework   | Moodle core, plugin types `local`, `block`, `gradereport` |
| UI          | Moodle Boost/Mustache + AMD modules (`amd/src` → `amd/build`), jQuery, Font Awesome |
| Database    | MySQL/MariaDB (`mysqli`), db `skryptech`, prefix `mdl_` |
| ORM/Query   | Moodle `$DB` (`moodle_database`) — no external ORM |
| Auth        | Moodle core + cohort sync enrolment |
| Course format| **flexsections** — nested sections, hierarchy in `course_format_options` |
| Key libs    | `tool_certificate`, custom `local_notification`, `supervideo` activity module |

---

## 3. Architecture Overview

Moodle install with heavy customisation around **per-class content gating**. A "class" is a Moodle
**cohort** synced into a course **group** via cohort enrolment. Teachers switch sections/activities
on per class in a content-configuration form; plugin stores those choices in own tables, then writes
Moodle `availability` JSON so students are gated. Custom `grade/report/user` reads same tables to
decide what to report.

`open_path` (on `mdl_user` + every plugin table) is an org-hierarchy path (`/1/15`) scoping what each
staff user sees/edits. Non-admins only see rows matching their own path.

**Critical: two stores, derived one way only.** See §4.

---

## 4. Content Configuration Model ⚠ core domain knowledge

### 4.1 The two stores

| | Store A — student gating | Store B — teacher toggles |
|---|---|---|
| Where | `mdl_course_modules.availability`, `mdl_course_sections.availability` | `mdl_local_coursegrouptopics` (sections), `mdl_local_coursegroupmodules` (activities) |
| Owner | Moodle core | `local_courses` plugin |
| Keyed by | **GROUP id** | **COHORT id** — in column named `groupid` (misleading) + `open_path` |
| Drives | what students can reach | teacher toggle state, `grade/report/user` |

**Derivation is one-way: B → A.** `api::update_section_availability()` walks Store B rows and ORs
group conditions into Store A. Nothing ever reads A to rebuild B. So B is load-bearing and
unrecoverable by the app; A is self-sustaining. Lose B → students keep access, every toggle reads
off, report says "No content has been enabled for this class."

### 4.2 Cohort ↔ group mapping

Authoritative source = cohort enrolment instance: `mdl_enrol` where `enrol='cohort'`,
`customint1` = cohort id, `customint2` = group id. Fallback = `groups_members` ∩ `cohort_members`.
Plugin tables store the **cohort** id; availability JSON stores the **group** id.

### 4.3 Availability JSON shapes

One top-level block **per class**, OR'd together. Written by `api::update_section_availability()`.

Module (quiz, supervideo — identical treatment):
```json
{"op":"|","c":[{"op":"|","c":[{"type":"group","id":296},{"type":"role","id":4}]}],"show":false}
```
Section:
```json
{"op":"|","c":[{"op":"&","c":[{"type":"group","id":296}]}],"show":false}
```
Module blocks have 2 elements (group + teacher role); section blocks have 1. A block belongs wholly
to one class → removing a class = drop its whole block, not just the group clause (else a bare
teacher-role clause keeps content visible to staff forever). `show:false` = non-matchers see nothing.
~~`cm.visible` stays 1 throughout — this subsystem never used the visibility flag to gate classes.~~ Wrong as of 2026-10-11: unselected content is `cm.visible=0` + empty availability (course 89: 170/532 quizzes; course 95: 545/570); selected = `visible=1` + group block. No store records which cohorts are enabled for a hidden item.

Match by walking decoded tree, never `LIKE '%"id":296%'` — collides with group 2960.

### 4.4 Section hierarchy (flexsections)

Tree lives in `mdl_course_format_options` where `name='parent'`; `value` = parent's section
**NUMBER**, not id. `mdl_course_sections` has no parent column.

Typical shape: `Numbers > Factors and Multiples > Divisibility Tests > quiz`, or
`Number > Estimation > Rounding off > quiz`. Container sections hold no activities.

Form nests checkboxes the same way (`data-parent`, `groupcontentconfiguration::get_parent_id()`).
**A leaf section row is not enough — every ancestor needs a `local_coursegrouptopics` row** or the
leaf's checkbox has nothing to nest under and reads off. Course 89 / cohort 19: 6 sections hold
quizzes, 8 topic rows originally stored; the extra 2 are ancestors.

### 4.5 `open_path` asymmetry

Store B rows carry `open_path`; Store A has no such concept. Form filters B by `$USER->open_path`
for non-admins (`groupcontentconfiguration.php` — skipped for siteadmin / `api::is_administrator()`).
Consequence: admin and teacher can see different toggle states for the same class, and a save by one
can delete rows the other created, while availability underneath is unchanged and shared.

### 4.6 Failure modes → symptom

| Cause | Symptom |
|---|---|
| B rows deleted, A left intact | students fine, toggles off, report empty ← **the 2026-10 incident** |
| B row `topicid` ≠ `cm.section` | toggle off (old form read keyed on `topicid`), availability live |
| B row `open_path` ≠ viewer's | toggle off for that viewer only |
| Ancestor section has no topic row | leaf toggle off though activity enabled |
| B row exists, A missing | toggle on, students have no access |
| Enabled content is videos only | report says "no content" — report filters `m.name = 'quiz'` |
| Class removed in ONE course, B deleted by cohort id only (no `courseid`), A untouched | cohort's B rows gone in EVERY course; students fine, toggles off, report empty ← **root cause 2026-10**. Fixed in working tree, see §6 |

### 4.7 Module types

`quiz` and `supervideo` both stored in `local_coursegroupmodules`, gated identically. Course 89 /
cohort 19 (dev): 93 quiz + 64 supervideo. **`grade/report/user` only counts `quiz`** — a class whose
enabled content is videos reports "no content" by design.

---

## 5. File Map

> Check here before opening any file. Only explore files NOT listed.

| Path | Purpose | Notes |
|---|---|---|
| `local/courses/classes/api.php` | Core content-config logic, availability writer | ~1469 lines. `insert_coursetopics_instance()`, `insert_groupsconfig_instance()`, `insert_groupcontentconfig_instance()`, `update_section_availability()`, `update_deleted_*_availability()`. `*_bkp()` methods are dead code |
| `local/courses/classes/form/groupcontentconfiguration.php` | Teacher toggle form (dynamic_form) | Root-owned — needs `sudo` to edit. Checkbox list built from `local_coursetopics` filtered by `open_path`: no row there → no checkbox at all |
| `local/courses/classes/external.php` | Web services | `:1377` calls `api::bulk_enable_for_group()` — method absent from prod + from final file |
| `local/courses/amd/src/contentconfiguration.js` | Toggle UI, parent/child cascade | Rebuild `amd/build` after edit. Stray `.broken.*`/`.bak.*` files in tree |
| `local/courses/db/install.xml` | Schema for the 4 plugin tables | `local_coursetopics`, `local_coursegroups`, `local_coursegrouptopics`, `local_coursegroupmodules`. No unique indexes |
| `local/courses/db/upgrade.php` | Upgrade steps | Latest `2023060600.28` seeds `local_notification_type` — unrelated to content config |
| `local/cli/rebuild_groupcontentconfig.php` | **NEW** repair: rebuild B rows from module availability | Dry-run default. Candidates = pairs with **zero** module rows only. `--clean-stale-sections` second pass *removes* stale section conditions — never creates rows |
| `local/cli/fix_missing_section_toggles.php` | **NEW** repair: switch on section chain above enabled activities | Dry-run default. Works on any pair incl. already-rebuilt. Writes `local_coursegrouptopics` only — never availability. `--from-rows` when no cohort enrol instance exists |
| `local/cli/audit_groupcontentconfig.php` | **NEW** read-only detector: pairs where students reach modules with no toggle row | Exit 1 if any broken, so cron-able. Runbook: `RUNBOOK_content_config_disconnect.md` |
| `local/courses/cli/{rebuild_groupcontentconfig,fix_missing_section_toggles}.php` | Older duplicates of `local/cli/` scripts, came in from `pk-dev` | `rebuild` copy lacks flexsections parent handling. Use `local/cli/`; delete these once confirmed unused |
| `blocks/course_config/templates/course_config.mustache` | Launches toggle form | `:21` passes `data-groupid={{cohortid}}` — confirms Store B `groupid` = cohort id |
| `grade/report/user/index.php` | Custom per-class performance report | `:432-442` resolves class→cohort by **name match** (fragile, see §9). `:665` filters `m.name='quiz'`. `:743` emits "No content has been enabled for this class." |
| `grade/report/user/lib.php` | Report helpers | `filter_course_structure()`, `get_course_structure()` |
| `RUNBOOK_content_config_disconnect.md` | Detect → backup → dry-run → execute → verify for toggle/access disconnect | Step 5: PARTIAL pairs not covered by rebuild (zero-row pairs only) |
| `grade/report/user/lib.php` (UNCOMMITTED part) | `gradereport_user_quiz_url()`, `gradereport_user_quiz_name_link()` | Pre-session uncommitted work. Random Bag + Past Year reports call `gradereport_user_quiz_name_link()` — deploy with them or page fatals |
| `grade/report/user/randombagview.php` | **NEW** (UNCOMMITTED, untracked) shared Random Bag Test report | `GRADEREPORT_USER_RANDOM_BAG_TEACHER_COURSES` const = course-ID allowlist for teacher button (empty = off everywhere). `gradereport_user_random_bag_page($courseid,$students,$classname,$urlparams,$teacherview)` always exits; `$teacherview=true` (set by 2 teacher calls in `index.php`) → picker flattens all sections w/ depth indent, else only "Random Bag Tests" children. `gradereport_user_random_bag_highlights()` shared by student + class view. Student view = original code moved verbatim; class view = `gradereport_user_random_bag_class_report()` |
| `grade/report/user/index.php` (Random Bag) | Wires Random Bag for both roles | Student branch: one call `gradereport_user_random_bag_page($courseid, [$USER->id => $USER])`. Teacher branch: hook after `$classes` built (~`:446`), button in class dropdown form. Params: `class_id`, `quiz_id`, `rbstudent`. Report sections located by name `"Random Bag Tests"` |
| `grade/report/user/quizdataprocessor.php` | Attempt → question data | `get_attempt_questions()` runs per-question queries — slow for big classes. `group_by_category()` correct = fraction ≥ 0.5 |
| `grade/report/user/integrated_quiz_renderer.php` | Renders question from real attempt usage | Needs one student's attempt → can't render class-wide view |
| `admin/cli/course_sync.php` | **NEW** (untracked) prod→target course copy, all user data | Source run (no `--target`) = backup + `extras.json` (cohorts, cohort enrols, `supervideo_view`). `--target` = delete-and-restore into existing course + re-import extras. Refuses if export `wwwroot` == this site. Export test: course 91 → 3.1 GB, ~10 min |

---

## 6. Active Goals

- [x] Diagnose "students see content, teacher toggles off, report empty"
- [x] Fix unscoped deletes + availability clean-up in `api.php`
- [x] Build repair scripts
- [x] Rebuild module rows for cohort 19 courses 87/89/95/98/99/100/112 (2026-10-06/07, re-audit 0 zero-row pairs)
- [ ] Prod: run `fix_missing_section_toggles.php` for all 8 cohort-19 pairs (119 section rows pending)
- [ ] Prod: sweep remaining casualty cohorts (39 / ICSE_2 confirmed; ~17 more candidates)
- [ ] Fix `grade/report/user` class→cohort name-match resolution
- [ ] Decide on `external.php:1377` / enable-all button vs removed `bulk_enable_for_group()`
- [x] Teacher class-average Random Bag report (COMMITTED `ebc309030`)
- [x] Teacher picker lists all sections/subsections; student picker stays "Random Bag Tests" only (UNCOMMITTED)
- [x] Strengths/weaknesses never share a category (UNCOMMITTED)
- [ ] Fill `GRADEREPORT_USER_RANDOM_BAG_TEACHER_COURSES` with **prod** course IDs (user to supply)
- [ ] Commit + deploy this session's Random Bag changes (UNCOMMITTED: `index.php`, `randombagview.php`). Base set already COMMITTED `ebc309030`: `randombagview.php`, `index.php`, `lib.php`, `integrated_quiz_renderer.php`, `quizdataprocessor.php`
- [ ] Browser-test Random Bag as student + teacher (only CLI-rendered so far)
- [ ] Run `course_sync.php --target` for real (target side never executed)
- [x] Finish `pk-dev` → `prod` merge commit — COMMITTED `ebc309030` (verified 2026-10-12)
- [x] Strip `pk-dev` enable-all button from `prod` merge (2026-10-12)

---

## 7. Decisions Log

> Settled choices — do not re-debate without a stated reason.

| Date | Decision | Rationale |
|---|---|---|
| 2026-10-06 | Scope all 4 plugin-table deletes by `courseid` (+ `open_path` in non-admin branches) | Unscoped `['groupid' => $groupid]` wiped a cohort's config in **every** course site-wide |
| 2026-10-06 | Deletes must revoke the availability they caused | Row delete alone = the incident. `class_content_ids()`/`topic_content_by_class()` capture before, `revoke_class_availability()` cleans after |
| 2026-10-06 | `revoke_class_availability()` checks surviving rows before clearing | Availability has no `open_path`; a module still enabled under another path must keep its condition |
| 2026-10-06 | Look module up by `id` alone in `update_deleted_module_availability()` | Old lookup joined on unreliable `topicid` → `false` → silent no-op → the 375 orphans |
| 2026-10-06 | Remove only the departing class's block; clear+hide only when nothing remains | Old code blanked whole field, revoking every other class |
| 2026-10-06 | Section loses last class → clear availability **and** `visible = 0` | Clearing alone exposes a group-restricted section to everyone. Cascades to its activities via `course_update_section()`, recorded in `visibleold` |
| 2026-10-06 | Derive `topicid` from `$modulesections[$activityid]` (real `cm.section`) | Old code reused leftover `$topicid` loop variable → rows filed under foreign sections |
| 2026-10-06 | Form reads activities by class, not per topic | Rows under non-enabled sections never loaded → toggle off while students had access |
| 2026-10-06 | Repair reconstructs B from **module** availability only | Section availability is unreliable: 41 of 47 conditions stale |
| 2026-10-06 | No version bump for these changes | No schema change, no new class file, no new strings/services/tasks. Purge caches after deploy |
| 2026-10-06 | Final prod `api.php` = 3-way merge (base dev snapshot, theirs prod, mine), **excluding** `bulk_enable_for_group()` | Prod never had the method; user confirmed it stays out |
| 2026-10-07 | Ancestor repair lives in a **separate** script, not folded into `rebuild_groupcontentconfig.php` | User reverted the in-place version; rebuild script's candidate query excludes already-rebuilt pairs anyway |
| 2026-10-07 | Course refresh prod→dev = backup with users + restore "delete contents, then restore" into existing course | Merge duplicates activities + splits user data. Course id kept; cm/quiz ids change |
| 2026-10-07 | `course_sync.php` forces backup/restore settings by unlocking (`set_status(NOT_LOCKED)`) | Site backup defaults may lock users/logs off; `set_value()` throws on locked settings |
| 2026-10-07 | Restore with `enrolments = ENROL_WITHUSERS`, then re-create cohort enrol instances from `extras.json` + `enrol_cohort_sync()` | Cross-site restore maps cohort enrol to 0 (`enrol/cohort/lib.php:237`) → cohort-enrolled users silently lose enrolment |
| 2026-10-07 | Cohort import adds members only, never removes | Cohorts site-wide; target may have members prod lacks |
| 2026-10-07 | Supervideo views matched by `<activity name>#<nth same-name in course order>` + username | cmids change on restore; names stable |
| 2026-10-08 | Random Bag moved to shared `randombagview.php`, student code verbatim | One code path for both roles; verbatim keeps student behaviour identical |
| 2026-10-08 | Teacher average = mean of per-student %; category avg over students who got that category | Random bag → students get different question mix |
| 2026-10-08 | Teacher averages use latest **finished** attempt; "View report" shows latest attempt (student rule) | Re-attempt in progress shouldn't drop completed student from average |
| 2026-10-08 | Teacher drill-down = per-question stats table, not rendered questions | Integrated renderer needs one student's attempt |
| 2026-10-09 | Teacher button limited by code-level const allowlist, gates button **and** handler | User rejected admin setting; handler gate blocks URL access |
| 2026-10-09 | Allowlist applies to teacher button only; student button unchanged in all courses | Scope of request; user can extend |
| 2026-10-07 | Repair = `rebuild_groupcontentconfig.php` (A → B), dry run first, back up rows before `--execute` | A is only faithful record of what was enabled |
| 2026-10-07 | Audit counts only `quiz`+`supervideo`; B-only rows ignored | B-only mismatch = module types outside filter, not real breaks (spot-checked 4 pairs) |
| 2026-10-12 | Section picker: teacher sees all sections/subsections, student only "Random Bag Tests" | Same UI both; teacher needs any quiz |
| 2026-10-12 | Highlights: category in both lists → strength if >50%, else weakness (50% = weakness); no backfill | User rule; no duplicate across lists |
| 2026-10-12 | Merge resolution: `local_courses` version = prod `2023060600.30` | Never lower a version prod DB already has |
| 2026-10-12 | Third-party plugin upgrades (pk-dev `627bfea23`: `tool_certificate`, `tool_tcpdffonts`, `availability_role`, `report_benchmark`, `block_xp`, `format_flexsections`, `filter_multilang2`, `mod_supervideo`) + composer `openai-php/client` bump kept **out of** `prod` merge | User won't ship plugin upgrades with this merge. Paths reset to prod `HEAD`; backup in session scratchpad |
| 2026-10-12 | `local/trialsignup` (pk-dev `6ad55041e`) kept out of `prod` merge | Not needed on prod |
| 2026-10-12 | Enable-all button + `local_courses_bulk_enable_topics` service removed from `prod` merge | `api::bulk_enable_for_group()` doesn't exist; button would fatal. Form keeps pk-dev's read-activities-by-class fix |
| 2026-10-12 | Merge resolution: Random Bag student code stays in `randombagview.php`; prod-only Chart.js AMD guard ported into it | Prod inline block = pk-dev pre-move code + filter/link/guard; filter + link already in `randombagview.php` |

---

## 8. Rejected Approaches ⛔

> Hard blocklist — do not re-suggest anything in this table.

| Approach | Reason rejected |
|---|---|
| `api::bulk_enable_for_group()` for repair | Enables every topic + activity in the course → over-grants, and writes new availability for content students couldn't see |
| Section `availability` as repair source | 41 of 47 sections carried stale conditions; restoring would re-enable deliberately-disabled topics |
| `LIKE '%"type":"group","id":296%'` for matching | Matches group 2960. Walk the decoded JSON tree instead |
| `--clean-stale-sections` to repair parent sections | It only *removes* conditions; it never creates topic rows |
| Blanking whole `availability` field on class removal | Revokes every other class's access to the same module |
| Trusting topic rows alone to protect sections in the stale pass | Pairs not yet rebuilt have none → would clear load-bearing ancestors. (Hardening written then reverted — re-add before using that flag) |
| `SET @var` in SQL handed to the user | phpMyAdmin runs each statement in its own session; inline the literals |
| Admin setting (`settings.php` + lang strings) for Random Bag teacher course list | User wants it in code, not admin settings |
| Restore "Merge into existing course" for prod refresh | Duplicates every activity; user data split across copies |
| `admin/cli/restore_backup.php` for existing course | Only takes `--categoryid`, always creates new course |
| Run rebuild on PARTIAL pairs | Only zero-row pairs are candidates; inspect first |
| Deploy only files touched this session for Random Bag | Depends on pre-session uncommitted `lib.php` (`gradereport_user_quiz_name_link()`) → fatal |

---

## 9. Known Issues

| Issue | Status | Notes |
|---|---|---|
| Unscoped `delete_records` wiped cohort config site-wide | Fixed (UNCOMMITTED) | `api.php` admin + non-admin branches of both insert functions |
| `update_deleted_module_availability()` silent no-op on wrong `topicid` | Fixed (UNCOMMITTED) | Root cause of 375 orphaned restrictions on course 89 |
| `update_deleted_section_availability()` skipped write when last condition removed | Fixed (UNCOMMITTED) | Caused the 39/47 stale section conditions |
| `topicid` from leftover loop variable | Fixed (UNCOMMITTED) | Now self-heals existing wrong rows on save |
| Cohort 19 toggles wiped (courses 87, 89, 95, 98, 99, 100, 112) | Fixed | Rebuilt 2026-10-06/07: 89 earlier, 95 = 37 rows, 87/98/99/100/112 = 1410 rows. Backups in session scratchpad. Re-audit: 0 zero-row pairs |
| Course 346 cohort 71 (group 337): 15 of 141 reachable modules lack B rows | Open | PARTIAL, cause unknown, not repaired |
| 109 stale section conditions on cohort 19 (87:16, 98:13, 99:25, 100:14, 112:41) | Open | Left alone; `--clean-stale-sections` changes student availability — dry run + review ids first |
| Exact trigger (course/user/date) of wipe | Unknown | No log of raw `delete_records`; only old code + data pattern |
| Course 91 cohort 19 has `local_coursegroups` row, absent from audit | Open | Likely unconfigured, not confirmed |
| Prod: 119 ancestor section rows missing across 7 pairs | Open | `fix_missing_section_toggles.php` dry run confirmed |
| ~19 cohorts have `local_coursegroups` rows but 0 module rows | Open | Cohort 19 (8 courses) + 39 confirmed casualties; trial/test classes likely never configured |
| `grade/report/user` resolves class→cohort by stripping `" cohort"` and name-matching teacher's own cohorts | Open | Fails on groups named `… cohort (2)`; groups spanning cohorts resolve arbitrarily (`get_records_sql` keys by `groupid`, dupes collapse). Dev course 345 group 276 → cohort 57 for one teacher, 59 for another |
| Report counts `quiz` only | Open (by design?) | Video-only class reports "no content" |
| `external.php:1377` calls missing `bulk_enable_for_group()` | Open | Enable-all button still fatals on `pk-dev` (`groupcontentconfiguration.php:78`, `local_courses_bulk_enable_topics` service). Removed from `prod` merge 2026-10-12 |
| No unique index on plugin tables | Open | `(courseid, groupid, moduleid, open_path)` would prevent dupes; needs version bump + upgrade step |
| Course backup omits `supervideo_view` (watch progress) | Open (worked around) | `mod/supervideo/backup/moodle2/backup_supervideo_stepslib.php` backs up settings only. `course_sync.php` copies it via `extras.json` |
| ~~`integrated_quiz_renderer.php` require fatals `/question/engine/lib.php` (`$CFG` unset in function)~~ | Fixed (UNCOMMITTED) | `global $CFG` added to `gradereport_user_random_bag_page()`; pre-existing bug, hit on quiz select |
| Random Bag class report slow on big classes | Open | `get_attempt_questions()` per student + per-question queries |
| Random Bag `quiz_id` not checked against "Random Bag Tests" section | Open (low risk; teacher picker now intentionally allows any section — student URL still unchecked) | Teacher can load any course quiz via URL; student path same as before |
| Random Bag drill-down blank/raw for image/TeX-only questions | Open | Preview = `strip_tags` + `shorten_text` of `questiontext` |
| Teachers without `teacher` role + group + cohort get no classes | Open (pre-existing) | Same `$classes` query as rest of teacher report |
| ~~Pre-session uncommitted work in `grade/report/user/` (`index.php`, `lib.php`, `integrated_quiz_renderer.php`, `quizdataprocessor.php` staged)~~ | Fixed | COMMITTED `ebc309030`; only this session's `index.php` + `randombagview.php` edits UNCOMMITTED | Must ship together with Random Bag change |
| Chart.js loaded without AMD guard in `grade/report/user/index.php` (2 spots) + `lib.php` (3 spots) | Open | Guarded copies use `window.define` swap; unguarded ones may register Chart as anonymous AMD module |

---

## 10. Open Questions

- [ ] Does prod `external.php` / `db/services.php` reference `bulk_enable_for_group` / `bulk_enable_topics`? If yes, pasting final `api.php` breaks the enable-all button
- [ ] Does prod have the enable-all button block in `groupcontentconfiguration.php`? (Button is in HEAD on dev — prod unknown)
- [ ] Which cohorts beyond 19 and 39 are real casualties vs never-configured? (corrected casualty query answers it)
- [ ] Which course's class-config uncheck triggered the original site-wide wipe? (`local_coursegroups` row 168 recreated 2026-03-10 by user 853, `timemodified=0`)
- [ ] Add the unique index?
- [ ] Should `grade/report/user` count non-quiz modules?
- [ ] Which **prod** course IDs get teacher Random Bag button?
- [ ] Limit student Random Report button to same courses too?
- [ ] Does prod have its own edits to `grade/report/user/*` files? (diff before overwrite)
- [ ] "Classes" data in custom `local/*` tables — needs adding to `course_sync.php`? Which table?
- [ ] Do Olympiad / Past Year / Random Bag reports hardcode quiz/cm IDs? (breaks after delete-and-restore)
- [ ] Why course 346 cohort 71 partial? Different cause from full wipe?
- [ ] Hidden-content model: `visible=0` vs availability-only. Pick one.
- [ ] Schedule `local/cli/audit_groupcontentconfig.php` in cron, alert on exit 1?

---

## 11. Conventions

| Topic | Convention |
|---|---|
| DB access | Moodle `$DB` only. `{tablename}` placeholders in SQL, `mdl_` prefix in raw/phpMyAdmin queries |
| `get_records` gotcha | Keys by first selected column — `get_records($t, $c, '', 'topic')` keys by `topic`. Duplicate first-column values **silently collapse rows** |
| Plugin table naming | Column `groupid` in `local_coursegroup*` means **cohort id**, not group id |
| `open_path` | Always filter plugin-table reads/writes by it for non-admins; never for availability |
| Visibility writes | `set_coursemodule_visible()`, never raw `set_field('visible')` — only the core API keeps `visibleold` in sync |
| Repair scripts | Live in `local/cli/`. Dry-run by default, `--execute` to write; idempotent; `--courseid`/`--cohortid` filters; never write `course_modules`/`course_sections` |
| Branching | `pk-dev` working branch, `prod` = production branch (merge `pk-dev` into it), `master` = main |
| Deploy to prod | Manual file paste. No schema change → no version bump; run `php admin/cli/purge_caches.php` |
| Testing on dev | Wrap mutations in `$DB->start_delegated_transaction()` + `rollback()`. Prefer read-only replay harnesses |
| Prod DB | **Never** query prod from this environment — user runs SQL on prod themselves |
| Report feature gating | Code-level const allowlist of course IDs, not admin settings. Gate button + request handler |
| CLI as www-data | Scratchpad not traversable by `www-data` — use `/var/www/moodledata/temp/...` for CLI output tests, clean up after |
| Committing | Commit only named files — working tree has big unrelated dirty changes (e.g. `admin/tool/certificate`) |

---

## 12. External Services

| Service | Config location |
|---|---|
| MySQL/MariaDB | `$CFG->dbname/dbuser/dbpass` in `/var/www/html/config.php` |
| Moodle dataroot | `$CFG->dataroot` in `config.php` |
| Font Awesome CDN | Hardcoded in `grade/report/user/index.php` |

---

## 13. Changelog

| Date | Summary |
|---|---|
| 2026-10-07 | Initial export. Diagnosed content-config incident: unscoped `delete_records(['groupid'=>$groupid])` in `insert_groupsconfig_instance()` wiped cohort 19's config across 8 courses while leaving 375 group availability conditions intact → students kept access, teacher toggles read off, grade report reported no content. Fixed 6 defects in `api.php` + form read path (all UNCOMMITTED). Added 2 CLI repair scripts (untracked). Merged final prod `api.php` excluding `bulk_enable_for_group()`. Documented §4 availability↔toggle model incl. flexsections ancestor requirement |
| 2026-10-09 | Added `admin/cli/course_sync.php` (prod→dev course copy incl. users, attempts, cohorts, cohort enrols, supervideo progress; export tested, target untested). Built teacher class-average Random Bag report: moved student code verbatim to new `grade/report/user/randombagview.php`, added class view + student drill-in, self-review fixes (latest finished attempt, status labels, entity decode). Gated teacher button by code const allowlist (empty). All UNCOMMITTED; depends on pre-session uncommitted `grade/report/user` work |
| 2026-10-11 | Session: investigated course 89 vs 95 + all-course audit; root cause = unscoped cohort-id deletes; rebuilt cohort 19 in 6 courses; added `local/cli/audit_groupcontentconfig.php` + runbook; moved scripts to `local/cli/`. Status: `api.php` fix, `local/cli/*`, `RUNBOOK_content_config_disconnect.md`, `PROJECT_CONTEXT.md` all `UNCOMMITTED` |
| 2026-10-12 | Resolved `pk-dev` → `prod` merge conflicts (9 files, not committed): plugin upgrades (`627bfea23`) + composer bump reset to prod; enable-all button + `local/trialsignup` removed; `local_courses` version per §7; `quizdataprocessor.php` kept prod div-by-zero guard + `calculate_overall_stats_from_tags()`; `index.php` uses `randombagview.php` + keeps prod cohort attach and Chart.js AMD guard; merged this file |
| 2026-10-12 | Session: Random Bag teacher picker → all sections/subsections (`$teacherview` param, student unchanged); fixed `$CFG` scope fatal on quiz select; strengths/weaknesses dedupe via shared `gradereport_user_random_bag_highlights()` (student view now uses it). Reconciled labels: merge + base Random Bag set COMMITTED `ebc309030`; this session's `index.php` + `randombagview.php` UNCOMMITTED. Not browser-tested (`php -l` only) |
