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
define('elearning', 1);
define('courses', 1);

if (file_exists($CFG->dirroot . '/local/costcenter/lib.php')) {
    require_once($CFG->dirroot . '/local/costcenter/lib.php');
}
require_once($CFG->dirroot . '/user/selector/lib.php');
require_once($CFG->libdir . '/completionlib.php');
require_once($CFG->dirroot . '/completion/completion_completion.php');

use \local_courses\form\custom_course_form as custom_course_form;
use \local_courses\form\custom_courseevidence_form as custom_courseevidence_form;

defined('MOODLE_INTERNAL') || die();

/**
 * process the mass enrolment
 * @param csv_import_reader $cir  an import reader created by caller
 * @param Object $course  a course record from table mdl_course
 * @param Object $context  course context instance
 * @param Object $data    data from a moodleform
 * @return string  log of operations
 */
function mass_enroll($cir, $course, $context, $data) {
    global $CFG, $DB, $USER;
    require_once($CFG->dirroot . '/group/lib.php');

    $result = '';

    $courseid = $course->id;
    $roleid = $data->roleassign;
    $useridfield = $data->firstcolumn;
    $coursecostcenter = $DB->get_field('course', 'open_path', array('id' => $data->id));
    $costcenter = explode('/', $coursecostcenter)[1];

    $enrollablecount = 0;
    $createdgroupscount = 0;
    $createdgroupingscount = 0;
    $createdgroups = '';
    $createdgroupings = '';

    $plugin = enrol_get_plugin('manual');
    //Moodle 2.x enrolment and role assignment are different
    // make sure couse DO have a manual enrolment plugin instance in that course
    //that we are going to use (only one instance is allowed @see enrol/manual/lib.php get_new_instance)
    // thus call to get_record is safe
    $instance = $DB->get_record('enrol', array('courseid' => $course->id, 'enrol' => 'manual'));
    if (empty($instance)) {
        // Only add an enrol instance to the course if non-existent
        $enrolid = $plugin->add_instance($course);
        $instance = $DB->get_record('enrol', array('id' => $enrolid));
    }

    // init csv import helper
    $notification = new \local_courses\notification();
    $type = 'course_enrol';
    $notificationdata = $notification->get_existing_notification($course, $type);

    $cir->init();

    while ($fields = $cir->next()) {
        $a = new stdClass();
        if (empty($fields))
            continue;
        $coscenter = $DB->get_field('course', 'open_path', array('id' => $course->id));
        $coscenter_name = $DB->get_field('local_costcenter', 'shortname', array('id' => $coscenter));

        $string = strtolower($coscenter_name);

        // 1st column = id Moodle (idnumber,username or email)
        // get rid on eventual double quotes unfortunately not done by Moodle CSV importer
        /*****Checking with all costcenters*****/

        $fields[0] = str_replace('"', '', trim($fields[0]));
        $fieldcontcat = $string . $fields[0];
        /******The below code is for the AH checking condtion if AH any user can be enrolled else if OH only his costcenter users enrol*****/
        $categorycontext = (new \local_courses\lib\accesslib())::get_module_context($course->id);

        /*First Condition To validate users*/
        $sql = "SELECT u.* from {user} u where u.deleted=0 and u.suspended=0 and u.$useridfield='$fields[0]' AND ((u.open_path LIKE '/$costcenter/%' OR u.open_path LIKE '/$costcenter'))";
        //$sql
        if (!(is_siteadmin())) {

            $sql .= (new \local_courses\lib\accesslib())::get_costcenter_path_field_concatsql($columnname = 'u.open_path', $costcenterpath = null, $datatype = 'lowerandsamepath');
        }
        $sql .= " and u.id <> {$USER->id} ";
        if (!$user = $DB->get_record_sql($sql)) {
            $result .= '<div class="alert alert-error">' . get_string('im:user_unknown', 'local_courses', $fields[0]) . '</div>';
            continue;
        }

        $id = $DB->get_field('course', 'open_path', array('id' => $course->id));
        /** The below code is for the AH checking condtion if AH any user can be enrolled else if OH only his costcenter users enrol **/
        if (!is_siteadmin()  && has_capability('local/costcenter:assign_multiple_departments_manage', $categorycontext)) {

            $pathlike = $id . '/%';
            $sql = " open_path like '{$pathlike}' AND ";
        } else {

            $sql = " ";
        }
        /*Second Condition To validate users*/
        if (!$DB->record_exists_sql("select id from {user} where $sql  id=$user->id")) {

            $costcentername = $DB->get_field('local_costcenter', 'fullname', array('id' => $course->costcenter));
            $cs_object = new stdClass();
            $cs_object->csname = $costcentername;
            $cs_object->user   = fullname($user);
            $result .= '<div class="alert alert-error">' . get_string('im:user_notcostcenter', 'local_courses', $cs_object) . '</div>';
            continue;
        }

        //already enroled ?

        $instance_auto = $DB->get_field('enrol', 'id', array('courseid' => $course->id, 'enrol' => 'auto'));
        $instance_self = $DB->get_field('enrol', 'id', array('courseid' => $course->id, 'enrol' => 'self'));

        if (!$instance_auto) {
            $instance_auto = 0;
        }
        if (!$instance_self) {
            $instance_self = 0;
        }

        $enrol_ids = $instance_auto . "," . $instance_self . "," . $instance->id;

        $sql = "select id from {user_enrolments} where enrolid IN ($enrol_ids) and userid=$user->id";
        $enrolormnot = $DB->get_field_sql($sql);

        if (user_has_role_assignment($user->id, $roleid, $context->id)) {
            $result .= '<div class="alert alert-error">' . get_string('im:already_in', 'local_courses', fullname($user)) . '</div>';
        } elseif ($enrolormnot) {
            $result .= '<div class="alert alert-error">' . get_string('im:already_in', 'local_courses', fullname($user)) . '</div>';
            continue;
        } else {
            //TODO take care of timestart/timeend in course settings
            // done in rev 1.1
            $timestart = $DB->get_field('course', 'startdate', array('id' => $course->id));
            $timeend = 0;
            // not anymore so easy in Moodle 2.x
            // Enrol the user with this plugin instance (unfortunately return void, no more status )
            $plugin->enrol_user($instance, $user->id, $roleid, $timestart, $timeend);

            if ($notificationdata) {
                $notification->send_course_email($course, $user, $type, $notificationdata);
            }
            $result .= '<div class="alert alert-success">' . get_string('im:enrolled_ok', 'local_courses', fullname($user)) . '</div>';
            $enrollablecount++;
        }

        $group = str_replace('"', '', trim($fields[1]));
        // 2nd column ?
        if (empty($group)) {
            $result .= "";
            continue; // no group for this one
        }

        // create group if needed
        if (!($gid = mass_enroll_group_exists($group, $courseid))) {
            if ($data->creategroups) {
                if (!($gid = mass_enroll_add_group($group, $courseid))) {
                    $a->group = $group;
                    $a->courseid = $courseid;
                    $result .= '<div class="alert alert-error">' . get_string('im:error_addg', 'local_courses', $a) . '</div>';
                    continue;
                }
                $createdgroupscount++;
                $createdgroups .= " $group";
            } else {
                $result .= '<div class="alert alert-error">' . get_string('im:error_g_unknown', 'local_courses', $group) . '</div>';
                continue;
            }
        }

        // if groupings are enabled on the site (should be ?)
        if (!($gpid = mass_enroll_grouping_exists($group, $courseid))) {
            if ($data->creategroupings) {
                if (!($gpid = mass_enroll_add_grouping($group, $courseid))) {
                    $a->group = $group;
                    $a->courseid = $courseid;
                    $result .= '<div class="alert alert-error">' . get_string('im:error_add_grp', 'local_courses', $a) . '</div>';
                    continue;
                }
                $createdgroupingscount++;
                $createdgroupings .= " $group";
            } else {
                // don't complains,
                // just do the enrolment to group
            }
        }
        // if grouping existed or has just been created
        if ($gpid && !(mass_enroll_group_in_grouping($gid, $gpid))) {
            if (!(mass_enroll_add_group_grouping($gid, $gpid))) {
                $a->group = $group;
                $result .= '<div class="alert alert-error">' . get_string('im:error_add_g_grp', 'local_courses', $a) . '</div>';
                continue;
            }
        }

        // finally add to group if needed
        if (!groups_is_member($gid, $user->id)) {
            $ok = groups_add_member($gid, $user->id);
            if ($ok) {
                $result .= '<div class="alert alert-success">' . get_string('im:and_added_g', 'local_courses', $group) . '</div>';
            } else {
                $result .= '<div class="alert alert-error">' . get_string('im:error_adding_u_g', 'local_courses', $group) . '</div>';
            }
        } else {
            $result .= '<div class="alert alert-notice">' . get_string('im:already_in_g', 'local_courses', $group) . '</div>';
        }
    }
    $result .= '<br />';
    //recap final
    $result .= get_string('im:stats_i', 'local_courses', $enrollablecount) . "";

    return $result;
}

/**
 * Enter description here ...
 * @param string $newgroupname
 * @param int $courseid
 * @return int id   Moodle id of inserted record
 */
function mass_enroll_add_group($newgroupname, $courseid) {
    $newgroup = new stdClass();
    $newgroup->name = $newgroupname;
    $newgroup->courseid = $courseid;
    $newgroup->lang = current_language();
    return groups_create_group($newgroup);
}

/**
 * Enter description here ...
 * @param string $newgroupingname
 * @param int $courseid
 * @return int id Moodle id of inserted record
 */
function mass_enroll_add_grouping($newgroupingname, $courseid) {
    $newgrouping = new StdClass();
    $newgrouping->name = $newgroupingname;
    $newgrouping->courseid = $courseid;
    return groups_create_grouping($newgrouping);
}

/**
 * @param string $name group name
 * @param int $courseid course
 * @return string or false
 */
