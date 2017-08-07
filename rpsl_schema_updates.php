<?php 
//*************************************************************************************
//*************************************************************************************
// Schema functions for WordPress Rapid Secure Login Updates and DB Changes
//*************************************************************************************
//*************************************************************************************
function rpsl_check_version() {
	$db_version = get_option('rpsl_plugin_db_version_number');
	
	if(empty($db_version))
	{
		// no previous version, old version of the plugin
		// do the initial upgrade required, and set the version number to 1 to begin
		// upgrade process.
		rpsl_database_upgrade();
		$db_version = get_option('rpsl_plugin_db_version_number');
	}

	$plugin_version = rpsl_WordPress_Plugin::$rpsl_plugin_db_version_number;
	if($db_version < $plugin_version)
    {
		do {
			$db_version++;
			$upgrade_function = "rpsl_database_upgrade" . $db_version;
			if(function_exists($upgrade_function)){
				call_user_func($upgrade_function);
			}
		} while ($db_version < $plugin_version);
    }
}

function rpsl_update_db_version_option($number) {
	update_option('rpsl_plugin_db_version_number', $number);
}

// Upgrade to Version 3, Setting up default roles.
function rpsl_database_upgrade3() {
	
	rpsl_initialise_roles();
	rpsl_update_db_version_option(3);
}

// Upgrade to Version 2, Add Default value on datetime.
function rpsl_database_upgrade2() {
	
	global $wpdb;
	// Update the session tables to move from register to user_collect
	$table_name = $wpdb->prefix . "rpsl_sessions";
	$update_register_sessions = "UPDATE " . $table_name . " SET action = 'user_collect' WHERE action = 'register'";
	$wpdb->query( $update_register_sessions );

	rpsl_update_db_version_option(2);
}

//*************************************************************************************
// First upgrade method, first time a version is not available so is an old plugin
// has to be migrated to database certificates first, then we give it a number.
// At which point additional methods can be called such as rpsl_database_upgrade1
function rpsl_database_upgrade() {
		
	global $wpdb;
	require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
	
	// add secretkey variable.
	$table_name = $wpdb->prefix . 'rpsl_config';
	$sql = "CREATE TABLE $table_name (
		site_id    varchar(10) NOT NULL,
		site_name  varchar(80) NOT NULL,
		show_login varchar(8)  NOT NULL,
		secretkey  varchar(36) NOT NULL,
		PRIMARY KEY  (site_id)
	) $charset_collate;";
	dbDelta( $sql );
	
	$secretkey = rpsl_UUID::v4();
	$update  = $wpdb->prepare("UPDATE " . $table_name . " SET secretkey = %s ", $secretkey);
	$updated = $wpdb->query( $update );

	// Create the rpsl_CERTIFICATES table to hold credentials 
	// type is Tic or Sac
	$charset_collate = $wpdb->get_charset_collate();

	$table_name = $wpdb->prefix . 'rpsl_certificates';
	$sql = "CREATE TABLE $table_name (
			certificate_id   int NOT NULL AUTO_INCREMENT,
			public_key       varchar(3000) NOT NULL,
			private_key      varchar(3000),
			type             varchar(3),
			created          datetime NOT NULL,
			status           varchar(20) NOT NULL,
			PRIMARY KEY (certificate_id)
		) $charset_collate;";
	dbDelta( $sql );

	$certfolder = rpsl_cert_folder();
	$certfile 	= $certfolder . "/crt.pem"; 
	$keyfile  	= $certfolder . "/key.pem"; 
	$issuerfile = $certfolder . "/Rapid_TrustedCa.cer";

	$certfiles_exist = file_exists($issuerfile) || (file_exists($certfile) && file_exists($keyfile));

	if($certfiles_exist === true)
	{
		$cert_config = rpsl_db_check_configuration();

		// attempt issuer import if the issuer is not already configured
		if(!$cert_config['CACert']){
			$issuer_cert = file_get_contents($issuerfile);		
			$result = rpsl_add_issuer_certificate($issuer_cert);
			if($result) { unlink($issuerfile); }
		}

		// attempt sac import if the sac is not already configured
		if(!$cert_config['RapidAuthCert'] && !$cert_config['RapidAuthKey']) {
			$cert = file_get_contents($certfile);
			$key = file_get_contents($keyfile);

			// we have the private key, we need to decrypt using nonce, then 
			// re-encrypt with new secretkey.
			// Parse the Private Key
			$clear_private_key = openssl_pkey_get_private($key, NONCE_KEY);

			$sslconf = rpsl_cert_folder() . '/openssl.cnf';
			if(file_exists($sslconf)) {	
				$config_args = array('config' => $sslconf); 
			} else {
				$config_args = null;
			}

			$key_ok = openssl_pkey_export($clear_private_key, $encrypted_private_key, rpsl_keyfile_password(), $config_args);

			if (!$key_ok) {
				$openssl_error = rpsl_log_openssl_error();
				if(rpsl_resolve_openssl_error($openssl_error)){
					clearstatcache();
					if(file_exists($sslconf)) {	$config_args = array('config' => $sslconf); }
					$key_ok = openssl_pkey_export($clear_private_key, $encrypted_private_key, rpsl_keyfile_password(), $config_args);
				}
			}

			$result = rpsl_db_add_sac_certificate($cert, $encrypted_private_key);
			if($result) {
				unlink($certfile);
				unlink($keyfile);
			}
		}
	}

	
	add_option("rpsl_plugin_db_version_number", 1);
}
?>