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
 * @subpackage block_eventtimetable
 */
require_once(dirname(__FILE__) . '/../../config.php');
require_once($CFG->libdir.'/adminlib.php');
global $USER, $CFG, $PAGE, $OUTPUT, $DB;
require_once($CFG->dirroot . '/lib/formslib.php');
require_once($CFG->dirroot . '/blocks/eventtimetable/lib.php');
require_once($CFG->dirroot . '/local/costcenter/lib.php');
require_login();
$categorycontext = (new \local_costcenter\lib\accesslib())::get_module_context();
$PAGE->set_context($categorycontext);
$PAGE->set_heading(get_string('eventcalender', 'block_eventtimetable'));
$PAGE->navbar->add(get_string('dashboard', 'local_costcenter'), new moodle_url('/my/dashboard.php'));
$PAGE->navbar->add(get_string('eventcalender', 'block_eventtimetable'));
$PAGE->set_url('/blocks/eventtimetable/eventcalender.php');
$renderer = $PAGE->get_renderer('block_eventtimetable');
$PAGE->requires->jquery();
$PAGE->requires->js('/blocks/eventtimetable/js/event-calendar.min.js', true);
$PAGE->requires->js_call_amd('local_classroom/classroom', 'load', array());
$PAGE->requires->js_call_amd('local_request/requestconfirm', 'load', array());
$PAGE->requires->js('/blocks/eventtimetable/js/custom.js', true);
$PAGE->requires->css('/blocks/eventtimetable/css/event-calendar.min.css');
echo $OUTPUT->header();
echo $renderer->render_timetable();
echo $OUTPUT->footer();
