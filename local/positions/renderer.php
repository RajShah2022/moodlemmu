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
 * @subpackage local_positions
 */
require_once(dirname(__FILE__) . '/../../config.php');
require_once('lib.php');

class local_positions_renderer extends plugin_renderer_base {
	//positions related functions
	public function display_positions_tablestructure(){
		$table = new \html_table();
		$table->id = 'all_positions_display_table';
		$table->head = array( get_string('open_costcenterid', 'local_costcenter'), get_string('domain', 'local_positions'), get_string('parent', 'local_positions'), get_string('positionname', 'local_positions'),get_string('positioncode', 'local_positions'),get_string('actions'));
		$table = \html_writer::table($table);
		return $table;
	}

	public function display_positions_tabledata($params){
		global $CFG, $DB;
		$querylib = new \local_positions\local\querylib();
		$displaydata = $querylib->get_positions_table_contents($params);
		$tabledata = array();
		foreach($displaydata as $position){
		 	$costcentername = $DB->get_field('local_costcenter','fullname', array('id'=>$position->costcenter));
		 	$parent = $DB->get_field('local_positions','name', array('id'=>$position->parent));
		 	$domain = $DB->get_field('local_domains','name', array('id'=>$position->domain));
			$actions = '';
			$canedit = $querylib->can_edit_position($position->id);
			if($canedit){
				$systemcontext = (new \local_costcenter\lib\accesslib())::get_module_context();
				$editicon = "<i class='fa fa-pencil fa-fw'></i>";
				$actions .= \html_writer::link('javascript:void(0)', $editicon, array('title'=>get_string('edit','local_positions'),'onclick' => '(function(e){ require("local_positions/positiontable").init({ contextid:'.$systemcontext->id.',positionid: '.$position->id.', positionname: "'.$position->name.'"}) })(event)'));
			}
			$candelete = $querylib->can_delete_position($position->id);
			if($candelete){
				$deleteicon ="<i class='fa fa-trash fa-fw'></i>";
				$actions .= \html_writer::link('javascript:void(0)', $deleteicon, array('title'=>get_string('delete','local_positions'),'onclick' => '(function(e){ require("local_positions/positiontable").deleteposition({positionid: '.$position->id.', positionname: "'.$position->name.'"}) })(event)'));
			}
			
			$data = array();
			$data[] = $costcentername;			
			if(!empty($domain)) {
				$data[] = $domain;			
			} else {
				$data[] = '--';			
			}
			if(!empty($parent)) {
				$data[] = $parent;			
			} else {
				$data[] = '--';			
			}
			$data[] = $position->name;
			$data[] = $position->code;
			$data[] = $actions;
			$tabledata[] = $data;
 		}
 		return $tabledata;
	}

	//domains related functions
	public function display_domains_tablestructure(){
		$table = new \html_table();
		$table->id = 'all_domains_display_table';
		$table->head = array(get_string('domainname', 'local_positions'),get_string('domaincode', 'local_positions'), get_string('open_costcenterid', 'local_costcenter'),get_string('actions'));
		$table = \html_writer::table($table);
		return $table;
	}

	public function display_domains_tabledata($params){
		global $CFG, $DB;
		$querylib = new \local_positions\local\querylib();
		$displaydata = $querylib->get_domains_table_contents($params);
		$tabledata = array();
		foreach($displaydata as $domain){
		 	$costcentername = $DB->get_field('local_costcenter','fullname', array('id'=>$domain->costcenter));
			$actions = '';
			$canedit = $querylib->can_edit_domain($domain->id);
			if($canedit){
				$systemcontext = (new \local_costcenter\lib\accesslib())::get_module_context();
				$editicon = "<i class='fa fa-pencil fa-fw'></i>";
				$actions .= \html_writer::link('javascript:void(0)', $editicon, array('title'=>get_string('edit','local_positions'),'onclick' => '(function(e){ require("local_positions/domaintable").init({ contextid:'.$systemcontext->id.',domainid: '.$domain->id.', domainname: "'.$domain->name.'"}) })(event)'));
			}
			$candelete = $querylib->can_delete_domain($domain->id);
			if($candelete){
				$deleteicon ="<i class='fa fa-trash fa-fw'></i>";
				$actions .= \html_writer::link('javascript:void(0)', $deleteicon, array('title'=>get_string('delete','local_positions'),'onclick' => '(function(e){ require("local_positions/domaintable").deletedomain({domainid: '.$domain->id.', domainname: "'.$domain->name.'"}) })(event)'));
			}
			
			$data = array();
			$data[] = $domain->name;
			$data[] = $domain->code;
			$data[] = $costcentername;
			$data[] = $actions;
			$tabledata[] = $data; 
 		}
 		return $tabledata;
	}
	
