<?php 
//*************************************************************************************
//*************************************************************************************
// Rapid Functions for interacting with the WordPress Database
//*************************************************************************************
//*************************************************************************************

//*************************************************************************************
// Find the user associated with the given UUID
//*************************************************************************************
function rpsl_get_user_by_uuid( $uuid ) {
// Given a UUID, returns the user object or null if nothing found 
	global $wpdb;
	$uuid       = sanitize_text_field( $uuid );
	$table_name = $wpdb->prefix . "rpsl_devices";
	
	$user_id    = $wpdb->get_var( $wpdb->prepare(
						"SELECT user_id FROM " . $table_name . " WHERE rpsl_uuid = %s ", $uuid ));
	if ($user_id == 0) { 
		return null ;
	} else {
		return get_userdata($user_id);
	}
}

//*************************************************************************************
// Add a new device to a user account
//*************************************************************************************
function rpsl_add_device( $user_id, $rpsl_uuid, $device_name) {
// Adds a device to the user account 
	global $wpdb;
	
	$device_name = sanitize_text_field($device_name);
	$rpsl_uuid   = sanitize_text_field($rpsl_uuid);
	
	if(!is_int($user_id)) {
		rpsl_trace("rpsl_add_device: user id not int: $user_id");
		return null;
	}
	
	if(!empty($device_name)) {
		$device_name = substr($device_name, 0, 80);
	}
		
	$table_name = $wpdb->prefix . "rpsl_devices";
	$result     = $wpdb->insert($table_name, array(
													'rpsl_uuid'   =>$rpsl_uuid, 
													'user_id'   =>$user_id, 
													'devicename'=>$device_name, 
													'created'   =>current_time('mysql', true),
													'last_used' =>current_time('mysql', true),
													'status'    =>"ok",
												));
	return $result;
}

//*************************************************************************************
// Set the 'last used' value for the given device
//*************************************************************************************
function rpsl_set_device_last_used ($uuid) {
	global $wpdb;
	$uuid          = sanitize_text_field( $uuid );
	$table_name    = $wpdb->prefix . "rpsl_devices";

	$set_last_used = $wpdb->prepare(
						"UPDATE " . $table_name . " SET last_used = NOW() WHERE rpsl_uuid = %s ", array($uuid)	);
	$updates = $wpdb->query( $set_last_used );
	return;
}

function rpsl_delete_device($uuid){
	global $wpdb;
	$uuid       = sanitize_text_field( $uuid );
	$table_name = $wpdb->prefix . "rpsl_devices";
		
	$delete_query = $wpdb->prepare(
						"DELETE FROM " . $table_name . " WHERE rpsl_uuid = %s ",
						array($uuid)
					);
	$deletes = $wpdb->query( $delete_query );
}

function rpsl_delete_session_row($uuid) {
	global $wpdb;
	$uuid       = sanitize_text_field( $uuid );
	$table_name = $wpdb->prefix . "rpsl_sessions";
		
	$delete_query = $wpdb->prepare("DELETE FROM " . $table_name . " WHERE rpsession = %s ", $uuid);
	$deletes = $wpdb->query( $delete_query );
}


//*************************************************************************************
// Create new Database Entry for the service authentication certificate
//*************************************************************************************
function rpsl_import_sac_certificate($pfx_content, $password){
// This function takes the supplied pfx which is base64 encoded and extracts the certificate and private
// key into separate PEM files. The key file is protected with a password derived from
// the system-wide NONCE_KEY defined in config.php
// The new files are written to the <plugin>/certs folder as crt.pem and key.pem

	rpsl_trace("Entering rpsl_import_sac_certificate method");
	// Get some filenames for the server authentication cert and key
	
	$result = false;
	try{
		// Parse the PFX file
		$parse_ok = openssl_pkcs12_read( $pfx_content, $p12, $password );
		
		if(!$parse_ok) {
			// If parse unsuccessful, log the error
			rpsl_log_openssl_error();
			return false;
		}

		$sslconf = rpsl_cert_folder() . '/openssl.cnf';
		if(file_exists($sslconf)) {	
			$config_args = array('config' => $sslconf); 
		} else {
			$config_args = null;
		}

		$key_ok = openssl_pkey_export($p12['pkey'], $protected_key, rpsl_keyfile_password(), $config_args);

		if (!$key_ok) {
			$openssl_error = rpsl_log_openssl_error();
			if(rpsl_resolve_openssl_error($openssl_error)){
				clearstatcache();
				if(file_exists($sslconf)) {	$config_args = array('config' => $sslconf); }
				$key_ok = openssl_pkey_export($p12['pkey'], $protected_key, rpsl_keyfile_password(), $config_args);
			}
		}

		if ($parse_ok && $key_ok) {
			// Add Sac's public and private key to db
			$result = rpsl_db_add_sac_certificate($p12['cert'], $protected_key);
		} else {
			$openssl_error = rpsl_log_openssl_error();		
		}	
	} catch (Exception $e) {
		rpsl_trace("Exception: " . $e->getMessage());
	}
	
	rpsl_trace("Exiting rpsl_import_sac_certificate method: $result");
	
	return $result;
}

