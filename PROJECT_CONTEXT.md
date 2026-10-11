
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
| `blocks/course_config/templates/course_config.mustache` | Launches toggle form | `:21` passes `data-groupid={{cohortid}}` — confirms Store B `groupid` = cohort id |
| `grade/report/user/index.php` | Custom per-class performance report | `:432-442` resolves class→cohort by **name match** (fragile, see §9). `:665` filters `m.name='quiz'`. `:743` emits "No content has been enabled for this class." |
| `grade/report/user/lib.php` | Report helpers | `filter_course_structure()`, `get_course_structure()` |
| `RUNBOOK_content_config_disconnect.md` | Detect → backup → dry-run → execute → verify for toggle/access disconnect | Step 5: PARTIAL pairs not covered by rebuild (zero-row pairs only) |

---

---

## 6. Decisions Log

| Date | Decision | Rationale |
|---|---|---|
| 2026-10-07 | Every delete on `local_coursegrouptopics`/`local_coursegroupmodules` scoped by `courseid` (+ `open_path` for non-admins) in `api.php` | Unscoped `['groupid'=>$groupid]` wiped cohort config in all courses |
| 2026-10-07 | Removing a class calls `revoke_class_availability()` after B delete | Keep A and B in step; B-only delete left students with access + toggles off |
| 2026-10-07 | Repair = `rebuild_groupcontentconfig.php` (A → B), dry run first, back up rows before `--execute` | A is only faithful record of what was enabled |
| 2026-10-07 | Audit counts only `quiz`+`supervideo`; B-only rows ignored | B-only mismatch = module types outside filter, not real breaks (spot-checked 4 pairs) |

---

## 7. Rejected Approaches ⛔

| Approach | Reason rejected |
|---|---|
| `LIKE '%"id":296%'` to match groups | Collides with group 2960; walk decoded tree |
| Run rebuild on PARTIAL pairs | Only zero-row pairs are candidates; inspect first |

---

## 8. Known Issues

| Issue | Status | Notes |
|---|---|---|
| Cohort 19 toggles wiped (courses 87, 89, 95, 98, 99, 100, 112) | Fixed | Rebuilt 2026-10-06/07: 89 earlier, 95 = 37 rows, 87/98/99/100/112 = 1410 rows. Backups in session scratchpad. Re-audit: 0 zero-row pairs |
| Course 346 cohort 71 (group 337): 15 of 141 reachable modules lack B rows | Open | PARTIAL, cause unknown, not repaired |
| 109 stale section conditions on cohort 19 (87:16, 98:13, 99:25, 100:14, 112:41) | Open | Left alone; `--clean-stale-sections` changes student availability — dry run + review ids first |
| Exact trigger (course/user/date) of wipe | Unknown | No log of raw `delete_records`; only old code + data pattern |
| Fix is `UNCOMMITTED` | Open | `api.php` dirty; HEAD still has bug — redeploy of HEAD reintroduces it |
| Course 91 cohort 19 has `local_coursegroups` row, absent from audit | Open | Likely unconfigured, not confirmed |

---

## 9. Open Questions

- [ ] Why course 346 cohort 71 partial? Different cause from full wipe?
- [ ] Hidden-content model: `visible=0` vs availability-only. Pick one.
- [ ] Schedule `audit_groupcontentconfig.php` in cron, alert on exit 1?

---

## 10. Conventions

| Topic | Convention |
|---|---|
| Repair scripts | `local/cli/`, dry-run default, `--execute` to write, `--cohortid`/`--courseid` filters |
| Cohort vs group id | Plugin tables = cohort id in `groupid`; availability = group id. Map per course via `mdl_enrol` `enrol='cohort'`, `customint1`→`customint2` |
| DB access ad hoc | PHP CLI via `config.php`, never print creds |

---

## 12. Changelog

| Date | Summary |
|---|---|
| 2026-10-11 | Session: investigated course 89 vs 95 + all-course audit; root cause = unscoped cohort-id deletes; rebuilt cohort 19 in 6 courses; added `local/cli/audit_groupcontentconfig.php` + runbook; fixed `local/cli/` paths in §5. Status: `api.php` fix, `local/cli/*`, `RUNBOOK_content_config_disconnect.md`, `PROJECT_CONTEXT.md` all `UNCOMMITTED` |
