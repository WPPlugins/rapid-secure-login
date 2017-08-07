<?php 
//*************************************************************************************
//*************************************************************************************
// Security, QR Data and Polling Methods for WordPress Rapid Secure Login
//*************************************************************************************
//*************************************************************************************

//*************************************************************************************
// Check the POST, validate the signed data and return status, UUID and payload
//*************************************************************************************
function rpsl_rapid_verify () {

	global $rpsl_trace_certs;

	// Get some filenames for the signing cert and contents
	$tempfolder = rpsl_temp_folder();
	$pkcs7file  = tempnam($tempfolder, "pk7");
	$certfile   = tempnam($tempfolder, "sig");
	$contfile   = tempnam($tempfolder, "con");
	$issuerfile = tempnam($tempfolder, "iss");
	$untrusted = rpsl_cert_folder() . "/untrusted.pem";        // Any other root cert that won't be matched
	
	try {
	
		$data     = file_get_contents("php://input");         //DIAG	echo "Data is: $data";
		$dataJson = json_decode($data);                       //DIAG	print_r($dataJson);
		$pkcs7    = "";				                          //DIAG	echo "Extracted P7 is: $pkcs7";
		$phone    = "";
		if(property_exists($dataJson, "phone")) {$phone = sanitize_text_field($dataJson->phone);}
		if(property_exists($dataJson, "pkcs7")) {$pkcs7 = $dataJson->pkcs7;}

		// Add the S/MIME Header and save the signed p7 data to file for OpenSSL to read
		$p7handle   = fopen($pkcs7file, "w");
		$mimeheader = "MIME-Version: 1.0\r\n" . "Content-Type: application/pkcs7-mime; smime-type=signed-data;\r\n name=smime.p7m\r\n" . "Content-Transfer-Encoding: base64\r\n\r\n";
		fwrite($p7handle, $mimeheader);
		fwrite($p7handle, $pkcs7);
		fclose($p7handle);

		// turn certificates into a singular pem file
		$issuer_certs = rpsl_db_get_active_issuer_certificates();

		if(count($issuer_certs) <= 0) {
			throw new \Exception("No issuer certs found in the database to be used for verification.");
		}

		$issuer_handle   = fopen($issuerfile, "w");
		foreach($issuer_certs as $cert) {
			fwrite($issuer_handle, $cert->public_key);
			fwrite($issuer_handle, PHP_EOL);
		}	
		fclose($issuer_handle);

		// PKCS7 flags 
		// Do not chain verification of signers certificates: 
		// that is don't use the certificates in the signed message as untrusted CAs.
		$flags = PKCS7_NOCHAIN;
		
		// Now verify and parse the data, If the json pkcs7 written to the file is not a pkcs7 data packet
		// the openssl libary will fail to parse and verify it. If it is a valid packet but signed with
		// incorrect certificates openssl will also fail to parse and verify it.
		// pkcs7 file is the packet to verify 
		// certfile is the certificate used to sign the p7
		// caroot cert is the trusted ca to use in the verification process
		// untrusted is a blank pem 
		// contfile is the filepath which contains the verified content of the p7
		$result = openssl_pkcs7_verify($pkcs7file, $flags, $certfile, array($issuerfile), $untrusted , $contfile);
		
		$content_handle = fopen($contfile, "r");
		$data = fread($content_handle, filesize($contfile));
		fclose($content_handle);	
		rpsl_log_openssl_error();
		
		$signcert = file_get_contents($certfile);
		$certinfo = openssl_x509_parse($signcert);
		$uuid     = substr($certinfo["name"],4);   // Strip off the "/"

		rpsl_set_device_last_used($uuid);
		
		//DIAG 
		$certAsText = print_r($certinfo, true);
		if ($rpsl_trace_certs) rpsl_trace("Certificate: \n $certAsText"); 
		if ($rpsl_trace_certs) rpsl_trace("Raw cert is: \n $signcert"); 
		rpsl_trace("Data: $data");
		
		// Clean up the temporary files
		clean_up_temp_file($certfile);
		clean_up_temp_file($contfile);
		clean_up_temp_file($pkcs7file);
		clean_up_temp_file($issuerfile);
		
		// If all is ok, pass back raw post data in result array for calling method to use as they see fit.
		// otherwise return an error array.
		$result = array (
					'status'    => 0,
					'message'   => 'ok',
					'uuid'      => $uuid,
					'data'      => $data,
					'phone'     => $phone,
						);
	} catch(Exception $e) {
	
		rpsl_trace("Verify Error: " . $e->getMessage());
		
		// Clean up the temporary files
		clean_up_temp_file($certfile);
		clean_up_temp_file($contfile);
		clean_up_temp_file($pkcs7file);
		clean_up_temp_file($issuerfile);

		$result = array (
					'status'    => 100,
					'message'   => 'The RapID trusted issuing CA certificate is missing. Please check the WordPress RapID Settings',
					'uuid'      => '',
					'data'      => '',
					'phone'     => '',
						);
	}

	return $result;
}

function clean_up_temp_file($tempfile_path) {
	
	if(!empty($tempfile_path) && file_exists($tempfile_path)) {
		unlink($tempfile_path);
	}
}

/*************************************************************
* function to create a token file to trigger the ajax polling.
*************************************************************/
function rpsl_create_session_token_file($session, $content){
		
		//Write a temporary file for the polling script.
		$tempfolder = rpsl_temp_folder();
		rpsl_delete_old_files($tempfolder);  // Housekeeping
		
		$file = $tempfolder . "/" . $session;		
		rpsl_trace("File to be written: $file");
		
		$fp = fopen($file, "w");
		fwrite($fp, $content);
		fclose($fp);
}

