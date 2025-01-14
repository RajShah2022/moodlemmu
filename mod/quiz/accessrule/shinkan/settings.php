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
 * TODO describe file settings
 *
 * @package    quizaccess_shinkan
 * @copyright  2023 YOUR NAME <your@email.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

global $ADMIN;

if ($hassiteconfig) {

    $settings->add(new admin_setting_configtext('quizaccess_shinkan/startrecord_url',
        get_string('setting:startrecord_url', 'quizaccess_shinkan'),
        get_string('setting:startrecord_url_desc', 'quizaccess_shinkan'), '', PARAM_TEXT));
    
    $settings->add(new admin_setting_configtext('quizaccess_shinkan/stoprecord_url',
        get_string('setting:stoprecord_url', 'quizaccess_shinkan'),
        get_string('setting:stoprecord_url_desc', 'quizaccess_shinkan'), '', PARAM_TEXT));

    $settings->add(new admin_setting_configpasswordunmask('quizaccess_shinkan/token_id',
        get_string('setting:token_id', 'quizaccess_shinkan'),
        get_string('setting:token_id_desc', 'quizaccess_shinkan'), '', PARAM_TEXT));

    
    $settings->add(new admin_setting_configtext('quizaccess_shinkan/client_id',
        get_string('setting:client_id', 'quizaccess_shinkan'),
        get_string('setting:client_id_desc', 'quizaccess_shinkan'), '', PARAM_TEXT));
    

    $settings->add(new admin_setting_configtext('quizaccess_shinkan/sub_client_id',
        get_string('setting:sub_client_id', 'quizaccess_shinkan'),
        get_string('setting:sub_client_id_desc', 'quizaccess_shinkan'), '', PARAM_TEXT));
   

}
