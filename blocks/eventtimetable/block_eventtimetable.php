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
 * Block for displaying courses updates to users
 *
 * @package    block_user_courses_update
 * @copyright  2012 onwards Totara Learning Solutions Ltd {@link http://www.totaralms.com/}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author     Yuliya Bozhko <yuliya.bozhko@totaralms.com>
 */


 class block_eventtimetable extends block_base {

    public function init() {
        $this->title = get_string('pluginname', 'block_eventtimetable');
    }

    public function get_content() {
        global $DB, $USER, $OUTPUT;

        if ($this->content !== null) {
            return $this->content;
        }

        // Create the content object
        $this->content = new stdClass();
        $renderer = $this->page->get_renderer('block_eventtimetable');
        $this->content->text .= $renderer->render_timetable();

        return $this->content;
    }
    // function hide_header() {
    //     return true;
    // }
    public function get_required_javascript() {
        $this->page->requires->jquery();
        $this->page->requires->js('/blocks/eventtimetable/js/event-calendar.min.js', true);
        // $this->page->requires->js('/blocks/eventtimetable/js/select2.js', true);
        $this->page->requires->js_call_amd('local_classroom/classroom', 'load', array());
        $this->page->requires->js_call_amd('local_request/requestconfirm', 'load', array());
        $this->page->requires->js('/blocks/eventtimetable/js/custom.js', true);
        $this->page->requires->css('/blocks/eventtimetable/css/event-calendar.min.css');
        // $this->page->requires->css('/blocks/eventtimetable/css/select2.css');
    }
    public function has_config() {
        return true;
    }
}
