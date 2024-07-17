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
class course_reminder extends \core\task\scheduled_task {

	public function get_name() {
        return get_string('taskcoursereminder', 'local_courses');
    }

	public function execute(){
		global $DB;
        $type = "course_reminder";
        /*Getting the notification type id*/
        $find_type_id = $DB->get_field('local_notification_type','id',array('shortname'=>$type));
        $corecomponent = new \core_component();
		$costcenterexist = $corecomponent::get_plugin_directory('local','costcenter');
        /*Getting the notification record to find the users*/
        $get_type_sql = "SELECT * FROM {local_notification_info} WHERE notificationid=:notificationid AND active=1 AND (moduleid IS NOT NULL OR moduleid = 0) ";
        $params = array('notificationid' => $find_type_id);
        $get_type_notifications = $DB->get_records_sql($get_type_sql, $params);
        $moduleids = array();
        foreach($get_type_notifications AS $notification){
            $notification->costcenterid = explode('/',$notification->open_path)[1];
            $moduleids[] = $notification->moduleid;
            $this->send_reminder_notification($notification, $type, $costcenterexist);   	
        }

	}

    public function send_reminder_notification($notification, $type, $costcenterexist){
        global $DB;
        $day = $notification->reminderdays;
       
        $starttime = strtotime(date('d-m-Y', strtotime("+".$day." days")));
        $endtime = $starttime+86399;
        $params = array();
     
        $sql = "SELECT  ue.*, e.id AS enrolid, c.id AS courseid, c.fullname 
            FROM {user_enrolments} ue  
            JOIN {enrol} e ON e.id = ue.enrolid 
            JOIN {course} c ON e.courseid = c.id 
            LEFT JOIN {course_completions} AS cc ON cc.course=e.courseid AND cc.userid = ue.userid AND c.id = cc.course
            WHERE (ue.timecreated BETWEEN :starttime AND :endtime) AND c.id>1 AND c.visible = 1 AND (cc.timecompleted IS NULL OR cc.timecompleted = '') ";
        $params['starttime'] = $starttime;
        $params['endtime'] = $endtime;
        if ($notification->moduleid) {
            $sql .= " AND c.id IN ($notification->moduleid )"; 
        }
        if($costcenterexist){
            $sql .= " AND concat('/',c.open_path,'/') LIKE :costcenterid ";
            $params['costcenterid'] ='%'.$notification->costcenterid .'%';
        }
      
        $enrolcourses=$DB->get_records_sql($sql, $params);
        $this->notification_to_user($enrolcourses, $notification, $type);
    }

    public function notification_to_user($enrolcourses, $notification, $type)
    {
        $coursenotification = new \local_courses\notification();
        $courses = array();
        foreach ($enrolcourses as $enrolcourse) {
            $touser = \core_user::get_user($enrolcourse->userid);
            if (empty($courses[$enrolcourse->courseid])) {
                $courses[$enrolcourse->courseid] = get_course($enrolcourse->courseid);
            }
            $course = $courses[$enrolcourse->courseid];
            $coursenotification->send_course_email($course, $touser, $type, $notification);
        }
    }

/*     public function notification_to_user($enrolcourses, $notification, $type){
        global $DB;
        foreach($enrolcourses as $enrolcourse){
            $sql="SELECT u.* from {user} AS u 
                JOIN {user_enrolments} AS ue ON ue.userid=u.id 
                where ue.enrolid =:enrolid";
            $enrolledusers=$DB->get_records_sql($sql, array('enrolid' => $enrolcourse->enrolid));
            $course = $DB->get_record('course', array('id' => $enrolcourse->courseid)); 
            // Getting the users list to whom the notification should be sent
            foreach($enrolledusers as $user){
                $coursenotification = new \local_courses\notification();
                $coursenotification->send_course_email($course, $user, $type, $notification);
            }
        }
    }
    public function notification_to_end_user($enrolcourses, $notification, $type){
        // global $DB;
        $coursenotification = new \local_courses\notification();
        $courses = array();
        foreach($enrolcourses as $enrolcourse){
            $touser = \core_user::get_user($enrolcourse->userid);
            if(empty($courses[$enrolcourse->courseid])){
                $courses[$enrolcourse->courseid] = get_course($enrolcourse->courseid);
            }
            $course = $courses[$enrolcourse->courseid];
            $coursenotification->send_course_email($course, $touser, $type, $notification);
        }
    } */
}