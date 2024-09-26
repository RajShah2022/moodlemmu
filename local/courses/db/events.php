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
 * local courses
 *
 * @package    local_courses
 * @copyright  2019 eAbyas <eAbyas.in>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_courses\event\coursetopic_added;
use local_courses\event\coursetopic_updated;
use local_courses\event\courseclass_added;
use local_courses\event\courseclass_updated;

//List of observers.
$observers = array(

    array(
        'eventname'   => '\core\event\course_viewed',
        'callback'    => 'local_courses_observer::course_viewed',
    ),
    array(
        'eventname'   => '\core\event\course_completed',
        'callback'    => 'local_courses_observer::course_completed_notification',
    ),
    array(
        'eventname'   => coursetopic_added::class,
        'callback'    => 'local_courses::coursetopic_added',
    ),
    array(
        'eventname'   => coursetopic_updated::class,
        'callback'    => 'local_courses::coursetopic_updated',
    ),
    array(
        'eventname'   => courseclass_added::class,
        'callback'    => 'local_courses::courseclass_added',
    ),
    array(
        'eventname'   => courseclass_updated::class,
        'callback'    => 'local_courses::courseclass_updated',
    ),
);
