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
 * @subpackage local_skillrepository
 */
require_once(dirname(__FILE__) . '/../../config.php');
global $USER, $CFG, $PAGE, $OUTPUT, $DB;
require_once($CFG->dirroot . '/lib/formslib.php');
require_once($CFG->dirroot . '/local/skillrepository/lib.php');

$advance = get_config('local_skillrepository','advance');
$url= $CFG->wwwroot.'/local/skillrepository/level.php';
if($advance != 1)
{
    redirect($url);
}

$id = optional_param('id', 0, PARAM_INT);
$delete_id = optional_param('delete_id', 0, PARAM_INT);
$delete = optional_param('delete', 0, PARAM_INT);
$confirm = optional_param('confirm', 0, PARAM_INT);
$submitbutton = optional_param('submitbutton', '', PARAM_RAW);

require_login();
$PAGE->set_url('/local/skillrepository/skillmatrix.php');
$PAGE->set_context((new \local_skillrepository\lib\accesslib())::get_module_context());
$PAGE->set_pagelayout('standard');
$PAGE->navbar->add(get_string('manage_skill_category', 'local_skillrepository'),new moodle_url('/local/skillrepository/competency_view.php'));
$PAGE->navbar->add(get_string('manage_skillmatrix', 'local_skillrepository'));
$PAGE->requires->jquery();
$PAGE->requires->js('/local/skillrepository/js/jquery.dataTables.js',true);
$PAGE->requires->js('/local/skillrepository/js/dataTables.buttons.min.js',true);
$PAGE->requires->js('/local/skillrepository/js/buttons.html5.min.js',true);
$PAGE->requires->css('/local/skillrepository/css/buttons.dataTables.min.css');

$PAGE->set_title(get_string('manage_skillmatrix', 'local_skillrepository'));

$PAGE->requires->js_call_amd('local_costcenter/newcostcenter', 'load', array());
$PAGE->requires->js_call_amd('local_skillrepository/newassignlevel', 'load', array());
$PAGE->requires->js_call_amd('local_skillrepository/newassignlevel', 'getDomains');
$PAGE->requires->js_call_amd('local_skillrepository/newassignlevel', 'getLevels');
// $PAGE->requires->js_call_amd('local_skillrepository/newassignlevel', 'getSkillLevels');

$systemcontext = (new \local_skillrepository\lib\accesslib())::get_module_context();

if (!has_capability('local/skillrepository:manage', (new \local_skillrepository\lib\accesslib())::get_module_context()) && !is_siteadmin()) {
	print_error('Sorry, You are not accessable to this page');
}
$renderer = $PAGE->get_renderer('local_skillrepository'); 
$PAGE->set_heading(get_string('manage_skillmatrix', 'local_skillrepository'));

$mform = new local_skillrepository\form\skillmatrix_form();

if ($mform->is_cancelled()) {
	redirect($PAGE->url);
} else if ($fromform = $mform->get_data()) {
	$costcenterid = $fromform->costcenterid;
	$domain = $fromform->domain;
	$mform->set_data($fromform->costcenterid);		
}
echo $OUTPUT->header();
$data =  "<ul class='course_extended_menu_list'>
            <li>
		    	<div class='coursebackup course_extended_menu_itemcontainer'>
		        <a href='".$CFG->wwwroot."/local/skillrepository/competency_view.php' title='".get_string("back")."' class='course_extended_menu_itemlink'>
		          <i class='icon fa fa-reply'></i>
		        </a>
		    	</div>
		    </li>
        </ul>";
$systemcontext =(new \local_skillrepository\lib\accesslib())::get_module_context();
echo $data;
echo $mform->display();
if(!empty($costcenterid) && !empty($domain)) {
	echo $renderer->get_skillmatrix_view($costcenterid, $domain);
}
echo $OUTPUT->footer();
