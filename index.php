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

require(__DIR__ . '/../../config.php');

// cek apakah user sudah login
require_login();

// cek parameter course id pada URL
$courseid = required_param('id', PARAM_INT);

// get data course berdasarkan parameter course id dan user id
$retake = new \local_retake\Retake($courseid);
$course = $DB->get_record('course', array('id' => $courseid), '*', MUST_EXIST);
$context = context_course::instance($course->id);
$url = new moodle_url('/local/retake/index.php', array('id'=>$course->id));
$courseUrl = new moodle_url('/course/view.php', array('id' => $courseid));

// cek apakah retake enable
$globallyEnable = get_config('local_retake')->enableonallcourse;
$locallyEnable = $retake->isEnabled();

if(!$globallyEnable && !$locallyEnable) {
    // add notification
    \core\notification::success(get_string('disabled_message', 'local_retake', $course));

    // redirect ke course
    redirect($courseUrl);
}

if(!$retake->isAllowedToRetake($USER->id)){
    // add notification
    \core\notification::error(get_string('max_retake_error', 'local_retake', $course));

    // redirect ke course
    redirect($courseUrl);
}

// set page content
$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_course($course);
$PAGE->set_pagelayout('standard');
$PAGE->set_title('Retake Course');
$PAGE->set_heading("Retake Course $course->fullname");

// jika request method POST, lakukan penghapusan di sini, lalu redirect ke halaman course

if(isset($_POST['confirm_retake'])){
    require_once($CFG->dirroot. '/course/lib.php');
    require_once($CFG->dirroot. '/lib/enrollib.php');

    \local_retake\Cleanser::removeActivityCompletion($courseid, $USER->id);
    \local_retake\Cleanser::removeScormData($courseid, $USER->id);
    \local_retake\Cleanser::removeH5PActivityAndAttempts($courseid, $USER->id);
    \local_retake\Cleanser::removeUserEnrollment($courseid, $USER->id);
    \local_retake\Cleanser::removeUserGrades($courseid, $USER->id);
    \local_retake\Cleanser::removeUserGradesHistory($courseid, $USER->id);
    \local_retake\Cleanser::removeUserCourseCompletion($courseid, $USER->id);
    \local_retake\Cleanser::removeUserCourseCompletionCriteria($courseid, $USER->id);
    \local_retake\Cleanser::removeUserIssuedBadges($courseid, $USER->id);

    // unenroll user
    $instances = $DB->get_records('enrol', array('courseid' => $courseid));
    foreach ($instances as $instance) {
        $plugin = enrol_get_plugin($instance->enrol);
        $plugin->unenrol_user($instance, $USER->id);
    }

    // remove course completion cache
    \local_retake\Cleanser::removeCompletionCache($courseid, $USER->id);

    // add notification
    \core\notification::success(get_string('success_message', 'local_retake', $course));

    // redirect ke course
    redirect($courseUrl);
}


// display content
echo $OUTPUT->header();
echo "Globally Enable " . $globallyEnable . '<br>';
echo "Locally Enable " . $locallyEnable . '<br>';
echo "Allowed Retake " . (int) $retake->isAllowedToRetake($USER->id) . '<br>';

// prepare confirmation message
$confirmMessage = $OUTPUT->render_from_template(
    'local_retake/message', array(
        "warning" => "Test Warning",
        "warning_detail" => get_string('warning_detail', 'local_retake'),
        "confirmation" => get_string('confirmation', 'local_retake')
    )
);
$yesUrl = new moodle_url('/local/retake/index.php', ['id' => $courseid, 'confirm_retake' => 'true']);
$noUrl = new moodle_url('/course/view.php', ['id' => $courseid]);

echo $OUTPUT->confirm($confirmMessage, $yesUrl, $noUrl);
echo $OUTPUT->footer();
