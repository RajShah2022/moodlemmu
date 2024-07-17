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
require_once($CFG->dirroot . '/local/skillrepository/lib.php');

global $CFG, $PAGE;

$systemcontext = (new \local_skillrepository\lib\accesslib())::get_module_context();
$id = required_param('competencyid', PARAM_INT);


//$PAGE->set_pagelayout('admin');
$PAGE->set_context($systemcontext);
$PAGE->set_url('/local/skillrepository/view.php');
require_login();

$title=get_string('manage_skill_category', 'local_skillrepository');
// exit;
$PAGE->set_title($title);

$PAGE->requires->js_call_amd('local_skillrepository/newcategory', 'load', array());
$PAGE->requires->js_call_amd('local_skillrepository/newassigncompetencylevel', 'load', array());
$PAGE->requires->js_call_amd('theme_epsilon/quickactions', 'quickactionsCall');
$PAGE->requires->js_call_amd('local_skillrepository/newassignlevel', 'load', array());
$PAGE->requires->js_call_amd('local_skillrepository/newassignlevel', 'getLevels', array());
$PAGE->requires->js_call_amd('local_skillrepository/newassignskill', 'load', array());
$PAGE->requires->js_call_amd('local_costcenter/costcenterdatatables', 'costcenterDatatable', array());
$PAGE->requires->js_call_amd('local_skillrepository/newassigncourse', 'load', array());
$PAGE->navbar->add(get_string('manage_skill_category', 'local_skillrepository'));
$lib =  new local_skillrepository\event\insertcategory();
$repository = new local_skillrepository\event\insertrepository();
$renderer = $PAGE->get_renderer('local_skillrepository');

$costcenterpathconcatsql = (new \local_costcenter\lib\accesslib())::get_costcenter_path_field_concatsql($columnname='open_path');
if (is_siteadmin() || has_capability('local/costcenter:manage_multiorganizations', $systemcontext)) {
   $skill_categories = $DB->get_record("local_skill_categories", array('id'=>$id));
} else {
   $sql = "SELECT * from {local_skill_categories} where 1=1 AND id = ? ".$costcenterpathconcatsql;
    $skill_categories = $DB->get_record_sql($sql,array('id'=>$id));
}

$PAGE->set_heading(get_string('competency','local_skillrepository')." : ". $skill_categories->name);
echo $OUTPUT->header();
$link = $CFG->wwwroot."/local/skillrepository/competency_view.php";
echo $renderer->backbuttonlink($link);

$res = $renderer->competency_data_view($skill_categories);
echo $res;

echo $OUTPUT->footer();
