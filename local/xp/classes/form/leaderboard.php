<?php
// This file is part of Level Up XP+.
//
// Level Up XP+ is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Level Up XP+ is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Level Up XP+.  If not, see <https://www.gnu.org/licenses/>.
//
// https://levelup.plus

/**
 * Form.
 *
 * @package    local_xp
 * @copyright  2024 Frédéric Massart
 * @author     Frédéric Massart <fred@branchup.tech>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_xp\form;

use block_xp\di;
use block_xp\form\staticfield;
use block_xp\local\config\course_world_config;
use local_xp\local\config\default_course_world_config;

/**
 * Form.
 *
 * @package    local_xp
 * @copyright  2024 Frédéric Massart
 * @author     Frédéric Massart <fred@branchup.tech>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class leaderboard extends \block_xp\form\leaderboard {

    /**
     * Form definition.
     *
     * @return void
     */
    public function definition() {
        parent::definition();
        $mform = $this->_form;

        // Leaderboard isolation.
        if ($mform->elementExists('ladderiso')) {
            $mform->removeElement('ladderiso');
        }
        if (di::get('addon')->supports_leaderboard_isolation()) {
            $ladderisoel = $mform->createElement('select', 'ladderiso', get_string('ladderiso', 'block_xp'), [
                default_course_world_config::LEADERBOARD_ISO_DEFAULT => get_string('ladderisodefault', 'block_xp'),
                default_course_world_config::LEADERBOARD_ISO_COHORTS => get_string('ladderisocohorts', 'block_xp'),
            ]);
        } else {
            $els = [];
            $els[] = $mform->createElement('select', 'choices', '', [
                get_string('ladderisodefault', 'block_xp'),
                get_string('ladderisocohorts', 'block_xp'),
            ], ['disabled' => 'disabled']);
            $els[] = $mform->createElement(staticfield::name(), 'addonrequired', '', function() {
                return \html_writer::div(
                    \html_writer::div('XP+ Premium', 'xp-py-0.5 xp-px-1 xp-not-italic xp-normal-case xp-text-2xs xp-inline-block '
                        . ' xp-bg-black xp-text-white xp-rounded xp-no-underline xp-font-normal xp-align-middle')
                    , 'xp-flex-0');
            });
            $ladderisoel = $mform->createElement('group', 'ladderiso', get_string('ladderiso', 'block_xp'), $els);
        }

        $mform->insertElementBefore($ladderisoel, 'identitymode');
        $mform->addHelpButton('ladderiso', 'ladderiso', 'block_xp');
        unset($ladderisoel);

        // Re-implement to include additional option.
        $mform->removeElement('identitymode');
        $el = $mform->createElement('select', 'identitymode', get_string('anonymity', 'block_xp'), [
            course_world_config::IDENTITY_OFF => get_string('hideparticipantsidentity', 'block_xp'),
            course_world_config::IDENTITY_ON => get_string('displayparticipantsidentity', 'block_xp'),
            default_course_world_config::IDENTITY_FIRSTNAME_INITIAL_LASTNAME =>
                get_string('displayfirstnameinitiallastname', 'local_xp'),
        ]);
        $mform->insertElementBefore($el, 'neighbours');
        unset($el);
        $mform->addHelpButton('identitymode', 'anonymity', 'block_xp');
    }

}
