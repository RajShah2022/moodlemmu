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
 * @subpackage local_search
 */

defined('MOODLE_INTERNAL') || die();
define('MODULE_NOT_ENROLLED', 0);
define('MODULE_ENROLLED', 1);
define('MODULE_ENROLMENT_REQUEST', 2);
define('MODULE_ENROLMENT_PENDING', 3);
define('MODULE_ENROLMENT_WAITING', 4);

use local_search\output\allcourses as allcourses;

/**
 * @param object $coursedetails
 */
/*
* Author Rizwana
* Displays a node in left side menu
* @return  [type] string  link for the leftmenu
*/
function local_search_leftmenunode() {
    $systemcontext = \local_costcenter\lib\accesslib::get_module_context();
    $catalognode = '';
    if (has_capability('local/search:viewcatalog', $systemcontext) || is_siteadmin()) {
        $catalognode .= html_writer::start_tag('li', array('id' => 'id_leftmenu_calalogue', 'class' => 'pull-left user_nav_div calalogue'));
        $catalog_url = new moodle_url('/local/search/allcourses.php');
        $catalog = html_writer::link($catalog_url, '<i class="fa fa-search" aria-hidden="true"></i><span class="user_navigation_link_text">' . get_string('pluginname', 'local_search') . '</span>', array('class' => 'user_navigation_link'));
        $catalognode .= $catalog;
        $catalognode .= html_writer::end_tag('li');
    }

    return array('5' => $catalognode);
}

function local_search_get_coursecount_for_modules($moduletype) {
    // global $DB;

    $response = (new allcourses())->get_available_catalogtypes($moduletype);
    $sumofallrecords = $response['sumofallrecords'];
    return $sumofallrecords;
}
function local_search_get_coursecount_for_status($status) {
    $response = allcourses::get_available_catalogtypes($status);
    $sumofallrecords = $response['sumofallrecords'];
    return $sumofallrecords;
}

function local_search_get_itemlist_grade($start = 0, $limit = 5) {
    global $DB, $USER;
    $selectsql = "SELECT DISTINCT(open_grade), open_grade as value";
    $countsql = "SELECT count(DISTINCT(open_grade)) ";
    $sql = " FROM {user} AS u WHERE 1=1 AND suspended = 0 AND deleted = 0 AND open_grade != '' ";
    $params = [];
    $systemcontext = \local_costcenter\lib\accesslib::get_module_context();
    if (!(is_siteadmin() || has_capability('local/costcenter:manage_multiorganizations', $systemcontext)) && !empty($USER->open_path)) {
        $sql .= " AND :pathlike LIKE concat('%/',u.open_path,'/%') ";
        $params['pathlike'] = $USER->open_path . '/';
    }
    $grades = $DB->get_records_sql_menu($selectsql . $sql, $params, $start, $limit);

    $itemlist = [];
    foreach ($grades as $grade) {
        $response = allcourses::get_available_catalogtypes(['grade_' . $grade]);
        $sumofallrecords = $response['sumofallrecords'];
        $itemlist[] = ['tagitemid' => 'grade_' . $grade, 'tagitemname' => $grade, 'tagitemshortname' => $grade, 'coursecount' => $sumofallrecords];
    }
    $showviewmore = false;
    if ($start == 0) {
        $total_count = $DB->count_records_sql($countsql . $sql, $params);
        $showviewmore = $total_count > 6 ? true : false;
    }
    return [$itemlist, $showviewmore];
}

function local_search_get_itemlist_skill($start = 0, $limit = 6) {
    global $DB, $USER;
    $selectsql = "SELECT s.id, s.name as value ";
    $countsql = "SELECT count(s.id) ";
    $sql .= " FROM {local_skill} AS s WHERE 1=1 ";
    $params = [];
    if (!is_siteadmin() && $USER->open_path) {
        $sql .= " AND :openpathlike = concat(s.open_path,'/%') ";
        $params['openpathlike'] = $USER->open_path . '/';
    }
    $skill = $DB->get_records_sql_menu($selectsql . $sql, $params, $start, $limit);
    if ($start == 0) {
        if (count($skill) == $limit) {
            array_pop($skill);
            $showviewmore = true;
        } else {
            $showviewmore = false;
        }
    }
    $itemlist = [];
    foreach ($skill as $skillid => $skillname) {
        $response = allcourses::get_available_catalogtypes([['type' => 'skill', 'values' => [$skillid]]]);
        $sumofallrecords = $response['sumofallrecords'];
        $itemlist[] = ['code' => $skillid, 'name' => $skillname, 'tagitemshortname' => $skillname, 'count' => $sumofallrecords];
    }
    return [$itemlist, $showviewmore];
}

