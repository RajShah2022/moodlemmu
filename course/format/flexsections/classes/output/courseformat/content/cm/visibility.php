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

namespace format_flexsections\output\courseformat\content\cm;

use context_course;
use format_flexsections\local\visibilityhelper;
use stdClass;

/**
 * Cohort-aware activity visibility for flexsections.
 *
 * Extends the core activity visibility badge so that an activity which is
 * `visible = 1` (enabled for some other cohort) but not available to any of the
 * current teacher's cohorts still shows the "Hidden from students" badge.
 *
 * @package   format_flexsections
 * @copyright 2026 MathMeUp
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class visibility extends \core_courseformat\output\local\content\cm\visibility {

    /**
     * Show the visibility badge when the core rules apply OR when the activity is
     * hidden from the current teacher's own students.
     *
     * @return bool
     */
    protected function show_visibility(): bool {
        return parent::show_visibility() || $this->hidden_from_my_students();
    }

    /**
     * Add the "hidden from students" flag for the static (non-editing) badge when the
     * activity is hidden from the current teacher's cohorts.
     *
     * @param \renderer_base $output
     * @return stdClass|null
     */
    public function build_static_data(\renderer_base $output): ?stdClass {
        $data = parent::build_static_data($output);
        if ($data !== null
                && empty($data->modhiddenfromstudents)
                && empty($data->modstealth)
                && $this->hidden_from_my_students()) {
            $data->modhiddenfromstudents = true;
        }
        return $data;
    }

    /**
     * Is this activity hidden from all of the current user's cohorts?
     *
     * @return bool
     */
    protected function hidden_from_my_students(): bool {
        $courseid = $this->mod->get_course()->id;
        // Only meaningful for users who can view hidden activities (teachers/managers).
        if (!has_capability('moodle/course:viewhiddenactivities', context_course::instance($courseid))) {
            return false;
        }
        return visibilityhelper::hidden_from_my_students($this->mod->availability, $courseid);
    }
}

