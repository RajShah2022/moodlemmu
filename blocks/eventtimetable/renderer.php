<?php

use plugin_renderer_base;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/tablelib.php');
require_once($CFG->dirroot . '/local/costcenter/lib.php');

class block_eventtimetable_renderer extends plugin_renderer_base
{
    public function render_timetable()
    {
        global $DB, $USER, $OUTPUT, $CFG, $PAGE;
        $moduleinfo = [];
        $moduleidsinfo = [];

        $systemcontext = (new \local_costcenter\lib\accesslib())::get_module_context();
        $core_component = new core_component();
        
        $moduleinfo[] = ['id' => null, 'fullname' => get_string('selectmodule', 'block_eventtimetable')];
        if($core_component::get_plugin_directory('local', 'courses')){ 
            $moduleinfo[] = ['id' => 'course', 'fullname' => get_string('course', 'block_eventtimetable')];
        }
        if($core_component::get_plugin_directory('local', 'classroom')){ 
            $moduleinfo[] = ['id' => 'classroom', 'fullname' => get_string('classroom', 'block_eventtimetable')];
        }
        if(!is_siteadmin() && has_capability('block/eventtimetable:view_events_trainer', $systemcontext)){
            $trainer = true;

            $costcenterpathconcatsql = (new \local_costcenter\lib\accesslib())::get_costcenter_path_field_concatsql($columnname='lcr.open_path');
            $classroomsql = "SELECT lcr.id, lcr.name AS fullname FROM {local_classroom} AS lcr
                JOIN {local_classroom_trainers} AS lct ON lct.classroomid = lcr.id 
                WHERE 1 AND lct.trainerid = ".$USER->id." AND lcr.status = 1 ".$costcenterpathconcatsql;
            $classroomids = $DB->get_records_sql($classroomsql);
            $moduleidsinfo[] = ['id' => null, 'fullname' => 'Select Classroom'];
            if(!empty($classroomids)){
            // print_r($classroomids); exit;
                foreach($classroomids AS $classroomid){
                    $moduleidsinfo[] = ['id' => $classroomid->id, 'fullname' => $classroomid->fullname];
                }
            }
        } else {
            $trainer = false;
            $moduleidsinfo[] = ['id' => null, 'fullname' => get_string('selecthere', 'block_eventtimetable')];
        }
        $renderedtemplate = $this->render_from_template('block_eventtimetable/timetable', 
            [
                'moduleinfo' => array_values($moduleinfo),
                'is_admin' => is_siteadmin(),
                'moduleidsinfo' => array_values($moduleidsinfo),
                'is_trainer' => $trainer
            ]);
        return $renderedtemplate;
    }
}
