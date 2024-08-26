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

defined('MOODLE_INTERNAL') || die;

use moodleform;

class local_courses_renderer extends plugin_renderer_base {

    /**
     * [render_classroom description]
     * @method render_classroom
     * @param  \local_classroom\output\classroom $page [description]
     * @return [type]                                  [description]
     */
    public function render_courses(\local_courses\output\courses $page) {
        $data = $page->export_for_template($this);
        return parent::render_from_template('local_courses/courses', $data);
    }
    /**
     * [render_form_status description]
     * @method render_form_status
     * @param  \local_classroom\output\form_status $page [description]
     * @return [type]                                    [description]
     */
    public function render_form_status(\local_courses\output\form_status $page) {
        $data = $page->export_for_template($this);
        return parent::render_from_template('local_courses/form_status', $data);
    }

    /**
     * Display the avialable courses
     *
     * @return string The text to render
     */
    public function get_catalog_courses($filter = false, $view_type = 'card') {
        global $USER;
        $categorycontext = (new \local_courses\lib\accesslib())::get_module_context();
        $status = optional_param('status', '', PARAM_RAW);
        $costcenterid = optional_param('costcenterid', '', PARAM_INT);
        $departmentid = optional_param('departmentid', '', PARAM_INT);
        $subdepartmentid = optional_param('subdepartmentid', '', PARAM_INT);
        $l4department = optional_param('l4department', '', PARAM_INT);
        $l5department = optional_param('l5department', '', PARAM_INT);

        $templateName = 'local_courses/catalog';
        $cardClass = 'col-md-6 col-12';
        $perpage = 12;
        if ($view_type == 'table') {
            $templateName = 'local_courses/catalog_table';
            $cardClass = 'tableformat';
            $perpage = 20;
        }
        $options = array('targetID' => 'manage_courses', 'perPage' => $perpage, 'cardClass' => 'col-lg-3 col-md-4 col-12 mb-5', 'viewType' => $view_type);
        $options['methodName'] = 'local_courses_courses_view';
        $options['templateName'] = $templateName;
        $options = json_encode($options);
        $filterdata = json_encode(array('status' => $status, 'filteropen_costcenterid' => $costcenterid, 'filteropen_department' => $departmentid, 'filteropen_subdepartment' => $subdepartmentid, 'filteropen_level4department' => $l4department, 'filteropen_level5department' => $l5department));
        $dataoptions = json_encode(array('userid' => $USER->id, 'contextid' => $categorycontext->id, 'status' => $status, 'filteropen_costcenterid' => $costcenterid, 'filteropen_department' => $departmentid, 'filteropen_subdepartment' => $subdepartmentid, 'filteropen_level4department' => $l4department, 'filteropen_level5department' => $l5department));
        $context = [
            'targetID' => 'manage_courses',
            'options' => $options,
            'dataoptions' => $dataoptions,
            'filterdata' => $filterdata
        ];
        if ($filter) {
            return  $context;
        } else {
            return  $this->render_from_template('local_costcenter/cardPaginate', $context);
        }
    }

