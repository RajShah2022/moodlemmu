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
 * @subpackage local_skillrepository
 */
require_once(dirname(__FILE__) . '/../../config.php');
global $CFG;
require_once($CFG->dirroot . '/lib/moodlelib.php');
/*
 *  @method Create Array Format
 *  @param string $table Database Table Name
 *  @param string $column Database Table Column Name for KEY
 *  @param string $value Database Table Column Name for VALUE
 *  @return array $array contains KEY AND VALUE
 */
function create_array($table, $key, $value) {
    global $DB;
    $data = $DB->get_records('local_skill_' . $table);
    $array[NULL] = '--SELECT--';
    foreach ($data as $d) {
        $array[$d->$key] = $d->$value;
    }
    return $array;
}

/*
 *  @method Database Table Columns List
 *  @param string $table Database Table Name
 *  @return array $columnnames contains KEY AND VALUE
 */
function getTableColumns($table){
    global $DB;

    $tables = $DB->get_tables();
    $currenttable = $tables[$table];

    $columns = $DB->get_columns($tables[$currenttable]);
        foreach ($columns as $column) {
            $columnnames[$column->name] = $column->name;
        }

    return $columnnames;
}


/*
 *  @method output fragment
 *  @param $args
 *  @return array $args contains KEY AND VALUE
 */
function local_skillrepository_output_fragment_new_skill_repository_form($args){
    global $CFG,$DB;
    $args = (object) $args;
    $context = $args->context;
    $repositoryid = $args->repositoryid;
    $o = '';
    $formdata = [];
    if (!empty($args->jsonformdata)) {
        $serialiseddata = json_decode($args->jsonformdata);
        if(is_object($serialiseddata)){
            $serialiseddata = serialize($serialiseddata);
        }
        parse_str($serialiseddata, $formdata);
    }
    if ($args->repositoryid > 0) {
        $heading = 'Update repository';
        $collapse = false;
        $data = $DB->get_record('local_skill', array('id'=>$repositoryid));
        $description=$data->description;
        $data->description=array();
        $data->description['text'] = $description;
    }
    $editoroptions = [
        'maxfiles' => EDITOR_UNLIMITED_FILES,
        'maxbytes' => $course->maxbytes,
        'trust' => false,
        'context' => $context,
        'noclean' => false,
        'subdirs' => false,
        'autosave' => false
    ];
    $customdata = array(
        'open_path' => $data->open_path,'id' => $args->repositoryid,'editoroptions' => $editoroptions);
        local_costcenter_set_costcenter_path($customdata);
    $group = file_prepare_standard_editor($group, 'description', $editoroptions, $context, 'group', 'description', null);

    $mform = new local_skillrepository\form\skill_repository_form(null, $customdata, 'post', '', null, true, $formdata);

    $mform->set_data($data);

    if (!empty($formdata) && strlen($args->jsonformdata) > 2) {
        // If we were passed non-empty form data we want the mform to call validation functions and show errors.
        $mform->is_validated();
    }

    ob_start();
    $mform->display();
    $o .= ob_get_contents();
    ob_end_clean();
    return $o;
}

function local_skillrepository_output_fragment_skill_category_form($args){
    global $CFG,$DB;
    $args = (object) $args;
    $context = $args->context;
    $categoryid = $args->categoryid;
    $o = '';
    $formdata = [];
    if (!empty($args->jsonformdata)) {
        $serialiseddata = json_decode($args->jsonformdata);
        if(is_object($serialiseddata)){
            $serialiseddata = serialize($serialiseddata);
        }
        parse_str($serialiseddata, $formdata);
    }
    if ($categoryid > 0) {
            $data = $DB->get_record('local_skill_categories', array('id'=>$categoryid));

            $description = $data->description;
            unset($data->description);
            $data->description['text'] = $description;
            
    }
    $customdata = array(
        'open_path' => $data->open_path,'id' => $args->categoryid);
        local_costcenter_set_costcenter_path($customdata);
    $mform = new local_skillrepository\form\skill_category_form(null, $customdata, 'post', '', null, true, $formdata);
    $mform->set_data($data);
        if (!empty($formdata) && strlen($args->jsonformdata) > 2) {
            // If we were passed non-empty form data we want the mform to call validation functions and show errors.
            $mform->is_validated();
        }

        ob_start();
        $mform->display();
        $o .= ob_get_contents();
        ob_end_clean();
        return $o;
}
/*
* Author Rizwana
* Displays a node in left side menu
* @return  [type] string  link for the leftmenu
*/
function local_skillrepository_leftmenunode(){
    global $CFG, $USER;

    $systemcontext =(new \local_skillrepository\lib\accesslib())::get_module_context();
    $skillreponode = '';
    if(has_capability('local/skillrepository:manage', $systemcontext) || is_siteadmin()) {
        $skillreponode .= html_writer::start_tag('li', array('id'=> 'id_leftmenu_skills', 'class'=>'pull-left user_nav_div skills'));
        $skills_url = new moodle_url('/local/skillrepository/competency_view.php');
        $skill_icon = '<i class="fa fa-hourglass-half" aria-hidden="true"></i>';
        $advance = get_config('local_skillrepository','advance');
        if($advance == 1)
        {
        $courses = html_writer::link($skills_url, $skill_icon.'<span class="user_navigation_link_text">'.get_string('manage_skills','local_skillrepository').'</span>',array('class'=>'user_navigation_link'));
        }
        else
        {
        $skills_url = new moodle_url('/local/skillrepository/index.php');
        $courses = html_writer::link($skills_url, $skill_icon.'<span class="user_navigation_link_text">'.get_string('manage_skills_level','local_skillrepository').'</span>',array('class'=>'user_navigation_link'));
        }
        $skillreponode .= $courses;
        $skillreponode .= html_writer::end_tag('li');
    }

    if(!is_siteadmin()) {

        $skillreponode .= html_writer::start_tag('li', array('id'=> 'id_leftmenu_skills', 'class'=>'pull-left user_nav_div skills'));
        $skills_url = new moodle_url('/local/skillrepository/user_competency.php?userid='.$USER->id);
        $skill_icon = '<i class="fa fa-hourglass-half" aria-hidden="true"></i>';

        $advance = get_config('local_skillrepository','advance');

        if($advance == 1 && $USER->open_positionid > 0)
        {
            $courses = html_writer::link($skills_url, $skill_icon.'<span class="user_navigation_link_text">'.get_string('mycompetency','local_skillrepository').'</span>',array('class'=>'user_navigation_link'));
        }

        $skillreponode .= $courses;
        $skillreponode .= html_writer::end_tag('li');
    }

    return array('18' => $skillreponode);
}

