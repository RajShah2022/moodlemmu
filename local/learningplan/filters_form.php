<?php
// use core_component;
if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.');    ///  It must be included from a Moodle page
}

require_once($CFG->libdir.'/formslib.php');
class filters_form extends moodleform {

    function definition() {
        global $CFG;

        $mform    = $this->_form;
        $filterlist        = $this->_customdata['filterlist'];// this contains the data of this form
        $filterparams      = isset($this->_customdata['filterparams']) ? $this->_customdata['filterparams'] : null;
        $options           = isset($filterparams['options']) ? $filterparams['options'] : null;
        $dataoptions       = isset($filterparams['dataoptions']) ? $filterparams['dataoptions'] :null;
        $submit            = isset($this->_customdata['submitid']) ? $this->_customdata['submitid']: null;
        $submitid          = $submit ? $submit : 'filteringform';
        //$submitid = $this->_customdata['submitid'] ? $this->_customdata['submitid'] : 'filteringform';

        $this->_form->_attributes['id'] = $submitid;
        $this->_form->_attributes['class'] = $submitid;


        $mform->addElement('hidden', 'options', $options);
        $mform->setType('options', PARAM_RAW);

        $mform->addElement('hidden', 'dataoptions', $dataoptions);
        $mform->setType('dataoptions', PARAM_RAW);

        foreach ($filterlist as $key => $value) {
            if($value === 'organizations' || $value === 'departments' || $value == 'subdepartment' || $value === 'department4level' || $value === 'department5level' || $value === 'states' || $value === 'district' || $value === 'subdistrict' || $value === 'village'){
                $filter = 'costcenter';
            }else if($value === 'categories' || $value == 'status'){
                $filter = 'courses';
            }else if($value === 'learningplan'){
                $filter = 'learningplan';
            }else if($value === 'program'){
                $filter = 'program';
            } else if($value === 'hierarchy_fields'){
                require_once($CFG->dirroot.'/local/costcenter/lib.php');
                $categorycontext = (new \local_users\lib\accesslib())::get_module_context();
                local_costcenter_set_costcenter_path($this->_customdata, $prefix = 'filter');
                local_costcenter_get_hierarchy_fields($mform, $this->_ajaxformdata, $this->_customdata,null, false, 'local_learningplan', $categorycontext, $multiple = true, $prefix = 'filter');
                continue;
            }else{
                $filter = $value;
            }
            $core_component = new core_component();
            $courses_plugin_exist = $core_component::get_plugin_directory('local', $filter);
            if ($courses_plugin_exist) {
                require_once($CFG->dirroot . '/local/' . $filter . '/lib.php');
                $functionname = $value.'_filter';
                $functionname($mform);
            }
        }
        
        $buttonarray = array();
        $applyclassarray = array('class' => 'form-submit','onclick' => '(function(e){ require("local_costcenter/cardPaginate").filteringData(e,"'.$submitid.'") })(event)');
        $cancelclassarray = array('class' => 'form-submit','onclick' => '(function(e){ require("local_costcenter/cardPaginate").resetingData(e,"'.$submitid.'") })(event)');
        $buttonarray[] = &$mform->createElement('button', 'filter_apply', get_string('apply','local_courses'), $applyclassarray);
        $buttonarray[] = &$mform->createElement('button', 'cancel', get_string('reset','local_courses'), $cancelclassarray);
    
        $mform->addGroup($buttonarray, 'buttonar', '', array(' '), false);
        $mform->disable_form_change_checker();

    }
     /**
     * Validation.
     *
     * @param array $data
     * @param array $files
     * @return array the errors that were found
     */
    function validation($data, $files) {
        global $DB;

        $errors = parent::validation($data, $files);
        return $errors;
    }
}