function rpsl_cleanup_expired_session_rows() {

	global $wpdb;
	$table_name = $wpdb->prefix . "rpsl_sessions";

	// Delete existing entries in the session table for this session 
	$cleanup = "SELECT * FROM " . $table_name . " WHERE requested < (UTC_TIMESTAMP() - INTERVAL 20 MINUTE)";
	$expired = $wpdb->get_results( $cleanup );
	// For each of the expired actions, we need to work out whether there is an orphaned credential
	// That we need to query for.
	
	$rows_to_delete = array();
	foreach($expired as $expired_row) {
		if(	!isset($expired_row->uuid) || 
			!isset($expired_row->loginname) ||
			(isset($expired_row->action) && $expired_row->action == "login")) {
			// its a login action ignore
			// no login name set on row, can't do anything, ignore.
			// it doesn't have a uuid row set, can't do anything anyway, ignore.
			$rows_to_delete[] = $expired_row->rpsession; // This adds it to the end of the array
			continue;
		}

		// we have an expired_row with a uuid, is that already matched to a user
		$already_linked = rpsl_get_user_by_uuid($expired_row->uuid);

		if($already_linked != null) {
			$rows_to_delete[] = $expired_row->rpsession; // This adds it to the end of the array
			continue;
		}

		// If there is not a user that exists by the loginname setup in the row
		// We cannot link a device or remove it
		$user = get_user_by('login', $expired_row->loginname);
		if(!is_a($user, 'WP_User')){
			$rows_to_delete[] = $expired_row->rpsession; // This adds it to the end of the array
			continue;
		}

		// make call to Rapid Service to confirm if the credential was collected
		// before we remove the session row.
		$credential = rpsl_get_credential($expired_row->uuid);

		if( is_a($credential,'rpsl_Rapid_Error') && $credential->number != "1109" ) {
		
			$error_message = $credential->rawErrorMessage();			
			rpsl_diag("Error retrieving credential: $error_message");
			// Something went wrong with the rapid service, we need to retry next time.
			// But ignore 1109 errors which are when the credential doesn't exist which is correct behaviour. ( was never collected so cleanedup)
			// Persist this one session for a future clean up loop.
			continue;
		}

		// At this point, either the credential was collected, the login wasn't 
		// A user, or the credential wasn't collected and so is expired, 
		// or there was an error on getting the credential
		// so we delete it
		
		$rows_to_delete[] = $expired_row->rpsession; // This adds it to the end of the array

		if(isset($credential->Status) && $credential->Status == "Collected") {
			// This credential was collected, lets link it up
			// User exists, add as a device.
			// Default device name of mobile device, will be updated when they log in.
			rpsl_add_device( $user->ID, $expired_row->uuid, "Mobile Device");

		} else if($expired_row->action == "self_collect")
			// The credential status is not collected, if that particular session row 
			// is a self_collect, a user account was created which needs removing
			wp_delete_user( $user->ID );
		}
	
	// Have now been through all the rows in the collection. Clean up where required.
	// Have to implode with quotes and comma to create correct sql in clause structure
	$rpsessions = implode("','", $rows_to_delete);
	$cleanup_query = "DELETE FROM " . $table_name . " WHERE rpsession in ('$rpsessions')";
	rpsl_diag("Deleting Rows: $cleanup_query");
	$wpdb->query($cleanup_query);
}

//-------------------------------------------------------------------------------------------------
// Generate the QR code data
function rpsl_qr_data($action, $requestid, $username, $anonid = null) {
// Structure is:   vAPP#tV#tV#tV ...
// v=version (currently 'b'), A=action, PP=platform
// fieldcodes are:  n=sitename, r=requestid, u=username, 
//					h= http siteurl, s=https siteurl
	$d = rpsl_delim();
	$qr_data  = "b" . $action . rpsl_site_code();
	$qr_data .= $d . "n" . rpsl_site_name();
	$qr_data .= $d . "r" . $requestid;

	if(!empty($username)) 	{ $qr_data .= $d . "u" . $username; }
	if(!empty($anonid)) 	{ $qr_data .= $d . "a" . $anonid; }

	$url = rpsl_admin_url();
	
	if(strtolower(substr($url,0,8)) == "https://") {
		$qr_data .= $d . "s" . substr($url,8);
	} else {
		$qr_data .= $d . "h" . substr($url,7);
	}
	
	if($action == "S")	{
		$qr_data .= $d . "p" . session_id();
	}
	
	return $qr_data;
}


//-------------------------------------------------------------------------------------------------
// Generate a QR code image from the given data and return the base 64 encoded image
//-------------------------------------------------------------------------------------------------
function rpsl_qr_base64($data, $size = 200) {
	ob_start();
	\PHPQRCode\QRcode::png($data, false, 'L', 4, 2);
	$image_string = base64_encode( ob_get_contents() );
	ob_end_clean();	
	return "data:image/png;base64," . $image_string;
}

function rpsl_resolve_openssl_error($message) {
	
	if(strpos($message, 'error:02001002:system library:fopen:No such file or directory') !== false) {
		// if this error is detected, it is due to the server platform not having access to the default openssl_open cnf file.
		// move sample default conf into position and retry method.
		$sample_sslconf = rpsl_cert_folder() . '/sample-openssl.cnf';
		$sslconf = rpsl_cert_folder() . '/openssl.cnf';

		if(!file_exists($sample_sslconf)) {
			rpsl_trace("No sample-openssl.cnf file exists to attempt default openssl.cnf fix, unable to retry");
			return false;
		}

		rename($sample_sslconf, $sslconf);
		return true;
	}

	return false;
}

?>