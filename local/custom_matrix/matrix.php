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
require_once($CFG->dirroot . '/lib/formslib.php');
require_once($CFG->dirroot . '/local/custom_matrix/lib.php');
// require_once($CFG->dirroot . '/local/custom_matrix/renderer.php');
$categorycontext = (new \local_custom_matrix\lib\accesslib())::get_module_context();

$id = optional_param('id', 0, PARAM_INT);
$parentcatid = optional_param('parentcatid', 0, PARAM_INT);
$delete_id = optional_param('delete_id', 0, PARAM_INT);
$delete = optional_param('delete', 0, PARAM_INT);
$confirm = optional_param('confirm', 0, PARAM_INT);
$submitbutton = optional_param('submitbutton', '', PARAM_RAW);
require_login();

if(!has_capability('local/custom_matrix:view_custom_matrix',$categorycontext)) {
    print_error('nopermissiontoviewpage');
}

if($parentcatid>0){   
    $querylib = new \local_custom_matrix\querylib();
    $childcatname = $querylib->get_matrixfield('fullname', array('id' => $parentcatid));
    $PAGE->set_url('/local/custom_matrix/index.php?parentcatid='.$parentcatid);
    $matrixtitle = get_string('manage_custom_matrixtitle', 'local_custom_matrix')." : ".$childcatname;
    $PAGE->set_heading($matrixtitle);
    $PAGE->navbar->add(get_string('manage_custom_matrixtitle', 'local_custom_matrix'), new moodle_url('/local/custom_matrix/matrix.php'));
    $PAGE->navbar->add($childcatname);
}else{
    $PAGE->set_heading(get_string('manage_custom_matrixtitle', 'local_custom_matrix'));
    $PAGE->set_url('/local/custom_matrix/matrix.php');
}
$PAGE->set_context($categorycontext);
$PAGE->set_pagelayout('standard');

$PAGE->set_title(get_string('performance_matrix', 'local_custom_matrix'));
$PAGE->requires->js_call_amd('local_custom_matrix/newcustommatrix', 'load', array());

$renderer = $PAGE->get_renderer('local_custom_matrix');

$filterparams = $renderer->custom_matrix_content($parentcatid, true);


echo $OUTPUT->header();

if(is_siteadmin()|| has_capability('local/custom_matrix:create_custom_matrix',$categorycontext)){
    echo $renderer->get_top_action_buttons_custom_matrix($parentcatid);
}
$filterparams['submitid'] = 'form#filteringform';
echo $OUTPUT->render_from_template('local_costcenter/global_filter', $filterparams);
echo $renderer->custom_matrix_content($parentcatid);

echo $OUTPUT->footer();
