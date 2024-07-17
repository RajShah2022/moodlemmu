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
 * @subpackage local_customform
 */
require_once(dirname(__FILE__) . '/../../config.php');
global $CFG;
require_once($CFG->dirroot . '/lib/moodlelib.php');

function local_custom_category_output_fragment_new_custom_category_form($args){
    global $CFG,$DB;
    $args = (object) $args;
    $context = $args->context;
    $repositoryid = $args->repositoryid;
    $parentcatid = $args->parentcatid;
    $o = '';
    $formdata = [];
    $querylib = new \local_custom_category\querylib();
    if (!empty($args->jsonformdata)) {
        $serialiseddata = json_decode($args->jsonformdata);
        if(is_object($serialiseddata)){
            $serialiseddata = serialize($serialiseddata);
        }
        parse_str($serialiseddata, $formdata);
    }
    if ($args->repositoryid > 0) {
        $heading = get_string('updatecuscategory', 'local_custom_category');        
        $data = $querylib->category_record(array('id'=>$repositoryid));
    }

    $mform = new local_custom_category\form\custom_category_form(null, array('id' => $args->repositoryid, 'editoroptions' => $editoroptions, 'open_costcenterid' => $data->costcenterid, 'parentid' => $data->parentid, 'parentcatid' => $parentcatid), 'post', '', null, true, $formdata);
    if($data){
        $data->name = $data->fullname;
        $data->parentid = $data->parentid ? $data->parentid:'Top';
        $data->open_costcenterid = $data->costcenterid;
        $mform->set_data($data);
    }

    if (!empty($args->jsonformdata)) {
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
function custom_category_details($tablelimits, $filtervalues){
    global $DB, $PAGE,$USER,$CFG,$OUTPUT;
    $systemcontext =(new \local_custom_category\lib\accesslib())::get_module_context();
    $querylib = new \local_custom_category\querylib();
    $countsql = "SELECT count(lcc.id) FROM {local_custom_fields} AS lcc WHERE 1 ";
    $selectsql = "SELECT lcc.*, lc.fullname as organisationname
        FROM {local_custom_fields} AS lcc
        JOIN {local_costcenter} AS lc ON lc.id = lcc.costcenterid
        WHERE 1 ";
    if($tablelimits->parentcatid > 0){
        $concatsql .= " AND lcc.parentid = ".$tablelimits->parentcatid;
    } else {
        $concatsql .= " AND lcc.parentid = 0 ";
    }

    $queryparam = array();

    if(!is_siteadmin()){        
        $costcenterid = explode("/",$USER->open_path);
        $concatsql .= " AND lcc.costcenterid= :usercostcenter ";
        $queryparam['usercostcenter'] = $costcenterid[1];

    }
    if (isset($filtervalues->search_query) && trim($filtervalues->search_query) != '') {
        $concatsql .= " AND (lcc.fullname LIKE :search1 )";
        $queryparam['search1'] = '%'.trim($filtervalues->search_query).'%';
    }
    $count = $DB->count_records_sql($countsql.$concatsql, $queryparam);
    $concatsql.=" order by lcc.id desc";
    $records = $DB->get_records_sql($selectsql.$concatsql, $queryparam, $tablelimits->start, $tablelimits->length);

    $list=array();
    $data=array();
    if ($records) {
        foreach ($records as $c) {
            $list=array();
            $id = $c->id;           
            $categoryexist = $querylib->category_mapped_exist(array('category'=>$c->id));
            $categorparent = $querylib->category_exist(array('parentid'=>$c->id));
            $parent = $querylib->category_field('fullname', array('id' => $c->parentid));
            $childcount = $querylib->category_child_count(array('parentid' => $c->id));
            $list['custom_category_name'] = $c->fullname;
            $list['organisationname'] = $c->organisationname;
            $list['custom_category_id'] = $c->id;
            $list['shortname']=$c->shortname;
            $list['parent']=$parent ? $parent : 'N/A';
            $list['childcount']=$childcount ? $childcount : 0;
            $list['wwwroot']= $CFG->dirroot.'/local/custom_category/index.php?';
            $list['childs']= $tablelimits->parentcatid > 0 ? $tablelimits->parentcatid : 0;
            if($categorparent){
                $list['categoryexist'] = $categorparent;
            } elseif($categoryexist) {
                $list['categoryexist'] = $categoryexist;
            }
            $data[] = $list;
        }
    }
    return array('count' => $count, 'data' => $data);
}

function local_custom_category_masterinfo(){
    global $CFG, $PAGE, $OUTPUT, $DB, $USER;
    $costcenterid = explode('/',$USER->open_path)[1];
    $categorycontext = (new \local_courses\lib\accesslib())::get_module_context();
    $categoryquerylib = new \local_custom_category\querylib();
    $content = '';
    if(has_capability('local/custom_category:view_custom_category', $categorycontext) || is_siteadmin()) {
        $totalcourse_category = $categoryquerylib->category_count(array('costcenterid' => $costcenterid));

        if($totalcourse_category > 0) {
            $cat = '('.$totalcourse_category.')';
        }
        $templatedata = array();
        $templatedata['show'] = true;
        $templatedata['count'] = $cat;
        $templatedata['link'] = $CFG->wwwroot.'/local/custom_category/index.php';
        $templatedata['stringname'] = get_string('category','block_masterinfo');
        $templatedata['icon'] = '<i class="fa fa-cubes"></i>';

        $content = $OUTPUT->render_from_template('block_masterinfo/masterinfo', $templatedata);
    }
    return array('3' => $content);
}

function get_custom_categories($costcenterid, $mform, $moduletype, $categoryids = false){

    global $CFG, $PAGE, $OUTPUT, $DB, $USER;
    $categoryquerylib = new \local_custom_category\querylib();
    $parentcategories = $categoryquerylib->category_records(1, array('costcenter'=>$costcenterid));
    $categorys = array();
    foreach($parentcategories as $key=>$value){
        $categoryname = $value->fullname;
        $categoryid = $value->id;
        $categorys[] = $value->id;
        $childcategories = $categoryquerylib->category_fullnamewithid(array('costcenter'=>$costcenterid, 'parent'=>$categoryid));
        
        $select = $mform->addElement('autocomplete', 'category_'.$categoryid, $categoryname, $childcategories);
        $mform->setType('category_'.$categoryid, PARAM_RAW);
        $select->setMultiple(true);

    }
    $categoryids = implode(',',$categorys);
    $mform->addElement('hidden', 'parentid', $categoryids);
    $mform->setType('parentid', PARAM_INT);
    $mform->setDefault('parentid',  $categoryids);

    $mform->addElement('hidden', 'moduletype', $moduletype);
    $mform->setType('moduletype', PARAM_RAW);
    $mform->setDefault('moduletype', $moduletype);
}
function get_parent_categoryids(){
    global $DB, $USER, $CFG;
    $parentcatids = '';
    if(is_siteadmin()){
        $parentcatids = $DB->get_records_sql("SELECT DISTINCT(lcc.id) FROM {local_custom_fields} as lcc JOIN {local_custom_fields} as lc on lc.parentid LIKE lcc.id");
    } else {
        $costcenterpathconcatsql = (new \local_costcenter\lib\accesslib())::get_costcenter_path_field_concatsql($columnname="CONCAT('/',lcc.costcenterid,'')");

        $parentcatids = $DB->get_records_sql("SELECT DISTINCT(lcc.id) FROM {local_custom_fields} as lcc JOIN {local_custom_fields} as lc on lc.parentid LIKE lcc.id WHERE 1=1 $costcenterpathconcatsql");
    }    
    return $parentcatids;
}

function get_moduleid_of_mapped_category($filterdata,$parentcatids,$moduletype,$moduleid){
    $sql = '';
    $params = [];
    $i = 0;
    foreach($parentcatids AS $parentcatid){
        if(!empty($filterdata->{'catfilter_'.$parentcatid->id})){
            $categories = $filterdata->{'catfilter_'.$parentcatid->id};

            $categories = explode(',', $categories);
            $catgeorylike = [];
            foreach($categories AS $category){
                $catgeorylike[] =  " concat(',',lcm.category,',') like :categorylike{$i} ";
                $params['categorylike'.$i] = '%,'.$category.',%';
                $i++;
            }
            if(!empty($catgeorylike)){
                $catdependencysql = implode(' OR ', $catgeorylike);
                $sql .= " AND $moduleid IN (SELECT lcm.moduleid FROM {local_category_mapped} AS lcm WHERE lcm.moduletype LIKE '$moduletype' AND ($catdependencysql) ) ";
            }
        }
    }
    return array('sql' => $sql, 'params' => $params);
}

function insert_category_mapped($data){
    global $DB, $USER;
    $categoryrecords = new stdClass();
    $categoryrecords->moduletype = $data->moduletype;
    $categoryrecords->costcenterid = $data->costcenterid;
    $categoryrecords->moduleid = $data->id;
    if($data->parentid){
        $parentid = explode(',', $data->parentid);
        foreach($parentid as $parent){
            $categoryrecords->id = $data->childcategoryid[$parent];
            $categoryrecords->parentid = $parent;
            $category = 'category_'.$parent;
            $categories = $data->$category != '_qf__force_multiselect_submission' ? implode(',', $data->$category) : 0;
            $categoryrecords->category = $categories ? $categories : 0;
            if($categoryrecords->id){
                $categoryrecords->timemodified = time();
                $categoryrecords->usermodified = $USER->id;
                $DB->update_record('local_category_mapped', $categoryrecords);     
            }else{
                $categoryrecords->timecreated = time();
                $categoryrecords->usercreated = $USER->id;
                $DB->insert_record('local_category_mapped', $categoryrecords);
            }

        }
    }

}
function category_mapping($data){
    global $DB, $USER;
    $topcats = $DB->get_records('local_custom_fields', array('depth'=>1, 'costcenterid'=> $data->costcenterid), '', 'id');
    if($topcats){
        $parentcatid = [];
        $data->timecreated = time();
        $data->usercreated = $USER->id;
        foreach($topcats as $parentcat){
            $data->parentid = $parentcat->id;
            $DB->insert_record('local_category_mapped', $data);
        }
    }
}
