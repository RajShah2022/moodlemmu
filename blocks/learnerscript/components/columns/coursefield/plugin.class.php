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
 * @subpackage block_learnerscript
 */
use block_learnerscript\local\pluginbase;
use block_learnerscript\local\reportbase;
class plugin_coursefield extends pluginbase {

    public function init() {
        global $DB;
        $this->fullname = get_string('coursefield', 'block_learnerscript');
        $this->type = 'advanced';
        $this->form = true;
        $this->reporttypes = array();
        $this->costcenterarray = array();
        $this->costcenterarray = $DB->get_records_menu('local_costcenter',array());
        $this->coursecategory = $DB->get_records_menu('local_custom_fields',array());       
    }

    public function summary($data) {
        return format_string($data->columname);
    }

    public function colformat($data) {
        $align = (isset($data->align)) ? $data->align : '';
        $size = (isset($data->size)) ? $data->size : '';
        $wrap = (isset($data->wrap)) ? $data->wrap : '';
        return array($align, $size, $wrap);
    }

    // Data -> Plugin configuration data.
    // Row -> Complet course row c->id, c->fullname, etc...
    public function execute($data, $row, $user, $courseid, $starttime = 0, $endtime = 0) {
        global $DB, $CFG,$USER; 
      
        list($zero, $org, $ctr, $bu, $cu, $territory) = explode("/",$row->course_open_path);
        $coursereportid = $DB->get_field('block_learnerscript', 'id', array('type'=>'courseprofile'), IGNORE_MULTIPLE);
        $categorycontext = (new \local_courses\lib\accesslib())::get_module_context();
       
        switch ($data->column) { 
            case 'coursename': 
                $checkpermissions = empty($coursereportid) ? false : (new reportbase($coursereportid))->check_permissions($USER->id, $categorycontext);
                if($this->report->type == 'courseprofile' || empty($coursereportid) || empty($checkpermissions)){
                    $row->{$data->column} = '<a href="'.$CFG->wwwroot.'/course/view.php?id='.$row->courseid.'" />'.$row->coursename.'</a>'; 
                } else if($coursereportid){
                    $row->{$data->column} = '<a href="'.$CFG->wwwroot.'/blocks/learnerscript/viewreport.php?id='.$coursereportid.'&filter_course='.$row->courseid.'&filter_organization='.$this->reportfilterparams['filter_organization'].'&filter_departments='.$this->reportfilterparams['filter_departments'].'" />'.$row->coursename.'</a>';
                }
                break;
 
            case 'coursecode':
                $row->{$data->column} = $row->shortname;
                break;
            case 'coursecategory':
                $coursecategory = $this->coursecategory[$row->open_categoryid];
                $row->{$data->column} = $coursecategory ? $coursecategory: 'NA';
            break;
            case 'coursevisible':
                $row->{$data->column} = ($row->visible) ?
                                            '<span class="label label-success">' . get_string('active') .'</span>':
                                            '<span class="label label-warning">' . get_string('inactive'). '</span';
            break;                
            case get_string('courseorg', 'local_costcenter'):
                $row->{$data->column} =  $this->costcenterarray[$org];              
                break;
            case get_string('coursedept', 'local_costcenter'):
                if($ctr){
                    $row->{$data->column} =  $this->costcenterarray[$ctr];                   
                }else{
                   $row->{$data->column} = get_string('all'); 
                }
                break;
            case get_string('course_subdept', 'local_costcenter'):
                if($bu){
                    $row->{$data->column} =  $this->costcenterarray[$bu];
                }else{
                   $row->{$data->column} = get_string('all'); 
                }
                break;
            case get_string('course_commercialarea', 'local_costcenter'):
                if($cu){
                    $row->{$data->column} =  $this->costcenterarray[$cu];
                }else{
                   $row->{$data->column} = get_string('all'); 
                }
                break;
            case get_string('course_territory', 'local_costcenter'):
                if($territory){
                    $row->{$data->column} =  $this->costcenterarray[$territory];
                }else{
                   $row->{$data->column} = get_string('all'); 
                }
                break;         
            case 'courseskill':
                if($row->open_skill){
                    $skill = $DB->get_field('local_skill', 'name', array('id' =>$row->open_skill));
                }else{
                    $skill = 'NA';
                }
                $row->{$data->column} = $skill;
                break;
            case 'courselevel':
                if($row->open_level){
                    $level = $DB->get_field('local_course_levels', 'name', array('id' =>$row->open_level));
                }else{
                    $level = 'NA';
                }
                $row->{$data->column} = $level;
                break;        
            default:
                $row->{$data->column} = $row->{$data->column};
            break;
        }
       return (isset($row->{$data->column})) ? $row->{$data->column} : '';
    }
}
