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
 * @subpackage local_learning
 */
define('learningplan', 3);

function local_learningplan_output_fragment_new_learningplan($args){
	global $CFG,$DB, $PAGE;
	$args = (object) $args;
    $contextid = $args->context;
    $o = '';
    $formdata = [];
    if (!empty($args->jsonformdata)) {
        $serialiseddata = json_decode($args->jsonformdata);
        if(is_object($serialiseddata)){
            $serialiseddata = serialize($serialiseddata);
        }
        parse_str($serialiseddata, $formdata);
    }
    $editoroptions = [
        'maxfiles' => EDITOR_UNLIMITED_FILES,
        'maxbytes' => $course->maxbytes,
        'trust' => false,
        'context' => $contextid,
        'noclean' => true,
        'subdirs' => false,
    ];
    
	if($args->id>0||$args->planid>0){
		if(isset($args->id) && $args->id > 0){
			$data = $DB->get_record('local_learningplan', array('id'=>$args->id));
		}else if(isset($args->planid)&&$args->planid>0){
			$data = $DB->get_record('local_learningplan', array('id'=>$args->planid));
		}
		if($data){
			$description = $data->description;
			unset($data->description);
			$data->description['text'] = $description;
			$data->open_band = (!empty($data->open_band)) ? array_diff(explode(',',$data->open_band), array('')) :NULL;
			$data->open_hrmsrole = (!empty($data->open_hrmsrole)) ? array_diff(explode(',',$data->open_hrmsrole), array('')) :array(NULL=>NULL);
			$data->open_branch =(!empty($data->open_branch)) ? array_diff(explode(',',$data->open_branch), array('')) :NULL;
			$data->open_group =(!empty($data->open_group)) ? array_diff(explode(',',$data->open_group), array('')) :array(NULL=>NULL);
			$data->open_designation = (!empty($data->open_designation)) ? array_diff(explode(',',$data->open_designation), array('')) :array(NULL=>NULL);
            $data->open_location = (!empty($data->open_location)) ? array_diff(explode(',',$data->open_location), array('')) :array(NULL=>NULL);
			$data->department =(!empty($data->department)) ? (count(explode(',',$data->department))>1)? array_diff(explode(',',$data->department), array('')):$data->department :NULL;
            $customdata = array('editoroptions' => $editoroptions, 'id'=>$data->id, 'form_status' => $args->form_status, 'open_path' => $data->open_path);
            local_costcenter_set_costcenter_path($customdata);
            local_users_set_userprofile_datafields($customdata,$data);
			$mform = new local_learningplan\forms\learningplan(null, $customdata, 'post', '', null, true, $formdata);
           
            if(!empty($data->certificateid)){
                $data->map_certificate = 1;
            }else{
                $data->map_certificate = null;
            }
            $description = $data->description;

            $costcenterid = explode('/',$data->open_path)[1];
            $sql = "SELECT id FROM {local_custom_fields} WHERE costcenterid =".$costcenterid ." AND parentid = 0";
            $parentid = $DB->get_records_sql($sql);
            if($parentid){
                $parentcat = [];
                foreach($parentid as $categoryid){
                    $parentcat[] = $categoryid->id;
                    $childcategories = $DB->get_field('local_category_mapped', 'category', array ('parentid' => $categoryid->id, 'moduletype' => 'learningplan', 'moduleid' => $data->id));
                    $data->{'category_'.$categoryid->id} = $childcategories ? $childcategories : 0;
                }
                $data->parentid = implode(',', $parentcat);
            }
			$mform->set_data($data);
		}
    }
    else{
        $customdata = array('editoroptions' => $editoroptions, 'form_status' => $args->form_status);
        local_costcenter_set_costcenter_path($customdata);
        $mform = new local_learningplan\forms\learningplan(null, $customdata, 'post', '', null, true, $formdata);
    }

    if (!empty($args->jsonformdata) && strlen($args->jsonformdata) >2) {
        // If we were passed non-empty form data we want the mform to call validation functions and show errors.
        $mform->is_validated();
    }

    $formheaders = array_keys($mform->formstatus);
    $nextform = array_key_exists($args->form_status, $formheaders);
    if ($nextform === false) {
        return false;
    }
    $renderer = $PAGE->get_renderer('local_users');
	
	ob_start();
	$formstatus = array();
    foreach (array_values($mform->formstatus) as $k => $mformstatus) {
        $activeclass = $k == $args->form_status ? 'active' : '';
        $formstatus[] = array('name' => $mformstatus, 'activeclass' => $activeclass, 'form-status' => $k);
    }
    $formstatusview = new \local_users\output\form_status($formstatus);
    $o .= $renderer->render($formstatusview);
    $mform->display();
    $o .= ob_get_contents();
    ob_end_clean();
    return $o;
}
function local_learningplan_output_fragment_lpcourse_enrol($args){
	global $CFG,$DB, $PAGE;
	$args = (object) $args;
    $contextid = $args->contextid;
    $planid = $args->planid;
    $o = '';
    $formdata = [];
    if (!empty($args->jsonformdata)) {
        
        $serialiseddata = json_decode($args->jsonformdata);
        if(is_object($serialiseddata)){
            $serialiseddata = serialize($serialiseddata);
        }
        parse_str($serialiseddata, $formdata);
    }
    $mform = new local_learningplan\forms\courseenrolform(null,array('planid' => $planid, 'condition' => 'manage'));
    if (!empty($args->jsonformdata) && strlen($args->jsonformdata) >2) {
        // If we were passed non-empty form data we want the mform to call validation functions and show errors.
        $mform->is_validated();
    }
    ob_start();
    $mform->display();
    $o .= ob_get_contents();
    ob_end_clean();
    return $o;
}
function local_learningplan_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options=array()) {
    // Check the contextlevel is as expected - if your plugin is a block, this becomes CONTEXT_BLOCK, etc.

    // Make sure the filearea is one of those used by the plugin.
    if ($filearea !== 'summaryfile') {
        return false;
    }

    $itemid = array_shift($args);

    $filename = array_pop($args);
    if (!$args) {
        $filepath = '/';
    } else {
        $filepath = '/'.implode('/', $args).'/';
    }

    // Retrieve the file from the Files API.
    $fs = get_file_storage();
    $file = $fs->get_file($context->id, 'local_learningplan', $filearea, $itemid, $filepath, $filename);
    if (!$file) {
        return false;
    }
    send_file($file, $filename, 0, $forcedownload, $options);
}
function learningplan_filter($mform){
    global $DB,$USER;
    $categorycontext = (new \local_learningplan\lib\accesslib())::get_module_context();
    $costcenterpathconcatsql = (new \local_learningplan\lib\accesslib())::get_costcenter_path_field_concatsql($columnname='open_path');
    $learningplan_params = array();
    $sql = " SELECT id, name FROM {local_learningplan} WHERE 1 = 1 ";
    if (is_siteadmin()) {
        $sql .= "";
    } else  {
        $sql .= $costcenterpathconcatsql;
    }

    if ((has_capability('local/request:approverecord', $categorycontext) || is_siteadmin())) {
        $learningplanlist = $DB->get_records_sql_menu($sql, $learningplan_params);
    }
    $select = $mform->addElement('autocomplete', 'learningplan', get_string('leaningpathsearch', 'local_learningplan'), $learningplanlist, array('placeholder' => get_string('learning_path_name', 'local_learningplan')));
    $mform->setType('learningplan', PARAM_RAW);
    $select->setMultiple(true);
}
function get_user_learningplan($userid) {
    global $DB, $CFG;
    $query = "SELECT lp.* 
                FROM {local_learningplan_user} AS ulp
                JOIN {local_learningplan} AS lp ON lp.id = ulp.planid
                WHERE lp.visible = ? AND ulp.userid = ?";
    $params = [1, $userid];
    $lps = $DB->get_records_sql($query, $params);
    return $lps;
}
/*
* Author Rizwana
* Displays a node in left side menu
* @return  [type] string  link for the leftmenu
*/
function local_learningplan_leftmenunode(){    
    $categorycontext = (new \local_learningplan\lib\accesslib())::get_module_context();
    $learningplannode = '';
    if(has_capability('local/learningplan:manage', $categorycontext) || is_siteadmin()) {
        $learningplannode .= html_writer::start_tag('li', array('id'=> 'id_leftmenu_learningplans', 'class'=>'pull-left user_nav_div learningplans'));
            $learningplan_url = new moodle_url('/local/learningplan/index.php');
            $learningplan = html_writer::link($learningplan_url, '<i class="fa fa-map" aria-hidden="true" aria-label=""></i><span class="user_navigation_link_text">'.get_string('managelep','local_learningplan').'</span>',array('class'=>'user_navigation_link'));
            $learningplannode .= $learningplan;
        $learningplannode .= html_writer::end_tag('li');
    }

    return array('9' => $learningplannode);
}
function local_learningplan_quicklink_node(){
    global $CFG, $PAGE, $OUTPUT;
    $categorycontext = (new \local_learningplan\lib\accesslib())::get_module_context();
    $content = '';
    if (is_siteadmin() || has_capability('local/learningplan:view',$categorycontext)){
            //local learningplans content
        $PAGE->requires->js_call_amd('local_learningplan/lpcreate', 'load', array());
        $learningplan = array();
        $learningplan['node_header_string'] = get_string('manage_br_learningplan', 'local_learningplan');
        $learningplan['pluginname'] = 'learningplans';
        $learningplan['plugin_icon_class'] = 'fa fa-map';
        if(is_siteadmin() || (has_capability('local/learningplan:manage', $categorycontext) && has_capability('local/learningplan:create', $categorycontext))){
            $learningplan['create'] = TRUE;
            $learningplan['create_element'] = html_writer::link('javascript:void(0)', get_string('create'), array('class' => 'quick_nav_link goto_local_learningplan', 'title' => get_string('create_learningplan', 'local_learningplan'), 'data-action' => 'createlpmodal', 'onclick' => '(function(e){ require("local_learningplan/lpcreate").init({selector:"createlpmodal", contextid:'.$categorycontext->id.', planid:0,form_status:0}) })(event)'));
        }
       
        $learningplan['viewlink_url'] = $CFG->wwwroot.'/local/learningplan/index.php';
        $learningplan['view'] = TRUE;
        $learningplan['viewlink_title'] = get_string("view_learningplan", "local_learningplan");        
        $learningplan['space_count'] = 'one';
        $learningplan['view_type'] = $PAGE->theme->settings->quicknavigationview;
        $content = $OUTPUT->render_from_template('block_quick_navigation/quicklink_node', $learningplan);
    }
    
    return array('5' => $content);
}