//Level related functions

function local_skillrepository_output_fragment_level_form($args){
    global $CFG,$DB;
    $args = (object) $args;
    $context = $args->context;
    $levelid = $args->levelid;
    $o = '';
    $formdata = [];
    if (!empty($args->jsonformdata)) {
        $serialiseddata = json_decode($args->jsonformdata);
        if(is_object($serialiseddata)){
            $serialiseddata = serialize($serialiseddata);
        }
        parse_str($serialiseddata, $formdata);
    }
    if ($levelid > 0) {
        $data = $DB->get_record('local_course_levels', array('id'=>$levelid));
    }
    $customdata = array(
        'open_path' => $data->open_path,'id' => $args->levelid);
        local_costcenter_set_costcenter_path($customdata);
$mform = new \local_skillrepository\form\levelsform(null, $customdata, 'post', '', null, true, $formdata);
$mform->set_data($data);
    if (!empty($formdata) && strlen($args->jsonformdata) > 2) {
        // If we were passed non-empty form data we want the mform to call validation functions and show errors.
        $mform->is_validated();
    }

    ob_start();
    $mform->display();
    $o .= ob_get_contents();
    ob_end_clean();
    return $o;
}

//////For display on index page//////////
function skill_details($tablelimits, $filtervalues){
    global $DB, $PAGE,$USER,$CFG,$OUTPUT;
    $systemcontext =(new \local_skillrepository\lib\accesslib())::get_module_context();
    $countsql = "SELECT count(sk.id) FROM {local_skill} AS sk WHERE 1=1 ";
    $selectsql = "SELECT sk.*, lc.fullname as organisationname
        FROM {local_skill} AS sk
        JOIN {local_costcenter} AS lc ON concat('/',sk.open_path,'/') LIKE concat('%/',lc.id,'/%') AND lc.depth = 1 
        -- JOIN {local_skill_categories} AS lsc ON lsc.id = sk.category
        WHERE 1=1 ";
    $queryparam = array();

    if(!is_siteadmin()){
        $concatsql = (new \local_skillrepository\lib\accesslib())::get_costcenter_path_field_concatsql($columnname='sk.open_path');
        $concatsql .= " $concatsql ";
    }
    if (isset($filtervalues->search_query) && trim($filtervalues->search_query) != '') {
        $concatsql .= " AND (sk.name LIKE :search1 )";
        $queryparam['search1'] = '%'.trim($filtervalues->search_query).'%';
    }
    $count = $DB->count_records_sql($countsql.$concatsql, $queryparam);
    $concatsql.=" order by sk.id desc";
    $records = $DB->get_records_sql($selectsql.$concatsql, $queryparam, $tablelimits->start, $tablelimits->length);
    $list=array();
    $data=array();
    if ($records) {
        foreach ($records as $c) {
            $list=array();
            $id = $c->id;
            $costcenterid= explode('/',$c->open_path)[1];
            $userpathconcatsql = (new \local_costcenter\lib\accesslib())::get_costcenter_path_field_concatsql($columnname='u.open_path');
            $costcenterpathconcatsql = (new \local_costcenter\lib\accesslib())::get_costcenter_path_field_concatsql($columnname='c.open_path');
            $usercountsql = "SELECT count(DISTINCT(u.id))
                FROM {course} c
                JOIN {course_completions} cc
                on cc.course = c.id
                JOIN {user} u
                on cc.userid = u.id
                WHERE c.open_skill = {$id} and cc.timecompleted IS NOT NULL $userpathconcatsql $costcenterpathconcatsql";
            $usercount = $DB->count_records_sql($usercountsql);
            $skillmaped = $DB->record_exists('course',array('open_skill'=>$id));
            $skilname=$c->name;
         //   print_r($c);exit;
            $advance = get_config('local_skillrepository','advance');

            $list['skilname'] = $skilname;
            // $list['levelsCount'] = $DB->count_records('local_skill_levels', array('skillid' => $c->id, 'costcenterid' => $costcenterid)); 
           // $list['levelsCount'] = $DB->count_records('local_skill_levels', array('skillid' => $c->id)); 
            $list['organisationname'] = $c->organisationname;
            $list['organisationid'] = str_replace( array( '/'), '', $c->open_path);
            $list['skill_id'] = $c->id;
            $list['achieved_users'] = $usercount;
            $list['shortname']=$c->shortname;
            $list['skill_catname']=$c->skill_catname;
            $list['skillmaped']=$skillmaped;
            if($advance == 1)
            {
                $advance1 = true;
            }
            else
            {
                $advance1 = false;
            }
            $data[] = $list;
        }
    }
    return array('count' => $count, 'data' => $data,'advance'=>$advance1);
}

