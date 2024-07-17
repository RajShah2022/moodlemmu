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
global $CFG;
require_once($CFG->dirroot . '/lib/moodlelib.php');

//Position related functions

function local_positions_output_fragment_position_form($args){
    global $CFG,$DB;
    $args = (object) $args;
    $context = $args->context;
    $positionid = $args->positionid;
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

    
    $costid = $DB->get_field('local_positions', 'costcenter', array('id'=> $positionid));
    $mform = new \local_positions\form\positionsform(null, array('id' => $args->positionid, 'open_costcenterid' => $costid), 'post', '', null, true, $formdata);
    if ($positionid > 0) {
        $data = $DB->get_record('local_positions', array('id'=>$positionid));
        $mform->set_data($data);
    }
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
//Domain related functions

function local_positions_output_fragment_domain_form($args){
    global $CFG,$DB;
    $args = (object) $args;
    $context = $args->context;
    $domainid = $args->domainid;
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
    $costid = $DB->get_record('local_domains', array('id'=>$domainid));
    $mform = new \local_positions\form\domainsform(null, array('id' => $args->domainid, 'open_costcenterid' => $costid->costcenter), 'post', '', null, true, $formdata);
    if ($domainid > 0) {
        $data = $DB->get_record('local_domains', array('id'=>$domainid));
        $mform->set_data($data);
    }
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

function get_next_child_sortthread($parentid, $table) {
        global $DB, $CFG;
    $maxthread = $DB->get_record_sql("SELECT MAX(sortorder) AS sortorder FROM {$CFG->prefix}{$table} WHERE parent = ?", array($parentid));
    if (!$maxthread || strlen($maxthread->sortorder) == 0) {
        if ($parentid == 0) {
            // first top level item
            return inttovancode(1);
        } else {
            // parent has no children yet
            return $DB->get_field('local_positions', 'sortorder', array('id' => $parentid)) . '.' . inttovancode(1);
        }
    }
    return increment_sortorder($maxthread->sortorder);
}
/**
 * Convert an integer to a vancode
 * @param int $int integer to convert.
 * @return vancode The vancode representation of the specified integer
 */
function inttovancode($int = 0) {
    $num = base_convert((int) $int, 10, 36);
    $length = strlen($num);
    return chr($length + ord('0') - 1) . $num;
}

/**
 * Convert a vancode to an integer
 * @param string $char Vancode to convert. Must be <= '9zzzzzzzzzz'
 * @return integer The integer representation of the specified vancode
 */
function vancodetoint($char = '00') {
    return base_convert(substr($char, 1), 36, 10);
}
/**
 * Increment a vancode by N (or decrement if negative)
 *
 */
function increment_vancode($char, $inc = 1) {
    return inttovancode(vancodetoint($char) + (int) $inc);
}
/**
 * Increment a sortorder by N (or decrement if negative)
 *
 */
function increment_sortorder($sortorder, $inc = 1) {
    if (!$lastdot = strrpos($sortorder, '.')) {
        // root level, just increment the whole thing
        return increment_vancode($sortorder, $inc);
    }
    $start = substr($sortorder, 0, $lastdot + 1);
    $last = substr($sortorder, $lastdot + 1);
    // increment the last vancode in the sequence
    return $start . increment_vancode($last, $inc);
}
/*
* Author Rizwana
* Displays a node in left side menu
* @return  [type] string  link for the leftmenu
*/
// $advance = get_config('local_skillrepository','advance');
// if($advance == 1)
// {

// function local_positions_leftmenunode(){
//     $systemcontext =(new \local_costcenter\lib\accesslib())::get_module_context();
//     $positionreponode = '';
//     if(has_capability('local/costcenter:manage', $systemcontext) || is_siteadmin()) {
//         $positionreponode .= html_writer::start_tag('li', array('id'=> 'id_leftmenu_skills', 'class'=>'pull-left user_nav_div skills'));
//             $skills_url = new moodle_url('/local/positions/domains.php');
//             $skill_icon = '<i class="fa fa-server"></i>';
//             $positions = html_writer::link($skills_url, $skill_icon.'<span class="user_navigation_link_text">'.get_string('manage_domains','local_positions').'</span>',array('class'=>'user_navigation_link'));
//             $positionreponode .= $positions;
//         $positionreponode .= html_writer::end_tag('li');
//     }

//     return array('18' => $positionreponode);
// }
// }
/*
* Author Sowmya
* @return  child positions count with parent
*/
function local_hirarichy_positions($domainid, $positionid=false){
    global $CFG,$DB;
        $parent_position = new stdClass();
        $querylib = new \local_positions\local\querylib();
        $parent_position->id = $positionid;
        $position_names = array();
        $sql = "SELECT p1.id, p1.name, p1.parent, p1.depth FROM {local_positions} as p1 
               where domain={$domainid} and (path like '%/{$parent_position->id}/%' OR id={$parent_position->id}) and parent !=0";
        $positions = $DB->get_records_sql($sql);
        if($positions){
            $sub_positionslist = array();
            $i=0;
            foreach ($positions as $position) {
                $i++;
                $sub_positionsarray = array();
                $subdeparray['childpositions'] = $position->name;
                $canedit = $querylib->can_edit_position($position->id);
                if($canedit){
                    $systemcontext = (new \local_costcenter\lib\accesslib())::get_module_context();
                    $editicon = "<i class='fa fa-pencil fa-fw'></i>";
                    $edit = \html_writer::link('javascript:void(0)', $editicon, array('title'=>get_string('edit','local_positions'),'onclick' => '(function(e){ require("local_positions/positiontable").init({ contextid:'.$systemcontext->id.',positionid: '.$position->id.', positionname: "'.$position->name.'"}) })(event)'));
                }
                if($position->parent != 0){    
                    $add = 'childposition'; 
                } else {    
                    $add = 'parentposition';    
                }
                $candelete = $querylib->can_delete_position($position->id);
                if($candelete){
                    $deleteicon ="<i class='fa fa-trash fa-fw'></i>";
                    $delete = \html_writer::link('javascript:void(0)', $deleteicon, array('title'=>get_string('delete','local_positions'),'onclick' => '(function(e){ require("local_positions/positiontable").deleteposition({positionid: '.$position->id.', positionname: "'.$position->name.'", positiontype: "'.$add.'"}) })(event)'));
                }
                $subdeparray['edit'] = $edit;
                $subdeparray['delete'] = $delete;
                $subdeparray['depth'] = $i;
                $style = $i*10;
                $subdeparray['stylevalue'] = $style.'px';
                $sub_positionslist[]= $subdeparray;
            }
            $position_names['parentpositions']= $sub_positionslist;
        }
    return $position_names;
}