function mass_enroll_group_exists($name, $courseid) {
    return groups_get_group_by_name($courseid, $name);
}

/**
 * @param string $name group name
 * @param int $courseid course
 * @return string or false
 */
function mass_enroll_grouping_exists($name, $courseid) {
    return groups_get_grouping_by_name($courseid, $name);
}

/**
 * @param int $gid group ID
 * @param int $gpid grouping ID
 * @return mixed a fieldset object containing the first matching record or false
 */
function mass_enroll_group_in_grouping($gid, $gpid) {
    global $DB;
    $sql = "SELECT * from {groupings_groups}
   where groupingid = ?
   and groupid = ?";
    $params = array($gpid, $gid);
    return $DB->get_record_sql($sql, $params, IGNORE_MISSING);
}

/**
 * @param int $gid group ID
 * @param int $gpid grouping ID
 * @return bool|int true or new id
 * @throws dml_exception A DML specific exception is thrown for any errors.
 */
function mass_enroll_add_group_grouping($gid, $gpid) {
    global $DB;
    $new = new stdClass();
    $new->groupid = $gid;
    $new->groupingid = $gpid;
    $new->timeadded = time();
    return $DB->insert_record('groupings_groups', $new);
}

/**
 * Serve the new course form as a fragment.
 *
 * @param array $args List of named arguments for the fragment loader.
 * @return string
 */
function local_courses_output_fragment_custom_course_form($args) {
    global $DB, $CFG, $PAGE;
    $args = (object) $args;
    $context = $args->context;
    $renderer = $PAGE->get_renderer('local_courses');
    $courseid = $args->courseid;
    $o = '';
    if ($courseid) {
        $course = get_course($courseid);
        $course = course_get_format($course)->get_course();
        $category = $course->category;
        $coursecontext = context_course::instance($course->id);
    } else {
        $category = $CFG->defaultrequestcategory;
    }
    $formdata = [];

    if (!empty($args->jsonformdata)) {

        $serialiseddata = json_decode($args->jsonformdata);
        if (is_object($serialiseddata)) {
            $formdata = (array)$serialiseddata;
        } else {
            parse_str($serialiseddata, $formdata);
        }
    }

    if (!empty($course) && empty($formdata)) {
        $formdata = clone $course;
        $formdata = (array)$formdata;
    }

    if ($courseid > 0) {
        $heading = get_string('updatecourse', 'local_courses');
        $collapse = false;
        $data = $DB->get_record('course', array('id' => $courseid));
        $categories = $DB->get_records('local_custom_fields_mapped', array('moduletype' => 'course', 'moduleid' => $courseid));
        if ($categories) {
            foreach ($categories as $parentcat) {
                $formdata['customfield_' . $parentcat->parentid] = $parentcat->category;
            }
        }
    }
    if ($courseid) {
        // Populate course tags.
        $editoroptions = array('maxfiles' => EDITOR_UNLIMITED_FILES, 'maxbytes' => $CFG->maxbytes, 'trusttext' => false, 'noclean' => true, 'autosave' => false);
        $overviewfilesoptions = course_overviewfiles_options($course);
        // Add context for editor.
        $editoroptions['context'] = $coursecontext;
        $editoroptions['subdirs'] = file_area_contains_subdirs($coursecontext, 'course', 'summary', 0);
        $course = file_prepare_standard_editor($course, 'summary', $editoroptions, $coursecontext, 'course', 'summary', 0);
        if ($overviewfilesoptions) {
            file_prepare_standard_filemanager($course, 'overviewfiles', $overviewfilesoptions, $coursecontext, 'course', 'overviewfiles', 0);
        }
        $get_coursedetails = $DB->get_record('course', array('id' => $course->id));
    } else {
        // Editor should respect category context if course context is not set.
        $editoroptions['context'] = $catcontext;
        $editoroptions['subdirs'] = 0;
        $editoroptions['autosave'] = 0;
        $course = file_prepare_standard_editor($course, 'summary', $editoroptions, null, 'course', 'summary', null);
        if ($overviewfilesoptions) {
            file_prepare_standard_filemanager($course, 'overviewfiles', $overviewfilesoptions, null, 'course', 'overviewfiles', 0);
        }
    }

    $formdata['open_points'] = (!empty($formdata['open_points'])) ? $formdata['open_points'] : 0;
    $plugin_exists = \core_component::get_plugin_directory('local', 'custom_matrix');
    if ($plugin_exists && !empty($formdata['performancecatid'])) {
        $performanceparentid = $DB->get_field('local_custom_category', 'parentid', array('id' => $formdata['performancecatid']));
        $formdata['performanceparentid'] = $performanceparentid;
    }
    $params = array(
        'course' => $course,
        'category' => $category,
        'editoroptions' => $editoroptions,
        'returnto' => $returnto,
        'get_coursedetails' => $get_coursedetails,
        'form_status' => $args->form_status,
        'costcenterid' => $data->open_path,
    );
    local_costcenter_set_costcenter_path($formdata);

    $mform = new custom_course_form(null, $params, 'post', '', null, true, $formdata);
    // Used to set the courseid.

    $mform->set_data($formdata);

    if (!empty($args->jsonformdata) && strlen($args->jsonformdata) > 2) {
        // If we were passed non-empty form data we want the mform to call validation functions and show errors.
        $mform->is_validated();
    }
    $formheaders = array_keys($mform->formstatus);
    $nextform = array_key_exists($args->form_status, $formheaders);
    if ($nextform === false) {
        return false;
    }
    ob_start();
    $formstatus = array();
    foreach (array_values($mform->formstatus) as $k => $mformstatus) {
        $activeclass = $k == $args->form_status ? 'active' : '';
        $formstatus[] = array('name' => $mformstatus, 'activeclass' => $activeclass, 'form-status' => $k);
    }
    $formstatusview = new \local_courses\output\form_status($formstatus);
    $o .= $renderer->render($formstatusview);
    $mform->display();
    $o .= ob_get_contents();
    ob_end_clean();
    return $o;
}

/**
 * Serve the table for course status
 *
 * @param array $args List of named arguments for the fragment loader.
 * @return string
 */
