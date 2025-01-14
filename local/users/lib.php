<?php

/*
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
 * @subpackage local_users
 */

use local_users\output\team_status_lib;

defined('MOODLE_INTERNAL') || die;
require_once($CFG->libdir . '/formslib.php');
require_once($CFG->dirroot . '/user/editlib.php');
require_once($CFG->dirroot . '/local/costcenter/lib.php');

/**
 * Description: To display the form in modal on modal trigger event.
 * @param  [array] $args [the parameters required for the form]
 * @return        [modal content]
 */
function local_users_output_fragment_new_create_user($args) {
    global $CFG, $DB, $PAGE;
    $args = (object) $args;
    $context = $args->context;
    $o = '';
    $formdata = [];
    if (!empty($args->jsonformdata)) {

        $serialiseddata = json_decode($args->jsonformdata);
        if (is_object($serialiseddata)) {
            $serialiseddata = serialize($serialiseddata);
        }
        parse_str($serialiseddata, $formdata);
    }
    $editoroptions = [
        'maxfiles' => EDITOR_UNLIMITED_FILES,
        'maxbytes' => $course->maxbytes,
        'trust' => false,
        'context' => $context,
        'noclean' => true,
        'subdirs' => false
    ];
    $group = file_prepare_standard_editor($group, 'description', $editoroptions, $context, 'group', 'description', null);
    if ($args->id > 0) {
        $heading = 'Update User';
        $collapse = false;
        $data = $DB->get_record('user', array('id' => $args->id));
        unset($data->password);

        $reportingmanger = $DB->record_exists('user', ['id' => $data->open_supervisorid, 'deleted' => 0, 'suspended' => 0]);

        if (!$reportingmanger) {
            unset($data->open_supervisorid);
        }

        $path = explode('/', $data->open_path);
        if(!is_siteadmin($args->id)){
            $costid = $path[2] ?: ($path[1] ?: 0);
            $categorysql = $DB->get_record('local_costcenter', ['id' => $costid], $fields = 'category', $strictness = IGNORE_MISSING);
            $categoryid = $categorysql->category;
            $categorycontext = \context_coursecat::instance($categoryid);
            $var = $DB->get_record('role_assignments', array('userid' => $data->id, 'contextid' => $categorycontext->id));
            if ($var) {
                $data->roleid = $var->roleid;
            }
        }

        useredit_load_preferences($data);
        $customdata = array(
            'editoroptions' => $editoroptions,
            'form_status' => $args->form_status,
            'id' => $data->id,
            'open_positionid' => $data->open_positionid,
            'open_domainid' => $data->open_domainid,
            'open_path' => $data->open_path,
            'roleid' => $var->roleid,
        );
        local_costcenter_set_costcenter_path($customdata);
        local_users_set_userprofile_datafields($customdata, $data);
        $mform = new local_users\forms\create_user(
            null,
            $customdata,
            'post',
            '',
            null,
            true,
            $formdata
        );

        $data->open_costcenterid = $formdata['open_costcenterid'];

        profile_load_data($data);
        $mform->set_data($data);
    } else {
        $customdata = array(
            'editoroptions' => $editoroptions,
            'form_status' => $args->form_status
        );
        local_costcenter_set_costcenter_path($customdata);
        local_users_set_userprofile_datafields($customdata, $args);
        $mform = new local_users\forms\create_user(null, $customdata, 'post', '', null, true, $formdata);
    }

    if (!empty($args->jsonformdata) && strlen($args->jsonformdata) > 2) {
        // If we were passed non-empty form data we want the mform to call validation functions and show errors.
        if (!isset($formdata['skipvalidation'])) {
            $mform->is_validated();
        }
    }
    $formheaders = array_keys($mform->formstatus);
    $nextform = array_key_exists($args->form_status, $formheaders);
    if ($nextform === false) {
        return false;
    }
    $renderer = $PAGE->get_renderer('local_users');
    ob_start();
    $formstatus = array();
    foreach (array_values($mform->formstatus) as $k => $mformstatus) {
        $activeclass = $k == $args->form_status ? 'active' : '';
        $formstatus[] = array('name' => $mformstatus, 'activeclass' => $activeclass, 'form-status' => $k);
    }
    $formstatusview = new \local_users\output\form_status($formstatus);
    $o .= $renderer->render($formstatusview);
    $mform->display();
    $o .= ob_get_contents();
    ob_end_clean();
    return $o;
}
/**
 * Description: User fullname filter code
 * @param  [mform object]  $mform          [the form object where the form is initiated]
 * @param  string  $query          [text inserted in filter]
 * @param  boolean $searchanywhere [description]
 * @param  integer $page           [page value]
 * @param  integer $perpage        [entities per page]
 */
function users_filter($mform, $query = '', $searchanywhere = false, $page = 0, $perpage = 25) {
    global $DB, $USER;

    $categorycontext = (new \local_users\lib\accesslib())::get_module_context();

    $costcenterpathconcatsql = (new \local_users\lib\accesslib())::get_costcenter_path_field_concatsql($columnname = 'open_path');

    $userslist = array();
    $data = data_submitted();

    $userslistparams = array('adminuserid' => 2, 'deleted' => 0, 'suspended' => 0, 'userid' => $USER->id);

    if (is_siteadmin() || has_capability('local/costcenter:manage_multiorganizations', $categorycontext)) {

        $userslist_sql = "SELECT id, concat(firstname,' ',lastname) as fullname FROM {user}
         WHERE id > :adminuserid AND deleted = :deleted AND suspended = :suspended AND id <> :userid  ";
    } else {

        $userslist_sql = "SELECT id, concat(firstname,' ',lastname) as fullname FROM {user}
         WHERE id > :adminuserid AND deleted = :deleted
          AND suspended = :suspended AND id <> :userid  $costcenterpathconcatsql";
    }

    // if (!empty($query)) {
    //     if ($searchanywhere) {
    //         $likesql = $DB->sql_like("CONCAT(firstname, ' ',lastname)", "'%$query%'", false);
    //         $userslist_sql .= " AND $likesql ";
    //     } else {
    //         $likesql = $DB->sql_like("CONCAT(firstname, ' ',lastname)", "'$query%'", false);
    //         $userslist_sql .= " AND $likesql ";
    //     }
    // }

    if (!empty($query)) {
        $userslist_sql .= " AND  CONCAT(firstname, ' ',lastname) LIKE :fullname ";
        $userslistparams['fullname'] = '%' . trim($query) . '%';
    }
    if (isset($data->users) && !empty(($data->users))) {

        list($usersql, $userparam) = $DB->get_in_or_equal($data->users, SQL_PARAMS_NAMED);
        $userslist_sql .= " AND id $usersql ";
        $userslistparams = $userparam + $userslistparams;
    }
    if (!empty($query) || empty($mform)) {
        $userslist = $DB->get_records_sql($userslist_sql, $userslistparams, $page, $perpage);
        return $userslist;
    }
    if ((isset($data->users) && !empty($data->users))) {
        $userslist = $DB->get_records_sql_menu($userslist_sql, $userslistparams, $page, $perpage);
    }

    $options = array(
        'ajax' => 'local_courses/form-options-selector',
        'multiple' => true,
        'data-action' => 'users',
        'data-options' => json_encode(array('id' => 0)),
        'placeholder' => get_string('users')
    );
    $select = $mform->addElement('autocomplete', 'users', '', $userslist, $options);
    $mform->setType('users', PARAM_RAW);
}
/**
 * Description: User email filter code
 * @param  [mform object]  $mform          [the form object where the form is initiated]
 * @param  string  $query          [text inserted in filter]
 * @param  boolean $searchanywhere [description]
 * @param  integer $page           [page value]
 * @param  integer $perpage        [entities per page]
 */
function email_filter($mform, $query = '', $searchanywhere = false, $page = 0, $perpage = 25) {
    global $DB, $USER;

    $categorycontext = (new \local_users\lib\accesslib())::get_module_context();

    $costcenterpathconcatsql = (new \local_users\lib\accesslib())::get_costcenter_path_field_concatsql($columnname = 'open_path');

    $userslist = array();
    $data = data_submitted();
    $userslistparams = array('adminuserid' => 2, 'deleted' => 0, 'suspended' => 0, 'userid' => $USER->id);
    if (is_siteadmin() || has_capability('local/costcenter:manage_multiorganizations', $categorycontext)) {
        $userslist_sql = "SELECT id, email as fullname FROM {user} WHERE id > :adminuserid AND deleted = :deleted AND
         suspended = :suspended AND id <> :userid ";
    } else {
        $userslist_sql = "SELECT id, email as fullname FROM {user} WHERE id >
         :adminuserid AND deleted = :deleted AND suspended = :suspended AND id <> :userid $costcenterpathconcatsql";
    }
    if (!empty($query)) {
        $userslist_sql .= " AND  email LIKE :email ";
        $userslistparams['email'] = '%' . trim($query) . '%';
    }
    // if (!empty($query)) {
    //     if ($searchanywhere) {
    //         $likesql = $DB->sql_like('email', "'%$query%'", false);
    //         $userslist_sql .= " AND $likesql ";
    //     } else {
    //         $likesql = $DB->sql_like('email', "'$query%'", false);
    //         $userslist_sql .= " AND $likesql ";
    //     }
    // }
    if (isset($data->email) && !empty(($data->email))) {

        list($mailsql, $mailparam) = $DB->get_in_or_equal($data->email, SQL_PARAMS_NAMED);
        $userslist_sql .= " AND id $mailsql ";
        $userslistparams = $mailparam + $userslistparams;
    }
    $userslist_sql .= " AND email != ''";
    if (!empty($query) || empty($mform)) {
        $userslist = $DB->get_records_sql($userslist_sql, $userslistparams, $page, $perpage);
        return $userslist;
    }
    if ((isset($data->email) && !empty($data->email))) {

        $userslist = $DB->get_records_sql_menu($userslist_sql, $userslistparams, $page, $perpage);
    }
    $options = array(
        'ajax' => 'local_courses/form-options-selector',
        'multiple' => true,
        'data-action' => 'email',
        'data-options' => json_encode(array('id' => 0)),
        'placeholder' => get_string('select_email', 'local_users')
    );
    $select = $mform->addElement('autocomplete', 'email', get_string('email', 'local_users'), $userslist, $options);
    $mform->setType('email', PARAM_RAW);
}
/**
 * Description: User employeeid filter code
 * @param  [mform object]  $mform          [the form object where the form is initiated]
 * @param  string  $query          [text inserted in filter]
 * @param  boolean $searchanywhere [description]
 * @param  integer $page           [page value]
 * @param  integer $perpage        [entities per page]
 */
function employeeid_filter($mform, $query = '', $searchanywhere = false, $page = 0, $perpage = 25) {
    global $DB, $USER;

    $categorycontext = (new \local_users\lib\accesslib())::get_module_context();

    $costcenterpathconcatsql = (new \local_users\lib\accesslib())::get_costcenter_path_field_concatsql($columnname = 'open_path');

    $userslist = array();
    $data = data_submitted();
    $userslistparams = array('adminuserid' => 2, 'deleted' => 0, 'suspended' => 0, 'userid' => $USER->id);
    if (is_siteadmin() || has_capability('local/costcenter:manage_multiorganizations', $categorycontext)) {
        $userslist_sql = "SELECT id, open_employeeid as fullname FROM {user} WHERE id >
         :adminuserid AND deleted = :deleted AND suspended = :suspended AND id <> :userid";
    } else {
        $userslist_sql = "SELECT id, open_employeeid as fullname FROM {user} WHERE id > :adminuserid AND deleted = :deleted AND suspended = :suspended
          AND id <> :userid $costcenterpathconcatsql";
    }
    // if (!empty($query)) {
    //     if ($searchanywhere) {
    //         $likesql = $DB->sql_like('open_employeeid', "'%$query%'", false);
    //         $userslist_sql .= " AND $likesql ";
    //     } else {
    //         $likesql = $DB->sql_like('open_employeeid', "'$query%'", false);
    //         $userslist_sql .= " AND $likesql ";
    //     }
    // }

    if (!empty($query)) {
        $userslist_sql .= " AND  open_employeeid LIKE :open_employeeid ";
        $userslistparams['open_employeeid'] = '%' . trim($query) . '%';
    }
    if (isset($data->idnumber) && !empty(($data->idnumber))) {
        list($idsql, $idparam) = $DB->get_in_or_equal($data->idnumber, SQL_PARAMS_NAMED);
        $userslist_sql .= " AND id $idsql ";
        $userslistparams = $idparam + $userslistparams;
    }
    if (!empty($query) || empty($mform)) {
        $userslist = $DB->get_records_sql($userslist_sql, $userslistparams, $page, $perpage);
        return $userslist;
    }
    if ((isset($data->idnumber) && !empty($data->idnumber))) {
        $userslist = $DB->get_records_sql_menu($userslist_sql, $userslistparams);
    }
    $options = array(
        'ajax' => 'local_courses/form-options-selector',
        'multiple' => true,
        'data-action' => 'employeeid',
        'data-options' => json_encode(array('id' => 0)),
        'placeholder' => get_string('idnumber_select', 'local_users')
    );
    $select = $mform->addElement('autocomplete', 'idnumber', get_string('idnumber', 'local_users'), $userslist, $options);
    $mform->setType('idnumber', PARAM_RAW);
}
/**
 * Description: User designation filter code
 * @param  [mform object]  $mform          [the form object where the form is initiated]
 */
