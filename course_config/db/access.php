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
 * Comments block caps.
 *
 * @package    block_course_config
 * @copyright  2024 Rizwana <rizwana.madire@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$capabilities = array(

    // Whether or not the user can add the block on their dashboard.
    // We don't allow anyone to add it, the teacher/manager should add it
    // to their default dashboards.
    // 'block/course_config:myaddinstance' => array(
    //     'captype' => 'write',
    //     'contextlevel' => CONTEXT_COURSECAT,
    //     'archetypes' => [],
    //     'clonepermissionsfrom' => 'moodle/my:manageblocks'
    // ),

    // Whether or not the user can add the block.
    'block/course_config:addinstance' => array(
        'riskbitmask' => RISK_SPAM | RISK_XSS,

        'captype' => 'write',
        'contextlevel' => CONTEXT_BLOCK,
        'archetypes' => array(
            'teacher' => CAP_ALLOW,
            'manager' => CAP_ALLOW
        ),

        'clonepermissionsfrom' => 'moodle/site:manageblocks'
    ),

    // Whether or not a user can see the block.
    'block/course_config:view' => array(
        'captype' => 'write',
        'contextlevel' => CONTEXT_COURSE,
        'archetypes' => array(
            'manager'        => CAP_PROHIBIT,
            'coursecreator'  => CAP_PROHIBIT,
            'editingteacher' => CAP_PROHIBIT,
            'teacher'        => CAP_ALLOW,
            'student'        => CAP_PROHIBIT,
            'guest'          => CAP_PROHIBIT,
            'user'           => CAP_PROHIBIT,
            'frontpage'      => CAP_PROHIBIT
        ),
        'clonepermissionsfrom' => 'block/course_config:addinstance',
    ),

    // Whether or not the user can manage all aspects of the block.
    // 'block/course_config:manage' => array(
    //     'captype' => 'write',
    //     'contextlevel' => CONTEXT_COURSE,
    //     'archetypes' => array(
    //         'manager'        => CAP_PROHIBIT,
    //         'coursecreator'  => CAP_PROHIBIT,
    //         'editingteacher' => CAP_PROHIBIT,
    //         'teacher'        => CAP_ALLOW,
    //         'student'        => CAP_PROHIBIT,
    //         'guest'          => CAP_PROHIBIT,
    //         'user'           => CAP_PROHIBIT,
    //         'frontpage'      => CAP_PROHIBIT
    //     ),
    // )
);
