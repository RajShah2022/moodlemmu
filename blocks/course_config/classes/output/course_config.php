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
 * Course Config View
 *
 * @package    block_course_config
 * @copyright  2024 Rizwana <rizwana.madire@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_course_config\output;

use html_writer;
use core_component;
require_once($CFG->dirroot . '/local/groups/lib.php');
class course_config {

    public function display_course_config_links() {
        global $USER, $COURSE, $OUTPUT;
        $categorycontext = (new \local_costcenter\lib\accesslib())::get_module_context();
        $cohortslist = get_user_cohort_names(null, $COURSE->id, true) ? get_user_cohort_names(null, $COURSE->id, true) : '';
        // $cohortslist = get_user_cohort_names($USER->id, null, true) ? get_user_cohort_names($USER->id, null, true) : '';
        // $data = [];
        foreach($cohortslist as $cohort) {
            $list = array();
            $list['cohortid'] = $cohort->id;
            $list['cohortname'] = $cohort->name;
            
            $data[] = $list;
        }
        $context = [
            'cohortslist' => $data,
            'canmanage' => (has_capability('local/courses:canconfiguregroupcontent', $categorycontext) && !is_siteadmin()) ? true : false,
            'coursename' => $COURSE->fullname,
            'courseid' => $COURSE->id
        ];
        return $OUTPUT->render_from_template('block_course_config/course_config', $context);;
    }
}