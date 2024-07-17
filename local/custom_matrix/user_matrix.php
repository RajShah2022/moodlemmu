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
require_once($CFG->dirroot . '/local/custom_matrix/lib.php');

global $USER, $CFG, $PAGE, $OUTPUT, $DB;

$PAGE->requires->js_call_amd('local_custom_matrix/matrix');


$categorycontext = (new \local_custom_matrix\lib\accesslib())::get_module_context();
$ueid   = required_param('userid', PARAM_INT);
require_login();

$username = get_username($ueid);
$period_title = get_period_details();
$PAGE->set_heading(get_string('manage_custom_matrix', 'local_custom_matrix').'        :   '.$username);
$PAGE->set_url('/local/custom_matrix/user_matrix.php');

$PAGE->set_context($categorycontext);
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('pluginname', 'local_custom_matrix'));

$costcenterid = get_user_costcenter($ueid);
$role = user_designation_position($ueid);
$periodtype = get_config('local_custom_matrix','performance_period_type');
$current_month = date('M',time());
$period = get_current_period($periodtype,$current_month); 
$year = date('Y',time());
$records = user_matrix_data(array('costcenter'=>$costcenterid,'role' => $role,'userid' => $ueid,'period' => $period,'year' => $year,'month' => ''));

$options = get_period_list(array('userid' => $ueid,'year' => $year));

$templatecontext = [
      'response' => array_values($records),  
      'userid' => $ueid, 
      'show' =>  ($records)?true:false,  
      'backurl' =>  $CFG->wwwroot.'/local/myteam/team.php', 
      'options' => $options, 
      'emprole' => $role,
      'costcenterid' => $costcenterid,
      'heading' => $period_title,
     ];
echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_custom_matrix/user_matrix_view', $templatecontext);
echo $OUTPUT->footer();
