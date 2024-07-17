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
 * @package BizLMS
 * @subpackage local_custom_matrix
 */

// We defined the web service functions to install.

defined('MOODLE_INTERNAL') || die;
$functions = array(
    'local_custom_matrix_submit_custom_matrix_form' => array(
        'classname'   => 'local_custom_matrix_external',
        'methodname'  => 'submit_custom_matrix_form',
        'classpath'   => 'local/custom_matrix/classes/external.php',
        'description' => 'Submit form',
        'type'        => 'write',
        'ajax' => true,
    ),
    'local_custom_matrix_custom_matrix_view' => array(
        'classname'   => 'local_custom_matrix_external',
        'methodname'  => 'managecustom_matrix',
        'classpath'   => 'local/custom_matrix/classes/external.php',
        'description' => 'Display the custom_matrix Page',
        'type'        => 'write',
        'ajax' => true,
        'services' => array(MOODLE_OFFICIAL_MOBILE_SERVICE),
    ),
    'local_custom_matrix_matrix_view' => array(
        'classname'   => 'local_custom_matrix_external',
        'methodname'  => 'matrix_view',
        'classpath'   => 'local/custom_matrix/classes/external.php',
        'description' => 'Display the matrix view',
        'type'        => 'write',
        'ajax' => true,        
    ),
    'local_custom_matrix_user_matrix_save' => array(
        'classname'   => 'local_custom_matrix_external',
        'methodname'  => 'users_matrix_save',
        'classpath'   => 'local/custom_matrix/classes/external.php',
        'description' => 'Save user matrix view',
        'type'        => 'write',
        'ajax' => true,        
    ),
    'local_custom_matrix_user_matrix_view' => array(
        'classname'   => 'local_custom_matrix_external',
        'methodname'  => 'user_matrix_view',
        'classpath'   => 'local/custom_matrix/classes/external.php',
        'description' => 'Display the user_matrix Page',
        'type'        => 'write',
        'ajax' => true,        
    ),
    'local_custom_matrix_matrix_save' => array(
        'classname'   => 'local_custom_matrix_external',
        'methodname'  => 'matrix_save',
        'classpath'   => 'local/custom_matrix/classes/external.php',
        'description' => 'Save matrix view',
        'type'        => 'write',
        'ajax' => true,        
    ),
    // 'local_custom_matrix_template_view' => array(
    //     'classname'   => 'local_custom_matrix_external',
    //     'methodname'  => 'managetemplate_view',
    //     'classpath'   => 'local/custom_matrix/classes/external.php',
    //     'description' => 'Display the template view Page',
    //     'type'        => 'write',
    //     'ajax' => true,
    //     'services' => array(MOODLE_OFFICIAL_MOBILE_SERVICE),
    // ),
    // 'local_custom_matrix_submit_template_form' => array(
    //     'classname'   => 'local_custom_matrix_external',
    //     'methodname'  => 'submit_template_form',
    //     'classpath'   => 'local/custom_matrix/classes/external.php',
    //     'description' => 'Submit template form',
    //     'type'        => 'write',
    //     'ajax' => true,        
    // ),
    

);
