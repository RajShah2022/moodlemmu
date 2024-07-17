<?php
namespace block_eventtimetable;
class lib {
    public function can_access_event($event){
        global $DB, $USER, $CFG;
        require_once($CFG->dirroot . '/local/costcenter/lib.php');
        require_once($CFG->dirroot . '/local/courses/lib.php');
        $systemcontext = (new \local_costcenter\lib\accesslib())::get_module_context();

        $return = false;
        if($event->eventtype == 'due' || ($event->eventtype == 'close' && $event->modulename == 'quiz')){
            return false;
        }
       
        return true;
        return $return;
    }
    public function get_event_info($event){
        global $DB, $CFG;
        require_once($CFG->dirroot . '/local/costcenter/lib.php');
        $systemcontext = (new \local_costcenter\lib\accesslib())::get_module_context();
        $methodname = $event->modulename.'_event';
        if(method_exists($this, $methodname)){
            $eventdata = $this->$methodname($event);
            if(is_siteadmin() || has_capability('block/eventtimetable:view_current_trg_events', $systemcontext) || has_capability('block/eventtimetable:view_events_trainer', $systemcontext)){
                $eventdata->is_admin = true;
            }
            $eventdata->companyview = is_siteadmin() ? true : false ;
        }else{
            $eventdata = $event;
            $eventinfo = $DB->get_record_sql("SELECT c.id , c.fullname as coursename FROM {course} AS c WHERE c.id = :courseid ", array('courseid' => $eventdata->courseid));
            $eventdata->courseurl = "$CFG->wwwroot/course/view.php?id={$eventdata->courseid}";
            $eventdata->coursename = $eventinfo->coursename;

        }
        return $eventdata;
    }
    private function quiz_event($event){
        global $DB, $CFG;
        $eventdata = $DB->get_record_sql("SELECT mq.id, mq.name, mq.timeopen, mq.timeclose, cm.id as cmid, mq.intro AS description, c.fullname AS coursename, c.id AS courseid, c.open_path
            FROM {quiz} AS mq
            JOIN {modules} AS m ON m.name LIKE 'quiz'
            JOIN {course_modules} AS cm ON cm.instance = mq.id AND cm.module = m.id
            JOIN {course} AS c ON c.id = cm.course
            
            WHERE mq.id = :instance AND cm.course = :course", array('instance' => $event->instance, 'course' => $event->courseid));
        $orgid = explode('/', $eventdata->open_path)[1];
        $org = $DB->get_record('local_costcenter', array('id'=> $orgid), 'fullname');
        // print_r($eventcolor); exit;
        $eventdata->orgname = $org->fullname; 
        $eventdata->courseurl = "$CFG->wwwroot/course/view.php?id={$eventdata->courseid}";
        $eventdata->timestart = $eventdata->timeopen;
        if($eventdata->timeclose){
            $eventdata->timeduration = $eventdata->timeclose - $eventdata->timeopen;
        }
        $eventdata->dataurl = "$CFG->wwwroot/mod/quiz/view.php?id={$eventdata->cmid}";
        $eventdata->launchstring = 'Go to Activity';
        $eventdata->is_enrolled = true;
        $courseeventcolor = get_config('block_eventtimetable','courseeventcolor');
        $eventcolor = json_decode($courseeventcolor);
        $eventdata->eventcolor = $eventcolor->courseevent_color ? $eventcolor->courseevent_color : $courseeventcolor;
        return $eventdata;
    }

    private function assign_event($event){
        global $DB, $CFG;
        $eventdata = $DB->get_record_sql("SELECT ma.id, ma.name, ma.allowsubmissionsfromdate, ma.duedate, cm.id as cmid, ma.intro AS description, c.fullname AS coursename, c.id AS courseid, c.open_path
            FROM {assign} AS ma
            JOIN {modules} AS m ON m.name LIKE 'assign'
            JOIN {course_modules} AS cm ON cm.instance = ma.id AND cm.module = m.id
            JOIN {course} AS c ON c.id = cm.course
            WHERE ma.id = :instance AND cm.course = :course", array('instance' => $event->instance, 'course' => $event->courseid));
        $orgid = explode('/', $eventdata->open_path)[1];
        $org = $DB->get_record('local_costcenter', array('id'=> $orgid), 'fullname');
        $eventdata->orgname = $org->fullname;
        $eventdata->courseurl = "$CFG->wwwroot/course/view.php?id={$eventdata->courseid}";
        $eventdata->timestart = $eventdata->allowsubmissionsfromdate;
        $eventdata->timeduration = 0;
        $eventdata->dataurl = "$CFG->wwwroot/mod/assign/view.php?id={$eventdata->cmid}";
        $eventdata->launchstring = 'Go to Activity';
        $eventdata->is_enrolled = true;
        $courseeventcolor = get_config('block_eventtimetable','courseeventcolor');
        $eventcolor = json_decode($courseeventcolor);
        $eventdata->eventcolor = $eventcolor->courseevent_color ? $eventcolor->courseevent_color : $courseeventcolor;

        return $eventdata;
    }
    
    private function classroom_event($event){
        global $DB, $CFG, $USER;
        $query = "SELECT lcs.*, lcs.name AS name, lcr.name AS coursename, lcr.open_path, lcr.id AS classroomid, lcr.approvalreqd, lcr.selfenrol, concat(u.firstname,' ',u.lastname) as fullname
            FROM {local_classroom_sessions} AS lcs
            JOIN {local_classroom} AS lcr ON lcr.id = lcs.classroomid
            LEFT JOIN {user} AS u ON u.id = lcs.trainerid";
        $where = " WHERE lcs.id = :itemid";
        $eventdata = $DB->get_record_sql($query.$where, array('itemid' => $event->plugin_itemid));
        // print_r($eventdata); die;

        $is_enrolled = $DB->record_exists('local_classroom_users', array ('userid' => $USER->id, 'classroomid' => $eventdata->classroomid));
        $is_requested = $DB->get_field('local_request_records', 'status', array('componentid'=>$eventdata->classroomid, 'createdbyid'=>$USER->id, 'compname'=>'classroom', 'status'=>'PENDING'));
        $organization = explode('/', $eventdata->open_path)[1];
        $org = $DB->get_record('local_costcenter', array('id'=> $organization), 'fullname');

        $eventdata->orgname = $org->fullname;
        $eventdata->is_enrolled = $is_enrolled;
        $eventdata->courseurl = "$CFG->wwwroot/local/classroom/view.php?cid={$eventdata->classroomid}";
        
        $eventdata->instructors = $eventdata->fullname ? $eventdata->fullname : 'N/A';
        $eventdata->dataurl = "$CFG->wwwroot/local/classroom/view.php?cid={$eventdata->classroomid}";
        $eventdata->launchstring = 'Go to Activity';
        if(!$is_enrolled && $eventdata->approvalreqd){
            $eventdata->enroltocr = 'Request';

            // $eventdata->eventcolor = $is_requested ? '#8627db' : '#16251d';
        }else if(!$is_enrolled && !$eventdata->approvalreqd && $eventdata->selfenrol){
            $eventdata->enroltocr = 'Enroll';
            $eventdata->approvalreqd = false;
            // $eventdata->eventcolor = '#540b26';
        }/*else{
            // $eventdata->eventcolor = '#16251d';
        }*/
        $eventdata->is_requested = $is_requested ? $is_requested : false;
        $classroomeventcolor = get_config('block_eventtimetable','classroomeventcolor');
        $eventcolor = json_decode($classroomeventcolor);
        $eventdata->eventcolor = $eventcolor->classroomevent_color ? $eventcolor->classroomevent_color : $classroomeventcolor;
        return $eventdata;
    }
}
