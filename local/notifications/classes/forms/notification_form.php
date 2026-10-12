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
 *
 * @package    notifications
 * @copyright  2018 sreenivas
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace local_notifications\forms;
require_once($CFG->libdir . '/formslib.php');
require_once($CFG->dirroot .'/local/notifications/lib.php');
require_once($CFG->dirroot .'/local/costcenter/lib.php');
use moodleform;
use stdClass;
class notification_form extends moodleform {
    // public $formstatus;
    public function __construct($action = null, $customdata = null, $method = 'post', $target = '', $attributes = null, $editable = true, $formdata = null) {

        // $this->formstatus = array(
        //     // 'generaldetails' => get_string('generaldetails', 'local_users'),
        //     // 'otherdetails' => get_string('otherdetails', 'local_users'),
        //     );
        parent::__construct($action, $customdata, $method, $target, $attributes, $editable, $formdata);
    }

    public function definition() {
        global $DB, $PAGE, $USER, $OUTPUT;
        $mform = $this->_form;
        $lib = new \notifications();
        // $form_status = $this->_customdata['form_status'];
        $org = $this->_customdata['open_costcenterid'];
        $id = $this->_customdata['id'] > 0 ? $this->_customdata['id'] : 0;
		$context =(new \local_notifications\lib\accesslib())::get_module_context();
		$moduleid = $this->_customdata['moduleid'];
		$notificationid = $this->_customdata['notificationid'];
		if($id){
       		$formdata = $DB->get_record('local_notification_info', array('id' => $id));
		}else{
			$formdata = new stdClass();
		}
		$categorycontext = (new \local_notifications\lib\accesslib())::get_module_context();
        // if($form_status == 0){          
            local_costcenter_get_hierarchy_fields($mform, $this->_ajaxformdata, $this->_customdata,range(1,1), false, 'local_users', $categorycontext, $multiple = false);
            $notification_type = array();
            $select = array();
            $select[null] = get_string('select_opt', 'local_notifications');
            $notification_type[null] = $select;
            $module_categories = $DB->get_records('local_notification_type', array('parent_module'=>0));
            if($module_categories){
                 foreach($module_categories as $module_category){
					// $module_category->shortname = ($module_category->shortname == 'course') ? 'courses' : $module_category->shortname;
					$module_category->shortname = ($module_category->shortname == 'onlineexam') ? 'onlineexams' : $module_category->shortname;
					$module_category->shortname = ($module_category->shortname == 'feedback') ? 'evaluation' : $module_category->shortname;
					$plugin_exists = \core_component::get_plugin_directory('local', $module_category->pluginname); 
					if($plugin_exists){					
                   		$notifications = $DB->get_records_sql_menu("SELECT * FROM {local_notification_type} WHERE parent_module = {$module_category->id} AND parent_module <> 0 AND shortname NOT IN ('course_notification','course_reminder','classroom_invitation', 'program_invitation','forum','forum_subscription','forum_unsubscription','forum_reply','forum_post','lep_reminder')");// and shortname!='classroom_invitation'and shortname!='program_invitation' and shortname NOT LIKE '%forum%'AND (shortname NOT LIKE 'course_reminder') 
                    	$notification_type[$module_category->name] = $notifications;
					}
                }
            }
            
            $mform->addElement('selectgroups', 'notificationid', get_string('notification_type', 'local_notifications'), $notification_type,array());
            $mform->addRule('notificationid', null, 'required');//, null, 'client'  
            $mform->addHelpButton('notificationid','notification_help','local_notifications');
      		/*$coursereminder = $DB->get_field('local_notification_type', 'id', array('shortname' => 'course_reminder'));
             if(($this->_ajaxformdata['open_costcenterid'] && $this->_ajaxformdata['notificationid'])||$formdata->notificationid == $coursereminder){
            	if($this->_ajaxformdata['open_costcenterid']){
            		$costcenterid = $this->_ajaxformdata['open_costcenterid'];
            	}else{
            		$costcenterid = $formdata->open_costcenterid;
            	}
            	$completiondays_sql = "SELECT open_coursecompletiondays AS value, open_coursecompletiondays AS completiondays 
            		FROM {course} WHERE id > 1 AND open_coursecompletiondays IS NOT NULL 
            		AND concat('/',open_path,'/') LIKE :costcenterpath
            		GROUP BY open_coursecompletiondays ";
					$params['costcenterpath'] = '%'.$costcenterid.'%';
            	$completiondays = $DB->get_records_sql_menu($completiondays_sql,$params);
            }else{
            	$completiondays = array();
            }
            $completiondays = array(null => get_string('selectcompletiondays', 'local_notifications')) + $completiondays;
            $mform->addElement('select', 'completiondays', get_string('completiondays', 'local_notifications').'<abbr class="initialism text-danger" title="Required"><img src='.$OUTPUT->image_url("new_req").'></abbr>',  $completiondays, array('id' => 'select_completiondays'));
            $mform->setType('completiondays', PARAM_INT);
            
    		$mform->hideIf('completiondays', 'notificationid', 'neq', $coursereminder); */

            $mform->addElement('text', 'subject', get_string('subject', 'local_notifications'));
            $mform->setType('subject', PARAM_RAW);
			
            $datamoduleids=array();
			$datamodule_label="Courses";
			$strings = 'None';
			$notification_selected = $this->_ajaxformdata['notificationid'];
			$organization_selected = $this->_ajaxformdata['open_costcenterid'];
            if($id > 0 || ($notificationid&&is_array($moduleid)&&!empty($moduleid))){
				if($id > 0){
					$notifyid = $DB->get_record('local_notification_info',  array('id'=>$id));
					
					$notif_type = $DB->get_field('local_notification_type', 'shortname', array('id'=>$notifyid->notificationid));
					$notifyid->costcenterid=explode('/',$notifyid->open_path)[1];
				}else{

					$notif_type = $DB->get_field('local_notification_type', 'shortname', array('id'=>$notificationid));
					$notifyid=new stdClass();
					$notif_type_find=explode('_',$notif_type);
					$notifyid->moduletype=$notif_type_find[0];
					$notifyid->costcenterid=$org;
				}
				$data = $this->get_datamoduleids_labels($notifyid->moduletype, $notifyid->costcenterid, $notif_type);
				$datamoduleids = $data['datamoduleids'];
				$datamodule_label = $data['datamodule_label'];
				$strings = $lib->get_string_identifiers($notif_type);				
				
    		}else if($notification_selected && $organization_selected){
				$notif_type = $DB->get_field('local_notification_type', 'shortname', array('id'=>$notification_selected));
    			$notif_type_find = explode('_',$notif_type);
				$moduletype = $notif_type_find[0]; 
				$data = $this->get_datamoduleids_labels($moduletype, $organization_selected, $notif_type);
				$datamoduleids = $data['datamoduleids'];
				$datamodule_label = $data['datamodule_label'];
				
				$strings = $lib->get_string_identifiers($notif_type);
    		}

			if(strtolower($notifyid->moduletype)!='request'){			
				$courseselect = $mform->addElement('autocomplete', 'moduleid',$datamodule_label,$datamoduleids,array('class' => 'module_label'));
				$mform->setType('moduleid', PARAM_RAW);
				$courseselect->setMultiple(true);

				$notifsql = "SELECT id FROM {local_notification_type} WHERE shortname IN ('certificate_uploaded','certificate_approved','certificate_declined')";
				$notiftypeids = $DB->get_fieldset_sql($notifsql, array()); 
				$mform->hideIf('moduleid', 'notificationid', 'in', $notiftypeids);
		   	} 
			
			$sql = "SELECT id FROM {local_notification_type} WHERE shortname IN ('course_reminder','course_completion_reminder','classroom_reminder','classroom_feedback_reminder','onlinetest_reminder','onlinetest_due','program_reminder','program_session_reminder','certification_reminder', 'assign_approve','assign_reject','assign_reject','certificate_uploaded','certificate_approved','certificate_declined')";
			$notiftypeids = $DB->get_fieldset_sql($sql, array()); 
			//$mform->hideIf('completiondays', 'notificationid', 'in', $notiftypeids);	

    		if(strtolower($notifyid->moduletype)!='request'){
	    		$mform->addElement('text', 'reminderdays', get_string('reminderdays', 'local_notifications'));
	            $mform->setType('reminderdays', PARAM_INT);
	            $notifications = $DB->get_records_sql_menu("SELECT id, id as nid FROM {local_notification_type} WHERE shortname NOT IN ('course_reminder','course_completion_reminder','classroom_reminder','onlinetest_reminder','onlinetest_due','program_reminder','program_session_reminder','certification_reminder', 'feedback_due')");
	    		// $mform->disabledIf('reminderdays', 'notificationid', 'in', $notifications);
	    		$mform->hideIf('reminderdays', 'notificationid', 'in', $notifications);
	    		$mform->hideIf('reminderdays', 'notificationid', 'eq', '');

		   	} 
			
			$sql = "SELECT id FROM {local_notification_type} WHERE shortname NOT IN ('course_reminder','course_completion_reminder')";
			$notiftypeids = $DB->get_fieldset_sql($sql, array()); 
		
            $mform->addElement('advcheckbox', 'frequencyflag', get_string('addfrequency', 'local_notifications'));
            $mform->setDefault('frequencyflag', 0);
            $mform->addHelpButton('frequency', 'frequencyflag', 'local_notifications');
            $mform->hideIf('frequencyflag', 'notificationid', 'in', $notiftypeids);
            $mform->hideIf('frequencyflag', 'notificationid', 'eq', NULL);
			
			//notification to be sent until below entered no.of days from user enrolled date if frequency is set
			$mform->addElement('text', 'frequencydays', get_string('frequencydays', 'local_notifications'));
			$mform->setType('frequencydays', PARAM_INT);
			$mform->addHelpButton('frequencydays','frequencydays','local_notifications');
			$mform->hideIf('frequencydays', 'notificationid', 'in', $notiftypeids);
			$mform->hideIf('frequencydays', 'frequencyflag', 'ne', 1);
			
			$mform->addElement('text', 'untildays', get_string('untildays', 'local_notifications'));
			$mform->setType('untildays', PARAM_INT);
			$mform->hideIf('untildays', 'notificationid', 'in', $notiftypeids);
			$mform->hideIf('untildays', 'frequencyflag', 'ne', 1);
			

			/*	$nonmodulenotifications = $DB->get_records_sql_menu("SELECT id, id as nid FROM {local_notification_type} WHERE parent_module IN (SELECT lnti.id FROM {local_notification_type} AS lnti WHERE shortname IN ('certification', 'request', 'challenge'))");
	    		$mform->hideIf('moduleid', 'notificationid', 'in', $nonmodulenotifications);
				$mform->hideIf('moduleid', 'notificationid', 'eq', NULL);
		   */

		    $certificates_exists = \core_component::get_plugin_directory('tool', 'certificate');
		   	if($certificates_exists){
		   	    $mform->addElement('advcheckbox', 'attach_certificate', get_string('attach_certificate', 'local_notifications'),get_string('send_cert_msg', 'local_notifications'),  array(), array(0, 1));
		   		//completion notification list
		   	    $completions = ['course_complete','classroom_complete','program_completion','learningplan_completion','onlinetest_completed'];
				list($insql, $inparams) = $DB->get_in_or_equal($completions);

				$sql = "SELECT id
						FROM {local_notification_type}
						WHERE shortname NOT $insql";

				$otherthan_completion_mails = $DB->get_fieldset_sql($sql, $inparams);
				$mform->hideIf('attach_certificate', 'notificationid', 'in', $otherthan_completion_mails);
				$mform->hideIf('attach_certificate', 'notificationid', 'eq', NULL);
		   	}
   
            $mform->addElement('static', 'string_identifiers', get_string('string_identifiers', 'local_notifications'),  $strings);
            $mform->addHelpButton('string_identifiers', 'strings', 'local_notifications');
            $mform->hideIf('string_identifiers', 'notificationid', 'eq', NULL);
            //default issue https://tracker.moodle.org/browse/MDL-66251

            $mform->addElement('editor', 'body', get_string('emp_body', 'local_notifications'), array(), array('autosave'=>false));
            $mform->setType('body', PARAM_RAW);
        // }// end of form status = 0 condition    
        // else if($form_status ==1){
        // 	$notifytypesql = "SELECT lnt.shortname FROM {local_notification_type} AS lnt
        // 		JOIN {local_notification_info} AS lni ON lni.notificationid=lnt.id WHERE lni.id=:id";
        // 	$notif_type = $DB->get_field_sql($notifytypesql, array('id'=>$id));
		// 	$strings = $lib->get_string_identifiers($notif_type);
        // 	$mform->addElement('static', 'string_identifiers', get_string('string_identifiers', 'local_notifications'),  $strings);
        //     $mform->addElement('editor', 'adminbody', get_string('admin_body', 'local_notifications'), array(), array('autosave'=>false));
        //     $mform->setType('adminbody', PARAM_RAW);
        // }// end of form status = 1 condition
        
        $mform->addElement('hidden', 'id', $id, array('id' => 'notificationid'));
        $mform->setType('id', PARAM_INT);

        //$this->add_action_buttons(true);
        $mform->disable_form_change_checker();
    }
    public function get_datamoduleids_labels($moduletype, $costcenterid, $notiftype){
    	global $DB;
		$params['costcenterpath'] = '%'.$costcenterid.'%';
    	switch(strtolower($moduletype)){
			case 'course':	
				$remindernotiftype = array('course_reminder','course_completion_reminder');				
				$sql = "SELECT c.id, c.fullname as name FROM {course} c                           
				WHERE  c.visible = 1 AND  concat('/',c.open_path,'/') LIKE :costcenterpath AND c.open_coursetype = 0 ";                    
				/* if(in_array($notiftype,$remindernotiftype)){
					$sql .=  " AND (c.open_coursecompletiondays != NULL OR c.open_coursecompletiondays != 0)";
				}	 */			
				$datamoduleids = $DB->get_records_sql_menu($sql,$params);
                $datamodule_label=get_string('form_courses', 'local_notifications');
				
			break;	
			case 'classroom':	
				$sql = "SELECT c.id, c.name FROM {local_classroom} c                           
				WHERE  concat('/',c.open_path,'/') LIKE :costcenterpath AND c.status NOT IN (0,2) ";
				$datamoduleids = $DB->get_records_sql_menu($sql,$params);
                $datamodule_label=get_string('form_classrooms', 'local_notifications');
			
			break;
			case 'onlinetest':	
				$sql = "SELECT c.id, c.name FROM {local_onlinetests} c                           
				WHERE  c.visible = 1 AND concat('/',c.open_path,'/') LIKE :costcenterpath  ";                    
				$datamoduleids = $DB->get_records_sql_menu($sql,$params);
				$datamodule_label=get_string('form_onlinetests', 'local_notifications');
			
			break;
			case 'feedback':	
				$sql = "SELECT c.id, c.name FROM {local_evaluations} c                           
				WHERE  c.visible = 1 AND concat('/',c.open_path,'/') LIKE :costcenterpath  AND deleted != 1 ";
				$datamoduleids = $DB->get_records_sql_menu($sql,$params);
			    $datamodule_label=get_string('form_feedbacks', 'local_notifications');
			
			break;	
			case 'program':	
				$sql = "SELECT c.id, c.name FROM {local_program} c                           
				WHERE  c.visible = 1 AND concat('/',c.open_path,'/') LIKE :costcenterpath  ";
				$datamoduleids = $DB->get_records_sql_menu($sql,$params);
				$datamodule_label=get_string('form_programs', 'local_notifications');
				
			break;
			case 'learningplan':	
				$sql = "SELECT c.id, c.name FROM {local_learningplan} c                           
				WHERE  c.visible = 1 AND concat('/',c.open_path,'/') LIKE :costcenterpath  ";                    
				$datamoduleids = $DB->get_records_sql_menu($sql,$params);
				$datamodule_label=get_string('form_learning_paths', 'local_notifications');
			break;
			case 'certification':	
				$sql = "SELECT c.id, c.name FROM {local_certification} c                           
				WHERE  c.visible = 1 AND concat('/',c.open_path,'/') LIKE :costcenterpath  ";                
				$datamoduleids = $DB->get_records_sql_menu($sql,$params);
				$datamodule_label=get_string('form_certifications', 'local_notifications');
			
			break;
			case 'onlineexam':	
				$sql = "SELECT c.id, c.fullname as name FROM {course} c                           
								WHERE  c.visible = 1 AND concat('/',c.open_path,'/') LIKE :costcenterpath AND c.open_coursetype = 1  AND c.open_module = 'online_exams' ";                   
				$datamoduleids = $DB->get_records_sql_menu($sql,$params);
	
				$datamodule_label=get_string('onlineexam', 'local_notifications');
				break;
		}
		return array('datamoduleids' => $datamoduleids, 'datamodule_label' => $datamodule_label);
    }
    public function validation($data, $files) {
        global $DB;
        $errors = parent::validation($data, $files);
		$mform = $this->_form;
        $moduleid = $this->_customdata['moduleid'];
		
        $notificationid = $data['notificationid'];
        $costcenterid = $data['costcenterid'];
        $id = $data['id'];
     
		if($notificationid&&$costcenterid){
			$sql="SELECT id FROM {local_notification_info} WHERE costcenterid = {$costcenterid} AND notificationid = {$notificationid}";
			if($id>0){
				$sql.=" AND id <> {$id} ";
			}
			
			if(is_array($moduleid)&&!empty($moduleid)){
				// $moduleid=implode(',',$moduleid);
				// $sql.=" AND moduleid in ($moduleid)";
				$concatqry = array();
				foreach($moduleid AS $module){
					if(empty($module)){
						continue;
					}
					$param = '%,'.$module.',%';
					$concatqry[] = " concat(',',moduleid,',') LIKE  '{$param}' " ;
				}
				if(!empty($concatqry)){
					$concatsql = implode(' OR ', $concatqry);
					$sql .= " AND ( {$concatsql} ) ";
				}
			}else{
				$sql.=" AND moduleid IS NULL";
			}
		
            $record = $DB->get_records_sql($sql);
			
            if (!empty($record)&&((count($record)>0&$id<=0)||(count($record)>0&$id>0))) {

                $notif_type = $DB->get_field('local_notification_type', 'shortname', array('id'=>$notificationid));
                $notif_type_find=explode('_',$notif_type);
				if($notif_type_find[1]!='reminder' && $notif_type != 'program_session_reminder' && !in_array($notif_type ,array('course_completion_reminder'))){
    				
                   $errors['notificationid'] = get_string('codeexists', 'local_notifications',$notif_type);
                }else{
                	$sql .= " AND reminderdays={$data['reminderdays']} ";
                	$record = $DB->get_record_sql($sql);
                	if(!empty($record)){
                		$errors['notificationid'] = get_string('codeexists', 'local_notifications',$notif_type);
                	}
                }
            }
		}
         //}
		$course_reminder_id = $DB->get_field('local_notification_type', 'id', array('shortname' => 'course_reminder'));
		if($data['notificationid'] == $course_reminder_id){
		
			if(empty($data['moduleid']) && $data['moduleid']== ""){
				$errors['moduleid'] = get_string('required');	
			}

		}
        
		$notifications = $DB->get_records_sql_menu("SELECT id, id as nid FROM {local_notification_type} WHERE shortname IN ('course_reminder','classroom_reminder','onlinetest_reminder','program_reminder','program_session_reminder','certification_reminder', 'feedback_due')");
		
		if (in_array($data['notificationid'], $notifications))  {
			if(empty($data['reminderdays'])){
				$errors['reminderdays'] = get_string('reminderdaysrequired', 'local_notifications'); 
			} elseif ($data['reminderdays'] < 0) {
				$errors['reminderdays'] = get_string('reminderdaysnumeric', 'local_notifications');
			}
		}
			
        
        return $errors;
    }
    
}
