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

/**
 *  Helper class for strings
 */
class lang {

    const COMPONENT = 'local_custom_matrix';

    /**
     * @param string      $identifier
     * @param object|null $a
     * @return string
     * @throws coding_exception
     */
    public static function get(string $identifier, object $a = null): string {
        return get_string($identifier, self::COMPONENT, $a);
    }

    /**
     * @return string
     * @throws coding_exception
     */
    public static function default_performancetype(): string {
        return self::get('defaultperformancetype');
    }

    /**
     * @return string
     * @throws coding_exception
     */
    public static function row_performance_params(): string {
        return self::get('rowperformanceparams');
    }

    /**
     * @return string
     * @throws coding_exception
     */
    public static function row_performance_maxscore(): string {
        return self::get('rowperformancemaxscore');
    }

    /**
     * @return string
     * @throws coding_exception
     */
    public static function performance_weightage(): string {
        return self::get('performanceweightage');
    }

    /**
     * @return string
     * @throws coding_exception
     */
    public static function row_performance_types(): string {
        return self::get('rowperformancetypes');
    }

    /**
     * @return string
     * @throws coding_exception
     */
    public static function add_performancetype_btn(): string {
        return self::get('addperformancetypebtn');
    }
    /**
     * @return string
     * @throws coding_exception
     */
    public static function add_performanceparam_btn(): string {
        return self::get('addperformanceparambtn');
    }
    /**
     * @return string
     * @throws coding_exception
     */
    public static function numeric_weightage_check(): string {
        return self::get('numericweightagecheck');
    }
    /**
     * @return string
     * @throws coding_exception
     */
    public static function over_weightage_check(): string {
        return self::get('overweightagecheck');
    }
    /**
     * @return string
     * @throws coding_exception
     */
    public static function numeric_score_check(): string {
        return self::get('numericscorecheck');
    }
    /**
     * @return string
     * @throws coding_exception
     */
    public static function over_score_check(): string {
        return self::get('overscorecheck');
    }
}