function local_courses_output_fragment_coursestatus_display($args) {
    global $DB, $CFG, $PAGE, $OUTPUT, $USER;
    $args = (object) $args;
    $course = $DB->get_record('course', array('id' => $args->courseid));
    $info = new completion_info($course);

    // Is course complete?
    $coursecomplete = $info->is_course_complete($USER->id);

    // Has this user completed any criteria?
    $criteriacomplete = $info->count_course_user_data($USER->id);
    $params = array(
        'userid' => $USER->id,
        'course' => $course->id,
    );
    $completions = $info->get_completions($USER->id);
    $ccompletion = new completion_completion($params);

    $rows = array();
    // Loop through course criteria.
    foreach ($completions as $completion) {
        $criteria = $completion->get_criteria();
        $row = array();
        $row['type'] = $criteria->criteriatype;
        $row['title'] = $criteria->get_title();
        $row['complete'] = $completion->is_complete();
        $row['timecompleted'] = $completion->timecompleted;
        $row['details'] = $criteria->get_details($completion);
        $rows[] = $row;
    }
    // Print table.
    $last_type = '';
    $agg_type = false;

    $table = new html_table();
    $table->head = array(get_string('criteriagroup', 'format_tabtopics'), get_string('criteria', 'format_tabtopics'), get_string('requirement', 'format_tabtopics'), get_string('complete', 'format_tabtopics'), get_string('completiondate', 'format_tabtopics'));
    $table->size = array('20%', '20%', '25%', '5%', '30%');
    $table->align = array('left', 'left', 'left', 'center', 'center');
    $table->id = 'scrolltable';
    foreach ($rows as $row) {
        if ($last_type !== $row['details']['type']) {
            $last_type = $row['details']['type'];
            $agg_type = true;
        } else {
            // Display aggregation type.
            if ($agg_type) {
                $agg = $info->get_aggregation_method($row['type']);
                $last_type .= '(' . html_writer::start_tag('i');
                if ($agg == COMPLETION_AGGREGATION_ALL) {
                    $last_type .= core_text::strtolower(get_string('all', 'completion'));
                } else {
                    $last_type .= core_text::strtolower(get_string('any', 'completion'));
                }
                $last_type .= html_writer::end_tag('i') . core_text::strtolower(get_string('required')) . ')';
                $agg_type = false;
            }
        }
        if ($row['timecompleted']) {
            $timecompleted = userdate($row['timecompleted'], get_string('strftimedate', 'langconfig'));
        } else {
            $timecompleted = '-';
        }
        $table->data[] = new html_table_row(array($last_type, $row['details']['criteria'], $row['details']['requirement'], $row['complete'] ? get_string('yes') : get_string('no'), $timecompleted));
    }
    $output = html_writer::table($table);
    $output .= html_writer::script("
         $(document).ready(function(){
            var table_rows = $('#scrolltable tr');
            // if(table_rows.length>6){
                $('#scrolltable').dataTable({
                    'searching': false,
                    'language': {
                        'paginate': {
                            'next': '>',
                            'previous': '<'
                        }
                    },
                    'pageLength': 5,
                });
            // }
        });
    ");
    return $output;
}

/*
* todo provides form element - courses
* @param $mform formobject
* return void
*/
function courses_filter($mform) {
    global $DB, $USER;
    $categorycontext = (new \local_courses\lib\accesslib())::get_module_context();
    $sql = "SELECT id, fullname FROM {course} WHERE id > 1 AND open_coursetype = 0 ";

    if (is_siteadmin()) {
        $courseslist = $DB->get_records_sql_menu($sql);
    } else {
        $sql .= (new \local_courses\lib\accesslib())::get_costcenter_path_field_concatsql($columnname = 'open_path');
    }
    $courseslist = $DB->get_records_sql_menu($sql);

    $select = $mform->addElement('autocomplete', 'courses', get_string('course'), $courseslist, array('placeholder' => get_string('course')));
    $mform->setType('courses', PARAM_RAW);
    $select->setMultiple(true);
}
function status_filter($mform) {
    $statusarray = array('active' => get_string('active'), 'inactive' => get_string('inactive'));
    $select = $mform->addElement('autocomplete', 'status', get_string('status'), $statusarray, array('placeholder' => get_string('user_status', 'local_users')));
    $mform->setType('status', PARAM_RAW);
    $select->setMultiple(true);
}

function coursetype_filter($mform) {
    global $DB, $USER;
    $categorycontext = (new \local_courses\lib\accesslib())::get_module_context();
    $performance_types = array();
    $select = array();
    $select[null] = get_string('select_ptype', 'local_courses');
    $performance_types[null] = $select;
    $ptype_categories = $DB->get_records('local_custom_category', array('parentid' => 0, 'type' => 0));
    if ($ptype_categories) {
        foreach ($ptype_categories as $ptype_category) {
            $performance_categories = $DB->get_records_sql_menu("SELECT * FROM {local_custom_category} WHERE parentid = :parentid AND parentid <> 0 ", array('parentid' => $ptype_category->id));
            $performance_types[$ptype_category->fullname] = $performance_categories;
        }
    }

    $select = $mform->addElement('selectgroups', 'coursetype', get_string('performance_type', 'local_courses'), $performance_types, array('placeholder' => get_string('course_type', 'local_courses')));
    $mform->setType('coursetype', PARAM_RAW);
}

/*
* todo provides form element - courses
* @param $mform formobject
* return void
*/
function elearning_filter($mform) {
    global $DB, $USER;
    $categorycontext = (new \local_courses\lib\accesslib())::get_module_context();
    if (is_siteadmin()) {
        $courseslist = $DB->get_records_sql_menu("SELECT id, fullname FROM {course} WHERE visible = 1");
    }
    $select = $mform->addElement('autocomplete', 'elearning', '', $courseslist, array('placeholder' => get_string('course_name', 'local_courses')));
    $mform->setType('elearning', PARAM_RAW);
    $select->setMultiple(true);
}

/*
* todo provides form element - categories
* @param $mform formobject
* return void
*/
function customfields_filter($mform) {
    global $DB, $USER, $CFG;
    $categorycontext = (new \local_courses\lib\accesslib())::get_module_context();
    require_once($CFG->dirroot . '/local/custom_fields/lib.php');
    $parentcatids = get_parent_customfields();
    foreach ($parentcatids as $parentcatid) {
        $parentcatid = $parentcatid->id;
        $parentcatname = $DB->get_field('local_custom_fields', 'fullname', array('id' => $parentcatid));
        $categorylist = $DB->get_records_sql_menu("SELECT id, fullname FROM {local_custom_fields} WHERE parentid =  $parentcatid");

        $select = $mform->addElement('autocomplete', 'catfilter_' . $parentcatid, $parentcatname, $categorylist, array('placeholder' => $parentcatname));
        $mform->setType('catfilter_' . $parentcatid, PARAM_RAW);
        $select->setMultiple(true);
    }
}

/*
* todo provides form element - categories
* @param $mform formobject
* return void
*/
function categories_filter($mform) {
    global $DB, $USER;

    $select = array();
    $select[null] = get_string('select_ptype', 'local_courses');

    if (is_siteadmin()) {
        $ptype_categories = $DB->get_records_sql("SELECT * FROM {local_custom_category} WHERE parentid = :parentid AND type = :type ", array('parentid' => 0, 'type' => 0));
    } else {
        $costcenterpathconcatsql = (new \local_costcenter\lib\accesslib())::get_costcenter_path_field_concatsql($columnname = "CONCAT('/',cc.costcenterid,'')");
        $ptype_categories = $DB->get_records_sql("SELECT * FROM {local_custom_category} AS cc WHERE 1=1 $costcenterpathconcatsql");
    }

    if ($ptype_categories) {
        foreach ($ptype_categories as $ptype_category) {
            //$performance_types[$ptype_category->id] = $ptype_category->fullname;
            $child_categories = $DB->get_records_sql(
                "SELECT * FROM {local_custom_category} WHERE parentid = :parentid AND parentid <> 0 ",
                array('parentid' => $ptype_category->id)
            );
            foreach ($child_categories as $child_category) {
                $performance_types[$child_category->id] = $ptype_category->fullname . ' / ' . $child_category->fullname;
            }
        }
    }

    $select = $mform->addElement('autocomplete', 'categories', get_string('category'), $performance_types, array('placeholder' => get_string('category')));
    $mform->setType('categories', PARAM_RAW);
    $select->setMultiple(true);
}
/*
* todo prints the filter form
*/
function print_filterform() {
    global $DB, $CFG;
    require_once($CFG->dirroot . '/local/courses/filters_form.php');
    $mform = new filters_form(null, array('filterlist' => array('courses', 'costcenter', 'categories')));
    if ($mform->is_cancelled()) {
        redirect($CFG->wwwroot . '/local/courses/courses.php');
    } else {
        $filterdata =  $mform->get_data();
        if ($filterdata) {
            $collapse = false;
        } else {
            $collapse = true;
        }
    }
    $heading = '<button >' . get_string('course_filters', 'local_courses') . '</button>';
    print_collapsible_region_start(' ', 'filters_form', ' ' . ' ' . $heading, false, $collapse);
    $mform->display();
    print_collapsible_region_end();
    return $filterdata;
}

/**
 * [course_enrolled_users description]
 * @param  string  $type       [description]
 * @param  integer $evaluationid [description]
 * @param  [type]  $params     [description]
 * @param  integer $total      [description]
 * @param  integer $offset    [description]
 * @param  integer $perpage    [description]
 * @param  integer $lastitem   [description]
 * @return [type]              [description]
 */
function course_enrolled_users($type = null, $course_id = 0, $params = array(), $total = 0, $offset = -1, $perpage = -1, $lastitem = 0) {

    global $DB, $USER;
    $context = (new \local_courses\lib\accesslib())::get_module_context($course_id);
    $course = $DB->get_record('course', array('id' => $course_id));
    $condition = (new \local_users\lib\accesslib())::get_costcenter_path_field_concatsql($columnname = 'u.open_path');
    $params['suspended'] = 0;
    $params['deleted'] = 0;

    if ($total == 0) {
        $sql = "SELECT u.id,concat(u.firstname,' ',u.lastname,' ','(',u.idnumber,')') as fullname";
    } else {
        $sql = "SELECT count(u.id) as total";
    }
    $sql .= " FROM {user} AS u WHERE  u.id > 2 AND u.suspended = :suspended AND u.deleted = :deleted ";
    // if($lastitem!=0){
    //    $sql.=" AND u.id > $lastitem ";
    // }
    if (!is_siteadmin()) {
        $sql .= $condition;
    }
    $sql .= " AND u.id <> $USER->id";
    if (!empty($params['email'])) {
        $sql .= " AND u.id IN ({$params['email']})";
    }
    if (!empty($params['uname'])) {
        $sql .= " AND u.id IN ({$params['uname']})";
    }
    if (!empty($params['organization'])) {
        $organizations = explode(',', $params['organization']);
        $orgsql = [];
        foreach ($organizations as $organisation) {
            $orgsql[] = " concat('/',u.open_path,'/') LIKE :organisationparam_{$organisation}";
            $params["organisationparam_{$organisation}"] = '%/' . $organisation . '/%';
        }
        if (!empty($orgsql)) {
            $sql .= " AND ( " . implode(' OR ', $orgsql) . " ) ";
        }
    }
    if (!empty($params['department'])) {
        $departments = explode(',', $params['department']);
        $deptsql = [];
        foreach ($departments as $department) {
            $deptsql[] = " concat('/',u.open_path,'/') LIKE :departmentparam_{$department}";
            $params["departmentparam_{$department}"] = '%/' . $department . '/%';
        }
        if (!empty($deptsql)) {
            $sql .= " AND ( " . implode(' OR ', $deptsql) . " ) ";
        }
    }

    if (!empty($params['subdepartment'])) {
        $subdepartments = explode(',', $params['subdepartment']);
        $subdeptsql = [];
        foreach ($subdepartments as $subdepartment) {
            $subdeptsql[] = " concat('/',u.open_path,'/') LIKE :subdepartmentparam_{$subdepartment}";
            $params["subdepartmentparam_{$subdepartment}"] = '%/' . $subdepartment . '/%';
        }
        if (!empty($subdeptsql)) {
            $sql .= " AND ( " . implode(' OR ', $subdeptsql) . " ) ";
        }
    }
    if (!empty($params['department4level'])) {
        $subdepartments = explode(',', $params['department4level']);
        $subdeptsql = [];
        foreach ($subdepartments as $department4level) {
            $subdeptsql[] = " concat('/',u.open_path,'/') LIKE :department4levelparam_{$department4level}";
            $params["department4levelparam_{$department4level}"] = '%/' . $department4level . '%';
        }
        if (!empty($subdeptsql)) {
            $sql .= " AND ( " . implode(' OR ', $subdeptsql) . " ) ";
        }
    }
    if (!empty($params['department5level'])) {
        $subdepartments = explode(',', $params['department5level']);
        $subdeptsql = [];
        foreach ($subdepartments as $department5level) {
            $subdeptsql[] = " concat('/',u.open_path,'/') LIKE :department5levelparam_{$department5level}";
            $params["department5levelparam_{$department5level}"] = '%/' . $department5level . '/%';
        }
        if (!empty($subdeptsql)) {
            $sql .= " AND ( " . implode(' OR ', $subdeptsql) . " ) ";
        }
    }
    if (!empty($params['idnumber'])) {
        $sql .= " AND u.id IN ({$params['idnumber']})";
    }
    if (!empty($params['village'])) {
        $villages = explode(',', $params['village']);
        list($villagesql, $villageparam) = $DB->get_in_or_equal($villages, SQL_PARAMS_NAMED, 'village');
        $params = array_merge($params, $villageparam);
        $sql .= " AND u.open_village {$villagesql} ";
    }
    if (!empty($params['subdistrict'])) {
        $subdistricts = explode(',', $params['subdistrict']);
        list($subdistrictsql, $subdistrictparam) = $DB->get_in_or_equal($subdistricts, SQL_PARAMS_NAMED, 'subdistrict');
        $params = array_merge($params, $subdistrictparam);
        $sql .= " AND u.open_subdistrict {$subdistrictsql} ";
    }
    if (!empty($params['district'])) {
        $districts = explode(',', $params['district']);
        list($districtsql, $districtparam) = $DB->get_in_or_equal($districts, SQL_PARAMS_NAMED, 'district');
        $params = array_merge($params, $districtparam);
        $sql .= " AND u.open_district {$districtsql} ";
    }
    if (!empty($params['states'])) {
        $state = explode(',', $params['states']);
        list($statessql, $statesparam) = $DB->get_in_or_equal($state, SQL_PARAMS_NAMED, 'states');
        $params = array_merge($params, $statesparam);
        $sql .= " AND u.open_states {$statessql} ";
    }

    if (!empty($params['location'])) {

        $locations = explode(',', $params['location']);
        list($locationsql, $locationparams) = $DB->get_in_or_equal($locations, SQL_PARAMS_NAMED, 'location');
        $params = array_merge($params, $locationparams);
        $sql .= " AND u.open_location {$locationsql} ";
    }

    if (!empty($params['hrmsrole'])) {

        $hrmsroles = explode(',', $params['hrmsrole']);
        list($hrmsrolesql, $hrmsroleparams) = $DB->get_in_or_equal($hrmsroles, SQL_PARAMS_NAMED, 'hrmsrole');
        $params = array_merge($params, $hrmsroleparams);
        $sql .= " AND u.open_hrmsrole {$hrmsrolesql} ";
    }
    if (!empty($params['groups'])) {
        $group_list = $DB->get_records_sql_menu("select cm.id, cm.userid from {cohort_members} cm, {user} u where u.id = cm.userid AND u.deleted = 0 AND u.suspended = 0 AND cm.cohortid IN ({$params['groups']})");

        $groups_members = implode(',', $group_list);
        if (!empty($groups_members))
            $sql .= " AND u.id IN ({$groups_members})";
        else
            $sql .= " AND u.id =0";
    }
    if ($type == 'add') {
        $sql .= " AND u.id NOT IN (SELECT ue.userid
                             FROM {user_enrolments} AS ue
                             JOIN {enrol} e ON (e.id = ue.enrolid and e.courseid=$course_id and (e.enrol='manual' OR e.enrol='self' OR e.enrol='auto')))";
    } elseif ($type == 'remove') {
        $sql .= " AND u.id IN (SELECT ue.userid
                             FROM {user_enrolments} AS ue
                             JOIN {enrol} e ON (e.id = ue.enrolid and e.courseid=$course_id and (e.enrol='manual' OR e.enrol='self' OR e.enrol='auto')))";
    }

    $order = " ORDER BY u.firstname  ASC ";

    if ($total == 0) {
        $availableusers = $DB->get_records_sql_menu($sql . $order, $params, $lastitem, $perpage);
    } else {
        $availableusers = $DB->count_records_sql($sql, $params);
    }
    return $availableusers;
}

