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
require_once('lib.php');

class local_skillrepository_renderer extends plugin_renderer_base {

    /*
 *  @method display table for showing repositories
 *  @return skill repository table
 */
    public function display_table() {
        global $DB, $CFG, $OUTPUT,$USER, $PAGE;
        $repository = new local_skillrepository\event\insertrepository();

        $systemcontext =(new \local_skillrepository\lib\accesslib())::get_module_context();
        if(is_siteadmin()){
            $skill = $repository->skillrepository_opertaions('local_skill', 'fetch-multiple','','','');
        } else {
            $open_path=$DB->get_field('user','open_path',array('id'=>$USER->id));
            $costcenterid=explode('/',$open_path)[1];
            $object=1;
            $skill = $repository->skillrepository_opertaions('local_skill', 'fetch-multiple',$object,'costcenterid',$costcenterid);
        }
        // Create Table Format
        $table = new html_table();
        $table->id = 'skill_repository';
        $table->attributes['class'] = 'generaltable';
        $table->head = [get_string('skill_name', 'local_skillrepository'),
                        get_string('achievedusercount', 'local_skillrepository'),
                        get_string('shortname', 'local_skillrepository'),
                        get_string('category', 'local_skillrepository'),
                        get_string('actions')
                        ];
        $table->align = array('left' ,'left', 'left', 'center');
        if ($skill) {
            foreach ($skill as $c) {
                $id = $c->id;
                $usercountsql = "SELECT count(u.id)
                FROM {course} c
                JOIN {course_completions} cc
                on cc.course = c.id
                JOIN {user} u
                on cc.userid = u.id
                WHERE c.open_skill = {$id} and cc.timecompleted IS NOT NULL
                ";
                $usercount = $DB->count_records_sql($usercountsql);
                $actions = html_writer::empty_tag('img', array('src' => $OUTPUT->image_url('t/edit'),'title' => get_string('edit'), 'data-action' => 'createrepositorymodal', 'class'=>'createrepositorymodal', 'data-value'=>$id, 'class' => 'iconsmall', 'onclick' =>'(function(e){ require("local_skillrepository/newrepository").init({selector:"createrepositorymodal", contextid:1, repositoryid:'.$c->id.'}) })(event)'));

                $deleteurl = "javascript:void(0)";
                $deleteiconurl = $OUTPUT->image_url('t/delete');
                $deleteicon = html_writer:: empty_tag('img', array('src'=>$deleteiconurl));
                $actions .= ' ';

                $actions .= html_writer:: link($deleteurl, $deleteicon, array('onclick' => '(function(e){ require("local_skillrepository/newrepository").deleteskill({selector:"deleteskill", contextid:1, skillid:'.$c->id.', name:"test"}) })(event)'));

                $skill_catname = $DB->get_field('local_skill_categories', 'name',array('id'=>$c->category));
                if($skill_catname){
                    $skill_catname = $skill_catname;
                }else{
                    $skill_catname = '---';
                }
                $skillurl = new moodle_url('/local/skillrepository/skillinfo.php', array('id'=>$c->id));
                $skilname = html_writer:: link($skillurl, $c->name, array());
                $table->data[] = [$skilname,$usercount, $c->shortname, $skill_catname, $actions];
            }
            $skillstable =  html_writer::table($table);
        } else
            $skillstable = '';
            return $skillstable;
    }


    //Using service.php showing data on index page instead of ajax datatables
    public function manageskills_content($filter = false){
        global $USER;
        $systemcontext =(new \local_skillrepository\lib\accesslib())::get_module_context();
        $options = array('targetID' => 'manage_skills','perPage' => 10, 'cardClass' => 'w_oneintwo', 'viewType' => 'table');

        $options['methodName']='local_skillrepository_manageskills_view';
        $options['templateName']='local_skillrepository/skills_view';
        $options = json_encode($options);

        $dataoptions = json_encode(array('userid' =>$USER->id,'contextid' => $systemcontext->id));
        $filterdata = json_encode(array());

        $context = [
            'targetID' => 'manage_skills',
            'options' => $options,
            'dataoptions' => $dataoptions,
            'filterdata' => $filterdata
        ];

        if($filter){
            return  $context;
        }else{
            return  $this->render_from_template('local_costcenter/cardPaginate', $context);
        }
    }

