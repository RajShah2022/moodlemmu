<?php
/**
 * Language strings for local_trialsignup.
 *
 * @package    local_trialsignup
 * @copyright  2026 MathMeUp
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Trial Signup';

// Page-1 headings.
$string['settings_links_heading']      = 'Invitation links';
$string['settings_links_heading_desc'] = 'Share these URLs with prospects. Choose a partner from the dropdown to attribute signups to that partner. Self means no partner attribution.';

// Page-2 headings.
$string['config_pagename']             = 'Trial Signup Configuration';
$string['config_pagename_short']       = 'Configuration';
$string['settings_heading']            = 'Trial signup configuration';
$string['settings_heading_desc']       = 'All trial signup settings and partner management.';

// Standard settings.
$string['setting_trialdays']           = 'Trial duration (days)';
$string['setting_trialdays_desc']      = 'How many days each trial user retains cohort membership before the scheduled task removes them.';

$string['setting_costcenter']          = 'Trial organisation (cost-centre)';
$string['setting_costcenter_desc']     = 'All new trial users will be linked to this organisation. Required to prevent the dashboard error "Unknown category".';

$string['setting_role']                = 'Role for trial users';
$string['setting_role_desc']           = 'Role assigned at the cost-centre\'s category context. Typically Student.';

$string['setting_eligiblecohorts']     = 'Eligible cohorts';
$string['setting_eligiblecohorts_desc'] = 'Cohorts available for trial signup. Each generates an invitation URL on the main page.';

$string['setting_formtitle']           = 'Signup form title';
$string['setting_formtitle_desc']      = 'Heading shown at the top of the signup form.';
$string['setting_formtitle_default']   = 'Welcome — Sign up for your free trial';

$string['setting_contactemail']        = 'Contact email (for expired trials)';
$string['setting_contactemail_desc']   = 'Email address shown to trial users after their access has expired. Displayed in a banner on every page they visit.';

// Partners section.
$string['partners_heading']            = 'Channel partners';
$string['partners_heading_desc']       = 'Add channel partners that refer trial users. Each partner can have its own invitation URLs (visible on the main page). Partners cannot be deleted — toggle Active off to block their URLs.';
$string['partner_id']                  = 'ID';
$string['partner_name']                = 'Name';
$string['partner_active']              = 'Active';
$string['partner_actions']             = 'Action';
$string['partner_add']                 = 'Add partner';
$string['partner_add_button']          = 'Add';
$string['partner_added']               = 'Partner added.';
$string['partner_activated']           = 'Partner activated.';
$string['partner_deactivated']         = 'Partner deactivated.';
$string['partner_activate_btn']        = 'Activate';
$string['partner_deactivate_btn']      = 'Deactivate';
$string['partner_status_active']       = 'Active';
$string['partner_status_inactive']     = 'Inactive';
$string['partner_none']                = 'No partners yet. Use the form above to add one.';
$string['partner_name_required']       = 'Partner name is required.';

// Invitation links table.
$string['links_filter_label']          = 'Show links for partner:';
$string['links_filter_self']           = 'Self (no partner)';
$string['links_table_cohort']          = 'Cohort';
$string['links_table_idnumber']        = 'ID number';
$string['links_table_members']         = 'Members';
$string['links_table_url']             = 'Invitation URL';
$string['links_table_action']          = 'Action';
$string['links_table_copy']            = 'Copy';
$string['links_table_copied']          = 'Copied!';
$string['links_table_empty']           = 'No eligible cohorts selected. Configure them in Trial Signup Configuration.';

// Signup page.
$string['signup_pagetitle']            = 'Sign up for your free trial';
$string['signup_subtitle']             = 'You are signing up for: <strong>{$a}</strong>';
$string['signup_submit']               = 'Create account';

// Form fields.
$string['field_firstname']             = 'First name';
$string['field_lastname']              = 'Last name';
$string['field_username']              = 'Username';
$string['field_password']              = 'Password';
$string['field_email']                 = 'Email address';
$string['field_mobile']                = 'Mobile number';
$string['field_mobile_help']           = 'Enter a 10-digit mobile number, digits only.';
$string['field_city']                  = 'City / town';
$string['field_country']               = 'Country';
$string['field_school']                = 'School name';
$string['field_country_choose']        = 'Select a country...';

// Validation.
$string['err_required']                = 'This field is required.';
$string['err_mobile_format']           = 'Mobile number must be exactly 10 digits.';
$string['err_email_invalid']           = 'Please enter a valid email address.';
$string['err_email_taken']             = 'That email address is already registered.';
$string['err_username_taken']          = 'That username is already taken.';
$string['err_username_format']         = 'Username can only contain lowercase letters, numbers, and these characters: . - _ @';

// Errors / blocked states.
$string['error_invalid_invitation']    = 'Invalid invitation link.';
$string['error_notconfigured']         = 'Trial signup is not configured. Please contact the administrator.';
$string['error_costcenter_missing']    = 'Trial signup is not configured correctly. Please contact the administrator.';

// Confirmation page.
$string['confirm_title']               = 'Almost done!';
$string['confirm_message']             = 'A confirmation email has been sent to <strong>{$a}</strong>. Please click the link in the email to activate your account.';
$string['confirm_emailtrouble']        = 'If you don\'t see the email within a few minutes, check your spam folder.';
$string['confirm_loginlink']           = 'Continue to login';

// Expired trial banner (shown on every page for expired trial users).
$string['expired_banner']              = 'Your trial access has expired. Please contact <a href="mailto:{$a}">{$a}</a> to extend your access.';
$string['expired_banner_noemail']      = 'Your trial access has expired. Please contact your administrator to extend your access.';

// Reports page.
$string['reports_pagename']            = 'Trial Signup Reports';
$string['reports_pagetitle']           = 'Trial Signup Reports';
$string['reports_from']                = 'From:';
$string['reports_to']                  = 'To:';
$string['reports_download']            = 'Download Report (Excel)';
$string['reports_download_desc']       = 'Downloads a multi-sheet Excel file with: All Users, Courses Visited, Quiz Attempts, Quiz Summary, and Partner Summary — filtered to the selected date range.';

// Extend page.
$string['extend_pagename']             = 'Extend Trials';
$string['extend_pagetitle']            = 'Extend Trial Access';
$string['extend_search_label']         = 'Search user:';
$string['extend_search_placeholder']   = 'Username, name, or email...';
$string['extend_search_button']        = 'Search';
$string['extend_noresults']            = 'No trial users found matching "{$a}".';
$string['extend_results_count']        = 'Found {$a->count} user(s) matching "{$a->query}".';
$string['extend_col_username']         = 'Username';
$string['extend_col_name']             = 'Name';
$string['extend_col_email']            = 'Email';
$string['extend_col_cohort']           = 'Cohort';
$string['extend_col_status']           = 'Status';
$string['extend_col_expires']          = 'Expires';
$string['extend_col_action']           = 'Action';
$string['extend_btn_active']           = 'Extend +{$a} days';
$string['extend_btn_expired']          = 'Reactivate +{$a} days';
$string['extend_btn_norecord']         = 'No trial record';
$string['extend_success_extended']     = 'Trial extended for {$a->name}. New expiry: {$a->expires}.';
$string['extend_success_reactivated']  = 'Trial reactivated for {$a->name}. New expiry: {$a->expires}. User has been re-added to their cohort.';
$string['extend_err_norecord']         = 'No trial assignment record found for this user.';
$string['extend_err_nottrialuser']     = 'This user is not a trial user.';

// Scheduled task.
$string['task_expire_trials']          = 'Expire trial signups (remove users from cohorts)';

// Privacy.
$string['privacy:metadata']            = 'The Trial Signup plugin stores cohort assignments and (optionally) the channel partner that referred each user.';
$string['privacy:metadata:assignments']            = 'Cohort assignments made by the plugin.';
$string['privacy:metadata:assignments:userid']     = 'The user ID.';
$string['privacy:metadata:assignments:cohortid']   = 'The cohort ID the user was added to.';
$string['privacy:metadata:assignments:timeadded']  = 'When the user was added to the cohort.';
$string['privacy:metadata:assignments:expires']    = 'When the user will be removed from the cohort.';
