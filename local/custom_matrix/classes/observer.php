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
 * local courses
 *
 * @package    local_learningplan
 * @copyright  2019 eAbyas <eAbyas.in>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();
require_once($CFG->dirroot . '/local/custom_matrix/lib.php');
/**
 * Event observer for local_learningplan. Dont let other user to view unauthorized courses
 */
class local_custom_matrix_observer  {

    public static function user_course_performance(\core\event\course_completed $event){
        global $DB;
        $roletype = get_config('local_custom_matrix','performance_matrix_role_type'); 
        try{
            $rolesetting = get_config('local_custom_matrix', 'performance_matrix_role_type');

            $custom_categories = get_module_custom_categories($event->courseid,'course');
            foreach($custom_categories as $catg){
                $tempid = get_active_template($catg->costcenterid);

                $performance_sql = "SELECT * FROM {local_performance_matrix} WHERE performancecatid = :parentid AND path LIKE :costcenterid AND templateid = :templateid";  
                if(isset($rolesetting) && $rolesetting != 0){
                    $role = user_designation_position($event->relateduserid);
                    $performance_sql .= " AND role = :role ";
                }                
                if($performance = $DB->get_record_sql($performance_sql,array('parentid' => $catg->parentid,'costcenterid' => '/'.$catg->costcenterid,'templateid'=>$tempid,'role' => $role))){ 
                    
                    $points_sql = "SELECT open_points FROM {course} WHERE id = :courseid AND visible = 1 AND open_points <> 0 AND performanceparentid = :parentid AND performancecatid = :catid";

                    $course_points = $DB->get_field_sql($points_sql,array('courseid'=>$event->courseid,'parentid' => $catg->parentid,'catid' => $catg->id ));
                    $performance->pointsachieved = $course_points;
                    
                    user_performance_logs($event,'course',$event->courseid,$performance,$course_points);
                }               
            
            }
        }catch(\Exception $e){
            debugging($e->getMessage());
        }
    }
  
    public static function user_classroom_performance(\local_classroom\event\classroom_user_completed $event){
        global $DB;
        try{
          
            $custom_categories = get_module_custom_categories($event->objectid,'classroom'); 

            foreach($custom_categories as $catg){
                
                $performance_sql = "SELECT * FROM {local_performance_matrix} WHERE performancecatid = :performancecatid AND concat('/',path,'/') LIKE :costcenterid";
                if($performance = $DB->get_record_sql($performance_sql,array('performancecatid' => $catg->id,'costcenterid' => '%'.$catg->costcenterid .'%'))){
                    $points_sql = "SELECT open_points FROM {local_classroom} WHERE id = :classroomid AND visible = 1 AND open_points <> 0 ";
                    $classroom_points = $DB->get_field_sql($points_sql,array('classroomid'=>$event->objectid));
                    $performance->pointsachieved = $classroom_points;
                   user_performance_logs($event,'classroom',$event->objectid,$performance);
                }                
            
            }
        }catch(\Exception $e){
            debugging($e->getMessage());
        }
    }

    public static function user_learningplan_performance(\local_learningplan\event\learningplan_user_completed $event){
        global $DB;
        try{          
            $custom_categories = get_module_custom_categories($event->objectid,'learningplan');
            foreach($custom_categories as $catg){
                
                $performance_sql = "SELECT * FROM {local_performance_matrix} WHERE performancecatid = :performancecatid AND concat('/',path,'/') LIKE :costcenterid";
                if($performance = $DB->get_record_sql($performance_sql,array('performancecatid' => $catg->id,'costcenterid' => '%'.$catg->costcenterid .'%'))){
                    $points_sql = "SELECT open_points FROM {local_learningplan} WHERE id = :lplan AND visible = 1 AND open_points <> 0 ";
                    $lplan_points = $DB->get_field_sql($points_sql,array('lplan'=>$event->objectid));
                    $performance->pointsachieved = $lplan_points;
                   user_performance_logs($event,'learningplan',$event->objectid,$performance);
                }               
            
            }
        }catch(\Exception $e){
            debugging($e->getMessage());
        }
    }

    public static function user_course_delete_performance(\core\event\course_deleted $event){    
        global $DB;         
        try{            
            $course = $event->get_record_snapshot('course', $event->courseid);
            $performanccat_id = $course->performanceparentid; 
            $courseid = $event->courseid;        
            delete_performance_logs($performanccat_id,$courseid,'course');

        }catch(\Exception $e){
            debugging($e->getMessage());
        }
    }

}