    // Building Popup Form
    /*
    *  @method popup_window_form to open popup
     */
    public function popup_window_form() {
        global $CFG;

        $popupform = '<div id="dialog_box" style="display:none;">
            <form autocomplete="off" name = "skillcategory" method="post" accept-charset="utf-8" id="popup_form" class="mform" >
                <fieldset class="clearfix collapsible" id="id_displayinfo">
                    <div class="fcontainer clearfix">
                        <div id="fitem_id_name" class="fitem required fitem_ftext ">
                            <div class="fitemtitle">
                                <label for="id_name">Name<img class="req" title="Required field" alt="Required field" src="'.$CFG->wwwroot.'/theme/image.php/lnt/core/1461248966/req"></label>
                            </div>
                            <div class="felement ftext">
                                <span id="id_error_name" class="error" tabindex="0" style="display:none;"> You must supply a value here.</span>
                                <input name="name" type="text" id="id_name">
                             </div>
                           </div>
                        <div id="fitem_id_shortname" class="fitem required fitem_ftext ">
                            <div class="fitemtitle">
                                <label for="id_shortname">Short Name<img class="req" title="Required field" alt="Required field" src="'.$CFG->wwwroot.'/theme/image.php/lnt/core/1461248966/req"> </label>
                            </div>
                            <div class="felement ftext">
                                <span id="id_error_shortname" class="error" tabindex="0" style="display:none;"> You must supply a value here.</span>
                                <input name="shortname" type="text" id="id_shortname">
                            </div>
                        </div>
                            <input id="cat" name="category" type="hidden" class="set_cat">
                            <input name="id" id="id" type="hidden">
                    </div>
                </fieldset>
                <fieldset class="hidden">
                    <div>
                        <div id="fgroup_id_buttonar" class="fitem fitem_actionbuttons fitem_fgroup">
                            <div class="felement fgroup">
                                <input name="submitbutton" value="Submit" type="button" id="id_submitbutton" onclick="addSkillCategory();">
                            </div>
                        </div>
                            <div class="fdescription required">There are required fields in this form marked <img alt="Required field" src="'.$CFG->wwwroot.'/theme/image.php/lnt/core/1461248966/req">.</div>
                    </div>
                </fieldset>
            </form>
        </div>';

        return $popupform;
    }


/*
 *  @method view_skill_categories to show skill categories
 *  @return skill categories table
 */
    public function view_skill_categories(){
        global $DB, $OUTPUT,$USER;

        $systemcontext =(new \local_skillrepository\lib\accesslib())::get_module_context();
        if(!is_siteadmin()){
        $concatsql = (new \local_skillrepository\lib\accesslib())::get_costcenter_path_field_concatsql($columnname='lsc.open_path');
            $skill_categories = $DB->get_records_sql("SELECT lsc.* from {local_skill_categories} AS lsc where 1 $concatsql order by lsc.id desc");
            $head = [];
        } else{
            $skill_categories = $DB->get_records_sql("SELECT lsc.*,lc.fullname as orginsationname from {local_skill_categories} AS lsc JOIN {local_costcenter} AS lc ON lc.id = lsc.costcenterid order by lsc.id desc");
            $isadmin = true;
            $head = [get_string('open_costcenterid', 'local_costcenter')];
        }
        if($skill_categories){
            $data = array();
            foreach($skill_categories as $skill_category){
                $row = array();
                if($isadmin){
                    $row[] = $skill_category->orginsationname;
                }
                $row[] = $skill_category->name;
                $row[] = $skill_category->shortname;
                $editurl = "javascript:void(0)";
                $editiconurl = $OUTPUT->image_url('t/editinline');
                $editicon = html_writer:: empty_tag('img', array('src'=>$editiconurl));
                $actions = html_writer:: link($editurl,  $editicon,  array('title'=>get_string('edit','local_skillrepository'),'onclick' => '(function(e){ require("local_skillrepository/newcategory").init({selector:"createcategorymodal", contextid:'.$systemcontext->id.', categoryid:'.$skill_category->id.'}) })(event)'));

                $deleteurl = "javascript:void(0)";
                $deleteiconurl = $OUTPUT->image_url('i/trash');

                $deleteicon = html_writer:: empty_tag('img', array('src'=>$deleteiconurl));
                $actions .= ' ';

                $actions .= html_writer:: link($deleteurl, $deleteicon, array('title'=>get_string('delete','local_skillrepository'),'onclick' => '(function(e){ require("local_skillrepository/newcategory").deletecategory({selector:"deletecategory", contextid:'.$systemcontext->id.', categoryid:'.$skill_category->id.', name:"'.$skill_category->name.'"}) })(event)'));

                $row[] =  $actions;

                $data[] = $row;
            }

            $table = new html_table();
            $table->id = 'skill_categories';
            $table->width = '100%';
            $table->align = array('left','center','center','center');
            $head = array_merge($head, array(get_string('fullname'), get_string('shortname', 'local_skillrepository'), get_string('actions')));
            $table->head = $head;
            $table->data = $data;
            $skill_categoriesview =  html_writer::table($table);
        }else{
            $skill_categoriesview = html_writer::tag('div', get_string('nocompetenciesavailable', 'local_skillrepository'),array('class'=>'emptymsg'));
        }

        return $skill_categoriesview;
    }
/*
 *  @method get skill info
 *  @param [integer] $skillid
 *  @return skill data
 */
    public function get_skill_info($skillid){
        global $DB, $USER;
        $skillrecord = $DB->get_record('local_skill', array('id'=>$skillid));
        $subskill_category = $DB->get_record('local_skill_categories', array('id'=>$skillrecord->category));
        $parent_skill_category = $DB->get_record('local_skill_categories', array('id'=>$subskill_category->parentid));
        $skilldata = '';
        $skilldata .= html_writer:: tag('h2', $skillrecord->name);
        $skilldata .= '<div class="skill_addinfo">';
        $skilldata .= html_writer:: start_tag('table', array('id'=>'skilldetails'));
        $skilldata .= html_writer:: start_tag('tr', array());
        $skilldata .= html_writer:: tag('td', get_string('skill_name', 'local_skillrepository').': <b>'.$skillrecord->name.'</b>', array());
        $skilldata .= html_writer:: tag('td', get_string('shortname', 'local_skillrepository').': <b>'.$skillrecord->shortname.'</b>', array());
        $skilldata .= html_writer:: end_tag('tr');
        $skilldata .= html_writer:: start_tag('tr', array());
        // $skilldata .= html_writer:: tag('td', get_string('category').': <b>'.$subskill_category->name.'</b>', array());
        $skilldata .= html_writer:: end_tag('tr');
        $skilldata .= html_writer:: end_tag('table');
        if(!empty($skillrecord->description)){
            $skilldata .= html_writer:: tag('div', $skillrecord->description, array('class'=>'skill_descr'));
        }
        $skilldata .= '</div>';

        $sql = "SELECT c.id, c.fullname, c.open_skill from
                {course} c
                where c.open_coursetype = 0 AND c.open_skill = $skillid";

        $skill_courses = $DB->get_records_sql($sql);

        if($skill_courses){
            $sk_courses = array();
            foreach($skill_courses as $skill_course){
                $sk_courses[] = $skill_course->fullname;
            }
            $skill_courses = implode(', ', $sk_courses);
        }else{
            $skill_courses = "<span class = 'noskillcoursesmsg'>".get_string('nodata', 'local_skillrepository')."</span>";
        }
        $skilldata .= "<div class='skillcourses'><b>".get_string('skill_courses', 'local_skillrepository').': </b>'.$skill_courses."</div>";
        $userpathconcatsql = (new \local_costcenter\lib\accesslib())::get_costcenter_path_field_concatsql($columnname='u.open_path');
        $costcenterpathconcatsql = (new \local_costcenter\lib\accesslib())::get_costcenter_path_field_concatsql($columnname='c.open_path');
        $sql = "SELECT u.id, u.open_employeeid, c.fullname, u.firstname, u.lastname, cc.timecompleted
            FROM {course} c
            JOIN {course_completions} cc
            on cc.course = c.id
            JOIN {user} u
            on cc.userid = u.id
            WHERE c.open_skill = $skillid and cc.timecompleted IS NOT NULL $userpathconcatsql $costcenterpathconcatsql";

        $skill_compl_courses = $DB->get_records_sql($sql);

        $skilldata .= html_writer::tag('h4', get_string('achievedusercount', 'local_skillrepository'));

        $data = array();
        if($skill_compl_courses){
            foreach($skill_compl_courses as $skill_compl_course){
                $row = array();
                $row[] = $skill_compl_course->firstname.' '.$skill_compl_course->lastname;
                $row[] = $skill_compl_course->open_employeeid;
                $row[] = $skill_compl_course->fullname;
                $completeddate = date('d M Y', $skill_compl_course->timecompleted);
                $row[] = $completeddate;
                $data[] = $row;
            }
            $table = new html_table();
            $table->id = 'additionalinfo';
            $table->head = array(get_string('employeename', 'local_skillrepository'), get_string('employeeid', 'local_skillrepository'), get_string('course'), 'Date Acquired');
            $table->data = $data;
            $skilldata .= html_writer::table($table);
        }else{
            $skilldata .= html_writer::tag('div', get_string('norecords', 'local_skillrepository'),array('class'=>'emptymsg'));
        }

        return $skilldata;
    }
    public function get_top_action_buttons_skills(){
        global $CFG;
        $advance = get_config('local_skillrepository','advance');

        $systemcontext =(new \local_skillrepository\lib\accesslib())::get_module_context();
        if($advance){
        $data =  "<ul class='course_extended_menu_list'>
        
                <li>
                <div class='coursebackup course_extended_menu_itemcontainer'>
                    <a id='extended_menu_syncerrors' title='".get_string('manage_domains', 'local_positions')."' class='course_extended_menu_itemlink' href='" . $CFG->wwwroot ."/local/positions/domains.php'><i class='icon fa fa-server' aria-hidden='true' aria-label=''></i>
                    </a>
                </div>
                </li>
                <li>
                <div class='coursebackup course_extended_menu_itemcontainer'>
                    <a id='extended_menu_syncerrors' title='".get_string('competency_matrix', 'local_skillrepository')."' class='course_extended_menu_itemlink' href='" . $CFG->wwwroot ."/local/skillrepository/skillmatrix.php'><i class='icon fa fa-cogs fa-fw' aria-hidden='true' aria-label=''></i>
                    </a>
                </div>
                </li>
                <li>
                    <div class='coursebackup course_extended_menu_itemcontainer'>
                        <a id='extended_menu_syncerrors' title='".get_string('managelevel', 'local_skillrepository')."' class='course_extended_menu_itemlink' href='" . $CFG->wwwroot ."/local/skillrepository/level.php'><i class='icon fa fa-list-alt' aria-hidden='true' aria-label=''></i>
                        </a>
                    </div>
                </li>
                <li>
                    <div class='coursebackup course_extended_menu_itemcontainer'>
                    <a id='extended_menu_syncusers' title='".get_string('manage_skill', 'local_skillrepository')."' class='course_extended_menu_itemlink' href='" . $CFG->wwwroot ."/local/skillrepository/index.php'><i class='icon fa fa-graduation-cap' aria-hidden='true' aria-label=''></i>
                    </a>
                    </div>             
                </li>
                <li>
                <div class='coursebackup course_extended_menu_itemcontainer'>
                <a id='extended_menu_syncstats' title='".get_string('create_skillcategory', 'local_skillrepository')."' class='course_extended_menu_itemlink' href='javascript:void(0)' onclick ='(function(e){ require(\"local_skillrepository/newcategory\").init({selector:\"createcategorymodal\", contextid:$systemcontext->id, categoryid:0}) })(event)'><i class='icon fa fa-plus' aria-hidden='true' aria-label=''></i></a>
                </div>              
             </li>
            </ul>";
        }
        return $data;
    }
    //Levels related functions
    public function display_levels_tablestructure(){
        $table = new \html_table();
        $table->id = 'all_levels_display_table';
        if(is_siteadmin()){
            $head = [get_string('open_costcenterid', 'local_costcenter')];
        }else{
            $head = [];
        }
        $table->head = array_merge($head, array(get_string('levelname', 'local_skillrepository'),get_string('levelcode', 'local_skillrepository'), get_string('createdby', 'local_skillrepository'),get_string('actions')));
        $table = \html_writer::table($table);
        return $table;
    }
    public function display_levels_tabledata($params){
        global $CFG;
        $querylib = new \local_skillrepository\local\querylib();
        $displaydata = $querylib->get_table_contents($params);
        $tabledata = array();
        foreach($displaydata as $level){
            $actions = '';
            $canedit = $querylib->can_edit_level($level->id);
            if($canedit){
                $systemcontext =(new \local_skillrepository\lib\accesslib())::get_module_context();
                $editicon = "<i class='fa fa-pencil fa-fw'></i>";
                $actions .= \html_writer::link('javascript:void(0)', $editicon, array('title'=>get_string('edit','local_skillrepository'),'onclick' => '(function(e){ require("local_skillrepository/leveltable").init({ contextid:'.$systemcontext->id.',levelid: '.$level->id.', levelname: "'.$level->name.'"}) })(event)'));
            }
            $candelete = $querylib->can_delete_level($level->id);
            if($candelete){
                $deleteicon ="<i class='fa fa-trash fa-fw'></i>";
                $actions .= \html_writer::link('javascript:void(0)', $deleteicon, array('title'=>get_string('delete','local_skillrepository'),'onclick' => '(function(e){ require("local_skillrepository/leveltable").deletelevel({levelid: '.$level->id.', levelname: "'.$level->name.'"}) })(event)'));
            }

            $data = array();
            if(is_siteadmin()){
                $data[] = $level->organisationname;
            }
            $data[] = $level->name;
            $data[] = $level->code;
            $data[] = $level->username;
            $data[] = $actions;
            $tabledata[] = $data;
         }
         return $tabledata;
    }

