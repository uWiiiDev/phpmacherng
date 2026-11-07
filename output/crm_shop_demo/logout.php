<?php
/**
 * PHPMacherNG Logout Page (logout.php)
 */
require_once dirname(__FILE__) . '/phpfn14.php';
$Security = ew_Security();
$canLogout = true;
if (function_exists('User_LoggingOut')) {
    $canLogout = (bool)User_LoggingOut($Security->CurrentUserName);
}
if ($canLogout) {
    $Security->Logout();
    ew_SetSuccessMessage('Sie wurden erfolgreich abgemeldet.');
    ew_Redirect('login.php');
} else {
    ew_Redirect('index.php');
}