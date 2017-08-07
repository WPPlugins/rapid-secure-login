<?php
//*************************************************************************************
//*************************************************************************************
//   NEW ACCOUNT - WORDPRESS SELF-REGISTRATION WITH RAPID
//*************************************************************************************
//*************************************************************************************


function rpsl_newregistration_qrcode() {
// This part of Wordpress uses tables for layout rather than divs
	$logoimg = esc_url( plugin_dir_url( __FILE__ ) . "images/rapidwm.png" ); 
	$blank   = esc_url( plugin_dir_url( __FILE__ ) . "images/white.jpg" ); 

	if ( is_user_logged_in() ) {
		return;
		// if user is logged in already, don't let them register
	}

	$role_to_check = get_role(get_option('default_role'));
	$front_end_register_allowed = $role_to_check->has_cap( RPSL_Configuration::$rpsl_can_have_credential_role );

	?>
	<script type="text/javascript">
		var rpsl_admin_ajaxurl		= "<?php echo admin_url('admin-ajax.php'); ?>";
		var rpsl_ajaxurl			= "<?php echo RPSL_Configuration::rpsl_ajax_endpoint() ?>";
		var rpsl_timeout			= "<?php echo RPSL_Configuration::$rpsl_ajax_timeout ?>";
	</script>
<?php
	wp_enqueue_script('rpsl_self_registration');

	// i18n Strings
	$mTitle           = esc_html__('RapID Registration', 'rp-securelogin');
	$mScanQR          = __('Use the <a href="https://www.intercede.com/rapidsl" target="_blank">RapID Secure Login</a> app to scan the QR Code, or just tap the code on your phone to register on this site.', 'rp-securelogin');
	$mScanWithRapid   = esc_html__('Scan with your RapID app to register', 'rp-securelogin');
	$mUseRapid        = esc_html__('Use RapID - No More Passwords!', 'rp-securelogin');
	$mAlreadyLoggedOn = esc_html__('You are already logged on as: ', 'rp-securelogin');
	 
	$action  = isset($_REQUEST['action']) ? sanitize_text_field( $_REQUEST['action'] ) : 'login';
	if ($action == "register" && $front_end_register_allowed) {
		?>
		<div id="rpsl_rapidlogon_container" style="display:none;">
			<h3 id="rpsl_newacc_header"><?php echo $mTitle ?></h3>
			<span name='rpsl_newacc_prompt'><?php echo $mScanQR ?></span>
			<br/><br/>
			<div class="rpsl_outer_div" id="rpsl_newacc_qrdiv">
			  <div class="rpsl_qr_backend_div">
			    <a name="rpsl_qr_url" href=''>
				  <img class="rpsl_qr_img"   id="rpsl_newacc_qr" name="rpsl_newacc_qr" src="<?php echo $blank;     ?>" title="<?php echo $mScanWithRapid ?>"  />
				</a>
			    <div>
				  <img class="rpsl_logo_img" id="rapidlogo"    name="rapidlogo"    src="<?php echo $logoimg; ?>" title="<?php echo $mUseRapid ?>" onClick="rpsl_newRequest();" />
				</div>
				<span name='rpsl_newacc_error'>Awaiting user registration.</span>
			  </div>
			  <br /><br />
			  <p>Alternatively, please fill out the details below and click register.</p><br/>
			</div>
		</div>
		<?php
	}
}

//*************************************************************************************
// This generates a rapid session ID and QR code for creating a new account
// It is run via an Ajax call to avoid the over-zealous caching of some WP hosts
// QR code contains: <version>R<platform>#<site name>#<request ID>#<login name>#<url>
//*************************************************************************************
function rpsl_generate_self_registration_qrcode() {
	global $wpdb;

	// Check the current user is not set
	$currentuser = wp_get_current_user();
	if ( $currentuser && $currentuser->ID != 0) {
		$message = "logged on as " . $currentuser->user_login;
		$data = array("status"=>"error", "message"=>$message);
		rpsl_echo(json_encode($data));
		die(); 
	}  
	
	$loginname = "";
	
	// Generate a pseudo-random session ID, prefix with {Forum}
	$d = rpsl_delim();
	$ht_sessionid = session_id();
	$rpsl_sessionid = rpsl_UUID::v4();
	
	// Create an entry in the session table
	$table_name = $wpdb->prefix . "rpsl_sessions";

	$insert = $wpdb->prepare("INSERT INTO " . $table_name .	" (sessionid, rpsession, loginname, action, userdata, requested) " .
				"VALUES (%s, %s, %s, 'self_registration', 'waiting', Now())", $ht_sessionid, $rpsl_sessionid, $loginname);
	$results = $wpdb->query( $insert );
	
	$qr_data = rpsl_qr_data("R", $rpsl_sessionid, $loginname);
	$qr_base64 = rpsl_qr_base64($qr_data);
	rpsl_trace("New User Info QR created for:  $qr_data");
	
	$data = array("rpsessionid"=>$rpsl_sessionid, "qrbase64"=>$qr_base64, "qrdata"=>$qr_data);
	die(rpsl_echo(json_encode($data)));
}

