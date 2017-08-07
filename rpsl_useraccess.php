<?php
//*************************************************************************************
//*************************************************************************************
//   USER ACCESS
//*************************************************************************************
//*************************************************************************************

function rpsl_revoke_device(){
//
// Gets a request to revoke a user access to the forum in a PKCS7 post signed by a trusted user account
//
// Minimum expected data is:
//   userinfo->uuid

	$data =  rpsl_verify_request();
	
	$userinfo = $data->userinfo;
	$uuid     = $userinfo->uuid;

	// Check whether the user already exists - either UUID or email
	$user = rpsl_get_user_by_uuid($uuid);
	
	if (!is_object($user)) {
		echo "An account with this UUID does not exist " . $uuid;
		die;
	}
	
	try	{
		// need to remove the device row.
		rpsl_delete_device($uuid);
		
	} catch (Exception $e) {
		echo "Exception: ", $e->getMessage() ; die;
	}
	
	die; // Always die in functions echoing Ajax content
}

function rpsl_verify_request(){
	
	$posted = rpsl_rapid_verify();
	if ($posted['status'] != 0) {
		echo "Error {$posted['status']} - unable to process request<br/>";
		echo $posted['message'];
		die(0);
	}
	
	// All ok - get the data
	$dataJson = $posted['data'];
	$authuuid = $posted['uuid'];  // This is the authorising UUID - we could check this relates to a specific WP admin account or role
	
	$authuser = rpsl_get_user_by_uuid($authuuid);  
	if (!is_object($authuser)) {
		echo "The requestor does not have rights to manage accounts " . $authuuid;
		die;
	}
	if (!in_array('administrator', (array) $authuser->roles)){
		echo "The requestor does not have rights to create an account " . $authuser->user_login;
		die;
	}
	
	// All good.  Now get the supplied user details for the account we need to create
	$data     = json_decode($dataJson);

	return $data;
}