/*
* Author Sarath
* return count of learningplans under selected costcenter
* @return  [type] int count of learningplans
*/
function costcenterwise_learningplan_count($costcenter,$department = false, $subdepartment = false, $l4department=false, $l5department=false){
    global $USER, $DB,$CFG;
        $params = array();
        $params['costcenterpath'] = '%/'.$costcenter.'/%';

        $countlpql = "SELECT count(lp.id) FROM {local_learningplan} lp WHERE concat('/',lp.open_path,'/') LIKE :costcenterpath ";
        if($department){
            $countlpql .= "  AND concat('/',lp.open_path,'/') LIKE :departmentpath  ";
            $params['departmentpath'] = '%/'.$department.'/%';
        }
        if ($subdepartment) {
        $countlpql .= " AND concat('/',lp.open_path,'/') LIKE :subdepartmentpath ";
        $params['subdepartmentpath'] = '%/'.$subdepartment.'/%';
        }
        if ($l4department) {
            $countlpql .= " AND concat('/',lp.open_path,'/') LIKE :l4departmentpath ";
            $params['l4departmentpath'] = '%/'.$l4department.'/%';
        }
        if ($l5department) {
            $countlpql .= " AND concat('/',lp.open_path,'/') LIKE :l5departmentpath ";
            $params['l5departmentpath'] = '%/'.$l5department.'/%';
        }
        $activesql = " AND visible = 1 ";
        $inactivesql = " AND visible = 0 ";
        $countlps = $DB->count_records_sql($countlpql, $params);
        $activelps = $DB->count_records_sql($countlpql.$activesql, $params);
        $inactivelps = $DB->count_records_sql($countlpql.$inactivesql, $params);
        if($countlps >= 0){
            if($costcenter){
                $viewlplink_url = $CFG->wwwroot.'/local/learningplan/index.php?costcenterid='.$costcenter; 
            }
            if($department){
                $viewlplink_url = $CFG->wwwroot.'/local/learningplan/index.php?costcenterid='.$costcenter.'&departmentid='.$department;
            }
            if($subdepartment){
                $viewlplink_url = $CFG->wwwroot.'/local/learningplan/index.php?costcenterid='.$costcenter.'&departmentid='.$department.'&subdepartmentid='.$subdepartment;
            }
            if($l4department){
                $viewlplink_url = $CFG->wwwroot.'/local/learningplan/index.php?costcenterid='.$costcenter.'&departmentid='.$department.'&subdepartmentid='.$subdepartment.'&l4department='.$l4department;
            }
            if($l5department){
                $viewlplink_url = $CFG->wwwroot.'/local/learningplan/index.php?costcenterid='.$costcenter.'&departmentid='.$department.'&subdepartmentid='.$subdepartment.'&l4department='.$l4department.'&l5department='.$l5department;
            }
           
        }
        if($activelps >= 0){
            if($costcenter){
                $count_lpactivelink_url = $CFG->wwwroot.'/local/learningplan/index.php?status1=active&costcenterid='.$costcenter; 
            }
            if($department){
                $count_lpactivelink_url = $CFG->wwwroot.'/local/learningplan/index.php?status1=active&costcenterid='.$costcenter.'&departmentid='.$department;
            }
            if($subdepartment){
                $count_lpactivelink_url = $CFG->wwwroot.'/local/learningplan/index.php?status1=active&costcenterid='.$costcenter.'&departmentid='.$department.'&subdepartmentid='.$subdepartment;
            }
            if($l4department){
                $count_lpactivelink_url = $CFG->wwwroot.'/local/learningplan/index.php?status1=active&costcenterid='.$costcenter.'&departmentid='.$department.'&subdepartmentid='.$subdepartment.'&l4department='.$l4department;
            }
            if($l5department){
                $count_lpactivelink_url = $CFG->wwwroot.'/local/learningplan/index.php?status1=active&costcenterid='.$costcenter.'&departmentid='.$department.'&subdepartmentid='.$subdepartment.'&l4department='.$l4department.'&l5department='.$l5department;
            }
        }
        if($inactivelps >= 0){
            if($costcenter){
                $count_lpinactivelink_url = $CFG->wwwroot.'/local/learningplan/index.php?status1=inactive&costcenterid='.$costcenter; 
            }
            if($department){
                $count_lpinactivelink_url = $CFG->wwwroot.'/local/learningplan/index.php?status1=inactive&costcenterid='.$costcenter.'&departmentid='.$department;
            }
            if($subdepartment){
                $count_lpinactivelink_url = $CFG->wwwroot.'/local/learningplan/index.php?status1=inactive&costcenterid='.$costcenter.'&departmentid='.$department.'&subdepartmentid='.$subdepartment;
            }
            if($l4department){
                $count_lpinactivelink_url = $CFG->wwwroot.'/local/learningplan/index.php?status1=inactive&costcenterid='.$costcenter.'&departmentid='.$department.'&subdepartmentid='.$subdepartment.'&l4department='.$l4department;
            }
            if($l5department){
                $count_lpinactivelink_url = $CFG->wwwroot.'/local/learningplan/index.php?status1=inactive&costcenterid='.$costcenter.'&departmentid='.$department.'&subdepartmentid='.$subdepartment.'&l4department='.$l4department.'&l5department='.$l5department;
            }
        }

    return array('lp_plugin_exist' => true,'alllearningplans' => $countlps,'activelearningplanscount' => $activelps,'inactivelearningplanscount' => $inactivelps,'viewlplink_url'=>$viewlplink_url,'count_lpactivelink_url' => $count_lpactivelink_url,'count_lpinactivelink_url' => $count_lpinactivelink_url);
}

