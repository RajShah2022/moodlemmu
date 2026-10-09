<?php
/**
 * Scheduled tasks for local_trialsignup.
 *
 * @package    local_trialsignup
 * @copyright  2026 MathMeUp
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$tasks = [
    [
        'classname' => 'local_trialsignup\task\expire_trials',
        'blocking'  => 0,
        'minute'    => '5',
        'hour'      => '2',
        'day'       => '*',
        'dayofweek' => '*',
        'month'     => '*',
    ],
];
