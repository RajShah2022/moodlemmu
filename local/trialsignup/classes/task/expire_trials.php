<?php
/**
 * Scheduled task: expire trial signups.
 *
 * For each assignment whose `expires` timestamp is in the past and status is 'active':
 *   - Remove the user from the cohort (cohort sync removes their enrolments)
 *   - Set the assignment status to 'expired' (row is kept for reporting
 *     and to trigger the expired-trial banner)
 *
 * Only touches assignments this plugin created. Admin-added cohort members
 * are never affected.
 *
 * @package    local_trialsignup
 * @copyright  2026 MathMeUp
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_trialsignup\task;

defined('MOODLE_INTERNAL') || die();

class expire_trials extends \core\task\scheduled_task {

    public function get_name() {
        return get_string('task_expire_trials', 'local_trialsignup');
    }

    public function execute() {
        global $DB, $CFG;
        require_once($CFG->dirroot . '/cohort/lib.php');

        $now = time();

        // Only process active assignments that have passed their expiry.
        $expired = $DB->get_records_select(
            'local_trialsignup_assignments',
            'expires <= :now AND status = :status',
            ['now' => $now, 'status' => 'active'],
            'expires ASC'
        );

        if (empty($expired)) {
            mtrace('local_trialsignup: no expired assignments.');
            return;
        }

        $total = count($expired);
        $removed = 0;
        $failed = 0;

        mtrace("local_trialsignup: processing {$total} expired assignment(s)...");

        foreach ($expired as $a) {
            try {
                // Remove from cohort if still a member.
                if ($DB->record_exists('cohort', ['id' => $a->cohortid])) {
                    if ($DB->record_exists('cohort_members',
                            ['cohortid' => $a->cohortid, 'userid' => $a->userid])) {
                        cohort_remove_member($a->cohortid, $a->userid);
                    }
                }

                // Mark as expired instead of deleting — preserves the audit trail
                // and enables the expired-trial banner.
                $DB->set_field('local_trialsignup_assignments', 'status', 'expired',
                    ['id' => $a->id]);
                $removed++;
                mtrace("  ✓ user {$a->userid} removed from cohort {$a->cohortid} (status → expired)");

            } catch (\Throwable $e) {
                $failed++;
                mtrace("  ✗ failed for user {$a->userid} / cohort {$a->cohortid}: "
                    . $e->getMessage());
                // Don't mark as expired — next run will retry.
            }
        }

        mtrace("local_trialsignup: done. removed={$removed}, failed={$failed}");
    }
}