/*
* Author sarath
* @return true for reports under category
*/
function learnerscript_learningplan_list(){
    return 'Learningpath';
}
function local_learningplan_request_dependent_query($aliasname){
    $returnquery = " WHEN ({$aliasname}.compname LIKE 'learningplan') THEN (SELECT name from {local_learningplan} WHERE id = {$aliasname}.componentid) ";
    return $returnquery;
}
/**
 * Returns learningplans tagged with a specified tag.
 *
 * @param local_tags_tag $tag
 * @param bool $exclusivemode if set to true it means that no other entities tagged with this tag
 *             are displayed on the page and the per-page limit may be bigger
 * @param int $fromctx context id where the link was displayed, may be used by callbacks
 *            to display items in the same context first
 * @param int $ctx context id where to search for records
 * @param bool $rec search in subcontexts as well
 * @param int $page 0-based number of page being displayed
 * @return \local_tags\output\tagindex
 */
function local_learningplan_get_tagged_learningplans($tag, $exclusivemode = false, $fromctx = 0, $ctx = 0, $rec = 1, $page = 0, $sort = '') {
    global $CFG, $PAGE;
    // prepare for display of tags related to tests
    $perpage = $exclusivemode ? 10 : 5;
    $displayoptions = array(
        'limit' => $perpage,
        'offset' => $page * $perpage,
        'viewmoreurl' => null,
    );
    $renderer = $PAGE->get_renderer('local_learningplan');
    $totalcount = $renderer->tagged_learningplans($tag->id, $exclusivemode, $ctx, $rec, $displayoptions, $count = 1, $sort);
    $content = $renderer->tagged_learningplans($tag->id, $exclusivemode, $ctx, $rec, $displayoptions, 0, $sort);
    $totalpages = ceil($totalcount / $perpage);
    if ($totalcount)
    return new local_tags\output\tagindex($tag, 'local_learningplan', 'learningplan', $content,
            $exclusivemode, $fromctx, $ctx, $rec, $page, $totalpages);
    else
    return '';
}

