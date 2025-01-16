<?php

defined('MOODLE_INTERNAL') || die();

/**
 * Save an AI-generated report
 *
 * @param int $userid The user ID
 * @param int $contextid The context ID
 * @param array $reportData The AI-generated report data
 * @param array $tokensUsage Token usage information
 * @return int|false The ID of the inserted record or false on failure
 */
function create_user_ai_report($userid, $contextid, $reportData, $tokensUsage)
{
    global $DB;

    // Validate input
    if (empty($userid) || empty($contextid)) {
        return false;
    }

    $record = new stdClass();
    $record->userid = $userid;
    $record->contextid = $contextid;
    $record->ai_report_data = $reportData;
    $record->tokens_usage = json_encode($tokensUsage);
    $record->created_at = date('Y-m-d H:i:s');
    $record->updated_at = date('Y-m-d H:i:s');

    try {
        return $DB->insert_record('user_ai_reports', $record);
    } catch (Exception $e) {
        // Log the error
        debugging('Error saving AI report: ' . $e->getMessage(), DEBUG_NORMAL);
        return false;
    }
}

/**
 * Retrieve AI-generated reports for a user
 *
 * @param int $userid The user ID
 * @param int $contextid The context ID
 * @return array List of AI reports
 */
function get_user_ai_report($userid, $contextid)
{
    global $DB;

    $conditions = ['userid' => $userid, 'contextid' => $contextid];

    try {
        // Fetch the latest record only
        $report = $DB->get_record_sql(
            "SELECT * FROM mdl_user_ai_reports 
             WHERE userid = :userid AND contextid = :contextid 
             ORDER BY created_at DESC LIMIT 1",
            $conditions
        );

        if ($report) {
            // Decode the AI report data
            $reportData = json_decode($report->ai_report_data, true);
            // Return the AI report data along with created and updated timestamps
            return [
                'ai_report_data' => $reportData,
                'created_at' => $report->created_at,
                'updated_at' => $report->updated_at
            ];
        }

        return null; // No record found
    } catch (Exception $e) {
        // Log the error
        debugging('Error retrieving the latest AI report: ' . $e->getMessage(), DEBUG_NORMAL);
        return null;
    }
}

//Helper function to check if student can generate new ai report or not
function can_generate_report($student_id, $contextid) {
    global $DB;

    // Get the last report creation attempt
    $latestAttempt = $DB->get_record_sql(
        "SELECT created_at FROM mdl_user_ai_reports 
         WHERE userid = :userid AND contextid = :contextid 
         ORDER BY created_at DESC LIMIT 1",
        ['userid' => $student_id, 'contextid' => $contextid]
    );

    if ($latestAttempt) {
        $currentDate = new DateTime();
        $lastAttemptDate = new DateTime($latestAttempt->created_at);
        $interval = $currentDate->diff($lastAttemptDate);

        // Restrict if the last attempt was made within the cooldown period (7 days)
        if ($interval->days < 7) {
            return false; // Cannot generate a new report yet
        }
    }

    return true; // Allowed to generate a new report
}
/**
 * Delete AI-generated reports
 *
 * @param array $conditions Conditions for deletion
 * @return bool Success status
 */
function delete_user_ai_report($conditions)
{
    global $DB;

    try {
        return $DB->delete_records('user_ai_reports', $conditions);
    } catch (Exception $e) {
        debugging('Error deleting AI reports: ' . $e->getMessage(), DEBUG_NORMAL);
        return false;
    }
}
