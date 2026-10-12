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

namespace format_flexsections\local;

/**
 * Cohort-aware visibility helper.
 *
 * The core "Hidden from students" badge is driven only by the single `visible`
 * flag on a section/module. In this deployment per-cohort visibility is expressed
 * through availability restrictions (group conditions). Once an item is enabled for
 * ANY one cohort its `visible` flag flips to 1 and the core badge disappears
 * globally - even for a teacher whose own cohorts still cannot see the item.
 *
 * This helper decides, from the point of view of the CURRENT user's cohorts
 * (the groups they belong to in the course), whether an otherwise visible item is
 * in fact hidden from all of their students.
 *
 * @package   format_flexsections
 * @copyright 2026 MathMeUp
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class visibilityhelper {

    /** @var array<string,int[]> per-request cache of the current user's group ids, keyed by "courseid:userid". */
    protected static $mygroupscache = [];

    /**
     * Is this item visible-flagged but available only to groups the current user is NOT in?
     *
     * Returns true only when the availability is group-gated and none of the gated
     * groups is one of the current user's own groups (i.e. hidden from all of *their*
     * students). Items with no restriction, or not group-gated, or gated to at least
     * one of the user's groups, return false.
     *
     * @param string|null $availability the raw availability JSON of the section/module
     * @param int $courseid the course id
     * @return bool
     */
    public static function hidden_from_my_students(?string $availability, int $courseid): bool {
        // Site admins see everything; the per-cohort notion does not apply to them.
        if (is_siteadmin()) {
            return false;
        }
        // No restriction -> visible to everyone enrolled -> not hidden.
        if (empty($availability)) {
            return false;
        }
        $tree = json_decode($availability);
        if (!is_object($tree)) {
            return false;
        }
        $groupids = [];
        self::collect_group_ids($tree, $groupids);
        // Not group-gated -> we cannot say it is hidden from a specific cohort.
        if (empty($groupids)) {
            return false;
        }
        $mygroupids = self::my_group_ids($courseid);
        // User is not scoped to any cohort/group -> do not flag.
        if (empty($mygroupids)) {
            return false;
        }
        // Hidden from my students when NONE of my groups is granted access.
        return count(array_intersect($groupids, $mygroupids)) === 0;
    }

    /**
     * The group ids the current user belongs to in the course (cached per request).
     *
     * @param int $courseid
     * @return int[]
     */
    protected static function my_group_ids(int $courseid): array {
        global $USER;
        $key = $courseid . ':' . $USER->id;
        if (!array_key_exists($key, self::$mygroupscache)) {
            self::$mygroupscache[$key] = array_map('intval',
                array_keys(groups_get_all_groups($courseid, $USER->id)));
        }
        return self::$mygroupscache[$key];
    }

    /**
     * Recursively collect every {"type":"group","id":N} id in an availability tree.
     *
     * @param mixed $node a node of the decoded availability JSON
     * @param int[] $out collected group ids (by reference)
     */
    protected static function collect_group_ids($node, array &$out): void {
        if (!is_object($node)) {
            return;
        }
        if (isset($node->type) && $node->type === 'group' && isset($node->id)) {
            $out[] = (int)$node->id;
        }
        if (isset($node->c) && is_array($node->c)) {
            foreach ($node->c as $child) {
                self::collect_group_ids($child, $out);
            }
        }
    }
}

