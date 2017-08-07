<?php
//*************************************************************************************
//*************************************************************************************
//   REGISTRATION
//*************************************************************************************
//*************************************************************************************



//*************************************************************************************
//*************************************************************************************
//   WORDPRESS USER PROFILE page - add the Register QR Code and Device List
//*************************************************************************************
//*************************************************************************************

function rpsl_wp_registration_qrcode($user) {
// This part of Wordpress uses tables for layout rather than divs
	$logoimg = esc_url(plugin_dir_url( __FILE__ ) . "images/rapidwm.png" ); 
	$logobig = esc_url(plugin_dir_url( __FILE__ ) . "images/rapid.png" ); 
	$blank   = esc_url(plugin_dir_url( __FILE__ ) . "images/white.jpg" ); 
	$target_user_id = $user->ID;

	$show_credential = current_user_can( RPSL_Configuration::$rpsl_can_have_credential_role );
	
	// i18n Strings
	$mHeader          = esc_html__('RapID Secure Login - No more passwords!', 'rp-securelogin');
	$mInstructions    = __('Use RapID for simple, secure login to WordPress. <br/>', 'rp-securelogin');
	$mInstructions   .= __('Just get the free RapID Secure Login app from <a href=\'https://play.google.com/store/apps/details?id=com.intercede.rapidsl&hl=en_GB\' target=\'_blank\'>Google Play</a> or the <a href=\'https://itunes.apple.com/us/app/rapid-secure-login/id1185934781?mt=8\' target=\'_blank\'>AppStore</a>.<br/>', 'rp-securelogin');
	$mInstructions   .= __('Then click or tap on the RapID logo below and scan the QR code with your RapID Secure Login app.<br/>', 'rp-securelogin');
	$mInstructions   .= __('The next time you want to login to this site, just scan the login code and use your finger or a simple PIN to authenticate.<br/>', 'rp-securelogin');
	$mInstructions   .= esc_html__('The same app can log you in to multiple accounts on different WordPress sites.', 'rp-securelogin');
	
	$mUseRapid        = esc_html__('Use RapID - No More Passwords!', 'rp-securelogin');
	$mClickToAdd      = esc_html__('Click or tap the logo to request a RapID credential', 'rp-securelogin');
	$mNewCredAdded    = esc_html__('Your new RapID credential has been added', 'rp-securelogin');
	$mScanPrompt2     = esc_html__('Scan or tap the QR code with the RapID app to create a new RapID credential', 'rp-securelogin');
	$mChecking        = esc_html__('Waiting for credential collection', 'rp-securelogin');
	$mPleaseWait      = esc_html__('Please wait while a credential is prepared', 'rp-securelogin');
	$mConfirmRemove   = esc_html__('You are about to remove this device from your account. Are you sure?', 'rp-securelogin');
	$mDeniedByRole    = esc_html__('Your role does not let you make use of a RapID login, contact your site administrator to request access.', 'rp-securelogin');
	$mNewName         = esc_html__('Please enter a new name for this device', 'rp-securelogin');

	if (IS_PROFILE_PAGE && !$show_credential) {
?>
	<h3><?php echo $mHeader ?></h3>
	<span name='rpsl_device_list'><?php rpsl_dump_devices($target_user_id); ?></span>
	<span><?php echo $mDeniedByRole; ?></span>
<?php 
	} elseif(IS_PROFILE_PAGE) { 
?>
	<h3><?php echo $mHeader ?></h3>
	<h4><?php echo $mInstructions ?></h4>
	<span name='rpsl_device_list'><?php rpsl_dump_devices($target_user_id); ?></span>
	<br/><br/>
	<span name='rpsl_assoc_prompt'><?php echo $mClickToAdd ?></span>
	<p>
	<div class="rpsl_outer_div">
		<div class="rpsl_qr_div" id="rpsl_big_logo" name="rpsl_big_logo">
			<img class="rpsl_qr_img" src="<?php echo $logobig; ?>" title="<?php echo $mClickToAdd ?>"  onClick="rpsl_newRequest();" />
		</div>
		
		<div class="rpsl_qr_div" id="rpsl_qr_code" name="rpsl_qr_code" style="display:none">
			<a name="rpsl_assoc_url" href=''>
			<img class="rpsl_qr_img"   id="rpsl_assoc_qr" name="rpsl_assoc_qr" src="<?php echo $blank;   ?>" title="<?php echo $mClickToAdd ?>" />
			</a>
			<div id="rpsl_small_logo" name="rpsl_small_logo" style="display:none">
				<img class="rpsl_logo_img_click" id="rapidlogo"    name="rapidlogo"    src="<?php echo $logoimg; ?>" title="<?php echo $mUseRapid ?>" onClick="rpsl_newRequest();" />
			</div>
		</div>
		<div class="rpsl_qr_div">
			<span name='rpsl_assoc_error'>&nbsp;</span>
		</div>
	</div>
	</p>
	
	<script type='text/javascript' >	
		var rpsl_newIdRequested = false;
		var rpsl_assocSession   = "";
		var rpsl_assocDots      = ".";
		var rpsl_user_id        = "<?php echo $target_user_id ; ?>";
		var rpsl_admin_ajaxurl  = "<?php echo admin_url('admin-ajax.php'); ?>";
		var rpsl_ajaxurl        = "<?php echo RPSL_Configuration::rpsl_ajax_endpoint() ?>";
		var rpsl_checkRegistered_timeout; if (typeof rpsl_checkRegistered_timeout === 'undefined') rpsl_checkRegistered_timeout = 0;
		var rpsl_refresh_qrcode = false;
		var rpsl_refresh_timeout;
		
		function rpsl_setRegistrationQR() {  // Calls to the server to get the QR code src
			rpsl_assocDots = ""; 
			jQuery.ajax({
				url :  rpsl_admin_ajaxurl, type: 'POST', data: {'action' :'rpsl_generate_registration_qrcode'},	dataType: 'json',
				success:  function(data) {
							if(data.error) {
								// Error generating QR Code, Stop.
								rpsl_setAssocErrorMessage(data.error);
								rpsl_hideQR();
								return;
							}

							rpsl_assocSession = data.uuid;
							rpsl_setAssocImages(data.qrbase64, data.qrdata); // src and url data for the image
							rpsl_showQR();
							rpsl_newIdRequested = true;
							rpsl_setAssocErrorMessage("<?php echo $mChecking ?>" + rpsl_assocDots);
							clearTimeout(rpsl_refresh_timeout);
							rpsl_refresh_timeout = null;
							rpsl_poll_for_registration();
						},
				error:   function(request, statusText, errorText){
							rpsl_setAssocErrorMessage("Error: " + request.status + ", Retrying...");
							rpsl_poll_for_registration();
						}
			});   
		};	

		function rpsl_checkRegistered() {  // Calls to the server to check whether the user has registered their new credential
			rpsl_assocDots += "."; if(rpsl_assocDots.length > 10) rpsl_assocDots = ".";
			jQuery.ajax({
				url :  rpsl_ajaxurl, type: 'POST', data: {'action' :'rpsl_check_registered', 'rpsession' : rpsl_assocSession},	dataType: 'json',
				success:  function(data) {
							if (data.status == "ok") {
								rpsl_setAssocErrorMessage("  <?php echo $mNewCredAdded ?>");
								rpsl_refresh(); // Swap back to static logo
							} else if(data.status == "error") {
								rpsl_setAssocErrorMessage(data.message);
								rpsl_refresh(); // Swap back to static logo
							} else {
							    rpsl_setAssocErrorMessage(data.status + rpsl_assocDots);
								rpsl_poll_for_registration();
							}
						},
				error:   function(request, statusText, errorText){
							rpsl_setAssocErrorMessage("Error: " + request.status + ", Retrying...");
							rpsl_poll_for_registration();
						}
			});   
		};	

		function rpsl_poll_for_registration() {

			// Sessions will timeout, to cover this, refresh the QR code if this token is set
			// Has to be part of poll for login as it will override the assoc session variable
			// Which has to be correct for the form post.
			if(rpsl_refresh_qrcode)	{
				rpsl_refresh_qrcode = false;
				rpsl_wplogin_code_requested = false;
				rpsl_setRegistrationQR();
				return;
			}

			// Only set timeout if it is not 0
			if(!rpsl_refresh_timeout) { rpsl_refresh_timeout = setTimeout(rpsl_refreshQRCodeToken, 602000); }

			// Avoid multiple polling timeouts from duplicate sections on the form
			if (rpsl_checkRegistered_timeout != 0) clearTimeout(rpsl_checkRegistered_timeout);
			if (rpsl_newIdRequested) rpsl_checkRegistered_timeout =	setTimeout(rpsl_checkRegistered, <?php echo RPSL_Configuration::$rpsl_ajax_timeout ?>);
		}

		function rpsl_refreshQRCodeToken()
		{
			rpsl_refresh_qrcode = true;
		}
		
		function rpsl_setAssocImages(src, data){

			jQuery('[name="rpsl_assoc_qr"]').attr("src", src);
			jQuery('[name="rpsl_assoc_url"]').attr("href", "rapid02://qr?sess=" + data);		
		};
			
		function rpsl_showQR(){
			//Hide the big logo and show the small one plus QRcode
			jQuery('[name="rpsl_big_logo"]').hide();
			jQuery('[name="rpsl_qr_code"]').show();
			jQuery('[name="rpsl_small_logo"]').show();

			rpsl_setAssocPromptMessage("<?php echo $mScanPrompt2 ?>");
		};

		function rpsl_hideQR(){
			//Hide the big logo and show the small one plus QRcode
			jQuery('[name="rpsl_big_logo"]').show();
			jQuery('[name="rpsl_qr_code"]').hide();
			jQuery('[name="rpsl_small_logo"]').hide();

			rpsl_setAssocPromptMessage("<?php echo $mClickToAdd ?>");
		};
		
		function rpsl_setAssocErrorMessage(mess){
			var message = mess;
			if (message == "Error: [object Object]") { message = "Errors: Session has timed out. Please refresh the page"; }
			
			jQuery('[name="rpsl_assoc_error"]').html(message);
		};
		
		function rpsl_setAssocPromptMessage(mess){
			jQuery('[name="rpsl_assoc_prompt"]').html(mess);
		};
		
		function rpsl_setDeviceList(){
			jQuery.ajax({
				url :  rpsl_admin_ajaxurl, type: 'POST', data: {'action' :'rpsl_list_devices', 'user' : rpsl_user_id},	dataType: 'html',
				success:  function(data) {
						jQuery('[name="rpsl_device_list"]').html(data);
				},
				error:   function(errorObj, statusText, errorText){
							rpsl_setAssocErrorMessage("Error: " + statusText + " - " + errorText);
				}
			});   
		};
		
		function rpsl_newRequest() {  // Creates a new RapID credential request and shows the request ID here
			if (!rpsl_newIdRequested) {
				rpsl_setAssocPromptMessage("<?php echo $mPleaseWait ?><br/>");
				rpsl_setAssocImages(<?php echo '"' . $blank . '"'?>);
				rpsl_setRegistrationQR();
			}
		};

		// Device Management functions
		
		function rpsl_delete_device(uuid) {
		// Delete the given device. Only allowed from a logged in session. 
		// Called from the table generated by rpsl_dump_devices
			var carryon = confirm("<?php echo $mConfirmRemove ?>");
			if (carryon) {
				jQuery.ajax({
					url :  rpsl_admin_ajaxurl, type: 'POST', data: {'action' :'rpsl_delete_device', 'uuid' : uuid},	dataType: 'html',
					success:  function(data) {
								rpsl_setDeviceList(); //alert("Device deleted ok");
							},
					error:   function(errorObj, statusText, errorText){
								rpsl_setAssocErrorMessage("Error: " + statusText + " - " + errorText);
							}
				});
			}
		};

		function rpsl_rename_device(uuid, oldname) {
		// Rename the given device. Only allowed from a logged in session.
		// Called from the table generated by rpsl_dump_devices
			var newname = prompt("<?php echo $mNewName ?>", oldname);
			if (newname != null) {
				jQuery.ajax({
					url :  rpsl_admin_ajaxurl, type: 'POST', data: {'action' :'rpsl_rename_device', 'uuid' : uuid, 'name' : newname},	dataType: 'html',
					success:  function(data) {
								rpsl_setDeviceList(); //alert(data);
							},
					error:   function(errorObj, statusText, errorText){
								rpsl_setAssocErrorMessage("Error: " + statusText + " - " + errorText);
							}
				});
			}
		};

		function rpsl_refresh() {
			rpsl_hideQR(); 
			rpsl_setDeviceList();
			rpsl_newIdRequested = false;
		}
		
	</script>
<?php
	} // End "elseif" IsProfile
} // End rpsl_wp_registration_qrcode

