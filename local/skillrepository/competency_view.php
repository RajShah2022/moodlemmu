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
$id = optional_param('id', -1, PARAM_INT);
$delete = optional_param('delete', 0, PARAM_INT);
$confirm = optional_param('confirm', 0, PARAM_INT);
$delete_id = optional_param('delete_id', 0, PARAM_INT);
$submitbutton = optional_param('submitbutton', '', PARAM_RAW);
$costcenterid = optional_param('costcenterid', '', PARAM_INT);
//$PAGE->set_pagelayout('admin');
$PAGE->set_context($systemcontext);
$PAGE->set_url('/local/skillrepository/competency_view.php');
require_login();

$title=get_string('manage_skill_category', 'local_skillrepository');
// exit;
$PAGE->set_title($title);

$PAGE->requires->js_call_amd('local_skillrepository/newcategory', 'load', array());
$PAGE->navbar->add(get_string('dashboard', 'local_costcenter'), new moodle_url('/my/dashboard.php'));
$PAGE->navbar->add(get_string('manage_skill_category', 'local_skillrepository'));
$PAGE->set_heading(get_string('manage_skill_category', 'local_skillrepository'));
$lib =  new local_skillrepository\event\insertcategory();
$repository = new local_skillrepository\event\insertrepository();
$renderer = $PAGE->get_renderer('local_skillrepository');
if (!has_capability('local/skillrepository:manage', (new \local_skillrepository\lib\accesslib())::get_module_context()) && !is_siteadmin()) {
    print_error(get_string('accessissue','local_skillrepository'));
}

echo $OUTPUT->header();

$collapse = true;
$show = '';

echo $renderer->get_top_action_buttons_skills();

$filterparams = $renderer->custom_competency_cardview(true);

$formdata = new stdClass();
$formdata->filteropen_costcenterid = $costcenterid;

$mform = competency_filters_form($filterparams, (array)$formdata);
if ($mform->is_cancelled()) {
    redirect('index.php');
}
if (!empty($costcenterid)) {

    $mform->set_data($formdata);

}
if(is_siteadmin())
{
    echo '<a class="btn-link btn-sm" data-toggle=
    "collapse" data-target="#local_users-filter_collapse" aria-expanded="false"
    aria-controls="local_users-filter_collapse">
            <i class="m-0 fa fa-sliders fa-2x" aria-hidden="true"></i>
          </a>';
    echo  '<div class="mt-2 mb-2 collapse '.$show.'" id="local_users-filter_collapse">
                <div id="filters_form" class="card card-body p-2">';
                    $mform->display();
    echo        '</div>
            </div>';
}

$filterparams['submitid'] = 'form#filteringform';
$filterparams['filterdata'] = json_encode($formdata);
echo $OUTPUT->render_from_template('local_costcenter/global_filter', $filterparams);

echo $renderer->custom_competency_cardview();

echo $OUTPUT->footer();