	public function domain_view() {
        global $DB, $CFG, $OUTPUT, $USER,$PAGE;
        $systemcontext = (new \local_costcenter\lib\accesslib())::get_module_context();
        if (!has_capability('local/domains:manage', $systemcontext)){
            print_error('errornopermission', 'local_assignroles');
        }
        if (is_siteadmin()) {
            $sql = "SELECT * FROM {local_domains} WHERE 1=1 ORDER BY id desc";
            $domains = $DB->get_records_sql($sql);
        } else/* if(has_capability('local/costcenter:view', $systemcontext))*/{
            $sql = "SELECT * FROM {local_domains} WHERE costcenter = ? ORDER BY id desc";
            $costarray = explode("/",$USER->open_path);
            $usercost = $costarray[1];

            $domains = $DB->get_records_sql($sql, [$usercost]);
        } 
        // if (!is_siteadmin() && empty($doamins)) {
        //     print_error('noodomainsavailable', 'local_costcenter');
        // }
        $data = array();
        if(!empty($domains)){
            foreach ($domains as $domain) {
                $line = array();
                $showdepth = 1;
                $line[] = $this->display_domain_item($domain, $showdepth);
                $data[] = $line;
            }

            $table = new html_table();
            $table->head = array('');
            $table->align = array('left');
            $table->width = '100%';
            $table->data = $data;
            $table->id = 'all_domains_display_table';
            $output = html_writer::table($table);
            
        }else{
            $output = html_writer::tag('div', get_string('noodomainsavailable', 'local_positions'), array('class'=>'alert alert-info text-center'));
        }

        return $output;
    }

    /**
     * @method display_department_item
     * @todo To display the all costcenter items
     * @param object $record is costcenter  
     * @param boolean $indicate_depth  depth for the costcenter item
     * @return string
     */
    public function display_domain_item($record, $indicate_depth = true) {
        global $OUTPUT, $DB, $CFG, $PAGE;
        $systemcontext = (new \local_costcenter\lib\accesslib())::get_module_context();

        $sql="SELECT id, id as id_val from {local_positions} where domain=? and parent=0";
        $orgs = $DB->get_records_sql_menu($sql, [$record->id]);

        $parentpositionscount = count($orgs);

        if($parentpositionscount > 0){
            $position_hira_link = new moodle_url("/local/positions/domainview.php?id=".$record->id."");
        }else{
            $position_hira_link = 'javascript:void(0)';            
        }

		$querylib = new \local_positions\local\querylib();
		$canedit = $querylib->can_edit_domain($record->id);
		if($canedit){
			$systemcontext = (new \local_costcenter\lib\accesslib())::get_module_context();
			$edit_icon = "<i class='fa fa-pencil fa-fw'></i>";
			$editicon = \html_writer::link('javascript:void(0)', $edit_icon, array('title'=>get_string('edit','local_positions'),'onclick' => '(function(e){ require("local_positions/domaintable").init({ contextid:'.$systemcontext->id.',domainid: '.$record->id.', domainname: "'.$record->name.'"}) })(event)'));
		}
		$candelete = $querylib->can_delete_domain($record->id);
		if($candelete){
			$delete_icon ="<i class='fa fa-trash fa-fw'></i>";
			$deleteicon = \html_writer::link('javascript:void(0)', $delete_icon, array('title'=>get_string('delete','local_positions'),'onclick' => '(function(e){ require("local_positions/domaintable").deletedomain({domainid: '.$record->id.', domainname: "'.$record->name.'"}) })(event)'));
		}
        if (has_capability('local/domains:manage', $systemcontext)) {
            $edit = true;        
            $delete = true;
        }
        $organization = $DB->get_field('local_costcenter', 'fullname', array('id'=>$record->costcenter));
        if($organization){
            $org_name = $organization;
        } else {
            $org_name = 'NA';
        }
        $viewdeptContext = [
            "domainname" => format_string($record->name),
            "org_name" => $org_name,
            "position_hira_link" => $position_hira_link,
            "parentpositionscount" => $parentpositionscount,
            "edit" => $edit,
            "editicon" => $editicon,
            "delete" => $delete,
            "deleteicon" => $deleteicon,
            "domainid" => $record->id
        ];

        $viewdeptContext = $viewdeptContext;//+$pluginnavs;
        // print_object($viewdeptContext); die;
        return $this->render_from_template('local_positions/domain_view', $viewdeptContext);
    }
	