//*************************************************************************************
// This generates a new rapid request ID and QR registration code URL
// It is run via an Ajax call to avoid the over-zealous caching of some WP hosts
// QR code contains: <version>E<platform>#<site name>#<request ID>#<login name>#<url>
//*************************************************************************************
function rpsl_generate_registration_qrcode() {
	global $wpdb;

	// Generate a pseudo-random user ID for the RapID service to use, then submit the request
	$ht_sessionid = session_id();
	$rpsl_uuid      = rpsl_UUID::v4();
	$rpsl_requestid = rpsl_get_new_rapid_request($rpsl_uuid);
	
	if( is_a($rpsl_requestid,'rpsl_Rapid_Error') ) {
		
		$error_message = $rpsl_requestid->rawErrorMessage();			
		rpsl_diag("rpsl_generate_registration_qrcode error: $error_message");

		$result = array("error" => $rpsl_requestid->rpsl_extract_rapid_service_message());
		die(rpsl_echo(json_encode($result)));
	}
	
	$currentuser = wp_get_current_user();
	$loginname = sanitize_text_field( $currentuser->user_login );
	
	$table_name = $wpdb->prefix . "rpsl_sessions";

	// Add an entry to the sessions table so we can track the confirmation and add the device
	// Note the rpsl_session must be the new UUID,  not the RapID request ID
	$insert = $wpdb->prepare("INSERT INTO " . $table_name .	" (sessionid, rpsession, loginname, action, requested, uuid) " .
				"VALUES (%s, %s, %s, 'user_collect', Now(), %s)", $ht_sessionid, $rpsl_uuid, $loginname, $rpsl_uuid);
	$results = $wpdb->query( $insert );

	$qr_data = rpsl_qr_data("E", $rpsl_requestid, $loginname);
	$qr_base64 = rpsl_qr_base64($qr_data);
	
	$result = array("uuid" => $rpsl_uuid, "qrbase64" => $qr_base64, "qrdata" => $qr_data);
	rpsl_echo(json_encode($result));

	rpsl_diag("New credential request:  $rpsl_requestid, user: $loginname ");

	die; // Always die in functions echoing Ajax content
}

