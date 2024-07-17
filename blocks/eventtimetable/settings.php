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
 * Course list block settings
 *
 * @package    block_eventtimetable
 * @copyright  2007 Petr Skoda
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

if ($ADMIN->fulltree) {

    $settings->add(new admin_setting_configcolourpicker('block_eventtimetable/courseeventcolor',
     get_string('courseeventcolor', 'block_eventtimetable'),
     get_string('courseeventcolor_desc',
        'block_eventtimetable'), '#025187'));

    $settings->add(new admin_setting_configcolourpicker('block_eventtimetable/classroomeventcolor',
     get_string('classroomeventcolor', 'block_eventtimetable'),
     get_string('classroomeventcolor_desc',
        'block_eventtimetable'), '#025187'));
}