//////For display on level page//////////
function skills_level_details($tablelimits, $filtervalues){
    global $DB, $PAGE,$USER,$CFG,$OUTPUT;
    $concatsql = (new \local_skillrepository\lib\accesslib())::get_costcenter_path_field_concatsql($columnname='lcl.open_path');
    $systemcontext =(new \local_skillrepository\lib\accesslib())::get_module_context();
    $countsql = "SELECT count(lcl.id) FROM {local_course_levels} AS lcl WHERE 1=1 ";
    $selectsql = "SELECT lcl.id,lcl.name,lcl.code, concat(u.firstname,' ', u.lastname) as username, lc.fullname as organisationname
        FROM {local_course_levels} AS lcl
        JOIN {user} AS u ON u.id=lcl.usercreated
        JOIN {local_costcenter} AS lc ON concat('/',lcl.open_path,'/') LIKE concat('%/',lc.id,'/%') AND lc.depth = 1
        WHERE 1=1 ";
    $queryparam = array();

    if(!is_siteadmin()){
        $concatsql .= " $concatsql ";
    }

    if (isset($filtervalues->search_query) && trim($filtervalues->search_query) != '') {
        $concatsql .= " AND (lcl.name LIKE :search1 )";
        $queryparam['search1'] = '%'.trim($filtervalues->search_query).'%';
    }
    $count = $DB->count_records_sql($countsql.$concatsql, $queryparam);
    $concatsql.=" order by lcl.id desc";
    $records = $DB->get_records_sql($selectsql.$concatsql, $queryparam, $tablelimits->start, $tablelimits->length);

    $list=array();
    $data=array();

    if ($records) {
        foreach ($records as $c) {

            $dellevel = $DB->record_exists('course',array('open_level'=>$c->id));
            $list=array();
            $list['skillslevelname'] = $c->name;
            $list['username'] = $c->username;
            $list['code'] = $c->code;
            $list['organisationname'] = $c->organisationname;
            $list['dellevel'] = $dellevel;
            $list['skillslevel_id'] = $c->id;
            $data[] = $list;
        }
    }
    return array('count' => $count, 'data' => $data);
}

function skills_category_details($tablelimits, $filtervalues){
    global $DB, $PAGE,$USER,$CFG,$OUTPUT;
    $systemcontext =(new \local_skillrepository\lib\accesslib())::get_module_context();
    $countsql = "SELECT count(lsc.id) FROM {local_skill_categories} AS lsc WHERE 1=1 ";
    $concatsql = (new \local_skillrepository\lib\accesslib())::get_costcenter_path_field_concatsql($columnname='lsc.open_path');
    $selectsql = "SELECT lsc.*,lc.fullname as orginsationname from {local_skill_categories} AS lsc JOIN {local_costcenter} AS lc ON concat('/',lsc.open_path,'/') LIKE concat('%/',lc.id,'/%') AND lc.depth = 1 ";
    $queryparam = array();

    if(!is_siteadmin()){
        $concatsql .= " $concatsql ";
    }
    if (isset($filtervalues->search_query) && trim($filtervalues->search_query) != '') {
        $concatsql .= " AND (lsc.name LIKE :search1 ) ";
        $queryparam['search1'] = '%'.trim($filtervalues->search_query).'%';
    }
    $count = $DB->count_records_sql($countsql.$concatsql, $queryparam);

    $concatsql.=" order by lsc.id desc ";
    $records = $DB->get_records_sql($selectsql.$concatsql, $queryparam, $tablelimits->start, $tablelimits->length);
    $list=array();
    $data=array();
    if ($records) {
        foreach ($records as $c) {
            $list=array();

            $id = $c->id;
            $delcat = $DB->get_field('local_skill','category',array('category'=>$id));

            $list['skillscategoryname'] = $c->name;
            $list['username'] = $c->username;
            $list['code'] = $c->shortname;
            $list['organisationname'] = $c->orginsationname;
            $list['skillscategory_id'] = $c->id;

            $list['delete_cat'] = $delcat;

            $data[] = $list;
        }
    }

    return array('count' => $count, 'data' => $data);
}


function local_skillrepository_output_fragment_skills_interested($args){

    global $CFG, $DB;
    $args = (object) $args;
    $context = $args->context;
    $intskill_id = $args->id;
    $o = '';
    $formdata = [];

    $o = '';
     if (!empty($args->jsonformdata)) {
        $serialiseddata = json_decode($args->jsonformdata);
        parse_str($serialiseddata, $formdata);
    }

    if (empty($formdata) && $intskill_id > 0) {
        $data = $DB->get_record('local_interested_skills', array('id'=>$intskill_id));
        $formdata = new stdClass();
        $formdata->id = $data->id;
        $formdata->interested_skill_ids = $data->interestes_skill_ids;

        $fromsql="SELECT * FROM {local_skill} AS sk WHERE sk.id >0 ";
        $ordersql= " ORDER BY sk.id DESC";
        if($data->interested_skill_ids){
                $fromsql .=" AND sk.id IN ($data->interested_skill_ids) ";
        }

        $interested_skills_list = $DB->get_records_sql($fromsql .$ordersql);
        foreach($interested_skills_list as $intskills){
            $interested_skills[] =  $intskills->id;
        }
        $formdata->skills = $interested_skills;
    }

    $params = array(
    'intskill_id' => $intskill_id,
    'interested_skill_ids' => $formdata->featured_course_ids,
    'context' => $context
    );

    $mform = new local_skillrepository\form\skills_interested_form(null, array('contextid'=> $context, 'interested_skills' => $formdata->skills,'intskill_id' => $intskill_id ), 'post', '', null, true, (array)$formdata);
    $mform->set_data($formdata);
    if (!empty($formdata)) {
        // If we were passed non-empty form data we want the mform to call validation functions and show errors.
        $mform->is_validated();
    }
    ob_start();
    $mform->display();
    $o .= ob_get_contents();
    ob_end_clean();
    return $o;

}

