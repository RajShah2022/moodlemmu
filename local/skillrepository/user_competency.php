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
require_once($CFG->dirroot . '/local/skillrepository/lib.php');

global $USER, $CFG, $PAGE, $OUTPUT, $DB;

$advance = get_config('local_skillrepository','advance');
if($advance == 0)
{
	print_error(get_string('accessissue','local_skillrepository'));
}


$categorycontext = (new \local_skillrepository\lib\accesslib())::get_module_context();
$ueid   = required_param('userid', PARAM_INT);
$user = $DB->get_record('user', array('id'=>$ueid));
require_login();
if($ueid != $USER->id){
    $name = ' : '.$user->username;
}else
{
    $name = "";
}
$PAGE->requires->js_call_amd('local_search/courseinfo', 'load', array());
$PAGE->set_heading(get_string('reqcompetency', 'local_skillrepository'));
$PAGE->set_url('/local/skillrepository/user_competency.php');
$PAGE->set_context($categorycontext);
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('competency', 'local_skillrepository'));

echo $OUTPUT->header();
$renderer   = $PAGE->get_renderer('local_users');

// $context = $renderer->employees_skill_profile_view($ueid);
$context['pagelength'] = 3;
if($user->open_positionid ){
    $response['response'] = array_values(get_user_competencies(array('open_positionid'=>$user->open_positionid, 'userid'=>$ueid)));
}
else
{
    print_error(get_string('accessissue','local_skillrepository'));
}
// echo    $OUTPUT->render_from_template('local_skillrepository/competencytabs', $context);
echo    $OUTPUT->render_from_template('local_myteam/competency_view', $response);

echo $OUTPUT->footer();
