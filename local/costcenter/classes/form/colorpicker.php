<?php
// This file is part of the tool_certificate for Moodle - http://moodle.org/
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
 *  This file contains the form element for handling the colour picker.
 *
 * @package    local_costcenter
 * @copyright  2023 Moodle India Information Solutions Pvt Ltd
 * @author     2023 Manasa.V <manasa.vulisa@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_costcenter\form;

defined('MOODLE_INTERNAL') || die;
require_once("HTML/QuickForm/text.php");

/**
 *  HTML colour picker.
 *
 * @package    local_costcenter
 * @copyright  2023 Moodle India Information Solutions Pvt Ltd
 * @author     2023 Manasa.V <manasa.vulisa@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class moodlequickform_local_costcenter_colorpicker extends \HTML_QuickForm_text {

    /**
     * Returns the html string to display this element.
     *
     * @return string
     */
    public function tohtml() {
        global $PAGE, $OUTPUT;
        $id     = $this->getAttribute('id');
        $PAGE->requires->js_init_call('M.util.init_colour_picker', [$id]);
        $content  = \html_writer::start_tag('div', ['class' => 'form-colourpicker defaultsnext']);
        $content .= \html_writer::tag('div', $OUTPUT->pix_icon(
            'i/loading',
            get_string('loading', 'admin'),
            'moodle',
            ['class' => 'loadingicon']
        ), ['class' => 'admin_colourpicker clearfix']);

        $content .= \html_writer::end_tag('div');
        $content .= '<input size="7" name="' . $this->getName() . '"
                        value="' . $this->getValue() . '" id="' . $id . '" type="text" >';
        return $content;
    }
}

require_once($CFG->libdir . '/formslib.php');
\MoodleQuickForm::registerElementType(
    'costcenter_colorpicker',
    $CFG->dirroot . '/' . $CFG->admin . '/local/costcenter/classes/form/colorpicker.php',
    \local_costcenter\form\MoodleQuickForm_local_costcenter_colorpicker::class
);
