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
 * Do db queries (in the absence of an index) to find required types of courses
 *
 * @package    coursesearch
 * @category   local
 * @copyright  2015, Oxford Brookes University {@link http://www.brookes.ac.uk/}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */



/**
 * A list of courses that match a specific search
 *
 * @global object
 * @global object
 * @param array $searchterms An array of search criteria
 * @param string $sort A field and direction to sort by
 * @param int $page The page number to get
 * @param int $recordsperpage The number of records per page
 * @param int $totalcount Passed in by reference.
 * @return object {@link $COURSE} records
 */

function do_advanced_course_search($searchtermsinclude, $searchtermsexclude, $searchteacher, $sort='fullname ASC', $page=0, $recordsperpage=50, &$totalcount, $exactinclude='', $exactexclude='', $includeandor='', $excludeandor='', $searchfield_summary='') {
    global $CFG, $DB;

    if ($DB->sql_regex_supported()) {
        $REGEXP    = $DB->sql_regex(true);
        $NOTREGEXP = $DB->sql_regex(false);
    }

    $searchcondinclude = array();
    $searchcondexclude = array();
    
    $params     = array();
    $i = 0;

    // Thanks Oracle for your non-ansi concat and type limits in coalesce. MDL-29912
    if ($DB->get_dbfamily() == 'oracle') {
        if (!empty($searchfield_summary)) {
            $concat = $DB->sql_concat('c.summary', "' '", 'c.fullname', "' '", 'c.idnumber', "' '", 'c.shortname');
        } else {
            $concat = $DB->sql_concat('c.fullname', "' '", 'c.idnumber', "' '", 'c.shortname');
        }
    } else {
        if (!empty($searchfield_summary)) {
            $concat = $DB->sql_concat("COALESCE(c.summary, '" ."')", "' '", 'c.fullname', "' '", 'c.idnumber', "' '", 'c.shortname');
        } else {
            $concat = $DB->sql_concat('c.fullname', "' '", 'c.idnumber', "' '", 'c.shortname');            
        } 
    }

    if ($searchtermsinclude) {
        
        foreach ($searchtermsinclude as $searchterm) {
            $i++;

            if ($exactinclude) {
                $searchterm = preg_quote($searchterm, '|');
                $searchcondinclude[] = "$concat $REGEXP :ss$i";
                $params['ss'.$i] = "(^|[^a-zA-Z0-9])$searchterm([^a-zA-Z0-9]|$)";                 
            } else {
                $NOT = false;
                $searchcondinclude[] = $DB->sql_like($concat,":ss$i", false, true, $NOT);
                $params['ss'.$i] = "%$searchterm%";
            }
        }
    }

    if ($searchtermsexclude) {
        
        foreach ($searchtermsexclude as $searchterm) {
            $i++;

            if ($exactexclude) {
                $searchterm = preg_quote($searchterm, '|');
                $searchcondexclude[] = "$concat $NOTREGEXP :ss$i";
                $params['ss'.$i] = "(^|[^a-zA-Z0-9])$searchterm([^a-zA-Z0-9]|$)";                 
            } else {            
                $NOT = true;
                $searchcondexclude[] = $DB->sql_like($concat,":ss$i", false, true, $NOT);
                $params['ss'.$i] = "%$searchterm%";
            }
        } 
    }   

    if (empty($searchcondinclude) && empty($searchteacher)) {
        $totalcount = 0;
        return array();
    }

    $searchcondinclude = implode(" $includeandor ", $searchcondinclude);
    if (!empty($searchcondinclude)) { 
        $searchcondinclude = "($searchcondinclude)";
    }
    
    if ($searchtermsexclude) {
        $searchcondexclude = " AND (" . implode(" $excludeandor ", $searchcondexclude) . ")";
    } else {
        $searchcondexclude = "";
    }
    
    $courses = array();
    $c = 0; // counts how many visible courses we've seen

    // Tiki pagination
    $limitfrom = $page * $recordsperpage;
    $limitto   = $limitfrom + $recordsperpage;

    $ccselect = ', ' . context_helper::get_preload_record_columns_sql('ctx');
    $ccjoin = "LEFT JOIN {context} ctx ON (ctx.instanceid = c.id AND ctx.contextlevel = :contextlevel)";
    $params['contextlevel'] = CONTEXT_COURSE;    
        
    // Searching on teacher we keep deliberately simple - just search for exact matches, no other options
    // This also hard codes the like etc (see %teacher%) so not guaranteed to work on all DBs - FIXME if aiming to share
    $teacherjoin = '';
    $teachercond = '';
    if (!empty($searchteacher)) {
        $teacherjoin = "LEFT JOIN mdl_role_assignments ra ON (ctx.id = ra.contextid AND ra.roleid in (select id from mdl_role where archetype like '%teacher%')) LEFT JOIN mdl_user u ON (ra.userid = u.id) ";
        if (!empty($searchcondinclude)) {
            $teachercond .= " AND ";
        }
        $teachercond .= " u.lastname = '$searchteacher' ";
    }
    
    $sql = "SELECT c.* $ccselect
              FROM {course} c
           $ccjoin
           $teacherjoin
             WHERE $searchcondinclude $searchcondexclude $teachercond AND c.id <> ".SITEID."
          ORDER BY $sort";
    
    $rs = $DB->get_recordset_sql($sql, $params);
    foreach($rs as $course) {
        context_helper::preload_from_record($course);
        $coursecontext = context_course::instance($course->id);
        if ($course->visible || has_capability('moodle/course:viewhiddencourses', $coursecontext)) {
            // Don't exit this loop till the end
            // we need to count all the visible courses
            // to update $totalcount
            if ($c >= $limitfrom && $c < $limitto) {
                $courses[$course->id] = $course;
            }
            $c++;
        }
    }
    $rs->close();

    // our caller expects 2 bits of data - our return
    // array, and an updated $totalcount
    $totalcount = $c;
    return $courses;
}