//*************************************************************************************
// This Ajax function is used by the phone to confirm the creation of a 
// new RapID credential
//*************************************************************************************
function rpsl_register () {
	global $wpdb;
	
	$received = rpsl_rapid_verify();
	$confirm = 0;
	
	if ($received['status'] == 0) {
		// Find the customer account from the certificate UUID
		$rpsl_uuid = $received['uuid'];

		$phone   = sanitize_text_field( $received['data'] );

		if ($phone == "") $phone = __('My registered Phone', 'rp-securelogin');
		rpsl_trace("Register device UID = $rpsl_uuid , Phone = $phone ");
		
		// Look for an 'user_collect' or 'self_collect' entry in the session table - use a SQL escaped method:
		$table_name = $wpdb->prefix . "rpsl_sessions";
		$select = $wpdb->prepare("SELECT loginname FROM " . $table_name . " WHERE rpsession = %s AND ( action = 'user_collect' OR action = 'self_collect' )", $rpsl_uuid );
		$loginname  = $wpdb->get_var($select);
		
	    if ($loginname != "") {
			$user = get_user_by('login', $loginname);
			// Set the UUID in the database for the currently logged in user
			rpsl_add_device( $user->ID, $rpsl_uuid, $phone);
			
			// Now create session token file to trigger to the database.
			rpsl_create_session_token_file($rpsl_uuid, $loginname);
			rpsl_diag("New credential added for user: $loginname, phone: $phone, UUID: $rpsl_uuid ");
			$result = "ok"; 

			rpsl_delete_session_row($rpsl_uuid);

			echo $result;
		} else {
			$result = __('Error: registration failed', 'rp-securelogin'); 
			rpsl_echo($result);
			rpsl_trace($confirm);
		}	
	} else {
		$result = __('Error: Invalid signed request', 'rp-securelogin');
		rpsl_echo($result);
	}
	die; // Always die in functions echoing Ajax content
}

