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
 * Event observers used in this plugin
 *
 * @package    enrol_auto
 * @copyright  Eugene Venter <eugene@catalyst.net.nz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_auto;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/enrol/locallib.php');
require_once($CFG->dirroot . '/enrol/auto/lib.php');
require_once($CFG->dirroot.'/local/users/lib.php');
/**
 * Event observer for enrol_auto.
 */
class observer {

    /**
     * Triggered via course_module_viewed event of a module.
     *
     * @param stdClass $event
     */
    // public static function course_module_viewed($event) {
    //     global $DB;

    //     $eventdata = $event->get_data();

    //     if (!enrol_is_enabled('auto')) {
    //         return;
    //     }

    //     if (is_siteadmin($eventdata['userid'])) {
    //         // Don't enrol site admins
    //         return;
    //     }

    //     $autoplugin = enrol_get_plugin('auto');

    //     if ((!$instance = $autoplugin->get_instance_for_course($eventdata['courseid']))
    //             || $instance->status == ENROL_INSTANCE_DISABLED) {
    //         return;
    //     }

    //     if ($instance->customint3 != ENROL_AUTO_MOD_VIEWED || empty($instance->customtext2)) {
    //         // nothing to see here :D
    //         return;
    //     }

    //     $enabledmods = explode(',', $instance->customtext2);
    //     $modname = str_replace('mod_', '', $eventdata['component']);
    //     if (!in_array($modname, $enabledmods)) {
    //         return;
    //     }
    //     //OL-1042 Add Target Audience to courses//
    //      //
    //         $params = array();
    //         $userid=$eventdata['userid'];
    //         $user_sql="SELECT id,open_departmentid,open_hrmsrole,open_ouname,open_grade,
    //                     open_designation,city FROM {user} where id=$userid";

    //         $userdata= $DB->get_record_sql($user_sql);           

    //         $group_list = $DB->get_records_sql_menu("select cm.id,cm.cohortid as groupid from {cohort_members} cm where cm.userid IN ({$userdata->id})");

    //         $sql = "SELECT e.courseid
    //                 FROM {enrol} e
    //                 WHERE e.enrol = 'auto'
    //                 AND e.status =0 AND e.id=$instance->id ";
    //         if (!empty($group_list)){
    //                  $groups_members = implode(',', $group_list);
    //                  // $params[]= " lp.open_group IN ($groups_members)";
    //                   $params[]= " CASE WHEN e.open_group IS NOT NULL
	// 								then e.open_group IN ($groups_members)
	// 								else 1 = 1 end ";
    //         }
    //         if(!empty($userdata->open_departmentid)){
    //             $params[]= " CASE WHEN e.department!='-1'
    //                             then FIND_IN_SET($userdata->open_departmentid,e.department) 
    //                             else 1 = 1 end ";
    //         }
    //         if(!empty($userdata->open_hrmsrole)){
    //               $params[]= " CASE WHEN e.open_hrmsrole IS NOT NULL
    //                             then FIND_IN_SET('$userdata->open_hrmsrole',e.open_hrmsrole) 
    //                             else 1 = 1 end ";
    //         }
    //         if(!empty($userdata->open_designation)){
    //               $params[]= " CASE WHEN e.open_designation IS NOT NULL
    //                             then FIND_IN_SET('$userdata->open_designation',e.open_designation) 
    //                             else 1 = 1 end ";
    //         }
          
    //         // if(!empty($userdata->city)){
    //         //     $params[]= " CASE WHEN e.open_location IS NOT NULL
    //         //                     then FIND_IN_SET('$userdata->city',e.open_location) 
    //         //                     else 1 = 1 end ";
    //         // }
    //          if(!empty($userdata->open_country)){
    //             $params[]= " CASE WHEN e.open_country IS NOT NULL
    //                             then FIND_IN_SET('$userdata->open_country',e.open_country) 
    //                             else 1 = 1 end ";
    //         }

