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
require_once(dirname(__FILE__) . '/../../config.php');

global $USER, $CFG, $PAGE, $OUTPUT, $DB;

require_once($CFG->dirroot . '/local/custom_matrix/classes/observer.php');
require_once($CFG->dirroot . '/lib/formslib.php');
require_once($CFG->dirroot . '/local/custom_matrix/lib.php');
require_once($CFG->dirroot . '/local/custom_matrix/edit_matrix_form.php');

$templateid = required_param('temid',PARAM_INT);
$org_id = required_param('orgid',PARAM_INT);


$roletype = get_config('local_custom_matrix','performance_matrix_role_type');
$PAGE->requires->js_call_amd('local_custom_matrix/matrix', 'fetchMatrixCategories',array('roletype' => $roletype,'org_id' => $org_id,'tempid' => $templateid));
$PAGE->requires->js_call_amd('local_custom_matrix/matrix');
$categorycontext = (new \local_custom_matrix\lib\accesslib())::get_module_context();

require_login();

if(!has_capability('local/custom_matrix:view_custom_matrix',$categorycontext)) {
    print_error('nopermissiontoviewpage');
}

$PAGE->set_heading(get_string('manage_custom_matrix', 'local_custom_matrix'));
$PAGE->set_url('/local/custom_matrix/index.php');
$PAGE->set_context($categorycontext);
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('pluginname', 'local_custom_matrix'));
$core_component = new core_component();
$positions_plugin_exists = $core_component::get_plugin_directory('local', 'positions');
$options = '';
if($roletype == 1){ // For Designations
    $options = get_designations();
}else if($roletype == 2){ // For Positions
    if($positions_plugin_exists){
        $options = get_positions();       
     }
} 

$mform = new local_custom_matrix_edit_form();
echo $OUTPUT->header();
$mform->display();
echo $OUTPUT->footer();
