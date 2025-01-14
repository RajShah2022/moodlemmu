<?php

// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or localify
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
* Courses external API
*
* @package    local_courses
* @category   external
* @copyright  eAbyas <www.eabyas.in>
*/

namespace local_courses\task;

class autoenrol_newuser extends \core\task\scheduled_task{
  public function get_name() {
    return get_string('autoenrol_newuser', 'local_courses');
  }
  
  public function execute(){
    global $DB, $CFG;
    $lastruntime=self::get_last_run_time();
    $timenow=time();
    $nextruntime=self::get_next_run_time();
    $course_sql = "SELECT c.*
    FROM {enrol} e
    JOIN {course} c ON  c.id = e.courseid
    WHERE e.enrol = 'auto'
    AND e.status = 0 AND c.visible = 1 ";
    $courses= $DB->get_records_sql($course_sql); 
    require_once($CFG->dirroot.'/local/courses/lib.php'); 
    require_once($CFG->dirroot.'/local/users/lib.php');
    require_once($CFG->dirroot.'/local/courses/includes.php');
    $type = "autoenrol_newuser";
    foreach($courses as $course){
      $pathsql = [];
      $paths = [];
      if(empty($course)){
        throw new \Exception('Course not found');
      }
      //OL-1042 Add Target Audience to courses//
      //
      
      $coursefields="SELECT fieldvalue FROM mdl_local_module_targetaudience mt 
      JOIN {user_info_field} uif ON uif.id=mt.fieldid
      WHERE mt.moduleid = $course->id AND mt.module = 'course' AND fieldvalue IS NOT NULL";
      $coursefields1 = $DB->get_fieldset_sql($coursefields);
      $total_fields=count($coursefields1);

        $userpath=$course->open_path;
        $userpathinfo = $userpath;
        $paths[] = $userpathinfo.'/%';
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
          $pathsql[] = " u.open_path LIKE '{$path}' ";
        }
        $condition = " AND ( ".implode(' OR ', $pathsql).' ) ';
      }
      $fields=['open_areaofwork','open_professionalrole','open_medicalspecialities','open_userenrollment'];
      $prefix='u';
      $array= target_audience_match_field($fields,$prefix,$course);
      $sqlarray=$array['sqlarray'];
      $params=$array['params'];
      $user_sql = "SELECT * FROM {user} u 
      WHERE u.id NOT IN (SELECT DISTINCT ue.userid
      FROM {user_enrolments} ue
      JOIN {enrol} e ON e.id = ue.enrolid
      JOIN {course} c ON c.id = e.courseid
      JOIN {user} u ON u.id = ue.userid
      WHERE c.id=$course->id $condition) AND u.deleted=0 and u.suspended=0 $condition ";
      if(!empty($sqlarray)) {
        $user_sql .= "AND $sqlarray ";
      }
      $customtargetusers = $DB->get_records_sql($user_sql,$params);
      if(!empty($coursefields1)){
        $fromsql='';
       if(isset($customtargetusers)){ 
         foreach($customtargetusers as $customtargetuser){
          $id=0;
          foreach($coursefields1 as $field){
          $query="SELECT uid.userid as userid FROM {user_info_data} uid JOIN {local_module_targetaudience} lmt  
          ON lmt.fieldid = uid.fieldid WHERE lmt.moduleid = $course->id AND uid.userid= $customtargetuser->id AND FIND_IN_SET(uid.data,'$field')";
           $coursefields_user = $DB->get_field_sql($query);
           if($coursefields_user){
            $id++;
           }
           if($id==$total_fields){
              $users[]=$coursefields_user;
             }
          }
        }
      }
    }else{
      $users=$customtargetusers;
    }
    auto_enrol_users($users,$course->id);
      
    }
  }
}
