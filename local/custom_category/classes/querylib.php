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
 * @subpackage querylib.php
 */

namespace local_custom_category;

use stdClass;

class querylib {
    function __construct() {
        global $DB, $CFG, $OUTPUT,  $USER, $PAGE;
        $this->db = $DB;
        $this->user = $USER;
    }
    function category_field($field, $data) {
        return $this->db->get_field('local_custom_category', $field, $data);
    }

    function category_shortname($data) {
        return $this->db->get_record_sql('SELECT * FROM {local_custom_category} WHERE shortname = ? AND  id <> ?', $data);
    }

    function category_record($data) {
        return $this->db->get_record('local_custom_category', $data);
    }

    function category_exist($data) {
        return $this->db->record_exists('local_custom_category', $data);
    }

    function category_records($depth, $data) {
        $sql = "SELECT DISTINCT(pc.id), pc.fullname FROM {local_custom_category} AS pc ";
        if ($depth == 1) {
            $sql .= " JOIN {local_custom_category} AS cc ON cc.parentid = pc.id ";
        }
        $sql .= " WHERE pc.depth = 1 AND pc.costcenterid = :costcenter";
        return $this->db->get_records_sql($sql, $data);
    }

    function category_fullnamewithid($data) {
        $sql = "SELECT id, fullname FROM {local_custom_category} WHERE costcenterid = :costcenter AND parentid = :parent";
        return $this->db->get_records_sql_menu($sql, $data);
    }

    function category_count($data) {
        $catogories = "SELECT count(id) FROM {local_custom_category}";
        if (!is_siteadmin()) {
            $catogories .= " where costcenterid = " . $data['costcenterid'];
        }
        return $this->db->count_records_sql($catogories);
    }

    function category_child_count($data) {
        return $this->db->count_records('local_custom_category', $data);
    }
}
