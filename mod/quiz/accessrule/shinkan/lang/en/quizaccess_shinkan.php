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
 * TODO describe file shinkan
 *
 * @package    quizaccess_shinkan
 * @copyright  2023 YOUR NAME <your@email.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
$string['pluginname'] = 'Shinkan';
$string['setting:startrecord_url'] = 'Start Recording API URL';
$string['setting:startrecord_url_desc'] = 'API URL to start recording once user start to attempt the Quiz!!';
$string['setting:stoprecord_url'] = 'Stop Recording API URL';
$string['setting:stoprecord_url_desc'] = 'API URL to stop recording once user submit the Quiz!!';
$string['setting:token_id'] = 'Token ID';
$string['setting:token_id_desc'] = 'Token ID';
$string['setting:client_id'] = 'Client ID';
$string['setting:client_id_desc'] = 'Client ID';
$string['setting:sub_client_id'] = 'Sub Client ID';
$string['setting:sub_client_id_desc'] = 'Sub Client ID';

$string['proctoringrequired'] = 'Webcam identity validation';
$string['proctoringrequired_help'] = 'If you enable this option, students will not be able to start an attempt until they have ticked a check-box confirming that they are aware of the policy on webcam.';
$string['proctoringrequiredoption'] = 'must be acknowledged before starting an attempt';
$string['notrequired'] = "not required";
$string['proctoringchecklabel'] = "I agree with the validation process.";
$string['proctoringcheckstatement'] = 'This exam requires webcam access.<br />(Please allow webcam access).';
$string['proctoringcheckheader'] = '<strong>To continue with this quiz attempt you must open your webcam.</strong>';
$string['pleaseselect'] = 'You must select this to validate';
$string['summaryreport'] = 'Summary Report';