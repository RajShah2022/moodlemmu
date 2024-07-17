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
 * TODO describe file report
 *
 * @package    quizaccess_shinkan
 * @copyright  2023 YOUR NAME <your@email.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../../../../config.php');
require_login();
$courseid  = optional_param('courseid', 0, PARAM_INT);
$quizid  = optional_param('quizid', 0, PARAM_INT);
$userid  = optional_param('userid', 0, PARAM_INT);
$attemptid  = optional_param('attemptid', 0, PARAM_INT);
$url = new moodle_url('/mod/quiz/accessrule/shinkan/report.php', []);
$PAGE->set_url($url);
$PAGE->set_context(context_system::instance());

$PAGE->set_heading('Report');
echo $OUTPUT->header();
$renderer = $PAGE->get_renderer('quizaccess_shinkan');
echo $renderer->summary_report($courseid,$quizid,$userid,$attemptid);
echo $OUTPUT->footer();
