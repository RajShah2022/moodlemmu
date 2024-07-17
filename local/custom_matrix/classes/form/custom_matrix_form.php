<?php
namespace local_custom_matrix\form;
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
 * @subpackage local_custom_matrix
 */
use moodleform;
use context_system;
use costcenter;
require_once(dirname(__FILE__) . '/../../../../config.php');
global $CFG;
require_once("$CFG->libdir/formslib.php");
require_once($CFG->dirroot . '/local/costcenter/lib.php');
class custom_matrix_form extends moodleform {

    public function definition() {
        global $USER,$DB;
        $categorycontext = (new \local_custom_matrix\lib\accesslib())::get_module_context();

        $mform = $this->_form;
        $fid = $this->_customdata['id'];
        $parentid = $this->_customdata['parentid'];
        $parentcatid = $this->_customdata['parentcatid'];
        $costcenterid = $this->_customdata['open_costcenterid'];
        $id = optional_param('id', 0, PARAM_INT);

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);
        $context = (new \local_custom_matrix\lib\accesslib())::get_module_context();
        $matrixquerylib = new \local_custom_matrix\querylib();
        $costcenterquerylib = new \local_costcenter\querylib();
        if($parentcatid>0 && is_siteadmin()){           
            $costcenterid = $matrixquerylib->get_matrixfield('costcenterid', array('id'=> $parentcatid));           
            $orgname = $costcenterquerylib->get_costcenterfield('fullname', array('id'=>$costcenterid));
            $mform->addElement('static','costcentername', get_string('open_costcenterid', 'local_costcenter'), $orgname);
            $mform->addElement('hidden','open_costcenterid', $costcenterid);
        }else{
            if($fid && is_siteadmin()){               
                $orgname = $costcenterquerylib->get_costcenterfield('fullname', array('id'=>$costcenterid));
                $mform->addElement('static','costcentername', get_string('open_costcenterid', 'local_costcenter'), $orgname);
                $mform->addElement('hidden','open_costcenterid');
            }else{
                local_costcenter_get_hierarchy_fields($mform, $this->_ajaxformdata, $this->_customdata,range(1, 1), false, 'local_custom_matrix', $context, $multiple = false);
            }
        }
        if($parentcatid>0){            
            $parentname = $matrixquerylib->get_matrixfield('fullname', array('id'=>$parentcatid));
            $mform->addElement('static', 'parentname', get_string('parent','local_costcenter'), $parentname);
            $mform->addElement('hidden', 'parentid', $parentcatid);
        } else {
            
            $parentname = 'Top';
            $mform->addElement('static','parentname', get_string('parent','local_costcenter'),$parentname);
            $mform->addElement('hidden', 'parentid', 0);
        }
        $mform->setType('parentid', PARAM_INT);

        $mform->addElement('text', 'name', get_string('name', 'local_custom_matrix'));
        $mform->setType('name', PARAM_RAW);
        $mform->addRule('name', null, 'required', null, 'client');

        $mform->addElement('text', 'shortname', get_string('shortname', 'local_custom_matrix'), array());
        $mform->setType('shortname', PARAM_RAW);
        $mform->addRule('shortname', null, 'required', null, 'client');

        // if($parentcatid  == 0){
        //     $choices = array(
        //         null => 'Select Type',
        //         0 => 'Learnings & Trainings',
        //         1 => 'External'        
        //     );
        //     $mform->addElement('select', 'type', get_string('type', 'local_custom_matrix'), $choices);
        //     $mform->addHelpButton('type', 'type', 'local_custom_matrix');
        // }

        $mform->disable_form_change_checker();        
    }

    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        $shortname = $data['shortname'];
        $id = $data['id'];
        $costcenterid = $data['open_costcenterid'];
        $matrixquerylib = new \local_custom_matrix\querylib();
        $record = $matrixquerylib->get_matrixshortname(array($shortname, $id, $costcenterid));
        
       if($data['name'] && empty(trim($data['name']))){
            $errors['name'] = get_string('namecannotbeempty', 'local_custom_matrix');    
          } 
       if($data['shortname'] && empty(trim($data['shortname']))){
             $errors['shortname'] = get_string('shortnamecannotbeempty', 'local_custom_matrix');    
           }    
        if (!empty($record)) {
            $errors['shortname'] = get_string('shortnameexists', 'local_custom_matrix');
        }
        if(strlen($shortname) > 150){
            $errors['shortname'] = get_string('shortnamelengthexceeds', 'local_custom_matrix');
        }
        // if (!empty($costcenterid) && $data['parentcatid'] = 0){
        //     if($data['type'] != NULL) {
        //         $errors['type'] = get_string('typerequired', 'local_custom_matrix');
        //     }
        // } 
        return $errors;
    }

}
