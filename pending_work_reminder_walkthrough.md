# Pending Work Reminder — Implementation & Deployment Walkthrough

## What it does

Every Monday at 8:00am, students get one email listing the quizzes they still have
pending, grouped by course, each with a **Continue** button straight into that course.
One digest per student per run — not one email per quiz.

Example (real output, captured from the actual code against test data):

> **Subject:** You have 5 quizzes waiting this week
>
> Hi Test Student,
> You still have a few quizzes left to finish. Here is where things stand across your courses:
>
> **1. CPM Stage 5** — 2 quizzes left to complete → [Continue]
> **2. CLSP Math Stage 7** — 3 quizzes left to complete → [Continue]
>
> A few minutes now keeps you on track — pick a course above and jump back in.
> Sent 20/08/2026

See `pending_work_email_preview.html` for the actual rendered version.

---

## How "pending" is defined

- Activity must be `mod_quiz` (scoped to quizzes only — `supervideo` and other
  completion-tracked modules are deliberately excluded).
- Completion tracking must be turned on for that quiz.
- Student must hold the **student role** in that course (teachers/HODs enrolled for
  testing purposes are excluded — this was a real bug caught during testing, see below).
- Student's enrolment must be active (not suspended).
- Completion state must be incomplete (null or 0).
- No due-date logic, no per-user day-count math. The weekly cadence is owned entirely by
  the task's cron schedule — every Monday 8am run just reports whatever is currently
  pending. The `reminderdays`/`frequencyflag`/`frequencydays`/`untildays` fields on the
  notification row are **not used** by this type; they're wired up for other pre-existing
  types (`course_reminder`, `course_completion_reminder`).

---

## Architecture

Two scheduled tasks, chained through a shared queue table — same pipeline this site
already uses for every other notification type (course enrolments, completions, etc.):

1. **`local_courses\task\pending_work_reminder`** (new) — runs Monday 8:00am. For each
   active `course_pending_work` notification row, finds pending quizzes per student and
   writes one queued row per student into `local_emaillogs` (`status=0`).
2. **`local_notifications\task\send_email_notifications`** (pre-existing, hourly) — drains
   the queue and actually sends via `message_send()`.

So a reminder can sit queued up to ~1 hour after the Monday task fires before actually
sending.

### Files

**New:**
- `local/courses/classes/local/pending_work_finder.php` — the SQL + role/completion
  filtering logic. Pure data lookup, no mail-sending, kept separate so it's independently
  testable.
- `local/courses/classes/task/pending_work_reminder.php` — the scheduled task. Iterates
  active per-course notification rows, then the global (catch-all) row, excluding courses
  already covered by a per-course row (active *or inactive* — see opt-out below).

**Changed:**
- `local/courses/classes/notification.php` — added `send_pending_work_email()` (builds
  the digest) and `render_pending_activities()` (the HTML card renderer). Also extended
  `check_pending_mail_exists()`'s same-day dedup branch to cover `course_pending_work`
  (previously that branch only applied to `course_completion_reminder`; without this fix,
  the queue's existing dedup logic would have silently suppressed every repeat send after
  the first one).
- `local/notifications/lib.php` — registered the placeholder list for `course_pending_work`
  in `get_string_identifiers()`. Anything not listed here silently doesn't get substituted.
- `local/courses/db/tasks.php` — registers the task, cron pattern `0 8 * * 1` (Monday 8am).
- `local/courses/db/messages.php` — message provider for `course_pending_work` (popup +
  email, matching every other type here).
- `local/courses/db/upgrade.php` — seeds the `course_pending_work` row in
  `local_notification_type` (parent = the shared "course" category, id resolved by
  shortname lookup, same pattern the existing `course_completion_reminder` seed uses).
- `local/notifications/db/upgrade.php` — re-asserts the `frequencyflag`/`frequencydays`/
  `untildays` columns exist on `local_notification_info` (they were already defined in
  earlier upgrade steps in this plugin's history, but were missing from the live schema
  when this work started — this step re-adds them defensively with `field_exists` guards).
- `local/notifications/classes/forms/notification_form.php` — **unrelated pre-existing
  bug fix**, not something introduced by this feature but discovered while testing it: the
  notification-type dropdown checked `get_plugin_directory('local', $shortname)`, and the
  "course" category's shortname is `course` (singular) while the real plugin directory is
  `local/courses` (plural). That mismatch silently hid the *entire* course category —
  including `course_completion_reminder`, which predates this work — from the create-
  notification dropdown. Fixed to check `pluginname` instead of `shortname`, which is what
  that column exists for. Confirmed only "request" was visible before the fix.
- `local/courses/lang/en/local_courses.php` — task name string.
- `local/courses/version.php` / `local/notifications/version.php` — version bumps to
  trigger the upgrade steps above and to force Moodle's `reset_scheduled_tasks_for_component()`
  to re-sync the new cron pattern (this only happens on a version-triggered upgrade, not
  just editing `db/tasks.php`).

### Placeholders available in Subject/Body