function local_skillrepository_masterinfo(){
    global $CFG, $PAGE, $OUTPUT, $DB, $USER;
    $costcenterid = explode('/',$USER->open_path)[1];
    $systemcontext =(new \local_skillrepository\lib\accesslib())::get_module_context();
    $content = '';
    if(has_capability('local/skillrepository:manage', $systemcontext) || is_siteadmin()) {
        // skill
        $skills_Query = "SELECT count(id) FROM {local_skill}";
        if(!is_siteadmin()){
            $skills_Query .=" WHERE open_path = '/$costcenterid'";
        }
        $totalskill = $DB->count_records_sql($skills_Query);

        if($totalskill > 0) {
            $skill = '('.$totalskill.')';
        }


        // level
        $levels = "SELECT count(id) FROM {local_course_levels}";
        if(!is_siteadmin()){
            $levels .=" WHERE open_path = '/$costcenterid'";
        }
        $totallevel = $DB->count_records_sql($levels);

        if($totallevel > 0) {
            $lev = '('.$totallevel.')';
        } /*else {
            $lev = '<i class="fa fa-times" aria-hidden="true"></i>';
        }*/
        $templatedata = array();
        $templatedata['show'] = true;
        $templatedata['count'] = $skill;
        $templatedata['link'] = $CFG->wwwroot.'/local/skillrepository/index.php';
        $templatedata['stringname'] = get_string('skill','block_masterinfo');
        $templatedata['icon'] = '<i class="fa fa-hourglass-half" aria-hidden="true"></i>';
        $templatedata['show2'] = true;
        $templatedata['count2'] = $lev;
        $templatedata['link2'] = $CFG->wwwroot.'/local/skillrepository/level.php';
        $templatedata['stringname2'] = get_string('level','block_masterinfo');

        $content = $OUTPUT->render_from_template('block_masterinfo/masterinfo', $templatedata);
    }
    return array('4' => $content);
}
function local_skillrepository_output_fragment_new_assigncompetencylevel($args) {
    global $CFG, $DB;

    $args = (object) $args;
    $context = $args->context;
    $costcenterid = $args->costcenterid;
    $competencyid = $args->competencyid;
    // $skillid = $args->skillid;
    // $positionid = $args->positionid;
    // $levelid = $args->levelid; 
    $o = '';
    $formdata = [];
    // if (!empty($args->jsonformdata)) {
    //     $serialiseddata = json_decode($args->jsonformdata);
    //     parse_str($serialiseddata, $formdata);
    // }
    if (!empty($args->jsonformdata)) {
        $serialiseddata = json_decode($args->jsonformdata);
        if(is_object($serialiseddata)){
            $serialiseddata = serialize($serialiseddata);
        }
        parse_str($serialiseddata, $formdata);
    }
    $mform = new local_skillrepository\form\assignlevel(null, array('editoroptions' => $editoroptions,'costcenterid' => $costcenterid, 'competencyid' => $competencyid/*, 'skillid' => $skillid, 'positionid' => $positionid, 'levelid' => $levelid*/), 'post', '', null, true, $formdata);
    $mform->set_data($data);
    if (!empty($formdata)) {
        // If we were passed non-empty form data we want the mform to call validation functions and show errors.
        $mform->is_validated();
    }

    ob_start();
    $mform->display();
    $o .= ob_get_contents();
    ob_end_clean();
    return $o;
}
/**
 * Function to display the assign level form in popup
 * returns data of the popup 
 */
function local_skillrepository_output_fragment_new_assignskill($args) {
    global $CFG, $DB;

    $args = (object) $args;
    $context = $args->context;
    $costcenterid = $args->costcenterid;
    $competencyid = $args->competencyid;
    $complevelid = $args->complevelid;
    // $skillid = $args->skillid;
    // $positionid = $args->positionid;
    // $levelid = $args->levelid; 
    $o = '';
    $formdata = [];
    // if (!empty($args->jsonformdata)) {
    //     $serialiseddata = json_decode($args->jsonformdata);
    //     parse_str($serialiseddata, $formdata);
    // }
    if (!empty($args->jsonformdata) && strlen($args->jsonformdata) > 2) {
        $serialiseddata = json_decode($args->jsonformdata);
        if(is_object($serialiseddata)){
            $serialiseddata = serialize($serialiseddata);
        }
        parse_str($serialiseddata, $formdata);
    }
    $mform = new local_skillrepository\form\assignskill(null, array('editoroptions' => $editoroptions,'costcenterid' => $costcenterid, 'competencyid' => $competencyid, 'complevelid' => $complevelid), 'post', '', null, true, $formdata);
    $mform->set_data($formdata);

    if (!empty($formdata) && strlen($args->jsonformdata) > 2) {
        // If we were passed non-empty form data we want the mform to call validation functions and show errors.
        $mform->is_validated();
    }

    ob_start();
    $mform->display();
    $o .= ob_get_contents();
    ob_end_clean();
    return $o;
}
/**
 * Function to display the assign level form in popup
 * returns data of the popup 
 */
function local_skillrepository_output_fragment_new_assigncourse($args) {
    global $CFG, $DB;

    $args = (object) $args;
    $context = $args->context;
    $costcenterid = $args->costcenterid;
    $skillid = $args->skillid;
    $levelid = $args->levelid;
    $competencyid = $args->competencyid;
    $o = '';
    $formdata = [];
    // if (!empty($args->jsonformdata)) {
    //     $serialiseddata = json_decode($args->jsonformdata);
    //     parse_str($serialiseddata, $formdata);
    // }
    if (!empty($args->jsonformdata)) {
        $serialiseddata = json_decode($args->jsonformdata);
        if(is_object($serialiseddata)){
            $serialiseddata = serialize($serialiseddata);
        }
        parse_str($serialiseddata, $formdata);
    }
    $mform = new local_skillrepository\form\assigncourse(null, array('editoroptions' => $editoroptions,'costcenterid' => $costcenterid, 'skillid' => $skillid, 'levelid' => $levelid, 'competencyid' => $competencyid), 'post', '', null, true, $formdata);
    $mform->set_data($data);
    if (!empty($args->jsonformdata) && strlen($args->jsonformdata) > 2) {

        // If we were passed non-empty form data we want the mform to call validation functions and show errors.
        $mform->is_validated();
    }

    ob_start();
    $mform->display();
    $o .= ob_get_contents();
    ob_end_clean();
    return $o;
}
/**
 * Serve the table for course categories
 *
 * @param array $args List of named arguments for the fragment loader.
 * @return string 
 */
