<?php 
//*************************************************************************************
//*************************************************************************************
// Utility functions for WordPress Rapid Secure Login
//*************************************************************************************
//*************************************************************************************

//*************************************************************************************
// Check the current status of RapID certificates
//*************************************************************************************
function rpsl_db_check_configuration(){

	$issuer_certificates = rpsl_db_get_active_issuer_certificates();
	$sac_certificate = rpsl_db_get_active_sac_certificate();
	
	$has_sac_cert = false;
	$has_issuer_cert = false;

	if(!empty($sac_certificate)){ $has_sac_cert = true; };
	if(!empty($issuer_certificates)) { $has_issuer_cert = true;}

	$result = array( 
		"CACert" 	=> $has_issuer_cert, 
		"RapidAuthCert" => $has_sac_cert, 
		"RapidAuthKey" 	=> $has_sac_cert
	);

    return $result;
}

//*************************************************************************************
// Get the path to the certs folder and ensure it exists	
//*************************************************************************************
function rpsl_cert_folder() {
	$folder = plugin_dir_path( __FILE__ ) . "certs";
	wp_mkdir_p($folder); 
	return $folder;
}

//*************************************************************************************
// Get the path / url to the temp folder and ensure it exists
//*************************************************************************************
function rpsl_temp_folder() {
	$folder = plugin_dir_path( __FILE__ ) . "temp";
	wp_mkdir_p($folder); 
	return $folder;
}

//*************************************************************************************
// Clean up old files
//*************************************************************************************
function rpsl_delete_old_files($folder) {
  $files = glob($folder."/*");
  $now   = time();

  foreach ($files as $file)
    if (is_file($file))
      if ($now - filemtime($file) >= 1200) 
        unlink($file);

}

//*************************************************************************************
// Read / Write the Site code, name, login style etc
//*************************************************************************************
function rpsl_site_code() {
	// This is effectively the 'Platform' code
	if(substr(admin_url(), -9) == "wp-admin/") {
		return "W1"; // Standard location
	} else {
		return "W2"; // Non-standard location
	}
}

//*************************************************************************************
// QR Code Construction
//*************************************************************************************
function rpsl_action_site($action) {
	return $action . rpsl_site_code() . rpsl_delim();
}

function rpsl_action_site_name($action) {
	return $action . rpsl_site_code() . rpsl_delim() . rpsl_site_name() . rpsl_delim();
}

//-------------------------------------------------------------------------------------------------
function rpsl_append_admin_url() {
	if (rpsl_site_code() == "W1") { 
		return  (rpsl_delim() . substr(admin_url(), 0, -9)); // Standard location so strip off the "wp-admin/"
	} else {
		return  (rpsl_delim() . admin_url());
	}
}

//-------------------------------------------------------------------------------------------------
function rpsl_admin_url() {
	if (rpsl_site_code() == "W1") { 
		$url = substr(admin_url(), 0, -9); // Standard location so strip off the "wp-admin/"
	} else {
		$url = admin_url();
	}
	if(substr($url, -1) == "/") { $url = substr($url, 0, -1); }
	return $url;
}

//-------------------------------------------------------------------------------------------------
// Returns the URL to the image folder
function rpsl_image_folder_url() {
	$upload = wp_upload_dir();
	return $upload['baseurl'];
}

//-------------------------------------------------------------------------------------------------
// Returns the QR code section delimiter 
function rpsl_delim() {
	return "#";
}

/****************************************************
** Function to return a json error back to the mobile app
****************************************************/
function rpsl_generate_json_wperror($code, $message){

	rpsl_diag("Error response $code, $message");
	$message = json_encode(array("Number"=>$code, "Message"=>$message));
	return $message;
}

/****************************************************
** Helper function for checking if a property is set
****************************************************/
function rpsl_is_empty($property){
	if(!isset($property) || empty($property)){
		return true;
	}

	return false;
}

// For 4.3.0 <= PHP <= 5.4.0, Returning Response Codes
if (!function_exists('http_response_code'))
{
    function http_response_code($newcode = NULL)
    {
        static $code = 200;
        if($newcode !== NULL)
        {
            header('X-PHP-Response-Code: '.$newcode, true, $newcode);
            if(!headers_sent())
                $code = $newcode;
        }       
        return $code;
    }
}

/*************************************************************
 * rpsl_UUID class
 * The following class generates VALID RFC 4122 COMPLIANT
 * Version 4 Universally Unique IDentifiers (UUID)
 *************************************************************/
class rpsl_UUID
{
	/*********************************************************
	 * Version 4 UUIDs are pseudo-random.
	 *********************************************************/
	public static function v4() {
		return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
		mt_rand(0, 0xffff), mt_rand(0, 0xffff), // 32 bits for "time_low"
		mt_rand(0, 0xffff),                     // 16 bits for "time_mid"
		mt_rand(0, 0x0fff) | 0x4000,			// 16 bits for "time_hi_and_version",
												// Four most significant bits hold version number 4
		mt_rand(0, 0x3fff) | 0x8000,    		// 16 bits, 8 bits for "clk_seq_hi_res",
												// 8 bits for "clk_seq_low",
												// Two most significant bits hold zero and one for variant DCE1.1
		mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)	// 48 bits for "node"
		);
	}
}
?>