function designation_filter($mform) {
    global $DB, $USER;

    $categorycontext = (new \local_users\lib\accesslib())::get_module_context();

    $costcenterpathconcatsql = (new \local_users\lib\accesslib())::get_costcenter_path_field_concatsql($columnname = 'open_path');

    $userslistparams = array('adminuserid' => 2, 'deleted' => 0, 'suspended' => 0, 'userid' => $USER->id);
    if (is_siteadmin() || has_capability('local/costcenter:manage_multiorganizations', $categorycontext)) {
        $userslist_sql = "SELECT id, open_designation FROM {user} WHERE id > :adminuserid
         AND deleted = :deleted AND suspended = :suspended AND open_designation IS NOT NULL AND open_designation !='' AND id <> :userid";
    } else {
        $userslist_sql = "SELECT id, open_designation FROM {user} WHERE id > :adminuserid  AND deleted = :deleted AND suspended =
          :suspended AND open_designation IS NOT NULL AND open_designation !=''  AND id <> :userid $costcenterpathconcatsql";
    }
    $userslist = $DB->get_records_sql_menu($userslist_sql, $userslistparams);
    $select = $mform->addElement(
        'autocomplete',
        'designation',
        '',
        $userslist,
        array('placeholder' => get_string('select_designation', 'local_users'))
    );
    $mform->setType('idnumber', PARAM_RAW);
    $select->setMultiple(true);
}

function states_filter($mform) {
    global $DB, $USER;

    $categorycontext = (new \local_users\lib\accesslib())::get_module_context();

    if (is_siteadmin()) {

        $stateslist_sql = "SELECT id, states_name FROM {local_states} ";
    } else {

        $costcenterpaths = (new \local_costcenter\lib\accesslib())::get_user_role_switch_path();

        $orgids = array();

        foreach ($costcenterpaths as $costcenterpath) {

            list($zero, $org, $ctr, $bu, $cu, $territory) = explode("/", $costcenterpath);

            $orgids[$org] = $org;
        }
        $implodeorgids = implode(',', $orgids);

        $stateslist_sql = "SELECT id, states_name FROM {local_states} WHERE costcenterid in ($implodeorgids) ";
    }

    $stateslist = $DB->get_records_sql_menu($stateslist_sql);
    $select = $mform->addElement(
        'autocomplete',
        'states',
        '',
        $stateslist,
        array('placeholder' => get_string('states', 'local_users'))
    );
    $mform->setType('states', PARAM_RAW);
    $select->setMultiple(true);
}

function district_filter($mform) {
    global $DB, $USER;

    $categorycontext = (new \local_users\lib\accesslib())::get_module_context();

    if (is_siteadmin()) {

        $districtlist_sql = "SELECT id, district_name FROM {local_district} ";
    } else {

        $costcenterpaths = (new \local_costcenter\lib\accesslib())::get_user_role_switch_path();

        $orgids = array();

        foreach ($costcenterpaths as $costcenterpath) {

            list($zero, $org, $ctr, $bu, $cu, $territory) = explode("/", $costcenterpath);

            $orgids[$org] = $org;
        }
        $implodeorgids = implode(',', $orgids);

        $districtlist_sql = "SELECT id, district_name FROM {local_district} WHERE costcenterid in ($implodeorgids) ";
    }

    $districtlist = $DB->get_records_sql_menu($districtlist_sql);

    $select = $mform->addElement(
        'autocomplete',
        'district',
        '',
        $districtlist,
        array('placeholder' => get_string('district', 'local_users'))
    );
    $mform->setType('district', PARAM_RAW);
    $select->setMultiple(true);
}

function subdistrict_filter($mform) {
    global $DB, $USER;

    $categorycontext = (new \local_users\lib\accesslib())::get_module_context();

    if (is_siteadmin()) {

        $subdistrictlist_sql = "SELECT id, subdistrict_name FROM {local_subdistrict} ";
    } else {

        $costcenterpaths = (new \local_costcenter\lib\accesslib())::get_user_role_switch_path();

        $orgids = array();

        foreach ($costcenterpaths as $costcenterpath) {

            list($zero, $org, $ctr, $bu, $cu, $territory) = explode("/", $costcenterpath);

            $orgids[$org] = $org;
        }
        $implodeorgids = implode(',', $orgids);

        $subdistrictlist_sql = "SELECT id, subdistrict_name FROM {local_subdistrict} WHERE costcenterid in ($implodeorgids) ";
    }

    $subdistrictlist = $DB->get_records_sql_menu($subdistrictlist_sql);

    $select = $mform->addElement(
        'autocomplete',
        'subdistrict',
        '',
        $subdistrictlist,
        array('placeholder' => get_string('subdistrict', 'local_users'))
    );
    $mform->setType('subdistrict', PARAM_RAW);
    $select->setMultiple(true);
}

function village_filter($mform) {
    global $DB, $USER;

    $categorycontext = (new \local_users\lib\accesslib())::get_module_context();

    if (is_siteadmin()) {

        $villagelist_sql = "SELECT id, village_name FROM {local_village} ";
    } else {

        $costcenterpaths = (new \local_costcenter\lib\accesslib())::get_user_role_switch_path();

        $orgids = array();

        foreach ($costcenterpaths as $costcenterpath) {

            list($zero, $org, $ctr, $bu, $cu, $territory) = explode("/", $costcenterpath);

            $orgids[$org] = $org;
        }
        $implodeorgids = implode(',', $orgids);

        $villagelist_sql = "SELECT id, village_name FROM {local_village}  WHERE costcenterid in ($implodeorgids) ";
    }

    $villagelist = $DB->get_records_sql_menu($villagelist_sql);

    $select = $mform->addElement(
        'autocomplete',
        'village',
        '',
        $villagelist,
        array('placeholder' => get_string('village', 'local_users'))
    );
    $mform->setType('village', PARAM_RAW);
    $select->setMultiple(true);
}
/**
 * Description: User location filter code
 * @param  [mform object]  $mform          [the form object where the form is initiated]
 */
function location_filter($mform) {
    global $DB, $USER;

    $categorycontext = (new \local_users\lib\accesslib())::get_module_context();

    $costcenterpathconcatsql = (new \local_users\lib\accesslib())::get_costcenter_path_field_concatsql($columnname = 'u.open_path');

    $userslistparams = array('adminuserid' => 2, 'deleted' => 0, 'suspended' => 0, 'userid' => $USER->id);
    if (is_siteadmin() || has_capability('local/costcenter:manage_multiorganizations', $categorycontext)) {
        $userslist_sql = "SELECT u.city, u.city AS name FROM {user} AS u WHERE
         u.id > :adminuserid AND u.deleted = :deleted AND u.suspended = :suspended AND u.id <> :userid ";
    } else {
        $userslist_sql = "SELECT u.city, u.city AS name FROM {user} AS u WHERE u.id > :adminuserid
          AND u.deleted = :deleted AND u.suspended = :suspended
          AND u.id <> :userid $costcenterpathconcatsql";
    }
    $userslist_sql .= " AND u.city != '' AND u.city IS NOT NULL GROUP BY u.city ";
    $userslist = $DB->get_records_sql_menu($userslist_sql, $userslistparams);
    $select = $mform->addElement('autocomplete', 'location', '', $userslist, array('placeholder' =>
    get_string('location', 'local_users')));
    $mform->setType('idnumber', PARAM_RAW);
    $select->setMultiple(true);
}
/**
 * [role_filter form element function]
 * @param  [form] $mform [filter form]
 * @return 
 */
