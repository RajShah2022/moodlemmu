<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * External functions and service declaration for Shinkan
 *
 * Documentation: {@link https://moodledev.io/docs/apis/subsystems/external/description}
 *
 * @package    quizaccess_shinkan
 * @category   webservice
 * @copyright  2023 YOUR NAME <your@email.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    'quizaccess_shinkan_api_call' => array(
        'classname' => 'quizaccess_shinkan_external',
        'methodname' => 'shinkan_api_call',
        'description' => 'Shinkan api call.',
        'type' => 'write',
        'ajax'        => true,
    ),
];

$services = [
];