    public function manageskillslevel_content($filter = false){
        global $USER;
        $systemcontext =(new \local_skillrepository\lib\accesslib())::get_module_context();
        $options = array('targetID' => 'manage_skills_level','perPage' => 10, 'cardClass' => 'w_oneintwo', 'viewType' => 'table');

        $options['methodName']='local_skillrepository_manageskillslevel_view';
        $options['templateName']='local_skillrepository/skills_level_view';
        $options = json_encode($options);

        $dataoptions = json_encode(array('userid' =>$USER->id,'contextid' => $systemcontext->id));
        $filterdata = json_encode(array());

        $context = [
            'targetID' => 'manage_skills_level',
            'options' => $options,
            'dataoptions' => $dataoptions,
            'filterdata' => $filterdata
        ];

        if($filter){
            return  $context;
        }else{
            return  $this->render_from_template('local_costcenter/cardPaginate', $context);
        }
    }

    public function manageskillscategory_content($filter = false){
        global $USER;
        $systemcontext =(new \local_skillrepository\lib\accesslib())::get_module_context();
        $options = array('targetID' => 'manage_skills_category','perPage' => 10, 'cardClass' => 'w_oneintwo', 'viewType' => 'table');

        $options['methodName']='local_skillrepository_manageskillscategory_view';
        $options['templateName']='local_skillrepository/skills_cat_view';
        $options = json_encode($options);

        $dataoptions = json_encode(array('userid' =>$USER->id,'contextid' => $systemcontext->id));
        $filterdata = json_encode(array());

        $context = [
            'targetID' => 'manage_skills_category',
            'options' => $options,
            'dataoptions' => $dataoptions,
            'filterdata' => $filterdata
        ];

        if($filter){
            return  $context;
        }else{
            return  $this->render_from_template('local_costcenter/cardPaginate', $context);
        }
    }
       /**
     * @method treeview
     * @todo To add action buttons
     */
    public function competency_view() {
        global $DB, $CFG, $OUTPUT, $USER,$PAGE;
        $systemcontext = (new \local_skillrepository\lib\accesslib())::get_module_context();
        $costcenterpathconcatsql = (new \local_costcenter\lib\accesslib())::get_costcenter_path_field_concatsql($columnname='open_path');
        if (is_siteadmin() || has_capability('local/costcenter:manage_multiorganizations', $systemcontext)) {
           $skill_categories = $DB->get_records_sql("select * from {local_skill_categories} order by id desc");
        } else {
            $skill_categories = $DB->get_records_sql("select * from {local_skill_categories} where 1=1 $costcenterpathconcatsql order by id desc");
        } 
        $data = array();
        if(!empty($skill_categories)){
            foreach ($skill_categories as $skill_category) {
                $line = array();
                $showdepth = 1;
                $line[] = $this->competency_data_item($skill_category);
                $data[] = $line;
            }
            $table = new html_table();
            if (has_capability('local/costcenter:manage', $systemcontext)){
                $table->head = array('');
                $table->align = array('left');
                $table->width = '100%';
                $table->data = $data;
                $table->id = 'department-index';
                $output = html_writer::table($table);
            }
        }else{
            $output = html_writer::tag('div', get_string('nocompetenciesavailable', 'local_skillrepository'), array('class'=>'alert alert-info text-center'));
        }
        return $output;
    }
    public function competency_data_item($skill_category) {
        global $OUTPUT, $DB, $CFG, $PAGE;
        $systemcontext = (new \local_skillrepository\lib\accesslib())::get_module_context();
        $costcenterpathconcatsql = (new \local_costcenter\lib\accesslib())::get_costcenter_path_field_concatsql($columnname='c.open_path');
        $costcenterid=explode('/',$skill_category->open_path)[1];
        //echo $costcenterid;exit;
        $costcenter = $DB->get_record('local_costcenter', array('id'=>$costcenterid));
		$competencies_row = array();
		$competencies_row['competency_name'] = $skill_category->name;
		$competencies_row['competency_id'] = $skill_category->id;
		$competencies_row['org_name'] = $costcenter->fullname;
		$editurl = "javascript:void(0)";
		$editicon = '<i class="fa fa-pencil fa-fw" title="Edit"></i>';
		$competency_edit = html_writer:: link($editurl,  $editicon.get_string('edit','local_skillrepository'),  array('title'=>get_string('edit','local_skillrepository'),'onclick' => '(function(e){ require("local_skillrepository/newcategory").init({selector:"createcategorymodal", contextid:'.$systemcontext->id.', categoryid:'.$skill_category->id.'}) })(event)','class'=>'dropdown-item'));

		$competencies_row['competency_edit'] = $competency_edit;
		$levelicon = '<i class="icon fa fa-users fa-fw" title="Assign Skill"></i>';
		$assigncourseicon = self::getAssignCourseIcon();
        $comp_level=0;
		//die();
        // $assignlevel = html_writer:: link($editurl,  $levelicon,  array('title'=>get_string('assignlevel','local_skillrepository'),'onclick' => '(function(e){ require("local_skillrepository/newassigncompetencylevel").init({selector:"createcompetencylevel", contextid:'.$systemcontext->id.', repositoryid:'.$skill_category->id.', costcenterid:'.$costcenterid.'}) })(event)'));
		// $competencies_row['assignlevel'] = $assignlevel;
        $assignskill = html_writer:: link($editurl,  $levelicon.get_string('assignskill','local_skillrepository'),  array('title'=>get_string('assignskill','local_skillrepository'),'onclick' => '(function(e){ require("local_skillrepository/newassignskill").init({selector:"createcompetencyskill", contextid:'.$systemcontext->id.', repositoryid:'.$skill_category->id.', costcenterid:'.$costcenterid.', complevelid:'.$comp_level.'}) })(event)','class'=>'dropdown-item'));
       
        $competencies_row['comp_level_assignskill'] = $assignskill;
		
		$deleteurl = "javascript:void(0)";
		$deleteicon = '<i class="fa fa-trash fa-fw" aria-hidden="true" title="Delete" aria-label="Delete"></i>';
		$actions .= ' ';
		$skills = $DB->get_records('local_comp_skill_mapping', array('competencyid'=>$skill_category->id));
		if(empty($skills)){
			$competency_delete = html_writer:: link($deleteurl, $deleteicon.get_string('delete','local_skillrepository'), array('title'=>get_string('delete','local_skillrepository'),'onclick' => '(function(e){ require("local_skillrepository/newcategory").deletecategory({selector:"deletecategory", contextid:'.$systemcontext->id.', categoryid:'.$skill_category->id.', name:"'.$skill_category->name.'"}) })(event)','class'=>'dropdown-item'));
		} else {

			$delete_reason = get_string('deletecompitency_reason','local_skillrepository');
			$competency_delete = html_writer:: link($deleteurl, $deleteicon.get_string('delete','local_skillrepository'), array('title'=>get_string('delete','local_skillrepository'),'onclick' => '(function(e){ require("local_skillrepository/newcategory").reasonfor_unabletodelete({reason: "'.$delete_reason.'" })})(event)','class'=>'dropdown-item'));
		}
		$competencies_row['competency_delete'] = $competency_delete;
		$competencies_data[] = $competencies_row;
		//Competency level data
		// $sql = "SELECT cl.*, cls.competencyid,cls.levelid FROM {local_course_levels} cl JOIN {local_competency_levels} cls ON cls.levelid=cl.id WHERE competencyid={$skill_category->id}";
		// $comp_levels = $DB->get_records_sql($sql);
		// foreach ($comp_levels as $comp_level) {
		// 	$competency_levels_row=array();
		// 	$competency_levels_row['comp_level_name']=$comp_level->name;
		// 	$competency_levels_row['comp_level_id']=$comp_level->id;
			$competency_levels_row['globalCostcenterid']=$costcenterid;
		// 	$skillicon = '<i class="fa fa-cogs"></i>';
		// 	$assignskill = html_writer:: link($editurl,  $skillicon,  array('title'=>get_string('assignskill','local_skillrepository'),'onclick' => '(function(e){ require("local_skillrepository/newassignskill").init({selector:"createcompetencyskill", contextid:'.$systemcontext->id.', repositoryid:'.$skill_category->id.', costcenterid:'.$skill_category->costcenterid.', complevelid:'.$comp_level->id.'}) })(event)'));
		// 	$competency_levels_row['comp_level_assignskill']=$assignskill;
		// 	$competency_level_skills = array();
		// 	// if($comp_level->skillid) {
				$sql = "SELECT s.*,sm.skilllevelid FROM {local_skill} as s JOIN {local_comp_skill_mapping} as sm ON sm.skillid=s.id WHERE sm.costcenterid=$costcenterid and competencyid={$skill_category->id}";
				$comp_level_skills = $DB->get_records_sql($sql);

				foreach ($comp_level_skills as $comp_level_skills) {
					// $skilllevelnameslistsql = "SELECT cl.id,cl.name FROM {local_course_levels} cl JOIN {local_comp_skill_mapping} cls ON cls.skilllevelid=cl.id WHERE cls.skillid={$comp_level_skills->id}";
					// $skilllevelnameslist=$DB->get_records_sql_menu($skilllevelnameslistsql);
					// $skilllevelnames = implode(', ', $skilllevelnameslist);
					$competency_level_skills_row = array();
					$competency_level_skills_row['comp_level_skill_name']=$comp_level_skills->name;
					$competency_level_skills_row['comp_level_skill_id']=$comp_level_skills->id;
					$competency_level_skills_row['comp_level_skill_levelid']=$comp_level_skills->skilllevelid;
					$competency_level_skills_row['comp_level_skill_levelname']=$skilllevelnames;
                    $levelicon = '<i class="icon fa fa-users fa-fw" title="Assign Level"></i>';
					$assignskilllevel = html_writer:: link($editurl,  $levelicon,  array('title'=>get_string('assignlevel','local_skillrepository'),'onclick' => '(function(e){ require("local_skillrepository/newassignlevel").init({selector:"createassignlevel", contextid:'.$systemcontext->id.', repositoryid:'.$comp_level_skills->id.', costcenterid:'.$costcenterid.', competencyid:'.$skill_category->id.'}) })(event)'));
					$competency_level_skills_row['comp_level_skill_assignlevel']=$assignskilllevel;
					//Skill levels
					$sql = "SELECT cl.*, sl.skillid FROM {local_course_levels} cl JOIN {local_skill_levels} sl ON sl.levelid=cl.id WHERE sl.skillid = {$comp_level_skills->id} AND sl.competencyid = {$skill_category->id}";
					$comp_level_skill_levels = $DB->get_records_sql($sql);
			        $competency_level_skill_levels = array();
					if($comp_level_skill_levels) {
						foreach ($comp_level_skill_levels as $comp_level_skill_level) {
							$comp_level_skill_level_row = array();
							 $comp_level_skill_level_row['comp_level_skill_level_name']=$comp_level_skill_level->name;
							$comp_level_skill_level_row['comp_level_skill_level_id']=$comp_level_skill_level->id;
							$assignskill_level_course = html_writer:: link($editurl,  $assigncourseicon,  array('title'=>get_string('assigncourse','local_skillrepository'),'onclick' => '(function(e){ require("local_skillrepository/newassigncourse").init({selector:"createassigncourse", contextid:'.$systemcontext->id.', repositoryid:'.$comp_level_skills->id.', org_id:'.$costcenterid.', levelid: '.$comp_level_skill_level->id.'}) })(event)'));
							$comp_level_skill_level_row['comp_level_skill_assignskill_level_course']=$assignskill_level_course;
							$course = $DB->get_records('course',array('open_skill'=>$comp_level_skills->id, 'open_level'=>$comp_level_skill_level->id));
							html_writer:: link('javascript:void(0)',  count($course),  array('class'=>'createcoursemodal course_count_popup','onclick' => '(function(e){ require("local_skillrepository/newassigncourse").courselist({contextid:'.$systemcontext->id.', skillid:'.$comp_level_skills->id.',levelid:'.$comp_level_skill_level->id.', costcenterid:'.$costcenterid.'}) })(event)'));
							$courses = html_writer:: link('javascript:void(0)',  count($course),  array('class'=>'createcoursemodal course_count_popup','onclick' => '(function(e){ require("local_skillrepository/newassigncourse").getCourselist({contextid:'.$systemcontext->id.', skillid:'.$comp_level_skills->id.',levelid:'.$comp_level_skill_level->id.', costcenterid:'.$costcenterid.'}) })(event)'));
							$comp_level_skill_level_row['skill_level_courses_count']=$courses;
                            $cost = (explode("/",$comp_level_skill_level->open_path));
                            $removeicon = html_writer::tag('i', '', array('class' => 'fa fa-times'));
                            $comp_level_skill_level_row['skill_level_remove']= html_writer::link('javascript:void(0)', $removeicon, array('class' => 'removelevelSkill', 'title' => get_string('purgelevel', 'local_skillrepository'), 'data-skillid' => $comp_level_skill_level->skillid, 'data-costcenterid' => $cost[1], 'data-levelid' => $comp_level_skill_level->id, 'data-levelname' => $comp_level_skill_level->name,'data-competencyid' => $skill_category->id));

							$competency_level_skill_levels[] = $comp_level_skill_level_row;
						}
					}
					$competency_level_skills_row['skill_levels']=$competency_level_skill_levels;
					$competency_level_skills[] = $competency_level_skills_row;
				}
			// }
			$competency_levels_row['competency_level_skills']=$competency_level_skills;
			$competency_levels[] = $competency_levels_row;
		
		
		// $removeCompetencyLevel = html_writer::tag('i', '', array('class' => 'fa fa-times', 'title' => get_string('purgecompetencylevel', 'local_skillrepository')));
    
        $viewdeptContext = [
            "competencies" => $competencies_data,
            "competency_levels" => $competency_levels,
            // "removeCompetencyLevel" => $removeCompetencyLevel,
        ];
        $viewdeptContext = $viewdeptContext;//+$pluginnavs;
        return $this->render_from_template('local_skillrepository/competency_view', $viewdeptContext);
    }
    public static function getAssignCourseIcon(){
    	return '<svg version="1.1" id="Layer_1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" x="0px" y="0px"	 viewBox="0 0 200 200" style="enable-background:new 0 0 200 200; width:30px; fill:#2d70a7;" xml:space="preserve"><path d="M27.66,110.86c0.45-0.85-0.16-1.87-1.12-1.87c-4.97,0-9.55-0.01-14.13,0.01c-1.71,0.01-3.73,0.02-3.65-2.15	c0.03-0.9,2-1.68,3.01-2.6c1.98-1.81,4.49-3.35,5.75-5.59c2.81-4.97,3.02-10.54,2.35-16.18c-0.32-2.69-0.36-5.41-0.51-8.29	c-0.02-0.47-0.31-0.9-0.75-1.09c-2.52-1.1-5.58-2.38-8.54-3.87c-0.7-0.35-1.56-1.94-1.31-2.34c0.53-0.88,1.69-1.86,2.64-1.91	c3.89-0.21,7.8-0.09,11.71-0.09c4.95,0,9.89,0,15.07,0c0.96,0,1.56-1.03,1.1-1.86l0,0c-4.97-8.95-5.17-18.26-1.72-27.66	c2.64-7.21,7.1-9.99,14.82-9.98c36.68,0,73.35,0.01,110.03,0.01c0.92,0,1.96-0.22,2.74,0.12c0.82,0.35,1.86,1.21,1.95,1.95	c0.08,0.73-0.79,2.15-1.45,2.29c-7.55,1.6-8.66,7.79-9.7,13.76c-0.91,5.23-0.27,10.47,2.1,15.33c1.47,3.02,3.35,5.59,7.11,6.19	c0.54,0.09,0.98,0.83,1.42,1.56c0.32,0.55,0.2,1.24-0.3,1.64c-0.71,0.57-1.43,1.16-2.15,1.17c-8.11,0.15-16.23,0.09-24.35,0.09	c-0.59,0-1.17,0-1.73,0c-0.84,0-1.45,0.81-1.21,1.62c1.03,3.49,2.03,6.88,2.92,10.31c0.01,0.04-0.07,0.11-0.21,0.18	c-0.45,0.26-0.3,0.96,0.23,0.99l0.15,0.01c0.27,0.02,0.49,0.25,0.5,0.52c0.04,1.91,0.26,3.74-0.12,5.44	c-0.15,0.68-0.72,1.27-1.33,1.85c-0.58,0.55-1.51,0.46-1.94-0.21c-0.36-0.55-0.69-1.11-0.84-1.72c-1.15-4.62-1.76-9.41-3.3-13.89	c-1.26-3.66-4.35-5.32-8.53-5.19c-7.49,0.24-15,0.07-22.5,0.07c-0.62,0-1.38,0.23-1.82-0.05c-0.65-0.4-1.24-0.9-1.83-1.41	c-0.57-0.49-0.59-1.37-0.03-1.89c0.55-0.51,1.1-0.98,1.74-1.17c1.22-0.37,2.64-0.09,3.98-0.09c16.13,0,32.26,0,48.39,0	c0.48,0,0.95,0,1.46,0c0.94,0,1.55-0.99,1.13-1.83c-5.2-10.5-5.25-20.71-0.02-31.13c0.41-0.82-0.15-1.79-1.06-1.84	c-0.49-0.03-0.97-0.05-1.44-0.05c-33.8-0.01-67.6,0.03-101.4-0.06c-3.67-0.01-6.18,1.43-7.78,4.46c-4.55,8.6-4.53,17.32-0.06,25.94	c1.63,3.15,4.26,4.94,8.04,4.53c0.81-0.09,1.82-0.27,2.42,0.1c0.83,0.53,1.77,1.49,1.87,2.36c0.07,0.62-1.05,1.64-1.86,2.01	c-0.77,0.36-1.81,0.12-2.74,0.12c-9.59,0-19.18,0-28.42,0c-0.84,0-1.44,0.8-1.21,1.61c1.2,4.19,2.53,8.3,3.34,12.5	c0.8,4.12,0.02,8.16-2.28,11.9c-0.46,0.76-2.84,2.4-0.19,3.92c0.14,0.08-0.54,1.57-1.23,3.12c-0.37,0.84,0.25,1.78,1.16,1.78	c4.36,0,8.59,0,12.82,0c4.31,0,8.62-0.01,12.93-0.01c18.29,0,36.57,0,54.86,0c0.51,0,1.03,0.01,1.54,0.01	c1.77-0.01,3.51,0.23,3.39,2.52c-0.11,2.1-1.8,2.14-3.42,2.14c-15.31-0.01-30.62-0.01-45.92,0c-6.37,0-12.74,0.1-19.11-0.04	c-3.25-0.07-5.46,1.35-7.2,3.89c-4.76,6.93-4.82,19.98-0.13,27.02c1.77,2.67,4.01,4.02,7.42,4c23.32-0.11,46.64-0.06,69.96-0.06	c0.92,0,2.02-0.3,2.73,0.1c0.64,0.36,1.17,0.94,1.68,1.54c0.45,0.52,0.41,1.31-0.11,1.77c-0.52,0.47-1.05,0.92-1.66,1.21	c-0.67,0.32-1.61,0.07-2.44,0.07c-23.22,0-46.44-0.11-69.65,0.08c-6.18,0.05-10.3-2.64-12.77-7.86c-4.68-9.89-4.55-19.83,0.55-29.61	C27.58,111.03,27.62,110.95,27.66,110.86z"/><path d="M168.34,152.57c4.99,7.13,9.69,13.85,14.38,20.57c2.29,3.29,2.13,4.16-1.12,6.48c-3.18,2.27-6.35,4.54-9.53,6.79	c-3.24,2.29-3.98,2.18-6.21-0.98c-4.2-5.96-8.36-11.95-12.55-17.91c-0.58-0.83-1.21-1.61-2.05-2.72	c-3.59,4.07-6.95,8.04-10.51,11.81c-1.01,1.07-2.82,2.37-3.91,2.1c-1.05-0.26-2.07-2.3-2.37-3.72	c-4.61-21.89-9.12-43.79-13.54-65.72c-0.27-1.33-0.02-3.73,0.72-4.08c1.14-0.54,3.15-0.14,4.37,0.58	c19.28,11.36,38.51,22.8,57.68,34.33c1.22,0.74,2.57,2.32,2.63,3.57c0.05,0.88-1.84,2.19-3.11,2.75	C178.44,148.54,173.57,150.43,168.34,152.57z M126.94,112.44c4.09,19.83,7.98,38.72,12,58.22c3.71-4.2,6.86-7.84,10.1-11.39	c1.99-2.18,3.5-2.08,5.23,0.34c3.29,4.59,6.48,9.25,9.72,13.87c1.86,2.66,3.73,5.31,5.69,8.1c2.94-2.09,5.58-3.97,8.3-5.9	c-4.92-7.04-9.62-13.77-14.32-20.51c-3.17-4.55-2.98-5.2,2.06-7.27c3.91-1.6,7.8-3.23,12.26-5.08	C160.8,132.59,144.25,122.74,126.94,112.44z"/><path d="M66.88,90.5c-0.51,0.74-0.87,1.78-1.58,2.13c-0.8,0.4-1.91,0.18-2.88,0.61c0.5,0.05,1.01,0.1,1.51,0.16	c0.37,1.93,0.35,1.81,0.72,3.74c-0.54,0.29-1.1,0.53-1.67,0.67c-2.11,0.51-2.64-1.36-2.64-3.19c0-14.45,0-28.91-0.01-43.36	c0,0,0-0.01,0-0.01c-0.01-0.75-0.67-1.34-1.42-1.32c-0.67,0.02-1.31,0.01-1.84-0.23c-0.58-0.26-1.07-0.73-1.55-1.22	c-0.5-0.52-0.53-1.34-0.05-1.88c0.55-0.63,1.11-1.24,1.72-1.31c2.74-0.31,5.54-0.12,8.31-0.12c9.76,0,19.52-0.06,29.28,0.11	c0.62,0.01,1.23,0.59,1.85,1.21c0.57,0.58,0.52,1.53-0.12,2.05c-0.51,0.42-1.03,0.83-1.58,1.21c-0.28,0.19-0.8,0.03-1.21,0.05	c-0.13,0.01-0.27,0.02-0.41,0.03c-0.71,0.07-1.26,0.67-1.26,1.38c0,0.67,0,1.33,0,2c0,13.65,0.04,27.29-0.04,40.94	c0,0.77-0.35,1.53-0.72,2.3c-0.32,0.68-1.13,0.98-1.82,0.67c-0.49-0.22-0.97-0.43-1.46-0.65c0,0-0.08,0.08-0.08,0.08	c-0.19-3.58-0.53-7.15-0.55-10.73c-0.07-10.88-0.01-21.75-0.02-32.63c0-0.61-0.03-1.23-0.06-1.82c-0.04-0.74-0.65-1.32-1.39-1.32	H66.47c-0.77,0-1.39,0.63-1.39,1.39c0,12.82,0.02,25.38-0.03,37.94c-0.01,1.66,0.8,1.57,1.91,1.28L66.88,90.5z"/><path d="M113.66,81.72c-2.04,0-4.13,0.25-6.11-0.1c-0.17-0.03-0.34-0.09-0.5-0.18c-1.58-0.82-1.57-3.16-0.02-4.05	c0.23-0.14,0.47-0.22,0.71-0.24c3.96-0.26,7.96-0.24,11.93,0c0.28,0.02,0.55,0.14,0.82,0.33c1.27,0.89,1.28,2.82,0.04,3.75	c-0.25,0.19-0.5,0.32-0.77,0.37c-1.97,0.37-4.06,0.11-6.1,0.11C113.66,81.72,113.66,81.72,113.66,81.72z"/><path d="M63.68,130.74c-3.25,0-6.57,0.25-9.71-0.1c-0.27-0.03-0.53-0.09-0.79-0.18c-2.52-0.82-2.49-3.16-0.04-4.05	c0.37-0.14,0.75-0.22,1.13-0.24c6.3-0.26,12.67-0.24,18.97,0c0.44,0.02,0.87,0.14,1.3,0.33c2.02,0.89,2.04,2.82,0.07,3.75	c-0.39,0.19-0.8,0.32-1.23,0.37c-3.14,0.37-6.46,0.11-9.71,0.11C63.68,130.73,63.68,130.74,63.68,130.74z"/><path d="M36.91,69.23c-4.82,0-9.75,0.25-14.42-0.1c-0.4-0.03-0.79-0.09-1.17-0.17c-3.74-0.82-3.7-3.13-0.06-4.02	c0.55-0.14,1.11-0.22,1.68-0.24c9.36-0.26,18.81-0.24,28.18,0c0.66,0.02,1.3,0.14,1.93,0.33c3,0.89,3.03,2.8,0.1,3.72	c-0.58,0.18-1.18,0.32-1.82,0.37c-4.66,0.36-9.6,0.11-14.42,0.11C36.91,69.22,36.91,69.22,36.91,69.23z"/><path d="M115.42,69.06c-4.82,0-9.75,0.24-14.42-0.1c-0.4-0.03-0.79-0.09-1.17-0.17c-3.74-0.79-3.7-3.02-0.06-3.88	c0.55-0.13,1.11-0.21,1.68-0.23c9.36-0.25,18.81-0.23,28.18,0c0.66,0.02,1.3,0.14,1.93,0.32c3,0.85,3.03,2.7,0.1,3.59	c-0.58,0.18-1.18,0.31-1.82,0.35C125.18,69.3,120.24,69.05,115.42,69.06C115.42,69.05,115.42,69.06,115.42,69.06z"/><path d="M83.02,93.7c-1.54-0.75-3.21-1.31-4.59-2.29c-1.91-1.35-3.45-1.27-5.19,0.25c-1.2,1.05-2.66,1.8-4.07,2.73	c-0.86-1.47-1.58-2.68-2.29-3.89c0,0,0.09,0.16,0.09,0.16c2.6-1.74,5.16-3.56,7.84-5.17c0.65-0.39,1.97-0.47,2.54-0.07	c2.55,1.77,6.43,2.67,5.36,7.19c-0.08,0.35,0.16,0.78,0.26,1.18L83.02,93.7z"/><path d="M137.11,58.9c-1.54,0-3.15,0.29-4.58-0.1c-0.18-0.05-0.35-0.13-0.51-0.24c-1.39-0.95-1.39-3.09,0.03-3.98	c0.18-0.11,0.36-0.19,0.55-0.2c2.94-0.27,5.94-0.28,8.89-0.01c0.26,0.02,0.51,0.15,0.75,0.33c1.19,0.9,1.25,2.72,0.12,3.7	c-0.21,0.18-0.44,0.32-0.68,0.39c-1.42,0.4-3.04,0.1-4.58,0.1C137.11,58.89,137.11,58.89,137.11,58.9z"/><path d="M87.94,96.54c0,0,0.08-0.08,0.08-0.08c-1.67-0.92-3.33-1.84-5-2.76c0,0-0.04,0.09-0.04,0.09c0.23-1.37,0.45-2.74,0.7-4.24	c2.02,0.64,3.52,1.41,3.31,3.94C86.92,94.47,87.61,95.52,87.94,96.54z"/><path d="M161.25,102.91L161.25,102.91c-1.11,0-2-0.9-2-2V85.18c0-1.11,0.9-2,2-2l0,0c1.11,0,2,0.9,2,2v15.72	C163.25,102.01,162.36,102.91,161.25,102.91z"/><path d="M168.03,105.85L168.03,105.85c-0.89-0.65-1.08-1.91-0.43-2.8l9.3-12.67c0.65-0.89,1.91-1.08,2.8-0.43l0,0	c0.89,0.65,1.08,1.91,0.43,2.8l-9.3,12.67C170.18,106.31,168.92,106.5,168.03,105.85z"/><path d="M172.61,112.97L172.61,112.97c-0.21-1.09,0.49-2.14,1.58-2.35l15.42-3.05c1.09-0.21,2.14,0.49,2.35,1.58v0	c0.21,1.09-0.49,2.14-1.58,2.35l-15.42,3.05C173.88,114.76,172.82,114.06,172.61,112.97z"/><path d="M139.23,79.38"/><path d="M18.52,68.43"/><polygon points="22.47,69.51 34.59,69.51 22.47,66.27 "/><path d="M18.52,68.43l2.75,2.69c0,0,10.32,14.33,0.87,28.32l-2.62-1.55l2.8-6.34l-1.3-15.66L18.52,68.43z"/><polygon points="64.66,97.14 71.33,93.04 69.88,88.69 66.97,90.66 64.64,92.32 63.69,83.18 61.71,93.55 "/><polygon points="88.69,92.32 77.36,85.42 82.14,93.04 88.02,96.46 "/><polygon points="140.53,85.42 139.53,80.52 136.09,73.98 138.31,87.58 "/><path d="M76.5,131h-25c-1.38,0-2.5-1.12-2.5-2.5l0,0c0-1.38,1.12-2.5,2.5-2.5h25c1.38,0,2.5,1.12,2.5,2.5l0,0	C79,129.88,77.88,131,76.5,131z"/><path d="M50,88H35c-1.66,0-3-1.34-3-3l0,0c0-1.66,1.34-3,3-3h15c1.66,0,3,1.34,3,3l0,0C53,86.66,51.66,88,50,88z"/></svg>';
    	// return '<svg version=1.1 xmlns="http://www.w3.org/2000/svg" viewBox="0 12.705 512 486.59" x="0px" y="0px" xml:space="preserve" width="{{ratewidth}}px" height="{{ratewidth}}px" fill="{{$fill}}{{/fill}}"> <polygon points="256.814,12.705 317.205,198.566 512.631,198.566 354.529,313.435 414.918,499.295 256.814,384.427 98.713,499.295 159.102,313.435 1,198.566 196.426,198.566 "/></svg>';
    }
    /**
     * @method get_skillmatrix_view
     * @todo To display the all skills and positions
     */
    public function get_skillmatrix_view($costcenterid, $domainid) {
        global $OUTPUT, $DB, $CFG, $PAGE;
        $querylib  = new \local_skillrepository\local\querylib();
		$systemcontext = (new \local_skillrepository\lib\accesslib())::get_module_context();
        if(!empty($costcenterid) && !empty($domainid)){
        	$positionslist = $querylib->get_positions($costcenterid, $domainid);
		    $position_nameslist = array(0=>'Roles');
	        foreach ($positionslist as $position_data) {
				$position_nameslist[]=$position_data->name;
			}
	    	$data = array();
			if(!empty($positionslist)) {		        
		        $competencies = $querylib->get_competencies($costcenterid);

				if(!empty($competencies)) {
		            foreach ($competencies as $competency) {
		            	$compt = array();
		            	$compt['comptname'] = $competency->name;
		            	//competency levels
		            	$comp_levels_list_sql = "SELECT cl.id, cl.name, cls.skillid,cls.levelid FROM {local_course_levels} as cl JOIN {local_skill_levels} cls ON cls.levelid=cl.id where cls.costcenterid={$costcenterid}";
		            	$comp_levels_info = $DB->get_records_sql($comp_levels_list_sql);
			               	
                        $comp_levels_list = array();
						if(!empty($comp_levels_info)) {
				            foreach ($comp_levels_info as $comp_level) {
				               	$comp_levels = array();
				               	$comp_levels['com_level_name'] = $comp_level->name;
				               	$comp_levels['com_level_id'] = $comp_level->id;
				               	// if($comp_level->skillid){
					               	//$sql = "SELECT * FROM {local_skill} WHERE id IN ({$comp_level->skillid})";
					               	$sql = "SELECT sm.id as mappingid, s.* FROM {local_skill} as s JOIN {local_comp_skill_mapping} as sm ON sm.skillid=s.id WHERE  sm.competencyid={$competency->id}";
									$skills = $DB->get_records_sql($sql);

						        	// $skills = $querylib->get_skills($costcenterid, $competency->id);
					               	$skills_list = array();
									if(!empty($skills)) {
						                foreach ($skills as $skill) {
						                	$skillLevelSql = "SELECT lsl.id, lsl.levelid, lcl.name FROM {local_skill_levels} AS lsl 
						                		JOIN {local_course_levels} AS lcl on lcl.id= lsl.levelid 
						                		WHERE lsl.skillid = :skillid";
						                	$skilllevels = $DB->get_records_sql($skillLevelSql, ['skillid' => $skill->id]);
                                            $skill_names = array();
							                $skill_names['skillname'] = $skill->name;
							                $levelslist = array(0=>'');               
					        				$positions = $querylib->get_positions($costcenterid, $domainid);
						               		$levels_list = array();
						               		$levelindexes = array();
						               		foreach ($skilllevels as $skilllevel) {
					               				$levelindexes[] = ['levellabel' => $skilllevel->name];
						               		}
											if(!empty($positions)) {
							            		foreach ($positions as $position) {
							            			$levelname=array();
							            			// $lev_name = $DB->get_field('local_course_levels', 'name', array('id'=>$skill->skilllevelid));
							            			// if($lev_theme){
							               //  			$levelname['lev_theme'] = $lev_theme;
							               //  		} else {
							               //  			$levelname['lev_theme'] = 'nolevel';
							               //  		}
							            			
							            			$levelsarray = [];
							            			foreach($skilllevels AS $skilllevel){
							            				$skillmatrix_rec = $DB->get_record('local_skillmatrix', array('costcenterid' => $costcenterid, 'skill_categoryid' => $competency->id,'skillid' => $skill->id, 'positionid' => $position->id, 'skilllevel' => $skilllevel->levelid));
								            			if($skillmatrix_rec){
								            				$addclass = 'skillassigned';
								            				if($skilllevel->theme){
									                			$lev_theme = $skilllevel->theme;
									                		}
								            			} else {
								            				$addclass = '';
								            				$lev_theme = 'nolevel';
								            			}
							            				$assignlevel = html_writer:: link('javascript:void(0)', '',  array('class'=>"levelnameclass assignlevel $addclass",'title'=>get_string('level','local_skillrepository'), 'data-scheme' => 0, 'data-costcenterid' => $costcenterid, 'data-competencyid' => $competency->id, 'data-positionid' => $position->id, 'data-levelid' => 0, 'data-skilllevel' => $skilllevel->levelid, 'data-levelname' => $skilllevel->name, 'data-contextid' => $systemcontext->id, 'data-skillid' => $skill->id));
							            				// 'onclick' => '(function(e){ require("local_skillrepository/newassignlevel").getSkillLevels({contextid:'.$systemcontext->id.', costcenterid:'.$costcenterid.', competencyid:'.$competency->id.', skillid:'.$skill->id.', positionid:'.$position->id.', levelid:'.$skill->skilllevelid.', skilllevel:'.$skilllevel->levelid.', levelname:"'.$skilllevel->name.'" ,element:this}) })(event)'
						            					$levelsarray[] = ['levellink' => $assignlevel, 'lev_theme' => $lev_theme];
							            			}
							            			$levelname['levels'] = $levelsarray;
							            			//$lev_theme = $DB->get_field('local_course_levels', 'theme', array('id'=>$skill->skilllevelid));
						            				
								                	// $skillmatrix=$querylib->get_skillmatrix($costcenterid, $competency->id, $skill->id, $position->id);
								                	// if($skillmatrix->levelid != 0) {
								                	// 	$lev_name = $DB->get_field('local_course_levels', 'name', array('id'=>$skillmatrix->levelid));
								                	// 	$lev_theme = $DB->get_field('local_course_levels', 'theme', array('id'=>$skillmatrix->levelid));
								                	// 	$levelid = $skillmatrix->levelid;
								                	// 	$assignlevel = html_writer:: link('javascript:void(0)',  $lev_name,  array('title'=>get_string('level','local_skillrepository'),'onclick' => '(function(e){ require("local_skillrepository/newassignlevel").init({selector:"createassignlevel", contextid:'.$systemcontext->id.', costcenterid:'.$costcenterid.', competencyid:'.$competency->id.', skillid:'.$skill->id.', positionid:'.$position->id.', levelid:'.$levelid.', levelname:"'.$lev_name.'"}) })(event)'));
								                	// 	$levelname['levels'] = $assignlevel;
								                	// 	if($lev_theme){
								                	// 		$levelname['lev_theme'] = $lev_theme;
								                	// 	} else {
								                	// 		$levelname['lev_theme'] = 'noscheme';
								                	// 	}
								                	// } else {
								                	// 	$levelid = 0;
								                	// 	$lev_name = 'NA';
								                	// 	$assignlevel = html_writer:: link('javascript:void(0)',  $lev_name,  array('title'=>$lev_name,'onclick' => '(function(e){ require("local_skillrepository/newassignlevel").init({selector:"createassignlevel", contextid:'.$systemcontext->id.', costcenterid:'.$costcenterid.', competencyid:'.$competency->id.', skillid:'.$skill->id.', positionid:'.$position->id.', levelid:'.$levelid.', levelname:"'.$lev_name.'"}) })(event)'));
								                	// 	$levelname['levels'] = $assignlevel;
								                	// 	$levelname['lev_theme'] = 'nolevel';
								                	// }
								                	$levels_list[] = $levelname;
								                }
								            }
							                $skill_names['levelnames'] = $levels_list;
							                $skill_names['levelindexes'] = $levelindexes;
							                $skills_list[]=$skill_names;
							            }
							        } 
						        // }
					            $comp_levels['skills'] = $skills_list;
						    }
					            $comp_levels_list[] = $comp_levels;
					    }
			            $compt['comp_levels'] = $comp_levels_list;
			            $data[] = $compt;
		            }
	        	}  else {
		        	$skilldata = html_writer::tag('div', get_string('nocompetencyrecords', 'local_skillrepository'),array('class'=>'emptymsg'));
		        	return $skilldata;
		        }
	        } else {
	        	$skilldata = html_writer::tag('div', get_string('nopositionrecords', 'local_skillrepository'),array('class'=>'emptymsg'));
	        	return $skilldata;
	        }
	        $viewskillmatrixContext = [
	            "positionnames" => $position_nameslist,
	            "data"=>$data
	        ];
	        return $this->render_from_template('local_skillrepository/skillmatrix_view', $viewskillmatrixContext);
	    } /*else {
        	$skilldata = html_writer::tag('div', get_string('norecords', 'local_skillrepository'),array('class'=>'emptymsg'));
        	return $skilldata;
        }*/
    }