    /**
     * Renders html to print list of courses tagged with particular tag
     *
     * @param int $tagid id of the tag
     * @param bool $exclusivemode if set to true it means that no other entities tagged with this tag
     *             are displayed on the page and the per-page limit may be bigger
     * @param int $fromctx context id where the link was displayed, may be used by callbacks
     *            to display items in the same context first
     * @param int $ctx context id where to search for records
     * @param bool $rec search in subcontexts as well
     * @param array $displayoptions
     * @return string empty string if no courses are marked with this tag or rendered list of courses
     */
    public function tagged_courses($tagid, $exclusivemode = true, $ctx = 0, $rec = true, $displayoptions = null, $count = 0, $sort = '') {
        global $CFG, $DB, $USER;
        $categorycontext = (new \local_courses\lib\accesslib())::get_module_context();
        $userorg = array();
        $userdep = array();
        if ($count > 0)
            $sql = " select count(c.id) from {course} c ";
        else
            $sql = " select c.* from {course} c  ";
        $joinsql = $groupby = $orderby = '';
        if (!empty($sort) and $count == 0) {
            switch ($sort) {
                case 'highrate':
                    if ($DB->get_manager()->table_exists('local_rating')) {
                        $joinsql .= " LEFT JOIN {local_rating} as r ON r.moduleid = c.id AND r.ratearea = 'local_courses' ";
                        $groupby .= " group by c.id ";
                        $orderby .= " order by AVG(rating) desc ";
                    }
                    break;
                case 'lowrate':
                    if ($DB->get_manager()->table_exists('local_rating')) {
                        $joinsql .= " LEFT JOIN {local_rating} as r ON r.moduleid = c.id AND r.ratearea = 'local_courses' ";
                        $groupby .= " group by c.id ";
                        $orderby .= " order by AVG(rating) asc ";
                    }
                    break;
                case 'latest':
                    $orderby .= " order by c.timecreated desc ";
                    break;
                case 'oldest':
                    $orderby .= " order by c.timecreated asc ";
                    break;
                default:
                    $orderby .= " order by c.timecreated desc ";
                    break;
            }
        }

        if (is_siteadmin()) {
            $joinsql .= " JOIN {local_costcenter} AS co ON co.id = c.open_path
                         JOIN {course_categories} AS cc ON cc.id = c.category
                         where 1 = 1 ";
        } else {

            $condition = (new \local_courses\lib\accesslib())::get_costcenter_path_field_concatsql($columnname = 'c.open_path');

            $joinsql .= " JOIN {local_costcenter} AS co ON co.id = c.open_path
                       JOIN {course_categories} AS cc ON cc.id = c.category
                       WHERE $condition";
        }

        $tagparams = array('tagid' => $tagid, 'itemtype' => 'courses', 'component' => 'local_courses');
        $params = array_merge($userorg, $userdep, $tagparams);

        $where = " AND c.id IN (SELECT t.itemid FROM {tag_instance} t WHERE t.tagid = :tagid AND t.itemtype = :itemtype AND t.component = :component)";

        if ($count > 0) {
            $records = $DB->count_records_sql($sql . $joinsql . $where, $params);
            return $records;
        } else {
            $records = $DB->get_records_sql($sql . $joinsql . $where . $groupby . $orderby, $params);
        }

        $tagfeed = new local_tags\output\tagfeed(array(), 'local_courses');
        $img = $this->output->pix_icon('i/course', '');
        foreach ($records as $key => $value) {
            $url = $CFG->wwwroot . '/course/view.php?id=' . $value->id . '';
            $imgwithlink = html_writer::link($url, $img);
            $modulename = html_writer::link($url, $value->fullname);
            $coursedetails = get_course_details($value->id);
            $details = $this->render_from_template('local_courses/tagview', $coursedetails);
            $tagfeed->add($imgwithlink, $modulename, $details);
        }
        return $this->output->render_from_template('local_tags/tagfeed', $tagfeed->export_for_template($this->output));
    }

    function display_course_enrolledusers($courseid, $module = false) {
        global $DB;

        $certificate_plugin_exist = \core_component::get_plugin_directory('tool', 'certificate');
        $categorycontext = (new \local_courses\lib\accesslib())::get_module_context($courseid);
        $maincheckcontext = (new \local_courses\lib\accesslib())::get_module_context();
        if (is_siteadmin() || ((has_capability(
            'local/courses:enrol',
            $maincheckcontext
        )  || is_siteadmin()) && has_capability('local/courses:manage', $maincheckcontext))) {
            $enrolid = $DB->get_field('enrol', 'id', array('courseid' => $courseid, 'enrol' => 'manual'));
            $userenrollment = true;
        }
        $info = array();
        $info['enrolid'] = $enrolid;
        $info['courseid'] = $courseid;

        if ($certificate_plugin_exist) {
            $certificate = $DB->get_field('course', 'open_certificateid', array('id' => $courseid));
            if ($certificate) {
                $info['added_certificate'] = true;
            } else {
                $info['added_certificate'] = false;
            }
        }

        if (is_siteadmin() || (has_capability('local/courses:managecourses', $categorycontext))) {

            $info['actions'] = true;
        } else {

            $info['actions'] = false;
        }
        $info['attempt'] = ($module == 'onlineexam') ? true : false;
        return $this->render_from_template('local_courses/courseusersview', $info);
    }

