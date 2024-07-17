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
 * @subpackage local_learningplan
 */

require_once(dirname(__FILE__) . '/../../config.php');
global $DB, $USER, $CFG,$PAGE,$OUTPUT;
require_once($CFG->dirroot . '/local/learningplan/lib.php');
$systemcontext = (new \local_skillrepository\lib\accesslib())::get_module_context();
//check the context level of the user and check whether the user is login to the system or not
$PAGE->set_context($systemcontext);
require_login();

$costcenterid = optional_param('costcenterid', 0, PARAM_INT);
$action=optional_param('order','',PARAM_ALPHANUMEXT);
$instanceid = optional_param('levelid', 0, PARAM_INT);
$instance = optional_param('levelid', 0, PARAM_INT);
$leveltype = optional_param('leveltype', 0, PARAM_RAW);
$condtion_lep = optional_param('row', 0, PARAM_INT);
$base_url = new moodle_url('/local/skillrepository/level.php');
$data=data_submitted();

$sql="SELECT id,name,sortorder FROM {local_course_levels} ORDER BY sortorder ASC";
$instances = $DB->get_records_sql($sql, array('costcenterid' => $costcenterid,'leveltype' => $leveltype));
if($costcenterid && $action) {
   if(isset($instances[$instance])) {
      if ($action === 'up') {
         $order = array_keys($instances);
         $order = array_flip($order);
         $pos = $order[$instanceid];
         if($pos > 0) {
            $switch = $pos - 1;
            $resorted = array_values($instances);
            $temp = $resorted[$pos];
            $resorted[$pos] = $resorted[$switch];
            $resorted[$switch] = $temp;
            // now update db sortorder
            foreach ($resorted as $sortorder=>$instance) {
               if ($instance->sortorder != $sortorder) {
                  $instance->sortorder = $sortorder;
                  $instance->timemodified = time();
                  $da=$DB->update_record('local_course_levels', $instance);
               }
            }
         }       
         $return_url = new moodle_url('/local/skillrepository/level.php');
         redirect($return_url); 
      } else if ($action === 'down') {
         $order = array_keys($instances);
         $order = array_flip($order);
         $pos = $order[$instance];
         if($pos < (count($instances) - 1)) {
            $switch = $pos + 1;
            $resorted = array_values($instances);
            echo "Restore";
            $temp = $resorted[$pos];
            echo "Temp";
            echo "Position".$pos;
            $resorted[$pos] = $resorted[$switch];
            $resorted[$switch] = $temp;
            foreach ($resorted as $sortorder=>$instanced) {
               if ($instanced->sortorder != $sortorder) {
                  echo "check1";
                  $instanced->sortorder = $sortorder;
                  $instanced->timemodified = time();
                  $da=$DB->update_record('local_course_levels', $instanced);
               }
            }
         }
         $return_url = new moodle_url('/local/skillrepository/level.php');
         redirect($return_url);
      }
   }
}
redirect($base_url);
?>
