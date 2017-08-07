<?php
//*************************************************************************************
//*************************************************************************************
//   LOGIN / AUTHENTICATION
//*************************************************************************************
//*************************************************************************************


//*************************************************************************************
//   WORDPRESS LOGIN page - add the 'Login' QR Code HTML
//*************************************************************************************
function rpsl_wplogin_page_html() {
// Provides the HTML content to inject into the WP Login form
	$logoimg    = plugin_dir_url( __FILE__ ) . "images/rapidwm.png"; 
	$blank      = plugin_dir_url( __FILE__ ) . "images/white.jpg"; 
	$adminUrl   = rpsl_append_admin_url();
	$show_login = rpsl_show_login();  // Yes, No or Click

	// i18n Strings
	$mPleaseUseTheApp = __('Use the <a href="https://www.intercede.com/rapidsl" target="_blank">RapID Secure Login</a> app to scan the QR Code, or just tap the code on your phone.', 'rp-securelogin');
	$mGenerating      = esc_html__('Generating the login code', 'rp-securelogin');
	$mScanWithRapid   = esc_html__('Scan with RapID to sign in - No More Passwords!', 'rp-securelogin');
	if($show_login == "No") {
		$mClickToShow     = esc_html__('Password login has been disabled for this site', 'rp-securelogin');
	} else {
		$mClickToShow     = esc_html__('Click to show password fields', 'rp-securelogin');
	}
	
	$action  = isset($_REQUEST['action']) ? sanitize_text_field ( $_REQUEST['action'] ) : 'login';
	if ($action == "login") {
		?>
		<div id="rpsl_wplogin_div">
			<span><?php echo $mPleaseUseTheApp ?></span><br/>
			<div class="rpsl_qr_backend_div">
				<a id="rpsl_login_url" href="">
				<img class="rpsl_qr_img" id="rpsl_login_qr" name="rpsl_login_qr" src="<?php echo $blank; ?>" title="<?php echo $mScanWithRapid; ?>" />
				</a>
				<div>
				<img class="rpsl_logo_img_click" id="rapidlogo" name="rapidlogo" src="<?php echo esc_url($logoimg); ?>" title="<?php echo $mClickToShow; ?>" 
				<?php if($show_login != "No") { echo "onClick = 'rpsl_toggle_password_display();'"; }?>
				/>
				</div>
				<span name='rpsl_login_status' id='rpsl_login_status' ><?php echo $mGenerating ?></span>
				<br/><br/><span class="rpsl_login_error" name='rpsl_login_error' id='rpsl_login_error'></span>
			</div>
		</div>
		<?php
	}
}

