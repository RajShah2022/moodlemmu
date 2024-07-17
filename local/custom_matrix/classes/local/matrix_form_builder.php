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
 * @package BizLMS
 * @subpackage local_custom_matrix
 */

namespace local_custom_matrix\local;

use coding_exception;
use HTML_QuickForm_element;
use MoodleQuickForm;

/**
 * Helper class to build the form.
 */
class matrix_form_builder {

    /**
     * @var MoodleQuickForm
     */
    private $_form;

    public function __construct(MoodleQuickForm $form) {
        $this->_form = $form;
    }

    /**
     * @param string $name
     * @param string $label
     * @return object
     * @throws coding_exception
     */
    public function create_text(string $name, string $label = ''): object {


        if ($label === '') {
            $shortname = explode('[', $name);
            $shortname = reset($shortname);
            $label = lang::get($shortname);
        }
        return $this->_form->createElement('text', $name, $label);
    }

    public function create_static(string $html): object {
        $name = $this->create_name();
        return $this->_form->createElement('static', $name, null, $html);
    }

    public function create_name(): string {
        static $count = 0;
        return '__j' . $count++;
    }

    public function create_hidden(string $name, $value = null): object {
        return $this->_form->createElement('hidden', $name, $value);
    }
    public function create_selecttype_dropdown(string $name, $value = null): object {
        $options = array('workperformance'=>'Work Performance','organization objectives'=>'Organization Objectives','leadershipobjectives'=>'Leadership Objectives');
        return $this->_form->createElement('select', $name,'',$options);
    }

    public function create_selecttype_parameter_dropdown(string $name, $value = null): object {
        $options = array('kpi1'=>'KPI-1','kpi2'=>'KPI-2','kpi3'=>'KPI-3','kpi4'=>'KPI-4','kpi5'=>'KPI-5','kpi6'=>'KPI-6');
        return $this->_form->createElement('select', $name,'',$options);
    }

    public function dynamic_table_form_data($typeparameterrow,$typerowkey,$mform,$grading,$first=true,$last=false){

        if($first){

            $matrix[] = $this->create_static('<tr class="performancetype">');

            $matrix[] = $this->create_static('<td colspan="2">');

            $matrix[] = $this->create_static('<div class="input-group">');

            $cellname = $this->cell_name($typeparameterrow,0, true);

            $matrix[] = $this->create_selecttype_dropdown($cellname, false);

            $matrix[] = $this->create_hidden("type_rowid[$typerowkey]");

            $matrix[] = $this->create_hidden("type_parameter_rowid[$typerowkey][$typeparameterrow]");

            $matrix[] = $this->create_static('</div>');

            $matrix[] = $this->create_static('</td>');


            $matrix[] = $this->create_static('<td colspan="2"></td>');

            $matrix[] = $this->create_static('<td colspan="2"></td>');
            $matrix[] = $this->create_static('<td>');

            $cellcontent = $grading->create_cell_element($mform, $typeparameterrow, 3, true);

            $cellcontent = $cellcontent ? : $this->create_static('');

            $matrix[] = $cellcontent;

            $matrix[] = $this->create_static('</td>');

            $matrix[] = $this->create_static('</tr>');

            return $matrix;

        }else{

            $matrix[] = $this->create_static('<tr>');

            $matrix[] = $this->create_static('<td colspan="2">');

            $matrix[] = $this->create_static('</td>');

        }

        $matrix[] = $this->create_static('<td colspan="2">');

        $cellname = $this->cell_name($typeparameterrow,1, true);
        $matrix[] = $this->create_selecttype_parameter_dropdown($cellname, false);

        $matrix[] = $this->create_hidden("type_rowid[$typerowkey]");

        $matrix[] = $this->create_hidden("type_parameter_rowid[$typerowkey][$typeparameterrow]");

        $matrix[] = $this->create_static('</td>');

        $matrix[] = $this->create_static('<td colspan="2">');

        $cellcontent = $grading->create_cell_element($mform, $typeparameterrow, 2, true);

        $cellcontent = $cellcontent ? : $this->create_static('');

        $matrix[] = $cellcontent;


        $matrix[] = $this->create_static('</td>');


        if($last){


            $matrix[] = $this->create_static('<td colspan="2">');


            $next_type_parameter_row=$typeparameterrow;

            $matrix[] = $this->create_submit("add_type_parameter_rows[$next_type_parameter_row]", lang::add_performanceparam_btn(), ['class' => 'button-add']);
            $this->register_no_submit_button("add_type_parameter_rows[$cellname]");

            $matrix[] = $this->create_static('</td>');

        }else{

            $matrix[] = $this->create_static('<td colspan="2"></td>');

        }

        $matrix[] = $this->create_static('</tr>');

        return $matrix;
    }

    /**
     * @param string|null $name
     * @param string|null $label
     * @param array       $elements
     * @param string      $separator
     * @param bool        $appendname
     * @return object
     * @throws coding_exception
     */
    public function create_group(?string $name = null,
        ?string $label = null,
        array $elements = [],
        string $separator = '',
        bool $appendname = true): object {
        if ($label === '') {
            $shortname = explode('[', $name);
            $shortname = reset($shortname);
            $label = lang::get($shortname);
        }
        return $this->_form->createElement('group', $name, $label, $elements, $separator, $appendname);
    }
    /**
     * @param string $name
     * @param string $label
     * @param array  $attributes
     * @return object
     * @throws coding_exception
     */
    public function create_submit(string $name, string $label = '', array $attributes = []): object {
        if ($label === '') {
            $shortname = explode('[', $name);
            $shortname = reset($shortname);
            $label = lang::get($shortname);
        }
        return $this->_form->createElement('submit', $name, $label, $attributes);
    }

    public function add_javascript(string $js): object {
        $element = $this->create_javascript($js);
        $this->_form->addElement($element);
        return $element;
    }

    public function create_javascript(string $js): object {
        $html = '<script type="text/javascript">';
        $html .= $js;
        $html .= '</script>';
        $name = $this->create_name();
        return $this->_form->createElement('static', $name, null, $html);
    }
    public function register_no_submit_button(string $name): void {
        $this->_form->registerNoSubmitButton($name);
    }
    /**
     * Create the form element used to define the weight of the cell
     *
     * @param MoodleQuickForm $form
     * @param int             $row      row number
     * @param int             $col      column number
     * @param bool            $multiple whether the entity allows multiple
     * @return object
     */
    public function create_cell_element(MoodleQuickForm $form, int $row, int $col, bool $multiple): object {

        $cellname = $this->cell_name($row, $col, $multiple);

        return $form->createElement('text', $cellname, '', '', $col);
    }

    /**
     * Returns a cell name.
     * Should be a valid php and html identifier
     *
     * @param int  $row      row number
     * @param int  $col      col number
     * @param bool $multiple one answer per row or several
     *
     * @return string
     */
    public static function cell_name(int $row, int $col, bool $multiple): string {
        return $multiple ? "cell{$row}_$col" : "cell$row";

    }
}
