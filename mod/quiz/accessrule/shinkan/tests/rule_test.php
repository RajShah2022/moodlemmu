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
 * TODO describe file rule_test
 *
 * @package    quizaccess_shinkan
 * @copyright  2023 YOUR NAME <your@email.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/quiz/accessrule/shinkan/rule.php');

class quizaccess_shinkan_test extends basic_testcase {
    public function test_honestycheck_rule() {
        $quiz = new stdClass();
        $quiz->attempts = 3;
        $quiz->questions = '';
        $cm = new stdClass();
        $cm->id = 0;
        $quizobj = new quiz($quiz, $cm, null);
        $rule = quizaccess_shinkan::make($quizobj, 0, false);
        $this->assertNull($rule);

        $quiz->proctoringrequired = true;
        $rule = quizaccess_shinkan::make($quizobj, 0, false);
        $this->assertInstanceOf('quizaccess_shinkan', $rule);
        $this->assertTrue($rule->is_preflight_check_required(null));

        $this->assertFalse($rule->is_preflight_check_required(1));

        $errors = $rule->validate_preflight_check(array(), null, array(), 1);
        $this->assertArrayHasKey('proctoringrequired', $errors);

        $errors = $rule->validate_preflight_check(array('proctoringcheck' => 1), null, array(), 1);
        $this->assertEmpty($errors);
    }
}