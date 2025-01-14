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

class course_completion_reminder extends \core\task\scheduled_task
{

    public function get_name()
    {
        return get_string('taskcoursecompletionreminder', 'local_courses');
    }

    public function execute()
    {
        global $DB;
        
        $corecomponent = new \core_component();
        $costcenterexist = $corecomponent::get_plugin_directory('local', 'costcenter');
        
      /*$availiablenotifications = $this->course_completion_due_notifications();
        $emailtype = 'course_completion_reminder';
        foreach ($availiablenotifications as $notification) {
            $notification->costcenterid = explode('/',$notification->open_path)[1];
            $this->send_reminder_notification($notification, $emailtype, $costcenterexist);        
         } */

        $type = "course_completion_reminder";
        /*Getting the notification type id*/
        $find_type_id = $DB->get_field('local_notification_type','id',array('shortname'=>$type));
        $get_type_sql = "SELECT * FROM {local_notification_info} WHERE notificationid=:notificationid AND active=1 AND (moduleid IS NOT NULL OR moduleid <> 0 OR moduleid != '_qf__force_multiselect_submission')";
        $params = array('notificationid' => $find_type_id);
        $get_type_notifications = $DB->get_records_sql($get_type_sql, $params);
        $moduleids = array();
        
        foreach($get_type_notifications AS $notification){
            $notification->costcenterid = explode('/',$notification->open_path)[1];
            $moduleids[] = $notification->moduleid;
            $this->send_reminder_notification($notification, $type, $costcenterexist);   	
        }
        $globalnotification_sql = "SELECT * FROM {local_notification_info} WHERE notificationid=:notificationid AND active=1 AND (moduleid IS  NULL OR moduleid = 0)";
        $params = array('notificationid' => $find_type_id);
        $global_notifications = $DB->get_records_sql($globalnotification_sql, $params);
        $moduleids = implode(',', $moduleids);
        foreach($global_notifications AS $notification){
            $notification->costcenterid = explode('/',$notification->open_path)[1];
            $this->send_global_reminder_notification($notification, $type, $moduleids, $costcenterexist);
        }        
        
    }

/*     private function course_completion_due_notifications()
    {
        global $DB;
        $globalnotification_sql = "SELECT lni.* FROM {local_notification_info} AS lni 
                WHERE lni.notificationid=(SELECT id FROM {local_notification_type} WHERE shortname=:shortname) order by id desc";
        $notifications = $DB->get_records_sql($globalnotification_sql, array('shortname' => 'course_completion_reminder'));
        return $notifications;
    } */


    public function send_reminder_notification($notification, $type, $costcenterexist)
    {

        global $DB;
        $day = $notification->reminderdays;
        //echo strtotime(date('d-m-Y', strtotime($today)));;die;
        $today = date('Y-m-d');
        $starttime = strtotime(date('d-m-Y', strtotime("+".$day." days")));
        $endtime = $starttime+86399;
       
        $params = array();
        $sql = "SELECT  ue.*, e.id AS enrolid, c.id AS courseid, c.fullname ,date(from_unixtime( c.timecreated)) as timecreated,
                     date(from_unixtime( ue.timecreated)) as enrolmentdate
                    FROM {user_enrolments} ue  
                    JOIN {user} u ON  ue.userid = u.id AND u.confirmed = 1 AND u.deleted = 0 AND u.suspended = 0
                    JOIN {enrol} e ON e.id = ue.enrolid 
                    JOIN {course} c ON e.courseid = c.id 
                    LEFT JOIN {course_completions} AS cc ON cc.course=e.courseid AND cc.userid = ue.userid AND c.id = cc.course
                    WHERE date(from_unixtime( (ue.timecreated+(($day)*86400)))) =  :today 
                    AND c.id>1 AND c.visible = 1 AND (cc.timecompleted IS NULL OR cc.timecompleted = '') ";
        if ($notification->moduleid) {
           $sql .= " AND c.id IN ($notification->moduleid )"; 
        }
        if($costcenterexist){
            $sql .= " AND concat('/',c.open_path,'/') LIKE :costcenterid ";
            $params['costcenterid'] ='%'.$notification->costcenterid .'%';
        }
        $params['starttime'] = $starttime;
        $params['endtime'] = $endtime;
        $params['today'] = $today;
        $enrolcourses = $DB->get_records_sql($sql, $params);
     
        $this->notification_to_user($enrolcourses, $notification, $type);       
    }

    public function send_global_reminder_notification($notification, $type, $moduleids, $costcenterexist)
    {

        global $DB;
        $day = $notification->reminderdays;
        //echo strtotime(date('d-m-Y', strtotime($today)));;die;
        $today = date('Y-m-d');
        $starttime = strtotime(date('d-m-Y', strtotime("+".$day." days")));
        $endtime = $starttime+86399;
       
        $params = array();
        $sql = "SELECT  ue.*, e.id AS enrolid, c.id AS courseid, c.fullname ,date(from_unixtime( c.timecreated)) as timecreated,
                     date(from_unixtime( ue.timecreated)) as enrolmentdate
                    FROM {user_enrolments} ue  
                    JOIN {user} u ON  ue.userid = u.id AND u.confirmed = 1 AND u.deleted = 0 AND u.suspended = 0
                    JOIN {enrol} e ON e.id = ue.enrolid 
                    JOIN {course} c ON e.courseid = c.id 
                    LEFT JOIN {course_completions} AS cc ON cc.course=e.courseid AND cc.userid = ue.userid AND c.id = cc.course
                    WHERE date(from_unixtime( (ue.timecreated+(($day)*86400)))) =  :today 
                    AND c.id>1 AND c.visible = 1 AND (cc.timecompleted IS NULL OR cc.timecompleted = '') ";
        if(trim($moduleids) != ''){
            $sql .= " AND c.id NOT IN ($moduleids) ";
        }
        if($costcenterexist){
            $sql .= " AND concat('/',c.open_path,'/') LIKE :costcenterid ";
            $params['costcenterid'] ='%'.$notification->costcenterid .'%';
        }
        $params['starttime'] = $starttime;
        $params['endtime'] = $endtime;
        $params['today'] = $today;
        $enrolcourses = $DB->get_records_sql($sql, $params);
     
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
}