function rpsl_db_add_sac_certificate($public_key, $private_key) {
	
	global $wpdb;
	// Adds a sac to the db
	$table_name = $wpdb->prefix . "rpsl_certificates";
	$result     = $wpdb->insert($table_name, array(
												'created'   	=> current_time('mysql', true), 
												'public_key'   	=> $public_key, 
												'private_key'	=> $private_key, 
												'type'			=> "SAC",
												'status'    	=> "ACTIVE",
												));

	if($result === false) {
		rpsl_trace("Unable to insert new sac certificate into the database.");
		return false;
	}
	else
	{
		// deactivate any other sac certs.
		// certificate rows which have status 1, are a SAC and are not the new row
		$new_row_id = $wpdb->insert_id;
		$update  = $wpdb->prepare("UPDATE " . $table_name . " SET status = 'DISABLED' where certificate_id != %d AND type = 'SAC' AND status = 'ACTIVE'", $new_row_id );
		$updated = $wpdb->query( $update );
		return true;
	}
}



//*************************************************************************************
// Create new Database Entry for the issuer certificate
//*************************************************************************************
function rpsl_add_issuer_certificate($public_key) {
	// Adds a sac to the db
	global $wpdb;
	
	if(empty($public_key))
	{
		rpsl_trace("rpsl_add_issuer_certificate: Certificate provided cannot be empty");
		return null;
	}

	try{
		$result = openssl_pkey_get_public($public_key);

		if($result === false){
			rpsl_trace("rpsl_add_issuer_certificate: Certificate not a valid X509 Public Key Certificate");
			return null;
		}
	} catch (Exception $e) {
		rpsl_trace("Exception: " . $e->getMessage());
		return null;
	}

	// check for duplicate issuer certificates.
	$table_name = $wpdb->prefix . "rpsl_certificates";
	$sql_duplicates = $wpdb->prepare("SELECT * FROM $table_name WHERE status = 'ACTIVE' AND type = 'TIC' AND public_key = %s", $public_key);
	$duplicates = $wpdb->get_row($sql_duplicates);

	if($duplicates !== null) {
		// Issuer Certificate already exists
		// don't add it but it is a successful state
		rpsl_trace("Issuer certificate with public key provided already exists. Skipping add.");
		return true;
	}

	$result     = $wpdb->insert($table_name, array(
							'created'   	=> current_time('mysql', true), 
							'public_key'   	=> $public_key,
							'type'			=> "TIC", 
							'status'    	=> 'ACTIVE',
							));
	if($result === false) {
		rpsl_trace("Unable to insert issuer certificate into the database.");
	}

	return $result;
}

//*************************************************************************************
// Get the certificates available for this site
//*************************************************************************************
function rpsl_db_get_active_issuer_certificates() {
// Given a UUID, returns the user object or null if nothing found 
	global $wpdb;
	
	$table_name = $wpdb->prefix . "rpsl_certificates";
	
	$rows = $wpdb->get_results("SELECT * FROM " . $table_name . " WHERE status = 'ACTIVE' AND type = 'TIC' ");
	
	return $rows;
}

//*************************************************************************************
// Get the certificates available for this site
//*************************************************************************************
function rpsl_db_get_active_sac_certificate() {
// Given a UUID, returns the user object or null if nothing found 
	global $wpdb;
	
	$table_name = $wpdb->prefix . "rpsl_certificates";
	
	$first_active_sac_cert = $wpdb->get_row("SELECT * FROM " . $table_name . " WHERE status = 'ACTIVE' AND type = 'SAC'");
	
	return $first_active_sac_cert;
}

//*************************************************************************************
// Get the password for the Rapid server authentication key file (unique per site)
//*************************************************************************************
function rpsl_keyfile_password() {

	global $wpdb;
	$table_name = $wpdb->prefix . "rpsl_config";
	$sql = "SELECT secretkey FROM $table_name ;";
	$secretkey = $wpdb->get_var( $sql );
	return $secretkey;
}

//-------------------------------------------------------------------------------------------------
function rpsl_set_site_name($name) {
	global $wpdb;
	$clean_name = sanitize_text_field( $name );
	$clean_name = str_replace(rpsl_delim(), " ", $clean_name); // Cannot have the QR code delimiter in the name
	$clean_name = str_replace("|", " ", $clean_name);  // .. or our Ajax delimiter
	
	
	$table_name = $wpdb->prefix . "rpsl_config";
	$update  = $wpdb->prepare("UPDATE " . $table_name . " SET site_name = %s ", $clean_name);
	$updated = $wpdb->query( $update );
	return;
}

function rpsl_site_name() {
	global $wpdb;
	$table_name = $wpdb->prefix . "rpsl_config";
	$sql = "SELECT site_name FROM $table_name ;";
	$name = $wpdb->get_var( $sql );
	return htmlentities($name);
}

//-------------------------------------------------------------------------------------------------
function rpsl_set_show_login($show_login) {
	global $wpdb;
	$show_login = sanitize_text_field($show_login);
	if($show_login != "Yes" && $show_login != "No" && $show_login != "Click" ) { return false; }
	
	$table_name = $wpdb->prefix . "rpsl_config";
	$update  = $wpdb->prepare("UPDATE " . $table_name . " SET show_login = %s ", $show_login	);
	$updated = $wpdb->query( $update );
	return true;
}

function rpsl_show_login() {
	global $wpdb;
	$table_name = $wpdb->prefix . "rpsl_config";
	$sql = "SELECT show_login FROM $table_name ;";
	$show_login = $wpdb->get_var( $sql );
	return htmlentities($show_login);
}

function rpsl_has_user_confirmed_email() {
	global $wpdb;
	$table_name = $wpdb->prefix . "rpsl_devices";
	$sql = "SELECT count(*) FROM $table_name ;";
	$licences_used = $wpdb->get_var( $sql );
	return $licences_used > 2;
}

?>