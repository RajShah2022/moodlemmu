<?php
/**
 * Trial signup form.
 *
 * @package    local_trialsignup
 * @copyright  2026 MathMeUp
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_trialsignup\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

class signup_form extends \moodleform {

    public function definition() {
        global $CFG;

        $mform = $this->_form;

        $cohortname = $this->_customdata['cohortname'] ?? '';
        $title = $this->_customdata['title']
            ?? get_string('setting_formtitle_default', 'local_trialsignup');

        // ============ Header block ============
        $headerhtml = '<div class="local-trialsignup-header text-center mb-4">';
        $headerhtml .= '<h2 class="local-trialsignup-title mb-2">' . s($title) . '</h2>';
        if ($cohortname !== '') {
            $headerhtml .= '<p class="local-trialsignup-subtitle mb-0">';
            $headerhtml .= get_string('signup_subtitle', 'local_trialsignup',
                format_string($cohortname));
            $headerhtml .= '</p>';
        }
        $headerhtml .= '</div>';
        $mform->addElement('html', $headerhtml);

        // ============ Open two-column wrapper ============
        $mform->addElement('html',
            '<div class="local-trialsignup-form"><div class="row">' .
            '<div class="col-md-6 px-md-4">'
        );

        // ============ LEFT COLUMN — Account credentials ============
        $mform->addElement('text', 'username',
            get_string('field_username', 'local_trialsignup'),
            ['autocomplete' => 'username']);
        $mform->setType('username', PARAM_RAW);
        $mform->addRule('username', get_string('err_required', 'local_trialsignup'),
            'required', null, 'client');

        $mform->addElement('passwordunmask', 'password',
            get_string('field_password', 'local_trialsignup'),
            ['size' => 24, 'autocomplete' => 'new-password']);
        $mform->setType('password', PARAM_RAW);
        $mform->addRule('password', get_string('err_required', 'local_trialsignup'),
            'required', null, 'client');

        $mform->addElement('text', 'email',
            get_string('field_email', 'local_trialsignup'),
            ['autocomplete' => 'email']);
        $mform->setType('email', PARAM_RAW_TRIMMED);
        $mform->addRule('email', get_string('err_required', 'local_trialsignup'),
            'required', null, 'client');

        $mform->addElement('text', 'city',
            get_string('field_city', 'local_trialsignup'),
            ['autocomplete' => 'address-level2']);
        $mform->setType('city', PARAM_TEXT);

        $countries = ['' => get_string('field_country_choose', 'local_trialsignup')]
            + get_string_manager()->get_list_of_countries();
        $mform->addElement('select', 'country',
            get_string('field_country', 'local_trialsignup'),
            $countries, ['autocomplete' => 'country-name']);
        if (!empty($CFG->country)) {
            $mform->setDefault('country', $CFG->country);
        }

        // ============ Switch to RIGHT COLUMN ============
        $mform->addElement('html', '</div><div class="col-md-6 px-md-4">');

        // ============ RIGHT COLUMN — Personal details ============
        $mform->addElement('text', 'firstname',
            get_string('field_firstname', 'local_trialsignup'),
            ['autocomplete' => 'given-name']);
        $mform->setType('firstname', PARAM_TEXT);
        $mform->addRule('firstname', get_string('err_required', 'local_trialsignup'),
            'required', null, 'client');

        $mform->addElement('text', 'lastname',
            get_string('field_lastname', 'local_trialsignup'),
            ['autocomplete' => 'family-name']);
        $mform->setType('lastname', PARAM_TEXT);
        $mform->addRule('lastname', get_string('err_required', 'local_trialsignup'),
            'required', null, 'client');

        $mform->addElement('text', 'phone1',
            get_string('field_mobile', 'local_trialsignup'),
            ['autocomplete' => 'tel', 'maxlength' => 10, 'inputmode' => 'numeric']);
        $mform->setType('phone1', PARAM_TEXT);
        $mform->addRule('phone1', get_string('err_required', 'local_trialsignup'),
            'required', null, 'client');
        $mform->addRule('phone1', get_string('err_mobile_format', 'local_trialsignup'),
            'regex', '/^[0-9]{10}$/', 'client');

        $mform->addElement('text', 'institution',
            get_string('field_school', 'local_trialsignup'),
            ['autocomplete' => 'organization']);
        $mform->setType('institution', PARAM_TEXT);

        // ============ Close columns ============
        $mform->addElement('html', '</div></div>');

        // ============ Hidden tokens ============
        $mform->addElement('hidden', 'cohortid', $this->_customdata['cohortid'] ?? 0);
        $mform->setType('cohortid', PARAM_INT);

        $mform->addElement('hidden', 'partnerid', $this->_customdata['partnerid'] ?? 0);
        $mform->setType('partnerid', PARAM_INT);

        // ============ Submit ============
        $mform->addElement('html',
            '<div class="local-trialsignup-actions text-center mt-4">');
        $this->add_action_buttons(false,
            get_string('signup_submit', 'local_trialsignup'));
        $mform->addElement('html', '</div>');

        $mform->addElement('html', '</div>');
    }

    public function validation($data, $files) {
        global $DB, $CFG;

        $errors = parent::validation($data, $files);

        // Username.
        $username = trim($data['username'] ?? '');
        if ($username !== '') {
            if (strtolower($username) !== $username) {
                $errors['username'] = get_string('err_username_format', 'local_trialsignup');
            } else if (!preg_match('/^[a-z0-9._\-@]+$/', $username)) {
                $errors['username'] = get_string('err_username_format', 'local_trialsignup');
            } else if ($DB->record_exists('user',
                    ['username' => $username, 'mnethostid' => $CFG->mnet_localhost_id])) {
                $errors['username'] = get_string('err_username_taken', 'local_trialsignup');
            }
        }

        // Email.
        $email = trim($data['email'] ?? '');
        if ($email !== '') {
            if (!validate_email($email)) {
                $errors['email'] = get_string('err_email_invalid', 'local_trialsignup');
            } else if ($DB->record_exists_select('user',
                    'LOWER(email) = LOWER(:email) AND mnethostid = :mnet AND deleted = 0',
                    ['email' => $email, 'mnet' => $CFG->mnet_localhost_id])) {
                $errors['email'] = get_string('err_email_taken', 'local_trialsignup');
            }
        }

        // Mobile.
        $mobile = trim($data['phone1'] ?? '');
        if ($mobile === '') {
            $errors['phone1'] = get_string('err_required', 'local_trialsignup');
        } else if (!preg_match('/^[0-9]{10}$/', $mobile)) {
            $errors['phone1'] = get_string('err_mobile_format', 'local_trialsignup');
        }

        // Password policy.
        $password = $data['password'] ?? '';
        if ($password !== '' && !empty($CFG->passwordpolicy)) {
            $errmsg = '';
            if (!check_password_policy($password, $errmsg, null)) {
                $errors['password'] = $errmsg;
            }
        }

        return $errors;
    }
}
