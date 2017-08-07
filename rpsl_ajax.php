<?php
require('rpsl_configuration.php');

// If no post has happened, don't do anything.
if ( !isset($_REQUEST) ) { die; }

$action = isset($_REQUEST['action']) ? $_REQUEST['action'] : '';

switch($action) {
	case "rpsl_check_login":             rpsl_check_login();			    break;
	case "rpsl_check_registered":        rpsl_check_registered();		    break;
    case "rpsl_check_self_registration": rpsl_check_self_registration();    break;
	case "rpsl_check_site_configured":   rpsl_check_site_configured();      break;
	default:							 die;
}

//*************************************************************************************
// Small helper function to determine if the input is valid for the session_id
// Only allowing numbers, letters and a -
//*************************************************************************************
function rpsl_valid_session_id($session) {
    
    $match = preg_match("/^[a-zA-Z0-9-]{1,}$/", $session);
    
    if($match === 1){
        return true;
    } 
    
    return false;
}

function rpsl_long_poll_loop($file_path){

	$poll_lifetime = 0;
	$authenticated = false;

	do {
		// Look for the file that gets set as a marker that authentication has occurred and the database updated.    
		sleep(RPSL_Configuration::$rpsl_long_poll_timeout);
		$authenticated = file_exists($file_path);
		$poll_lifetime += RPSL_Configuration::$rpsl_long_poll_timeout;
		clearstatcache();		
	// While we have a negative response, and the total connection lifetime from the sleeps is less than the long poll lifetime setting
	// Continue the sleep loop and only return a response after this timeout.
	} while(!$authenticated && $poll_lifetime < RPSL_Configuration::$rpsl_long_poll_lifetime);

    return $authenticated;
}

function rpsl_get_and_validate_sessionid()
{
    if(!isset($_REQUEST["rpsession"])) {
        return null;
    }

	$rpsl_sessionid = $_REQUEST["rpsession"];

    if(!rpsl_valid_session_id($rpsl_sessionid)) {
        return null;
    }

    return $rpsl_sessionid;
}


//*************************************************************************************
// Perform a pre-login check: is the LoginName is set for this session and QR code
// Use this from the Ajax poller in the login form
//*************************************************************************************
function rpsl_check_login () {
	
    $rpsl_sessionid = rpsl_get_and_validate_sessionid();
    if(empty($rpsl_sessionid)){
        $result = array("status"=>"error", "message"=>"Unable to validate session id.");
		die(json_encode($result));
    }

    $file_path = "temp/" . $rpsl_sessionid;
    $loginname = "waiting_for_auth";
    $authenticated = rpsl_long_poll_loop($file_path);
	
	if ($authenticated){ 
        $fp = fopen($file_path,"r");
        $loginname = fread($fp, filesize($file_path));
        fclose($fp);
        unlink($file_path);
    };

    $result = array("status"=>$loginname);
	die(json_encode($result));
}

//*************************************************************************************
// This Ajax function checks whether the session has been used to register 
// a credential yet. Needed to be able to refresh the screen.
//*************************************************************************************
function rpsl_check_registered () {

    $rpsl_sessionid = rpsl_get_and_validate_sessionid();

    if(empty($rpsl_sessionid)){
        $result = array("status"=>"error", "message"=>"Unable to validate session id.");
		die(json_encode($result));
    }

	$file_path = "temp/" . $rpsl_sessionid;
    $register_status = "Waiting for credential collection";
    $authenticated = rpsl_long_poll_loop($file_path);
		
    if($authenticated) { 
        $register_status = "ok"; 
        unlink($file_path);
    }

    $result = array("status"=>$register_status);
	die(json_encode($result));
}


//*************************************************************************************
// This Ajax function checks the database for supplied user information
// Returns the UUID and UserData contents for this session from the Sessions table if set
//*************************************************************************************
function rpsl_check_self_registration () {
	
    $rpsl_sessionid = rpsl_get_and_validate_sessionid();

    if(empty($rpsl_sessionid)){
        $result = array("status"=>"error", "message"=>"Unable to validate session id.");
		die(json_encode($result));
    }

	$file_path = "temp/" . $rpsl_sessionid;
    $authenticated = rpsl_long_poll_loop($file_path);
		
	if (!$authenticated) { 
        $result = array("status"=>"100","message"=>"Awaiting user registration" );
		die(json_encode($result));
    }

    unlink($file_path);
    $result = array("status"=>"200");
	die(json_encode($result));
}

//*************************************************************************************
// This Ajax function checks whether the site has been configured
// If so refresh the logo on screen.
//*************************************************************************************
function rpsl_check_site_configured () {

    $rpsl_sessionid = rpsl_get_and_validate_sessionid();

    if(empty($rpsl_sessionid)){
        $result = array("status"=>"error", "message"=>"Unable to validate session id.");
		die(json_encode($result));
    }

	$file_path = "temp/" . $rpsl_sessionid;
    $configure_status = "Waiting for site registration";
    $authenticated = rpsl_long_poll_loop($file_path);
		
    if($authenticated) { 
        $configure_status = "ok";
        unlink($file_path);
    }

    $result = array("status"=>$configure_status);
	die(json_encode($result));
}
?>