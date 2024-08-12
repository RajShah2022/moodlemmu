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
 * @subpackage local_courses
 */

namespace local_courses\form;

use moodleform;
use context_course;
use context_coursecat;
use core_component;

defined('MOODLE_INTERNAL') || die;
require_once($CFG->libdir . '/formslib.php');
require_once($CFG->libdir . '/completionlib.php');
require_once($CFG->dirroot . '/local/users/lib.php');
require_once($CFG->dirroot . '/local/courses/lib.php');
require_once($CFG->dirroot . '/local/costcenter/lib.php');
require_once($CFG->dirroot . '/local/custom_fields/lib.php');
//require_once($CFG->libdir. '/coursecatlib.php');

class custom_course_form extends moodleform {
    protected $course;
    protected $context;
    public $formstatus;
    public function __construct($action = null, $customdata = null, $method = 'post', $target = '', $attributes = null, $editable = true, $formdata = null) {

        global $USER;

        $this->formstatus = array(
            'manage_course' => get_string('manage_course', 'local_courses'),
            'other_details' => get_string('courseother_details', 'local_courses'),
        );
        $costcenterdepth = local_costcenter_get_fields();

        $depth = count($costcenterdepth);
        // if ($USER->useraccess['currentroleinfo']['depth'] < $depth) {

        //     $this->formstatus['target_audience'] = get_string('target_audience', 'local_users');
        // }
        parent::__construct($action, $customdata, $method, $target, $attributes, $editable, $formdata);
    }
    /**
     * Form definition.
     */
    function definition() {
        global $DB, $OUTPUT, $CFG, $PAGE, $USER;

        $mform    = $this->_form;
        $course        = $this->_customdata['course']; // this contains the data of this form
        $course_id        = $this->_customdata['courseid']; // this contains the data of this form
        $category      = $this->_customdata['category'];
        $childfieldid = $this->_customdata['childfieldid'];
        $formstatus = $this->_customdata['form_status'];
        $get_coursedetails = $this->_customdata['get_coursedetails'];
        $editoroptions = $this->_customdata['editoroptions'];
        $returnto = $this->_customdata['returnto'];
        $returnurl = $this->_customdata['returnurl'];
        $categorycontext = (new \local_courses\lib\accesslib())::get_module_context();
        $formheaders = array_keys($this->formstatus);
        $formheader = $formheaders[$formstatus];
        $costcenterid = $this->_ajaxformdata['open_costcenterid'];
        if (empty($category)) {
            $category = $CFG->defaultrequestcategory;
        }

        if (!empty($course->id)) {
            $coursecontext = context_course::instance($course->id);
            $context = $coursecontext;
            $categorycontext = context_coursecat::instance($course->category);
        } else {
            $coursecontext = null;
            $categorycontext = context_coursecat::instance($category);
            $context = $categorycontext;
        }

        $courseconfig = get_config('moodlecourse');

        $this->course  = $course;
        $this->context = $context;

        // Form definition with new course defaults.
        $mform->addElement('hidden', 'returnto', null);
        $mform->setType('returnto', PARAM_ALPHANUM);
        $mform->setConstant('returnto', $returnto);

        $mform->addElement('hidden', 'childfieldid', $childfieldid);
        $mform->setType('childfieldid', PARAM_RAW);

        $mform->addElement('hidden', 'form_status', $formstatus);
        $mform->setType('form_status', PARAM_ALPHANUM);

        $mform->addElement('hidden', 'returnurl', null);
        $mform->setType('returnurl', PARAM_LOCALURL);
        $mform->setConstant('returnurl', $returnurl);

        $mform->addElement('hidden', 'getselectedclients');
        $mform->setType('getselectedclients', PARAM_BOOL);

        $defaultformat = $courseconfig->format;

        if (empty($course->id)) {
            $courseid = 0;
        } else {
            $courseid = $id = $course->id;
        }

        //For Announcements activity
        $mform->addElement('hidden', 'newsitems', $courseconfig->newsitems);

        $mform->addElement('hidden', 'id', $courseid, array('id' => 'courseid'));
        $mform->setType('id', PARAM_INT);

        $categorycontext = (new \local_courses\lib\accesslib())::get_module_context($courseid);
        $core_component = new core_component();
        if ($formstatus == 0) {

            local_costcenter_get_hierarchy_fields($mform, $this->_ajaxformdata, $this->_customdata, range(1, HIERARCHY_LEVELS), false, 'local_costcenter', $categorycontext, $multiple = true,
            $prefix = '',
            $open_path = '',
            $ismandatory = true);

            $mform->addElement('text', 'fullname', get_string('course_name', 'local_courses'), 'maxlength="254" size="50"');
            $mform->addHelpButton('fullname', 'course_name', 'local_courses');

            if (!empty($course->id) and !has_capability('moodle/course:changefullname', $categorycontext)) {
                $mform->hardFreeze('fullname');
                $mform->setConstant('fullname', $course->fullname);
            } elseif (has_capability('moodle/course:changefullname', $categorycontext)) {

                $mform->addRule('fullname', get_string('missingfullname', 'local_courses'), 'required', null, 'client');
                $mform->setType('fullname', PARAM_TEXT);
            }

            $mform->addElement('text', 'shortname', get_string('coursecode', 'local_courses'), 'maxlength="100" size="20"');
            $mform->addHelpButton('shortname', 'coursecode', 'local_courses');

            if (!empty($course->id) and !has_capability('moodle/course:changeshortname', $categorycontext)) {
                $mform->hardFreeze('shortname');
                $mform->setConstant('shortname', $course->shortname);
            } elseif (has_capability('moodle/course:changefullname', $categorycontext)) {

                $mform->addRule('shortname', get_string('missingshortname', 'local_courses'), 'required', null, 'client');
                $mform->setType('shortname', PARAM_TEXT);
            }

            $plugin_exists = \core_component::get_plugin_directory('local', 'custom_category');

            if ($plugin_exists) {

                $performance_types = array();
                $select = array();
                $select[null] = get_string('select_ptype', 'local_courses');
                $performance_types[0] = get_string('select_ptype', 'local_courses');
                $sql = "SELECT * FROM {local_custom_category} WHERE parentid = :parentid AND type = :type ";
                if (isset($costcenterid) && $costcenterid != 0) {
                    $sql .= " AND costcenterid = :costcenterid";
                }
                $ptype_categories = $DB->get_records_sql($sql, array('parentid' => 0, 'type' => 0, 'costcenterid' => $costcenterid));
                if ($ptype_categories) {
                    foreach ($ptype_categories as $ptype_category) {
                        //$performance_types[$ptype_category->id] = $ptype_category->fullname;
                        $child_categories = $DB->get_records_sql("SELECT * FROM {local_custom_category} WHERE parentid = :parentid AND parentid <> 0 ", array('parentid' => $ptype_category->id));
                        foreach ($child_categories as $child_category) {
                            $performance_types[$child_category->id] = $ptype_category->fullname . ' / ' . $child_category->fullname;
                        }
                    }
                }

                $mform->addElement('select', 'performancecatid', get_string('performance_type', 'local_courses'), $performance_types);
                $mform->addRule('performancecatid', null, 'required', null, 'client');
            }

            //for course format
            $courseformats = get_sorted_course_formats(true);
            $formcourseformats = array();
            foreach ($courseformats as $courseformat) {
                $formcourseformats[$courseformat] = get_string('pluginname', "format_$courseformat");
            }

            if (isset($course->format)) {
                $course->format = course_get_format($course)->get_format(); // replace with default if not found
                if (!in_array($course->format, $courseformats)) {
                    // this format is disabled. Still display it in the dropdown
                    $formcourseformats[$course->format] = get_string(
                        'withdisablednote',
                        'moodle',
                        get_string('pluginname', 'format_' . $course->format)
                    );
                }
            }
            $mform->addElement('select', 'format', get_string('format'), $formcourseformats);
            $mform->addHelpButton('format', 'format');
            $mform->setDefault('format', $defaultformat);

            // $mform->addElement('text', 'open_coursecompletiondays', get_string('coursecompday', 'local_courses'));
            // $mform->setType('open_coursecompletiondays', PARAM_TEXT);
            // $mform->addRule('open_coursecompletiondays', get_string('numeric', 'local_users'), 'numeric', 'numeric', 'client');

            $mform->addElement('select', 'open_difficultylevel', get_string('difficultylevel', 'local_courses'), difficulty_levels());
            $mform->addRule('open_difficultylevel', null, 'required', null, 'client');
            $mform->setType('open_difficultylevel', PARAM_INT);

            $manageselfenrol = array();
            $manageselfenrol[] = $mform->createElement('radio', 'selfenrol', '', get_string('yes'), 1, $attributes);
            $manageselfenrol[] = $mform->createElement('radio', 'selfenrol', '', get_string('no'), 0, $attributes);
            $mform->addGroup(
                $manageselfenrol,
                'selfenrol',
                get_string('need_self_enrol', 'local_courses'),
                array('&nbsp;&nbsp;'),
                false
            );
            $mform->addHelpButton('selfenrol', 'selfenrolcourse', 'local_courses');

            $manageapproval = array();
            $manageapproval[] = $mform->createElement('radio', 'approvalreqd', '', get_string('yes'), 1, $attributes);
            $manageapproval[] = $mform->createElement('radio', 'approvalreqd', '', get_string('no'), 0, $attributes);
            $mform->addGroup(
                $manageapproval,
                'approvalreqd',
                get_string('need_manage_approval', 'local_courses'),
                array('&nbsp;&nbsp;'),
                false
            );
            $mform->addHelpButton('approvalreqd', 'approvalreqdcourse', 'local_courses');
            $mform->hideIf('approvalreqd', 'selfenrol', 'neq', '1');

            // Completion tracking.
            $mform->addElement('hidden', 'enablecompletion');
            $mform->setType('enablecompletion', PARAM_INT);
            $mform->setDefault('enablecompletion', 1);

            $mform->addElement('editor', 'summary_editor', get_string('coursesummary', 'local_courses'), null, $editoroptions);
            $mform->addHelpButton('summary_editor', 'coursesummary');
            $mform->setType('summary_editor', PARAM_RAW);
            $summaryfields = 'summary_editor';

            if ($overviewfilesoptions = course_overviewfiles_options($course)) {
                $mform->addElement('filemanager', 'overviewfiles_filemanager', get_string('courseoverviewfiles', 'local_courses'), null, $overviewfilesoptions);
                $mform->addHelpButton('overviewfiles_filemanager', 'courseoverviewfiles');
                $summaryfields .= ',overviewfiles_filemanager';
            }
        } elseif ($formstatus == 1) {
            // if($this->course->open_path){
            //     if(!is_siteadmin()){
            //         $patharr = explode(',', $this->course->open_path);
            //         $keyval = (int) array_search($USER->path, $patharr);
            //         $this->course->open_path = $patharr[$keyval];
            //     }else{
            //         $this->course->open_path = explode(',', $this->course->open_path)[0];
            //     }
            // }
            // list($zero, $org, $ctr, $bu, $cu, $territory) = explode("/", $this->course->open_path);
            // $mform->addElement('hidden', 'open_costcenterid');
            // $mform->setConstant('open_costcenterid', $org);

            // local_costcenter_get_hierarchy_fields($mform, $this->_ajaxformdata, $this->_customdata, range(2, HIERARCHY_LEVELS), true, 'local_costcenter', $categorycontext, true);
            $mform->addElement('text', 'open_points', get_string('points', 'local_courses'));
            $mform->addHelpButton('open_points', 'open_pointscosurse', 'local_courses');
            $mform->setType('open_points', PARAM_INT);
            $costcenterid = explode('/', $this->course->open_path)[1];
            get_custom_fields($costcenterid, $mform, $moduletype = 'course');
            
            
            $plugin_exists = \core_component::get_plugin_directory('local', 'skillrepository');
            if ($plugin_exists) {
                $costcenterpathconcatsql = (new \local_costcenter\lib\accesslib())::get_costcenter_path_field_concatsql($columnname = 'open_path', $costcenterpath = $this->course->open_path);
                $skillselect = array(0 => get_string('select_skill', 'local_courses'));
                $skillcostcentersql = "SELECT id,name FROM {local_skill}
                                    WHERE 1=1 $costcenterpathconcatsql ";

                $skills = $DB->get_records_sql_menu($skillcostcentersql);

                if (!empty($skills)) {
                    $skillselect = $skillselect + $skills;
                }

                $mform->addElement('select',  'open_skill', get_string('open_skillcourse', 'local_courses'), $skillselect);
                $mform->addHelpButton('open_skill', 'open_skillcourse', 'local_courses');
                $mform->setType('open_skill', PARAM_INT);

                $levelselect = array(0 => get_string('select_level', 'local_courses'));

                $levelsql = "SELECT id,name FROM {local_course_levels}
                                    WHERE 1=1 $costcenterpathconcatsql ";

                $levels = $DB->get_records_sql_menu($levelsql);

                if (!empty($levels)) {
                    $levelselect = $levelselect + $levels;
                }
                $mform->addElement('select',  'open_level', get_string('open_levelcourse', 'local_courses'), $levelselect);
                $mform->addHelpButton('open_level', 'open_levelcourse', 'local_courses');
                $mform->setType('open_level', PARAM_INT);
            }

            // $mform->addElement(
            //     'date_time_selector',
            //     'startdate',
            //     get_string('startdate', 'local_courses'),
            //     array()
            // );
            // $mform->addHelpButton('startdate', 'startdate');

            // $mform->addElement('date_time_selector', 'enddate', get_string('enddate', 'local_courses'), array('optional' => false));
            // $mform->addHelpButton('enddate', 'enddate');

            $certificate_plugin_exist = $core_component::get_plugin_directory('tool', 'certificate');
            if ($certificate_plugin_exist) {
                $checkboxes = array();
                $checkboxes[] = $mform->createElement('advcheckbox', 'map_certificate', null, '', array(), array(0, 1));
                $mform->addGroup($checkboxes, 'map_certificate', get_string('add_certificate', 'local_courses'), array(' '), false);
                $mform->addHelpButton('map_certificate', 'add_certificate', 'local_courses');

                $select = array(0 => get_string('select_certificate', 'local_courses'));
                $certificatesql = "SELECT id,name FROM {tool_certificate_templates}
                                    WHERE 1=1 AND open_path=:openpath";

                $cert_templates = $DB->get_records_sql_menu($certificatesql, array('openpath' => "/" . $costcenterid));
                $certificateslist = $select + $cert_templates;

                $mform->addElement('select',  'open_certificateid', get_string('certificate_template', 'local_courses'), $certificateslist);
                $mform->addHelpButton('open_certificateid', 'certificate_template', 'local_courses');
                $mform->setType('open_certificateid', PARAM_INT);
                $mform->hideIf('open_certificateid', 'map_certificate', 'neq', 1);
            }

            $handler = \core_course\customfield\course_handler::create();
            $handler->set_parent_context($categorycontext); // For course handler only.
            $handler->instance_form_definition($mform, empty($this->course->id) ? 0 : $this->course->id);
            $handler->instance_form_before_set_data($course);
        }
        // else if ($formstatus == 2) {
           
        //     $functionname = 'globaltargetaudience_elementlist';
        //     if (function_exists($functionname)) {
        //         $costcenterfields = local_costcenter_get_fields();
        //         $firstdepth = current($costcenterfields);
        //         $mform->modulecostcenterpath = $this->_customdata[$firstdepth];

        //         $functionname($mform, array('group', 'designation'), $org);
        //     }
        //     $course->module = 'course';
        //     local_users_get_custom_userprofilefields($mform, $course, 'course'); //target audience

        // }
        $mform->closeHeaderBefore('buttonar');
        $mform->disable_form_change_checker();
        // Finally set the current form data
        if (empty($course) && $course_id > 0) {
            $course = get_course($course_id);
        }
        if (!empty($this->_ajaxformdata['open_certificateid'])) {
            $course->open_certificateid = $this->_ajaxformdata['open_certificateid'];
        }
        if (!empty($course->open_certificateid)) {
            $course->map_certificate = 1;
        }

        if (!empty($this->_ajaxformdata['open_categoryid'])) {
            $course->open_categoryid = $this->_ajaxformdata['open_categoryid'];
        } else {
            $course->open_categoryid = 0;
        }
        $this->set_data($course);
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
        $form_data = data_submitted();
        // Add field validation check for duplicate shortname.
        if ($data['form_status'] == 0) {
            if ($course = $DB->get_record('course', array('shortname' => $data['shortname']), '*', IGNORE_MULTIPLE)) {
                if (empty($data['id']) || $course->id != $data['id']) {
                    $errors['shortname'] = get_string('shortnametaken', '', $course->fullname);
                }
            }
            if (empty(trim($data['shortname']))) {
                $errors['shortname'] = get_string('missingshortname', 'local_courses');
            } else {
                $spacescount = explode(' ', trim($data['shortname']));
                if (count($spacescount) > 1) {
                    $errors['shortname'] = get_string('missingshortname', 'local_courses');
                }
            }
            if ((empty(trim($data['fullname'])) && $data['form_status'] == 0) || strpos($data['fullname'], '"') !== false) {
                $errors['fullname'] = get_string('missingfullname', 'local_courses');
            }
            if (isset($data['performancecatid']) && $data['form_status'] == 0) {
                if ($data['performancecatid'] == 0) {
                    $errors['performancecatid'] = get_string('pleaseselectcategory', 'local_courses');
                }
            }
            if (isset($data['open_department']) && $data['form_status'] == 0) {
                if (!$data['open_department']) {
                    $errors['open_department'] = get_string('selectopen_department', 'local_costcenter');
                }
            }
            if (isset($data['open_path']) && $data['form_status'] == 0) {
                if ($data['open_path'] == 0) {
                    $errors['open_path'] = get_string('pleaseselectorganization', 'local_courses');
                }
            }
            if (isset($data['identifiedtype']) && $data['form_status'] == 0) {
                if ($data['identifiedtype'] == 0) {
                    $errors['identifiedtype'] = get_string('pleaseselectidentifiedtype', 'local_courses');
                }
            }
            if (isset($data['open_coursecompletiondays']) && $data['open_coursecompletiondays']) {
                $value = $data['open_coursecompletiondays'];
                $intvalue = (int)$value;

                if (!("$intvalue" === "$value") || $intvalue < 0) {
                    $errors['open_coursecompletiondays'] = get_string('numeric', 'local_classroom');
                }
            }
        }
        if ($data['form_status'] == 1) {
            if (isset($data['startdate']) && isset($data['enddate'])) {
                if ($data['enddate'] <= $data['startdate']) {
                    $errors['enddate'] = get_string('nosameenddate', 'local_courses');
                }
            }
            if (isset($data['map_certificate']) && $data['map_certificate'] == 1  && isset($data['open_certificateid']) && $data['open_certificateid'] == 0) {
                $errors['open_certificateid'] = get_string('err_certificate', 'local_courses');
            }

            if (isset($data['open_points']) && $data['open_points']) {
                $value = $data['open_points'];
                $intvalue = (int)$value;

                if (!("$intvalue" === "$value") || $intvalue < 0) {
                    $errors['open_points'] = get_string('numeric', 'local_classroom');
                }
            }
        }

        $errors = array_merge($errors, enrol_course_edit_validation($data, $this->context));
        return $errors;
    }
}
