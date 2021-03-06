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

/**
 * Provide left hand navigation link
 *
 * @package    coursesearch
 * @category   local
 * @copyright  2015, Oxford Brookes University {@link http://www.brookes.ac.uk/}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


function local_coursesearch_extend_navigation($navigation) {
    global $CFG, $USER, $PAGE;
 
	// Add the parent if necessary
	$nodeCourses = $navigation->find('courses', navigation_node::TYPE_UNKNOWN);
	if (!$nodeCourses) {
		$nodeHome = $navigation->find('myhome', navigation_node::TYPE_UNKNOWN);
		if (!$nodeHome) {
			return;
		}
		$nodeCourses = $nodeHome->add(get_string('coursesnode', 'local_coursesearch'));
	}
	
	$nodeCourses->add(get_string('coursesearch', 'local_coursesearch'), '/local/coursesearch/coursesearch.php');
}