	public function position_view($id, $systemcontext){
        global $DB, $USER, $OUTPUT;
		$querylib = new \local_positions\local\querylib();
        $domain = $DB->get_record('local_domains', array('id' => $id));
        $canedit = $querylib->can_edit_domain($id);
		if($canedit){
			$systemcontext = (new \local_costcenter\lib\accesslib())::get_module_context();
			$edit_icon = "<i class='fa fa-pencil fa-fw'></i>";
			$editicon = \html_writer::link('javascript:void(0)', $edit_icon, array('title'=>get_string('edit','local_positions'),'onclick' => '(function(e){ require("local_positions/domaintable").init({ contextid:'.$systemcontext->id.',domainid: '.$domain->id.', domainname: "'.$domain->name.'"}) })(event)'));
		}
		$candelete = $querylib->can_delete_domain($record->id);
		if($candelete){
			$delete_icon ="<i class='fa fa-trash fa-fw'></i>";
			$deleteicon = \html_writer::link('javascript:void(0)', $delete_icon, array('title'=>get_string('delete','local_positions'),'onclick' => '(function(e){ require("local_positions/domaintable").deletedomain({domainid: '.$domain->id.', domainname: "'.$domain->name.'"}) })(event)'));
		}

        if (has_capability('local/positions:manage', $systemcontext)) {
            $edit = true;
            $delete = true;
        }
        $positions_sql="SELECT id,id AS id_val FROM {local_positions} WHERE parent=0 AND domain=:domain";
        $positions =$DB->get_records_sql_menu($positions_sql, array('domain' => $id));
        $positions_count = count($positions);
        $positions_count = ($positions_count > 0 ? $positions_count : 'N/A');
        $domain = $DB->get_record('local_domains', array('id'=>$id));
        $organization = $DB->get_field('local_costcenter', 'fullname', array('id'=>$domain->costcenter));
        if($organization){
            $org_name = $organization;
        } else {
            $org_name = 'NA';
        }
        $positions_content = array();
        if($positions_count % 2 == 0){ 
            $deptclass = '';
        }else{ 
            $deptclass = 'deptsodd';
        } 

        $deptkeys = array_values($positions);

        foreach($deptkeys as $key => $dept){
            $even = false;
            $odd = false;
            if($key % 2 == 0){ 
                $even = true;
            } 
            else{ 
                $odd = true;
            } 
         
        	$position = $DB->get_record('local_positions', array('id'=>$dept));
            $positions_array = array();
            $positions_childarray = local_hirarichy_positions($id, $dept);
            if (has_capability('local/positions:manage', $systemcontext)) {
                $querylib = new \local_positions\local\querylib();
                $canedit = $querylib->can_edit_position($position->id);
                if($canedit){
                    $systemcontext = (new \local_costcenter\lib\accesslib())::get_module_context();
                    $editicon = "<i class='fa fa-pencil fa-fw'></i>";
                    $editposition = \html_writer::link('javascript:void(0)', $editicon, array('title'=>get_string('edit','local_positions'),'onclick' => '(function(e){ require("local_positions/positiontable").init({ contextid:'.$systemcontext->id.',positionid: '.$position->id.', positionname: "'.$position->name.'"}) })(event)'));
                }
                if($position->parent != 0){    
                    $add = 'childposition'; 
                } else {    
                    $add = 'parentposition';    
                }
                $candelete = $querylib->can_delete_position($position->id);
                if($candelete){
                    $deleteicon ="<i class='fa fa-trash fa-fw'></i>";
                    $deleteposition = \html_writer::link('javascript:void(0)', $deleteicon, array('title'=>get_string('delete','local_positions'),'onclick' => '(function(e){ require("local_positions/positiontable").deleteposition({positionid: '.$position->id.', positionname: "'.$position->name.'", positiontype: "'.$add.'"}) })(event)'));
                }
            }
            $positions_array['positionfullname'] = $position->name;
            $positions_array['positionid'] = $dept;
            $positions_array['even'] = $even;
            $positions_array['odd'] = $odd;
            $positions_array['deleteposition'] = $deleteposition;   
            $positions_array['editposition'] = $editposition;
            $positions_array['deptclass'] = $deptclass;
            $positions_array['deptid'] = $dept;
            $positions_array['hira_positions'] = $positions_childarray;
            $positions_content[] = $positions_array+$positions_childarray;
        }
        $canedit = $querylib->can_edit_domain($domain->id);
        if($canedit){
            $systemcontext = (new \local_costcenter\lib\accesslib())::get_module_context();
            $edit_icon = "<i class='fa fa-pencil fa-fw'></i>";
            $editdomain = \html_writer::link('javascript:void(0)', $edit_icon, array('title'=>get_string('edit','local_positions'),'onclick' => '(function(e){ require("local_positions/domaintable").init({ contextid:'.$systemcontext->id.',domainid: '.$domain->id.', domainname: "'.$domain->name.'"}) })(event)'));
        }
        $candelete = $querylib->can_delete_domain($record->id);
        if($candelete){
            $delete_icon ="<i class='fa fa-trash fa-fw'></i>";
            $deletedomain = \html_writer::link('javascript:void(0)', $delete_icon, array('title'=>get_string('delete','local_positions'),'onclick' => '(function(e){ require("local_positions/domaintable").deletedomain({domainid: '.$domain->id.', domainname: "'.$domain->name.'"}) })(event)'));
        }
        $costcenter_view_content = [
            'domainname' => $domain->name,
            'org_name' => $org_name,
            'showsubdept_content' => false,
            "parentpositionscount" => $positions_count,
            "deptclass" => $deptclass, 
            "edit" => $edit,
            "hide" => $hide,
            "delete" => $delete,
            "domainid" => $id,
            "editicon" => $editdomain,
            "deleteicon" => $deletedomain,
            "positions_content" => $positions_content
        ];
        
        $costcenter_view_content = $costcenter_view_content;
        return $OUTPUT->render_from_template('local_positions/positions_view', $costcenter_view_content);
    }
}
