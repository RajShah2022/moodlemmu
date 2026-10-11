<?php
// This program is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Recalculates Level Up XP totals by replaying the standard log.
 *
 * @package    local_xprecalc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_xprecalc;

use block_xp\local\utils\user_utils;
use context_course;
use core\event\base as event_base;

defined('MOODLE_INTERNAL') || die();

/**
 * Replays \core event history through the configured block_xp event filters and
 * rewrites each user's total.
 *
 * Why the standard log and not block_xp_log: block_xp only keeps its own reason log
 * for `keeplogs` days (90 by default) and purges the rest, so it cannot be used as a
 * source of truth for historical totals. The standard log store is the only complete
 * record of what actually happened.
 */
class recalculator {

    /** Award a given (event, activity) pair at most once per user, ever. */
    const DEDUP_ONCE = 'once';
    /** Award a given (event, activity) pair at most once per cooldown window. */
    const DEDUP_COOLDOWN = 'cooldown';
    /** No de-duplication; reproduces the uncapped collection behaviour. */
    const DEDUP_RAW = 'raw';

    /** Keep XP that the event replay cannot account for (grades, manual awards, legacy rules). */
    const RESIDUAL_PRESERVE = 'preserve';
    /** Discard unaccounted XP; the total becomes purely what the event replay produced. */
    const RESIDUAL_DROP = 'drop';

    /** @var int Rows pulled from the log store per batch. */
    const BATCH = 5000;

    /** @var int The course ID. */
    protected $courseid;
    /** @var string One of the DEDUP_* constants. */
    protected $dedup;
    /** @var int Cooldown window in seconds, used by DEDUP_COOLDOWN. */
    protected $cooldown;
    /** @var string One of the RESIDUAL_* constants. */
    protected $residual;
    /** @var bool Whether a user's total is allowed to go up. */
    protected $allowincrease;
    /** @var callable|null Progress/debug sink. */
    protected $output;

    /**
     * Constructor.
     *
     * @param int $courseid The course to recalculate.
     * @param array $options dedup, cooldown, residual, allowincrease, output.
     */
    public function __construct($courseid, array $options = []) {
        $this->courseid = (int) $courseid;
        $this->dedup = $options['dedup'] ?? self::DEDUP_ONCE;
        $this->cooldown = (int) ($options['cooldown'] ?? 86400);
        $this->residual = $options['residual'] ?? self::RESIDUAL_PRESERVE;
        $this->allowincrease = !empty($options['allowincrease']);
        $this->output = $options['output'] ?? null;

        if (!in_array($this->dedup, [self::DEDUP_ONCE, self::DEDUP_COOLDOWN, self::DEDUP_RAW], true)) {
            throw new \coding_exception('Unknown dedup policy: ' . $this->dedup);
        }
        if (!in_array($this->residual, [self::RESIDUAL_PRESERVE, self::RESIDUAL_DROP], true)) {
            throw new \coding_exception('Unknown residual policy: ' . $this->residual);
        }
    }

    /**
     * Write a line to the output sink, if there is one.
     *
     * @param string $message The message.
     */
    protected function log($message) {
        if ($this->output) {
            call_user_func($this->output, $message);
        }
    }

    /**
     * Run the recalculation.
     *
     * @param bool $execute False to compute without writing anything.
     * @return array Per-user rows plus a summary, suitable for printing or storing.
     */
    public function run($execute = false) {
        global $DB;

        $world = \block_xp\di::get('course_world_factory')->get_world($this->courseid);
        if (!$world->get_config()->get('enabled')) {
            throw new \moodle_exception('XP is not enabled for course ' . $this->courseid);
        }

        $context = context_course::instance($this->courseid);
        $filtermanager = $world->get_filter_manager();
        $store = $world->get_store();

        // Replay the log to get what the event rules alone would award.
        $replayed = $this->replay($filtermanager, $context);

        // What the same replay would produce with no de-duplication at all. This is the
        // best available reconstruction of how the stored totals were built, so the
        // difference against the stored total is XP the event rules cannot explain --
        // grade-based rules, manual awards, or rules that have since been edited.
        $raw = $this->dedup === self::DEDUP_RAW ? $replayed : $this->replay($filtermanager, $context, true);

        $stored = [];
        foreach ($DB->get_records('block_xp', ['courseid' => $this->courseid], '', 'userid, xp') as $r) {
            $stored[(int) $r->userid] = (int) $r->xp;
        }

        $userids = array_unique(array_merge(array_keys($replayed), array_keys($stored)));
        sort($userids);

        $rows = [];
        foreach ($userids as $userid) {
            $before = $stored[$userid] ?? 0;
            $events = $replayed[$userid] ?? 0;
            $rawevents = $raw[$userid] ?? 0;

            // Anything in the stored total that the raw replay does not account for.
            $unaccounted = max(0, $before - $rawevents);
            $after = $events + ($this->residual === self::RESIDUAL_PRESERVE ? $unaccounted : 0);

            // The replay applies TODAY's filters to ALL of history, so activity that
            // predates a rule being added earns under it retroactively. That can push a
            // total above what the user was ever actually awarded -- and for someone with
            // no prior record at all, conjure XP out of nothing. Unless explicitly allowed,
            // clamp: this tool exists to remove over-awarded points, not to grant new ones.
            $capped = false;
            if (!$this->allowincrease && $after > $before) {
                $after = $before;
                $capped = true;
            }

            $rows[$userid] = (object) [
                'userid' => $userid,
                'before' => $before,
                'events' => $events,
                'rawevents' => $rawevents,
                'unaccounted' => $unaccounted,
                'after' => $after,
                'delta' => $after - $before,
                'capped' => $capped,
            ];
        }

        if ($execute) {
            $this->backup($rows);
            $changed = 0;
            foreach ($rows as $row) {
                if ($row->after === $row->before) {
                    continue;
                }
                // set() recomputes the level from the new total and fires the store observers.
                $store->set($row->userid, $row->after);
                $changed++;
            }
            $this->log("Wrote {$changed} changed user total(s).");
        }

        return $rows;
    }