    public function custom_competency_cardview($filter = false){
        global $USER;
        $systemcontext =(new \local_skillrepository\lib\accesslib())::get_module_context();

        $costcenterid = optional_param('costcenterid', '', PARAM_INT);

        $options = array('targetID' => 'manage_competency_view','perPage' => 6, 'cardClass' => 'w_oneintwo', 'cardClass' => 'col-md-6 col-12', 'viewType' => 'card');

        $options['methodName']='local_skillrepository_competency_view';
        $options['templateName']='local_skillrepository/newcompetency_view';
        $options = json_encode($options);

        $dataoptions = json_encode(array('userid' =>$USER->id,'contextid' => $systemcontext->id, 'filteropen_costcenterid' => $costcenterid));
        $filterdata = json_encode(array('filteropen_costcenterid' => $costcenterid));

        $context = [
            'targetID' => 'manage_competency_view',
            'options' => $options,
            'dataoptions' => $dataoptions,
            'filterdata' => $filterdata
        ];

        if($filter){
            return  $context;
        }else{
            return  $this->render_from_template('local_costcenter/cardPaginate', $context);
        }
    }

    public function competency_data_view($skill_category) {
        global $OUTPUT, $DB, $CFG, $PAGE;

        $systemcontext =(new \local_skillrepository\lib\accesslib())::get_module_context();
        $cost = (explode("/",$skill_category->open_path));
        $assigncourseicon = self::getAssignCourseIcon();
        $costcenterid = explode('/', $skill_category->open_path)[1];
        $sql = "SELECT s.*,sm.skilllevelid FROM {local_skill} as s JOIN {local_comp_skill_mapping} as sm ON sm.skillid=s.id WHERE sm.costcenterid=$costcenterid and competencyid=".$skill_category->id;
        $comp_skills = $DB->get_records_sql($sql);

        $skillcount = $DB->count_records_sql("SELECT count(lcsm.id) FROM {local_comp_skill_mapping} AS lcsm WHERE competencyid= $skill_category->id");
        $levelcount = $DB->count_records_sql("SELECT count(lsl.id) FROM {local_skill_levels} AS lsl WHERE competencyid= $skill_category->id");

                $competency_level_skills = array(); 
                foreach ($comp_skills as $comp_level_skills) {

                    $competency_level_skills_row = array();
                    $competency_level_skills_row['comp_level_skill_name']=$comp_level_skills->name;
                    $competency_level_skills_row['comp_level_skill_id']=$comp_level_skills->id;
                    $competency_level_skills_row['comp_level_skill_levelid']=$comp_level_skills->skilllevelid;
                    $competency_level_skills_row['comp_level_skill_levelname']=$skilllevelnames;
                    $levelicon = '<span class="icon assignuser_icon" title="Assign Level"></span>';
                    $assignskilllevel = html_writer:: link($editurl,  $levelicon,  array('title'=>get_string('assignlevel','local_skillrepository'),'onclick' => '(function(e){ require("local_skillrepository/newassignlevel").init({selector:"createassignlevel", contextid:'.$systemcontext->id.', repositoryid:'.$comp_level_skills->id.', costcenterid:'.$costcenterid.', competencyid:'.$skill_category->id.'}) })(event)' , 'class'=>'assign_icon'));
                    $competency_level_skills_row['comp_level_skill_assignlevel']=$assignskilllevel;

                    //Skill levels
                    $sql = "SELECT cl.*, sl.skillid FROM {local_course_levels} cl JOIN {local_skill_levels} sl ON sl.levelid=cl.id WHERE sl.skillid = {$comp_level_skills->id} AND sl.competencyid = {$skill_category->id}";
                    $comp_level_skill_levels = $DB->get_records_sql($sql);
                    $competency_level_skill_levels = array();
                    if($comp_level_skill_levels) {
                        foreach ($comp_level_skill_levels as $comp_level_skill_level) {
                            $comp_level_skill_level_row = array();
                             $comp_level_skill_level_row['comp_level_skill_level_name']=$comp_level_skill_level->name;
                            $comp_level_skill_level_row['comp_level_skill_level_id']=$comp_level_skill_level->id;
                            $assignskill_level_course = html_writer:: link($editurl,  get_string('assigncourse','local_skillrepository'),  array('class'=>'assigncourse_icon mr-3','title'=>get_string('assigncourse','local_skillrepository'),'onclick' => '(function(e){ require("local_skillrepository/newassigncourse").init({selector:"createassigncourse", contextid:'.$systemcontext->id.', repositoryid:'.$comp_level_skills->id.', org_id:'.$costcenterid.', levelid: '.$comp_level_skill_level->id.', competencyid: '.$skill_category->id.'}) })(event)'));
                            $comp_level_skill_level_row['comp_level_skill_assignskill_level_course']=$assignskill_level_course;
                            $course = $DB->get_records('local_comp_course_mapping',array('skillid'=>$comp_level_skills->id, 'levelid'=>$comp_level_skill_level->id, 'competencyid'=>$skill_category->id));
                            html_writer:: link('javascript:void(0)',  count($course),  array('class'=>'createcoursemodal course_count_popup','onclick' => '(function(e){ require("local_skillrepository/newassigncourse").courselist({contextid:'.$systemcontext->id.', skillid:'.$comp_level_skills->id.',levelid:'.$comp_level_skill_level->id.', costcenterid:'.$costcenterid.'}) })(event)'));
                            $courses = html_writer:: link('javascript:void(0)',  count($course),  array('class'=>'createcoursemodal course_count_popup','onclick' => '(function(e){ require("local_skillrepository/newassigncourse").getCourselist({contextid:'.$systemcontext->id.', skillid:'.$comp_level_skills->id.',levelid:'.$comp_level_skill_level->id.', costcenterid:'.$costcenterid.', competencyid: '.$skill_category->id.'}) })(event)'));
                            $comp_level_skill_level_row['skill_level_courses_count']=$courses;
                            $cost = (explode("/",$comp_level_skill_level->open_path));
                            $removeicon = html_writer::tag('span', 'Delete', array('class' => 'delete_level'));
                            $comp_level_skill_level_row['skill_level_remove']= html_writer::link('javascript:void(0)', $removeicon, array('class' => 'removelevelSkill', 'title' => get_string('purgelevel', 'local_skillrepository'), 'data-skillid' => $comp_level_skill_level->skillid, 'data-costcenterid' => $cost[1], 'data-levelid' => $comp_level_skill_level->id, 'data-levelname' => $comp_level_skill_level->name,'data-competencyid' => $skill_category->id));
                            $competency_level_skills_row['lable'] = get_string('labellevels', 'local_skillrepository');                            
                            $competency_level_skill_levels[] = $comp_level_skill_level_row;
                        }
                    }

                    $competency_level_skills_row['skill_levels'] = $competency_level_skill_levels;
                    $competency_level_skills_row['competencyid'] = $skill_category->id;
                    $competency_level_skills_row['costcenterid'] = $cost[1];
                    $competency_level_skills[] = $competency_level_skills_row;
                }

        $levelicon = '<i class="icon fa fa-users fa-fw" title="'.get_string('assignskill','local_skillrepository').'"></i>';
        $comp_level=0;
        $skill_category->assignskill = html_writer:: link($editurl,  $levelicon,  array('title'=>get_string('assignskill','local_skillrepository'),'onclick' => '(function(e){ require("local_skillrepository/newassignskill").init({selector:"createcompetencyskill", contextid:'.$systemcontext->id.', repositoryid:'.$skill_category->id.', costcenterid:'.$costcenterid.', complevelid:'.$comp_level.'}) })(event)','class'=>'assignskill_icon'));
        $competencyContext = [
                "competency" => $skill_category,
                "competency_level_skills" => $competency_level_skills,
                "skillcount" => $skillcount,
                "levelcount" => $levelcount,
            ];

        return $this->render_from_template('local_skillrepository/competency', $competencyContext);

    }

    public function backbuttonlink($backurllink) {

        return "<ul class='course_extended_menu_list'>
                   <li>
                                <div class='coursebackup course_extended_menu_itemcontainer'>
                                    <a href='".$backurllink."' title='".get_string("back")."' class='course_extended_menu_itemlink'><i class='icon fa fa-reply'></i>
                                    </a>
                                </div>
                            </li>
              </ul>";
    }
}
