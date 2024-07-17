<?php
/**
 * This file is part of eAbyas
 *
 * Copyright eAbyas Info Solutons Pvt Ltd, India
 *
 * This trainerdashboard is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This trainerdashboard is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this trainerdashboard.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @author eabyas  <info@eabyas.in>
 * @package Bizlms 
 * @subpackage block_trainerdashboard
 */
class block_myskills_edit_form extends block_edit_form {
 
    protected function specific_definition($mform) {

        // Section header title according to language file.
        $mform->addElement('header', 'config_header', get_string('blocksettings', 'block'));

        $employeedashboard = array();  
      
        $employeedashboard['showskills'] = get_string('showskills', 'block_myskills');
        if(get_config('local_skillrepository','advance')){
            $employeedashboard['showcompetency'] = get_string('showcompetency', 'block_myskills');
        }        

        $mform->addElement('select', 'config_employeedashboardlist', get_string('skilldisplay', 'block_myskills'), $employeedashboard); 
 
    }
    
}