function roles_filter($mform, $query='',$searchanywhere=false, $page=0, $perpage=25){
    global $DB, $USER;
    // Get the category context from the local_users access library.
    $categorycontext = (new \local_users\lib\accesslib())::get_module_context();

    $roleslist = array();
    $data = data_submitted();
    $costcenterpathconcatsql = (new \local_groups\lib\accesslib())::get_costcenter_path_field_concatsql($columnname='open_path');
    $roleslist_sql = "SELECT r.id, r.name as fullname FROM {role} r 
    JOIN {role_context_levels} rcl ON (rcl.contextlevel = 40 AND r.id = rcl.roleid)  
    WHERE r.name != '' AND (r.archetype != 'manager' ";

    // Adjust query conditions based on context depth.
    if ($categorycontext->depth == 1) {
        $roleslist_sql .= " OR r.shortname LIKE 'administrator' ";
    }
    if ($categorycontext->depth == 2 || is_siteadmin()) {
        $roleslist_sql .= " OR r.shortname LIKE 'hod' ";
    }

    // Close the SQL query.
    $roleslist_sql .= ")";
    
    // $rolesparam['contextlevel'] = CONTEXT_COURSECAT;
    if(!empty($query)){ 
        $roleslist_sql .= " AND r.name LIKE :name ";
        $rolesparam['name'] = '%' . trim($query) . '%';
    }

    if(isset($data->roles) && !empty(($data->roles))){
        list($roles_sql, $rolesparam) = $DB->get_in_or_equal($data->roles, SQL_PARAMS_NAMED);
        $roleslist_sql.=" AND r.id $roles_sql ";
        $rolesparam = array_merge($rolesparam, $rolesparam);
    }
    // Execute query and return results.
    if (!empty($query) || empty($mform)) {
        $roleslist = $DB->get_records_sql($roleslist_sql, $rolesparam, $page, $perpage);
        return $roleslist;
    }
    if((isset($data->roles)&&!empty($data->roles))){ 
        $roleslist = $DB->get_records_sql_menu($roleslist_sql, $rolesparam, $page, $perpage);
    }
    $options = array(
        'ajax' => 'local_courses/form-options-selector',
        'multiple' => true,
        'data-action' => 'roles',
        'data-options' => json_encode(array('id' => 0)),
        'placeholder' => get_string('open_role', 'local_users')
     );
    $select = $mform->addElement('autocomplete', 'roles', '', $roleslist, $options);
    $mform->setType('roles', PARAM_RAW);
}
/**
 * Description: User hrmsrole filter code
 * @param  [mform object]  $mform          [the form object where the form is initiated]
 */
function hrmsrole_filter($mform) {
    global $DB, $USER;
    $categorycontext = (new \local_users\lib\accesslib())::get_module_context();

    $costcenterpathconcatsql = (new \local_users\lib\accesslib())::get_costcenter_path_field_concatsql($columnname = 'u.open_path');

    $userslistparams = array('adminuserid' => 2, 'deleted' => 0, 'suspended' => 0, 'userid' => $USER->id);
    if (is_siteadmin() || has_capability('local/costcenter:manage_multiorganizations', $categorycontext)) {
        $userslist_sql = "SELECT u.open_hrmsrole, u.open_hrmsrole as name FROM {user} AS u WHERE u.id >
         :adminuserid AND u.deleted = :deleted AND u.suspended = :suspended AND u.id <> :userid ";
    } else {
        $userslist_sql = "SELECT u.open_hrmsrole, u.open_hrmsrole as name FROM
         {user} AS u WHERE u.id > :adminuserid
          AND u.deleted = :deleted AND u.suspended = :suspended $costcenterpathconcatsql";
    }
    $userslist_sql .= " AND u.open_hrmsrole != '' AND u.open_hrmsrole IS NOT NULL GROUP BY u.open_hrmsrole ";
    $userslist = $DB->get_records_sql_menu($userslist_sql, $userslistparams);
    $select = $mform->addElement('autocomplete', 'hrmsrole', '', $userslist, array('placeholder' =>
    get_string('open_hrmsrole', 'local_users')));
    $mform->setType('hrmsrole', PARAM_RAW);
    $select->setMultiple(true);
}
/**
 * Description: User project filter code
 * @param  [mform object]  $mform          [the form object where the form is initiated]
 */
function project_filter($mform) {
    global $DB, $USER;
    $categorycontext = (new \local_users\lib\accesslib())::get_module_context();

    $costcenterpathconcatsql = (new \local_users\lib\accesslib())::get_costcenter_path_field_concatsql($columnname = 'u.open_path');

    $userslistparams = array('adminuserid' => 2, 'deleted' => 0, 'suspended' => 0, 'userid' => $USER->id);
    if (is_siteadmin() || has_capability('local/costcenter:manage_multiorganizations', $categorycontext)) {
        $userslist_sql = "SELECT u.open_project, u.open_project as name FROM {user} AS u WHERE u.id >
         :adminuserid AND u.deleted = :deleted AND u.suspended = :suspended AND u.id <> :userid ";
    } else {
        $userslist_sql = "SELECT u.open_project, u.open_project as name FROM
         {user} AS u WHERE u.id > :adminuserid
          AND u.deleted = :deleted AND u.suspended = :suspended $costcenterpathconcatsql";
    }
    $userslist_sql .= " AND u.open_project != '' AND u.open_project IS NOT NULL GROUP BY u.open_project ";
    $userslist = $DB->get_records_sql_menu($userslist_sql, $userslistparams);
    $select = $mform->addElement('autocomplete', 'project', '', $userslist, array('placeholder' =>
    get_string('open_project', 'local_users')));
    $mform->setType('project', PARAM_RAW);
    $select->setMultiple(true);
}

/**
 * Description: User band filter code
 * @param  [mform object]  $mform          [the form object where the form is initiated]
 */
function band_filter($mform) {
    global $DB, $USER;

    $categorycontext = (new \local_users\lib\accesslib())::get_module_context();

    $costcenterpathconcatsql = (new \local_users\lib\accesslib())::get_costcenter_path_field_concatsql($columnname = 'open_path');

    $userslistparams = array('adminuserid' => 2, 'deleted' => 0, 'suspended' => 0, 'userid' => $USER->id);
    if (is_siteadmin() || has_capability('local/costcenter:manage_multiorganizations', $categorycontext)) {
        $userslist_sql = "SELECT id, open_band FROM {user} WHERE id > :adminuserid AND deleted = :deleted
         AND suspended = :suspended AND open_band IS NOT NULL AND open_band != '' AND id <> :userid ";
    } else {
        $userslist_sql = "SELECT id, open_band FROM {user} WHERE id > :adminuserid  AND deleted = :deleted AND suspended = :suspended AND open_band IS NOT NULL AND open_band != '' AND id <> :userid $costcenterpathconcatsql";
    }
    $userslist = $DB->get_records_sql_menu($userslist_sql, $userslistparams);
    $select = $mform->addElement('autocomplete', 'band', '', $userslist, array('placeholder' => get_string('select_band', 'local_users')));
    $mform->setType('idnumber', PARAM_RAW);
    $select->setMultiple(true);
}

/**
 * Description: User customername filter code
 * @param  [mform object]  $mform          [the form object where the form is initiated]
 */
function customername_filter($mform) {
    global $DB, $USER;

    $categorycontext = (new \local_users\lib\accesslib())::get_module_context();

    $costcenterpathconcatsql = (new \local_users\lib\accesslib())::get_costcenter_path_field_concatsql($columnname = 'open_path');

    $userslistparams = array('adminuserid' => 2, 'deleted' => 0, 'suspended' => 0, 'userid' => $USER->id);
    if (is_siteadmin() || has_capability('local/costcenter:manage_multiorganizations', $categorycontext)) {
        $userslist_sql = "SELECT id, open_customername FROM {user} WHERE id > :adminuserid AND deleted = :deleted
         AND suspended = :suspended AND open_customername IS NOT NULL AND open_customername != '' AND id <> :userid ";
    } else {
        $userslist_sql = "SELECT id, open_customername FROM {user} WHERE id > :adminuserid  AND deleted = :deleted AND suspended = :suspended AND open_customername IS NOT NULL AND open_customername != '' AND id <> :userid $costcenterpathconcatsql";
    }
    $userslist = $DB->get_records_sql_menu($userslist_sql, $userslistparams);
    $select = $mform->addElement('autocomplete', 'customername', '', $userslist, array('placeholder' => get_string('select_customername', 'local_users')));
    $mform->setType('customername', PARAM_RAW);
    $select->setMultiple(true);
}

/**
 * Description: User name filter code
 * @param  [mform object]  $mform          [the form object where the form is initiated]
 */
function username_filter($mform) {
    global $DB, $USER;

    $categorycontext = (new \local_users\lib\accesslib())::get_module_context();

    $costcenterpathconcatsql = (new \local_users\lib\accesslib())::get_costcenter_path_field_concatsql($columnname = 'open_path');

    $userslistparams = array('adminuserid' => 2, 'deleted' => 0, 'suspended' => 0, 'userid' => $USER->id);
    if (is_siteadmin() || has_capability('local/costcenter:manage_multiorganizations', $categorycontext)) {
        $userslist_sql = "SELECT id, username FROM {user} WHERE id > :adminuserid AND deleted = :deleted
         AND suspended = :suspended AND id <> :userid";
    } else {

        $userslist_sql = "SELECT id, username FROM {user} WHERE id > :adminuserid  AND deleted = :deleted AND suspended = :suspended AND id <> :userid $costcenterpathconcatsql";
    }
    $userslist = $DB->get_records_sql_menu($userslist_sql, $userslistparams);
    $select = $mform->addElement('autocomplete', 'username', '', $userslist, array('placeholder' => get_string('username')));
    $mform->setType('username', PARAM_RAW);
    $select->setMultiple(true);
}
/**
 * Description: User custom filter code
 * @param  [mform object]  $mform          [the form object where the form is initiated]
 */
function custom_filter($mform) {
    global $DB, $USER;

    $categorycontext = (new \local_users\lib\accesslib())::get_module_context();

    $costcenterpathconcatsql = (new \local_users\lib\accesslib())::get_costcenter_path_field_concatsql($columnname = 'open_path');

    $filterv = $DB->get_field('local_filters', 'filters', array('plugins' => 'users'));
    $filterv = explode(',', $filterv);
    foreach ($filterv as $fieldvalue) {
        $userslistparams = array('adminuserid' => 2, 'deleted' => 0, 'suspended' => 0, 'userid' => $USER->id);
        if (is_siteadmin() || has_capability('local/costcenter:manage_multiorganizations', $categorycontext)) {
            $userslist_sql = "SELECT id, $fieldvalue FROM {user} WHERE id > :adminuserid AND deleted =
             :deleted AND suspended = :suspended AND id <> :userid ";
        } else {
            $userslist_sql = "SELECT id, $fieldvalue FROM {user} WHERE id > :adminuserid  AND deleted = :deleted AND suspended = :suspended AND
              id <> :userid $costcenterpathconcatsql";
        }
        $userslist = $DB->get_records_sql_menu($userslist_sql, $userslistparams);
        $select = $mform->addElement('autocomplete', $fieldvalue, '', $userslist, array('placeholder'
        => get_string($fieldvalue, 'local_users')));
        $mform->setType($fieldvalue, PARAM_RAW);
        $select->setMultiple(true);
    }
}
// OL-1042 Add Target Audience to Classrooms//
/**
 * [globaltargetaudience_elementlist description]
 * @param  [type] $mform       [description]
 * @param  [type] $elementlist [description]
 * @return [type]              [description]
 */
function globaltargetaudience_elementlist($mform, $elementlist, $costcenter = null) {
    global $CFG, $DB, $USER;
    $context = (new \local_users\lib\accesslib())::get_module_context();
    $element = $mform->_elements[15];
    if ($element->_attributes['name'] == 'evaluationhdr') {
        $options = array(
            'ajax' => 'local_users/form-options-selector',
            'multiple' => true,
            'data-contextid' => $context->id,
            'data-action' => 'eval_group_selector',
            'data-options' => json_encode(array('organizationselect' => 'open_costcenterid_select')),
            'class' => 'trainerselect',
            'data-parentclass' => 'open_costcenterid_select',
            'data-class' => 'open_costcenterid_select',
        );
        $params = array();
        $params['deleted'] = 0;
        $params['suspended'] = 0;
        if ($mform->modulecostcenterpath == 0) {
            $main_sql = "";
        } else {
            $costcenterpath = $mform->modulecostcenterpath ? $mform->modulecostcenterpath : $USER->open_path;

            $costcenterpathconcatsql = (new \local_costcenter\lib\accesslib())::get_costcenter_path_field_concatsql($columnname = 'u.open_path', $costcenterpath);

            $main_sql = " AND u.suspended = :suspended AND u.deleted =:deleted $costcenterpathconcatsql ";
        }
        $dbman = $DB->get_manager();
        $grouplist[0] = array('id' => 0, 'fullname' => get_string('all'));
        $costcenterpathconcatsql = (new \local_costcenter\lib\accesslib())::get_costcenter_path_field_concatsql($columnname = 'g.open_path', $costcenterpath);
        $grouplist += $DB->get_records_sql_menu(
            "SELECT c.id, c.name as fullname FROM {local_groups} g, {cohort} c
    WHERE c.visible = :visible AND c.id = g.cohortid  $costcenterpathconcatsql ",
            array('visible' => 1)
        );
        if (in_array('group', $elementlist)) {
            $selectgroup = $mform->addElement(
                'autocomplete',
                'open_group',
                get_string('open_group', 'local_users'),
                $grouplist,
                $options
            );
            $mform->setType('open_group', PARAM_RAW);
            $mform->addHelpButton('open_group', 'groups', 'local_users');
            $selectgroup->setMultiple(true);
        }
    } else {
        $plugin_exists = \core_component::get_plugin_directory('local', 'groups');
        if ($plugin_exists) {
            if (in_array('group', $elementlist)) {
                $groupslist[null] = get_string('all');

                $costcenterpathconcatsql = (new \local_costcenter\lib\accesslib())::get_costcenter_path_field_concatsql($columnname = 'g.open_path', $costcenterpath);
                if (is_siteadmin()) {
                    $costcenterpathconcatsql = "AND ( (  g.open_path LIKE '/" . $costcenter . "/%'  OR  g.open_path LIKE '/" . $costcenter . "'  ) )";
                }

                $groupslist += $DB->get_records_sql_menu(
                    "SELECT c.id, c.name FROM {local_groups} g, {cohort} c
              WHERE c.visible = :visible AND c.id = g.cohortid $costcenterpathconcatsql ",
                    array('visible' => 1)
                );

                $selectgroup = $mform->addElement(
                    'autocomplete',
                    'open_group',
                    get_string('open_group', 'local_users'),
                    $groupslist
                );
                $mform->setType('open_group', PARAM_RAW);
                $mform->addHelpButton('open_group', 'groups', 'local_users');
                $selectgroup->setMultiple(true);
            }
        }
    }
    if (in_array('hrmsrole', $elementlist)) {
        $hrmsrole_details[null] = get_string('all');
        $hrmsrole_sql = "SELECT u.open_hrmsrole, u.open_hrmsrole AS hrmsrolevalue FROM {user} AS u WHERE u.id
         > 2 $main_sql AND u.open_hrmsrole IS NOT NULL GROUP BY u.open_hrmsrole";
        $hrmsrole_details += $DB->get_records_sql_menu($hrmsrole_sql, $params);
        $selecthrmsrole = $mform->addElement(
            'autocomplete',
            'open_hrmsrole',
            get_string('hrmrole', 'local_users'),
            $hrmsrole_details
        );
        $mform->setType('open_hrmsrole', PARAM_RAW);
        $mform->addHelpButton('open_hrmsrole', 'role', 'local_users');
        $selecthrmsrole->setMultiple(true);
    }
    if (in_array('designation', $elementlist)) {
        $costcenterpathconcatsql = (new \local_costcenter\lib\accesslib())::get_costcenter_path_field_concatsql($columnname = 'u.open_path', $costcenterpath);
        if (is_siteadmin()) {
            $costcenterpathconcatsql = "AND ( (  u.open_path LIKE '/" . $costcenter . "/%'  OR  u.open_path LIKE '/" . $costcenter . "'  ) )";
        }

        $designation_details[null] = get_string('all');
        $designation_sql = "SELECT u.open_designation,u.open_designation AS designationvalue FROM {user} AS
         u WHERE u.id > 2 $main_sql AND u.open_designation IS NOT NULL AND u.open_designation !='' $costcenterpathconcatsql ";

        $designation_details += $DB->get_records_sql_menu($designation_sql, $params);
        $selectdesignation = $mform->addElement(
            'autocomplete',
            'open_designation',
            get_string('open_designation', 'local_users'),
            $designation_details
        );
        $mform->setType('open_designation', PARAM_RAW);
        $mform->addHelpButton('open_designation', 'designation', 'local_users');
        $selectdesignation->setMultiple(true);
    }
    if (in_array('location', $elementlist)) {
        $location_details[null] = get_string('all');
        $location_sql = "SELECT u.city, u.city AS locationvalue FROM {user} AS u WHERE u.id > 2 $main_sql AND
         u.city IS NOT NULL GROUP BY u.city    ";
        $location_details += $DB->get_records_sql_menu($location_sql, $params);
        $selectlocation = $mform->addElement('autocomplete', 'open_location', get_string(
            'open_location',
            'local_users'
        ), $location_details);
        $mform->setType('open_location', PARAM_RAW);
        $mform->addHelpButton('open_location', 'location', 'local_users');
        $selectlocation->setMultiple(true);
    }

    if (in_array('band', $elementlist)) {
        $band_details[null] = get_string('all');
        $band_sql = "SELECT u.open_band,u.open_band AS bandvalue FROM {user} AS u WHERE u.id > 2 $main_sql AND
         u.open_band IS NOT NULL GROUP BY u.open_band";
        $band_details += $DB->get_records_sql_menu($band_sql, $params);
        $selectband = $mform->addElement('autocomplete', 'open_band', get_string('open_band', 'local_users'), $band_details);
        $mform->setType('open_band', PARAM_RAW);
        $selectband->setMultiple(true);
    }
}
/*
* Author Rizwana
* Displays a node in left side menu
* @return  [type] string  link for the leftmenu
*/
function local_users_leftmenunode() {
    global $USER, $DB;

    $categorycontext = (new \local_users\lib\accesslib())::get_module_context();
    $usersnode = '';
    $key = '';
    if (has_capability('local/users:manage', $categorycontext) || has_capability(
        'local/users:view',
        $categorycontext
    ) || is_siteadmin()) {
        $usersnode .= html_writer::start_tag('li', array('id' => 'id_leftmenu_users', 'class' => 'pull-left user_nav_div users'));
        $users_url = new moodle_url('/local/users/index.php');
        $users = html_writer::link(
            $users_url,
            '<i class="fa fa-user-plus" aria-hidden="true"></i>
                <span class="user_navigation_link_text">' . get_string('manage_users', 'local_users') . '</span>',
            array('class' => 'user_navigation_link')
        );
        $usersnode .= $users;
        $usersnode .= html_writer::end_tag('li');
        $key = array('2' => $usersnode);
    }
    return $key;
}

function local_users_quicklink_node() {
    global $DB, $PAGE, $USER, $CFG, $OUTPUT;

    $orgid  = optional_param('orgid', 0, PARAM_INT);

    $categorycontext = (new \local_users\lib\accesslib())::get_module_context();

    $costcenterpathconcatsql = (new \local_users\lib\accesslib())::get_costcenter_path_field_concatsql($columnname = 'open_path');

    $local_users = '';
    if (is_siteadmin() || has_capability('local/users:view', $categorycontext)) {
        $sql = "SELECT count(id) FROM {user} WHERE id > 2  AND deleted = :deleted ";
        $suspendsql = " AND suspended = :suspended ";

        $params = array();
        $params['deleted'] = 0;

        $activeparams = array();
        $activeparams['suspended'] = 0;
        $activeparams['deleted'] = 0;

        $inactiveparams = array();
        $inactiveparams['suspended'] = 1;
        $inactiveparams['deleted'] = 0;

        if (is_siteadmin() && $orgid == 0) {
            $sql .= "";
        } else {
            //costcenterid concating
            $sql .= " AND id != :userid " . $costcenterpathconcatsql;
            $activeparams['userid'] = $USER->id;
            $inactiveparams['userid'] = $USER->id;
            $params['userid'] = $USER->id;
        }

        $count_activeusers = $DB->count_records_sql($sql . $suspendsql, $activeparams);
        $count_inactiveusers = $DB->count_records_sql($sql . $suspendsql, $inactiveparams);
        $count_users = $DB->count_records_sql($sql, $params);

        if ($count_activeusers == 0 || $count_users == 0) {
            $percentage = 0;
        } else {
            $percent = round(($count_activeusers / $count_users) * 100);
            $percent = (int)$percent;
        }

        //local users count content
        $local_users = $PAGE->requires->js_call_amd('local_users/newuser', 'load', array());

        $countinformation = array();

        $displayline = false;
        $hascapablity = false;

        if (has_capability('local/users:create', $categorycontext) || is_siteadmin()) {
            $displayline = true;
            $hascapablity = true;
            $countinformation['create_element'] = html_writer::link(
                'javascript:void(0)',
                get_string('create'),
                array('class' => 'quick_nav_link goto_local_users course_extended_menu_itemlink', 'data-action' =>
                'createusermodal', 'title' => get_string('createuser', 'local_users'), 'data-action' =>
                'createusermodal',  'onclick' => '(function(e){ require
                ("local_users/newuser").init({selector:"createusermodal", context:1, userid:' . $USER->id . ',
                form_status:0}) })(event)')
            );
        }
        $countinformation['node_header_string'] = get_string('manage_br_users', 'local_users');
        $countinformation['pluginname'] = 'users';
        $countinformation['plugin_icon_class'] = 'fa fa-user-plus';
        $countinformation['contextid'] = $categorycontext->id;
        $countinformation['userid'] = $USER->id;
        $countinformation['create'] = $hascapablity;
        $countinformation['viewlink_url'] = $CFG->wwwroot . '/local/users/index.php';
        $countinformation['view'] = true;
        $countinformation['displaystats'] = true;
        // $countinformation['percentage'] = $percent;
        $countinformation['count_total'] = $count_users;
        $countinformation['count_inactive'] = $count_inactiveusers;
        $countinformation['inactive_string'] = get_string('inactive_string', 'block_quick_navigation');
        $countinformation['count_active'] = $count_activeusers;
        if ($count_activeusers >= 0) {
            $countinformation['count_activelink_url'] = $CFG->wwwroot . '/local/users/index.php?status=active';
        }
        if ($count_inactiveusers >= 0) {
            $countinformation['count_inactivelink_url'] = $CFG->wwwroot . '/local/users/index.php?status=inactive';
        }
        $countinformation['space_count'] = 'two';
        $countinformation['view_type'] = $PAGE->theme->settings->quicknavigationview;
        $local_users .= $OUTPUT->render_from_template('block_quick_navigation/quicklink_node', $countinformation);
    }
    return array('1' => $local_users);
}

