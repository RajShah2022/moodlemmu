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
 * @subpackage local_users
 */



require_once(dirname(__FILE__) . '/../../config.php');
global $CFG,$PAGE,$DB;
require_once($CFG->dirroot . '/local/users/lib.php');
$courseid = optional_param('id',0,PARAM_INT);
$categorycontext = (new \local_users\lib\accesslib())::get_module_context();
$coursename=$DB->get_field('course', 'fullname', array('id' => $courseid));
$PAGE->set_context($categorycontext);
$PAGE->set_url($CFG->wwwroot .'/local/courses/courseautoenrol_log.php');
$PAGE->set_title(get_string('courseautoenrol_log', 'local_courses'));
$PAGE->set_heading(get_string('courseautoenrol_log', 'local_courses').' - '.$coursename);
$PAGE->requires->jquery();
$PAGE->requires->js_call_amd('local_courses/courseAjaxform', 'load');
$PAGE->navbar->ignore_active();
require_login();

$renderer = $PAGE->get_renderer('local_courses');
$PAGE->navbar->add( get_string('pluginname', 'local_courses'));

echo $OUTPUT->header();
if ((has_capability('local/courses:manage', $categorycontext) || has_capability('local/courses:view', $categorycontext))) {
echo $renderer->userlog_for_autoenrol($courseid,$confirmation,$enrolid);
}else{
 echo get_string('no_permissions','local_courses');
}
echo $OUTPUT->footer();
