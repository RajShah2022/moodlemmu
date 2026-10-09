<?php
/**
 * Trial Extension page.
 *
 * Lets admin search for trial users and extend their trial by 7 days.
 * Handles both active trials (push expiry forward) and expired trials
 * (reactivate + re-add to cohort).
 *
 * URL: /local/trialsignup/extend.php
 *
 * @package    local_trialsignup
 * @copyright  2026 MathMeUp
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->dirroot . '/cohort/lib.php');
require_once(__DIR__ . '/lib.php');

admin_externalpage_setup('local_trialsignup_extend');

$context = context_system::instance();
require_capability('moodle/site:config', $context);

$trialpath = '/1/18';
$extenddays = 7;

$action = optional_param('action', '', PARAM_ALPHA);
$search = optional_param('q', '', PARAM_TEXT);
$userid = optional_param('userid', 0, PARAM_INT);

$pageurl = new moodle_url('/local/trialsignup/extend.php');
$PAGE->set_url($pageurl);

// =====================================================================
// Handle extend action
// =====================================================================
if ($action === 'extend' && $userid > 0) {
    require_sesskey();

    $result = local_trialsignup_extend_trial($userid, $extenddays, $trialpath);

    if ($result['success']) {
        redirect(new moodle_url('/local/trialsignup/extend.php', ['q' => $search]),
            $result['message'],
            null, \core\output\notification::NOTIFY_SUCCESS);
    } else {
        redirect(new moodle_url('/local/trialsignup/extend.php', ['q' => $search]),
            $result['message'],
            null, \core\output\notification::NOTIFY_ERROR);
    }
}

// =====================================================================
// Page render
// =====================================================================
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('extend_pagetitle', 'local_trialsignup'));

// Search form.
echo '<form method="get" action="' . $pageurl->out(false) . '" class="form-inline mb-4">';
echo '<label class="mr-2 font-weight-bold" for="q">' .
    get_string('extend_search_label', 'local_trialsignup') . '</label>';
echo '<input type="text" name="q" id="q" value="' . s($search) .
    '" class="form-control mr-2" style="min-width: 300px;" ' .
    'placeholder="' . get_string('extend_search_placeholder', 'local_trialsignup') . '">';
echo '<button type="submit" class="btn btn-primary">' .
    get_string('extend_search_button', 'local_trialsignup') . '</button>';
echo '</form>';

// Search results.
if ($search !== '') {
    $searchterm = '%' . $DB->sql_like_escape($search) . '%';

    $users = $DB->get_records_sql("
        SELECT u.id, u.username, u.firstname, u.lastname, u.email,
               u.timecreated, u.lastaccess,
               a.id AS assignmentid, a.cohortid, a.status, a.expires,
               co.name AS cohortname
        FROM {user} u
        LEFT JOIN {local_trialsignup_assignments} a ON a.userid = u.id
        LEFT JOIN {cohort} co ON co.id = a.cohortid
        WHERE u.deleted = 0
          AND u.open_path = :path
          AND (u.username LIKE :s1
               OR u.firstname LIKE :s2
               OR u.lastname LIKE :s3
               OR u.email LIKE :s4
               OR CONCAT(u.firstname, ' ', u.lastname) LIKE :s5)
        ORDER BY u.lastname, u.firstname
        LIMIT 50
    ", [
        'path' => $trialpath,
        's1' => $searchterm,
        's2' => $searchterm,
        's3' => $searchterm,
        's4' => $searchterm,
        's5' => $searchterm,
    ]);

    if (empty($users)) {
        echo html_writer::div(
            get_string('extend_noresults', 'local_trialsignup', s($search)),
            'alert alert-info'
        );
    } else {
        echo html_writer::tag('p',
            get_string('extend_results_count', 'local_trialsignup',
                (object) ['count' => count($users), 'query' => s($search)]),
            ['class' => 'text-muted mb-2']
        );

        $table = new html_table();
        $table->head = [
            get_string('extend_col_username', 'local_trialsignup'),
            get_string('extend_col_name', 'local_trialsignup'),
            get_string('extend_col_email', 'local_trialsignup'),
            get_string('extend_col_cohort', 'local_trialsignup'),
            get_string('extend_col_status', 'local_trialsignup'),
            get_string('extend_col_expires', 'local_trialsignup'),
            get_string('extend_col_action', 'local_trialsignup'),
        ];
        $table->attributes['class'] = 'admintable generaltable';

        foreach ($users as $u) {
            // Status badge.
            $status = $u->status ?? 'no record';
            if ($status === 'active') {
                $statushtml = '<span class="badge badge-success">active</span>';
            } else if ($status === 'expired') {
                $statushtml = '<span class="badge badge-warning">expired</span>';
            } else {
                $statushtml = '<span class="badge badge-secondary">' . s($status) . '</span>';
            }

            // Expiry date.
            $expiryhtml = $u->expires ? userdate($u->expires, '%Y-%m-%d %H:%M') : '—';

            // What the extend button will do.
            if ($status === 'active') {
                // Push expiry 7 days from now (or from current expiry, whichever is later).
                $newexpiry = max($u->expires, time()) + ($extenddays * 86400);
                $btnlabel = get_string('extend_btn_active', 'local_trialsignup', $extenddays);
                $btnclass = 'btn-primary';
            } else if ($status === 'expired') {
                $newexpiry = time() + ($extenddays * 86400);
                $btnlabel = get_string('extend_btn_expired', 'local_trialsignup', $extenddays);
                $btnclass = 'btn-success';
            } else {
                // No assignment record — can't extend. User needs to sign up first.
                $btnlabel = get_string('extend_btn_norecord', 'local_trialsignup');
                $btnclass = 'btn-secondary disabled';
            }

            // Extend button (POST form for CSRF protection).
            if ($status === 'active' || $status === 'expired') {
                $extendform  = '<form method="post" action="' . $pageurl->out(false) . '" style="display:inline;">';
                $extendform .= '<input type="hidden" name="action" value="extend">';
                $extendform .= '<input type="hidden" name="userid" value="' . $u->id . '">';
                $extendform .= '<input type="hidden" name="q" value="' . s($search) . '">';
                $extendform .= '<input type="hidden" name="sesskey" value="' . sesskey() . '">';
                $extendform .= '<button type="submit" class="btn btn-sm ' . $btnclass . '">' .
                    $btnlabel . '</button>';
                $extendform .= '</form>';
            } else {
                $extendform = '<span class="text-muted">' . $btnlabel . '</span>';
            }

            // New expiry preview.
            if ($status === 'active' || $status === 'expired') {
                $expiryhtml .= '<br><small class="text-muted">→ ' .
                    userdate($newexpiry, '%Y-%m-%d %H:%M') . '</small>';
            }

            $table->data[] = [
                $u->username,
                $u->firstname . ' ' . $u->lastname,
                $u->email,
                $u->cohortname ?? '—',
                $statushtml,
                $expiryhtml,
                $extendform,
            ];
        }

        echo html_writer::table($table);
    }
}

echo $OUTPUT->footer();

// =====================================================================
// Extension logic
// =====================================================================

/**
 * Extend a trial user's access by N days from now.
 *
 * Handles both active and expired states:
 *   - Active: pushes expiry forward (from max(current expiry, now) + days)
 *   - Expired: reactivates, re-adds to cohort, clears session cache
 *
 * @param int $userid
 * @param int $days Number of days to extend
 * @param string $trialpath The open_path for trial users
 * @return array ['success' => bool, 'message' => string]
 */
