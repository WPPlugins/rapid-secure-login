<?php
//*************************************************************************************
//*************************************************************************************
//   LOGIN / AUTHENTICATION
//*************************************************************************************
//*************************************************************************************


//*************************************************************************************
//   WORDPRESS LOGIN page - COMPLETE SECTION FOR USE AS SHORTCODE
//*************************************************************************************
function rpsl_wplogin_fullpage_html($attributes) {
// Provides the HTML content to inject into the WP Login form
	$logoimg     = esc_url(plugin_dir_url( __FILE__ ) . "images/rapidwm.png"); 
	$blank       = esc_url(plugin_dir_url( __FILE__ ) . "images/white.jpg"); 
	$adminUrl    = esc_url(rpsl_append_admin_url());
	
	$show_login  = rpsl_show_login() != "No";  // Yes, No or Click

	$attributes = shortcode_atts( array(
		'showregister' => "true",
		'redirect_to'  => "/",
	), $attributes, 'rpsl_secure_login' );

	$showRegisterLink   = $attributes["showregister"] === "true";

	$redirect_to = $attributes["redirect_to"];
	if (isset($_GET['redirect_to'])){ $redirect_to = $_GET['redirect_to'];}
	
	// i18n Strings
	$mPleaseUseTheApp = __('Use the <a href="https://www.intercede.com/rapidsl" target="_blank">RapID Secure Login</a> app to scan the QR Code, or just tap the code on your phone.', 'rp-securelogin');
	$mGenerating      = esc_html__('Generating the login code', 'rp-securelogin');
	$mRegister        = esc_html__('Register for a new account', 'rp-securelogin');
	$mLostPassword    = esc_html__('Lost your password?', 'rp-securelogin');
	$mScanWithRapid   = esc_html__('Scan with RapID to sign in - No More Passwords!', 'rp-securelogin');
	$mPasswordShown   = esc_html__('Or enter your username and password below', 'rp-securelogin');

	$mClickToShow = ($show_login
		? esc_html__('Click to show password fields', 'rp-securelogin')
		: esc_html__('Password login has been disabled for this site', 'rp-securelogin'));
	$clickHandler = ($show_login
		? 'onClick = "rpsl_toggle_password_display();" '
		: '');
	$hiddenDiv =  ($show_login
		? ''
		: 'style="display:none"');
	
	// For a shortcode, we must RETURN the output, not just echo it, or it appears in the wrong place
	$content = "";
	$content .= '<div>';
	$content .= '	<form name="loginform" id="loginform" action="' . wp_login_url() . '?redirect_to=' . esc_html($redirect_to) . '" method="post" style="text-align: left;"> ';  
	$content .= '		<div class="rpsl_outer_div" id="rpsl_wplogin_div">';
	$content .= '		<span>' . $mPleaseUseTheApp . '</span><br/><br/>';
	$content .= '	<div class="rpsl_qr_div">';
	$content .= '		<a id="rpsl_login_url" href="">';
	$content .= '		<img class="rpsl_qr_img" id="rpsl_login_qr" name="rpsl_login_qr" src="' . $blank . '" title="' . $mScanWithRapid . '" />';
	$content .= '  		</a>';
	$content .= '			<div>';
	$content .= '				<img class="rpsl_logo_img_click" id="rapidlogo" name="rapidlogo" src="' . $logoimg . '" title="' . $mClickToShow . '" ' . $clickHandler . '/>';
	$content .= '			</div>';
	$content .= '		<span id="rpsl_login_status" >'  . $mGenerating . '</span>';
	$content .= '		<br/><br/><span class="rpsl_login_error" name="rpsl_login_error" id="rpsl_login_error"></span>';
	$content .= '		</div>';
	$content .= '	</div>';
	
	$content .= '	<div id="loginfields" ' . $hiddenDiv . '>';
	$content .= '		<span id="PasswordMessage">' . $mPasswordShown . '</span><br/><br/>';
	$content .= '		<label>Username: <input type="text"     name="log"        id="user_login" value="" size="20"    tabindex="1" /></label><br /> ';  
	$content .= '		<label>Password: <input type="password" name="pwd"        id="user_pass"  value="" size="20"    tabindex="2" /></label><br /> '; 
	$content .= '						 <input type="submit"   name="rp-submit"  id="rp-submit"  value="Login &raquo;" tabindex="4" />&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;';
	$content .= '		<div id="lost" style="float: right">';
	$content .= '			<a href="' . wp_login_url() . '?action=lostpassword' . '">' . $mLostPassword . '</a>';
	$content .= '		</div>';
	$content .= '	</div>';
	$content .= '	<input type="hidden" name="redirect_to" value="' . $redirect_to . '" /> ';
	$content .= '</form>';
			
	if($showRegisterLink){
		$content .= '<br/>	';
		$content .= '<p id="register" >';
		$content .= '	<a href="' .  wp_login_url() . '?action=register'. '">' . $mRegister . '</a>'; 	
		$content .= '</p>';
	}
	$content .= '</div>';
	$content .= rpsl_wplogin_fullpage_script();
	
	return $content;
}