//*************************************************************************************
// This Ajax function is used by the phone to confirm the creation of a 
// new RapID credential with apps > 2.0.7
//*************************************************************************************
function rpsl_credential_confirmation () {
	global $wpdb;
	
	$received = rpsl_rapid_verify();
	
	if ($received['status'] != 0) {
		http_response_code(400);
		$message = rpsl_generate_json_wperror(9021,"Request packet was incorrectly signed.");
		die($message);
	}
	
	// Find the customer account from the certificate UUID
	$rpsl_uuid = $received['uuid'];
	$device_name = __('My registered Phone', 'rp-securelogin');
	
	$jsonData = json_decode($received['data']);
	
	if(!rpsl_is_empty($jsonData) && !rpsl_is_empty($jsonData->deviceName)) {
		$device_name = $jsonData->deviceName;
	}

	rpsl_trace("Register device UID = $rpsl_uuid , Device Name = $device_name ");
	
	// Look for an 'user_collect' or 'self_collect' entry in the session table - use a SQL escaped method:
	$table_name = $wpdb->prefix . "rpsl_sessions";
	$select = $wpdb->prepare("SELECT loginname FROM " . $table_name . " WHERE rpsession = %s AND ( action = 'user_collect' OR action = 'self_collect' )", $rpsl_uuid );
	$loginname  = $wpdb->get_var($select);
	
	if(empty($loginname)) {
		http_response_code(500);
		$message = rpsl_generate_json_wperror(9022,"Rapid session did not contain a loginname, unable to confirm credential.");
		die($message);
	}

	$user = get_user_by('login', $loginname);

	if(!is_a($user, 'WP_User')){
		http_response_code(500);
		$message = rpsl_generate_json_wperror(9023,"WordPress User account does not exist for loginname $loginname");
		die($message);
	}

	//Set the UUID in the database for the currently logged in user
	rpsl_add_device( $user->ID, $rpsl_uuid, $device_name);
	
	// Now create session token file to trigger to the database.
	rpsl_create_session_token_file($rpsl_uuid, $loginname);
	rpsl_delete_session_row($rpsl_uuid);
	rpsl_diag("New credential added for user: $loginname, device: $device_name, UUID: $rpsl_uuid ");
	die;
}