function local_trialsignup_extend_trial(int $userid, int $days, string $trialpath): array {
    global $DB;

    // Find the assignment.
    $assignment = $DB->get_record('local_trialsignup_assignments', ['userid' => $userid]);
    if (!$assignment) {
        return ['success' => false,
            'message' => get_string('extend_err_norecord', 'local_trialsignup')];
    }

    // Verify this is a trial user.
    $user = $DB->get_record('user', ['id' => $userid, 'deleted' => 0]);
    if (!$user || $user->open_path !== $trialpath) {
        return ['success' => false,
            'message' => get_string('extend_err_nottrialuser', 'local_trialsignup')];
    }

    $now = time();
    $wasexpired = ($assignment->status === 'expired');

    // Calculate new expiry: from max(current expiry, now) + days.
    $base = max((int) $assignment->expires, $now);
    $newexpiry = $base + ($days * DAYSECS);

    // Update the assignment.
    $assignment->status = 'active';
    $assignment->expires = $newexpiry;
    $DB->update_record('local_trialsignup_assignments', $assignment);

    // If was expired, need to re-add to cohort and clear session.
    if ($wasexpired) {
        $cohortid = (int) $assignment->cohortid;

        // Re-add to cohort if not already a member.
        if ($cohortid > 0 && $DB->record_exists('cohort', ['id' => $cohortid])) {
            if (!$DB->record_exists('cohort_members',
                    ['cohortid' => $cohortid, 'userid' => $userid])) {
                try {
                    cohort_add_member($cohortid, $userid);
                } catch (\Throwable $e) {
                    return ['success' => false,
                        'message' => 'Extended but cohort re-add failed: ' . $e->getMessage()];
                }
            }
        }

        // Clear session so expired banner disappears on next login.
        $DB->delete_records('sessions', ['userid' => $userid]);
    }

    $newexpirystr = userdate($newexpiry, '%Y-%m-%d %H:%M');
    $username = $user->firstname . ' ' . $user->lastname . ' (' . $user->username . ')';

    if ($wasexpired) {
        return ['success' => true,
            'message' => get_string('extend_success_reactivated', 'local_trialsignup',
                (object) ['name' => $username, 'expires' => $newexpirystr])];
    } else {
        return ['success' => true,
            'message' => get_string('extend_success_extended', 'local_trialsignup',
                (object) ['name' => $username, 'expires' => $newexpirystr])];
    }
}
