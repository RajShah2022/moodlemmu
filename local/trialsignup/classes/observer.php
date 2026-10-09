<?php
/**
 * Event observer for local_trialsignup.
 *
 * On user_created (fired by auth_email's user_signup → user_create_user):
 *   1. Set mdl_user.open_path to the configured cost-centre's path.
 *   2. Set mdl_user.trialsignup_partnerid (from session, if present).
 *   3. Assign the configured role at that cost-centre's category context.
 *   4. Add user to the cohort. Cohort sync handles enrolments.
 *   5. Record the assignment in tracking table for the expire task.
 *
 * @package    local_trialsignup
 * @copyright  2026 MathMeUp
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_trialsignup;

defined('MOODLE_INTERNAL') || die();

class observer {

    public static function user_created(\core\event\user_created $event) {
        global $DB, $SESSION, $CFG;

        require_once($CFG->dirroot . '/cohort/lib.php');
        require_once($CFG->libdir . '/accesslib.php');
        require_once($CFG->dirroot . '/local/trialsignup/lib.php');

        $userid = (int) $event->objectid;
        if (!$userid) {
            return;
        }

        $user = $DB->get_record('user', ['id' => $userid], 'id, auth, deleted');
        if (!$user || $user->deleted) {
            return;
        }

        if ($user->auth !== 'email') {
            return;
        }

        // Read trial signup context from session.
        $cohortid = !empty($SESSION->local_trialsignup_cohortid)
            ? (int) $SESSION->local_trialsignup_cohortid
            : 0;
        $partnerid = !empty($SESSION->local_trialsignup_partnerid)
            ? (int) $SESSION->local_trialsignup_partnerid
            : 0;

        unset($SESSION->local_trialsignup_cohortid);
        unset($SESSION->local_trialsignup_partnerid);

        if (!$cohortid) {
            // Not a trial signup.
            return;
        }

        // Allowlist re-check.
        $eligible = local_trialsignup_get_eligible_cohort_ids();
        if (!in_array($cohortid, $eligible, true)) {
            self::log_error("user {$userid}: cohort {$cohortid} not in eligible allowlist");
            return;
        }

        // Re-validate partner if provided.
        if ($partnerid > 0) {
            $partner = local_trialsignup_get_partner($partnerid);
            if (!$partner || !$partner->active) {
                self::log_error("user {$userid}: partner {$partnerid} invalid or inactive at observer time");
                $partnerid = 0; // Don't write a stale partnerid.
            }
        }

        $costcenterid = (int) get_config('local_trialsignup', 'costcenter');
        $roleid       = (int) get_config('local_trialsignup', 'roleid');
        $trialdays    = (int) get_config('local_trialsignup', 'trialdays');
        if ($trialdays <= 0) {
            $trialdays = 7;
        }

        try {
            // ---- Step 1: open_path + partner attribution ----
            $costcenter = $DB->get_record('local_costcenter', ['id' => $costcenterid],
                'id, path, category', IGNORE_MISSING);
            if (!$costcenter || empty($costcenter->path) || empty($costcenter->category)) {
                throw new \moodle_exception('error_costcenter_missing', 'local_trialsignup');
            }

            $userupdate = new \stdClass();
            $userupdate->id = $userid;
            $userupdate->open_path = $costcenter->path;
            if ($partnerid > 0) {
                $userupdate->trialsignup_partnerid = $partnerid;
            }
            $DB->update_record('user', $userupdate);

            // ---- Step 2: role at category context ----
            $role = $DB->get_record('role', ['id' => $roleid], 'id, shortname', IGNORE_MISSING);
            if (!$role) {
                throw new \moodle_exception('error_role_missing', 'local_trialsignup');
            }
            $categorycontext = \context_coursecat::instance($costcenter->category);
            role_assign($role->id, $userid, $categorycontext->id);

            // ---- Step 3: cohort + tracking ----
            $now = time();
            $expires = $now + ($trialdays * DAYSECS);
            self::assign_to_cohort($userid, $cohortid, $now, $expires);

            \context_helper::reset_caches();

        } catch (\Throwable $e) {
            self::log_error("user {$userid}: " . $e->getMessage());
        }
    }

    private static function assign_to_cohort(int $userid, int $cohortid,
            int $timeadded, int $expires): void {
        global $DB;

        if (!$DB->record_exists('cohort', ['id' => $cohortid])) {
            self::log_error("cohort {$cohortid} does not exist");
            return;
        }

        if (!$DB->record_exists('cohort_members',
                ['cohortid' => $cohortid, 'userid' => $userid])) {
            try {
                cohort_add_member($cohortid, $userid);
            } catch (\Throwable $e) {
                self::log_error("cohort_add_member({$cohortid}, {$userid}) failed: "
                    . $e->getMessage());
                return;
            }
        }

        $existing = $DB->get_record('local_trialsignup_assignments',
            ['userid' => $userid, 'cohortid' => $cohortid]);

        if ($existing) {
            $existing->timeadded = $timeadded;
            $existing->expires   = $expires;
            $DB->update_record('local_trialsignup_assignments', $existing);
        } else {
            $DB->insert_record('local_trialsignup_assignments', (object) [
                'userid'    => $userid,
                'cohortid'  => $cohortid,
                'timeadded' => $timeadded,
                'expires'   => $expires,
            ]);
        }
    }

    private static function log_error(string $message): void {
        debugging('local_trialsignup: ' . $message, DEBUG_DEVELOPER);
        error_log('local_trialsignup ERROR: ' . $message);
    }
}
