<?php

function block_eventtimetable_create_attendance($courseid){
    global $DB, $CFG;
    require_once($CFG->dirroot.'/course/modlib.php');
    $newcourse = get_course($courseid);
    // Attendance Module addition to the courses.
    $instance_type = 'attendance';
    $moduleinfo = new \stdClass();
    $moduleinfo->timemodified = time();
    $moduleinfo->module = $DB->get_field('modules','id',array('name'=>$instance_type));
    $moduleinfo->modulename= $instance_type;
    $moduleinfo->name = 'Time Table';
    $moduleinfo->section = 1;
    $moduleinfo->visible = 1;
    $moduleinfo->visible = 1;
    $moduleinfo->grade = 0;
    $moduleinfo = add_moduleinfo($moduleinfo, $newcourse, null);
    $attendanceinfo = new \stdClass();
    $attendanceinfo->id = $moduleinfo->coursemodule;
    $attendanceinfo->name = 'attendance';
    return $attendanceinfo;
}
// function block_eventtimetable_get_event_info($event){
//     global $DB;
//     $functionname = 'block_eventtimetable_'.$event->modulename.'_event';
//     if(function_exists($functionname)){
//         $eventdata = $functionname($event);
//     }else{
//         $eventdata = $event;
//     }
//     return $eventdata;
// }
// function block_eventtimetable_attendance_event($event){
//     global $DB;
//     $eventdata = $DB->get_record_sql("SELECT ats.id, ats.sessionname AS name, ats.* FROM {attendance_sessions} AS ats WHERE ats.caleventid = :caleventid", array('caleventid' => $event->id));
//     return $eventdata;
// }
// function block_eventtimetable_can_access_event($event){
//     global $DB;
//     if(is_siteadmin() || has_capability('block/eventtimetable:view_all_events', $systemcontext)){
//         return true;
//     }
//     $coursecontext = context_course::instance($event->courseid);
//     $is_enrolled = is_enrolled($coursecontext, $USER->id);
// }


function block_eventtimetable_leftmenunode(){

    $systemcontext = \local_costcenter\lib\accesslib::get_module_context();
    $eventtimetablenode = '';
    if(has_capability('block/eventtimetable:eventtimetable_menu', $systemcontext) || is_siteadmin()) {
        $eventtimetablenode .= html_writer::start_tag('li', array('class' => 'pull-left user_nav_div browsecertifications'));
            $eventtimetable_url = new moodle_url('/blocks/eventtimetable/eventcalender.php');
            $eventtimetable = html_writer::link($eventtimetable_url, '<i class="fa fa-calendar"></i><span class="user_navigation_link_text">'.get_string('eventcalender','block_eventtimetable').'</span>',array('class'=>'user_navigation_link'));
            $eventtimetablenode .= $eventtimetable;
        $eventtimetablenode .= html_writer::end_tag('li');
    }
    return array('28' => $eventtimetablenode);
}
