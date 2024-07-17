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
* @subpackage block_myskills
*/
class block_myskills extends block_base {
	public function init() {
        $advance = get_config('local_skillrepository','advance');

        $this->title = get_string('pluginname', 'block_myskills');
    }
    
    public function get_content() {
        global $PAGE;
    	$this->content = new stdClass();
    	$renderer = $PAGE->get_renderer('block_myskills');
        //Using service.php way to display the myskill block
         if (isset ($this->config)) {            
          $displayskills = $this->config->employeedashboardlist;
        }        

    	if(is_siteadmin()){
		  $this->content->text = '';
		}else{
		  $this->content->text = $renderer->manageblockskill_content($displayskills);
		}
		$this->content->footer = '';
        return $this->content;
    }

    public function get_required_javascript() {
        global $USER;       
        $this->page->requires->js_call_amd('local_users/newuser', 'load', array());
        $this->page->requires->js_call_amd('block_myskills/skillinfotable', 'skillinfotable', array($USER->id));       
    }

    public function instance_allow_multiple() {
        //allow more than one instance on a page
        return true;
    }
    
    public function specialization() {
        $advance = get_config('local_skillrepository','advance');
        if (isset($this->config)) {        
            if($this->config->employeedashboardlist=='showcompetency' && $advance!=0)
            {
               $this->title = get_string('competencyprogress', 'block_myskills');
            }
            else
            {
                $this->title = get_string('pluginname', 'block_myskills');
            }
        }
    }
}