/**
* todo sql query departmentwise
* @param  $categorycontext object
* @return array
**/
function orgsql($categorycontext){
    global $DB, $USER;
    $sql = '';
    $params =array();
    if (has_capability('local/learningplan:manage', $categorycontext)){       
        $categorycontext = (new \local_learningplan\lib\accesslib())::get_module_context();
        $costcenterpathconcatsql = (new \local_learningplan\lib\accesslib())::get_costcenter_path_field_concatsql($columnname='open_path');
         $sql = $costcenterpathconcatsql;
     }
    
    return compact('sql', 'params'); 
}

/**
* todo sql query departmentwise
* @param  $categorycontext object 
* @return array
**/

function get_learningplan_details($lpid) {
    global $USER, $DB, $PAGE;
    $categorycontext = (new \local_learningplan\lib\accesslib())::get_module_context($lpid);
    $PAGE->requires->js_call_amd('local_learningplan/learningplan','load', array());
    $PAGE->requires->js_call_amd('local_request/requestconfirm','load', array());
    $details = array();    
    $joinsql = '';
    if(is_siteadmin() OR has_capability('local/learningplan:manage',$categorycontext)) {

        $selectsql = "select c.*  ";
        $fromsql = " from  {local_learningplan} c ";
        if ($DB->get_manager()->table_exists('local_rating')) {
            $selectsql .= " , AVG(rating) as avg ";
            $joinsql .= " LEFT JOIN {local_rating} as r ON r.moduleid = c.id AND r.ratearea = 'local_learningplan' ";
        }
        $wheresql = " where c.id = ? ";

        $adminrecord = $DB->get_record_sql($selectsql.$fromsql.$joinsql.$wheresql, [$lpid]);
        $details['manage'] = 1;
        $completedcount = $DB->count_records_sql("select count(cu.id) from {local_learningplan_user} cu, {user} u where u.id = cu.userid AND u.deleted = 0 AND u.suspended = 0 AND cu.planid=? AND cu.status=?", array($lpid, 1));
        $enrolledcount = $DB->count_records_sql("select count(cu.id) from {local_learningplan_user} cu, {user} u where u.id = cu.userid AND u.deleted = 0 AND u.suspended = 0 AND cu.planid=? ", array($lpid));
        $sessioncount = $DB->count_records_sql("select count(cu.id) from {local_learningplan_courses} cu, {local_learningplan} c where c.id = cu.planid AND cu.planid=? ", array($lpid));
        $details['completed'] = $completedcount;
        $details['enrolled'] = $enrolledcount;
        $details['noofsessions'] = $sessioncount;
    } else {
        $selectsql = "select cu.*, c.id as cid, c.startdate, c.enddate ";

        $fromsql = "from {local_learningplan_user} cu 
        JOIN {local_learningplan} c ON c.id = cu.planid ";
        if ($DB->get_manager()->table_exists('local_rating')) {
            $selectsql .= " , AVG(rating) as avg ";
            $joinsql .= " LEFT JOIN {local_rating} as r ON r.moduleid = c.id AND r.ratearea = 'local_learningplan' ";
        }
        $wheresql = " where 1 = 1 AND cu.userid = ? AND c.id = ? ";

        $record = $DB->get_record_sql($selectsql.$fromsql.$joinsql.$wheresql, [$USER->id, $lpid], IGNORE_MULTIPLE);
        
        $sql = "SELECT count(cu.id) 
                FROM {local_learningplan_courses} cu
                JOIN {course} crse ON cu.courseid = crse.id
                JOIN {local_learningplan} c ON cu.planid = c.id
                WHERE cu.planid = ? ";
        $sessioncount = $DB->count_records_sql($sql, array($lpid));
        $details['manage'] = 0;
        $details['status'] = ($record->status == 1) ? get_string('learningplancompleted', 'local_learningplan'):get_string('learningplanpending', 'local_learningplan');

        $classsql = "SELECT c.* 
                    FROM {local_learningplan} c 
                    WHERE c.id = ?";
        $lpinfo = $DB->get_record_sql($classsql, [$lpid]);
        
        if (!empty($record)) {
            if ($lpinfo->approvalreqd == 0) {
                $enrollmentbtn = '<a href="javascript:void(0);" class="cat_btn" alt = ' . get_string('enroll','local_learningplan'). ' title = ' .get_string('enroll','local_classroom'). ' onclick="(function(e){ require(\'local_learningplan/courseenrol\').enrolUser({ planid: '.$lpinfo->id.', userid:'.$USER->id.'}) })(event)" ><button class="cat_btn viewmore_btn"><i class="fa fa-pencil-square-o" aria-hidden="true"></i>'.get_string('enroll','local_classroom').'</button></a>';
            } else {
                $enrollmentbtn = '<a href="javascript:void(0);" class="cat_btn" alt = ' . get_string('requestforenroll','local_classroom'). ' title = ' .get_string('enroll','local_classroom'). ' onclick="(function(e){ require(\'local_request/requestconfirm\').init({action:\'add\', componentid: '.$lpinfo->id.', component:\'learningplan\',componentname:\''.$lpinfo->name.'\'}) })(event)" ><button class="cat_btn viewmore_btn"><i class="fa fa-pencil-square-o" aria-hidden="true"></i>'.get_string('requestforenroll','local_classroom').'</button></a>';
            }
        } else {
            $enrollmentbtn ='-';
        }
        $details['noofsessions'] = $sessioncount;
        $details['enrolled'] = ($record->timecreated) ? \local_costcenter\lib::get_userdate("d/m/Y H:i", $record->timecreated): $enrollmentbtn;
        $details['completed'] = ($record->completiondate) ? \local_costcenter\lib::get_userdate("d/m/Y H:i", $record->completiondate): '-';
    }
    return $details;
}
function check_learningplanenrol_pluginstatus($value){
 global $DB ,$OUTPUT ,$CFG;
$enabled_plugins = $DB->get_field('config', 'value', array('name' => 'enrol_plugins_enabled'));
$enabled_plugins =  explode(',',$enabled_plugins);
$enabled_plugins = in_array('learningplan',$enabled_plugins);

if(!$enabled_plugins){

    if(is_siteadmin()){
        $url = $CFG->wwwroot.'/admin/settings.php?section=manageenrols';
        $enable = get_string('enableplugin','local_learningplan',$url);
        echo $OUTPUT->notification($enable,'notifyerror');
    }
    else{
        $enable = get_string('manageplugincapability','local_learningplan');
        echo $OUTPUT->notification($enable,'notifyerror');
     }
   }    
}
function local_learningplan_search_page_js(){
    global $PAGE;
    $PAGE->requires->js_call_amd('local_learningplan/courseenrol','load');
}
function local_learningplan_search_page_filter_element(&$filterelements){
    global $CFG;
    if(file_exists($CFG->dirroot.'/local/search/lib.php')){
        require_once($CFG->dirroot.'/local/search/lib.php');
        $filterelements['learningpath'] = ['code' => 'learningplan', 'name' => 'Learning Paths', 'tagitemshortname' => 'learningplan', 'count' => local_search_get_coursecount_for_modules([['type' => 'moduletype', 'values' => ['learningplan']]])];
    }
}
function local_learningplan_enabled_search(){
    return ['pluginname' => 'local_learningpath', 'templatename' => 'local_learningplan/searchpagecontent', 'type' => learningplan];
}
function  local_learningplan_applicable_filters_for_search_page(&$filterapplicable){
    $filterapplicable[learningplan] = [ 'status', 'categories'];
}



