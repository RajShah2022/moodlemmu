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

$id = required_param('id', PARAM_INT);//domainid
$userid = optional_param('userid', 0, PARAM_INT);
global $DB,$OUTPUT,$CFG, $PAGE;
/* ---First level of checking--- */
require_login();
$systemcontext = (new \local_costcenter\lib\accesslib())::get_module_context();

if(!has_capability('local/domains:view', $systemcontext)) {
    print_error('nopermissiontoviewpage');
}

$systemcontext = (new \local_costcenter\lib\accesslib())::get_module_context();

if(!has_capability('local/costcenter:manage', $systemcontext) && !is_siteadmin()) {
            print_error('nopermissiontoviewpage');
}

$PAGE->requires->jquery();
$PAGE->requires->jquery('ui');
$PAGE->requires->jquery('ui-css');
$PAGE->requires->js_call_amd('local_costcenter/newcostcenter', 'load', array());
$PAGE->requires->js_call_amd('theme_epsilon/quickactions', 'quickactionsCall');

$PAGE->requires->js_call_amd('local_positions/domaintable', 'domaintable', array());
$PAGE->requires->js_call_amd('local_positions/positiontable', 'load', array());
$PAGE->requires->js_call_amd('local_positions/positiontable', 'positiontable', array());
$PAGE->requires->js_call_amd('local_positions/positiontable', 'getposition');

// $PAGE->set_pagelayout('admin');
/* ---check the context level of the user and check whether the user is login to the system or not--- */
$PAGE->set_context($systemcontext);
$PAGE->set_url('/local/positions/domainview.php');
/* ---Header and the navigation bar--- */
$PAGE->navbar->ignore_active();
if (!((is_siteadmin()) || has_capability('local/costcenter:manage_multiorganizations', $systemcontext) || has_capability('local/costcenter:manage_ownorganization', $systemcontext))) {
    if($USER->open_domainid != $id){
        redirect($CFG->wwwroot . '/local/positions/domainview.php?id='.$USER->open_domainid);
    }
}


$PAGE->set_title(get_string('manage_positions', 'local_positions'));
$PAGE->navbar->add(get_string('dashboard', 'local_costcenter'), new moodle_url('/my/dashboard.php'));
$PAGE->navbar->add(get_string('manage_domains', 'local_positions'), new moodle_url('/local/positions/domains.php'));
$PAGE->navbar->add(get_string('manage_positions', 'local_positions'));
$PAGE->set_heading(get_string('manage_positions', 'local_positions'));


echo $OUTPUT->header();

echo "<ul class='course_extended_menu_list'>
    <li>
      <div class='coursebackup course_extended_menu_itemcontainer'>
        <a href='".$CFG->wwwroot."/local/positions/domains.php' title='".get_string("back")."' class='course_extended_menu_itemlink'>
          <i class='icon fa fa-reply'></i>
        </a>
      </div>
    </li>
    <li>
        <div class='coursebackup course_extended_menu_itemcontainer'>
            <a id='extended_menu_syncstats' title='".get_string('createposition', 'local_positions')."' class='course_extended_menu_itemlink' href='javascript:void(0)' onclick ='(function(e){ require(\"local_positions/positiontable\").init({selector:\"createlevelmodal\", contextid:$systemcontext->id, positionid:0}) })(event)'><i class='icon fa fa-plus' aria-hidden='true' aria-label=''></i></a>
        </div>              
    </li>
</ul>";
$renderer = $PAGE->get_renderer('local_positions');
if($position->parent == 0){ // display department page
    echo $renderer->position_view($id, $systemcontext);
}else{// display organization page
    echo 'No data available';//$renderer->costcenterview($id, $systemcontext);
}
echo $OUTPUT->footer();
