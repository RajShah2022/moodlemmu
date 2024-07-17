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

/*
 * This file is used to make the block in site or course
 *
 * @package    block
 * @subpackage performance_matrix
 * @copyright 
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

class block_performance_matrix extends block_base {

    /*
    * Standard block API function for initializing block instance
    * @return void
    */
    public function init() {
       $this->title = get_string('pluginname', 'block_performance_matrix');
    }

    public function get_required_javascript() {
        parent::get_required_javascript();
        $this->page->requires->jquery();
        $this->page->requires->js('/blocks/performance_matrix/js/matrix.js', true);
    }

    public function get_content() {
        global $PAGE,$CFG,$OUTPUT;
        if (is_siteadmin() || $this->content !== NULL) {
            return $this->content;
        }

        require_once($CFG->dirroot.'/blocks/performance_matrix/lib.php');
        $this->content = new stdClass();
      
        $renderer = $PAGE->get_renderer('block_performance_matrix');
        $this->content->text = $renderer->render_performancefilters();
        //$filterdata = make_custom_content();
       
        //$this->content->text .= html_writer::tag('div', $OUTPUT->render($filterdata),['class' => 'block_performance_matrix_filter']);
        
        return $this->content;
  
    }

    public function has_config() {
        return true;
    }

    /**
     * Do we allow multiple instances on the same page
     * @return bool
     */
    public function instance_allow_multiple() {
        return true;
    }

    public function get_config_for_external() {
        // Return all settings for all users since it is safe (no private keys, etc..).
        $configs = get_config('block_performance_matrix');

        return (object) [
            'instance' => new stdClass(),
            'plugin' => $configs,
        ];
    }

    public function instance_config_save($data, $nolongerused = false) {
        
        $config = clone ($data);
        parent::instance_config_save($config, $nolongerused);
    }

 
}