//*************************************************************************************
//   WORDPRESS LOGIN page - add the scripts
//*************************************************************************************
function rpsl_wplogin_page_script() {

	// i18n Strings
	$mWaitingForAuth   = __('Waiting for authorization', 'rp-securelogin');
	$mAuthorized       = __('Authorized for user ', 'rp-securelogin');
 	$show_login        = rpsl_show_login();  // Yes, No or Click

	$action  = isset($_REQUEST['action']) ? sanitize_text_field ( $_REQUEST['action'] ) : 'login';
	if ($action == "login") {
		?>
		<script type='text/javascript' >
			var rpsl_wplogin_dots     = "";
			var rpsl_wplogin_ajaxurl  = "<?php echo admin_url('admin-ajax.php'); ?>";
			var rpsl_ajaxurl     = "<?php echo RPSL_Configuration::rpsl_ajax_endpoint() ?>";
			var rpsl_wplogin_adminurl = "<?php echo rpsl_append_admin_url(); ?>";
			var rpsl_wplogin_delim    = "<?php echo rpsl_delim(); ?>";
			var rpsl_wplogin_session  = "";
			var rpsl_wplogin_timeout; if (typeof rpsl_wplogin_timeout === 'undefined') rpsl_wplogin_timeout = 0;
			var rpsl_wplogin_code_requested;        // Needed to stop duplicate requests
			var rpsl_wplogin_pwform_visible = true; // Track state of password form
			var rpsl_login_user_field ;
			var rpsl_login_pass_field ;
			var rpsl_login_remember   ;
			var rpsl_login_button     ;
			var rpsl_refresh_qrcode = false;
			var rpsl_refresh_timeout;
			
			function rpsl_checkAuth() {  // Checks whether this session has been authenticated yet
				jQuery.ajax({
					url: rpsl_ajaxurl,
					type: 'POST',
					data: {	'action' :'rpsl_check_login', 'rpsession' : rpsl_wplogin_session},
					dataType: 'json',
					success:function(data) {
						var rpStatus = data.status;
						rpsl_wplogin_dots += "."; 
						if (rpsl_wplogin_dots.length > 10) { rpsl_wplogin_dots = ".";}
						// DIAG rpsl_setRawErrorMessage("code refreshed");
						
						if (rpStatus == "request_expired") {			// We must refresh the QR code
							window.location.reload(true);
						   
						} else if (rpStatus == "waiting_for_auth") {	// Wait and try again
							rpsl_setLoginStatusMessage("<?php echo $mWaitingForAuth ?>" + rpsl_wplogin_dots);
							rpsl_poll_for_login();
						   
						} else if (rpStatus.substr(0,2) == "zz") {	    // Diagnostics
							rpsl_setLoginStatusMessage(rpStatus);
							rpsl_poll_for_login();

						} else if (rpStatus == "waiting_for_association") {	 // Not meant for this page! Ignore
							rpsl_poll_for_login();
							
						} else if(rpStatus == "error") {
							rpsl_setLoginStatusMessage(data.message);
						} 
						else {										// Login name is set - submit
							if (rpStatus.trim() != "" ) {
								rpsl_setLoginStatusMessage("<?php echo $mAuthorized ?>" + rpStatus);
								rpsl_setNamedValue("user_login", "RapID-User");
								rpsl_setNamedValue("user_pass", rpsl_wplogin_session);
								document.getElementById("wp-submit").click(); 
							}
						}
					},
					error: function(errorThrown){
						rpsl_setLoginErrorMessage("An error occurred, please refresh the page to re-attempt to login.");
					}
				});   
			};	

			function rpsl_poll_for_login() {
				
				// Sessions will timeout, to cover this, refresh the QR code if this token is set
				// Has to be part of poll for login as it will override the assoc session variable
				// Which has to be correct for the form post.
				if(rpsl_refresh_qrcode) {
					rpsl_refresh_qrcode = false;
					rpsl_wplogin_code_requested = false;
					rpsl_setLoginQR();
					return;
				}
				
				// 18 minutes
				// Only set timeout if it is not 0
				if(!rpsl_refresh_timeout) { rpsl_refresh_timeout = setTimeout(rpsl_refreshQRCodeToken, 1080000); }

				// Avoid multiple polling timeouts from duplicate sections on the form
				if (rpsl_wplogin_timeout != 0) clearTimeout(rpsl_wplogin_timeout);
				rpsl_wplogin_timeout = setTimeout(rpsl_checkAuth, <?php echo RPSL_Configuration::$rpsl_ajax_timeout ?>);
			}

			function rpsl_refreshQRCodeToken()
			{
				rpsl_refresh_qrcode = true;
			}

			function rpsl_setLoginImages(base64src, data){
				jQuery("#rpsl_login_qr").attr("src",base64src);
				jQuery("#rpsl_login_url").attr("href", "rapid02://qr?sess=" + data);
			};
			
			function rpsl_setLoginErrorMessage(message){
					rpsl_setRawErrorMessage(message);
			};
			
			function rpsl_setRawErrorMessage(message){
				try { document.getElementById("rpsl_login_error").innerHTML = message; } catch(e) {};
			};
			
			function rpsl_setLoginStatusMessage(message){
				try { document.getElementById("rpsl_login_status").innerHTML = message; } catch(e) {};
			};
			
			function rpsl_setNamedValue(tagName, value){
				try { document.getElementById(tagName).value = value; } catch(e) {};
			};
			
			function rpsl_setLoginQR() {  // Calls to the server to get the QR code src
				if (!rpsl_wplogin_code_requested) {
					rpsl_wplogin_code_requested = true;
					jQuery.ajax({
						url :  rpsl_wplogin_ajaxurl, type: 'POST', data: {'action' :'rpsl_generate_login_qrcode'},	dataType: 'json',
						success:  function(data) {
									if(data.status == "error"){
										rpsl_setLoginErrorMessage("Error - Cannot create QR: " + data.message);
										return;
									} else{
										rpsl_wplogin_session = data.rpsessionid;
										rpsl_setLoginImages(data.qrbase64, data.qrdata);
										rpsl_setLoginStatusMessage("<?php echo $mWaitingForAuth ?>");
										clearTimeout(rpsl_refresh_timeout);
										rpsl_refresh_timeout = null;
										rpsl_poll_for_login();
									}
								},
						error:   function(errorThrown){
									rpsl_setLoginErrorMessage("Error - Cannot create QR: " + errorThrown);
								}
					}); 
				};
			};	
			
			function rpsl_wplogin_move_div() {
			// As there is no action hook in the right place, we have to move the div to the right place.
				var rpsl_div        = document.getElementById("rpsl_wplogin_div");
				var rpsl_parent_div = document.getElementById("loginform");
				if (rpsl_div.parentNode != rpsl_parent_div ) {
					rpsl_div.parentNode.removeChild(rpsl_div);
					rpsl_parent_div.insertBefore(rpsl_div,rpsl_parent_div.firstChild);
				}
			}

			function rpsl_toggle_password_display() {
			// Turns the password login fields on/off
				rpsl_login_user_field = document.getElementById("user_login").parentNode; // Get the outer <p>
				rpsl_login_pass_field = document.getElementById("user_pass" ).parentNode; // Get the outer <p>
				rpsl_login_remember   = document.getElementById("rememberme").parentNode; // Get the outer <p>
				rpsl_login_button     = document.getElementById("wp-submit" ).parentNode; // Get the outer <p>
				if (rpsl_wplogin_pwform_visible) {
					rpsl_login_user_field.style.display = "none";
					rpsl_login_pass_field.style.display = "none";
					rpsl_login_remember.style.display   = "none";
					rpsl_login_button.style.display     = "none";
					rpsl_wplogin_pwform_visible = false;
				} else {
					rpsl_login_user_field.style.display = "block";
					rpsl_login_pass_field.style.display = "block";
					rpsl_login_remember.style.display   = "block";
					rpsl_login_button.style.display     = "block";
					rpsl_wplogin_pwform_visible = true;
				}
			}

			rpsl_wplogin_move_div();
			<?php if($show_login != "Yes") { echo "rpsl_toggle_password_display();"; } ?>

			jQuery(document).ready(function($) {
				rpsl_setLoginQR();
			})
			
		</script>
		<?php
	}
}


