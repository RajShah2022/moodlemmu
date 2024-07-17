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
 * @package Bizlms
 * @subpackage local_custom_matrix
 */
defined('MOODLE_INTERNAL') || die;

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_custom_matrix', get_string('pluginname', 'local_custom_matrix'));


    $ADMIN->add('localplugins', $settings);

    $menu = array(0=>new lang_string('select_no_role', 'local_custom_matrix'),1=>new lang_string('select_role_user_designation', 'local_custom_matrix'),2=>new lang_string('select_role_user_position', 'local_custom_matrix'));


    $name = new lang_string('performance_matrix_role_type', 'local_custom_matrix');
    $description = new lang_string('performance_matrix_role_type_desc', 'local_custom_matrix');
    $settings->add(new admin_setting_configselect('local_custom_matrix/performance_matrix_role_type',
                                                      $name,
                                                      $description,
                                                      'performance_matrix_role_type_comments',
                                                      $menu));

    $period_types = array(0=>new lang_string('select_monthly', 'local_custom_matrix'),1=>new lang_string('select_quarterly', 'local_custom_matrix'),2=>new lang_string('select_halfyearly', 'local_custom_matrix'),3=>new lang_string('select_yearly', 'local_custom_matrix'));
    $period_name = new lang_string('performance_period_name', 'local_custom_matrix');
    $period_description = new lang_string('performance_period_name_desc', 'local_custom_matrix');

    $setting = new admin_setting_configselect('local_custom_matrix/performance_period_type',
                                                  $period_name,
                                                  $period_description,
                                                  'performance_period_name_comments',
                                                  $period_types);   
    $settings->add($setting);
}

