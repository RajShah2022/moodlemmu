<?php

/**
* This file is part of eAbyas
*
* Copyright eAbyas Info Solutons Pvt Ltd, India
*
* This program is free software; you can redistribute it and/or modify
* it under the terms of the GNU General Public License as published by
* the Free Software Foundation; either version 3 of the License, or
* (at your option) any later version.
*
* This program is distributed in the hope that it will be useful,
* but WITHOUT ANY WARRANTY; without even the implied warranty of
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
* GNU General Public License for more details.
*
* You should have received a copy of the GNU General Public License
* along with this program. If not, see <http://www.gnu.org/licenses/>.
*
* @author eabyas <info@eabyas.in>
* @package BizLMS
* @subpackage block_course_config
*/

defined('MOODLE_INTERNAL') || die();
use \block_course_config\output\course_config as course_config;

class block_course_config extends block_base {

	function init() {
		$this->title = get_string('pluginname', 'block_course_config');
	}

	function instance_allow_multiple() {
		return false;
	}

	function get_content() {
		global $COURSE;
		$general_lib = new local_courses\local\general_lib();
		$theme = $general_lib->get_theme();

		if ($this->content !== NULL) {
			return $this->content;
		}

		$this->content = new stdClass();
        // if ($COURSE->id == SITEID) {
            $context = \local_costcenter\lib\accesslib::get_module_context();
        // } else {
        //     $context = context_course::instance($COURSE->id);
        // }
        // print_r($context);exit;
		// $systemcontext = \local_costcenter\lib\accesslib::get_module_context();
		// if(is_siteadmin() || has_capability('block/course_config:view', $context)){
			// print_r('have cap');exit;
			$this->page->requires->js_call_amd('theme_' . $theme . '/quickactions', 'quickactionsCall');
			$this->page->requires->js_call_amd('local_courses/contentconfiguration', 'init', array());
			$course_config = new course_config();

			$this->content->text = $course_config->display_course_config_links();
		// }else{
		// 	$this->content->text = '';
		// }
		$this->content->footer = '';
		return $this->content;
	}
}
