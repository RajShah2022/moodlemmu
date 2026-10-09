<?php
// This program is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * CLI entry point for recalculating Level Up XP totals.
 *
 * @package    local_xprecalc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

use local_xprecalc\recalculator;
use local_xprecalc\task\recalculate_task;

[$options, $unrecognised] = cli_get_params([
    'help' => false,
    'courses' => '',
    'dedup' => recalculator::DEDUP_ONCE,
    'cooldown' => 86400,
    'residual' => recalculator::RESIDUAL_PRESERVE,
    'execute' => false,
    'queue' => false,
    'allow-increase' => false,
], ['h' => 'help']);

if ($unrecognised) {
    cli_error(get_string('cliunknowoption', 'admin', implode("\n  ", $unrecognised)));
}

if ($options['help'] || $options['courses'] === '') {
    cli_writeln("
Recalculate Level Up XP totals by replaying the standard log.

Runs as a dry run unless --execute is given. Previous totals are written to
\$CFG->dataroot/local_xprecalc/ before anything is overwritten.

Options:
  --courses=1,2,3   Course IDs to process, or 'all' for every XP-enabled course. Required.
  --dedup=once      once      award each (event, activity) pair once per user, ever (default)
                    cooldown  award it at most once per --cooldown seconds
                    raw       no de-duplication; reproduces current behaviour
  --cooldown=86400  Cooldown window in seconds, used by --dedup=cooldown.
  --residual=preserve
                    preserve  keep XP the event replay cannot explain, e.g. grade-based
                              or manually awarded points (default)
                    drop      discard it; the total becomes purely the event replay
  --allow-increase  Permit totals to go UP. Off by default: the replay applies today's
                    filters to all of history, so activity predating a rule would earn
                    under it retroactively. Leave off unless you want that.
  --execute         Write the new totals. Without this nothing is changed.
  --queue           Queue an ad-hoc task instead of running now. Honours --execute.
  -h, --help        Print this help.

Examples:
  # See what would change in course 89, without touching anything.
  php local/xprecalc/cli/recalculate.php --courses=89

  # Apply it.
  php local/xprecalc/cli/recalculate.php --courses=89 --execute
");
    exit(0);
}

$courseids = [];
if (trim($options['courses']) === 'all') {
    $courseids = $DB->get_fieldset_select('block_xp_config', 'courseid', 'enabled = 1 AND courseid <> 1');
} else {
    foreach (explode(',', $options['courses']) as $id) {
        $id = (int) trim($id);
        if ($id > 0) {
            $courseids[] = $id;
        }
    }
}

if (empty($courseids)) {
    cli_error('No courses to process.');
}

$execute = !empty($options['execute']);
$taskoptions = [
    'dedup' => $options['dedup'],
    'cooldown' => (int) $options['cooldown'],
    'residual' => $options['residual'],
    'allowincrease' => !empty($options['allow-increase']),
    'execute' => $execute,
];

if (!empty($options['queue'])) {
    foreach ($courseids as $courseid) {
        recalculate_task::queue($courseid, $taskoptions);
        cli_writeln("Queued recalculation for course {$courseid}.");
    }
    cli_writeln('Queued tasks are picked up by the next cron run. To run one immediately:');
    cli_writeln('  php admin/cli/adhoc_task.php --id=<taskid> --execute');
    exit(0);
}

if (!$execute) {
    cli_writeln('*** DRY RUN — nothing will be written. Add --execute to apply. ***');
}

$grandtotal = 0;
foreach ($courseids as $courseid) {
    $course = $DB->get_record('course', ['id' => $courseid], 'id, shortname');
    cli_writeln('');
    cli_writeln(str_repeat('=', 78));
    cli_writeln("Course {$courseid} ({$course->shortname})  dedup={$options['dedup']}  residual={$options['residual']}");
    cli_writeln(str_repeat('=', 78));

    try {
        $recalculator = new recalculator($courseid, $taskoptions + ['output' => function($m) {
            cli_writeln('  ' . $m);
        }]);
        $rows = $recalculator->run($execute);
    } catch (Exception $e) {
        cli_writeln('  SKIPPED: ' . $e->getMessage());
        continue;
    }

    $changed = array_filter($rows, function($r) {
        return $r->delta !== 0;
    });
    $capped = array_filter($rows, function($r) {
        return !empty($r->capped);
    });

    // Capped rows end up with delta 0, so they would otherwise vanish from the report.
    // They are the interesting ones: they mark courses whose rules postdate the activity
    // they are being applied to.
    if ($capped) {
        cli_writeln('  ' . count($capped) . ' user(s) would have GAINED XP; suppressed because'
            . ' increases are off. Rules likely postdate this activity:');
        foreach ($capped as $row) {
            cli_writeln(sprintf('    user %-8d holds %d, replay scored %d (+%d suppressed)',
                $row->userid, $row->before, $row->events, $row->events - $row->before));
        }
    }

    if (empty($changed)) {
        cli_writeln('  No changes.');
        continue;
    }

    cli_writeln('');
    cli_writeln(sprintf('  %-8s %10s %10s %12s %10s %10s', 'user', 'before', 'events', 'unaccounted', 'after', 'delta'));
    foreach ($changed as $row) {
        cli_writeln(sprintf('  %-8d %10d %10d %12d %10d %+10d%s',
            $row->userid, $row->before, $row->events, $row->unaccounted, $row->after, $row->delta,
            !empty($row->capped) ? '  (capped)' : ''));
        $grandtotal += $row->delta;
    }
    cli_writeln('  ' . count($changed) . ' of ' . count($rows) . ' user(s) change.');
}

cli_writeln('');
cli_writeln('Net XP change across all processed courses: ' . sprintf('%+d', $grandtotal));
if (!$execute) {
    cli_writeln('Dry run only — nothing was written.');
}
