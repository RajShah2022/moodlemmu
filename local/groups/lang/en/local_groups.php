<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * @package    local_groups
 * @copyright  2018 sreenivas
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['addcohort'] = 'Add new class';
$string['allcohorts'] = 'All classes';
$string['anycohort'] = 'Any';
$string['assign'] = 'Assign';
$string['assignto'] = 'Class \'{$a}\' members';
$string['backtocohorts'] = 'Back to classes';
$string['bulkadd'] = 'Add to class';
$string['bulknocohort'] = 'No available classes found';
$string['categorynotfound'] = 'Category <b>{$a}</b> not found or you don\'t have permission to create a class there. The default context will be used.';
$string['cohort'] = 'Class';
$string['cohorts'] = 'Manage Classes';
$string['pluginname'] = 'classes';
$string['cohortsin'] = '{$a}: available classes';
$string['assigncohorts'] = 'Assign class members';
$string['component'] = 'Source';
$string['contextnotfound'] = 'Context <b>{$a}</b> not found or you don\'t have permission to create a class there. The default context will be used.';
$string['csvcontainserrors'] = 'Errors were found in CSV data. See details below.';
$string['csvcontainswarnings'] = 'Warnings were found in CSV data. See details below.';
$string['csvextracolumns'] = 'Column(s) <b>{$a}</b> will be ignored.';
$string['currentusers'] = 'Current users';
$string['currentusersmatching'] = 'Current users matching';
$string['defaultcontext'] = 'Default context';
$string['delcohort'] = 'Delete class';
$string['delconfirm'] = 'Do you really want to delete class "<b>{$a}</b> "?';
$string['description'] = 'Description';
$string['displayedrows'] = '{$a->displayed} rows displayed out of {$a->total}.';
$string['duplicateidnumber'] = 'class with the same ID number already exists';
$string['editcohort'] = 'Edit class';
$string['editcohortidnumber'] = 'Edit class ID';
$string['editcohortname'] = 'Edit class name';
$string['eventcohortcreated'] = 'Class created';
$string['eventcohortdeleted'] = 'Class deleted';
$string['eventcohortmemberadded'] = 'User added to a class';
$string['eventcohortmemberremoved'] = 'User removed from a class';
$string['eventcohortupdated'] = 'Class updated';
$string['external'] = 'External class';
$string['idnumber'] = 'Class ID';
$string['memberscount'] = 'Class size';
$string['name'] = 'Name';
$string['namecolumnmissing'] = 'There is something wrong with the format of the CSV file. Please check that it includes column names.';
$string['namefieldempty'] = 'Field name can not be empty';
$string['newnamefor'] = 'New name for class {$a}';
$string['newidnumberfor'] = 'New ID number for class {$a}';
$string['nocomponent'] = 'Created manually';
$string['potusers'] = 'Potential users';
$string['potusersmatching'] = 'Potential matching users';
$string['preview'] = 'Preview';
$string['removeuserwarning'] = 'Removing users from a class may result in unenrolling of users from multiple courses which includes deleting of user settings, grades, class membership and other user information from affected courses.';
$string['selectfromcohort'] = 'Select members from cohort';
$string['systemcohorts'] = 'System classes';
$string['unknowncohort'] = 'Unknown class ({$a})!';
$string['uploadcohorts'] = 'Upload classes';
$string['uploadedcohorts'] = 'Uploaded {$a} classes';
$string['useradded'] = 'User added to class "{$a}"';
$string['search'] = 'Search';
$string['searchcohort'] = 'Search class';
$string['uploadcohorts_help'] = 'Classes may be uploaded via text file. The format of the file should be as follows:

