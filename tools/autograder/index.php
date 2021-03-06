<?php
require_once "../config.php";

use \Tsugi\Core\Settings;
use \Tsugi\Core\LTIX;
use \Tsugi\UI\SettingsForm;
use \Tsugi\UI\Lessons;

$LAUNCH = LTIX::requireData();
$p = $CFG->dbprefix;

if ( SettingsForm::handleSettingsPost() ) {
    header( 'Location: '.addSession('index.php') ) ;
    return;
}

// All the assignments we support
$assignments = array(
    'http_headers.php' => 'Exploring HTTP Headers',
    'howdy.php' => 'Howdy application', 
    'guess.php' => 'Guessing Game',
    'rps.php' => 'Rock, Paper, Scissors',
    'autosdb.php' => 'Autos PDO',
    'autosess.php' => 'Autos Post-Redirect',
    'autoscrud.php' => 'Autos CRUD',
    'res-profile.php' => 'Profiles Database',
    'res-position.php' => 'Profiles+Postions Database',
    'res-education.php' => 'Prof+Pos+Educ Database',
    // 'practical-f16-pizza' => 'Fall 16 Pizza Exam',
    'practical-sample.php' => 'Sample Practical Exam'
);

$oldsettings = Settings::linkGetAll();

$assn = Settings::linkGet('exercise');
$custom = LTIX::ltiCustomGet('exercise');
if ( $assn && isset($assignments[$assn]) ) {
    // Configured
} else if ( strlen($custom) > 0 && isset($assignments[$custom]) ) {
    Settings::linkSet('exercise', $custom);
    $assn = $custom;
}


if ( $assn === false && isset($_GET["inherit"]) && isset($CFG->lessons) ) {
    $l = new Lessons($CFG->lessons);
    if ( $l ) {
        $lti = $l->getLtiByRlid($_GET['inherit']);
        if ( isset($lti->custom) ) foreach($lti->custom as $custom ) {
            if (isset($custom->key) && isset($custom->value) && $custom->key == 'exercise' ) {
                $assn = $custom->value;
                Settings::linkSet('exercise', $assn);
            }
        }
    }
}

// Get any due date information
$dueDate = SettingsForm::getDueDate();
// Let the assignment handle the POST
if ( count($_POST) > 0 && $assn && isset($assignments[$assn]) ) {
    require($assn);
    return;
}

// View
$OUTPUT->header();
$OUTPUT->bodyStart();
$OUTPUT->topNav();

// Settings button and dialog

echo('<div style="float: right;">');
if ( $USER->instructor ) {
    echo('<a href="grades.php" target="_blank"><button class="btn btn-info">Grade detail</button></a> '."\n");
}
SettingsForm::button();
echo('</div>');

SettingsForm::start();
SettingsForm::select("exercise", __('Please select an assignment'),$assignments);
SettingsForm::dueDate();
SettingsForm::done();
SettingsForm::end();

$OUTPUT->flashMessages();

$code = $USER->id+$LINK->id+$CONTEXT->id;
if ( $USER->displayname === false || $USER->displayname == '' ) {
    echo('<p style="color:blue;">Auto grader launched without a student name.</p>'.PHP_EOL);
} else {
    $OUTPUT->welcomeUserCourse();
}

$ALL_GOOD = false;

function my_error_handler($errno , $errstr, $errfile, $errline , $trace = false)
{
    global $OUTPUT, $ALL_GOOD;
    error_out("The autograder did not find something it was looking for in your HTML - test ended.");
    error_out("Usually the problem is in one of the pages returned from your application.");
    error_out("Use the 'Toggle' links above to see the pages returned by your application.");
    $message = $errfile."@".$errline." ".$errstr;
    error_log($message);
    if ( $trace ) error_log($trace);
    $detail = 
        "Check the most recently retrieved page (above) and see why the autograder is uphappy.\n" .
        "\nHere is some internal detail where the autograder was unable to continue.\n".
        'Caught exception: '.$message."\n".$trace."\n";
    showHTML("Internal error detail.",$detail);
    $OUTPUT->footer();
    $ALL_GOOD = true;
}

function fatalHandler() {
    global $ALL_GOOD, $OUTPUT;
    if ( $ALL_GOOD ) return;
    $error = error_get_last();
    error_out("Fatal error handler triggered");
    if($error) {
        my_error_handler($error["type"], $error["message"], $error["file"], $error["line"]);
    } else {
        $OUTPUT->footer();
    }
    exit();
}
register_shutdown_function("fatalHandler");

// Assume try / catch is in the script
if ( $assn && isset($assignments[$assn]) ) {
    include($assn);
} else {
    if ( $USER->instructor ) {
        echo("<p>Please use settings to select an assignment for this tool.</p>\n");
    } else {
        echo("<p>This tool needs to be configured - please see your instructor.</p>\n");
    }
}

$ALL_GOOD = true;

$OUTPUT->footer();


