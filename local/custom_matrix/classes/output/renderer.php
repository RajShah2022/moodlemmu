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
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @author eabyas  <info@eabyas.in>
 * @package Bizlms
 * @subpackage local_custom_matrix
 */
namespace local_custom_matrix\output;

use dml_exception;
use html_table;
use html_writer;
use plugin_renderer_base;


/**
 * Generates the output for matrix entities.
 */
class renderer extends plugin_renderer_base {

    public function get_top_action_buttons_custom_matrix($parentcatid){
        global $CFG;
        $systemcontext =(new \local_custom_matrix\lib\accesslib())::get_module_context();
        $addcatstring = $parentcatid ? get_string('addmatrixperformance','local_custom_matrix') : get_string('addnewcustom_matrix', 'local_custom_matrix');
        $data =  "<ul class='course_extended_menu_list'>
                <li>
                    <div class='coursebackup course_extended_menu_itemcontainer'>
                          <a id='extended_menu_syncstats' title='". $addcatstring."' class='course_extended_menu_itemlink' href='javascript:void(0)' onclick ='(function(e){ require(\"local_custom_matrix/newcustommatrix\").init({selector:\"createrepositorymodal\", contextid:$systemcontext->id, repositoryid:0, parentcatid:$parentcatid}) })(event)'><i class='icon fa fa-plus' aria-hidden='true'></i>
                          </a>
                      </div>
                </li>
            </ul>";
        return $data;
    }

    public function custom_matrix_content($parentcatid = false, $filter = false, $pagetype = false){
        global $USER;
        $systemcontext =(new \local_custom_matrix\lib\accesslib())::get_module_context();
        $options = array('targetID' => 'manage_custom_matrix','perPage' => 10, 'cardClass' => 'w_oneintwo', 'viewType' => 'table');

        $options['methodName']='local_custom_matrix_custom_matrix_view';
        $options['templateName']='local_custom_matrix/custom_matrix_view';
        $options = json_encode($options);

        $dataoptions = json_encode(array('userid' =>$USER->id,'contextid' => $systemcontext->id, 'parentcatid' => $parentcatid));
        $filterdata = json_encode(array());

        $context = [
            'targetID' => 'manage_custom_matrix',
            'options' => $options,
            'dataoptions' => $dataoptions,
            'filterdata' => $filterdata
        ];

        if($filter){
            return  $context;
        }else{
            return  $this->render_from_template('local_costcenter/cardPaginate', $context);
        }
    }

 

}
