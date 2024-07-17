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
define('AJAX_SCRIPT', true);
global $PAGE, $DB, $USER, $CFG;
require_once(dirname(__FILE__).'/../../config.php');
require_once('lib.php');
$action = required_param('action', PARAM_TEXT);

require_login();
$PAGE->set_url('/local/custom_matrix/ajax.php');
$PAGE->set_context((new \local_custom_matrix\lib\accesslib())::get_module_context());
$PAGE->set_pagelayout('standard');


switch($action) {
    case 'deletecustommatrix':
        $custommatrixid = required_param('matrixid', PARAM_INT);
        $return = $DB->delete_records('local_custom_category',  array('id' => $custommatrixid));
    break;
    case 'hidecustommatrix':
        $custommatrixid = required_param('matrixid', PARAM_INT);
        $return = $DB->update_record('local_custom_category',  array('id' => $custommatrixid,'visible'=>0));
    break;
    case 'unhidecustommatrix':
        $custommatrixid = required_param('matrixid', PARAM_INT);
        $return = $DB->update_record('local_custom_category',  array('id' => $custommatrixid,'visible'=>1));
    break;
    case 'deletetemplate':
    $templateid = required_param('templateid', PARAM_INT);
     $return = $DB->delete_records('local_performance_template',  array('id' => $templateid));
    $DB->delete_records('local_performance_matrix',array('parentid' => $templateid));
    $DB->delete_records('local_performance_logs',array('parentid' => $templateid));
    $DB->delete_records('local_performance_overall',array('parentid' => $templateid));
}
echo json_encode($return);
