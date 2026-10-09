<?php
// This program is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Nightly reconciliation task.
 *
 * @package    local_xprecalc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_xprecalc\task;

use local_xprecalc\recalculator;

defined('MOODLE_INTERNAL') || die();

/**
 * Re-runs the recalculation across XP-enabled courses.
 *
 * This does not prevent over-accrual, it corrects it after the fact. Points earned by
 * repeating an activity during the day are removed on the next run, so totals converge
 * on the configured policy rather than being right at the instant they are awarded.
 *
 * Courses are processed least-recently-reconciled first and the run stops when its time
 * budget is spent, so a site with more courses than fit in one night still makes steady
 * progress instead of always starving the same tail.
 */
class reconcile_task extends \core\task\scheduled_task {

    /**
     * Task name.
     *
     * @return string
     */
    public function get_name() {
        return get_string('taskreconcile', 'local_xprecalc');
    }

    /**
     * Execute the task.
     */
    public function execute() {
        global $DB;

        if (!get_config('local_xprecalc', 'enabled')) {
            mtrace('local_xprecalc reconciliation is disabled; set local_xprecalc/enabled to run it.');
            return;
        }

        $dedup = get_config('local_xprecalc', 'dedup') ?: recalculator::DEDUP_ONCE;
        $cooldown = (int) (get_config('local_xprecalc', 'cooldown') ?: 86400);
        $residual = get_config('local_xprecalc', 'residual') ?: recalculator::RESIDUAL_PRESERVE;
        $budget = (int) (get_config('local_xprecalc', 'budget') ?: 1800);
        $dryrun = (bool) get_config('local_xprecalc', 'dryrun');
        $allowincrease = (bool) get_config('local_xprecalc', 'allowincrease');

        // Least recently reconciled first, so a truncated run still rotates through everything.
        $courseids = $DB->get_fieldset_sql("
            SELECT c.courseid
              FROM {block_xp_config} c
         LEFT JOIN {local_xprecalc_course} s ON s.courseid = c.courseid
             WHERE c.enabled = 1 AND c.courseid <> 1
          ORDER BY COALESCE(s.timereconciled, 0) ASC, c.courseid ASC");

        mtrace('Reconciling ' . count($courseids) . " course(s); dedup={$dedup}, residual={$residual}, "
            . "budget={$budget}s" . ($allowincrease ? ', increases allowed' : '')
            . ($dryrun ? ', DRY RUN' : ''));

        $started = time();
        $done = 0;
        $skipped = 0;
        $totaldelta = 0;

        foreach ($courseids as $courseid) {
            $elapsed = time() - $started;
            if ($elapsed > $budget) {
                mtrace("Time budget spent after {$elapsed}s; " . (count($courseids) - $done - $skipped)
                    . ' course(s) deferred to the next run.');
                break;
            }

            $coursestart = time();
            try {
                $recalculator = new recalculator($courseid, [
                    'dedup' => $dedup,
                    'cooldown' => $cooldown,
                    'residual' => $residual,
                    'allowincrease' => $allowincrease,
                ]);
                $rows = $recalculator->run(!$dryrun);
            } catch (\Throwable $e) {
                // One bad course must not abort the whole run.
                mtrace("  course {$courseid}: SKIPPED - " . $e->getMessage());
                $skipped++;
                continue;
            }

            $changed = 0;
            $delta = 0;
            $capped = 0;
            foreach ($rows as $row) {
                if ($row->delta !== 0) {
                    $changed++;
                    $delta += $row->delta;
                }
                if (!empty($row->capped)) {
                    $capped++;
                }
            }

            if ($capped) {
                mtrace("  course {$courseid}: {$capped} user(s) would have gained XP;"
                    . ' suppressed because increases are off.');
            }

            $duration = time() - $coursestart;
            $this->record_state($courseid, $delta, $changed, $duration);

            $done++;
            $totaldelta += $delta;

            if ($changed) {
                mtrace(sprintf('  course %d: %d user(s) changed, net %+d XP (%ds)',
                    $courseid, $changed, $delta, $duration));
            }
        }

        mtrace(sprintf('Reconciled %d course(s), %d skipped, net %+d XP in %ds.',
            $done, $skipped, $totaldelta, time() - $started));
    }

    /**
     * Record that a course was reconciled.
     *
     * @param int $courseid The course ID.
     * @param int $delta Net XP change.
     * @param int $changed Number of users changed.
     * @param int $duration Seconds taken.
     */
    protected function record_state($courseid, $delta, $changed, $duration) {
        global $DB;

        $record = $DB->get_record('local_xprecalc_course', ['courseid' => $courseid]);
        if ($record) {
            $record->timereconciled = time();
            $record->lastdelta = $delta;
            $record->lastchanged = $changed;
            $record->lastduration = $duration;
            $DB->update_record('local_xprecalc_course', $record);
            return;
        }

        $DB->insert_record('local_xprecalc_course', (object) [
            'courseid' => $courseid,
            'timereconciled' => time(),
            'lastdelta' => $delta,
            'lastchanged' => $changed,
            'lastduration' => $duration,
        ]);
    }
}