| Placeholder | Value |
|---|---|
| `[enroluser_fullname]` | Recipient's name |
| `[enroluser_email]` | Recipient's email |
| `[pending_count]` | Total pending quizzes across all courses |
| `[pending_activities]` | The rendered course cards (HTML, inline-styled) |
| `[pending_generateddate]` | Date the digest was generated, in the recipient's timezone |

---

## Admin setup

1. Go to the Notifications admin page (`/local/notifications/index.php`), needs the
   `local/notifications:manage` capability.
2. Click **Create Notification** (bell icon).
3. Type = **"Pending Activity Work Reminder"**.
4. **Courses**: pick specific course(s), or leave empty for a global/catch-all rule.
5. Fill in Subject/Body (placeholders above). Leave Reminder days / Add Frequency /
   Frequency days / Until days alone — vestigial for this type.
6. Optional: **Admin body** — if set, and the student has `open_supervisorid` on their
   profile, the supervisor gets a copy too.
7. Check **Active**, save.

**Per-course opt-out**: create a course-scoped row for that course and leave it
**inactive**. It sends nothing of its own, but the task still pulls its course id into the
exclusion list for the global rule — so that course is silently skipped. Flip the same row
to active with its own content to give that course a custom reminder instead of silence.

**Monitoring**: `/local/notifications/email_status.php` shows queued vs. sent emails.

---

## Bugs found during testing (fixed)

1. **Missing role filter** — the original query only checked enrolment status, not role.
   Teacher/HOD test accounts enrolled in a course (with leftover incomplete-quiz
   completion data) were getting swept into the student digest. Fixed by requiring the
   `student` role archetype via `role_assignments`/`role` in course context.
2. **Notification-type dropdown bug** — described above, pre-existing, unrelated to this
   feature but blocking it.
3. **Same-day dedup gap** — `check_pending_mail_exists()` only applied its same-day
   dedup branch to `course_completion_reminder`; extended to also cover
   `course_pending_work`.
4. **SMTP config cache staleness** (environment issue, not code) — after changing
   `smtppass` in the DB, Moodle kept authenticating with the old cached value until
   `php admin/cli/purge_caches.php` was run. Worth remembering any time an SMTP/config
   change here "doesn't take."

---

## Production deployment

### Files to deploy (only these — the working tree has unrelated in-progress changes
mixed in from other work; don't blanket-commit)

New:
- `local/courses/classes/local/pending_work_finder.php`
- `local/courses/classes/task/pending_work_reminder.php`

Changed:
- `local/courses/classes/notification.php`
- `local/courses/db/messages.php`
- `local/courses/db/tasks.php`
- `local/courses/db/upgrade.php`
- `local/courses/lang/en/local_courses.php`
- `local/courses/version.php`
- `local/notifications/classes/forms/notification_form.php`
- `local/notifications/db/upgrade.php`
- `local/notifications/lib.php`
- `local/notifications/version.php`

**Never deploy `config.php`** from dev — it's untracked in git (confirmed), has this box's
DB credentials, `wwwroot`, and a temporary `$CFG->divertallemailsto` line redirecting all
mail to a test inbox for QA. Production needs its own untouched `config.php`.

### Steps

1. **Back up production's database** before anything else:
   ```bash
   mysqldump -u <produser> -p <proddb> > backup_before_pending_work_$(date +%Y%m%d).sql
   ```
2. **Deploy the code** — commit only the files listed above, push, merge to whatever
   branch production deploys from, then pull/deploy on the production box.
3. **Verify `config.php` on production is untouched.**
4. **Run the Moodle upgrade**: `php admin/cli/upgrade.php` (or the web "Upgrade Moodle
   database now" flow). This is what actually:
   - Inserts the `course_pending_work` row into `local_notification_type` (this does
     **not** happen from deploying code alone — it only runs when the upgrade routine
     executes against that database).
   - Ensures the `frequencyflag`/`frequencydays`/`untildays` columns exist.
   - Syncs the Monday 8am cron pattern into `mdl_task_scheduled`.
5. **Purge caches** (upgrade does this automatically, but `php admin/cli/purge_caches.php`
   is safe to run again).
6. **Verify**:
   ```sql
   SELECT value FROM mdl_config_plugins WHERE plugin='local_courses' AND name='version';
   SELECT value FROM mdl_config_plugins WHERE plugin='local_notifications' AND name='version';
   SELECT id, shortname FROM mdl_local_notification_type WHERE shortname='course_pending_work';
   SELECT minute,hour,day,month,dayofweek FROM mdl_task_scheduled WHERE classname LIKE '%pending_work_reminder%';
   ```
   Also confirm "Pending Activity Work Reminder" now shows in the Create Notification
   dropdown.
7. **Set up the real notification** via the admin UI (see "Admin setup" above). Production
   has **no** `divertallemailsto` safety net — whatever's activated goes straight to real
   students. Start scoped to one small course, verify via `email_status.php` after the
   first Monday run, then widen.
8. **Confirm production's system cron** is actually invoking `admin/cli/cron.php`
   regularly — the Monday 8am pattern only fires if that's running.
