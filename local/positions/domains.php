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
 * @subpackage local_domain
 */
require_once(dirname(__FILE__) . '/../../config.php');
global $USER, $CFG, $PAGE, $OUTPUT, $DB;
require_once($CFG->dirroot . '/lib/formslib.php');
require_once($CFG->dirroot . '/local/positions/classes/local/querylib.php');

$advance = get_config('local_skillrepository','advance');
if($advance != 1)
{
    print_error(" You don't have permissions to access this page.");
}

$id = optional_param('id', 0, PARAM_INT);

require_login();
$pageurl = new moodle_url('/local/positions/domains.php');
$PAGE->set_url('/local/positions/domains.php');
$PAGE->set_context((new \local_costcenter\lib\accesslib())::get_module_context());
// $PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('manage_domains', 'local_positions'));
$PAGE->navbar->add(get_string('dashboard', 'local_costcenter'), new moodle_url('/my/dashboard.php'));
$PAGE->navbar->add(get_string('manage_domains', 'local_positions'));
$PAGE->set_heading(get_string('manage_domains', 'local_positions'));

$PAGE->requires->jquery();
$PAGE->requires->jquery('ui');
$PAGE->requires->jquery('ui-css');
$PAGE->requires->js_call_amd('local_positions/domaintable', 'domaintable', array());
$PAGE->requires->js_call_amd('theme_epsilon/quickactions', 'quickactionsCall');
$PAGE->requires->js_call_amd('local_positions/positiontable', 'load', array());
$PAGE->requires->js_call_amd('local_positions/positiontable', 'positiontable', array());
$PAGE->requires->js_call_amd('local_positions/positiontable', 'getposition');
$PAGE->requires->js_call_amd('local_costcenter/newcostcenter', 'load', array());

$systemcontext = (new \local_costcenter\lib\accesslib())::get_module_context();

if(!has_capability('local/costcenter:manage', $systemcontext) && !is_siteadmin()) {
            print_error('nopermissiontoviewpage');
}

$locallib = new \local_positions\local\querylib();
$renderer = $PAGE->get_renderer('local_positions');
echo $OUTPUT->header();
echo "<ul class='course_extended_menu_list'>
    <li>
      <div class='coursebackup course_extended_menu_itemcontainer'>
            <a id='extended_menu_syncstats' title='".get_string('createposition', 'local_positions')."' class='course_extended_menu_itemlink' href='javascript:void(0)' onclick ='(function(e){ require(\"local_positions/positiontable\").init({selector:\"createlevelmodal\", contextid:$systemcontext->id, positionid:0}) })(event)'><i class='icon fa fa-plus' aria-hidden='true' aria-label=''></i></a>
        </div>              
    </li>
    <li>
        <div class='coursebackup course_extended_menu_itemcontainer'>
            <a id='extended_menu_syncstats' title='".get_string('createdomain', 'local_positions')."' class='course_extended_menu_itemlink' href='javascript:void(0)' onclick ='(function(e){ require(\"local_positions/domaintable\").init({selector:\"createlevelmodal\", contextid:$systemcontext->id, domainid:0}) })(event)'><i class='icon fa fa-server' aria-hidden='true' aria-label=''></i></a>
        </div>              
    </li>
</ul>";
/*<li>
      <div class='coursebackup course_extended_menu_itemcontainer'>
            <a id='extended_menu_syncerrors' title='".get_string('manage_positions', 'local_positions')."' class='course_extended_menu_itemlink' href='" . $CFG->wwwroot ."/local/positions/positions.php'><i class='icon fa fa-users fa-fw' aria-hidden='true' aria-label=''></i></a>
        </div>
    </li>
*/
// echo $renderer->display_domains_tablestructure();
echo $renderer->domain_view();
echo $OUTPUT->footer();