    //         if(!empty($userdata->open_ouname)){
    //             $params[]= " CASE WHEN e.open_ouname!='-1'
    //                             then FIND_IN_SET('$userdata->open_ouname',e.open_ouname) 
    //                             else 1 = 1 end ";
    //         }
            
    //         if(!empty($params)){
    //             $finalparams=implode('AND',$params);
    //         }else{
    //             $finalparams= '1=1' ;
    //         }
    //         $sql .= " AND ($finalparams OR (e.open_hrmsrole IS NULL AND e.open_designation IS NULL AND e.open_country IS NULL AND e.open_group IS NULL AND e.open_ouname= '-1' AND e.department='-1' )  )";
    //         $coursetype_sql = "SELECT id,shortname FROM {local_course_types} WHERE shortname NOT IN ('learningpath', 'ilt') AND active = 1 ";
    //         $coursetype = $DB->get_fieldset_sql($coursetype_sql);
    //         $coursetypes = implode(",",$coursetype);
    //         $course_sql = "SELECT id FROM {course} WHERE id =$instance->courseid AND open_identifiedas IN ($coursetypes)";//FIND_IN_SET(3,open_identifiedas)";  
            
    //     //$course_sql = "SELECT id FROM {course} WHERE id =$instance->courseid AND FIND_IN_SET(3,open_identifiedas)";    
    //     //OL-1042 Add Target Audience to courses////
    //     if (!$DB->record_exists('user_enrolments', array('enrolid' => $instance->id, 'userid' => $eventdata['userid']))&&$DB->record_exists_sql($sql)&&$DB->record_exists_sql($course_sql)) {
    //         $autoplugin->enrol_user($instance, $eventdata['userid'], $instance->roleid);

    //         if ($instance->customint2) {
    //             self::schedule_welcome_email($instance, $eventdata['userid']);
    //         }
    //     }
    // }

