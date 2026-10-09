<?php
/**
 * Shared helpers for local_trialsignup.
 *
 * Also implements local_trialsignup_extend_navigation() which fires on
 * every page load and shows an expired-trial banner to users whose
 * trial has ended.
 *
 * @package    local_trialsignup
 * @copyright  2026 MathMeUp
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Read configured eligible cohort IDs from plugin settings.
 *
 * @return int[]
 */
function local_trialsignup_get_eligible_cohort_ids(): array {
    $raw = get_config('local_trialsignup', 'eligiblecohorts');
    if (empty($raw)) {
        return [];
    }
    return array_values(array_filter(array_map('intval', explode(',', $raw))));
}

/**
 * Get all partners ordered: active first (alphabetical), then inactive (alphabetical).
 *
 * @param bool|null $active null = all, true = active only, false = inactive only
 * @return \stdClass[] indexed by id
 */
function local_trialsignup_get_partners(?bool $active = null): array {
    global $DB;

    $where = '';
    $params = [];
    if ($active === true) {
        $where = 'WHERE active = 1';
    } else if ($active === false) {
        $where = 'WHERE active = 0';
    }

    return $DB->get_records_sql("
        SELECT id, name, active, timecreated
          FROM {local_trialsignup_partners}
        $where
      ORDER BY active DESC, name ASC
    ", $params);
}

/**
 * Look up a partner by ID. Returns null if not found.
 *
 * @param int $partnerid
 * @return \stdClass|null
 */
function local_trialsignup_get_partner(int $partnerid): ?\stdClass {
    global $DB;

    if ($partnerid <= 0) {
        return null;
    }

    $row = $DB->get_record('local_trialsignup_partners', ['id' => $partnerid]);
    return $row ?: null;
}

/**
 * Navigation hook — fires on every page load.
 *
 * Checks if the current user is an expired trial user (has at least one
 * row in local_trialsignup_assignments with status='expired') and, if so,
 * injects a persistent warning notification with the configured contact email.
 *
 * The DB check is cached in $SESSION to avoid a query on every page load.
 * Cache is set once per session; if a user's trial expires mid-session,
 * the banner appears after their next login.
 *
 * @param global_navigation $nav
 */
function local_trialsignup_extend_navigation(global_navigation $nav) {
    global $USER, $DB, $SESSION;

    // Only for logged-in, non-guest, non-admin users.
    if (!isloggedin() || isguestuser() || is_siteadmin()) {
        return;
    }

    // Check once per session to avoid DB hit on every page.
    if (!isset($SESSION->local_trialsignup_expired_check)) {
        $SESSION->local_trialsignup_expired_check = $DB->record_exists_select(
            'local_trialsignup_assignments',
            'userid = :uid AND status = :status',
            ['uid' => $USER->id, 'status' => 'expired']
        );
    }

    if (!$SESSION->local_trialsignup_expired_check) {
        return;
    }

    // Also check they have NO active assignments (if they have both active and
    // expired rows — e.g. signed up for a second cohort — don't show the banner).
    if (!isset($SESSION->local_trialsignup_has_active)) {
        $SESSION->local_trialsignup_has_active = $DB->record_exists_select(
            'local_trialsignup_assignments',
            'userid = :uid AND status = :status',
            ['uid' => $USER->id, 'status' => 'active']
        );
    }

    if ($SESSION->local_trialsignup_has_active) {
        return;
    }

    // Build the banner message.
    $contactemail = get_config('local_trialsignup', 'contactphone');  // Config key kept for backward compat.
    if (!empty($contactemail)) {
        $message = get_string('expired_banner', 'local_trialsignup', $contactemail);
    } else {
        $message = get_string('expired_banner_noemail', 'local_trialsignup');
    }

    // Add as a Moodle notification. This fires before page render, so the
    // notification displays at the top of the page. It's added on every
    // page load (extend_navigation fires each time) but cleared after
    // display, so it persists across navigations as intended.
    \core\notification::add($message, \core\notification::WARNING);
}
