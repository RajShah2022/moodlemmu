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
 * @subpackage local_positions
 */

// We defined the web service functions to install.

defined('MOODLE_INTERNAL') || die;
$functions = array(
    'local_positions_submit_position_form' => array(
        'classname'   => 'local_positions_external',
        'methodname'  => 'submit_position_form',
        'classpath'   => 'local/positions/classes/external.php',
        'description' => 'Submit positions form',
        'type'        => 'write',
        'ajax' => true,
    ),
    'local_positions_submit_domain_form' => array(
        'classname'   => 'local_positions_external',
        'methodname'  => 'submit_domain_form',
        'classpath'   => 'local/positions/classes/external.php',
        'description' => 'Submit domains form',
        'type'        => 'write',
        'ajax' => true,
    ),
    'local_parent_positions' => array(
        'classname' => 'local_positions_external',
        'methodname' => 'organization_positions',
        'classpath' => 'local/positions/externallib.php',
        'description' => 'All positions display event handling',
        'ajax' => true,
        'type' => 'read'
    ),
    'local_positions_form_option_selector' => array(
        'classname' => 'local_positions_external',
        'methodname' => 'positions_form_option_selector',
        'classpath' => 'local/positions/classes/external.php',
        'description' => 'Position dependency value setup',
        'ajax' => true,
        'type' => 'read',
    )
);

