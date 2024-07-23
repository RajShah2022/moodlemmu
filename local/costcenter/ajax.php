<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * TODO describe file ajax
 *
 * @package    local_costcenter
 * @copyright  2024 Moodle India Information Solutions Pvt Ltd
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);
global $PAGE, $DB, $USER, $CFG;
require_once(dirname(__FILE__) . '/../../config.php');
require_once('lib.php');
$action = required_param('action', PARAM_TEXT);
$categorycontext = (new \local_custom_category\lib\accesslib())::get_module_context();
require_login();
$PAGE->set_url('/local/skillrepository/ajax.php');
$PAGE->set_context($categorycontext);
$PAGE->set_pagelayout('standard');
$renderer = $PAGE->get_renderer('local_costcenter');

switch ($action) {
    case 'deptcontent':
        $deptid = required_param('id', PARAM_INT);
        $return = $renderer->get_department_content($deptid, $categorycontext);
        echo json_encode($return);
        break;
}