//*************************************************************************************
// Generate a new rapid session ID and QR login code URL
// It is run via an Ajax call to avoid the over-zealous caching of some WP hosts
//*************************************************************************************
function rpsl_generate_login_qrcode() {
	global $wpdb;

	try {
		// Generate a pseudo-random session ID, prefix with "L<siteID>:" 
		$rpsl_sessionid = rpsl_UUID::v4();
		$ht_sessionid = session_id();

		rpsl_cleanup_expired_session_rows();

		// Create an entry in the session table
		$table_name = $wpdb->prefix . "rpsl_sessions";
		rpsl_trace("Inserting new login request:  $rpsl_sessionid ");
		$insert = "INSERT INTO " . $table_name .
					" (sessionid, rpsession, loginname, action, requested) " .
					"VALUES ('$ht_sessionid', '$rpsl_sessionid', 'waiting_for_auth', 'login', Now() )";
		$results = $wpdb->query( $insert );
	
		rpsl_diag("Generating new login QR:  $rpsl_sessionid ");
		//Leave a blank for the login name - obviously unknown at this point
		$qrdata = rpsl_qr_data("L", $rpsl_sessionid, "");
		$qr_base64 = rpsl_qr_base64($qrdata);
		rpsl_trace("New login QR created for:  $qrdata");
	
		$data = array("rpsessionid"=>$rpsl_sessionid, "qrbase64"=>$qr_base64, "qrdata"=>$qrdata);
		die(rpsl_echo(json_encode($data)));
	} catch (Exception $e) {
		$data = array("status"=>"error", "message"=>"Unable to generate QR code.");
		rpsl_trace("Error: Unable to generate QR code: $ht_sessionid. Message: " . $e->getMessage());
		die(json_encode($data));
	}
}