    /**
     * Triggered via the user_loggedin event, when a user logs in.
     *
     * @param stdClass $event
     */
    public static function user_loggedin($event) {
        global $DB,$USER;
        $eventdata = $event->get_data();
       
        if (!enrol_is_enabled('auto')) {
            return;
        }

        if (is_siteadmin($eventdata['userid'])) {
            // Don't enrol site admins
            return;
        }

        // Get all courses that have an auto enrol plugin, set to auto enrol on login, where the user isn't enrolled yet
        $sql = "SELECT e.courseid
            FROM {enrol} e
            LEFT JOIN {user_enrolments} ue ON e.id = ue.enrolid AND ue.userid = :userid
            JOIN {course} c ON  c.id = e.courseid
            WHERE e.enrol = 'auto'
            AND e.status = 0
            AND ue.id IS NULL
            AND c.visible = 1 ";
        $paths = [];
        $usercostcenterpaths = $DB->get_records_menu('user', array('id' => $USER->id), '', 'id, open_path');
        foreach($usercostcenterpaths AS $userpath){ 
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
        }
        if(!empty($paths)){
            foreach($paths AS $path){
                $pathsql[] = " c.open_path LIKE '{$path}' ";
            }
            $sql .= " AND ( ".implode(' OR ', $pathsql).' ) ';
        }
        $fromsql='';
        profile_load_custom_fields($USER);
        if(isset($USER->profile) && !empty($USER->profile)){                
            foreach($USER->profile as $pkey => $pval){
                $usercustomcatgid = get_usercustomfield_category($USER);
                $uif = $DB->get_record('user_info_field',array('shortname' => $pkey,'categoryid' => $usercustomcatgid, 'targetaudience' => 1),'id,shortname,targetaudience,datatype');
                
                $targetsql = "SELECT c.id FROM {local_module_targetaudience} lmt JOIN {course} c ON c.id = lmt.moduleid  WHERE fieldid = :fieldid AND c.visible = 1 and c.id >1";
                if($DB->record_exists_sql($targetsql,array('fieldid' => $uif->id)))   {    
                   
                    if($pkey === $uif->shortname){
                    /*   $fromsql .= " JOIN {local_module_targetaudience} as {$uif->shortname} ON {$uif->shortname}.fieldid = {$uif->id} 
                                        AND (CONCAT(',', {$uif->shortname}.fieldvalue,',') LIKE CONCAT('%,{$pval},%') OR {$uif->shortname}.fieldvalue IS NULL) 
                                        AND {$uif->shortname}.moduleid = c.id AND {$uif->shortname}.module = 'course'"; */
                        $fromsql .= " JOIN {local_module_targetaudience} as {$uif->shortname} ON {$uif->shortname}.fieldid = {$uif->id} ";                                       
                                        
                        if($uif->datatype == 'autocomplete'){
                            $uservalues = explode(',',$pval);
                            foreach($uservalues as $ukey=>$uval){
                                $uval = "'".trim($uval)."'";
                                $autosql[] = " FIND_IN_SET({$uval}, {$uif->shortname}.fieldvalue ) ";
                            }
                            $autosql[] = " {$uif->shortname}.fieldvalue IS NULL ";
                            $fromsql .= " AND ( ".implode(' OR ', $autosql).' ) ';
                        }else{
                            $fromsql .= " AND (CONCAT(',', {$uif->shortname}.fieldvalue,',') LIKE CONCAT('%,{$pval},%') OR {$uif->shortname}.fieldvalue IS NULL) ";
                        }
                        $fromsql .= "AND {$uif->shortname}.moduleid = c.id AND {$uif->shortname}.module = 'course'";
                    }
                }
            }              
        }   
        //   if(!empty($USER->open_designation) && $USER->open_designation != "" && $USER->open_designation != NULL){                 
        //     $sql .= " AND ( concat(',',c.open_designation,',') LIKE '%,$USER->open_designation,%'  OR c.open_designation = '-1' OR c.open_designation = '' OR c.open_designation IS NULL OR c.open_designation = '_qf__force_multiselect_submission')";
        // }   
     
            $fields=['open_designation'];
            $prefix='c';
            $array= target_audience_match_field($fields,$prefix);
            $sqlarray=$array['sqlarray'];
            $params=$array['params'];
            if (!empty($sqlarray)){
                $sql .=" AND $sqlarray ";
            }
     
        $params['userid']=$eventdata['userid'];
        if (!$courses = $DB->get_records_sql($sql.$fromsql,$params)) {
            return;
        }
        $autoplugin = enrol_get_plugin('auto');
        foreach ($courses as $course) {
            if (!$instance = $autoplugin->get_instance_for_course($course->courseid)) {
                continue;
            }
        //     $coursetype_sql = "SELECT id,shortname FROM {local_course_types} WHERE shortname NOT IN ('learningpath', 'ilt') AND active = 1 ";
        //     $coursetype = $DB->get_fieldset_sql($coursetype_sql);
        //     $coursetypes = implode(",",$coursetype);
        //     $course_sql = "SELECT id FROM {course} WHERE id =$course->courseid AND open_identifiedas IN ($coursetypes)";//FIND_IN_SET(3,open_identifiedas)";  
            
        //    if($DB->record_exists_sql($course_sql)) {  

                $autoplugin->enrol_user($instance, $eventdata['userid'], $instance->roleid);

        //         if ($instance->customint2) {
        //             self::schedule_welcome_email($instance, $eventdata['userid']);
        //         }
        //    }
        }
    }

    public static function schedule_welcome_email($instance, $userid) {
        global $DB;

        $user = $DB->get_record('user', array('id' => $userid));
        if (empty($user)) {
            // wat?
            return false;
        }

        // Schedule welcome message task.
        $emailtask = new \enrol_auto\task\course_welcome_email();
        // add custom data
        $emailtask->set_custom_data(array(
            'user' => $user,
            'instance' => $instance
        ));
        // queue it
        \core\task\manager::queue_adhoc_task($emailtask);
    }
}