/*
* Author Sarath
* return count of users under selected costcenter
* @return  [type] int count of users
*/
function costcenterwise_users_count($costcenter, $department = false, $subdepartment = false, $l4department = false, $l5department = false) {
    global $USER, $DB, $CFG;
    $params = array();

    $params['costcenterpath'] = '%/' . $costcenter . '/%';
    $countusersql = "SELECT count(u.id) FROM {user} u WHERE concat('/',u.open_path,'/') LIKE :costcenterpath  AND deleted = 0";
    if ($department) {
        $countusersql .= " AND concat('/',u.open_path,'/') LIKE :departmentpath ";
        $params['departmentpath'] = '%/' . $department . '/%';
    }
    if ($subdepartment) {
        $countusersql .= " AND concat('/',u.open_path,'/') LIKE :subdepartmentpath ";
        $params['subdepartmentpath'] = '%/' . $subdepartment . '/%';
    }
    if ($l4department) {
        $countusersql .= " AND concat('/',u.open_path,'/') LIKE :l4departmentpath ";
        $params['l4departmentpath'] = '%/' . $l4department . '/%';
    }
    if ($l5department) {
        $countusersql .= " AND concat('/',u.open_path,'/') LIKE :l5departmentpath ";
        $params['l5departmentpath'] = '%/' . $l5department . '/%';
    }
    $activesql = " AND suspended = 0 ";
    $inactivesql = " AND suspended = 1 ";
    $countusers = $DB->count_records_sql($countusersql, $params);
    $activeusers = $DB->count_records_sql($countusersql . $activesql, $params);
    $inactiveusers = $DB->count_records_sql($countusersql . $inactivesql, $params);
    if ($countusers >= 0) {
        if ($costcenter) {
            $viewlink_url = $CFG->wwwroot . '/local/users/index.php?costcenterid=' . $costcenter;
        }
        if ($department) {
            $viewlink_url = $CFG->wwwroot . '/local/users/index.php?costcenterid=' . $costcenter . '&departmentid=' . $department;
        }
        if ($subdepartment) {
            $viewlink_url = $CFG->wwwroot . '/local/users/index.php?costcenterid=' . $costcenter . '&departmentid=' . $department . '&subdepartmentid=' . $subdepartment;
        }
        if ($l4department) {
            $viewlink_url = $CFG->wwwroot . '/local/users/index.php?costcenterid=' . $costcenter . '&departmentid=' . $department . '&subdepartmentid=' . $subdepartment . '&l4department=' . $l4department;
        }
        if ($l5department) {
            $viewlink_url = $CFG->wwwroot . '/local/users/index.php?costcenterid=' . $costcenter . '&departmentid=' . $department . '&subdepartmentid=' . $subdepartment . '&l4department=' . $l4department . '&l5department=' . $l5department;
        }
    }
    if ($activeusers >= 0) {
        if ($costcenter) {
            $count_activelink_url = $CFG->wwwroot . '/local/users/index.php?status=active&costcenterid=' . $costcenter;
        }
        if ($department) {
            $count_activelink_url = $CFG->wwwroot . '/local/users/index.php?status=active&costcenterid=' . $costcenter . '&departmentid=' . $department;
        }
        if ($subdepartment) {
            $count_activelink_url = $CFG->wwwroot . '/local/users/index.php?status=active&costcenterid=' . $costcenter . '&departmentid=' . $department . '&subdepartmentid=' . $subdepartment;
        }
        if ($l4department) {
            $count_activelink_url = $CFG->wwwroot . '/local/users/index.php?status=active&costcenterid=' . $costcenter . '&departmentid=' . $department . '&subdepartmentid=' . $subdepartment . '&l4department=' . $l4department;
        }
        if ($l5department) {
            $count_activelink_url = $CFG->wwwroot . '/local/users/index.php?status=active&costcenterid=' . $costcenter . '&departmentid=' . $department . '&subdepartmentid=' . $subdepartment . '&l4department=' . $l4department . '&l5department=' . $l5department;
        }
    }
    if ($inactiveusers >= 0) {
        if ($costcenter) {
            $count_inactivelink_url = $CFG->wwwroot . '/local/users/index.php?status=inactive&costcenterid=' . $costcenter;
        }
        if ($department) {
            $count_inactivelink_url = $CFG->wwwroot . '/local/users/index.php?status=inactive&costcenterid=' . $costcenter . '&departmentid=' . $department;
        }
        if ($subdepartment) {
            $count_inactivelink_url = $CFG->wwwroot . '/local/users/index.php?status=inactive&costcenterid=' . $costcenter . '&departmentid=' . $department . '&subdepartmentid=' . $subdepartment;
        }
        if ($l4department) {
            $count_inactivelink_url = $CFG->wwwroot . '/local/users/index.php?status=inactive&costcenterid=' . $costcenter . '&departmentid=' . $department . '&subdepartmentid=' . $subdepartment . '&l4department=' . $l4department;
        }
        if ($l5department) {
            $count_inactivelink_url = $CFG->wwwroot . '/local/users/index.php?status=inactive&costcenterid=' . $costcenter . '&departmentid=' . $department . '&subdepartmentid=' . $subdepartment . '&l4department=' . $l4department . '&l5department=' . $l5department;
        }
    }
    return array(
        'totalusers' => $countusers, 'activeusercount' => $activeusers, 'inactiveusercount' =>
        $inactiveusers, 'viewlink_url' => $viewlink_url, 'count_activelink_url' => $count_activelink_url,
        'count_inactivelink_url' => $count_inactivelink_url
    );
}