/*
* Author Rizwana
* Displays a node in left side menu
* @return  [type] string  link for the leftmenu
*/
function local_courses_leftmenunode() {
    global $DB, $USER;
    $categorycontext = (new \local_courses\lib\accesslib())::get_module_context();
    $coursecatnodes = '';
    // if(has_capability('local/custom_fields:view_custom_category', $categorycontext) || is_siteadmin()) {
    //     $coursecatnodes .= html_writer::start_tag('li', array('id'=> 'id_leftmenu_categories', 'class'=>'pull-left user_nav_div categories usernavdep'));
    //     $categories_url = new moodle_url('/local/custom_fields/index.php');
    //     $categories = html_writer::link($categories_url, '<i class="fa fa-book" aria-hidden="true" aria-label=""></i><i class="fa fa-book secbook" aria-hidden="true" aria-label=""></i><span class="user_navigation_link_text">'.get_string('leftmenu_browsecategories','local_courses').'</span>',array('class'=>'user_navigation_link'));
    //     $coursecatnodes .= $categories;
    //     $coursecatnodes .= html_writer::end_tag('li');
    // }

    if (has_capability('local/courses:view', $categorycontext) || has_capability('local/courses:manage', $categorycontext) || is_siteadmin()) {
        $coursecatnodes .= html_writer::start_tag('li', array('id' => 'id_leftmenu_browsecourses', 'class' => 'pull-left user_nav_div browsecourses'));
        $courses_url = new moodle_url('/local/courses/courses.php');
        $courses = html_writer::link($courses_url, '<i class="fa fa-book"></i><span class="user_navigation_link_text">' . get_string('manage_courses', 'local_courses') . '</span>', array('class' => 'user_navigation_link'));
        $coursecatnodes .= $courses;
        $coursecatnodes .= html_writer::end_tag('li');
    }

    return array('6' => $coursecatnodes);
}

function local_courses_quicklink_node() {
    global $CFG, $PAGE, $OUTPUT, $DB;

    $orgid  = optional_param('orgid', 0, PARAM_INT);

    $categorycontext = (new \local_courses\lib\accesslib())::get_module_context();

    $costcenterpathconcatsql = (new \local_costcenter\lib\accesslib())::get_costcenter_path_field_concatsql($columnname = 'open_path', $orgid);

    $content = '';
    if (has_capability('local/courses:view', $categorycontext) || has_capability('local/courses:manage', $categorycontext) || is_siteadmin()) {
        $PAGE->requires->js_call_amd('local_courses/courseAjaxform', 'load');
        $coursedata = array();
        if (is_siteadmin() || has_capability('local/courses:view', $categorycontext)) {
            $sql = "SELECT count(c.id) FROM {course} c
            JOIN {local_costcenter} AS co ON co.path = c.open_path
            JOIN {course_categories} AS cc ON cc.id = c.category
            WHERE c.id > 1 AND c.open_coursetype=0 ";
            // Join is removed to get the count which is displayed in local/courses
            //  JOIN {local_custom_category} As ct ON ct.id = c.performancecatid

            if (is_siteadmin() && $orgid == 0) {
                $sql .= "";
            } else {
                //costcenterid concating
                $sql .= $costcenterpathconcatsql;
            }
            $open_path = (new \local_courses\lib\accesslib())::get_costcenter_path_field_concatsql($columnname = 'c.open_path');
            $enrolsql = "SELECT count(u.id)
                    FROM {user} u
                    JOIN {user_enrolments} ue ON ue.userid = u.id
                    JOIN {enrol} e ON e.id = ue.enrolid
                    JOIN {course} c ON  e.courseid = c.id
                    WHERE e.status = 0 AND u.suspended = 0 AND u.deleted = 0
                    AND ue.status = 0 AND c.open_coursetype=0 $open_path";
            $completioncount = "SELECT count(u.id) FROM {course_completions} AS cp
                                JOIN {course} AS c ON cp.course = c.id
                                JOIN {user} AS u ON cp.userid = u.id
                                WHERE c.enablecompletion = 1
                                AND c.open_coursetype=0 $open_path";
            $enrolcount = $DB->get_field_sql($enrolsql);
            $completioncount = $DB->get_field_sql($completioncount);
            $count_courses = $DB->count_records_sql($sql);

            if ($completioncount == 0 || $enrolcount == 0) {
                $percent = 0;
            } else {
                $percent = round(($completioncount / $enrolcount) * 100);
                $percent = (int)$percent;
            }
        }
        $coursedata['node_header_string'] = get_string('manage_br_courses', 'local_courses');
        $coursedata['pluginname'] = 'courses';
        $coursedata['plugin_icon_class'] = 'fa fa-book';
        $coursedata['displaystats'] = TRUE;
        if (is_siteadmin() || (has_capability('moodle/course:create', $categorycontext) && has_capability('moodle/course:update', $categorycontext) && has_capability('local/courses:manage', $categorycontext))) {
            $coursedata['create'] = TRUE;
            $coursedata['create_element'] = html_writer::link('javascript:void(0)', get_string('create'), array('onclick' => '(function(e){ require("local_courses/courseAjaxform").init({contextid:' . $categorycontext->id . ', component:"local_courses", callback:"custom_course_form", form_status:0, plugintype: "local", pluginname: "courses"}) })(event)'));
        }
        if (has_capability('local/courses:view', $categorycontext) || has_capability('local/courses:manage', $categorycontext)) {
            $coursedata['viewlink_url'] = $CFG->wwwroot . '/local/courses/courses.php';
            $coursedata['view'] = TRUE;
            $coursedata['viewlink_title'] = get_string('view_courses', 'local_courses');
        }
        $coursedata['percentage'] = $percent;
        $coursedata['count_total'] = $count_courses;
        $coursedata['count_active'] = $enrolcount;
        $coursedata['enroll_string'] = get_string('enrolcount', 'local_courses');
        $coursedata['inactive_string'] = get_string('completioncount', 'local_courses');
        $coursedata['count_inactive'] = $completioncount;
        $coursedata['space_count'] = 'two';
        $coursedata['view_type'] = $PAGE->theme->settings->quicknavigationview;
        $content = $OUTPUT->render_from_template('block_quick_navigation/quicklink_node', $coursedata);
    }
    return array('5' => $content);
}

/**
 * function costcenterwise_courses_count
 * @todo count of courses under selected costcenter
 * @param int $costcenter costcenter
 * @param int $department department
 * @return  array courses count of each type
 */