//*************************************************************************************
// This Ajax function checks the supplied POSTed envelope and matches it to the
// session request in the database
//*************************************************************************************
function rpsl_login_authorization () {
	global $wpdb;

	$received = rpsl_rapid_verify();
	$status  = $received['status'];
	
	if ($status == 0) {
		// Find the customer account from the certificate UUID
		$uuid    = sanitize_text_field( $received['uuid'] );
		$session = sanitize_text_field( $received['data'] );
		// Set the account in the Sessions table for the session ID in the POST
		$table_name = $wpdb->prefix . "rpsl_sessions";

		// Find the User account having this UUID:
		$user = rpsl_get_user_by_uuid($uuid);
		
		if (!is_object($user)) { 
			// Unable to retrieve a device record for the supplied uuid
			// Could be orphaned credential, check sessions table.

			// Updated collect credential to set the same value against the challenge as the uuid, now it's the same field
			// to check against for collect credential, front end registration and sign up.
			$table_name = $wpdb->prefix . "rpsl_sessions";
			$orphaned_row  = $wpdb->get_row( $wpdb->prepare("SELECT * FROM " . $table_name . " WHERE uuid = %s ", $uuid ));
			
			if(empty($orphaned_row) || empty($orphaned_row->loginname)) {
				rpsl_diag("Error: Attempted login with invalid UUID: $uuid, no loginname found in the sessions table.");
				echo __('Login denied - your RapID identity was not recognized', 'rp-securelogin'); 
				die; 
			}

			$loginname = $orphaned_row->loginname;
			// we have a matching session through one of the operations of register, front end register or sign up
			// add a device row.
			$user = get_user_by('login', $loginname);

			if(!is_a($user, 'WP_User')){
				rpsl_diag("Error: Attempted login with UUID: $uuid, but no login found for user $loginname");
				echo __('Login denied - your RapID identity was not recognized', 'rp-securelogin'); 
				die; 
			}
			$device_name = empty($received['phone']) ? "Mobile Device" : $received['phone'];
			
			//User exists, add as a device then allow login.
			rpsl_add_device( $user->ID, $uuid, $device_name) ;
			rpsl_delete_session_row($orphaned_row->rpsession);

		} else {
			$loginname = sanitize_text_field( $user->user_login );  // get the loginname for the session
		}

		$table_name = $wpdb->prefix . "rpsl_sessions";
		$setaccount = $wpdb->prepare(
							"UPDATE " . $table_name . " SET loginname = %s WHERE rpsession = %s AND action = 'login'",
							array($loginname, $session)
						);
		$updates = $wpdb->query( $setaccount );
		
		rpsl_create_session_token_file($session, $loginname);
		
		if ($updates > 0) {
			rpsl_echo(sprintf( __('Login authorized for %s', 'rp-securelogin'), $loginname)) ; // ( $updates ) <br/> $session ";
		} else {
			rpsl_echo(sprintf( __('Login denied for %s', 'rp-securelogin'), $loginname ));
		}
	} else {
		// Error
		$message = $received['message'];
		rpsl_diag("Login Failed: $message");
		rpsl_echo(__('The website was unable to log you in, please contact the sites administrator.', 'rp-securelogin'));
	}
    die; // Always die in functions echoing Ajax content
}

//*************************************************************************************
// This is the actual login function that checks the database
// RapID Logins will have username "RapID" and the QRCode as the password
//*************************************************************************************
function rpsl_login_intercept( $user, $username, $password ) {
	global $wpdb;

	if ($username != "RapID-User") { return $user; }

	 // Get the http and Rapid session IDs 
	$ht_sessionid = session_id();  
	$rpsl_sessionid = $password;

	// Look for an authorized entry in the session table - use a SQL escaped method:
	// Don't return rows where login name is still waiting_for_auth
	$table_name = $wpdb->prefix . "rpsl_sessions";
	$select = $wpdb->prepare(
						"SELECT loginname FROM " . $table_name . " WHERE rpsession = %s AND sessionid = %s AND action = 'login' AND loginname != 'waiting_for_auth'",
						array($rpsl_sessionid, $ht_sessionid)
					);

	$loginname = $wpdb->get_var( $select );
	rpsl_diag("Login attempt: $rpsl_sessionid result: $loginname ");
	
	if(empty($loginname)) {
		// Login name empty, 
		return new WP_Error('rapid_session_is_invalid');
	}
	
	// get user by login name
	$user = get_user_by('login', $loginname);
	
	if(is_a($user, 'WP_User')){
		rpsl_delete_session_row($rpsl_sessionid);
		return $user;
	}

	return new WP_Error('rapid_session_is_invalid');
}
//*************************************************************************************
// Set the authentication filter to append our own RapID method
//*************************************************************************************
add_filter( 'authenticate', 'rpsl_login_intercept', 50, 3 );  // Place it after the default password checker (20)