/*
* Author Sarath
* return count of users under selected costcenter
* @return  [type] int count of users
*/
function manage_users_count($stable, $filterdata) {
    global $DB, $PAGE, $USER, $CFG, $OUTPUT;

    $categorycontext = (new \local_users\lib\accesslib())::get_module_context();

    $costcenterpathconcatsql = (new \local_users\lib\accesslib())::get_costcenter_path_field_concatsql($columnname = 'u.open_path');

    $statustype = $stable->status;
    $totalcostcentercount = $stable->costcenterid;
    $totaldepartmentcount = $stable->departmentid;
    $totalsubdepartmentcount = $stable->subdepartmentid;
    $countsql = "SELECT  count(u.id) ";
    $selectsql = "SELECT  u.*  ";
    $formsql = " FROM {user} AS u
         WHERE u.id > 2 AND u.deleted = 0 ";
    $params = array();
    if (is_siteadmin()) {
        $formsql .= "";
    } else {
        $formsql .= " AND u.id != :userid " . $costcenterpathconcatsql;
        $params['userid'] = $USER->id;
    }
    if (isset($filterdata->search_query) && trim($filterdata->search_query) != '') {
        $formsql .= " AND (u.username LIKE :search1 OR concat(u.firstname,' ',u.lastname)
         LIKE :search2 OR u.email LIKE :search3 OR u.open_employeeid LIKE :search4 )";
        $params['search1'] = '%' . trim($filterdata->search_query) . '%';
        $params['search2'] = '%' . trim($filterdata->search_query) . '%';
        $params['search3'] = '%' . trim($filterdata->search_query) . '%';
        $params['search4'] = '%' . trim($filterdata->search_query) . '%';
    }
    if (!empty($filterdata->idnumber)) {
        $idnumbers = explode(',', $filterdata->idnumber);
        list($relatedidnumbersql, $relatedidnumberparams) = $DB->get_in_or_equal($idnumbers, SQL_PARAMS_NAMED, 'idnumber');
        $params = array_merge($params, $relatedidnumberparams);
        $formsql .= " AND u.id $relatedidnumbersql";
    }

    if (!empty($filterdata->email)) {
        $emails = explode(',', $filterdata->email);
        list($relatedemailsql, $relatedemailparams) = $DB->get_in_or_equal($emails, SQL_PARAMS_NAMED, 'email');
        $params = array_merge($params, $relatedemailparams);
        $formsql .= " AND u.id $relatedemailsql";
    }
    // var_dump($filterdata);exit;
    if (!empty($filterdata->filteropen_costcenterid)) {
        $organizations = explode(',', $filterdata->filteropen_costcenterid);
        $orgsql = [];
        foreach ($organizations as $organisation) {
            $orgsql[] = " concat('/',u.open_path,'/') LIKE :organisationparam_{$organisation}";
            $params["organisationparam_{$organisation}"] = '%/' . $organisation . '/%';
        }
        if (!empty($orgsql)) {
            $formsql .= " AND ( " . implode(' OR ', $orgsql) . " ) ";
        }
    }
    if (!empty($filterdata->filteropen_department)) {
        $departments = explode(',', $filterdata->filteropen_department);
        // list($relatededepartmentssql, $relateddepartmentsparams) = $DB->get_in_or_equal($departments,
        //  SQL_PARAMS_NAMED, 'departments');
        // $params = array_merge($params, $relateddepartmentsparams);
        // $formsql .= " AND u.open_departmentid $relatededepartmentssql";
        $deptsql = [];
        foreach ($departments as $department) {
            $deptsql[] = " concat('/',u.open_path,'/') LIKE :departmentparam_{$department}";
            $params["departmentparam_{$department}"] = '%/' . $department . '/%';
        }
        if (!empty($deptsql)) {
            $formsql .= " AND ( " . implode(' OR ', $deptsql) . " ) ";
        }
    }
    if (!empty($filterdata->filteropen_subdepartment)) {
        $subdepartments = explode(',', $filterdata->filteropen_subdepartment);
        // list($relatedesubdepartmentsql, $relatedsubdepartmentparams) = $DB->get_in_or_equal($subdepartment,
        //  SQL_PARAMS_NAMED, 'subdepartment');
        // $params = array_merge($params, $relatedsubdepartmentparams);
        // $formsql .= " AND u.open_subdepartment $relatedesubdepartmentsql";
        $subdeptsql = [];
        foreach ($subdepartments as $subdepartment) {
            $subdeptsql[] = " concat('/',u.open_path,'/') LIKE :subdepartmentparam_{$subdepartment}";
            $params["subdepartmentparam_{$subdepartment}"] = '%/' . $subdepartment . '/%';
        }
        if (!empty($subdeptsql)) {
            $formsql .= " AND ( " . implode(' OR ', $subdeptsql) . " ) ";
        }
    }
    if (!empty($filterdata->filteropen_level4department)) {
        $depart4level = explode(',', $filterdata->filteropen_level4department);
        $department4levelsql = [];
        foreach ($depart4level as $department4level) {
            $department4levelsql[] = " concat('/',u.open_path,'/') LIKE :department4levelparam_{$department4level}";
            $params["department4levelparam_{$department4level}"] = '%/' . $department4level . '/%';
        }
        if (!empty($department4levelsql)) {
            $formsql .= " AND ( " . implode(' OR ', $department4levelsql) . " ) ";
        }
    }
    if (!empty($filterdata->filteropen_level5department)) {
        $depart5level = explode(',', $filterdata->filteropen_level5department);
        $department5levelsql = [];
        foreach ($depart5level as $department5level) {
            $department5levelsql[] = " concat('/',u.open_path,'/') LIKE :department5levelparam_{$department5level}";
            $params["department5levelparam_{$department5level}"] = '%/' . $department5level . '/%';
        }
        if (!empty($department5levelsql)) {
            $formsql .= " AND ( " . implode(' OR ', $department5levelsql) . " ) ";
        }
    }
    if (!empty($filterdata->location)) {
        $locations = explode(',', $filterdata->location);
        list($locationsql, $locationparams) = $DB->get_in_or_equal($locations, SQL_PARAMS_NAMED, 'location');
        $params = array_merge($params, $locationparams);
        $formsql .= " AND u.open_location {$locationsql} ";
    }

    if (!empty($filterdata->hrmsrole)) {
        $hrmsroles = explode(',', $filterdata->hrmsrole);
        list($hrmsrolesql, $hrmsroleparams) = $DB->get_in_or_equal($hrmsroles, SQL_PARAMS_NAMED, 'hrmsrole');
        $params = array_merge($params, $hrmsroleparams);
        $formsql .= " AND u.open_hrmsrole {$hrmsrolesql} ";
    }

    if (!empty($filterdata->roles)) {
        $query = "SELECT u.id
            FROM {user} AS u
            JOIN {role_assignments} ra ON ra.userid = u.id
            JOIN {context} c ON c.id = ra.contextid AND c.contextlevel = 40
           WHERE ra.roleid IN ({$filterdata->roles}) ";
        $roleusers = $DB->get_records_sql_menu($query);
        if (!empty($roleusers)) {
            $userslist = implode(',', array_keys($roleusers)); // Note: array_keys to get the user IDs
            $formsql .= " AND u.id IN ($userslist) ";
        } else {
            $formsql .= " AND u.id IN (NULL) ";
        }
    }
    
    if (!empty($filterdata->project)) {
        $projects = explode(',', $filterdata->project);
        list($projectssql, $projectsparam) = $DB->get_in_or_equal($projects, SQL_PARAMS_NAMED, 'project');
        $params = array_merge($params, $projectsparam);
        $formsql .= " AND u.open_project {$projectssql} ";
    }

    if (!empty($filterdata->village)) {
        $villages = explode(',', $filterdata->village);
        list($villagesql, $villageparam) = $DB->get_in_or_equal($villages, SQL_PARAMS_NAMED, 'village');
        $params = array_merge($params, $villageparam);
        $formsql .= " AND u.open_village {$villagesql} ";
    }
    if (!empty($filterdata->subdistrict)) {
        $subdistricts = explode(',', $filterdata->subdistrict);
        list($subdistrictsql, $subdistrictparam) = $DB->get_in_or_equal($subdistricts, SQL_PARAMS_NAMED, 'subdistrict');
        $params = array_merge($params, $subdistrictparam);
        $formsql .= " AND u.open_subdistrict {$subdistrictsql} ";
    }
    if (!empty($filterdata->district)) {
        $districts = explode(',', $filterdata->district);
        list($districtsql, $districtparam) = $DB->get_in_or_equal($districts, SQL_PARAMS_NAMED, 'district');
        $params = array_merge($params, $districtparam);
        $formsql .= " AND u.open_district {$districtsql} ";
    }
    if (!empty($filterdata->states)) {
        $state = explode(',', $filterdata->states);
        list($statessql, $statesparam) = $DB->get_in_or_equal($state, SQL_PARAMS_NAMED, 'states');
        $params = array_merge($params, $statesparam);
        $formsql .= " AND u.open_states {$statessql} ";
    }
    if (!empty($filterdata->status)) {
        $status = explode(',', $filterdata->status);
        if (!(in_array('active', $status) && in_array('inactive', $status))) {
            if (in_array('active', $status)) {
                $formsql .= " AND u.suspended = 0";
            } else if (in_array('inactive', $status)) {
                $formsql .= " AND u.suspended = 1";
            }
        }
    }
    $ordersql = " ORDER BY u.id DESC ";
    $totalusers = $DB->count_records_sql($countsql . $formsql/*.$ordersql*/, $params);

    $activesql = " AND u.suspended = :suspended ";
    $params['suspended'] = 0;
    $activeusers = $DB->count_records_sql($countsql . $formsql . $activesql/*.$ordersql*/, $params);

    $params['suspended'] = 1;
    $inactiveusers = $DB->count_records_sql($countsql . $formsql . $activesql/*.$ordersql*/, $params);
    $users = $DB->get_records_sql($selectsql . $formsql . $ordersql, $params, $stable->start, $stable->length);
    return array(
        'totalusers' => $totalusers, 'activeusercount' => $activeusers,
        'inactiveusercount' => $inactiveusers, 'users' => $users
    );
}