* Each line of the file contains one record
* Each record is a series of data separated by commas (or other delimiters)
* The first record contains a list of fieldnames defining the format of the rest of the file
* Required fieldname is name
* Optional fieldnames are idnumber, description, descriptionformat, visible, context, category, category_id, category_idnumber, category_path
';
$string['visible'] = 'Visible';
$string['visible_help'] = "Any class can be viewed by users who have 'moodle/cohort:view' capability in the cohort context.<br/>
Visible groups can also be viewed by users in the underlying courses.";
$string['select_all'] = 'Select All';
$string['remove_all'] = 'Un Select All';
$string['not_enrolled_users'] = '<b>Not Enrolled Users ({$a})</b>';
$string['enrolled_users'] = '<b> Assign Users ({$a})</b>';
$string['remove_selected_users'] = '<b> Un Assign Users </b><i class="fa fa-arrow-right" aria-hidden="true"></i><i class="fa fa-arrow-right" aria-hidden="true"></i>';
$string['remove_all_users'] = '<b> Un Enroll All Users </b><i class="fa fa-arrow-right" aria-hidden="true"></i><i class="fa fa-arrow-right" aria-hidden="true"></i>';
$string['add_selected_users'] = '<i class="fa fa-arrow-left" aria-hidden="true"></i><i class="fa fa-arrow-left" aria-hidden="true"></i><b> Assign Users</b>';
$string['add_all_users'] = ' <i class="fa fa-arrow-left" aria-hidden="true"></i><i class="fa fa-arrow-left" aria-hidden="true"></i> <b> Enroll All Users </b>';
$string['groups:manage'] = 'Manage classes';
$string['groups:addinstance'] = 'Add local class instance';
$string['groups:create'] = 'Create local class';
$string['groups:delete'] = 'Delete local classes';
$string['groups:view'] = 'View local classes';
$string['availablelist'] = 'Available users';
$string['selectedlist'] = 'Selected users';
$string['enrolledlist'] = 'Enrolled users';
$string['completedlist'] = 'Completed users';
$string['enrolluserssuccess'] = '<b>{$a->changecount}</b> Employee(s) successfully enrolled to this <b>"{$a->group}"</b> class .';
$string['unenrolluserssuccess'] = '<b>{$a->changecount}</b> Employee(s) successfully un enrolled from this <b>"{$a->group}"</b> class .';

$string['enrollusers'] = 'Class <b>"{$a}"</b> enrollment is in process...';

$string['un_enrollusers'] = 'Class <b>"{$a}"</b> un enrollment is in process...';
$string['click_continue'] = 'Click on continue';
$string['leftmenu_groups'] = 'Manage Classes';
$string['enroll'] = 'Assign them to class';
$string['bulk_enroll'] = 'Bulk Assignments';
$string['user_exist'] = '{$a} - already enrolled to this class';
$string['im:stats_i'] = '{$a} Employee(s) successfully enrolled to this class';
$string['edit']='Actions';
$string['addnewgroups']='Add New class';
$string['editgroups']='Edit class';
$string['editgroup']='Update class';
$string['create_group'] = 'Create class';
$string['groupname']='Please add class name';
$string['assignments']='Assignments';

$string['tabgroupid']='Class-Id';
$string['grouptabuserscount']='Users-count';
$string['grouptabmanageusers']='Manage users';
$string['nogroupstoshow']='No Classes to Show';
$string['libupdategroup']='Update Class';
$string['renderepagetype']='page';
$string['rendercenteralignaction']='centeralign action';
$string['admintablegeneraltable']='admintable generaltable';
$string['admintableid']='cohorts';
$string['pleaseselectorganization'] = 'Please Select Company';
$string['listtype'] = 'LIST';
$string['cardtype'] = 'CARD';
$string['group_name'] = 'Classes Name';
$string['listicon'] ='icon fa fa-bars fa-fw';
$string['cardicon'] ='icon fa fa-fw fa-th';
$string['organisation'] ='Company';
$string['pleaseselectidnumber'] ='Please add class id';
$string['open_costcenteridlocal_groups'] = 'Company';
$string['open_costcenteridlocal_groups_help'] = 'Search and select an Company';
$string['hidecohort'] ='Make Inactive';
$string['confirmhide'] ='Do you really want to inactive class "<b>{$a}</b>"?';
$string['showcohort'] ='Make Active';
$string['confirmshow'] ='Do you really want to active class "<b>{$a}</b>"?';
$string['roles'] ='Roles';
$string['school'] ='School';