    function get_course_enrolledusers($dataobj) {
        global $DB, $USER, $OUTPUT, $CFG;

        $countsql = "SELECT COUNT(DISTINCT u.id) ";
        $selectsql = "SELECT DISTINCT(u.id) as userid,ue.id,u.firstname, u.lastname, u.email, u.open_employeeid,
            cc.timecompleted ";
        $sql = " FROM {course} c
        JOIN {course_categories} cat ON cat.id = c.category
        JOIN {enrol} e ON e.courseid = c.id AND
                    (e.enrol = 'manual' OR e.enrol = 'self' OR e.enrol = 'classroom' OR e.enrol = 'learningplan' OR e.enrol = 'auto' OR e.enrol = 'cohort')
        JOIN {user_enrolments} ue ON ue.enrolid = e.id
        JOIN {user} u ON u.id = ue.userid AND u.deleted = 0 AND u.suspended=0
        JOIN {local_costcenter} lc ON lc.path = u.open_path
        JOIN {role_assignments} as ra ON ra.userid = u.id
        JOIN {context} AS cxt ON cxt.id=ra.contextid AND cxt.contextlevel = 50 AND cxt.instanceid=c.id
        JOIN {role} as r ON r.id = ra.roleid AND r.shortname IN ('student','employee', 'teacher')
        LEFT JOIN {course_completions} as cc ON cc.course = c.id AND u.id = cc.userid ";

        if ($dataobj->moduletype == 'onlineexam') {
            $selectsql .= " , gi.gradepass ";
            $sql .= "LEFT JOIN {grade_items} AS gi ON gi.courseid = c.id AND gi.itemmodule = 'quiz' ";
        }
        $sql .= " WHERE c.id = :courseid ";

        $params = array();
        $params['courseid'] = $dataobj->courseid;

        $categorycontext = (new \local_courses\lib\accesslib())::get_module_context($dataobj->courseid);

        $sql .= (new \local_users\lib\accesslib())::get_costcenter_path_field_concatsql($columnname = 'u.open_path');

        if (!empty($dataobj->search)) {
            $concatsql = " AND ( CONCAT(u.firstname,' ',u.lastname) LIKE '%" . $dataobj->search . "%' OR
                          u.open_employeeid LIKE '%" . $dataobj->search . "%' ) ";
        } else {
            $concatsql = '';
        }

        $courseusers = $DB->get_records_sql($selectsql . $sql . $concatsql, $params, $dataobj->start, $dataobj->length);
        $enrolleduserscount = $DB->count_records_sql($countsql . $sql . $concatsql, $params);

