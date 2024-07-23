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

namespace local_custom_fields;

use stdClass;

class querylib {
    function __construct() {
        global $DB, $CFG, $OUTPUT,  $USER, $PAGE;
        $this->db = $DB;
        $this->user = $USER;
    }
    function customfield_field($field, $data) {
        return $this->db->get_field('local_custom_fields', $field, $data);
    }

    function customfield_shortname($data) {
        return $this->db->get_record_sql('SELECT * FROM {local_custom_fields} WHERE shortname = ? AND  id <> ?', $data);
    }

    function customfield_record($data) {
        return $this->db->get_record('local_custom_fields', $data);
    }

    function customfield_exist($data) {
        return $this->db->record_exists('local_custom_fields', $data);
    }

    function customfield_mapped_exist($data) {
        return $this->db->record_exists('local_custom_fields_mapped', $data);
    }

    function customfield_records($depth, $data) {
        $sql = "SELECT DISTINCT(pc.id), pc.fullname FROM {local_custom_fields} AS pc ";
        if ($depth == 1) {
            $sql .= " JOIN {local_custom_fields} AS cc ON cc.parentid = pc.id ";
        }
        $sql .= " WHERE pc.depth = 1 AND pc.costcenterid = :costcenter";
        return $this->db->get_records_sql($sql, $data);
    }

    function customfield_fullnamewithid($data) {
        $sql = "SELECT id, fullname FROM {local_custom_fields} WHERE costcenterid = :costcenter AND parentid = :parent";
        return $this->db->get_records_sql_menu($sql, $data);
    }

    function customfield_count($data) {
        $catogories = "SELECT count(id) FROM {local_custom_fields}";
        if (!is_siteadmin()) {
            $catogories .= " where costcenterid = " . $data['costcenterid'];
        }
        return $this->db->count_records_sql($catogories);
    }

    function customfield_child_count($data) {
        return $this->db->count_records('local_custom_fields', $data);
    }
}