    /**
     * Replay the course's log through the event filters.
     *
     * @param \block_xp\local\xp\course_filter_manager $filtermanager The filter manager.
     * @param \context $context The course context.
     * @param bool $forceraw True to ignore the dedup policy and count every match.
     * @return array userid => points.
     */
    protected function replay($filtermanager, $context, $forceraw = false) {
        $reader = $this->get_reader();
        $totals = [];
        $seen = [];
        $canearn = [];

        $select = 'courseid = :courseid';
        $params = ['courseid' => $this->courseid];
        $total = $reader->get_events_select_count($select, $params);
        $label = $forceraw ? 'baseline (no de-duplication)' : "policy '{$this->dedup}'";
        $this->log("Replaying {$total} log entries for course {$this->courseid} — {$label}...");

        $offset = 0;
        while ($offset < $total) {
            $events = $reader->get_events_select($select, $params, 'timecreated ASC, id ASC', $offset, self::BATCH);
            foreach ($events as $event) {
                $userid = (int) $event->userid;
                if (!$userid) {
                    continue;
                }

                $points = $filtermanager->get_points_for_event($event);
                if ($points === null || $points <= 0) {
                    continue;
                }

                // Same capability gate the live collection strategy applies. Note this uses
                // current capabilities, not whatever was true when the event fired.
                if (!array_key_exists($userid, $canearn)) {
                    $canearn[$userid] = user_utils::can_earn_points($context, $userid);
                }
                if (!$canearn[$userid]) {
                    continue;
                }

                if (!$forceraw && !$this->accept($event, $userid, $seen)) {
                    continue;
                }

                $totals[$userid] = ($totals[$userid] ?? 0) + $points;
            }
            unset($events);
            $offset += self::BATCH;
        }

        return $totals;
    }

    /**
     * Whether this occurrence should be counted under the configured dedup policy.
     *
     * Unlike the live cheat guard, this state is per user and spans the whole replay
     * rather than living in a single session. See the key comments below for how the
     * two policies differ in what they treat as "the same action".
     *
     * @param event_base $event The event.
     * @param int $userid The user ID.
     * @param array $seen Running state, by reference.
     * @return bool
     */
    protected function accept(event_base $event, $userid, array &$seen) {
        if ($this->dedup === self::DEDUP_RAW) {
            return true;
        }

        if ($this->dedup === self::DEDUP_ONCE) {
            // Once per activity. Deliberately excludes objectid: for
            // \mod_quiz\event\attempt_submitted the objectid is the attempt, so including
            // it would make every re-attempt a distinct action and collapse nothing.
            $key = $userid . '|' . $event->eventname . '|' . $event->contextinstanceid;
            if (isset($seen[$key])) {
                return false;
            }
            $seen[$key] = true;
            return true;
        }

        // Cooldown keys mirror the live cheat guard's action key exactly, objectid included,
        // so this answers "what would timebetweensameactions have done?".
        $key = $userid . '|' . $event->eventname . '|' . $event->contextinstanceid . '|' . $event->objectid;

        // Events are replayed in chronological order, so the last time we accepted
        // this key is all we need to keep.
        $time = (int) $event->timecreated;
        if (isset($seen[$key]) && $time - $seen[$key] < $this->cooldown) {
            return false;
        }
        $seen[$key] = $time;
        return true;
    }

    /**
     * Get the log reader.
     *
     * @return \core\log\sql_reader
     */
    protected function get_reader() {
        $readers = get_log_manager()->get_readers('core\log\sql_reader');
        foreach ($readers as $reader) {
            if ($reader instanceof \logstore_standard\log\store) {
                return $reader;
            }
        }
        if (empty($readers)) {
            throw new \moodle_exception('No SQL log reader is enabled; cannot replay history.');
        }
        return reset($readers);
    }

    /**
     * Dump the current totals to a file before overwriting them.
     *
     * @param array $rows The computed rows.
     * @return string The file path.
     */
    protected function backup(array $rows) {
        global $CFG;

        $dir = $CFG->dataroot . '/local_xprecalc';
        if (!is_dir($dir)) {
            mkdir($dir, $CFG->directorypermissions, true);
        }
        $path = $dir . '/course-' . $this->courseid . '-' . date('Ymd-His') . '.json';
        file_put_contents($path, json_encode([
            'courseid' => $this->courseid,
            'time' => time(),
            'dedup' => $this->dedup,
            'cooldown' => $this->cooldown,
            'residual' => $this->residual,
            'totals' => array_map(function($r) {
                return ['userid' => $r->userid, 'xp' => $r->before];
            }, array_values($rows)),
        ], JSON_PRETTY_PRINT));

        $this->log("Backed up previous totals to {$path}");
        return $path;
    }
}
