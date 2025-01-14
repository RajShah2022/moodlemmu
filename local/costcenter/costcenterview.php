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
 * @subpackage local_costcenter
 */


require_once('../../config.php');
require_once($CFG->dirroot.'/local/costcenter/lib.php');
require_once($CFG->dirroot.'/local/costcenter/renderer.php');

$id = required_param('id', PARAM_INT);
$userid = optional_param('userid', 0, PARAM_INT);
$deptid = optional_param('deptid', 0, PARAM_INT);
$costcenterquerylib = new \local_costcenter\querylib();
global $DB,$OUTPUT,$CFG, $PAGE;
/* ---First level of checking--- */
require_login();
/* ---Get the records from the database--- */
$costcenterpathconcatsql = (new \local_costcenter\lib\accesslib())::get_costcenter_path_field_concatsql($columnname='lc.path');

$depart = $costcenterquerylib->costcenter_record_sql('lc.id, lc.fullname,lc.parentid,lc.depth', array('id' => $id), $costcenterpathconcatsql); 

if (!$depart) {
    print_error('invalidcostcenterid');

}

if(!empty($depart) && isset($depart->path)){

    $categorycontext = (new \local_costcenter\lib\accesslib())::get_module_context($depart->path);
}else{
    $categorycontext = (new \local_costcenter\lib\accesslib())::get_module_context();
}

if(!has_capability('local/costcenter:view', $categorycontext)) {
    print_error('nopermissiontoviewpage');
}
$PAGE->requires->jquery();
$PAGE->requires->jquery('ui');
$PAGE->requires->jquery('ui-css');

$PAGE->requires->js_call_amd('local_costcenter/costcenterdatatables', 'costcenterDatatable', array());
$PAGE->requires->js_call_amd('local_assignroles/newcostcenterassignrole', 'load', array());

$PAGE->requires->js_call_amd('local_assignroles/rolespopup', 'init',array(array('contextid' => $categorycontext->id, 'selector' => '.rolescostcenterpopup')));
$PAGE->requires->js_call_amd('local_assignroles/popup', 'Datatable', array());
$PAGE->requires->js_call_amd('local_costcenter/newcostcenter', 'load', array());
$PAGE->requires->js_call_amd('local_costcenter/newsubdept', 'load', array());
$costcenter = new costcenter();
$theme = $costcenter->get_theme();
$PAGE->requires->js_call_amd('theme_'.$theme.'/quickactions', 'quickactionsCall');

$PAGE->set_pagelayout('standard');
/* ---check the context level of the user and check whether the user is login to the system or not--- */
$PAGE->set_context($categorycontext);
$PAGE->set_url('/local/costcenter/costcenterview.php');
/* ---Header and the navigation bar--- */
$PAGE->navbar->ignore_active();

$PAGE->set_heading(get_string('orgStructure_'.$depart->depth, 'local_costcenter'));
$PAGE->set_title(get_string('orgStructure_'.$depart->depth, 'local_costcenter'));
costcenterpagenavbar($depart->depth, $depart->parentid);

echo $OUTPUT->header();

$url = new moodle_url('/local/costcenter/costcenterview.php', array('sesskey'=>sesskey()));
$costcenterpath = (new \local_costcenter\lib\accesslib())::get_user_role_switch_select_option($url,'id');

echo $costcenterpath;

$renderer = $PAGE->get_renderer('local_costcenter');
echo $renderer->get_dept_view_btns($id);
if($depart->parentid){ // display department page
    echo $renderer->department_view($id, $categorycontext);
}else{// display organization page
    echo $renderer->costcenterview($id, $categorycontext);
}
echo $OUTPUT->footer();