/**
    * function get_listof_learningpalns
    * @todo all learningpalns based  on costcenter / department
    * @param object $stable limit values
    * @param object $filterdata filterdata
    * @return  array learningpalns
*/

function get_listof_learningplans($stable, $filterdata,$options=array()) {

    global $DB,$CFG,$PAGE,$OUTPUT;
    require_once($CFG->dirroot.'/local/courses/lib.php');
    require_once($CFG->dirroot.'/local/custom_category/lib.php');
    if(is_string($options)){
        $options=json_decode($options);
    } 

    $open_path=(new \local_learningplan\lib\accesslib())::get_costcenter_path_field_concatsql($columnname='l.open_path');
    
    $countsql  = "SELECT count(l.id) FROM {local_learningplan} AS l ";
    $sql = "SELECT l.* FROM {local_learningplan} AS l ";       
    
    $fromsql = " WHERE 1 = 1 $open_path ";
   
    $params=array();
    if(isset($filterdata->search_query) && trim($filterdata->search_query) != ''){
        $fromsql .= " AND l.name LIKE :search";
        $params['search'] = '%'.trim($filterdata->search_query).'%';
    }

    $parentcatids = get_parent_categoryids();
    if(!empty($parentcatids)){
        $categoryquery = get_moduleid_of_mapped_category($filterdata,$parentcatids,'learningplan','l.id');
        $params = array_merge($params, $categoryquery['params']);
        $fromsql .= $categoryquery['sql'];
    }
    
    if(!empty($filterdata->learningplan)){
        $filterlearningplan = explode(',', $filterdata->learningplan);
        list($filterlplansql, $filterlpanparams) = $DB->get_in_or_equal($filterlearningplan, SQL_PARAMS_NAMED, 'learningplan', true, false);
        $params = array_merge($params, $filterlpanparams);
        $fromsql .= " AND l.id $filterlplansql";
    }
    if (!empty($filterdata->filteropen_costcenterid)) {

        $filteropen_costcenterid = explode(',', $filterdata->filteropen_costcenterid);
        $orgsql = [];
        foreach($filteropen_costcenterid AS $organisation){
            $orgsql[] = " concat('/',l.open_path,'/') LIKE :organisationparam_{$organisation}";
            $params["organisationparam_{$organisation}"] = '%/'.$organisation.'/%';
        }
        if(!empty($orgsql)){
            $fromsql .= " AND ( ".implode(' OR ', $orgsql)." ) ";
        }
    }
    if (!empty($filterdata->filteropen_department)) {
        $filteropen_department = explode(',', $filterdata->filteropen_department);

        $deptsql = [];
        foreach($filteropen_department AS $department){
            $deptsql[] = " concat('/',l.open_path,'/') LIKE :departmentparam_{$department}";
            $params["departmentparam_{$department}"] = '%/'.$department.'/%';
        }
        if(!empty($deptsql)){
            $fromsql .= " AND ( ".implode(' OR ', $deptsql)." ) ";
        }
    }
    if (!empty($filterdata->filteropen_subdepartment)) {
        $subdepartments = explode(',', $filterdata->filteropen_subdepartment);

        $subdeptsql = [];
        foreach($subdepartments AS $subdepartment){
            $subdeptsql[] = " concat('/',l.open_path,'/') LIKE :subdepartmentparam_{$subdepartment}";
            $params["subdepartmentparam_{$subdepartment}"] = '%/'.$subdepartment.'/%';
        }
        if(!empty($subdeptsql)){
            $fromsql .= " AND ( ".implode(' OR ', $subdeptsql)." ) ";
        }
    }
    if (!empty($filterdata->filteropen_level4department)) {
        $subsubdepartments = explode(',', $filterdata->filteropen_level4department);

        $subsubdeptsql = [];
        foreach($subsubdepartments AS $department4level){
            $subsubdeptsql[] = " concat('/',l.open_path,'/') LIKE :department4levelparam_{$department4level}";
            $params["department4levelparam_{$department4level}"] = '%/'.$department4level.'/%';
        }
        if(!empty($subsubdeptsql)){
            $fromsql .= " AND ( ".implode(' OR ', $subsubdeptsql)." ) ";
        }
    }  

    if(!empty($filterdata->status)){
        $status = explode(',',$filterdata->status);
        if(!(in_array('active',$status) && in_array('inactive',$status))){
            if(in_array('active' ,$status)){
                $fromsql .= " AND l.visible = 1 ";           
            }else if(in_array('inactive' ,$status)){
                $fromsql .= " AND l.visible = 0 ";
            }
        }
    }
    $fromsql .= " ORDER BY l.id DESC ";
    $totallearningplans = $DB->count_records_sql($countsql.$fromsql, $params);
    $learningplans = $DB->get_records_sql($sql.$fromsql, $params, $stable->start,$stable->length);
    
    $learningplan_contents = array();
    foreach ($learningplans as $learning_plan) {

        $capability1 = $capability2 = $capability3 = false;
        $actions = '';
        $departmentcount = isset($learning_plan->department) ? count(array_filter(explode(',', $learning_plan->department))) : 0;
       
        $plan_url = new \moodle_url('/local/learningplan/plan_view.php', array('id' => $learning_plan->id));
        $planview_url = new \moodle_url('/local/learningplan/lpathinfo.php', array('id' => $learning_plan->id));

        $totaluser_sql = "SELECT count(llu.userid) as enrolledcount FROM {local_learningplan_user} as llu 
                    JOIN {user} as u ON u.id=llu.userid 
                    WHERE llu.planid = :planid AND u.deleted = :deleted AND u.suspended = :suspended GROUP BY llu.planid ";
        $enrolledcount = $DB->get_record_sql($totaluser_sql, array('planid' => $learning_plan->id, 'deleted' => 0, 'suspended' => 0));
        /*Count of the requested users to LEP*/
        $completedcount = $DB->get_record_sql("SELECT count(id) as completedcount FROM {local_learningplan_user} WHERE completiondate IS NOT NULL
                                                    AND status = 1 AND planid = $learning_plan->id");
        if (empty($learning_plan->credits)) {
            $plan_credits = get_string('statusna');
        } else {
            $plan_credits = $learning_plan->credits;
        }
        if (empty($learning_plan->usercreated)) {
            $plan_usercreated = get_string('statusna');
        } else {
            $plan_usercreated = $learning_plan->usercreated;
            $user = $DB->get_record_sql("SELECT id, firstname, lastname, firstnamephonetic, lastnamephonetic, middlename, alternatename FROM {user} WHERE id = :plan_usercreated", array('plan_usercreated' => $plan_usercreated));
            $created_user = fullname($user);
        }

        if (!empty($learning_plan->location)) {
            $plan_location = $learning_plan->location;
        } else {
            $plan_location = get_string('statusna');
        }

        $categorycontext = (new \local_learningplan\lib\accesslib())::get_module_context();
        $action_icons = '';
        if (is_siteadmin() || (has_capability('local/learningplan:visible', $categorycontext) && has_capability('local/learningplan:manage', $categorycontext))) {
            $capability1 = true;
        }


        if (has_capability('local/learningplan:update', $categorycontext)) {
            $capability2 = true;
        }
        if ($departmentcount > 1 && !(is_siteadmin() || has_capability('local/learningplan:manage', $categorycontext))) {
            $capability2 = false;
        }

        if (has_capability('local/learningplan:delete', $categorycontext)) {
            $capability3 = true;
        }

        if ($departmentcount > 1 && !(is_siteadmin() || has_capability('local/learningplan:manage', $categorycontext))) {
            $capability3 = false;
        }
        $can_view = false;
        if (is_siteadmin() || has_capability('local/learningplan:view', $categorycontext)) {
            $can_view = true;
        }

        $planlib = new \local_learningplan\lib\lib();
        $lplanassignedcourses = $planlib->get_learningplan_assigned_courses($learning_plan->id);
        $pathcourses = '';
        if (count($lplanassignedcourses) >= 2) {
            $i = 1;
            $coursespath_context['pathcourses'] = array();
            foreach ($lplanassignedcourses as $assignedcourse) {
                $coursename = $assignedcourse->fullname;
                $coursespath_context['pathcourses'][] = array('coursename' => $coursename, 'coursename_string' => 'C' . $i);
                $i++;
                if ($i > 10) {
                    break;
                }
            }
            $renderer = $PAGE->get_renderer('local_learningplan');
            $pathcourses .= $renderer->render_from_template('local_learningplan/cousrespath', $coursespath_context);
           
        }

        $learningplan_content = array();
        $learning_plan_name = strlen($learning_plan->name) > 34 ? clean_text(substr($learning_plan->name, 0, 34)) . "..." : $learning_plan->name;
        $hide_show_icon = $learning_plan->visible ? $OUTPUT->image_url('i/hide') : $OUTPUT->image_url('i/show');
        $title_hide_show = $learning_plan->visible ? get_string('make_inactive', 'local_learningplan') : get_string('make_active', 'local_learningplan');
        $learning_plan_pathname = addslashes($learning_plan_name);
        
        $learningplan_content['plan_url'] = $plan_url;
        $learningplan_content['description'] = $learning_plan->description;
        $learningplan_content['enrolledcount'] = ($enrolledcount->enrolledcount) ? $enrolledcount->enrolledcount :0;
        $learningplan_content['completedcount'] = ($completedcount->completedcount) ? $completedcount->completedcount : 0;
        $learningplan_content['shortname'] = $learning_plan->shortname;
        $learningplan_content['learning_plan_name'] = \local_costcenter\lib::strip_tags_custom($learning_plan_name);
        $learningplan_content['learning_plan_pathname'] = \local_costcenter\lib::strip_tags_custom($learning_plan_pathname);
        $learningplan_content['capability1'] = $capability1;
        $learningplan_content['capability2'] = $capability2;
        $learningplan_content['capability3'] = $capability3;

        $learningplan_content['hide'] = $learning_plan->visible ? true : false;

        $learningplan_content['hide_show_icon_url'] = $hide_show_icon;
        $learningplan_content['title_hide_show'] = $title_hide_show;
        $learningplan_content['delete_icon_url'] = $OUTPUT->image_url('i/delete');

        $learningplan_content['edit_icon_url'] = $OUTPUT->image_url('i/edit');

        $learningplan_content['learning_planid'] = $learning_plan->id;
        //$learningplan_content['plan_type'] = $plan_type;
        $learningplan_content['plan_credits'] = isset($learning_plan->credits);
        $learningplan_content['created_user'] = $created_user;

        list($zero, $org, $ctr, $bu, $cu, $territory) = explode("/", $learning_plan->open_path);
        
        $plandpt = $DB->get_field('local_costcenter', 'fullname', array('id' => $ctr));

        $learningplan_content['visible'] = $learning_plan->visible;
        $learningplan_content['plandpt'] = $plandpt ? $plandpt : 'All';
        $learningplan_content['plan_department'] = (empty($plan_department) || $plan_department == '-1') ? 'All' : $plan_department;
        $learningplan_content['plan_shortname_string'] = $learning_plan->shortname ? $learning_plan->shortname : 'NA';
        $learningplan_content['plan_department_string'] = ( empty($plan_department_string) || $plan_department_string == '-1') ? 'All' : $plan_department_string;
        $learningplan_content['plan_subdepartment'] = empty($plan_subdepartment ) ? 'All' : $plan_subdepartment;
        $learningplan_content['plan_url'] = $plan_url;
        $learningplan_content['planview_url'] = $CFG->wwwroot . '/local/learningplan/lpathinfo.php?id=' . $learning_plan->id;       
        $learningplan_content['lpcoursespath'] = $pathcourses;
        $learningplan_content['lpcoursescount'] = count($lplanassignedcourses);
        $learningplan_content['can_view'] = $can_view;
        $learningplan_content['enroll_link'] = $CFG->wwwroot . '/local/learningplan/lpusers_enroll.php?lpid=' . $learning_plan->id;

        if($can_view){
            $view_url = $CFG->wwwroot . '/local/learningplan/plan_view.php?id=' . $learning_plan->id;
        }else{
            $view_url = $CFG->wwwroot . '/local/learningplan/lpathinfo.php?id=' . $learning_plan->id;
        }
        $learningplan_content['view_url'] = $view_url;
        $learningplan_content['lpimg'] = (new \local_learningplan\lib\lib())->get_learningplansummaryfile($learning_plan->id);

        $actions .=  '<a href="' . $CFG->wwwroot . '/local/learningplan/lpusers_enroll.php?lpid=' . $learning_plan->id . '" title = ' . get_string('le_enrol_users', 'local_learningplan') . '><i class="icon fa fa-user-plus fa-fw" aria-hidden="true"></i></a>';
        $learningplan_content['actions'] = '';//$actions;
        $learningplan_contents[] = $learningplan_content;
    }       
   
    $lpcontext = array(
        "haslp" => $learningplan_contents,
        "nolearningplans" => false,
        "totallearningplans" => $totallearningplans,
        "length" => count($learningplan_contents),
    );
    return $lpcontext;
}