/*
* Author Sarath
* return count of users under selected costcenter
* @return  [type] int count of users
*/
function manage_users_content($stable, $users/*,$filterdata*/) {
    global $DB, $PAGE, $USER, $CFG, $OUTPUT;
    $categorycontext = (new \local_users\lib\accesslib())::get_module_context();
    $userslist = $users['users'];
    $data = array();
    foreach ($userslist as $user) {

        $list = array();
        $line = array();
        $user_picture = new user_picture($user, array('size' => 60, 'class' => 'userpic', 'link' => false));
        $user_picture = $user_picture->get_url($PAGE);
        $userpic = $user_picture->out();
        $list['userpic'] = $userpic;
        $list['username'] = $user->username;
        $list['empid'] = ($user->open_employeeid) ? $user->open_employeeid : '--';
        $useremail = $user->email;
        if (strlen($useremail) > 24) {
            $useremail = substr($useremail, 0, 24) . '...';
        }
        $list['email'] = !empty($useremail) ? $useremail : 'N/A';
        $organisationdata = array_filter(explode('/', $user->open_path));
        $organisationnames = array_map(function ($orgid) {
            return \local_costcenter\lib\accesslib::get_costcenter_info($orgid, 'fullname');
        }, $organisationdata);
        $organization = $organisationnames[1];
        $dept = $organisationnames[2];
        if (!$dept) {
            $dept = 'All';
        }
        $commercialunit = $organisationnames[3];
        if (!$commercialunit) {
            $commercialunit = 'All';
        }
        $commercialarea = $organisationnames[4];
        if (!$commercialarea) {
            $commercialarea = 'All';
        }
        if(is_siteadmin()) {
            $orgstring = strlen($organization) > 24 ? substr($organization, 0, 24) . "..." : $organization;
            $list['org'] = $organization;
            $list['orgstring'] = $orgstring; 
        }
        
        $deptstring = strlen($dept) > 24 ? substr($dept, 0, 24) . "..." : $dept;
        $designation = $user->open_designation;
        $designationstring = strlen($user->open_designation) > 14 ? substr($user->open_designation, 0, 14) .
            "..." : $user->open_designation;

        $list['deptstring'] = $deptstring;
        $list['dept'] = $dept;
        $list['commercialunit'] = $commercialunit;
        $list['commercialarea'] = $commercialarea;
        if ($user->gender == 0) {
            $list['gender'] = 'Male';
        } else if ($user->gender == 1) {
            $list['gender'] = 'Female';
        } else if ($user->gender == 2) {
            $list['gender'] = 'Other';
        }

        if ($user->open_prefix == 1) {
            $list['prefix'] = 'Mr';
        } else if ($user->open_prefix == 2) {
            $list['prefix'] = 'Mrs';
        } else if ($user->open_prefix == 3) {
            $list['prefix'] = 'Ms';
        }

        $list['employmenttype'] = $user->open_employmenttype ? $user->open_employmenttype : '--';
        $list['region'] = $user->open_region ? $user->open_region : '--';
        $list['grade'] = $user->open_grade ? $user->open_grade : '--';
        $list['phno'] = ($user->phone1) ? $user->phone1 : '--';
        $list['designation'] = $designation;
        $list['dateofbirth'] = $user->open_dateofbirth ? date('d-M-Y', $user->open_dateofbirth) : '';
        $list['dateofjoining'] = $user->open_joindate ? date('d-M-Y', $user->open_joindate) : '';
        $rolecount = $DB->get_record_sql("SELECT COUNT(ra.id) AS role
            FROM {role_assignments} AS ra
            JOIN {context} AS c ON c.id = ra.contextid AND c.contextlevel = 40
            WHERE userid =" . $user->id);
        if ($rolecount->role > 0) {
            $list['rolecount'] = $rolecount->role;
        } else {
            $list['rolecount'] = false;
        }

        $rolename = $DB->get_field_sql(
            "SELECT r.name
            FROM {role} r
            JOIN {role_assignments} ra ON r.id = ra.roleid
            JOIN {context} c ON c.id = ra.contextid AND c.contextlevel = 40
           WHERE userid = " . $user->id
        );
        if ($rolename) {
            $list['rolename'] = $rolename;
        } else {
            $list['rolename'] = false;
        }
        $list['designationstring'] = ($designationstring) ? $designationstring : '--';
        if (!empty($user->open_supervisorid)) {
            $supervisior = $DB->get_field_sql("SELECT CONCAT(firstname,' ',lastname) AS fullname
                 FROM {user} WHERE id = :supervisiorid", array('supervisiorid' => $user->open_supervisorid));
            $supervisiorstring = strlen($supervisior) > 12 ? substr($supervisior, 0, 12) . "..." : $supervisior;
            $list['supervisor'] = $supervisior;
            $list['supervisiorstring'] = $supervisiorstring;
        } else {
            $list['supervisiorstring'] = '--';
        }
        $list['lastaccess'] = ($user->lastaccess) ? format_time(time() - $user->lastaccess) : get_string('never');
        $list['userid'] = $user->id;
        $list['fullname'] = $list['prefix'] ? $list['prefix'] . '. ' . fullname($user) : fullname($user);
        if (has_capability('local/users:manage', (new \local_users\lib\accesslib())::get_module_context()) || is_siteadmin()) {
            $list['visible'] = $user->suspended;
            $list['activeicon'] = 1;
        } else {
            $list['activeicon'] = 0;
        }
        if (is_siteadmin() || has_capability('local/users:edit', (new \local_users\lib\accesslib())::get_module_context())) {
            $list['editcap'] = 1;
        } else {
            $list['editcap'] = 0;
        }
        if (is_siteadmin() || has_capability('local/users:delete', (new \local_users\lib\accesslib())::get_module_context())) {
            $list['delcap'] = 1;
        } else {
            $list['delcap'] = 0;
        }
        $data[] = $list;
    }
    return $data;
}

/*
* Author Sarath
* return filterform
*/
function users_filters_form($filterparams, $formdata = []) {
    global $CFG, $USER;

    require_once($CFG->dirroot . '/local/courses/filters_form.php');

    $categorycontext = (new \local_users\lib\accesslib())::get_module_context();
    if (is_siteadmin()) {
        $mform = new filters_form(null, array(
            'filterlist' => array('hierarchy_fields',/*'geographyfields',*/ 'email', 'employeeid', 'status', 'roles', 'designation'), 'courseid' => 1,
            'enrolid' => 0, 'plugins' => array('users', 'costcenter'), 'filterparams' => $filterparams
        ) + $formdata);
    } else {
        $filters = array('hierarchy_fields',/* 'geographyfields',*/ 'email', 'employeeid', 'status', 'roles', 'designation');

        $mform = new filters_form(null, array('filterlist' => $filters, 'courseid' => 1, 'enrolid' => 0, 'plugins' => array('users', 'costcenter'), 'filterparams'
        => $filterparams) + $formdata);
    }
    return $mform;
}

/*
* Author Sarath
* return count  of sync errors
* @return  [type] int count of sync errors
*/
function manage_syncerrors_count($stable, $filterdata) {
    global $DB, $USER;

    $categorycontext = (new \local_users\lib\accesslib())::get_module_context();
    $params = array();
    $countsql = " SELECT count(id) ";
    $selectsql = "SELECT * ";
    $fromsql = " FROM {local_syncerrors} ls where 1=1 ";
    if (is_siteadmin()) {
        $fromsql .= " ";
    } else {
        $fromsql .= " AND (modified_by = :modified_by OR open_path = :open_path)";
        $params['modified_by'] = $USER->id;
        $params['open_path'] = $USER->open_path;
    }
    $count = $DB->count_records_sql($countsql . $fromsql, $params);
    $fromsql .= " ORDER BY id DESC ";

    $syncerrors = $DB->get_records_sql($selectsql . $fromsql, $params, $stable->start, $stable->length);

    return array('count' => $count, 'syncerrors' => $syncerrors);
}

/*
* Author Sarath
* return data of sync errors
* @return  [type] char data of sync errors
*/
function manage_syncerrors_content($stable, $filterdata) {
    global $DB;
    $data = array();
    $totalerrors = manage_syncerrors_count($stable, $filterdata);
    $syncerrors = $totalerrors['syncerrors'];
    foreach ($syncerrors as $syncerror) {
        $list = array();
        $list['idnumber'] = $syncerror->idnumber ? $syncerror->idnumber : '-';
        $list['email'] = $syncerror->email ? $syncerror->email : '-';
        $str = $syncerror->mandatory_fields;
        $exp = explode(',', $str);
        $exp = implode('<br><br>', $exp);
        $list['mandatoryfields'] = $exp;
        $err = $syncerror->error;
        $exp1 = explode(',', $err);
        $expe = implode('<br><br>', $exp1);
        $list['errors'] = $expe;
        $date = $syncerror->date_created;
        $list['modifiedby'] = fullname($DB->get_record('user', array('id' => $syncerror->modified_by)));;
        $list['createddate'] = \local_costcenter\lib::get_userdate("d/m/Y H:i", $date);

        $data[] = $list;
    }
    return $data;
}

/*
* Author Sarath
* return count  of sync statistics
* @return  [type] int count of sync statistics
*/
function manage_syncstatistics_count($stable, $filterdata) {
    global $DB, $USER;
    $categorycontext = (new \local_users\lib\accesslib())::get_module_context();
    $params = array();
    $countsql = " SELECT count(id) ";
    $selectsql = "SELECT * ";
    $fromsql = " FROM {local_userssyncdata} ls where 1=1";
    if (is_siteadmin() || has_capability('local/costcenter:manage_multiorganizations', $categorycontext)) {
        $fromsql .= " ";
    } else {
        // $fromsql .= " AND usercreated = :modifiedby ";
        // $params['modifiedby'] = $USER->id;
        $fromsql .= " AND costcenterid = :orgid ";
        $orgpath = explode('/', $USER->open_path);
        $params['orgid'] = $orgpath[1];
    }

    $count = $DB->count_records_sql($countsql . $fromsql, $params);
    $fromsql .= " ORDER BY id DESC";

    $syncstatstics = $DB->get_records_sql($selectsql . $fromsql, $params, $stable->start, $stable->length);
    return array('count' => $count, 'syncstatstics' => $syncstatstics);
}

/*
* Author Sarath
* return data of sync statistics
* @return  [type] char data of sync statistics
*/
function manage_syncstatistics_content($stable, $filterdata) {
    global $DB;
    $data = array();
    $totalerrorsstatstics = manage_syncstatistics_count($stable, $filterdata);
    $syncstatstics = $totalerrorsstatstics['syncstatstics'];
    foreach ($syncstatstics as $syncstatstic) {
        $list = array();
        $list['newuserscount'] = $syncstatstic->newuserscount;
        $list['updateduserscount'] = $syncstatstic->updateduserscount;
        $list['errorscount'] = $syncstatstic->errorscount;
        $list['warningscount'] = $syncstatstic->warningscount;
        $list['supervisorwarningscount'] = $syncstatstic->supervisorwarningscount;
        $usercreated = $DB->get_record('user', array('id' => $syncstatstic->usercreated));
        $list['usercreated'] = $usercreated->firstname . ' ' . $usercreated->lastname;
        $list['createddate'] = \local_costcenter\lib::get_userdate("d/m/Y", $syncstatstic->timecreated);
        $list['modifieddate'] = \local_costcenter\lib::get_userdate("d/m/Y", $syncstatstic->timemodified);
        $list['checkbox'] = $syncstatstic->id;
        $data[] = $list;
    }
    return $data;
}

/*
* Author sarath
* @return true for reports under category
*/
function learnerscript_users_list() {
    return get_string('users', 'local_users');
}

function send_logins_user($user) {
    // removal of code if triggered by any chance. should never happen..
    // global $DB, $CFG;
    // $from_user = $DB->get_record('user', ['id'=>2]);
    // $subject = get_string('logininfo', 'local_users');
    // $user->siteurl = $CFG->wwwroot;
    // $body = $emailbody = get_string('logininfobody', 'local_users', $user);
    // email_to_user($user, $from_user, $subject, $body, $emailbody);
}
function local_users_before_http_headers() {
    global $PAGE, $CFG;
    require_once($CFG->libdir . '/accesslib.php');
    if (!is_siteadmin()) {
        $PAGE->add_body_class('usersclass');
    }
}

//masterdata view capabilities checking here by narendra
function masterdata_capabilities($active) {
    $categorycontext = (new \local_users\lib\accesslib())::get_module_context();
    $viewstates = false;
    $viewdistrict = false;
    $viewsubdistrict = false;
    $viewvillage = false;

    if (is_siteadmin() || has_capability('usersprofilefields/states:view', $categorycontext)) {
        $viewstates = true;
    }
    if (is_siteadmin() || has_capability('usersprofilefields/district:view', $categorycontext)) {
        $viewdistrict = true;
    }
    if (is_siteadmin() || has_capability('usersprofilefields/subdistrict:view', $categorycontext)) {
        $viewsubdistrict = true;
    }
    if (is_siteadmin() || has_capability('usersprofilefields/village:view', $categorycontext)) {
        $viewvillage = true;
    }

    $navbar = array(
        $active . 'active' => true,
        'viewstates' => $viewstates,
        'viewdistrict' => $viewdistrict,
        'viewsubdistrict' => $viewsubdistrict,
        'viewvillage' => $viewvillage,
    );
    return $navbar;
}

function local_users_output_fragment_user_field_create($args) {
    global $CFG, $DB, $PAGE;
    $args = (object) $args;
    $o = '';
    $context = $args->context;
    $formType = $args->form_type;
    $formClass = "local_users\\forms\\create_$formType";
    $formdata = [];
    if (!empty($args->jsonformdata)) {
        $serialiseddata = json_decode($args->jsonformdata);
        parse_str($serialiseddata, $formdata);
    }
    if ($args->id > 0) {
        $tableData = $DB->get_record($args->tablename, array('id' => $args->id));
        $mform = new $formClass(null, (array)$tableData, 'post', '', null, true, $formdata);
        $mform->set_data($tableData);
    } else {
        $mform = new $formClass(null, array(), 'post', '', null, true, $formdata);
    }
    if (!empty($args->jsonformdata) && strlen($args->jsonformdata) > 2) {
        // If we were passed non-empty form data we want the mform to call validation functions and show errors.
        $mform->is_validated();
    }
    ob_start();
    $mform->display();
    $o .= ob_get_contents();
    ob_end_clean();
    return $o;
}
//global user profile form fields
function local_users_get_userprofile_fields($mform, $ajaxformdata, $customdata, $allenable = false, $pluginname, $context, $multiple = false, $prefix = '') {
    global $DB, $USER;

    $fields = (new \local_users\lib\accesslib())::get_userprofile_fields();

    $costcenterfields = local_costcenter_get_fields();

    $firstdepth = current($costcenterfields);

    $lastdepth = end($costcenterfields);

    if ($pluginname != 'local_users' && $prefix != 'filter') {

        $functionname = 'globaltargetaudience_elementlist';

        if (function_exists($functionname)) {

            $mform->modulecostcenterpath = $customdata[$firstdepth];

            $functionname($mform, array('group', 'designation'));
        }
    }

    $prev_element = $lastdepth . '_select';
    $firstelement = true;

    $prevfield = 'costcenter';

    $depth = 0;

    foreach ($fields as $field) {
        $tablename = $DB->get_prefix() . str_replace("open", "local", $field);
        $fieldname = str_replace("open_", "", $field) . '_name';
        $actfield = $field;
        $field = $prefix . $field;
        if ($depth == 0) {
            if ($prefix == 'filter') {
                $prev_element = 'filteropen_costcenterid_select';
            } else {
                $prev_element = 'locationfieldparentid_select';

                $mform->addElement('hidden', 'locationfieldparentid', null, array('data-class' => $prev_element));
                $mform->setConstant('locationfieldparentid', $customdata[$firstdepth]);
            }
        }
        $fieldelementoptions = array(
            'class' => $field . '_select custom_form_field',
            'id' => 'id_' . $field . '_select',
            'data-parentclass' => $prev_element,
            'data-selectstring' => get_string('select' . $actfield, 'local_users'),
            'placeholder' => get_string('select' . $actfield, 'local_users'),
            'data-depth' => $depth,
            'data-class' => $field . '_select',
            'onchange' => '(function(e){ require("local_users/newuser").changeElement(event) })(event)',

        );
        $prev_element = $field . '_select';

        $fieldvalue = $ajaxformdata[$field] ? $ajaxformdata[$field] : $customdata[$field];

        $fieldelementoptions['multiple'] = $multiple;
        $fieldelementoptions['ajax'] = 'local_users/form-options-selector';
        $fieldelementoptions['data-contextid'] = $context->id;
        $fieldelementoptions['data-action'] = 'userprofile_element_selector';
        $parentid = $ajaxformdata[$prevfield] ? $ajaxformdata[$prevfield] : $customdata[$prevfield];
        $fieldelementoptions['data-options'] = json_encode(array('depth' => $depth, 'columnname' => $field, 'parentid' => $parentid, 'parentidcolumn' => $prevfield, 'enableallfield' => $allenable));

        $fieldelements = [];
        if ($allenable) {
            $fieldelements = [0 => get_string('all')];
        } else {
            $fieldelements = [];
        }
        if ($fieldvalue) {
            $fieldelementids = is_array($fieldvalue) ? $fieldvalue : explode(',', $fieldvalue);
            $fieldelementids = array_filter($fieldelementids);

            if ($fieldelementids) {

                list($idsql, $idparams) = $DB->get_in_or_equal($fieldelementids, SQL_PARAMS_QM, 'targetaudienceelements');

                $fieldsql = "SELECT id, $fieldname as fullname FROM {$tablename} WHERE id {$idsql} ";
                $fieldelements = $DB->get_records_sql_menu($fieldsql, $idparams);
            }
        }
        $mform->addElement('autocomplete', $field, get_string($field, 'local_users'), $fieldelements, $fieldelementoptions);
        $mform->addHelpButton($field, $field, $pluginname);

        $firstelement = false;

        $mform->setType($field, PARAM_RAW);
        $prevfield = $field;
        $depth++;
    }
}
function local_users_get_userprofile_datafields(&$data) {

    $fields = (new \local_users\lib\accesslib())::get_userprofile_fields();

    foreach ($fields as $field) {

        if (isset($data->$field) && !empty($data->$field)) {

            $data->$field = is_array($data->$field) ? implode(',', $data->$field) : $data->$field;
        }
    }
}
function local_users_set_userprofile_datafields(&$customdata, $data) {

    $fields = (new \local_users\lib\accesslib())::get_userprofile_fields();
    foreach ($fields as $field) {

        if (isset($data->$field) && !empty($data->$field)) {

            $customdata[$field] = $data->$field;
        }
    }
}
function local_users_output_fragment_userrole_display($args) {
    global $DB, $CFG, $PAGE, $OUTPUT, $USER;

    $args = (object) $args;
    $context = $args->context;
    $userid = $args->id;

    $sql = "SELECT ra.id,r.name,ra.timemodified,cc.name as costcenter,cc.depth, ra.contextid,ra.roleid
        FROM mdl_role_assignments AS ra
        JOIN mdl_context AS c ON c.id = ra.contextid AND c.contextlevel = 40
        JOIN mdl_course_categories AS cc ON cc.id = c.instanceid
        JOIN mdl_role AS r ON r.id = ra.roleid
        WHERE ra.userid =:userid";
    $roles = $DB->get_records_sql($sql, array('userid' => $userid));

    $templatedata = array();
    if ($roles) {
        $templatedata['enabletable'] = true;
        foreach ($roles as $role) {
            $rowdata = array();
            $rowdata['userid'] = $userid;
            $rowdata['ctxid'] = $role->contextid;
            $rowdata['id'] = $role->id;
            $rowdata['roleid'] = $role->roleid;
            $rowdata['role'] = $role->name;
            $rowdata['timeassign'] = date("d M Y", $role->timemodified);
            $rowdata['costcenter'] = $role->costcenter;
            if ($role->depth == 1) {
                $rowdata['depth'] = get_string('open_costcenterid', 'local_costcenter');
            } elseif ($role->depth == 2) {
                $rowdata['depth'] = get_string('open_department', 'local_costcenter');
            } elseif ($role->depth == 3) {
                $rowdata['depth'] = get_string('open_subdepartment', 'local_costcenter');
            } elseif ($role->depth == 4) {
                $rowdata['depth'] = get_string('open_level4department', 'local_costcenter');
            } elseif ($role->depth == 5) {
                $rowdata['depth'] = get_string('subsubsubdepartment', 'local_costcenter');
            }
            $templatedata['rowdata'][] = $rowdata;
        }
    } else {
        $templatedata['enabletable'] = false;
    }
    $output = $OUTPUT->render_from_template('local_users/roledetails', $templatedata);
    return $output;
}

/****************************************User profile fields as target audience for modules ************************************** */

/**
 * Print out the customisable categories and fields for a users profile
 *
 * @param MoodleQuickForm $mform instance of the moodleform class
 * @param int $userid id of user whose profile is being edited or 0 for the new user
 */
function local_users_get_custom_userprofilefields(MoodleQuickForm $mform, $module, $table = 'course'): void {
    global $DB;

    $customcategoryid = get_modulecustomfield_category($module, $table);
    if ($customcategoryid) {
        $fields = get_user_targetaudience_fields($customcategoryid);
        foreach ($fields as $key => $field) {
            //if($DB->get_field('user_info_field','targetaudience',array('categoryid' => $customcategoryid, 'id'=>$field->id))){
            $fielddata = array();
            $sql = "SELECT distinct(data)  FROM {user_info_data} WHERE fieldid = :fieldid AND data <> ''";
            $fielddata = $DB->get_records_sql($sql, ['fieldid' => $field->id]);

            $options = array();
            foreach ($fielddata as $key => $val) {
                if ($field->datatype == 'autocomplete') {
                    $values = explode(',', $val->data);
                    foreach ($values as $v) {
                        $v = trim($v);
                        $options[$v] = $v;
                    }
                } else {
                    $options[$key] = $val->data;
                }
                /* if ($field->datatype == 'checkbox') {
                        $options[$key] = ($val->data) ? get_string('yes') : get_string('no');
                    } */
            }

            $select = $mform->addElement('autocomplete',  $field->shortname, $field->name, $options);
            $mform->setType($field->shortname, PARAM_INT);
            $select->setMultiple(true);
            $fieldvalue = $DB->get_field_sql("SELECT fieldvalue FROM {local_module_targetaudience} WHERE moduleid = :moduleid AND fieldid = :fieldid AND module = :module", ['moduleid' => $module->id, 'fieldid' => $field->id, 'module' => $module->module]);

            /*   if(is_null($fieldvalue)){
                $fieldvalue = 'All';
                } */
            if ($fieldvalue) {
                $mform->setDefault($field->shortname, $fieldvalue);
            }

            //}
        }
    }
}

function get_user_fields_with_data_by_coursecategory(int $customcategoryid): array {
    global $DB;

    $sql = 'SELECT uif.*, uic.name AS categoryname ';
    $sql .= 'FROM {user_info_field} uif ';
    $sql .= 'LEFT JOIN {user_info_category} uic ON uif.categoryid = uic.id ';
    $sql .= "WHERE uif.categoryid = :customcategoryid ";
    $sql .= 'ORDER BY uic.sortorder ASC, uif.sortorder ASC ';
    $fields = $DB->get_records_sql($sql, ['customcategoryid' => $customcategoryid]);

    return $fields;
}

function get_user_targetaudience_fields(int $customcategoryid): array {
    global $DB;

    $sql = 'SELECT uif.*, uic.name AS categoryname ';
    $sql .= 'FROM {user_info_field} uif ';
    $sql .= 'LEFT JOIN {user_info_category} uic ON uif.categoryid = uic.id ';
    $sql .= "WHERE uif.categoryid = :customcategoryid AND uif.targetaudience = :flag";
    $sql .= 'ORDER BY uic.sortorder ASC, uif.sortorder ASC ';
    $fields = $DB->get_records_sql($sql, ['customcategoryid' => $customcategoryid, 'flag' => 1]);

    return $fields;
}

function get_usercustomfield_category_bycostcenter(int $userid, int $form_status = 0, $costcenterid) {
    global $DB;
    $customsql = "SELECT lc.profileinfocategory FROM {local_costcenter} AS lc WHERE lc.depth = 1  AND lc.id = :id ";
    $customparams = ['id' => $costcenterid];
    $categoryid = $DB->get_field_sql($customsql, $customparams);

    $sql = 'SELECT uif.id,uif.*, uic.name AS categoryname ';
    if ($userid > 0) {
        $sql .= ', uind.id AS hasuserdata, uind.data, uind.dataformat ';
    }
    $sql .= 'FROM {user_info_field} uif ';
    $sql .= 'LEFT JOIN {user_info_category} uic ON uif.categoryid = uic.id ';
    if ($userid > 0) {
        $sql .= ' LEFT JOIN {user_info_data} uind ON uif.id = uind.fieldid AND uind.userid = :userid ';
    }

    $sql .= " WHERE uif.categoryid = :infocatid  ";

    $sql .= 'ORDER BY uic.sortorder ASC, uif.sortorder ASC ';
    $fields = $DB->get_records_sql($sql, ['infocatid' => $categoryid, 'userid' => $userid]);
    $data = [];
    foreach ($fields as $field) {
        $field->hasuserdata = !empty($field->hasuserdata);
        $fieldobject = profile_get_user_field($field->datatype, $field->id, 0, $field);
        $fieldobject->set_category_name($field->categoryname);
        unset($field->categoryname);
        $data[] = $fieldobject;
    }

    return $data;
}

function get_modulecustomfield_category($module, $table = 'course') {
    global $DB, $CFG;
    $table = $CFG->prefix . $table;
    $customsql = "SELECT lc.profileinfocategory FROM {local_costcenter} AS lc JOIN $table AS c ON concat('/',c.open_path,'/') LIKE concat('%/',lc.id,'/%') AND lc.depth = 1  WHERE c.id = :moduleid ";
    $customparams = ['moduleid' => $module->id];
    $modulecategoryid = $DB->get_field_sql($customsql, $customparams);
    return $modulecategoryid;
}

function get_usercustomfield_category($user) {
    global $DB;
    $customsql = "SELECT lc.profileinfocategory FROM {local_costcenter} AS lc JOIN {user} AS u ON concat('/',u.open_path,'/') LIKE concat('%/',lc.id,'/%') AND lc.depth = 1  WHERE u.id = :userid ";
    $customparams = ['userid' => $user->id];
    $customcategoryid = $DB->get_field_sql($customsql, $customparams);
    return $customcategoryid;
}

function update_custom_targetaudience($customcategoryid, $data, $module) {
    global $DB, $USER;
    $fields = get_user_targetaudience_fields($customcategoryid);
    $data = (array)$data;
    foreach ($fields as $key => $field) {
        $customdata = new stdclass();
        $fieldname = $field->shortname;
        $fielddata = ($data[$fieldname] === '_qf__force_multiselect_submission' || empty($data[$fieldname])) ? NULL : implode(",", $data[$fieldname]); //"'" . implode ( "', '", $data[$fieldname]) . "'";

        /*  if( $fielddata){
            $fieldvalue = $DB->get_field_sql("SELECT GROUP_CONCAT(data) FROM {user_info_data} WHERE data IN ($fielddata)");
        }else{
            $fieldvalue = NULL;
        }  */

        if (isset($data[$fieldname]) && !empty($data[$fieldname])) {
            $customdata->moduleid = $module->id;
            $customdata->module = $module->module;
            $customdata->fieldid = $field->id;
            $customdata->fieldvalue = $fielddata;

            if ($existid = $DB->get_field_sql('SELECT id from {local_module_targetaudience} WHERE moduleid = ? AND fieldid = ? AND module = ?', array($module->id, $field->id, $module->module))) {
                $customdata->id = $existid;
                $customdata->usermodified = $USER->id;
                $customdata->timemodified = time();
                $DB->update_record('local_module_targetaudience', $customdata);
            } else {
                $customdata->usercreated = $USER->id;
                $customdata->timecreated = time();
                $DB->insert_record('local_module_targetaudience', $customdata);
            }
        }
    }
}

function insert_custom_targetaudience($customcategoryid, $course) {
    global $DB, $USER;
    $fields = get_user_fields_with_data_by_coursecategory($customcategoryid);

    foreach ($fields as $key => $field) {
        $customdata = new stdclass();
        $fieldvalue = NULL;

        $customdata->moduleid = $course->id;
        $customdata->module = $course->module;
        $customdata->fieldid = $field->id;
        $customdata->fieldvalue = $fieldvalue;
        $customdata->usercreated = $USER->id;
        $customdata->timecreated = time();
        $DB->insert_record('local_module_targetaudience', $customdata);
    }
}
// function target_audience_match_field($fields,$prefix,$match_object=NULL){
//     global $DB,$USER;
//     $params=[];
//     foreach($fields as $field){
//         $fieldvalue='';
//         if($match_object){
//         $fieldvalue=$match_object->$field;
//         }else{
//         $fieldvalue=$USER->$field;
//         }
//     if(!empty($fieldvalue) && $fieldvalue != "" && $fieldvalue != NULL){
//         $params[$field] = "%,$fieldvalue,%";
//     }else{
//         $params[$field] = "";
//     }
//     if($fieldvalue != NULL && $fieldvalue != 0){
//     $sqlarray[]= " 1 = CASE WHEN ".$prefix.".".$field." IS NOT NULL AND ".$prefix.".".$field." != '' AND ".$prefix.".".$field." != 0
//                 THEN
//                     CASE
//                         WHEN CONCAT(',',".$prefix.".".$field.",',') LIKE :".$field."
//                             THEN 1
//                             ELSE 0
//                     END
//                 ELSE 1 END  ";

// }
//     }
// $return=   array(
//     'params' => $params,
//     'sqlarray' => ($sqlarray) ? '(' .implode(' AND ', $sqlarray) . ')' : '',
// );
// return $return;
// }

function target_audience_match_field($fields, $prefix, $match_object = null) {
    global $DB, $USER;
    $params = [];
    foreach ($fields as $field) {
        $fieldvalue = '';
        $mul_fieldvals = [];
        $findinsql = array();
        if ($match_object) {
            $fieldvalue = $match_object->$field;
        } else {
            $userinf = $DB->get_record('user', array('id' => $USER->id));
            $fieldvalue = $userinf->$field;
        }
        if (!empty($fieldvalue) && $fieldvalue != "" && $fieldvalue != null) {
            $params[$field] = "%,$fieldvalue,%";
        } else {
            $params[$field] = "";
        }

        if (strpos($fieldvalue, ',') !== false) {
            $mul_fieldvals = [];
            $mul_fieldvals = explode(",", $fieldvalue);
        }
        if ($fieldvalue != null && $fieldvalue != 0) {
            /*  $sqlarray[]= " 1 = CASE WHEN ".$prefix.".".$field." IS NOT NULL AND ".$prefix.".".$field." != '' AND ".$prefix.".".$field." != 0
            THEN
            CASE
            WHEN CONCAT(',',".$prefix.".".$field.",',') LIKE :".$field."
            THEN 1
            ELSE 0
            END
            ELSE 1 END ";  */
            if (count($mul_fieldvals) > 0) {
                $fieldsql = [];
                foreach ($mul_fieldvals as $fval) {
                    if ($field == 'open_designation') {
                        $fieldsql[] = ' FIND_IN_SET("' . $fval . '",' . $prefix . '.' . $field . ') ';
                    } else {
                        $fieldsql[] = ' FIND_IN_SET(' . $fval . ',' . $prefix . '.' . $field . ') ';
                    }
                }
                $findinsql = "  ( " . implode(' OR ', $fieldsql) . ' ) ';
            }
            if (!empty($findinsql)) {
                $sqlarray[] = " 1 = CASE WHEN " . $prefix . "." . $field . " IS NOT NULL OR " . $prefix . "." . $field . " != '' OR " . $prefix . "." . $field . " != 0
                    THEN
                         $findinsql
                    ELSE 0 END ";
            } else {
                $sqlarray[] = " 1 = CASE WHEN " . $prefix . "." . $field . " IS NOT NULL OR " . $prefix . "." . $field . " != '' OR " . $prefix . "." . $field . " != 0
                    THEN
                         CONCAT(','," . $prefix . "." . $field . ",',') LIKE :" . $field . "
                    ELSE 0 END ";
            }
        }
    }
    $return = array(
        'params' => $params,
        'sqlarray' => ($sqlarray) ? '(' . implode(' AND ', $sqlarray) . ')' : '',
    );
    return $return;
}
/**
 * Description: User employeeid filter code
 * @param  [mform object]  $mform          [the form object where the form is initiated]
 * @param  string  $query          [text inserted in filter]
 * @param  boolean $searchanywhere [description]
 * @param  integer $page           [page value]
 * @param  integer $perpage        [entities per page]
 */
function employmenttype_filter($mform, $query = '', $searchanywhere = false, $page = 0, $perpage = 25) {
    global $DB, $USER;
    $employeetype = [

        '1' => get_string('esp', 'local_users'),
        '2' => get_string('insource', 'local_users'),
    ];
    $select = $mform->addElement(
        'autocomplete',
        'employeetype',
        '',
        $employeetype,
        array('placeholder' => get_string('employeetype', 'local_users'))
    );
    $select->setMultiple(true);
}

function costcenterwise_users_datacount($costcenter, $department = false, $subdepartment = false, $l4department = false, $l5department = false) {
    global $USER, $DB, $CFG;
    $params = array();

    $params['costcenterpath'] = '%/' . $costcenter . '/%';
    $countusersql = "SELECT count(u.id) FROM {user} u WHERE concat('/',u.open_path,'/') LIKE :costcenterpath  AND deleted = 0";

    if ($l5department) {
        $countusersql .= " AND concat('/',u.open_path,'/') LIKE :l5departmentpath ";
        $params['l5departmentpath'] = '%/' . $l5department . '/%';
    } else if ($l4department) {
        $countusersql .= " AND concat('/',u.open_path,'/') LIKE :l4departmentpath ";
        $params['l4departmentpath'] = '%/' . $l4department . '/%';
    } else if ($subdepartment) {
        $countusersql .= " AND concat('/',u.open_path,'/') LIKE :subdepartmentpath ";
        $params['subdepartmentpath'] = '%/' . $subdepartment . '/%';
    } else if ($department) {
        $countusersql .= " AND concat('/',u.open_path,'/') LIKE :departmentpath ";
        $params['departmentpath'] = '%/' . $department . '/%';
    }

    $countusers = $DB->count_records_sql($countusersql, $params);
    return ['datacount' => $countusers];
}

/**
 * Retrieves a list of roles available for selection based on the current context level.
 *
 * This function queries the Moodle database to fetch roles that are applicable to the current
 * context level. It constructs a SQL query to select roles that meet specific criteria
 * depending on the depth of the category context. The roles are returned in a format suitable
 * for use in a dropdown menu, with an initial "select role" option.
 *
 * Role selection criteria:
 * - Excludes roles with an empty name.
 * - Excludes roles with the archetype 'manager'.
 * - Includes roles with 'administrator' shortname if the category context depth is 1.
 * - Includes roles with 'hod' shortname if the category context depth is 2 or if the user is a site admin.
 *
 * @param int $roleid Optional. The ID of a specific role to include in the results. If provided,
 *                     this role will be added to the list regardless of the other criteria.
 *
 * @return array An associative array where the keys are role IDs and the values are role names,
 *               with an initial option for selecting a role.
 */
function getrole($roleid = false) {
    global $DB;

    $params = [];

    // Get the category context from the local_users access library.
    $categorycontext = (new \local_users\lib\accesslib())::get_module_context();

    // Initialize the role selection options with a default "select role" option.
    $select = [0 => get_string('selectrole', 'role')];

    // Construct the SQL query to fetch role IDs and names based on the category context level.
    $sql = "SELECT r.id, r.name as fullname
              FROM {role} r
              JOIN {role_context_levels} rcl ON (rcl.contextlevel = :contextlevel AND r.id = rcl.roleid)
             WHERE r.name != '' AND (r.archetype != 'manager' ";

    // Adjust query conditions based on context depth.
    if ($categorycontext->depth == 1) {
        $sql .= " OR r.shortname LIKE 'administrator' ";
    }
    if ($categorycontext->depth == 2 || is_siteadmin()) {
        $sql .= " OR r.shortname LIKE 'hod' ";
    }

    if ($roleid > 0) {
        $sql .= " OR r.id = :id ";
        $params['id'] = $roleid;
    }

    // Close the SQL query.
    $sql .= ")";

    $params['contextlevel'] = CONTEXT_COURSECAT;

    // Execute the SQL query and merge the results with the initial selection options.
    $role = $select + $DB->get_records_sql_menu($sql, $params);

    // Return the list of roles with the initial selection option.
    return $role ?: $select;
}
