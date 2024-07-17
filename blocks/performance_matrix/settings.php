<?php
/**
 * This file is part of eAbyas
 *
 * Copyright eAbyas Info Solutons Pvt Ltd, India
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @author eabyas  <info@eabyas.in>
 */
defined('MOODLE_INTERNAL') || die;
$settings = new admin_settingpage('block_performance_matrix', get_string('matrix_graphconfig', 'block_performance_matrix'));
$ADMIN->add('blocks', $settings);
if ($ADMIN->fulltree) {

    $settings->add(new admin_setting_configtext('block_performance_matrix/performancetype',
                get_string('performancetype', 'block_performance_matrix'),
                get_string('performancetype', 'block_performance_matrix'),
                '', PARAM_RAW ));

    $settings->add(new admin_setting_configtext('block_performance_matrix/barcolor',
                get_string('barcolor', 'block_performance_matrix'),
                get_string('barcolor', 'block_performance_matrix'),
                '', PARAM_RAW ));

    $settings->add(new admin_setting_configtext('block_performance_matrix/graphtype',
                get_string('graphtype', 'block_performance_matrix'),
                get_string('graphtype', 'block_performance_matrix'),
                '', PARAM_RAW ));


}