function local_skillrepository_output_fragment_skill_levelcourse_display($args){
    global $DB,$CFG,$PAGE,$OUTPUT;

    $args = (object) $args;
    $context = $args->context;
    $skillid = $args->skillid;
    $costcenterid = $args->costcenterid;
    $levelid = $args->levelid;
    $competencyid = $args->competencyid;

    $formdata = [];
    if (!empty($args->jsonformdata)) {
        $serialiseddata = json_decode($args->jsonformdata);
        parse_str($serialiseddata, $formdata);
    }

    $table = new html_table();
    $table->id = 'popup_course';
    $table->align = ['left','center','center','center','center'];
    $table->head = array(get_string('course_name', 'local_courses'),get_string('enrolledusers', 'local_courses'),get_string('completed_users', 'local_courses'),get_string('type', 'local_courses'));
    $concatsql = (new \local_skillrepository\lib\accesslib())::get_costcenter_path_field_concatsql($columnname='c.open_path');
    
$courses = $DB->get_records_sql("SELECT c.id,c.category,c.fullname, c.open_identifiedas FROM {course} c, {local_comp_course_mapping} cc WHERE c.id = cc.courseid AND c.id > 1 AND cc.skillid = {$skillid} AND cc.levelid = {$levelid} AND cc.competencyid = {$competencyid} $concatsql");
    if($courses){
    $data=array();
    foreach($courses as $course){
        $row = array();
        $row[] = html_writer::link(new moodle_url('/course/view.php',array('id'=>$course->id)),$course->fullname);
        $course_sql = "SELECT count(ue.userid) as enrolled,count(cc.course) as completed
                            FROM {user_enrolments} as ue
                            JOIN {enrol} as e ON e.id=ue.enrolid
                            RIGHT JOIN {course} as c ON c.id =e.courseid
                            LEFT JOIN {course_completions} cc ON cc.course=e.courseid and ue.userid=cc.userid and cc.timecompleted IS NOT NULL
                            WHERE c.id = ?
                                group by e.courseid";
        $course_stats = $DB->get_record_sql($course_sql, [$course->id]);
       if($course_stats->enrolled){
            $row[] = $course_stats->enrolled;
        }else{
             $row[] = "N/A";
        }
        if($course_stats->completed){
            $row[] = $course_stats->completed;
        }else{
             $row[] = "N/A";
        }

        // $ilt_sql = "SELECT open_identifiedas from {course}  WHERE id = ? " ;  
        // $ilt_stats = $DB->get_record_sql($ilt_sql, [$course->id]);
        // $types = explode(',',$ilt_stats->open_identifiedas);
        // $classtype = array();
        // foreach($types as $type){

        //     if($type == 2){
        //       $classtype[0]= get_string('ilt','local_courses');
        //     }
        //     if($type == 3){
        //      $classtype[2]= get_string('elearning','local_courses');
        //     }
        //     if($type == 4){
        //      $classtype[3]= get_string('learningplan','local_courses');
        //     }
        //     if($type == 5){
        //      $classtype[5]= get_string('program','local_courses');
        //     }
        //     if($type == 6){
        //      $classtype[6]= get_string('certification','local_courses');
        //     }
        // }
        // $ctype = implode(',',$classtype);
        $ctype = $DB->get_field('local_course_types', 'name', array('id' => $course->open_identifiedas));
        if($ctype){

            $row[] = $ctype;
        }else{
             $row[] = "N/A";
        }
        $data[] = $row;
    }
    $table->data = $data;
    $output = html_writer::table($table);
    $output .= html_writer::script("$('#popup_course').DataTable({
        'language': {
            paginate: {
            'previous': '<',
            'next': '>'
            }
        },
        'bInfo' : false,
        lengthMenu: [
            [5, 10, 25, 50, 100, -1],
            [5, 10, 25, 50, 100, 'All']
        ]
    });");
    }else{
        $output = '<div class="text-center">No Courses Available</div>';
    }

    return $output;
}


function local_skillrepository_output_fragment_competency_course_display($args){
    global $DB,$CFG,$PAGE,$OUTPUT;
    $systemcontext =(new \local_skillrepository\lib\accesslib())::get_module_context();
    $args = (object) $args;
    $context = $args->context;
    $courseid = $args->courseid;
    $costcenterid = explode('/', $DB->get_field('course', 'open_path', array('id'=>$courseid)))[1];
    $formdata = [];
    if (!empty($args->jsonformdata)) {
        $serialiseddata = json_decode($args->jsonformdata);
        parse_str($serialiseddata, $formdata);
    }

    $table = new html_table();
    $table->id = 'popup_competency_course';
    $table->align = ['left','center','center','center','center'];
    $table->head = array(get_string('competency','local_skillrepository'), get_string('skill', 'local_skillrepository'), get_string('level','local_skillrepository'));
    $concatsql = (new \local_skillrepository\lib\accesslib())::get_costcenter_path_field_concatsql($columnname='c.open_path');
    $competencies = $DB->get_records_sql("SELECT sc.name as competency, ls.name as skill, cl.name as level FROM {local_comp_course_mapping} cc, {local_skill_categories} sc, {local_skill} as ls, {local_course_levels} as cl WHERE cc.competencyid = sc.id AND cc.skillid = ls.id AND cc.levelid = cl.id AND cc.courseid =:courseid", array('courseid'=>$courseid));

    if(is_siteadmin() || has_capability('local/skillrepository:manage', $systemcontext)){
        $output = "
                  
                    <div class='coursebackup course_extended_menu_itemcontainer float-right'>
                    <a id='extended_menu_syncstats' title='".get_string('assigncomp', 'local_skillrepository')."' class='course_extended_menu_itemlink' href='javascript:void(0)' onclick ='(function(e){ require(\"local_skillrepository/assigncoursecomp\").init({selector:\"createcategorymodal\", contextid:".$systemcontext->id.",courseid:".$courseid." ,costcenterid:".$costcenterid."}) })(event)'><i class='icon fa fa-plus' aria-hidden='true' aria-label=''></i></a>
                    </div>              
               
                ";
    }
          

    if($competencies){
    $data=array();
    foreach($competencies as $competency){
        $row = array();
        $row[] = $competency->competency;
        $row[] = $competency->skill;
        $row[] = $competency->level;
        $data[] = $row;
    }
    $table->data = $data;
    $output .= html_writer::table($table);
    $output .= html_writer::script("$('#popup_competency_course').DataTable({
        'language': {
            paginate: {
            'previous': '<',
            'next': '>'
            }
        },
        'bInfo' : false,
        lengthMenu: [
            [5, 10, 25, 50, 100, -1],
            [5, 10, 25, 50, 100, 'All']
        ]
    });");
    }else{
        $output .= '<div class="text-center">No competencies Available</div>';
    }

    return $output;
}