function costcenterwise_courses_count($costcenter, $department = false, $subdepartment = false, $l4department = false, $l5department = false) {
    global $USER, $DB, $CFG;
    $params = array();
    $params['costcenterpath'] = '%/' . $costcenter . '/%';
    $countcoursesql = "SELECT count(id) FROM {course} WHERE concat('/',open_path,'/') LIKE :costcenterpath AND open_coursetype=0 ";
    if ($department) {
        $countcoursesql .= "  AND concat('/',open_path,'/') LIKE :departmentpath  ";
        $params['departmentpath'] = '%/' . $department . '/%';
    }
    if ($subdepartment) {
        $countcoursesql .= " AND concat('/',open_path,'/') LIKE :subdepartmentpath ";
        $params['subdepartmentpath'] = '%/' . $subdepartment . '/%';
    }
    if ($l4department) {
        $countcoursesql .= " AND concat('/',open_path,'/') LIKE :l4departmentpath ";
        $params['l4departmentpath'] = '%/' . $l4department . '/%';
    }
    if ($l5department) {
        $countcoursesql .= " AND concat('/',open_path,'/') LIKE :l5departmentpath ";
        $params['l5departmentpath'] = '%/' . $l5department . '/%';
    }
    $activesql = " AND visible = 1 ";
    $inactivesql = " AND visible = 0 ";

    $countcourses = $DB->count_records_sql($countcoursesql, $params);
    $activecourses = $DB->count_records_sql($countcoursesql . $activesql, $params);
    $inactivecourses = $DB->count_records_sql($countcoursesql . $inactivesql, $params);
    if ($countcourses >= 0) {
        if ($costcenter) {
            $viewcourselink_url = $CFG->wwwroot . '/local/courses/courses.php?costcenterid=' . $costcenter;
        }
        if ($department) {
            $viewcourselink_url = $CFG->wwwroot . '/local/courses/courses.php?costcenterid=' . $costcenter . '&departmentid=' . $department;
        }
        if ($subdepartment) {
            $viewcourselink_url = $CFG->wwwroot . '/local/courses/courses.php?costcenterid=' . $costcenter . '&departmentid=' . $department . '&subdepartmentid=' . $subdepartment;
        }
        if ($l4department) {
            $viewcourselink_url = $CFG->wwwroot . '/local/courses/courses.php?costcenterid=' . $costcenter . '&departmentid=' . $department . '&subdepartmentid=' . $subdepartment . '&l4department=' . $l4department;
        }
        if ($l5department) {
            $viewcourselink_url = $CFG->wwwroot . '/local/courses/courses.php?costcenterid=' . $costcenter . '&departmentid=' . $department . '&subdepartmentid=' . $subdepartment . '&l4department=' . $l4department . '&l5department=' . $l5department;
        }
    }

    if ($activecourses >= 0) {
        if ($costcenter) {
            $count_courseactivelink_url = $CFG->wwwroot . '/local/courses/courses.php?status=active&costcenterid=' . $costcenter;
        }
        if ($department) {
            $count_courseactivelink_url = $CFG->wwwroot . '/local/courses/courses.php?status=active&costcenterid=' . $costcenter . '&departmentid=' . $department;
        }
        if ($subdepartment) {
            $count_courseactivelink_url = $CFG->wwwroot . '/local/courses/courses.php?status=active&costcenterid=' . $costcenter . '&departmentid=' . $department . '&subdepartmentid=' . $subdepartment;
        }
        if ($l4department) {
            $count_courseactivelink_url = $CFG->wwwroot . '/local/courses/courses.php?status=active&costcenterid=' . $costcenter . '&departmentid=' . $department . '&subdepartmentid=' . $subdepartment . '&l4department=' . $l4department;
        }
        if ($l5department) {
            $count_courseactivelink_url = $CFG->wwwroot . '/local/courses/courses.php?status=active&costcenterid=' . $costcenter . '&departmentid=' . $department . '&subdepartmentid=' . $subdepartment . '&l4department=' . $l4department . '&l5department=' . $l5department;
        }
    }
    if ($inactivecourses >= 0) {
        if ($costcenter) {
            $count_courseinactivelink_url = $CFG->wwwroot . '/local/courses/courses.php?status=inactive&costcenterid=' . $costcenter;
        }
        if ($department) {
            $count_courseinactivelink_url = $CFG->wwwroot . '/local/courses/courses.php?status=inactive&costcenterid=' . $costcenter . '&departmentid=' . $department;
        }
        if ($subdepartment) {
            $count_courseinactivelink_url = $CFG->wwwroot . '/local/courses/courses.php?status=inactive&costcenterid=' . $costcenter . '&departmentid=' . $department . '&subdepartmentid=' . $subdepartment;
        }
        if ($l4department) {
            $count_courseinactivelink_url = $CFG->wwwroot . '/local/courses/courses.php?status=inactive&costcenterid=' . $costcenter . '&departmentid=' . $department . '&subdepartmentid=' . $subdepartment . '&l4department=' . $l4department;
        }
        if ($l5department) {
            $count_courseinactivelink_url = $CFG->wwwroot . '/local/courses/courses.php?status=inactive&costcenterid=' . $costcenter . '&departmentid=' . $department . '&subdepartmentid=' . $subdepartment . '&l4department=' . $l4department . '&l5department=' . $l5department;
        }
    }

    return array('coursecount' => $countcourses, 'activecoursecount' => $activecourses, 'inactivecoursecount' => $inactivecourses, 'viewcourselink_url' => $viewcourselink_url, 'count_courseactivelink_url' => $count_courseactivelink_url, 'count_courseinactivelink_url' => $count_courseinactivelink_url);
}

/**
 * function get_listof_courses
 * @todo all courses based  on costcenter / department
 * @param object $stable limit values
 * @param object $filterdata filterdata
 * @return  array courses
 */

