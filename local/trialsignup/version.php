<?php
/**
 * Version details for local_trialsignup.
 *
 * v0.6.0 — Trial extension page:
 *   - New admin page to search users and extend their trial by 7 days
 *   - Handles both active (push expiry) and expired (reactivate + re-add to cohort)
 *
 * @package    local_trialsignup
 * @copyright  2026 MathMeUp
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$plugin->component = 'local_trialsignup';
$plugin->version   = 2026051101;
$plugin->requires  = 2023100900;
$plugin->maturity  = MATURITY_BETA;
$plugin->release   = '0.6.0';