function local_skillrepository_output_fragment_competencycourse_form($args){
    global $CFG,$DB;
    $args = (object) $args;
    $context = $args->context;
    $costcenterid = "/".$args->costcenterid;
    $courseid = $args->courseid;
    $o = '';
    $formdata = [];
    if (!empty($args->jsonformdata)) {
        $serialiseddata = json_decode($args->jsonformdata);
        if(is_object($serialiseddata)){
            $serialiseddata = serialize($serialiseddata);
        }
        parse_str($serialiseddata, $formdata);
    }

    $customdata = array(
        'costcenterid' => $costcenterid, 'courseid' => $courseid);
        local_costcenter_set_costcenter_path($customdata);
    $mform = new local_skillrepository\form\competencycourse_form(null, $customdata, 'post', '', null, true, $formdata);
    $mform->set_data($data);
        if (!empty($formdata) && strlen($args->jsonformdata) > 2) {
            // If we were passed non-empty form data we want the mform to call validation functions and show errors.
            $mform->is_validated();
        }

        ob_start();
        $mform->display();
        $o .= ob_get_contents();
        ob_end_clean();
        return $o;
}

/**
 * Function to display the assign level form in popup
 * returns data of the popup 
 */
function local_skillrepository_output_fragment_new_assignlevel($args) {
    global $CFG, $DB;

    $args = (object) $args;
    $context = $args->context;
    $costcenterid = $args->costcenterid;
    $competencyid = $args->competencyid;
    $skillid = $args->skillid;
    $positionid = $args->positionid;
    $levelid = $args->levelid; 
    $o = '';
    $formdata = [];
    // if (!empty($args->jsonformdata)) {
    //     $serialiseddata = json_decode($args->jsonformdata);
    //     parse_str($serialiseddata, $formdata);
    // }
    if (!empty($args->jsonformdata)) {
        $serialiseddata = json_decode($args->jsonformdata);
        if(is_object($serialiseddata)){
            $serialiseddata = serialize($serialiseddata);
        }
        parse_str($serialiseddata, $formdata);
    }
    $mform = new local_skillrepository\form\assignlevel(null, array('editoroptions' => $editoroptions,'costcenterid' => $costcenterid, 'competencyid' => $competencyid, 'skillid' => $skillid, 'positionid' => $positionid, 'levelid' => $levelid), 'post', '', null, true, $formdata);
    $mform->set_data($data);
    if (!empty($formdata) && strlen($args->jsonformdata) > 2) {
        // If we were passed non-empty form data we want the mform to call validation functions and show errors.
        $mform->is_validated();
    }

    ob_start();
    $mform->display();
    $o .= ob_get_contents();
    ob_end_clean();
    return $o;
}
function local_skillrepository_output_fragment_skill_level_display($args){
    global $DB;
    $levelsSql = "SELECT lcl.id,lcl.name FROM {local_course_levels} AS lcl 
        JOIN {local_skill_levels} AS lsl ON lsl.levelid = lcl.id
        WHERE lsl.costcenterid = :costcenterid AND lsl.skillid = :skillid ";
    $levelsinfo = $DB->get_records_sql_menu($levelsSql, array('costcenterid' => $args['costcenterid'], 'skillid' => $args['skillid']));
    if($levelsinfo){
        $table = new html_table();
        $table->id = 'skilllevel_info';
        $table->head = array(get_string('levelname', 'local_skillrepository'), get_string('actions'));
        $table->data = [];
        $removeicon = html_writer::tag('i', '', array('class' => 'fa fa-times'));
        foreach($levelsinfo AS $key =>  $level){
            $removelink = html_writer::link('javascript:void(0)', $removeicon, array('class' => 'removelevelSkill', 'title' => get_string('purgelevel', 'local_skillrepository'), 'data-skillid' => $args['skillid'], 'data-costcenterid' => $args['costcenterid'], 'data-levelid' => $key, 'data-levelname' => $level));
            $table->data[] = [$level, $removelink];
        }
        return html_writer::table($table);
    }else{
        return html_writer::div(get_string('nolevelsavailable','local_skillrepository'), 'alert alert-info text-center');
    }
}
/**
 * Serve the table for course categories
 *
 * @param array $args List of named arguments for the fragment loader.
 * @return string 
 */
