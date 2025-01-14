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
 * Local Users plugin page allows you to edit a users profile for required custom profile fields
 *
 * @copyright MoodleIndia
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @package local_users
 */

 require_once(dirname(__FILE__) . '/../../config.php');

 global $CFG,$USER;

require_once($CFG->libdir.'/gdlib.php');
require_once($CFG->dirroot.'/user/edit_form.php');
require_once($CFG->dirroot.'/user/editlib.php');
require_once($CFG->dirroot.'/user/profile/lib.php');
require_once($CFG->dirroot.'/user/lib.php'); 

$userid = optional_param('id', $USER->id, PARAM_INT);    // User id.
$course = optional_param('course', SITEID, PARAM_INT);   // Course id (defaults to Site).
//$returnto = optional_param('returnto', null, PARAM_ALPHA);  // Code determining where to return to after save.
//$cancelemailchange = optional_param('cancelemailchange', 0, PARAM_INT);   // Course id (defaults to Site).

$PAGE->set_url('/local/users/edit.php', array('course' => $course, 'id' => $userid));

if (!$course = $DB->get_record('course', array('id' => $course))) {
    print_error('invalidcourseid');
}

if ($course->id != SITEID) {
    require_login($course);
} else if (!isloggedin()) {
    if (empty($SESSION->wantsurl)) {
        $SESSION->wantsurl = $CFG->wwwroot.'/local/users/edit.php';
    }
    redirect(get_login_url());
} else {
    $PAGE->set_context(context_system::instance());
}

// Guest can not edit.
if (isguestuser()) {
    print_error('guestnoeditprofile');
}

// The user profile we are editing.
if (!$user = $DB->get_record('user', array('id' => $userid))) {
    print_error('invaliduserid');
}

// Guest can not be edited.
if (isguestuser($user)) {
    print_error('guestnoeditprofile');
}

// Load the appropriate auth plugin.
$userauth = get_auth_plugin($user->auth);

if (!$userauth->can_edit_profile()) {
    print_error('noprofileedit', 'auth');
}

if ($editurl = $userauth->edit_profile_url()) {
    // This internal script not used.
    redirect($editurl);
}

if ($course->id == SITEID) {
    $coursecontext = context_system::instance();   // SYSTEM context.
} else {
    $coursecontext = context_course::instance($course->id);   // Course context.
}
$systemcontext   = context_system::instance();
$personalcontext = context_user::instance($user->id);

// Check access control.
if ($user->id == $USER->id) {
    // Editing own profile - require_login() MUST NOT be used here, it would result in infinite loop!
    if (!has_capability('moodle/user:editownprofile', $systemcontext)) {
        print_error('cannotedityourprofile');
    }

} else {
    // Teachers, parents, etc.
    require_capability('moodle/user:editprofile', $personalcontext);
    // No editing of guest user account.
    if (isguestuser($user->id)) {
        print_error('guestnoeditprofileother');
    }
    // No editing of primary admin!
    if (is_siteadmin($user) and !is_siteadmin($USER)) {  // Only admins may edit other admins.
        print_error('useradmineditadmin');
    }
}

if ($user->deleted) {
    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('userdeleted'));
    echo $OUTPUT->footer();
    die;
}

$PAGE->set_pagelayout('admin');
$PAGE->add_body_class('limitedwidth');
$PAGE->set_context($personalcontext);
if ($USER->id != $user->id) {
    $PAGE->navigation->extend_for_user($user);
} else {
    if ($node = $PAGE->navigation->find('myprofile', navigation_node::TYPE_ROOTNODE)) {
        $node->force_open();
    }
}

// Load custom profile fields data.
profile_load_data($user);

// Create form.

$userform = new local_users\forms\edit_user(new moodle_url($PAGE->url, array('returnto' => $returnto)), array(
    'editoroptions' => $editoroptions,
    'filemanageroptions' => $filemanageroptions,
    'user' => $user));

if ($userform->is_cancelled()) {
    redirect($returnurl);
} else if ($usernew = $userform->get_data()) {

   

    $authplugin = get_auth_plugin($user->auth);

    $usernew->timemodified = time();

    // Description editor element may not exist!
    if (isset($usernew->description_editor) && isset($usernew->description_editor['format'])) {
        $usernew = file_postupdate_standard_editor($usernew, 'description', $editoroptions, $personalcontext, 'user', 'profile', 0);
    }

    // Pass a true old $user here.
    if (!$authplugin->user_update($user, $usernew)) {
        // Auth update failed.
        print_error('cannotupdateprofile');
    }

    // Update user with new profile data.
    user_update_user($usernew, false, false);

    // Save custom profile fields data.
    profile_save_data($usernew);

    // Trigger event.
    \core\event\user_updated::create_from_userid($user->id)->trigger();


    // Reload from db, we need new full name on this page if we do not redirect.
    $user = $DB->get_record('user', array('id' => $user->id), '*', MUST_EXIST);

    if ($USER->id == $user->id) {
        
        // Preload custom fields.
        profile_load_custom_fields($USER);
    }

    if (is_siteadmin() and empty($SITE->shortname)) {
        // Fresh cli install - we need to finish site settings.
        redirect(new moodle_url('/admin/index.php'));
    }

    if (!$emailchanged || !$CFG->emailchangeconfirmation) {
        redirect($returnurl, get_string('changessaved'), null, \core\output\notification::NOTIFY_SUCCESS);
    }
}


// Display page header.
$streditmyprofile = get_string('editmyprofile');
$strparticipants  = get_string('participants');
$userfullname     = fullname($user, true);

$PAGE->set_title("$course->shortname: $streditmyprofile");
$PAGE->set_heading($userfullname);

echo $OUTPUT->header();
echo $OUTPUT->heading($userfullname);

if ($emailchanged) {
    echo $emailchangedhtml;
} else {
    // Finally display THE form.
    $userform->display();
}

// And proper footer.
echo $OUTPUT->footer();