function local_search_get_itemlist_level($start = 0, $limit = 6) {
    global $DB, $USER;
    $selectsql = "SELECT cl.id, cl.name as value ";
    $countsql = "SELECT count(cl.id) ";
    $sql .= " FROM {local_course_levels} AS cl WHERE 1=1 ";
    $params = [];
    if (!is_siteadmin() && $USER->open_path) {
        $sql .= " AND :openpathlike = concat(cl.open_path,'/%') ";
        $params['openpathlike'] = $USER->open_path . '/';
    }
    $courselevel = $DB->get_records_sql_menu($selectsql . $sql, $params, $start, $limit);
    if ($start == 0) {
        if (count($courselevel) == $limit) {
            array_pop($courselevel);
            $showviewmore = true;
        } else {
            $showviewmore = false;
        }
    }
    $itemlist = [];
    foreach ($courselevel as $levelid => $levelname) {
        $response = allcourses::get_available_catalogtypes([['type' => 'level', 'values' => [$levelid]]]);
        $sumofallrecords = $response['sumofallrecords'];
        $itemlist[] = ['code' => $levelid, 'name' => $levelname, 'tagitemshortname' => $levelname, 'count' => $sumofallrecords];
    }
    return [$itemlist, $showviewmore];
}
function local_search_include_search_js() {
    $plugins = get_plugins_with_function('search_page_js');
    foreach ($plugins as $plugin) {
        foreach ($plugin as $function) {
            $function();
        }
    }
}
function local_search_get_enabled_searchplugin_info() {
    $plugins = get_plugins_with_function('enabled_search');
    $pluginsinfo = [];
    foreach ($plugins as  $plugin_type => $plugin) {
        foreach ($plugin as $pluginname => $function) {
            $plugin_exists = core_component::get_plugin_directory('local', $pluginname);
            if ($plugin_exists) {
                $pluginsinfo[] = $function();
            }
        }
    }
    return $pluginsinfo;
}
function local_search_get_filters() {
    $filter_array = [];
    $filter_array[] = local_search_get_filter_itemlist('moduletype', 0, 0);
    $filter_array[] = local_search_get_filter_itemlist('status', 0, 0);
    $categories = local_search_get_filter_itemlist('categories', 0, 0);

    if (!empty($categories['options'])) {
        if (count($categories['options']) > 0) {
            $filter_array[] = $categories;
        }
    }
    $customfield = local_search_get_filter_itemlist('customfields', 0, 0);
    if (!empty($customfield['options'])) {
        if (count($customfield['options']) > 0) {
            $filter_array[] = $customfield;
        }
    }
    $levels = local_search_get_filter_itemlist('level', 0, 0);
    if (!empty($levels['options'])) {
        if (count($levels['options']) > 0) {
            $filter_array[] = $levels;
        }
    }
    $skills = local_search_get_filter_itemlist('skill', 0, 0);
    if (!empty($skills['options'])) {
        if (count($skills['options']) > 0) {
            $filter_array[] = $skills;
        }
    }
    return $filter_array;
}
function local_search_get_filter_itemlist($catid, $start = 0, $limit = 7) {
    global $DB, $USER;
    switch ($catid) {
        case 'moduletype':
            $itemslist = [];
            $filterplugins = get_plugins_with_function('search_page_filter_element');

            foreach ($filterplugins as $filterelements) {
                foreach ($filterelements as $filterelement) {

                    $filterelement($itemslist);
                }
            }
            return ['type' => 'moduletype', 'name' => 'Module Type', 'options' => $itemslist, 'showviewmore' => false];
            break;
        case 'status':
            $itemslist[] = ['code' => 'notenrolled', 'name' => 'Not Enrolled', 'tagitemshortname' => 'notenrolled_modules', 'count' => local_search_get_coursecount_for_status([['type' => 'status', 'values' => ['notenrolled']]])];
            $itemslist[] = ['code' => 'inprogress', 'name' => 'In Progress', 'tagitemshortname' => 'enrolled_modules', 'count' => local_search_get_coursecount_for_status([['type' => 'status', 'values' => ['inprogress']]])];
            $itemslist[] = ['code' => 'completed', 'name' => 'Completed', 'tagitemshortname' => 'completed_modules', 'count' => local_search_get_coursecount_for_status([['type' => 'status', 'values' => ['completed']]])];
            return ['type' => 'status', 'name' => 'Status', 'options' => $itemslist, 'showviewmore' => false];
            break;
        case 'categories':
            $topcategorySql = "SELECT lcm.id, lcm.fullname FROM {local_custom_category} AS lcm WHERE lcm.parentid = 0 AND type = 0 ";
            if (!is_siteadmin() && !empty($USER->open_path)) {
                $costcenter = explode('/', $USER->open_path)[1];
                $topcategorySql .= " AND lcm.costcenterid = $costcenter ";
            }
            $topcategories = $DB->get_records_sql_menu($topcategorySql, $params);
            $return = [];
            foreach ($topcategories as $categoryid => $categoryname) {
                $return[] = local_search_get_categories($categoryid, $categoryname);
            }
            return $return;
            break;
        case 'customfields':
            $topcategorySql = "SELECT lcc.id, lcc.fullname FROM {local_custom_fields} AS lcc WHERE lcc.parentid = 0 ";
            $params = [];
            if (!is_siteadmin() && !empty($USER->open_path)) {
                $topcategorySql .= " AND :pathlike LIKE concat('%/',lcc.costcenterid,'/%') ";
                $params['pathlike'] = $USER->open_path . '/';
            }
            $topcategories = $DB->get_records_sql_menu($topcategorySql, $params);
            $return = [];
            foreach ($topcategories as $categoryid => $categoryname) {
                $return[] = local_search_get_customfields($categoryid, $categoryname);
            }
            return $return;
            break;
        case 'level':
            list($itemslist, $showviewmore) = local_search_get_itemlist_level($start, $limit);
            return ['type' => 'level', 'name' => 'Level', 'options' => $itemslist, 'showviewmore' => $showviewmore];
            break;

        case 'skill':
            list($itemslist, $showviewmore) = local_search_get_itemlist_skill($start, $limit);
            return ['type' => 'skill', 'name' => 'Skill', 'options' => $itemslist, 'showviewmore' => $showviewmore];
            break;
    }
}
function  local_search_get_customfields($categoryid, $categoryname, $start = 0, $limit = 7) {
    global  $DB;
    $categories = $DB->get_records_menu('local_custom_fields', ['parentid' => $categoryid], '', 'id, fullname', $start, $limit);
    if ($start == 0) {
        if (count($categories) == 7) {
            array_pop($categories);
            $showviewmore = true;
        } else {
            $showviewmore = false;
        }
    }
    $itemslist = [];
    foreach ($categories as $catid => $catname) {
        $coursecount = local_search\output\allcourses::get_available_catalogtypes([['type' => 'customfield_' . $categoryid, 'values' => [$catid]]])['sumofallrecords'];
        $itemslist[] = ['code' => $catid, 'name' => $catname, 'tagitemshortname' => $catname, 'count' => $coursecount];
    }
    return ['type' => 'customfield_' . $categoryid, 'name' => $categoryname, 'options' => $itemslist, 'showviewmore' => $showviewmore];
}
function  local_search_get_categories($categoryid, $categoryname, $start = 0, $limit = 7) {
    global  $DB;
    $categories = $DB->get_records_menu('local_custom_category', ['parentid' => $categoryid], '', 'id, fullname', $start, $limit);

    if ($start == 0) {
        if (count($categories) == 7) {
            array_pop($categories);
            $showviewmore = true;
        } else {
            $showviewmore = false;
        }
    }
    $itemslist = [];
    foreach ($categories as $typeid => $typename) {
        $coursecount = local_search\output\allcourses::get_available_catalogtypes([['type' => 'categories_' . $categoryid, 'values' => [$typeid]]])['sumofallrecords'];
        $itemslist[] = ['code' => $typeid, 'name' => $typename, 'tagitemshortname' => $typename, 'count' => $coursecount];
    }
    return ['type' => 'categories_' . $categoryid, 'name' => $categoryname, 'options' => $itemslist, 'showviewmore' => $showviewmore];
}
function local_search_get_module_return_parameters($tablename, $columnsdata = []) {
    global $DB;
    $columns = $DB->get_columns($tablename, false);
    foreach ($columns as $columnname => $datatype) {
        if (strpos($datatype->type, 'int') !== false) {
            $datatype = PARAM_INT;
        } else if (strpos($datatype->type, 'char') !== false) {
            $datatype = PARAM_TEXT;
        } else {
            $datatype = PARAM_RAW;
        }
        $columnsdata[$columnname] = new external_value($datatype, $columnname);
    }
    return new external_single_structure($columnsdata);
}
