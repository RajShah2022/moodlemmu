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
 * @subpackage local_positions
 */
define('AJAX_SCRIPT', true);
global $PAGE, $USER, $CFG;
require_once(dirname(__FILE__).'/../../config.php');
require_once('lib.php');
$action = required_param('action', PARAM_TEXT);
$id = optional_param('id', 0, PARAM_INT);
$name = optional_param('name', '', PARAM_TEXT);
$shortname = optional_param('shortname', '', PARAM_TEXT);
$table = optional_param('table', '', PARAM_RAW);
$dataid = optional_param('dataid', 0, PARAM_INT);
$q = optional_param('q', '', PARAM_RAW);

require_login();
$PAGE->set_url('/local/positions/ajax.php');
$PAGE->set_context((new \local_costcenter\lib\accesslib())::get_module_context());
$PAGE->set_pagelayout('admin');

$record = new stdClass();
$record->name = $name;
$record->shortname = $shortname;
$querylib = new \local_positions\local\querylib();
$renderer = $PAGE->get_renderer('local_positions');
switch($action) {
	case 'getpositionstable':
		$params = new \stdClass();
		$requestdata            = $_REQUEST;
        $params->perpage        = $requestdata['iDisplayLength'];
        $params->recordsperpage = $requestdata['iDisplayStart'];
        $params->search         = $requestdata['sSearch'];

        $data = $renderer->display_positions_tabledata($params);
        $total = $querylib->get_total_positions_count($params);
        $output = array(
            "sEcho" => intval($requestdata['sEcho']),
            "iTotalRecords" => $total,
            "iTotalDisplayRecords" => $total,
            "aaData" => $data,
        );
        $return = $output;
        // print_object($output);exit;
        // echo json_encode($output);
    break;
    case 'deleteposition':
    	$positionid = required_param('positionid', PARAM_INT);
        $domainid = $DB->get_field('local_positions','domain',array('id'=>$positionid));
    	$deleted = $querylib->delete_position($positionid);
        $positionrecord = $DB->get_record('local_positions',array('domain'=>$domainid));
        if(!$positionrecord)
        {
            $position = true;
        }
        else
        {
            $position = false;
        }
        $return = $position;
    	// echo json_encode($deleted);
    break;
    case 'getdomainstable':
        $params = new \stdClass();
        $requestdata            = $_REQUEST;
        $params->perpage        = $requestdata['iDisplayLength'];
        $params->recordsperpage = $requestdata['iDisplayStart'];
        $params->search         = $requestdata['sSearch'];

        $data = $renderer->display_domains_tabledata($params);
        $total = $querylib->get_total_domains_count($params);
        $output = array(
            "sEcho" => intval($requestdata['sEcho']),
            "iTotalRecords" => $total,
            "iTotalDisplayRecords" => $total,
            "aaData" => $data,
        );
        $return = $output;
        // print_object($output);exit;
        // echo json_encode($output);
    break;
    case 'deletedomain':
        $domainid = required_param('domainid', PARAM_INT);
        $deleted = $querylib->delete_domain($domainid);
        $return = $deleted;
        // echo json_encode($deleted);
    break;

}
	
echo json_encode($return);
