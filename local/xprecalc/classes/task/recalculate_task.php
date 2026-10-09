<?php
// This program is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Ad-hoc recalculation task.
 *
 * @package    local_xprecalc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_xprecalc\task;

use local_xprecalc\recalculator;

defined('MOODLE_INTERNAL') || die();

/**
 * Recalculate a course's XP totals.
 *
 * Deliberately ad-hoc rather than scheduled: it is queued on demand and runs once.
 * Queue it with recalculate_task::queue().
 */
class recalculate_task extends \core\task\adhoc_task {

    /**
     * Task name shown in the task logs.
     *
     * @return string
     */
    public function get_name() {
        return get_string('taskrecalculate', 'local_xprecalc');
    }

    /**
     * Queue a recalculation for a course.
     *
     * @param int $courseid The course ID.
     * @param array $options dedup, cooldown, residual, execute.
     * @return int The task ID.
     */
    public static function queue($courseid, array $options = []) {
        $task = new static();
        $task->set_custom_data((object) [
            'courseid' => (int) $courseid,
            'dedup' => $options['dedup'] ?? recalculator::DEDUP_ONCE,
            'cooldown' => (int) ($options['cooldown'] ?? 86400),
            'residual' => $options['residual'] ?? recalculator::RESIDUAL_PRESERVE,
            'allowincrease' => !empty($options['allowincrease']),
            'execute' => !empty($options['execute']),
        ]);
        return \core\task\manager::queue_adhoc_task($task);
    }

    /**
     * Execute the task.
     */
    public function execute() {
        $data = $this->get_custom_data();
        $courseid = (int) $data->courseid;

        $recalculator = new recalculator($courseid, [
            'dedup' => $data->dedup,
            'cooldown' => $data->cooldown,
            'residual' => $data->residual,
            'allowincrease' => !empty($data->allowincrease),
            'output' => function($message) {
                mtrace($message);
            },
        ]);

        $execute = !empty($data->execute);
        mtrace("Recalculating course {$courseid} (dedup={$data->dedup}, residual={$data->residual}, "
            . ($execute ? 'WRITING' : 'dry run') . ')');

        $rows = $recalculator->run($execute);

        $changed = 0;
        foreach ($rows as $row) {
            if ($row->delta !== 0) {
                mtrace("  user {$row->userid}: {$row->before} -> {$row->after} ({$row->delta})");
                $changed++;
            }
        }
        mtrace("Done. " . count($rows) . " user(s) examined, {$changed} with a change.");
    }
}