function local_skillrepository_output_fragment_competency_skills_display($args){
    global $DB,$CFG,$PAGE,$OUTPUT;

    $args = (object) $args;
    $context = $args->context;
    $compitencyid = $args->categoryid;
    $costcenterid = $args->costcenterid;
    $positionid = $args->positionid;
    $userid = $args->userid;

    $formdata = [];
    if (!empty($args->jsonformdata)) {
        $serialiseddata = json_decode($args->jsonformdata);
        parse_str($serialiseddata, $formdata);
    }

    $table = new html_table();
    $table->id = 'popup_category';
    if($args->type == 'incompetencies') {
        $table->head = array(get_string('skill_name', 'local_skillrepository'),
                        // get_string('achievedusercount', 'local_skillrepository'),
                        get_string('shortname', 'local_skillrepository')
                        );
        $table->align = array('left','center','left');
    } else {
        $table->head = array(get_string('skillname', 'local_users'),get_string('level', 'local_skillrepository'),get_string('status', 'local_users'),get_string('courses'));
        $table->align = array('left','left','left');
    }
    if($args->type === 'incompetencies') {
        $systemcontext = context_system::instance();
        $skills = $DB->get_records('local_skill', array('category'=>$compitencyid));
    } else {
        $sql = "SELECT lsm.id as skillMatrixId, lsm.skilllevel as skilllevel, lsm.levelid AS compllevelid,
                    (SELECT name FROM {local_course_levels} WHERE id = lsm.levelid ) as competencylevelname, s.* 
                    FROM {local_skill} AS s 
                    JOIN {local_skillmatrix} AS lsm ON lsm.skillid = s.id AND lsm.positionid = {$positionid}
                    WHERE lsm.skill_categoryid = {$compitencyid} ";
        if($costcenterid){
            $sql .= "AND concat('/',s.open_path,'/') LIKE '%$costcenterid%'";
        } else {
        }
        $skills = $DB->get_records_sql($sql);
    }

    $data=array();
    $data1=array();
    if($skills){
        foreach($skills as $skill){
            if($args->type === 'incompetencies') {
                // code for comitency skill popup in competency page
                $usercountsql = "SELECT count(u.id) 
                        FROM {course} c
                        JOIN {local_comp_course_mapping} ccm ON ccm.courseid = c.id
                        JOIN {course_completions} cc
                        on cc.course = c.id
                        JOIN {user} u
                        on cc.userid = u.id
                        WHERE ccm.skillid = {$skill->id} and cc.timecompleted IS NOT NULL
                        ";
                $usercount = $DB->count_records_sql($usercountsql);

                $rec = array();
                $rec[] = $skill->name;
                $rec[] = $skill->shortname;
                $data1[] = $rec;            
            } else {
                $row = array();
                $get_level = $DB->get_field('local_course_levels', 'name', array('id' => $skill->skilllevel));
                
                $row[] = $skill->name;
                if($get_level){
                    $row[] = $get_level;
                } else {
                    $row[] = get_string('n/a', 'local_skillrepository');            
                }
                

                if($skill->skilllevel) {
                    $courseSql = "SELECT c.id, c.fullname, cc.timecompleted
                    FROM {course} AS c
                    JOIN {local_comp_course_mapping} ccm ON ccm.courseid = c.id
                    JOIN {enrol} AS e ON c.id = e.courseid
                    JOIN {user_enrolments} AS ue ON e.id = ue.enrolid 
                    LEFT JOIN {course_completions} AS cc ON ue.userid = cc.userid AND cc.course = c.id
                    WHERE 
                    c.visible=1 AND c.id>1 AND ue.userid = {$userid} AND ccm.levelid={$skill->skilllevel} AND ccm.skillid={$skill->id} ORDER BY cc.timecompleted DESC";
                    $courseInfo = $DB->get_records_sql($courseSql);
                    if($courseInfo){
                        
                        $completed = false;
                        $coursename = '';
                        $courses = [];
                        foreach($courseInfo AS $info){
                            if($info->timecompleted > 0){
                                $completed = true;
                                $coursename = $info->fullname;
                                break;
                            }else{
                                $courses[] = $info->fullname;
                            }
                        }
                        if($completed){
                            $row[]=get_string('completed', 'local_skillrepository');
                        }else{
                            $row[]= get_string('notcompleted', 'local_skillrepository');
                        }
                        if(!empty($coursename)){
                            $row[] = $coursename;    
                        }else{

                            if(count($courses) > 2){
                                $titlename = implode(' ,', $courses);
                                $displayname = implode(' ,', array_chunk($courses, 2)[0]).'...';
                            }else{
                                $displayname = $titlename = implode(' ,', $courses);
                            }
                            $row[] = html_writer::tag('span', $displayname, array('title' => $titlename));    
                        }
                    }else{
                        $row[]=get_string('notcompleted', 'local_skillrepository');
                        $row[]=get_string('n/a', 'local_skillrepository');
                    }
                } else {
                    $row[]=get_string('notcompleted', 'local_skillrepository');
                    $row[]= get_string('n/a', 'local_skillrepository');
                }
                $data[] = $row;
            }
            
        }
        if($args->type == 'incompetencies') {
            $table->data = $data1;
        } else {
            $table->data = $data;
        }
        $output = html_writer::table($table);
        $output .= html_writer::script("$('#popup_category').DataTable({
            'language': {
                paginate: {
                'previous': '<',
                'next': '>'
                }
            },
            'bInfo' : false,
            lengthMenu: [
                [5, 10, 25, 50, 100, -1],
                [5, 10, 25, 50, 100, 'All']
            ]
        });");
    }else{
        $output = get_string('noskills', 'local_skillrepository');
    }

    return $output;
}


function custom_competency_details($tablelimits, $filtervalues){
    global $DB, $PAGE,$USER,$CFG,$OUTPUT;
    $systemcontext = (new \local_skillrepository\lib\accesslib())::get_module_context();
    $costcenterpathconcatsql = (new \local_costcenter\lib\accesslib())::get_costcenter_path_field_concatsql($columnname='open_path');

    $countsql = "SELECT count(lsc.id) FROM {local_skill_categories} AS lsc WHERE 1=1 ";
    if(!is_siteadmin())
    {
        $countsql = $countsql.$costcenterpathconcatsql;
    }

    $selectsql = "SELECT lsc.*
        FROM {local_skill_categories} AS lsc
        WHERE 1=1 ";
    if(!is_siteadmin())
    {
        $selectsql =$selectsql.$costcenterpathconcatsql;
    }

    $queryparam = array();

    if (isset($filtervalues->search_query) && trim($filtervalues->search_query) != '') {
        $concatsql .= " AND (lsc.name LIKE :search1 )";
        $queryparam['search1'] = '%'.trim($filtervalues->search_query).'%';
    }

    if (isset($filtervalues->filteropen_costcenterid) && $filtervalues->filteropen_costcenterid != "") {
        $concatsql .= " AND (lsc.open_path = :costpath )";
        $queryparam['costpath'] = '/'.$filtervalues->filteropen_costcenterid;
    }

    $count = $DB->count_records_sql($countsql.$concatsql, $queryparam);
    $concatsql.=" order by lsc.id desc";
    $competencies = $DB->get_records_sql($selectsql.$concatsql, $queryparam, $tablelimits->start, $tablelimits->length);

    $data = array();
    foreach($competencies AS $competency)
    {
        $costarr = explode("/",$competency->open_path);

        $competency->organisationname = $DB->get_field('local_costcenter','fullname', array('id'=> $costarr[1]));
        $competency->skillcount = $DB->count_records_sql("SELECT count(lcsm.id) FROM {local_comp_skill_mapping} AS lcsm WHERE competencyid= $competency->id");
        $competency->levelcount = $DB->count_records_sql("SELECT count(lsl.id) FROM {local_skill_levels} AS lsl WHERE competencyid= $competency->id");

        $editurl = "javascript:void(0)";
        $editicon = '<i class="fa fa-pencil fa-fw" title=""></i>';
        $competency->edit = html_writer:: link($editurl,  $editicon.get_string('edit','local_skillrepository'),  array('title'=>get_string('edit','local_skillrepository'),'onclick' => '(function(e){ require("local_skillrepository/newcategory").init({selector:"createcategorymodal", contextid:'.$systemcontext->id.', categoryid:'.$competency->id.'}) })(event)','class'=>'dropdown-item'));

        $deleteurl = "javascript:void(0)";
        $deleteicon = '<i class="fa fa-trash fa-fw" aria-hidden="true" title="" aria-label="Delete"></i>';

        $skills = $DB->get_records('local_comp_skill_mapping', array('competencyid'=>$competency->id));
        
        if(empty($skills)){
            $competency->delete = html_writer:: link($deleteurl, $deleteicon.get_string('delete','local_skillrepository'), array('title'=>get_string('delete','local_skillrepository'),'onclick' => '(function(e){ require("local_skillrepository/newcategory").deletecategory({selector:"deletecategory", contextid:'.$systemcontext->id.', categoryid:'.$competency->id.', name:"'.$competency->name.'"}) })(event)','class'=>'dropdown-item'));
        } else {

            $delete_reason = get_string('deletecompitency_reason','local_skillrepository');
            $competency->delete = html_writer:: link($deleteurl, $deleteicon.get_string('delete','local_skillrepository'), array('title'=>get_string('delete','local_skillrepository'),'onclick' => '(function(e){ require("local_skillrepository/newcategory").reasonfor_unabletodelete({reason: "'.$delete_reason.'" })})(event)','class'=>'dropdown-item'));
        }

    }

    return array('count' => $count, 'data' => $competencies);

}

