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
global $CFG, $DB, $USER;
require_once($CFG->dirroot.'/blocks/eventtimetable/lib.php');
require_once($CFG->dirroot . '/local/costcenter/lib.php');
$start = required_param('start', PARAM_RAW);
$end = required_param('end', PARAM_RAW);
$moduletype = required_param('moduletype', PARAM_RAW);
$moduleids = required_param('moduleids', PARAM_RAW);


$systemcontext = (new \local_costcenter\lib\accesslib())::get_module_context();
$courseEvent = array();
$classroomEvent = array();

$eventsSql = "SELECT e.* FROM {event} AS e ";
  
if(is_siteadmin()){
    $whereSql = " WHERE 1 ";

    if(!empty($moduletype)){
        if($moduletype == 'course'){
            $whereSql .= " AND (e.modulename = 'assign' OR e.modulename = 'quiz') ";
            if($moduleids){
                $whereSql .= " AND e.courseid =".$moduleids;
            }
        } else {
            $whereSql .= " AND e.modulename = '".$moduletype."' ";
            if($moduleids)
            {
                $whereSql .= " AND e.plugin_instance =".$moduleids;
            }
        }
    }

    $events = $DB->get_records_sql($eventsSql.$whereSql, $params);
}else if(!is_siteadmin() && has_capability('block/eventtimetable:view_current_trg_events', $systemcontext)){
    $costcenterpathconcatsql = (new \local_costcenter\lib\accesslib())::get_costcenter_path_field_concatsql($columnname='c.open_path');
    if(empty($moduletype) || $moduletype == 'course'){
        $courseevents = " JOIN {course} as c on c.id = e.courseid
        WHERE 1 ".$costcenterpathconcatsql;
        if($moduletype){
            $courseevents .= " AND (e.modulename = 'assign' OR e.modulename = 'quiz') ";
    
            if($moduleids){
                $courseevents .= " AND e.courseid =".$moduleids;
            }
        }
        $courseEvent = $DB->get_records_sql($eventsSql.$courseevents, $params);
    }
    if(empty($moduletype) || $moduletype == 'classroom'){
        $classroomevents=" JOIN {local_classroom} AS c on c.id = e.plugin_instance WHERE 1 ".$costcenterpathconcatsql;
        if($moduletype){
            $classroomevents .= " AND e.modulename = '".$moduletype."' ";
            
            if($moduleids){
                $classroomevents .= " AND e.plugin_instance =".$moduleids;
            }
        }
        $classroomEvent = $DB->get_records_sql($eventsSql.$classroomevents, $params);
    }
    $events = array_merge($courseEvent,$classroomEvent);
}else if(!is_siteadmin() && has_capability('block/eventtimetable:view_events_trainer', $systemcontext)){
    $moduletype = 'classroom';
    $costcenterpathconcatsql = (new \local_costcenter\lib\accesslib())::get_costcenter_path_field_concatsql($columnname='c.open_path');
    
    if(empty($moduletype) || $moduletype == 'classroom'){
        $classroomevents=" JOIN {local_classroom} AS c ON c.id = e.plugin_instance
            LEFT JOIN {local_classroom_trainers} AS lct ON lct.classroomid = c.id
            WHERE 1 AND lct.trainerid = ".$USER->id." AND c.status = 1 ".$costcenterpathconcatsql;
        if($moduletype){
            $classroomevents .= " AND e.modulename = '".$moduletype."' ";
            
            if($moduleids){
                $classroomevents .= " AND e.plugin_instance =".$moduleids;
            }
        }
        $classroomEvent = $DB->get_records_sql($eventsSql.$classroomevents, $params);
    }
    $events = $classroomEvent;
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

    if(empty($moduletype) || $moduletype == 'course'){
        $courseevents=" JOIN {course} as c ON c.id = e.courseid
            LEFT JOIN {context} AS ct ON ct.instanceid = c.id
            JOIN {role_assignments} AS ra ON ra.contextid = ct.id
            WHERE ra.roleid = 5 AND ra.userid = ".$USER->id;
        
        if($moduletype){
            $courseevents .= " AND (e.modulename = 'assign' OR e.modulename = 'quiz') ";
    
            if($moduleids){
                $courseevents .= " AND e.courseid =".$moduleids;
            }
        }
        $courseEvent = $DB->get_records_sql($eventsSql.$courseevents, $params);
    }
    if(empty($moduletype) || $moduletype == 'classroom'){
        $classroomevents=" JOIN {local_classroom} AS c ON c.id = e.plugin_instance
            LEFT JOIN {local_classroom_users} AS lcu ON lcu.classroomid = c.id
            WHERE 1 AND c.status =1 AND (( ".$wheresql." AND c.selfenrol=1) OR (lcu.classroomid = c.id AND lcu.userid = ".$USER->id.")) ";

        if($moduletype){
            $classroomevents .= " AND e.modulename = '".$moduletype."' ";
            
            if($moduleids){
                $classroomevents .= " AND e.plugin_instance =".$moduleids;
            }
        }
        $classroomEvent = $DB->get_records_sql($eventsSql.$classroomevents, $params);
    }
    $events = array_merge($courseEvent,$classroomEvent);
}

$returnevents = [];
$eventlib = new \block_eventtimetable\lib();
foreach($events AS $event){
    if(!$eventlib->can_access_event($event)){
        continue;
    }
    $eventdata = $eventlib->get_event_info($event);
    $eventinfo = new stdClass();
    $timestart = isset($eventdata->timestart) ? $eventdata->timestart : $event->timestart;
    $timeduration = isset($eventdata->timeduration) ? $eventdata->timeduration : $event->timeduration;
    $eventinfo->start = date('Y-m-d H:i', $timestart);
    $eventinfo->resourceId = '1';
    $eventinfo->title = strip_tags($eventdata->name);
     $eventinfo->color = $eventdata->eventcolor ? $eventdata->eventcolor:get_config('block_eventtimetable','courseeventcolor');
    $eventinfo->editable = false;
    $eventdata->startdatetime = date('d-m-Y H:i', $timestart);
    if($timeduration){
        $eventdata->enddatetime = date('d-m-Y H:i', $timestart + $timeduration);
        $eventinfo->end = date('Y-m-d H:i', $timestart + $timeduration);
    }else if($eventdata->duedate){
        $eventdata->enddatetime = date('d-m-Y H:i', $eventdata->duedate);
        $eventinfo->end = date('Y-m-d H:i', $eventdata->duedate);
    }else if($eventdata->timefinish){
        $eventdata->enddatetime = date('d-m-Y H:i', $eventdata->timefinish);
        $eventinfo->end = date('Y-m-d H:i', $eventdata->timefinish);
    }else{
        $eventdata->enddatetime = false;
        $eventinfo->end = date('Y-m-d H:i', $timestart);
    }

    $eventinfo->extendedProps = $eventdata;
    $returnevents[] = $eventinfo;
}
echo json_encode($returnevents);
