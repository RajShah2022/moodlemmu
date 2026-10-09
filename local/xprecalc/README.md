# local_xprecalc — Level Up XP recalculation

Recomputes `block_xp` totals by replaying a course's history from
`logstore_standard_log` through the course's own block_xp event filters.

Nothing is scheduled. It runs when you ask it to, either straight from the CLI or
as a one-off ad-hoc task.

## Why the standard log

`block_xp` keeps its own reason log (`block_xp_log`) only for `keeplogs` days — 90 by
default — and the purge task deletes the rest. It is therefore not a usable source of
truth for historical totals. The standard log store is.

## Usage

```bash
# Dry run: show what would change, touch nothing.
php local/xprecalc/cli/recalculate.php --courses=89

# Apply it.
php local/xprecalc/cli/recalculate.php --courses=89 --execute

# Queue as an ad-hoc task instead (picked up by the next cron run).
php local/xprecalc/cli/recalculate.php --courses=89 --queue --execute

# Every XP-enabled course.
php local/xprecalc/cli/recalculate.php --courses=all
```

It is a dry run unless `--execute` is passed. Before any write, the previous totals are
dumped to `$CFG->dataroot/local_xprecalc/course-<id>-<timestamp>.json`.

## `--dedup`

What counts as a repeat of the same action.

| Value | Key | Meaning |
|---|---|---|
| `once` (default) | user + event + cmid | Award each event type once per activity, ever. |
| `cooldown` | user + event + cmid + objectid | Mirrors the live cheat guard's action key; award at most once per `--cooldown` seconds. Use this to model what `timebetweensameactions` would have done. |
| `raw` | — | No de-duplication. Reproduces current live behaviour. |

`once` deliberately drops `objectid` from the key. For
`\mod_quiz\event\attempt_submitted` the objectid is the *attempt*, so keeping it would
make every re-attempt a distinct action and collapse nothing.

## `--residual`

Event replay cannot explain every stored point. Grade-based rules
(`block_xp_filter::CATEGORY_GRADES`) award the grade value itself and cannot be replayed
at all — `local_xp\local\rule\grade_calculator` bails out on restored events
(`is_restored()`), which is what every event read back from the log store is. Manual
awards and since-edited rules are likewise unaccounted for.

So the tool measures the residual as `stored total − uncapped replay`, and:

- `preserve` (default) — keeps that residual and only corrects the event-driven part.
- `drop` — discards it; the total becomes purely what the event replay produced.

`--dedup=raw --residual=preserve` is a no-op by construction. If it reports any change,
the replay does not model your configuration correctly and you should not trust the
other modes.

## Totals never go up by default

The replay applies **today's** filters to **all** of history. Activity that happened
before a rule existed therefore earns under it retroactively, and a student with no XP
record at all can be granted points they were never awarded.

This was not hypothetical: in one course, rules were added in early 2026 and a student
whose activity ran through 2025 — correctly holding zero XP — was scored 295 by the
replay.

So `allowincrease` is **off** by default and any total that would rise is clamped to what
it already is; the CLI marks those rows `(capped)`. The tool removes over-awarded points,
it does not grant new ones. Turn it on with `--allow-increase` (or the matching setting)
only if retroactive awarding is what you actually want.

A consequence worth stating: a legitimate award the live cheat guard wrongly blocked
at the time will *not* be restored while the clamp is on.

## Caveats

- The capability gate (`block/xp:earnxp`) is evaluated against *current* capabilities,
  not what was true when the event fired.
- Levels are recomputed from the new total by `store->set()`. Users can level **down**,
  and level-up notifications/badges already issued are not revoked.

## Nightly reconciliation

`local_xprecalc\task\reconcile_task` runs daily at 03:20 and re-applies the
recalculation across every XP-enabled course.

This **corrects** over-accrual rather than preventing it. Points earned by repeating an
activity during the day are removed on the next run, so totals converge on the
configured policy instead of being correct at the moment they are awarded. It is a
stopgap for sites where the once-only rule cannot be enforced at collection time.

Two independent switches, both starting in the safe position:

| Setting | Default | Meaning |
|---|---|---|
| `local_xprecalc/enabled` | **off** | The task exits immediately unless this is on. |
| `local_xprecalc/dryrun` | **on** | Reports what would change, writes nothing. |

So a fresh install changes no data until you deliberately turn both off/on. Review a dry
run in the task log first.

Other settings mirror the CLI flags: `dedup`, `cooldown`, `residual`, and `budget`.

### Time budget

Courses are processed least-recently-reconciled first, tracked in
`local_xprecalc_course`. When `budget` (default 30 min) is spent the run stops starting
new courses and the rest are picked up on subsequent nights, so a large site rotates
through everything instead of always starving the same tail.

A course takes roughly 20s per 100k log rows, and each course is replayed twice
(policy + baseline) when `residual = preserve`.

### Caveat

Students can level **down** when a total drops. Level-up notifications and badges
already issued are not revoked.