//*************************************************************************************
//   WORDPRESS LOGIN page - add the scripts for the Shortcode login page
//*************************************************************************************
function rpsl_wplogin_fullpage_script() {

	// i18n Strings
	$mWaitingForAuth  = esc_html__('Waiting for authorization', 'rp-securelogin');
	$mAuthorized      = esc_html__('Authorized for user ', 'rp-securelogin');
 	$toggle_login     = rpsl_show_login() === "Click";  // Yes, No or Click
	$mPasswordShown   = esc_html__('Or enter your username and password below', 'rp-securelogin');
	$mPasswordHidden  = esc_html__('Or click the RapID logo to use a password', 'rp-securelogin');

	// For a shortcode, we must RETURN the output, not just echo it, or it appears in the wrong place
	$content = "";
	$content .= '<script type="text/javascript" >';
	$content .= '	var rpsl_wplogin_diags    = false; ';    // Diagnostics flag
	$content .= '	var rpsl_wplogin_dots     = ""; ';
	$content .= '	var rpsl_ajaxurl  = "' . RPSL_Configuration::rpsl_ajax_endpoint() . '"; ';
	$content .= '	var rpsl_wplogin_ajaxurl  = "' . admin_url('admin-ajax.php') . '"; ';
	$content .= '	var rpsl_wplogin_adminurl = "' . rpsl_append_admin_url() . '"; ';
	$content .= '	var rpsl_wplogin_delim    = "' . rpsl_delim() . '"; ';
	$content .= '	var rpsl_wplogin_session  = "";';
	$content .= '	var rpsl_wplogin_timeout; if (typeof rpsl_wplogin_timeout === "undefined") rpsl_wplogin_timeout = 0;';
	$content .= '	var rpsl_wplogin_code_requested;        ';// Needed to stop duplicate requests
	$content .= '	var rpsl_wplogin_pwform_visible = true; ';// Track state of password form
	$content .= "	var rpsl_refresh_qrcode = false; ";
	$content .= "	var rpsl_refresh_timeout; ";
		
	$content .= '	function rpsl_checkAuth() {  '; // Checks whether this session has been authenticated yet
	$content .= '		jQuery.ajax({';
	$content .= '			url: rpsl_ajaxurl,';
	$content .= '			type: \'POST\',';
	$content .= '			data: {	\'action\' :\'rpsl_check_login\', \'rpsession\' : rpsl_wplogin_session},';
	$content .= '			dataType: \'json\',';
	$content .= '			success:function(data) {';
	$content .= '				var rpStatus = data.status;';
	$content .= '				rpsl_wplogin_dots += "."; ';
	$content .= '				if (rpsl_wplogin_dots.length > 10) { rpsl_wplogin_dots = ".";}';
					
	$content .= '				if (rpStatus == "request_expired") {	';		// We must refresh the QR code
	$content .= '					window.location.reload(true);';
					   
	$content .= '				} else if (rpStatus == "waiting_for_auth") {';	// Wait and try again
	$content .= '					rpsl_setLoginStatusMessage("' . $mWaitingForAuth . '" + rpsl_wplogin_dots);';
	$content .= '					rpsl_poll_for_login();';
					   
	$content .= '				} else if (rpStatus.substr(0,2) == "zz") {	';    // Diagnostics
	$content .= '					rpsl_setLoginStatusMessage(rpStatus);';
	$content .= '					rpsl_poll_for_login();';

	$content .= '				} else if (rpStatus == "waiting_for_association") {	'; // Not meant for this page! Ignore
	$content .= '					rpsl_poll_for_login();';

	$content .= '				} else if(rpStatus == "error") {'; 
	$content .= '					rpsl_setLoginStatusMessage(data.message);';
					
	$content .= '				} else {	';									// Login name is set - submit
	$content .= '					if (rpStatus.trim() != "" ) {';
	$content .= '						rpsl_setLoginStatusMessage("' . $mAuthorized . '" + rpStatus);';
	$content .= '						rpsl_setNamedValue("user_login", "RapID-User");';
	$content .= '						rpsl_setNamedValue("user_pass", rpsl_wplogin_session);';
	$content .= '						document.getElementById("rp-submit").click(); ';
	$content .= '					}';
	$content .= '				}';
	$content .= '			},';
	$content .= '			error: function(errorThrown){';			
	$content .= '				rpsl_setLoginErrorMessage("An error occurred, please refresh the page to re-attempt to login.");';
	$content .= '			}';
	$content .= '		});   ';
	$content .= '	};	';

	$content .= '	function rpsl_poll_for_login() {';
					// Sessions will timeout, to cover this, refresh the QR code if this token is set
					// Has to be part of poll for login as it will override the assoc session variable
					// Which has to be correct for the form post.
	$content .= "		if(rpsl_refresh_qrcode) {";
	$content .= "			rpsl_refresh_qrcode = false; ";
	$content .= "			rpsl_wplogin_code_requested = false; ";
	$content .= "			rpsl_setLoginQR(); ";
	$content .= "			return; ";
	$content .= "		} ";
						// Only set timeout if it is not 0
	$content .= "		if(!rpsl_refresh_timeout) { rpsl_refresh_timeout = setTimeout(rpsl_refreshQRCodeToken, 1080000); } ";
						// Avoid multiple polling timeouts from duplicate sections on the form
	$content .= '		if (rpsl_wplogin_timeout != 0) clearTimeout(rpsl_wplogin_timeout);';
	$content .= '		rpsl_wplogin_timeout = setTimeout(rpsl_checkAuth, ' . RPSL_Configuration::$rpsl_ajax_timeout . ');';
	$content .= '	}';

	$content .= "	function rpsl_refreshQRCodeToken() { ";
	$content .= "		rpsl_refresh_qrcode = true; ";
	$content .= "	} ";

	$content .= '	function rpsl_setLoginImages(base64src, data){';
	$content .= '		jQuery("#rpsl_login_qr").attr("src",base64src);';
	$content .= '		jQuery("#rpsl_login_url").attr("href", "rapid02://qr?sess=" + data);';
	$content .= '	};';
		
	$content .= '	function rpsl_setLoginErrorMessage(message){';
	$content .= '		rpsl_setRawErrorMessage(message);';
	$content .= '	};';
	$content .= '	function rpsl_setRawErrorMessage(message){';
	$content .= '		try { document.getElementById("rpsl_login_error").innerHTML = message; } catch(e) {};';
	$content .= '	};';
		
	$content .= '	function rpsl_setLoginStatusMessage(message){';
	$content .= '		try { document.getElementById("rpsl_login_status").innerHTML = message; } catch(e) {};';
	$content .= '	};';
		
	$content .= '	function rpsl_setNamedValue(tagName, value){';
	$content .= '		try { document.getElementById(tagName).value = value; } catch(e) {};';
	$content .= '	};';
		
	$content .= '	function rpsl_setLoginQR() { '; // Calls to the server to get the QR code src
	$content .= '		if (!rpsl_wplogin_code_requested) {';
	$content .= '			rpsl_wplogin_code_requested = true;';
	$content .= '			jQuery.ajax({';
	$content .= '				url :  rpsl_wplogin_ajaxurl, type: \'POST\', data: {\'action\' :\'rpsl_generate_login_qrcode\'},	dataType: \'json\',';
	$content .= '				success:  function(data) {';
	$content .= '							if(data.status == "error"){    ';
	$content .= '								rpsl_setLoginErrorMessage("Error - Cannot create QR: " + data.message);';
	$content .= '								return;      ';  
	$content .= '							} else { '; 
	$content .= '								rpsl_wplogin_session = data.rpsessionid;  ';  
	$content .= '								rpsl_setLoginImages(data.qrbase64, data.qrdata);  '; 
	$content .= '								rpsl_setLoginStatusMessage("' . $mWaitingForAuth . '");';
	$content .= "								clearTimeout(rpsl_refresh_timeout); ";
	$content .= "								rpsl_refresh_timeout = null; ";
	$content .= '								rpsl_poll_for_login();';
	$content .= '							} ';
	$content .= '						},';
	$content .= '				error:   function(errorThrown){';
	$content .= '							rpsl_setLoginErrorMessage("Error - Cannot create QR: " + errorThrown); ';
	$content .= '						}';
	$content .= '			}); ';
	$content .= '		};';
	$content .= '	};	';
		
	$content .= '	function rpsl_toggle_password_display() {';
		// Turns the password login fields on/off
	$content .= '		if (rpsl_wplogin_pwform_visible) {';
	$content .= '			document.getElementById("loginfields").style.display = "none";';
	$content .= '			document.getElementById("PasswordMessage").innerText = "' . $mPasswordHidden . '";';
	$content .= '			rpsl_wplogin_pwform_visible = false;';
	$content .= '		} else {';
	$content .= '			document.getElementById("loginfields").style.display = "block";';
	$content .= '			document.getElementById("PasswordMessage").innerText = "' . $mPasswordShown . '";';
	$content .= '			rpsl_wplogin_pwform_visible = true;';
	$content .= '		}';
	$content .= '	};';

	if($toggle_login) { $content .= ' rpsl_toggle_password_display();' ; }

	$content .= '	jQuery(document).ready(function($) { ';
	$content .= '		rpsl_setLoginQR(); ';
	$content .= '	})';
		
	$content .= '</script>';
	
	return $content;
}

add_shortcode( 'rpsl_secure_login', 'rpsl_wplogin_fullpage_html' );