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
 * Displays Advanced search for courses, adapted (heavily) from course/search.php, 
 * with lots of things removed (chiefly around editability) and some extra functionality added (sorting, extra searching)
 *
 * @package    coursesearch
 * @category   local
 * @copyright  2015, Oxford Brookes University {@link http://www.brookes.ac.uk/}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once("../../config.php");
require_once("dbquery.php");
require_once($CFG->dirroot.'/course/lib.php');
require_once($CFG->libdir . '/coursecatlib.php');

$searchinclude    = optional_param('searchinclude', '', PARAM_RAW);  // search words to include
$searchexclude    = optional_param('searchexclude', '', PARAM_RAW);  // search words to exclude
$searchteacher    = optional_param('searchteacher', '', PARAM_RAW);  // optional last name of teacher/module leadet etc
$exactinclude    = optional_param('exactinclude', '', PARAM_RAW);  // whether to do exact match (rather than like) on include terms (default no)
$exactexclude    = optional_param('exactexclude', '', PARAM_RAW);  // whether to do exact match (rather than like) on exclude terms (default no)
$page      = optional_param('page', 0, PARAM_INT);     // which page to show
$perpage   = optional_param('perpage', 50, PARAM_INT); // how many per page (default 3 for testing, else 50)
$includeandor = optional_param('includeandor', 'AND', PARAM_RAW);  // whether to do AND or OR search on the includes
$excludeandor = optional_param('excludeandor', 'AND', PARAM_RAW);  // whether to do AND or OR search on the excludes
$sortby    = optional_param('sortby', 'fullname ASC', PARAM_RAW); // how to sort the results
$displayformat  = optional_param('displayformat', 0, PARAM_INT); // 0 = short format, 1 = full format
$searchfield_summary    = optional_param('searchfield_summary', '', PARAM_RAW); // whether to search in the summary (default no)


$searchinclude = trim(strip_tags($searchinclude)); // trim & clean raw searched string
$searchexclude = trim(strip_tags($searchexclude)); // trim & clean raw searched string
$searchteacher = trim(strip_tags($searchteacher)); // trim & clean raw searched string
$searchinclude = str_replace(",", " ", $searchinclude);
$searchexclude = str_replace(",", " ", $searchexclude);
$searchtermsinclude = '';
$searchtermsexclude = '';

if ($searchinclude) {
    $searchtermsinclude = explode(" ", $searchinclude);    // Search for words independently
    foreach ($searchtermsinclude as $key => $searchterm) {
        if (strlen($searchterm) < 2) {
            unset($searchtermsinclude[$key]);
        }
    }
    $searchinclude = trim(implode(" ", $searchtermsinclude));
}

if ($searchexclude) {
    $searchtermsexclude = explode(" ", $searchexclude);    // Search for words independently
    foreach ($searchtermsexclude as $key => $searchterm) {
        if (strlen($searchterm) < 2) {
            unset($searchtermsexclude[$key]);
        }
    }
    $searchexclude = trim(implode(" ", $searchtermsexclude));
}

$site = get_site();

$urlparams = array();
foreach (array('searchinclude', 'searchexclude', 'searchteacher', 'exactinclude', 'includeandor', 'excludeandor', 'exactexclude', 'page', 'perpage', 'sortby', 'displayformat', 'searchfield_summary') as $param) {
    if (!empty($$param)) {
        $urlparams[$param] = $$param;
    }
}

$PAGE->set_url('/local/coursesearch/coursesearch.php', $urlparams);

$PAGE->set_context(context_system::instance());
$PAGE->set_pagelayout('standard');

if ($CFG->forcelogin) {
    require_login();
}


// make_categories_list($displaylist, $parentlist);
$displaylist =  coursecat::make_categories_list();

$strsearch = new lang_string("search");
$strsearchresults = new lang_string("searchresults");
$strcategory = new lang_string("category");
$strselect   = new lang_string("select");
$strselectall = new lang_string("selectall");
$strdeselectall = new lang_string("deselectall");
$stredit = new lang_string("edit");
$strfrontpage = new lang_string('frontpage', 'admin');
$strnovalidcourses = new lang_string('novalidcourses');

if (empty($searchinclude) && empty($searchteacher)) {
    $PAGE->set_title("$site->fullname : $strsearch");
    $PAGE->set_heading($site->fullname);

    echo $OUTPUT->header();
    echo $OUTPUT->box_start();
    // echo "<center>";
    echo "<br />";
    echo print_advanced_course_search("", "", "", "", "", "", "", "", "");
    echo "<br /><p>";
    echo get_string('searchhelp', 'local_coursesearch');
    echo "</p>";
    // echo "</center>";
    echo $OUTPUT->box_end();
    echo $OUTPUT->footer();
    exit;
}

$courses = array();

if (!empty($searchterm) || !empty($searchteacher)) {
       
    // Do not do search for empty search request.
    $courses = do_advanced_course_search($searchtermsinclude, $searchtermsexclude, $searchteacher, $sortby, $page, $perpage, $totalcount, $exactinclude, $exactexclude, $includeandor, $excludeandor, $searchfield_summary);
}

if (!empty($searchinclude)) {
    $PAGE->navbar->add(s($searchinclude));
}
$PAGE->set_title("$site->fullname : $strsearchresults");
$PAGE->set_heading($site->fullname);

echo $OUTPUT->header();

$lastcategory = -1;
if ($courses) {
            
    echo print_advanced_course_search($searchinclude, $searchexclude, $searchteacher, $sortby, $searchfield_summary, $exactinclude, $exactexclude, $includeandor, $excludeandor, $displayformat, $perpage);
    echo "<br /><br />";
    
    echo "<strong>$strsearchresults: $totalcount</strong><br><br>";
    
    print_navigation_bar($totalcount, $page, $perpage, $urlparams);

    // Show list of courses
    if (1) { // No editing mode in this version
        foreach ($courses as $course) {
            // front page don't belong to any category and block can exist.
            if ($course->category > 0) {
                $course->category_summary .= "<br /><p class=\"category\">";
                $course->category_summary .= "$strcategory: <a href=\"/course/category.php?id=$course->category\">";
                $course->category_summary .= $displaylist[$course->category];
                $course->category_summary .= "</a></p>";
            }
            print_course_search_result($course, $displayformat, $searchinclude);
            echo $OUTPUT->spacer(array('height'=>5, 'width'=>5, 'br'=>true)); // should be done with CSS instead
        }
    }

    print_navigation_bar($totalcount, $page, $perpage, $urlparams);

} else {
    if (!empty($searchinclude)) {
        echo $OUTPUT->heading(get_string("nocoursesfound",'', s($searchinclude)));
        echo "<br /><br />";        
        echo print_advanced_course_search($searchinclude, $searchexclude, $searchteacher, $sortby, $searchfield_summary, $exactinclude, $exactexclude, $includeandor, $excludeandor, $displayformat, $perpage);
    }
    else {
        echo $OUTPUT->heading($strnovalidcourses);
        echo "<br /><br />";
        echo print_advanced_course_search($searchinclude, $searchexclude, $searchteacher, $sortby, $searchfield_summary, $exactinclude, $exactexclude, $includeandor, $excludeandor, $displayformat, $perpage);
    }
}

echo "<br /><br />";

echo $OUTPUT->footer();

/**
 * Print a list navigation bar
 * Display page numbers, and a link for displaying all entries
 * @param int $totalcount number of entry to display
 * @param int $page page number
 * @param int $perpage number of results per page
 * @param string $encodedsearch
 * @param string $sortby sorting 
 */

function print_navigation_bar($totalcount, $page, $perpage, $params) {
    global $OUTPUT;
    
    echo $OUTPUT->paging_bar($totalcount, $page, $perpage, "/local/coursesearch/coursesearch.php?".build_param_string($params));
    
    // display
    if ($perpage != 99999 && $totalcount > $perpage) {
        echo "<center><p>";
        $params['page'] = '0'; 
        $params['perpage'] = '9999'; 
        echo "<a href=\"/local/coursesearch/coursesearch.php?" . build_param_string($params) . "\">".get_string("showall", "", $totalcount)."</a>";
        echo "</p></center>";
    } else if ($perpage === 99999) {
        $defaultperpage = 10;
        echo "<center><p>";
        $params['perpage'] = $defaultperpage;
        echo "<a href=\"/local/coursesearch/coursesearch.php?" . build_param_string($params) . "\">".get_string("showperpage", "", $defaultperpage)."</a>";
        echo "</p></center>";
    }
}

/** Print the search form
 * 
 * @global type $CFG
 * @staticvar int $count
 * @param type $value
 * @param type $return
 * @param type $format
 * @return string
 */

function print_advanced_course_search($valueinclude="", $valueexclude="", $valueteacher="", $sortby="", $searchfield_summary="", $exactinclude="", $exactexclude="", $incandor="", $excandor="", $displayformat=0, $perpage=10) {
    global $CFG, $OUTPUT;
    static $count = 0;

    $count++;
    
    $id = 'advcoursesearch';

    if ($count > 1) {
        $id .= $count;
    }

    $strsearchcourses= get_string("searchcourses");

    echo $OUTPUT->heading(get_string('title', 'local_coursesearch'));
    
    $output = get_string('searchrefine', 'local_coursesearch');
    $output .= '<br>';
        
    $output .= '<form id="'.$id.'" action="'.$CFG->wwwroot.'/local/coursesearch/coursesearch.php" method="post">';
    $output .= '<fieldset class="coursesearchbox invisiblefieldset">';
    
    $output .= "<div style=\"line-height: 2.0;\">";
    
    $output .= '<label for="searchfields">'.(get_string('searchfields', 'local_coursesearch')).': </label>';
    $output .= '<input type="checkbox" id="searchfield_summary" name="searchfield_summary" ' . ($searchfield_summary ? "checked" : "") . ' />';
    $output .= '<br>';
    
    $output .= '<label for="searchinclude">'.(get_string('searchinclude', 'local_coursesearch')).': </label>';
    $output .= '<input type="text" id="searchinclude" size="30" name="searchinclude" value="'.s($valueinclude).'" />';
    $output .= '<select id="includeandor" size="1" name="includeandor">';
    $output .= ' <option value="AND" ' . ($incandor == "AND" ? 'selected' : '') . '>AND';
    $output .= ' <option value="OR" ' . ($incandor == "OR" ? 'selected' : '') . '>OR';
    $output .= '</select>';
    $output .= ' ' . get_string('exact', 'local_coursesearch') . '<input type="checkbox" id="exactinclude" name="exactinclude" ' . ($exactinclude ? "checked" : "") . ' />';
    $output .= '<br>';
    
    $output .= '<label for="searchinclude">'.(get_string('searchexclude', 'local_coursesearch')).': </label>';
    $output .= '<input type="text" id="searchexclude" size="30" name="searchexclude" value="'.s($valueexclude).'" />';
    $output .= '<select id="excludeandor" size="1" name="excludeandor">';
    $output .= ' <option value="AND" ' . ($excandor == "AND" ? 'selected' : '') . '>AND';
    $output .= ' <option value="OR" ' . ($excandor == "OR" ? 'selected' : '') . '>OR';
    $output .= '</select>';
    $output .= ' ' . get_string('exact', 'local_coursesearch') . '<input type="checkbox" id="exactexclude" name="exactexclude" ' . ($exactexclude ? "checked" : "") . ' />';
    $output .= '<br>';
      
    $output .= '<label for="searchteacher">'.(get_string('searchteacher', 'local_coursesearch')).': </label>';
    $output .= '<input type="text" id="searchteacher" size="30" name="searchteacher" value="'.s($valueteacher).'" />';
    $output .= '<br>';
    
    $output .= '<fieldset class="coursesearchbox invisiblefieldset">';
    $output .= '<label for="sortby">' . get_string('sortby', 'local_coursesearch') . ':</label>';
    $output .= '<select id="sortby" size="1" name="sortby">';
    $output .= ' <option value="fullname ASC" ' . ($sortby == "fullname ASC" ? 'selected' : '') . '>Code / full name (A-Z)';
    $output .= ' <option value="fullname DESC" ' . ($sortby == "fullname DESC" ? 'selected' : '') . '>Code / full name (Z-A)';
    $output .= ' <option value="startdate ASC" ' . ($sortby == "startdate ASC" ? 'selected' : '') . '>Start date (old-new)';
    $output .= ' <option value="startdate DESC" ' . ($sortby == "startdate DESC" ? 'selected' : '') . '>Start date (new-old)';
    $output .= ' <option value="timecreated ASC" ' . ($sortby == "timecreated ASC" ? 'selected' : '') . '>Creation date (old-new)';
    $output .= ' <option value="timecreated DESC" ' . ($sortby == "timecreated DESC" ? 'selected' : '') . '>Creation date (new-old)';
    $output .= ' <option value="timemodified ASC" ' . ($sortby == "timemodified ASC" ? 'selected' : '') . '>Modified date (old-new)';
    $output .= ' <option value="timemodified DESC" ' . ($sortby == "timemodified DESC" ? 'selected' : '') . '>Modified date (new-old)';
    $output .= '</select>';
    $output .= '<br>';
    
    $output .= '<fieldset class="coursesearchbox invisiblefieldset">';
    $output .= '<label for="displayformat">' . get_string('displayformat', 'local_coursesearch') . ':</label>';
    $output .= '<select id="displayformat" size="1" name="displayformat">';
    $output .= ' <option value="0" ' . ($displayformat == 0 ? 'selected' : '') . '>Short format';
    $output .= ' <option value="1" ' . ($displayformat == 1 ? 'selected' : '') . '>Full details';
    $output .= '</select>';    
    $output .= '<br>';

    $output .= '<fieldset class="coursesearchbox invisiblefieldset">';
    $output .= '<label for="perpage">' . get_string('resultsperpage', 'local_coursesearch') . ':</label>';
    $output .= '<select id="perpage" size="1" name="perpage">';
    $output .= ' <option value="10" ' . ($perpage == 10 ? 'selected' : '') . '>10';
    $output .= ' <option value="20" ' . ($perpage == 20 ? 'selected' : '') . '>20';
    $output .= ' <option value="30" ' . ($perpage == 30 ? 'selected' : '') . '>30';
    $output .= ' <option value="50" ' . ($perpage == 50 ? 'selected' : '') . '>50';
    $output .= '</select>';    
    $output .= '<br>';    
    
    $output .= '<input type="submit" value="'.get_string('performsearch', 'local_coursesearch').'" />';
    
    $output .= "</div>";
    
    $output .= '</fieldset></form>';

    return $output;
    
}


/** Build & separated params from param array (probably already exists somewhere)
 * 
 * @param type $params
 * @return string
 */

function build_param_string($params) {
   
    $result = "";
    foreach ($params as $key => $value) {
       if ($result) {
           $result .= "&";
       }
       $result .= $key . "=" . urlencode($value);  
    }
    return $result;
}



/** Borrowed from moodle/lib.php and modified here so that we can customise which parts of the course to show and make other small modifications
 * Print a description of a course, suitable for browsing in a list.
 *
 * @param object $course the course object.
 * @param string $highlightterms (optional) some search terms that should be highlighted in the display.
 */

function print_course_search_result($course, $displayformat=0, $highlightterms = '') {
    global $CFG, $USER, $DB, $OUTPUT;
    
    $context = context_course::instance($course->id);

    // Rewrite file URLs so that they are correct
    $course->summary = file_rewrite_pluginfile_urls($course->summary, 'pluginfile.php', $context->id, 'course', 'summary', NULL);

    echo html_writer::start_tag('div', array('class'=>'coursebox clearfix'));
    echo html_writer::start_tag('div', array('class'=>'info'));
    echo html_writer::start_tag('h3', array('class'=>'name'));

    $linkhref = new moodle_url('/course/view.php', array('id'=>$course->id));

    $coursename = get_course_display_name_for_list($course);
    $linktext = highlight($highlightterms, format_string($coursename));
    $linkparams = array('title'=>get_string('entercourse'));
    if (empty($course->visible)) {
        $linkparams['class'] = 'dimmed';
    }
    echo html_writer::link($linkhref, $linktext, $linkparams);
    echo html_writer::end_tag('h3');

    /// first find all roles that are supposed to be displayed
    if (!empty($CFG->coursecontact)) {
        $managerroles = explode(',', $CFG->coursecontact);
        $rusers = array();

        if (!isset($course->managers)) {
            list($sort, $sortparams) = users_order_by_sql('u');
            $rusers = get_role_users($managerroles, $context, true,
                'ra.id AS raid, u.id, u.username, u.firstname, u.lastname, rn.name AS rolecoursealias,
                 r.name AS rolename, r.sortorder, r.id AS roleid, r.shortname AS roleshortname',
                'r.sortorder ASC, ' . $sort, null, '', '', '', '', $sortparams);
        } else {
            //  use the managers array if we have it for perf reasosn
            //  populate the datastructure like output of get_role_users();
            foreach ($course->managers as $manager) {
                $user = clone($manager->user);
                $user->roleid = $manager->roleid;
                $user->rolename = $manager->rolename;
                $user->roleshortname = $manager->roleshortname;
                $user->rolecoursealias = $manager->rolecoursealias;
                $rusers[$user->id] = $user;
            }
        }

        if ($displayformat) {

            $namesarray = array();
            $canviewfullnames = has_capability('moodle/site:viewfullnames', $context);
            foreach ($rusers as $ra) {
                if (isset($namesarray[$ra->id])) {
                    //  only display a user once with the higest sortorder role
                    continue;
                }

                $role = new stdClass();
                $role->id = $ra->roleid;
                $role->name = $ra->rolename;
                $role->shortname = $ra->roleshortname;
                $role->coursealias = $ra->rolecoursealias;
                $rolename = role_get_name($role, $context, ROLENAME_ALIAS);

                // Thisno longer usable in 2.6 without adding a bunch of pointless cruft
                // so just DIY
                // $fullname = fullname($ra, $canviewfullnames);
                $fullname = $ra->firstname . ' ' . $ra->lastname;
                $namesarray[$ra->id] = $rolename.': '.
                    html_writer::link(new moodle_url('/user/view.php', array('id'=>$ra->id, 'course'=>SITEID)), $fullname);
            }

            if (!empty($namesarray)) {
                echo html_writer::start_tag('ul', array('class'=>'teachers'));
                foreach ($namesarray as $name) {
                    echo html_writer::tag('li', $name);
                }
                echo html_writer::end_tag('ul');
            }
        }
    }
    echo html_writer::end_tag('div'); // End of info div
    
    echo html_writer::start_tag('div', array('class'=>'summary'));
    $options = new stdClass();
    $options->noclean = true;
    $options->para = false;
    $options->overflowdiv = true;
    if (!isset($course->summaryformat)) {
        $course->summaryformat = FORMAT_MOODLE;
    }
    echo $course->category_summary;
    if ($displayformat) {
        echo highlight($highlightterms, format_text($course->summary, $course->summaryformat, $options,  $course->id));
        if ($icons = enrol_get_course_info_icons($course)) {
            echo html_writer::start_tag('div', array('class'=>'enrolmenticons'));
            foreach ($icons as $icon) {
                $icon->attributes["alt"] .= ": ". format_string($coursename, true, array('context'=>$context));
                echo $OUTPUT->render($icon);
            }
            echo html_writer::end_tag('div'); // End of enrolmenticons div
        }
    }
    
    echo html_writer::end_tag('div'); // End of summary div
    
    echo html_writer::end_tag('div'); // End of coursebox div
}