        $userslist = array();
        if ($courseusers) {
            $userslist = array();

            $enrolledcount = $enrolleduserscount;

            $certificate_plugin_exist = \core_component::get_plugin_directory('tool', 'certificate');

            if ($certificate_plugin_exist) {
                $cert_plugin_exists = true;
                $certificate = $DB->get_field('course', 'open_certificateid', array('id' => $dataobj->courseid));
                if ($certificate) {
                    $icon = '<i class="icon fa fa-download" aria-hidden="true"></i>';
                    $certificate_added = true;
                } else {
                    $certificate_added = false;
                }
            } else {
                $cert_plugin_exists = false;
            }
            if ($dataobj->moduletype == 'onlineexam') {
                $quizdata = $DB->get_record('quiz', array('course' => $dataobj->courseid), 'id, attempts');
            }
            foreach ($courseusers as $enroluser) {
                $query = "SELECT MAX(qa.attempt)as attemps, qg.grade, qg.timemodified FROM {quiz_attempts} AS qa
                JOIN {quiz_grades} AS qg ON qg.quiz = qa.quiz AND qg.userid = qa.userid

                WHERE qa.userid = :userid AND qa.quiz = :quiz AND qa.state = :state";
                $givenattemp = $DB->get_record_sql($query, array('userid' => $enroluser->userid, 'quiz' => $quizdata->id, 'state' => 'finished'));

                $userinfo = array();
                $userinfo[] = $enroluser->firstname . ' ' . $enroluser->lastname;
                $userinfo[] = $enroluser->open_employeeid;
                $userinfo[] = $enroluser->email;
                $userinfo[] = date('d/m/Y', $enroluser->timecreated);

                if ($dataobj->moduletype == 'onlineexam') {
                    $totalattempt = $quizdata->attempts ? $quizdata->attempts : 'Unlimited';
                    $completeattemp = $givenattemp->attemps ? $givenattemp->attemps : 0;
                    $userinfo[] = $completeattemp . '/' . $totalattempt;

                    if ($givenattemp->grade >= $enroluser->gradepass) {
                        $userinfo[] = get_string('completed', 'local_courses');
                        $userinfo[] = date('d/m/Y H:i a', $givenattemp->timemodified);
                    } else {
                        $userinfo[] = get_string('notcompleted', 'local_courses');
                        $userinfo[] = 'N/A';
                    }
                } else {
                    if ($enroluser->timecompleted) {
                        $userinfo[] = get_string('completed', 'local_courses');
                        $userinfo[] = \local_costcenter\lib::get_userdate('d/m/Y H:i a', $enroluser->timecompleted);
                    } else {
                        $userinfo[] = get_string('notcompleted', 'local_courses');
                        $userinfo[] = 'N/A';
                    }
                }
                $get_enrolid = "";
                $get_enrolmentod = "";
                $sql = "SELECT ue.id,e.enrol,e.customint1,ue.userid FROM {user_enrolments} as ue
                    JOIN {enrol} as e ON e.id = ue.enrolid
                    WHERE e.courseid = $dataobj->courseid AND ue.userid =$enroluser->userid ";
                $userenrolment = $DB->get_records_sql($sql);
                $enrolmethod = array();
                $enroll = array();
                foreach ($userenrolment as $userenrol) {
                    $enroll[] = ucfirst($userenrol->enrol);

                    if (is_siteadmin() || (has_capability('local/courses:managecourses', $categorycontext))) {
                        $icon = '<i class="icon fa fa-pencil" aria-hidden="true"></i>';
                        $array = array('id' => $dataobj->courseid, 'ue' => $userenrol->id);
                        $url = new moodle_url('editenrol.php', $array);
                        $options = array('title' => get_string('edit', 'local_courses'));
                        $courseedit = html_writer::link($url, $icon, $options);
                        $deleteurl = 'javascript:void(0)';
                        $deleteicon = '<i class="icon fa fa-trash fa-fw"></i>';
                        $array = array(
                            'title' => get_string('delete'),
                            'alt' => get_string('delete'),
                            'onclick' => "(function(e){ require('local_courses/courses').deleteuser({ action:'delete_user',userid:" . $userenrol->id . ",id:" . $dataobj->courseid . "}) })(event)"
                        );
                        $regenarateurl = 'javascript:void(0)';
                        $regenarateicon = '<i class="icon fa fa-refresh fa-fw"></i>';
                        $certificateparams = [];

                        $issueid = $DB->get_field('tool_certificate_issues', 'id', array('userid' => $userenrol->userid, 'moduleid' => $dataobj->courseid));
                        $regenaratearray = array(
                            'title' => get_string('regenarate', 'local_courses'),
                            'alt' => get_string('regenarate', 'local_courses'),
                            'onclick' => "(function(e){ require('local_courses/courses').regeneratecertificate({ action:'regenarate_certificate',issueid:" . $issueid . ",id:" . $dataobj->courseid . "}) })(event)"
                        );

                        $delete = html_writer::link($deleteurl, $deleteicon, $array);
                        $regenarate = html_writer::link($regenarateurl, $regenarateicon, $regenaratearray);
                        if ($cert_plugin_exists && $certificate_added) {
                            if (!empty($enroluser->timecompleted)) {
                                $enrolmethod[] = $courseedit . $delete . $regenarate;
                            } else {
                                $enrolmethod[] = $courseedit . $delete;
                            }
                        }
                    }
                    $enrolmethod[] = $DB->get_field('cohort', 'name', array('id' => $userenrol->customint1));
                }
                if ($dataobj->moduletype != 'onlineexam') {
                    $userinfo[] = implode('<br />', $enroll);
                }
                $userinfo[] = implode(' <br>', $enrolmethod);
                if ($cert_plugin_exists && $certificate_added) {
                    if (!empty($enroluser->timecompleted)) {
                        $icon = '<i class="icon fa fa-download" aria-hidden="true"></i>';
                        //  mallikarjun added to download default certificate
                        $certcode = $DB->get_field('tool_certificate_issues', 'code', array('moduleid' => $dataobj->courseid, 'userid' => $enroluser->userid, 'moduletype' => 'course'));
                        $array = array('code' => $certcode);
                        $url = new moodle_url('/admin/tool/certificate/view.php', $array);
                        $options = array('title' => get_string('download_certificate', 'local_courses'), 'target' => '_blank');
                        $userinfo[] = html_writer::link($url, $icon, $options);
                    } else {
                        $url = 'javascript: void(0)';
                        $userinfo[] = html_writer::tag($url, get_string('notassigned', 'local_classroom'));
                    }
                }
                $userslist[] = $userinfo;
            }
            $return = array(
                "recordsFiltered" => $enrolleduserscount,
                "data" => $userslist,
            );
        } else {
            $return = array(
                "recordsFiltered" => 0,
                "data" => array(),
            );
        }
        return $return;
    }
    public function get_userdashboard_courses($tab, $filter = false, $view_type = 'card') {
        $categorycontext = (new \local_courses\lib\accesslib())::get_module_context();

        $templateName = 'local_courses/userdashboard_paginated';
        $cardClass = 'col-md-6 col-12';
        $perpage = 6;
        if ($view_type == 'table') {
            $templateName = 'local_courses/userdashboard_paginated_catalog_list';
            $cardClass = 'tableformat';
            $perpage = 20;
        }

        $options = array('targetID' => 'dashboard_courses', 'perPage' => $perpage, 'cardClass' => $cardClass, 'viewType' => $view_type);
        $options['methodName'] = 'local_courses_userdashboard_content_paginated';
        $options['templateName'] = $templateName;
        $options['filter'] = $tab;
        $options = json_encode($options);
        $filterdata = json_encode(array());
        $dataoptions = json_encode(array('contextid' => $categorycontext->id));
        $context = [
            'targetID' => 'dashboard_courses',
            'options' => $options,
            'dataoptions' => $dataoptions,
            'filterdata' => $filterdata
        ];
        if ($filter) {
            return  $context;
        } else {
            return  $this->render_from_template('local_costcenter/cardPaginate', $context);
        }
    }

    /**
     * Render the selfcompletion
     * @param  selfcompletion $widget
     * @return bool|string
     * @throws moodle_exception
     */
    protected function render_selfcompletion(\local_courses\output\selfcompletion $page) {
        $data = $page->export_for_template($this);
        return parent::render_from_template('local_courses/selfcompletion', $data);
    }
    function user_for_autoenrol($courseid, $confirmation = 0, $enrollid) {
        global $DB, $OUTPUT, $USER, $PAGE, $CFG;
        $core_component = new core_component();
        $autoenroll_plugin_exist = $core_component::get_plugin_directory('enrol', 'auto');
        if (!empty($autoenroll_plugin_exist)) {
            $autoplugin = enrol_get_plugin('auto');
            $instance = $autoplugin->get_instance_for_course($courseid);
            if (!$instance) {
                // if ($instance->status == ENROL_INSTANCE_DISABLED) {
                $data1["auto_enrol"] = $OUTPUT->single_button(new moodle_url(
                    '/enrol/auto/edit.php',
                    array('courseid' => $courseid, 'id' => $enrollid)
                ), 'Enable Auto-Enrolement method');
                // }
            } else {
                $data1["auto_enrol"] = $OUTPUT->single_button(new moodle_url(
                    '/enrol/auto/edit.php',
                    array('courseid' => $courseid, 'id' => $enrollid)
                ), 'Disable Auto-Enrolement method');
                $data1['users'] = $OUTPUT->single_button(new moodle_url('courseautoenrol.php', array('confirmation' => 1, 'id' => $courseid)), 'Click here to enroll users');
            }
        }
        $systemcontext = (new \local_users\lib\accesslib())::get_module_context();
        $course = $DB->get_record('course', array('id' => $courseid));
        if (empty($course)) {
            throw new \Exception('Course not found');
        }
        $paths = [];
        $coursecostcenterpaths[] = $course->open_path;
        foreach ($coursecostcenterpaths as $userpath) {
            $userpathinfo = $userpath;
            $paths[] = $userpathinfo . '/%';
            $paths[] = $userpathinfo;
            while ($userpathinfo = rtrim($userpathinfo, '0123456789')) {
                $userpathinfo = rtrim($userpathinfo, '/');
                if ($userpathinfo === '') {
                    break;
                }
                $paths[] = $userpathinfo;
            }
        }
        if (!empty($paths)) {
            foreach ($paths as $path) {
                $pathsql[] = " u.open_path LIKE '{$path}' ";
            }
            $condition = " AND ( " . implode(' OR ', $pathsql) . ' ) ';
        }
        $fields = ['open_designation'];
        $prefix = 'u';
        $array = target_audience_match_field($fields, $prefix, $course);
        $sqlarray = $array['sqlarray'];
        $params = $array['params'];
        $coursefields = "SELECT fieldvalue FROM {local_module_targetaudience} mt
    JOIN {user_info_field} uif ON uif.id=mt.fieldid
    WHERE mt.moduleid = $course->id AND mt.module = 'course' AND fieldvalue IS NOT NULL";
        $coursefields1 = $DB->get_fieldset_sql($coursefields);
        $total_fields = count($coursefields1);
        $user_sql = "SELECT u.* FROM {user} u
            WHERE u.id NOT IN (SELECT DISTINCT ue.userid
            FROM {user_enrolments} ue
            JOIN {enrol} e ON e.id = ue.enrolid
            JOIN {course} c ON c.id = e.courseid
            WHERE c.id=$course->id $condition)
            AND u.suspended=0 AND u.deleted=0 $condition ";
        if (!empty($sqlarray)) {
            $user_sql .= " AND $sqlarray";
        }
        $customtargetusers = $DB->get_records_sql($user_sql, $params);
        if ($total_fields > 0) {
            $fromsql = '';
            if (isset($customtargetusers)) {
                foreach ($customtargetusers as $customtargetuser) {
                    $id = 0;
                    foreach ($coursefields1 as $field) {
                        $query = "SELECT uid.userid as userid FROM {user_info_data} uid JOIN {local_module_targetaudience} lmt
      ON lmt.fieldid = uid.fieldid WHERE lmt.moduleid = $course->id AND uid.userid= $customtargetuser->id AND FIND_IN_SET(uid.data,'$field')";
                        $coursefields_user = $DB->get_field_sql($query);
                        if ($coursefields_user) {
                            $id++;
                        }
                        if ($id == $total_fields) {
                            $users[] = $coursefields_user;
                        }
                    }
                }
            }
        } else {
            $users = $customtargetusers;
        }
        $tabledata = array();
        if (!empty($users)) {
            foreach ($users as $user) {

                if (!is_object($user)) {
                    $user = $DB->get_record('user', array('id' => $user));
                }
                list($zero, $org, $ctr, $bu, $cu, $territory) = explode("/", $user->open_path);
                $orgnization = $DB->get_field('local_costcenter', 'fullname', array('id' => $org));
                if ($ctr > 0) {
                    $country = $DB->get_field('local_costcenter', 'fullname', array('id' => $ctr));
                }
                $data = array();
                $data['user_fullname'] = $user->firstname . ' ' . $user->lastname;
                $data['email'] = $user->email;
                $data['idnumber'] = $user->idnumber;
                $data['platform'] = $orgnization;
                $tabledata[] = $data;
            }

            echo '<br>';
            $tabledata1 = array_unique($tabledata, SORT_REGULAR);
            $data1['tabledata'] = $tabledata1;
            $auto_enable = $DB->get_field('enrol', 'id', array('status' => 0, 'enrol' => 'auto'));
            if ($auto_enable) {
                if ($confirmation == 1) {
                    auto_enrol_users($users, $courseid);
                    redirect($CFG->wwwroot . '/local/courses/courseautoenrol_log.php?id=' . $courseid);
                }
            }
        } else {
            echo '<br>';
            $rendertable = \html_writer::tag('div', get_string('no_records', 'usersprofilefields_professionalrole'), array('class' => 'alert alert-info text-center'));
        }
        $enrolinstance = $DB->get_record('enrol', array('courseid' => $courseid, 'enrol' => 'auto'));
        $data1['courseid'] = $courseid;
        $data1['user_count'] = $user_count;
        $data1['enrolid'] = $enrolinstance->id ? $enrolinstance->id : 0;
        $data1['enrol_status'] = $enrolinstance->status ? $enrolinstance->status : 0;
        $icon = '<i class="icon fa fa-history fa-5x" aria-hidden="true"></i>';
        $array = array('id' => $courseid);
        $url = new moodle_url('courseautoenrol_log.php', $array);
        $options = array('title' => get_string('enrolled_users_list', 'local_courses'));
        $courseedit = html_writer::link($url, $icon, $options);
        $data1['courseautoenrol_log'] = $courseedit;
        return $this->render_from_template('local_courses/autoenrol_eligible_users', $data1);
    }

    function userlog_for_autoenrol($courseid, $confirmation = 0, $enrollid) {
        global $DB, $OUTPUT, $USER, $PAGE, $CFG;
        $core_component = new core_component();
        $autoenroll_plugin_exist = $core_component::get_plugin_directory('enrol', 'auto');
        if (!empty($autoenroll_plugin_exist)) {
            $autoplugin = enrol_get_plugin('auto');
            $instance = $autoplugin->get_instance_for_course($courseid);

            $systemcontext = (new \local_users\lib\accesslib())::get_module_context();
            $course = $DB->get_record('course', array('id' => $courseid));
            if (empty($course)) {
                throw new \Exception('Course not found');
            }

            $user_sql = " SELECT DISTINCT u.*,ue.timecreated as timecreated
            FROM {user_enrolments} ue
            JOIN {enrol} e ON e.id = ue.enrolid
            JOIN {course} c ON c.id = e.courseid
            JOIN {user} u ON u.id = ue.userid
            WHERE c.id=$courseid AND e.enrol='auto'";
            $users = $DB->get_records_sql($user_sql);
            $user_count = count($users);
            $tabledata = array();
            if (!empty($users)) {
                foreach ($users as $user) {
                    list($zero, $org, $ctr, $bu, $cu, $territory) = explode("/", $user->open_path);
                    $platform = $DB->get_field('local_costcenter', 'fullname', array('id' => $org));
                    if ($ctr > 0) {
                        $organization = $DB->get_field('local_costcenter', 'fullname', array('id' => $ctr));
                    }
                    $data = array();
                    $data['user_fullname'] = $user->firstname . ' ' . $user->lastname;
                    $data['email'] = $user->email;
                    $data['idnumber'] = $user->idnumber;
                    $data['platform'] = $platform;
                    $data['timecreated'] = $user->timecreated ? date('d-m-Y', $user->timecreated) : 0;;
                    $tabledata[] = $data;
                }

                echo '<br>';
                $tabledata1 = array_unique($tabledata, SORT_REGULAR);
                $data1['tabledata'] = $tabledata1;
            } else {
                echo '<br>';
                $rendertable = \html_writer::tag('div', get_string('no_records', 'usersprofilefields_professionalrole'), array('class' => 'alert alert-info text-center'));
            }
            $data1['courseid'] = $courseid;
            $data1['user_count'] = $user_count;
            return $this->render_from_template('local_courses/autoenrol_users_log', $data1);
        }
        //return $rendertable;
    }

    function get_performance_categories($orgid) {
        global $DB;
        $performance_types = array();
        $select = array();
        //$select[null] = get_string('select_ptype', 'local_courses');
        $performance_types[null] = $select;
        $sql = "SELECT * FROM {local_custom_category} lcm WHERE lcm.parentid = :parentid AND lcm.type = :type ";
        if (isset($orgid) && $orgid != 0) {
            $sql .= " AND lcm.costcenterid = :costcenterid";
        }
        // $sql .= " AND EXISTS (SELECT id FROM {local_custom_category} cm WHERE lcm.id = cm.parentid ) ";
        $ptype_categories = $DB->get_records_sql($sql, array('parentid' => 0, 'type' => 0, 'costcenterid' => $orgid));
        if ($ptype_categories) {
            foreach ($ptype_categories as $ptype_category) {
                // $performance_categories = $DB->get_records_sql_menu("SELECT * FROM {local_custom_category} WHERE parentid = :parentid AND parentid <> 0 ", array('parentid' => $ptype_category->id));
                //$performance_types[$ptype_category->id] = $ptype_category->fullname;

                $child_categories = $DB->get_records_sql("SELECT * FROM {local_custom_category} WHERE parentid = :parentid AND parentid <> 0 ", array('parentid' => $ptype_category->id));

                foreach ($child_categories as $child_category) {
                    $performance_types[$child_category->id] = $ptype_category->fullname . ' / ' . $child_category->fullname;
                }
            }
        }

        return $performance_types;
    }
}
