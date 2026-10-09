<?php
/**
 * Event observer registration.
 *
 * @package    local_trialsignup
 * @copyright  2026 MathMeUp
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$observers = [
    [
        'eventname' => '\core\event\user_created',
        'callback'  => '\local_trialsignup\observer::user_created',
        'priority'  => 9999,
        'internal'  => true,
    ],
];
