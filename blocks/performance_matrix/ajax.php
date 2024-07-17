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
 * @subpackage
 */
define('AJAX_SCRIPT', true);

require_once(dirname(__FILE__) . '/../../config.php');
global $DB, $CFG, $USER, $PAGE, $OUTPUT;

use context_system;

$context = context_system::instance();
require_login();
$PAGE->set_context($context);

require_once($CFG->dirroot.'/blocks/performance_matrix/lib.php');

$filters = array();

$filters['performancetype'] = optional_param('ptype','', PARAM_INT);
$filters['radio_filter'] = optional_param('radio_filter','', PARAM_RAW);
$filters['userid'] = optional_param('userid','', PARAM_INT);
$filters['performance'] = optional_param('performance','', PARAM_INT);
$output = array();
$filterdata = make_custom_content($filters,$filters['userid']);
$o = '';
$OUTPUT->header();
$PAGE->start_collecting_javascript_requirements();
ob_start();
$o .= $OUTPUT->render_chart($filterdata);

$o .= ob_get_contents();

ob_end_clean();

$data = $o;

$jsfooter = $PAGE->requires->get_end_code();
$output['error'] = false;
$output['html'] = $data;
$output['javascript'] = $jsfooter;

echo json_encode($output);