//*************************************************************************************
// This Ajax function checks the supplied POSTed envelope and matches it to the
// 'userinfo' request in the database, which it then updates with the info
// Returns a RapID Request ID or error message
// If the phone has a credential for this website already it blocks the submission
//*************************************************************************************
function rpsl_set_account_info () {
	die(rpsl_echo("Error: Please upgrade your mobile app to continue."));
}

//*************************************************************************************
// This Ajax function checks the supplied POSTed envelope and matches it to the
// 'userinfo' request in the database, which it then updates with the info
// Returns a RapID Request ID or error message
// If the phone has a credential for this website already it blocks the submission
//*************************************************************************************
function rpsl_self_registration_create_user() {
	global $wpdb;

	$dataJson = file_get_contents("php://input");     
	$data     = json_decode($dataJson);    
	$uuid     = rpsl_UUID::v4(); // for credential request           

	if(rpsl_is_empty($data->session) || rpsl_is_empty($data->user)){

		http_response_code(400);
		$message = rpsl_generate_json_wperror(9010,"RapID Self Registration: Minimum data not supplied");
		die($message);
	}

	$session  = $data->session;
	$user_data = $data->user;
	
	// data required for wordpress user
	// firstname, lastname and email
	if(rpsl_is_empty($user_data->firstName) || rpsl_is_empty($user_data->lastName) || rpsl_is_empty($user_data->email)) {
		http_response_code(400);
		$message = rpsl_generate_json_wperror(9010,"RapID Self Registration: Minimum data not supplied.");
		die($message);
	}

	// Select to see if session details match a known user registration request.
	$table_name = $wpdb->prefix . "rpsl_sessions";
	$select = $wpdb->prepare("SELECT COUNT(*) as howmany FROM " . $table_name . " WHERE rpsession = %s AND action = 'self_registration'", sanitize_text_field( $session ));
	$found  = $wpdb->get_var($select);
	
    if ($found <= 0) {
		http_response_code(500);
		$message = rpsl_generate_json_wperror(9011,"RapID Self Registration: Unable to locate session record with rp_session $session");
		die($message);
	}


	// up front, query wordpress users by loginname
	// if it exists, throw that specific error
	// otherwise throw generic.
	$loginname = sanitize_user($data->user->email, true);
	$user = get_user_by('login', $loginname);

	if(is_a($user, 'WP_User')){

		// A wordpress user already exists with that email, It could be a case that their initial registration failed
		// or the app crashed such that they attempted to collect a credential which didn't work. Restart the app
		// and then try to re-use their email. After the cleanup window that's not a problem but need to deal with
		// the during.

		$table_name = $wpdb->prefix . "rpsl_sessions";
		$select = $wpdb->prepare("SELECT * FROM " . $table_name . " WHERE loginname = %s AND action = 'self_collect'", $loginname);
		$previous_session_row = $wpdb->get_row( $select );
	
		if(rpsl_is_empty($previous_session_row)) {
			// Row didn't exist, duplicate email, reject
			http_response_code(400);
			$message = rpsl_generate_json_wperror(9012,"RapID Self Registration: User with that email already exists.");
			die($message);
		}
		
		// there was a row that matched this so a user is attempting to use an existing email address.
		// We need to check if that credential was collected.
		// check uuid is not empty ->
		$credential = rpsl_get_credential($previous_session_row->uuid);

		if(is_a($credential,'rpsl_Rapid_Error')) {

			$error_message = $credential->rawErrorMessage();			
			rpsl_diag("Error requesting credential $error_message");
		
			// if it's an 1109 , where it doesn't exist, we can say it wasn't collected, other errors we can't deal with so we return
			// a generic error
			if($credential->number != "1109") {
				rpsl_diag("Number is not equal to 1109 so erroring.");
				http_response_code(500);
				$response_message = rpsl_generate_json_wperror(9501,$error_message);
				die($response_message);
			}
		} else if(isset($credential->Status) && $credential->Status == "Collected") {
			// If credential status is set, and it is collected, that loop was closed, don't re-register
			rpsl_diag("The previous credential was collected: $uuid, can't re-register the user.");
			http_response_code(400);
			$message = rpsl_generate_json_wperror(9012,"RapID Self Registration: User exists and credential was collected, cannot re-register");
			die($message);
		} 

		// At this point we have confirmed that the user is re-registering and that their was a valid session with a credential that
		// was not collected. We can't re-request using the original UUID as this would violate non-uniqueness, So we request
		// With a new anon id. Improvement is to remove existing request
		rpsl_diag("The previous credential was not collected for the user with anon id: $previous_session_row->uuid. Requesting a new credential with new anon id $uuid");
		$user_id = $user->ID;

		// update the user in case any of the fields are different.
		// If this fails, we will continue anyway as the user does exist.
		wp_update_user( array(
						'ID'					=> $user_id,
						'first_name'			=> sanitize_text_field( $user_data->firstName ),
						'last_name'				=> sanitize_text_field( $user_data->lastName ),
					)
				);

		if(!rpsl_is_empty($previous_session_row->rpsession) && $previous_session_row->rpsession != $session) {
			// If the session row retrieved by loginname, has a rpsession field.
			// And that session doesn't equal the current session, remove it
			// When this code is complete we will update the existing one (new session)
			// Which would cause a key conflict
			// In addition, if a previous row has now been recovered, 
			// There will be a new row to replace it for future recovery if this operation failed
			// Only if it is the same session id should we keep it.
			rpsl_delete_session_row($previous_session_row->rpsession);
		}
	} else {
		// if not a user statement else.
		$password = wp_generate_password(12,true);
		$userinfo = array(
			'user_login'            => $loginname,
			'user_email'            => sanitize_text_field( $user_data->email ),
			'user_pass'             => $password, 
			'first_name'			=> sanitize_text_field( $user_data->firstName ),
			'last_name'				=> sanitize_text_field( $user_data->lastName  ),
			'nickname'				=> sanitize_text_field( $user_data->firstName ),
			'display_name'			=> sanitize_text_field( $user_data->firstName ),
		);

		$user_id = wp_insert_user( $userinfo );
	

		if( is_wp_error($user_id) ){

			// Wp Error Message
			$wp_error = $user_id->get_error_message();
			// Error creating wordpress user.
			$message = rpsl_generate_json_wperror(9013,"WordPress Error: $wp_error");
			rpsl_diag($message);
			http_response_code(500);
			die($message);
		}
	}

	// Use the new random user ID or existing one for the RapID service request
	$ht_sessionid = session_id();
	$rpsl_request_message = null;
	$rpsl_requestid = rpsl_get_new_rapid_request($uuid);


	if( is_a($rpsl_requestid,'rpsl_Rapid_Error') ) {

		$error_message = $rpsl_requestid->rawErrorMessage();			
		rpsl_diag("rpsl_self_registration_create_user error: $error_message");

		$response_message = rpsl_generate_json_wperror(9501,$error_message);
		// delete wordpress user as request error happened
		wp_delete_user( $user_id );
		http_response_code(500);
		die($response_message);
	}

	// Otherwise User was successfully created, We will now be expecting a P7 confirmation. 
	// Update the session row to now await the p7 using the previous challenge
	$table_name = $wpdb->prefix . "rpsl_sessions";
	$update_session = $wpdb->prepare
	              ("UPDATE " . $table_name . " SET loginname = %s, uuid = %s, rpsession = %s ,action = 'self_collect', requested = Now() WHERE rpsession = %s AND action = 'self_registration'", $loginname, $uuid, $uuid, $session );
	$updates = $wpdb->query( $update_session );
	
	// Return success message with request id.
	rpsl_diag("User Registered with anonid : $uuid");
	$message = json_encode(array("requestid"=>$rpsl_requestid));
	rpsl_create_session_token_file($session, "Successful Self Registration");
	die($message);
}
?>