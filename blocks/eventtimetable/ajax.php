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
require_once($CFG->dirroot . '/local/costcenter/lib.php');
$action = required_param('action', PARAM_RAW);
$systemcontext = (new \local_costcenter\lib\accesslib())::get_module_context();
global $DB, $USER;
$result = [];
switch($action){
    
    case 'get_moduleids':
        $moduletype = required_param('mastervalue', PARAM_TEXT);
        if($moduletype=='course'){

            $coursesql = "SELECT c.id,c.fullname, c.shortname FROM {course} AS c";

            $params = [];

            if(is_siteadmin()){
                $coursesql .= " WHERE 1 AND c.format != 'site' AND c.open_module IS NULL "; 
            }else if(!is_siteadmin() && has_capability('block/eventtimetable:view_current_trg_events', $systemcontext)){
                $costcenterpathconcatsql = (new \local_costcenter\lib\accesslib())::get_costcenter_path_field_concatsql($columnname='c.open_path');
                $coursesql .=" WHERE 1 AND c.open_module IS NULL ".$costcenterpathconcatsql;
            
            }else{

                $paths = [];
                $userpathinfo = $USER->open_path;
                $paths[] = $userpathinfo;
                while ($userpathinfo = rtrim($userpathinfo,'0123456789')) {
                    $userpathinfo = rtrim($userpathinfo, '/');
                    if ($userpathinfo === '') {
                        break;
                    }
                    $paths[] = $userpathinfo;
                }

                if(!empty($paths)){
                    foreach($paths AS $path){
                        $pathsql[] = " c.open_path LIKE '{$path}' ";
                    }
                    $wheresql = " ( ".implode(' OR ', $pathsql).' ) ';
                }

                $coursesql .= " LEFT JOIN {context} AS ct ON ct.instanceid = c.id
                JOIN {role_assignments} AS ra ON ra.contextid = ct.id
                WHERE ra.roleid = 5 AND ra.userid = ".$USER->id;
            }
            $result = $DB->get_records_sql_menu($coursesql, $params);
        }
        if($moduletype=='classroom'){
            $classroomsql = "SELECT lcr.id, lcr.name AS fullname FROM {local_classroom} AS lcr ";

            $params = [];

            if(is_siteadmin()){
                $classroomsql .=  " WHERE 1 "; 
            }else if(!is_siteadmin() && has_capability('block/eventtimetable:view_current_trg_events', $systemcontext)){
                $costcenterpathconcatsql = (new \local_costcenter\lib\accesslib())::get_costcenter_path_field_concatsql($columnname='lcr.open_path');
                $classroomsql .=" WHERE 1 ". $costcenterpathconcatsql;
            
            // }else if(!is_siteadmin() && has_capability('block/eventtimetable:view_events_trainer', $systemcontext)){
            //     $costcenterpathconcatsql = (new \local_costcenter\lib\accesslib())::get_costcenter_path_field_concatsql($columnname='lcr.open_path');
            //     $classroomsql .=" JOIN {local_classroom_trainers} AS lct ON lct.classroomid = lcr.id 
            //         WHERE 1 AND lct.trainerid = ".$USER->id." ".$costcenterpathconcatsql;
            
            }else{
                $paths = [];
                $userpathinfo = $USER->open_path;
                $paths[] = $userpathinfo;
                while ($userpathinfo = rtrim($userpathinfo,'0123456789')) {
                    $userpathinfo = rtrim($userpathinfo, '/');
                    if ($userpathinfo === '') {
                        break;
                    }
                    $paths[] = $userpathinfo;
                }

                if(!empty($paths)){
                    foreach($paths AS $path){
                        $pathsql[] = " lcr.open_path LIKE '{$path}' ";
                    }
                    $wheresql = " ( ".implode(' OR ', $pathsql).' ) ';
                }

                $classroomsql .= " LEFT JOIN {local_classroom_users} AS lcu ON lcu.classroomid = lcr.id
                    WHERE 1 AND lcr.status =1 AND (( ".$wheresql." AND lcr.selfenrol=1) OR (lcu.classroomid = lcr.id AND lcu.userid = ".$USER->id.")) ";
            }
            $result = $DB->get_records_sql_menu($classroomsql, $params);
        }
    break;
}
echo json_encode($result);