function get_listof_courses($stable, $filterdata, $options = array()) {
    global $CFG, $DB, $OUTPUT, $USER, $PAGE;
    if (is_string($options)) {
        $options = json_decode($options);
    }

    $core_component = new core_component();
    require_once($CFG->dirroot . '/course/renderer.php');
    require_once($CFG->dirroot . '/local/costcenter/lib.php');

    require_once($CFG->dirroot . '/local/custom_fields/lib.php');
    require_once($CFG->dirroot . '/enrol/locallib.php');
    $autoenroll_plugin_exist = $core_component::get_plugin_directory('enrol', 'auto');
    if (!empty($autoenroll_plugin_exist)) {
        require_once($CFG->dirroot . '/enrol/auto/lib.php');
    }
    $maincheckcontext = $categorycontext = (new \local_courses\lib\accesslib())::get_module_context();

    $chelper = new coursecat_helper();
    $countsql  = "SELECT count(c.id) ";

    $selectsql = "SELECT c.id ,c.fullname, c.shortname, c.category, c.summary, c.visible, c.format, c.selfenrol, c.open_path, c.performancecatid, c.open_skill, c.open_coursecompletiondays, c.open_difficultylevel ";
    $joinsql;
    $fromsql = " FROM {course} AS c ";
    $wheresql = " WHERE c.id > 1 AND c.open_module IS NULL ";
    $orderby = " ORDER BY c.id DESC";
    $params = array();

    if (isset($filterdata->search_query) && trim($filterdata->search_query) != '') {
        $wheresql .= " AND c.fullname LIKE :search ";
        $params['search'] = '%' . trim($filterdata->search_query) . '%';
    } else {
        $searchparams = array();
    }
    if (!empty($filterdata->courses)) {
        $filtercourses = explode(',', $filterdata->courses);
        list($filtercoursessql, $filtercoursesparams) = $DB->get_in_or_equal($filtercourses, SQL_PARAMS_NAMED, 'courses', true, false);
        $params = array_merge($params, $filtercoursesparams);
        $wheresql .= " AND c.id $filtercoursessql";
    }
    if (!empty($filterdata->status)) {
        $status = explode(',', $filterdata->status);
        if (!(in_array('active', $status) && in_array('inactive', $status))) {
            if (in_array('active', $status)) {
                $wheresql .= " AND c.visible = 1 ";
            } else if (in_array('inactive', $status)) {
                $wheresql .= " AND c.visible = 0 ";
            }
        }
    }

    $costcenter_plugin_exists = \core_component::get_plugin_directory('local', 'costcenter');
    if ($costcenter_plugin_exists) {
        $costcentervalues = costcentervalues($filterdata);
        $wheresql .= $costcentervalues['where'];
        $params = array_merge($params, $costcentervalues['params']);
    }

    $custfield_plugin_exists = \core_component::get_plugin_directory('local', 'custom_fields');
    if ($custfield_plugin_exists) {
        $parentcatids = get_parent_customfields();

        if (!empty($parentcatids)) {
            $categoryquery = get_moduleid_of_mapped_customfield($filterdata, $parentcatids, 'course', 'c.id');
            $params = array_merge($params, $categoryquery['params']);
            $wheresql .= $categoryquery['sql'];
        }
    }

    $category_plugin_exists = \core_component::get_plugin_directory('local', 'custom_category');
    if ($category_plugin_exists) {
        if (!empty($filterdata->categories)) {
            $filtercategories = explode(',', $filterdata->categories);
            list($filtercategoriessql, $filtercategoriesparams) = $DB->get_in_or_equal($filtercategories, SQL_PARAMS_NAMED, 'courses', true, false);
            $params = array_merge($params, $filtercategoriesparams);
            $wheresql .= " AND (c.performancecatid $filtercategoriessql) ";
        }
    }

    $totalcourses = $DB->count_records_sql($countsql . $fromsql . $joinsql . $wheresql, $params);
    $courses = $DB->get_records_sql($selectsql . $fromsql . $joinsql . $wheresql . $orderby, $params, $stable->start, $stable->length);
    $ratings_plugin_exist = $core_component::get_plugin_directory('local', 'ratings');
    $courseslist = array();

    if (!empty($courses)) {
        $count = 0;
        foreach ($courses as $key => $course) {

            $course = (array)$course;
            local_costcenter_set_costcenter_path($course);
            $course = (object)$course;

            $course_in_list = new core_course_list_element($course);
            $context =  \context_course::instance($course->id);
            $departmentcount = 1;
            $acceslib = new local_courses\local\general_lib();
            $enrolleduser = $acceslib->get_course_enrolled_users($course->id);
            $enrolled_count = $enrolleduser['usercount'];

            $completeduser = $acceslib->get_course_enrolled_users($course->id, $status = 'complete');
            $completed_count = $completeduser['usercount'];

            $coursename = $course->fullname;

            if (strlen($coursename) > 35) {
                $coursenameCut = substr($coursename, 0, 35) . "...";
                $courseslist[$count]["coursenameCut"] = \local_costcenter\lib::strip_tags_custom($coursenameCut);
            }

            if ($ratings_plugin_exist) {
                require_once($CFG->dirroot . '/local/ratings/lib.php');
                $ratingenable = True;
                $avgratings = get_rating($course->id, 'local_courses');
                $rating_value = $avgratings->avg == 0 ? 'N/A' : $avgratings->avg/*/2*/;
            } else {
                $ratingenable = False;
                $rating_value = 'N/A';
            }
            $classname = '\local_tags\tags';
            if (class_exists($classname)) {
                $tags = new $classname;

                $tagstring = $tags->get_item_tags($component = 'local_courses', $itemtype = 'courses', $itemid = $course->id, $contextid = context_course::instance($course->id)->id, $arrayflag = 0, $more = 0);
                $tagstringtotal = $tagstring;
                if ($tagstring == "") {
                    $tagstring = 'N/A';
                } else {
                    $tagstring = strlen($tagstring) > 35 ? substr($tagstring, 0, 35) . '...' : $tagstring;
                }
                $tagenable = True;
            } else {
                $tagenable = False;
                $tagstring = '';
                $tagstringtotal = $tagstring;
            }

            if ($costcenter_plugin_exists) {
                $costcenterid = explode('/', $course->open_path)[1];
                $costcentername = (new \local_costcenter\lib\costcenter())::costcenter_field('fullname', array('id' => $costcenterid));
                $courseslist[$count]["costcentername"] = $costcentername;
            }
            $courseslist[$count]["coursename"] = \local_costcenter\lib::strip_tags_custom($coursename);
            $courseslist[$count]["shortname"] =  \local_costcenter\lib::strip_tags_custom($course->shortname);

            $courseslist[$count]["ratings_value"] = '';
            $courseslist[$count]["tagstring"] = \local_costcenter\lib::strip_tags_custom($tagstring);
            $courseslist[$count]["tagstringtotal"] = $tagstringtotal;
            $courseslist[$count]["tagenable"] = $tagenable;
            $courseslist[$count]["catname"] = \local_costcenter\lib::strip_tags_custom($catname);
            $courseslist[$count]["catnamestring"] = \local_costcenter\lib::strip_tags_custom($catnamestring);
            $courseslist[$count]["enrolled_count"] = $enrolled_count;
            $courseslist[$count]["courseid"] = $course->id;
            $courseslist[$count]["completiondays"] = $course->open_coursecompletiondays;
            $courseslist[$count]["completed_count"] = $completed_count;
            if ($matrix_plugin_exists) {
                $coursetype = $DB->get_field('local_custom_category', 'fullname', array('id' => $course->performancecatid));
                $displayed_names = '<span class="pl-10 ' . $coursetype . '">' . $coursetype . '</span>';
                $courseslist[$count]["coursetype"] = \local_costcenter\lib::strip_tags_custom($displayed_names);
            }
            $courseslist[$count]["course_class"] = $course->visible ? 'active' : 'inactive';
            $courseslist[$count]["grade_view"] = ((has_capability(
                'local/courses:grade_view',
                $context
            ) || is_siteadmin()) && has_capability('local/courses:manage', $context)) ? true : false;
            $courseslist[$count]["request_view"] = ((has_capability('local/request:approverecord', $maincheckcontext)) || is_siteadmin()) ? true : false;

            $coursesummary = \local_costcenter\lib::strip_tags_custom($chelper->get_course_formatted_summary(
                $course_in_list,
                array('overflowdiv' => false, 'noclean' => false, 'para' => false)
            ));
            $summarystring = strlen($coursesummary) > 100 ? substr($coursesummary, 0, 100) . "..." : $coursesummary;
            $courseslist[$count]["coursesummary"] = \local_costcenter\lib::strip_tags_custom($summarystring);
            $courseslist[$count]["fullcoursesummary"] = $coursesummary;
            $courseslist[$count]["format"] = $course->format;
            $course =  (array)$course;
            local_costcenter_set_costcenter_path($course);
            $course = (object)$course;
            if ($course->open_department > 0) {
                $courseslist[$count]["open_department"] = $DB->get_records_sql_menu('SELECT id,fullname
                FROM {local_costcenter}
                WHERE id IN(' . $course->open_department . ')');
            } else {
                $courseslist[$count]["open_department"] = get_string('all');
            }
            if ($course->open_subdepartment > 0) {
                $courseslist[$count]["open_subdepartment"] = $DB->get_records_sql_menu('SELECT id,fullname
                FROM {local_costcenter}
                WHERE id IN(' . $course->open_subdepartment . ')');
            } else {
                $courseslist[$count]["open_subdepartment"] = get_string('all');
            }
            if ($course->open_level4department > 0) {
                $courseslist[$count]["open_level4department"] = $DB->get_records_sql_menu('SELECT id,fullname
                FROM {local_costcenter}
                WHERE id IN(' . $course->open_level4department . ')');
            } else {
                $courseslist[$count]["open_level4department"] = get_string('all');
            }

            if ($course->selfenrol == 1) {
                $courseslist[$count]["selfenrol"] = get_string('yes');
            } else {
                $courseslist[$count]["selfenrol"] = get_string('no');
            }

            //course image
            if (file_exists($CFG->dirroot . '/local/includes.php')) {
                require_once($CFG->dirroot . '/local/includes.php');
                $includes = new user_course_details();
                $courseimage = $includes->course_summary_files($course);
                if (is_object($courseimage)) {
                    $courseslist[$count]["courseimage"] = $courseimage->out();
                } else {
                    $courseslist[$count]["courseimage"] = $courseimage;
                }
            }

            $enrolid = $DB->get_field('enrol', 'id', array('enrol' => 'manual', 'courseid' => $course->id));

            if (has_capability('local/courses:enrol', $maincheckcontext) && has_capability('local/courses:manage', $maincheckcontext)) {
                $courseslist[$count]["enrollusers"] = $CFG->wwwroot . "/local/courses/courseenrol.php?id=" . $course->id . "&enrolid=" . $enrolid;
            }
            if (has_capability('local/courses:enrol', $maincheckcontext) && has_capability('local/courses:manage', $maincheckcontext)) {
                $courseslist[$count]["autoenrollusers"] = $CFG->wwwroot . "/local/courses/courseautoenrol.php?id=" . $course->id;
            }
            if (has_capability('moodle/course:view', $context) || is_enrolled($context)) {
                $courseslist[$count]["courseurl"] = $CFG->wwwroot . "/course/view.php?id=" . $course->id;
            } else {
                $courseslist[$count]["courseurl"] = $CFG->wwwroot . "/local/search/coursedetails.php?id=" . $course->id;
            }

            if ($departmentcount > 1 && !(is_siteadmin())) {
                $courseslist[$count]["grade_view"] = false;
                $courseslist[$count]["request_view"] = false;
            }

            if (has_capability('local/courses:update', $context) && has_capability('local/courses:manage', $context) && has_capability('moodle/course:update', $context)) {
                if ($options->viewType == 'table') {
                    $courseedit = html_writer::link('javascript:void(0)', html_writer::tag('i', '', array('class' => 'fa fa-pencil ')), array('title' => get_string('edit'), 'alt' => get_string('edit'), 'data-action' => 'createcoursemodal', 'class' => 'createcoursemodal dropdown-item', 'data-value' => $course->id, 'onclick' => '(function(e){ require("local_courses/courseAjaxform").init({contextid:' . $context->id . ', component:"local_courses", callback:"custom_course_form", form_status:0, plugintype: "local", pluginname: "courses", courseid: ' . $course->id . ' }) })(event)'));
                } else {
                    $courseedit = html_writer::link('javascript:void(0)', html_writer::tag('i', '', array('class' => 'fa fa-pencil ')) . get_string('edit'), array('title' => get_string('edit'), 'alt' => get_string('edit'), 'data-action' => 'createcoursemodal', 'class' => 'createcoursemodal dropdown-item', 'data-value' => $course->id, 'onclick' => '(function(e){ require("local_courses/courseAjaxform").init({contextid:' . $context->id . ', component:"local_courses", callback:"custom_course_form", form_status:0, plugintype: "local", pluginname: "courses", courseid: ' . $course->id . ' }) })(event)'));
                }
                $courseslist[$count]["editcourse"] = $courseedit;
                if ($options->viewType == 'table') {
                    if ($course->visible) {
                        $icon = 't/hide';
                        $string = get_string('make_active', 'local_courses');
                    } else {
                        $icon = 't/show';
                        $string = get_string('make_inactive', 'local_courses');
                    }
                    $courseslist[$count]["visibleclass"]  = '';
                    if ($course->visible == 0) {
                        $courseslist[$count]["visibleclass"]  = "disabled";
                    }
                } else {
                    if ($course->visible) {
                        $icon = 't/hide';
                        $string = get_string('make_active', 'local_courses');
                        $title = get_string('make_inactive', 'local_courses');
                    } else {
                        $icon = 't/show';
                        $string = get_string('make_inactive', 'local_courses');
                        $title = get_string('make_active', 'local_courses');
                    }
                }

                $image = $OUTPUT->pix_icon($icon, $title, 'moodle', array('class' => 'iconsmall', 'title' => '')) . $title;
                $params = json_encode(array('coursename' => $coursename, 'coursestatus' => $course->visible));
                $courseslist[$count]["update_status"] .= html_writer::link("javascript:void(0)", $image, array('class' => ' make_inactive dropdown-item', 'data-fg' => "d", 'data-method' => 'course_update_status', 'data-plugin' => 'local_courses', 'data-params' => $params, 'data-id' => $course->id));
                if (!empty($autoenroll_plugin_exist)) {
                    $autoplugin = enrol_get_plugin('auto');
                    if (has_capability('local/courses:enrol', $maincheckcontext) && has_capability('local/courses:manage', $maincheckcontext)) {
                        $courseslist[$count]["auto_enrol"] = $CFG->wwwroot . "/local/courses/courseautoenrol.php?id=" . $course->id;
                    }
                }
            }

            if ($departmentcount > 1 && !(is_siteadmin())) {
                $courseslist[$count]["editcourse"] = '';
                $courseslist[$count]["update_status"] = '';
                $courseslist[$count]["auto_enrol"] = '';
            }
            $enrolinstance = $DB->get_record('enrol', array('courseid' => $course->id, 'enrol' => 'auto'));
            if ($enrolinstance) {
                $courseslist[$count]["auto_enrol_active"] = $enrolinstance->status == 0 ? true : false;
            }
            if (has_capability('local/courses:delete', $context) && has_capability('local/courses:manage', $context) && has_capability('moodle/course:delete', $context)) {
                if ($options->viewType == 'table') {
                    $deleteactionshtml = html_writer::link('javascript:void(0)', $OUTPUT->pix_icon('t/delete', get_string('delete'), 'moodle', array('')), array('class' => "dropdown-item delete_icon", 'title' => get_string('delete'), 'id' => "courses_delete_confirm_" . $course->id, 'onclick' => '(function(e){ require(\'local_courses/courseAjaxform\').deleteConfirm({action:\'deletecourse\' , id: ' . $course->id . ', name:"' . $coursename . '" }) })(event)'));
                } else {
                    $deleteactionshtml = html_writer::link('javascript:void(0)', $OUTPUT->pix_icon('t/delete', get_string('delete'), 'moodle', array('')) . get_string('delete'), array('class' => "dropdown-item delete_icon", 'title' => get_string('delete'), 'id' => "courses_delete_confirm_" . $course->id, 'onclick' => '(function(e){ require(\'local_courses/courseAjaxform\').deleteConfirm({action:\'deletecourse\' , id: ' . $course->id . ', name:"' . $coursename . '" }) })(event)'));
                }
                $courseslist[$count]["deleteaction"] = $deleteactionshtml;
            }

            if ($departmentcount > 1 && !(is_siteadmin())) {
                $courseslist[$count]["deleteaction"] = '';
            }

            if (has_capability('local/courses:grade_view', $context) && has_capability('local/courses:manage', $context)) {
                $courseslist[$count]["grader"] =  $CFG->wwwroot . "/grade/report/grader/index.php?id=" . $course->id;
            }

            if ($departmentcount > 1 && !(is_siteadmin())) {
                unset($courseslist[$count]["grader"]);
            }

            if (has_capability('local/courses:report_view', $context) && has_capability('local/courses:manage', $context)) {
                $courseslist[$count]["activity"] = $CFG->wwwroot . "/report/outline/index.php?id=" . $course->id;
            }
            if ($departmentcount > 1 && !(is_siteadmin())) {
                unset($courseslist[$count]["activity"]);
            }
            if ((has_capability('local/request:approverecord', $maincheckcontext) || is_siteadmin())) {
                $courseslist[$count]["requestlink"] = $CFG->wwwroot . "/local/request/index.php?courseid=" . $course->id;
            }

            if ($departmentcount > 1 && !(is_siteadmin())) {
                unset($courseslist[$count]["requestlink"]);
            }
            $courseslist[$count]["difficultylevel"] = $course->open_difficultylevel;

            $courseslist[$count] = array_merge($courseslist[$count], array(
                "actions" => (((has_capability('local/courses:enrol', $maincheckcontext) || has_capability('local/courses:update', $context) || has_capability('local/courses:delete', $context) || has_capability('local/courses:grade_view', $context) || has_capability('local/courses:report_view', $context)) || is_siteadmin()) && has_capability('local/courses:manage', $maincheckcontext)) ? true : false,
                "enrol" => ((has_capability('local/courses:enrol', $maincheckcontext)  || is_siteadmin()) && has_capability('local/courses:manage', $maincheckcontext)) ? true : false,
                "canconfiguretopics" => (has_capability('local/courses:canconfiguretopics', $maincheckcontext)  || is_siteadmin()) ? true : false,
                "update" => ((has_capability('local/courses:update', $context) || is_siteadmin()) && has_capability('local/courses:manage', $context) && has_capability('moodle/course:update', $context)) ? true : false,
                "delete" => ((has_capability('local/courses:delete', $context) || is_siteadmin()) && has_capability('local/courses:manage', $context) && has_capability('moodle/course:delete', $context)) ? true : false,
                "report_view" => ((has_capability('local/courses:report_view', $context) || is_siteadmin()) && has_capability('local/courses:manage', $context)) ? true : false
            ));
            $count++;
        }
        $nocourse = false;
        $pagination = false;
    } else {
        $nocourse = true;
        $pagination = false;
    }
    // check the course instance is not used in any plugin
    $candelete = true;
    $core_component = new core_component();
    $classroom_plugin_exist = $core_component::get_plugin_directory('local', 'classroom');
    if ($classroom_plugin_exist) {
        $exist_sql = "Select id from {local_classroom_courses} where courseid = ?";
        if ($DB->record_exists_sql($exist_sql, array($course->id)))
            $candelete = false;
    }

    $program_plugin_exist = $core_component::get_plugin_directory('local', 'program');
    if ($program_plugin_exist) {
        $exist_sql = "Select id from {local_program_level_courses} where courseid = ?";
        if ($DB->record_exists_sql($exist_sql, array($course->id)))
            $candelete = false;
    }
    $certification_plugin_exist = $core_component::get_plugin_directory('local', 'certification');
    if ($certification_plugin_exist) {
        $exist_sql = "Select id from {local_certification_courses} where courseid = ?";
        if ($DB->record_exists_sql($exist_sql, array($course->id)))
            $candelete = false;
    }
    $coursesContext = array(
        "hascourses" => $courseslist,
        "nocourses" => $nocourse,
        "totalcourses" => $totalcourses,
        "length" => count($courseslist),

    );
    return $coursesContext;
}

function courses_filters_form($filterparams, $ajaxformdata = null) {

    global $CFG, $PAGE, $USER;

    require_once($CFG->dirroot . '/local/courses/filters_form.php');

    $action = isset($filterparams['action']) ? $filterparams['action'] : '';
    $fields = array('hierarchy_fields', 'courses', 'categories', 'customfields',  'status', 'coursetype');

    $mform = new filters_form(null, array('filterlist' => $fields, 'filterparams' => $filterparams, 'action' => $action), 'post', '', null, true, $ajaxformdata);
    return $mform;
}
/*
* Author sarath
* @return true for reports under category
*/
function learnerscript_courses_list() {
    return 'Courses';
}

/**
 * Returns onlinetests tagged with a specified tag.
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
function local_courses_get_tagged_courses($tag, $exclusivemode = false, $fromctx = 0, $ctx = 0, $rec = 1, $page = 0, $sort = '') {
    global $CFG, $PAGE;
    // prepare for display of tags related to tests
    $perpage = $exclusivemode ? 10 : 5;
    $displayoptions = array(
        'limit' => $perpage,
        'offset' => $page * $perpage,
        'viewmoreurl' => null,
    );
    $renderer = $PAGE->get_renderer('local_courses');
    $totalcount = $renderer->tagged_courses($tag->id, $exclusivemode, $ctx, $rec, $displayoptions, $count = 1, $sort);
    $content = $renderer->tagged_courses($tag->id, $exclusivemode, $ctx, $rec, $displayoptions, 0, $sort);
    $totalpages = ceil($totalcount / $perpage);
    if ($totalcount)
        return new local_tags\output\tagindex(
            $tag,
            'local_courses',
            'courses',
            $content,
            $exclusivemode,
            $fromctx,
            $ctx,
            $rec,
            $page,
            $totalpages
        );
    else
        return '';
}
/**
 * todo sql query departmentwise
 * @param  $categorycontext object
 * @return array
 **/

function get_course_details($courseid) {
    global $USER, $DB, $PAGE;
    $context = (new \local_courses\lib\accesslib())::get_module_context($courseid);

    $PAGE->requires->js_call_amd('local_courses/courses', 'load', array());
    $PAGE->requires->js_call_amd('local_request/requestconfirm', 'load', array());
    $details = array();
    $joinsql = '';
    if (is_siteadmin()) {
        $sql = "select c.* from {course} c where c.id = ?";

        $selectsql = "select c.*  ";
        $fromsql = " from  {course} c";
        if ($DB->get_manager()->table_exists('local_rating')) {
            $selectsql .= " , AVG(rating) as avg ";
            $joinsql .= " LEFT JOIN {local_rating} as r ON r.moduleid = c.id AND r.ratearea = 'local_courses' ";
        }

        $wheresql = " where c.id = ? ";

        $adminrecord = $DB->get_record_sql($selectsql . $fromsql . $joinsql . $wheresql, [$courseid]);
        $enrolsql = "SELECT count(id) as ccount from {course_completions} where course = ? AND timecompleted IS NOT NULL";

        $completionsql = "SELECT count(u.id) as total FROM {user} AS u WHERE u.id > 2 AND u.suspended =0 AND u.deleted = 0 AND u.id <> 3 AND u.id IN (
            SELECT ue.userid FROM {user_enrolments} ue
            JOIN {enrol} e ON (e.id = ue.enrolid and e.courseid = ? and (e.enrol='manual' OR e.enrol='self')))";
        $completedcount =  $DB->count_records_sql($completionsql, [$adminrecord->id]);
        $enrolledcount = $DB->count_records_sql($enrolsql, [$adminrecord->id]);
        $details['manage'] = 1;
        $details['completed'] = $completedcount;
        $details['enrolled'] = $enrolledcount;
    } else {
        $ccsql = "SELECT * from {course_completions} where course = ? AND userid = ?";
        $userrecord = $DB->get_record_sql($ccsql, [$courseid, $USER->id]);
        $selectsql = "select c.*, ra.timemodified ";

        $fromsql = " from {course} c ";

        if ($DB->get_manager()->table_exists('local_rating')) {
            $selectsql .= " , AVG(rating) as avg ";
            $joinsql .= " LEFT JOIN {local_rating} as r ON r.moduleid = c.id AND r.ratearea = 'local_courses' ";
        }
        $joinsql .= " JOIN {context} ctx ON c.id = ctx.instanceid AND ctx.contextlevel = 50
        JOIN {role_assignments} ra ON ra.contextid = ctx.id AND ra.userid = ? ";
        $wheresql = " where 1 = 1 AND c.id = ? ";
        $courserecord = $DB->get_record_sql($selectsql . $fromsql . $joinsql . $wheresql, [$USER->id, $courseid], IGNORE_MULTIPLE);
        if ($courserecord->selfenrol == 1 && $courserecord->approvalreqd == 0) {
            $enrollmentbtn = '<a href="javascript:void(0);" data-action="courseselfenrol' . $courseid . '" class="courseselfenrol enrolled' . $courseid . '" onclick ="(function(e){ require(\'local_catalog/courseinfo\').test({selector:\'courseselfenrol' . $courseid . '\', courseid:' . $courseid . ', enroll:1}) })(event)"><button class="cat_btn viewmore_btn"><i class="fa fa-pencil-square-o" aria-hidden="true"></i>' . get_string('enroll', 'local_catalog') . '</button></a>';
        } elseif ($courserecord->selfenrol == 1 && $courserecord->approvalreqd == 1) {
            $enrollmentbtn = '<a href="javascript:void(0);" class="cat_btn" alt = ' . get_string('requestforenroll', 'local_classroom') . ' title = ' . get_string('requestforenroll', 'local_classroom') . ' onclick="(function(e){ require(\'local_request/requestconfirm\').init({action:\'add\', componentid: ' . $courserecord->id . ', component:\'elearning\',componentname:\'' . $courserecord->fullname . '\'}) })(event)" ><button class="cat_btn viewmore_btn"><i class="fa fa-pencil-square-o" aria-hidden="true"></i>' . get_string('requestforenroll', 'local_classroom') . '</button></a>';
        } else {
            $enrollmentbtn = '-';
        }
        $details['manage'] = 0;
        $details['status'] = (!is_null($userrecord->timecompleted)) ? get_string('completed', 'local_onlinetests') : get_string('pending', 'local_onlinetests');
        $details['enrolled'] = ($courserecord->timemodified) ? \local_costcenter\lib::get_userdate("d/m/Y H:i", $courserecord->timemodified) : $enrollmentbtn;
        $details['completed'] = ($courserecord->timecompleted) ? \local_costcenter\lib::get_userdate("d/m/Y H:i", $courserecord->timecompleted) : '-';
    }

    return $details;
}
function local_courses_request_dependent_query($aliasname) {
    $returnquery = " WHEN ({$aliasname}.compname LIKE 'elearning') THEN (SELECT fullname from {course} WHERE id = {$aliasname}.componentid) ";
    return $returnquery;
}

function local_courses_render_navbar_output() {
    global $PAGE;
    $PAGE->requires->js_call_amd('local_courses/courseAjaxform', 'load');
}
function local_courses_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = array()) {
    // Check the contextlevel is as expected - if your plugin is a block, this becomes CONTEXT_BLOCK, etc.

    // Make sure the filearea is one of those used by the plugin.
    if ($filearea !== 'files_filemanager') {
        return false;
    }

    $itemid = array_shift($args);

    $filename = array_pop($args);
    if (!$args) {
        $filepath = '/';
    } else {
        $filepath = '/' . implode('/', $args) . '/';
    }

    // Retrieve the file from the Files API.
    $fs = get_file_storage();
    $file = $fs->get_file($context->id, 'local_courses', $filearea, $itemid, $filepath, $filename);
    if (!$file) {
        return false;
    }
    send_file($file, $filename, null, 0, false, 0, $options);
}
/**
 * Serve the new course form as a fragment.
 *
 * @param array $args List of named arguments for the fragment loader.
 * @return string
 */
function local_courses_output_fragment_custom_selfcompletion_form($args) {
    global $DB, $CFG, $PAGE;
    $args = (object) $args;

    return get_string('selfcompletionconfirm', 'local_courses', $args->coursename);
}

function local_courses_search_page_filter_element(&$filterelements) {
    global $CFG;
    if (file_exists($CFG->dirroot . '/local/search/lib.php')) {
        require_once($CFG->dirroot . '/local/search/lib.php');
        $filterelements['elearning'] = ['code' => 'elearning', 'name' => 'Courses', 'tagitemshortname' => 'elearning', 'count' => local_search_get_coursecount_for_modules([['type' => 'moduletype', 'values' => ['elearning']]])];
    }
}
function local_courses_enabled_search() {
    return ['pluginname' => 'local_courses', 'templatename' => 'local_courses/searchpagecontent', 'type' => elearning];
}
function  local_courses_applicable_filters_for_search_page(&$filterapplicable) {
    $filterapplicable[elearning] = ['learningtype', 'status', 'categories', 'level', 'skill'];
}

function course_thumbimage($course) {
    global $DB, $CFG;
    if (file_exists($CFG->dirroot . '/local/includes.php')) {
        require_once($CFG->dirroot . '/local/includes.php');
        $includes = new \user_course_details();
    }

    if (file_exists($CFG->dirroot . '/local/includes.php')) {
        $courseimage = $includes->course_summary_files($course);
        if (is_object($courseimage)) {
            $imageurl = $courseimage->out();
        } else {
            $imageurl = $courseimage;
        }
    }
    return $imageurl;
}

function get_course_customfileds($courseid) {
    global $DB;

    $customsql = "SELECT lc.courseinfocategory FROM {local_costcenter} AS lc JOIN {course} AS c ON (c.open_path LIKE lc.path)  WHERE c.id = :courseid ";
    $params = ['courseid' => $courseid];
    $customfield = $DB->get_field_sql($customsql, $params);
    $sql = "
        SELECT f.*
          FROM {customfield_field} f
          JOIN {customfield_category} cat ON cat.id = f.categoryid
         WHERE cat.component = 'core_course' AND cat.area = 'course'
    ";

    $sql .= " AND f.categoryid = :infocatid";
    $sql .= " ORDER BY f.name";
    $fields = $DB->get_records_sql($sql, ['infocatid' => $customfield]);

    return $fields;
}
function auto_enrol_users($users, $courseid) {
    global $DB;
    $autoplugin = enrol_get_plugin('auto');
    foreach ($users as $user) {
        if (!is_object($user)) {
            $user = $DB->get_record('user', array('id' => $user));
        }
        if (!$instance = $autoplugin->get_instance_for_course($courseid)) {
            continue;
        }
        $autoplugin->enrol_user($instance, $user->id, $instance->roleid);
    }
    return '';
}

/**
 * costcenterwise_courses_datacount
 *
 * @param  mixed $costcenter
 * @param  mixed $department
 * @param  mixed $subdepartment
 * @param  mixed $l4department
 * @param  mixed $l5department
 * @return array
 */
function costcenterwise_courses_datacount($costcenter, $department = false, $subdepartment = false, $l4department = false, $l5department = false) {
    global $USER, $DB, $CFG;
    $params = array();
    $params['costcenterpath'] = '%/' . $costcenter . '/%';
    $countcoursesql = "SELECT count(id) FROM {course} WHERE concat('/',open_path,'/') LIKE :costcenterpath AND open_coursetype=0 ";

    if ($l5department) {
        $countcoursesql .= " AND concat('/',open_path,'/') LIKE :l5departmentpath ";
        $params['l5departmentpath'] = '%/' . $l5department . '/%';
    } else if ($l4department) {
        $countcoursesql .= " AND concat('/',open_path,'/') LIKE :l4departmentpath ";
        $params['l4departmentpath'] = '%/' . $l4department . '/%';
    } else  if ($subdepartment) {
        $countcoursesql .= " AND concat('/',open_path,'/') LIKE :subdepartmentpath ";
        $params['subdepartmentpath'] = '%/' . $subdepartment . '/%';
    } else  if ($department) {
        $countcoursesql .= "  AND concat('/',open_path,'/') LIKE :departmentpath  ";
        $params['departmentpath'] = '%/' . $department . '/%';
    }

    $countcourses = $DB->count_records_sql($countcoursesql, $params);
    return ['datacount' => $countcourses];
}

/**
 * Retrieves a list of difficulty levels.
 *
 * This function returns an array of integers representing different levels of difficulty.
 * The levels are typically used to categorize or rank items based on their difficulty.
 *
 * @return array An array of integers representing difficulty levels. The levels range from 0 to 5.
 */
function difficulty_levels() {
    // Define an array containing difficulty levels from 0 to 5.
    $levels = [
        get_string('difficultylevels', 'local_courses', 0),
        get_string('difficultylevels', 'local_courses', 1),
        get_string('difficultylevels', 'local_courses', 2),
        get_string('difficultylevels', 'local_courses', 3),
        get_string('difficultylevels', 'local_courses', 4),
        get_string('difficultylevels', 'local_courses', 5),
    ];

    // Return the array of difficulty levels.
    return $levels;
}