function competency_filters_form($filterparams, $formdata = []) {
    global $CFG, $USER;

    require_once($CFG->dirroot . '/local/courses/filters_form.php');

    $categorycontext=(new \local_users\lib\accesslib())::get_module_context();
    if (is_siteadmin()) {
        $mform = new filters_form(null, array('filterlist' => array( 'costcenter_field'), 'courseid' => 1,
             'enrolid' => 0, 'plugins' => array('users', 'costcenter'), 'filterparams' => $filterparams)+$formdata);
    } else {
        $filters = array('costcenter_field');

        $mform = new filters_form(null, array('filterlist' => $filters, 'courseid' => 1, 'enrolid' => 0, 'plugins' => array('users', 'costcenter'), 'filterparams'
          => $filterparams)+$formdata);
    }
    return $mform;
}

function get_user_competencies($data)
{
    global $DB, $CFG, $USER;
    $systemcontext = (new \local_skillrepository\lib\accesslib())::get_module_context();
    
    $sql = "SELECT sc.*, sm.positionid FROM {local_skillmatrix} as sm JOIN {local_skill_categories}
             as sc ON sc.id=sm.skill_categoryid where sm.positionid=:open_positionid";

            $competencies = $DB->get_records_sql($sql, array('open_positionid'=>$data['open_positionid']));
    foreach ($competencies as $competency) {
        $userid = $data['userid'];
        $positionid = $data['open_positionid'];
        $competencyid = $competency->id;
        $costcenterid = explode('/', $competency->open_path)[1];


        $sql = "SELECT lsm.id as skillMatrixId, lsm.skilllevel as skilllevel, lcl.name AS levelname, lsm.skilllevel AS levelid,s.* 
                    FROM {local_skill} AS s 
                    JOIN {local_skillmatrix} AS lsm ON lsm.skillid = s.id 
                    JOIN {local_course_levels} AS lcl ON lsm.skilllevel = lcl.id 
                    AND lsm.positionid = {$positionid}
                    WHERE lsm.skill_categoryid = {$competencyid} AND s.open_path = '/".$costcenterid."'";
        $skills = $DB->get_records_sql($sql);
        $coursearr = array();
        foreach($skills as $skill){
            $competency->skillid = $skill->id;
            $competency->levelid = $skill->levelid;
            $competency->skillname = $skill->name;
            $competency->levelname = $skill->levelname;

                if($skill->skilllevel) {
                    $courseSql = "SELECT c.id, c.fullname
                    FROM {course} AS c
                    JOIN {local_comp_course_mapping} ccm ON ccm.courseid = c.id
                    WHERE 
                    c.visible=1 AND c.id>1 AND ccm.levelid={$skill->levelid} AND ccm.skillid={$skill->id} AND ccm.competencyid={$competency->id}";
                    $courseInfos = array_values($DB->get_records_sql($courseSql));
                    
                    foreach($courseInfos AS $courseInfo){

                        $sql = "SELECT c.id, c.fullname, cc.timecompleted
                        FROM {course} AS c
                        JOIN {local_comp_course_mapping} AS ccm ON ccm.courseid = c.id
                        JOIN {enrol} AS e ON c.id = e.courseid
                        JOIN {user_enrolments} AS ue ON e.id = ue.enrolid 
                        LEFT JOIN {course_completions} AS cc ON ue.userid = cc.userid AND cc.course = c.id
                        WHERE 
                        c.visible=1 AND c.id>1 AND ue.userid = {$userid} AND c.id ={$courseInfo->id} AND ccm.levelid={$skill->skilllevel} AND ccm.skillid={$skill->id} ORDER BY cc.timecompleted DESC";

                        $coursedata = $DB->get_record_sql($sql);

                        if($coursedata)
                        {
                            if($coursedata->timecompleted)
                            {
                                $courseInfo->timecompleted = \local_costcenter\lib::get_userdate("d/m/Y H:i", $coursedata->timecompleted);
                            }
                            $courseInfo->enrolled = get_string('enrolled', 'local_skillrepository');
                        }else
                        {
                            if($USER->open_supervisorid != 0){
                                $icon = '<i" title="enroll">'.get_string('enrollhere', 'local_skillrepository').'</i>';
        
                                $noredirecturl = 'javascript:void(0)';
                                $action = \html_writer::link($noredirecturl ,$icon,array('onclick' => '(function(e){ require("local_search/courseinfo").coursetest({selector:"courseselfenrol", contextid:'.$systemcontext->id.', courseid:'.$courseInfo->id.', enroll:1, coursename:"'.$courseInfo->fullname.'" }) })(event)'));
                                $courseInfo->enrolled = $action;
                            }else
                            {
                                $courseInfo->enrolled = get_string('notenrolled', 'local_skillrepository');
                            }
                            $courseInfo->timecompleted = null;
                        }

                    }
                }
                    $competency->course= $courseInfos;


        }
    }

    return $competencies;
}
