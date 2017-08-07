<?php
//*************************************************************************************
//*************************************************************************************
//   EDIT MY DEVICE Shortcode - add the Register QR Code and Device List
//
//   [rpsl_my_devices showheader='true' showblurb='true' showregister='true']
//
//*************************************************************************************
//*************************************************************************************

function rpsl_my_devices_html($attributes) {
// Allow attributes in the shortcode to limit what gets shown
	$attributes = shortcode_atts( array(
		'showregister'  => "true",
		'showheader'    => "true",
		'showblurb'     => "true"
	), $attributes, 'rpsl_my_devices' );

	$show_credential = current_user_can( RPSL_Configuration::$rpsl_can_have_credential_role );
	$showregister = $attributes["showregister"] === "true";
	$showheader   = $attributes["showheader"  ] === "true";
	$showblurb    = $attributes["showblurb"   ] === "true";

	// This part of Wordpress uses tables for layout rather than divs
	$logoimg = esc_url( plugin_dir_url( __FILE__ ) . "images/rapidwm.png" ); 
	$logobig = esc_url( plugin_dir_url( __FILE__ ) . "images/rapid.png" ); 
	$blank   = esc_url( plugin_dir_url( __FILE__ ) . "images/white.jpg" ); 
	$user = wp_get_current_user();
	if ( !($user instanceof WP_User) ) { return "You must be logged in to managed your devices!"; }
		
	$target_user_id = $user->ID;
	if ( $target_user_id == 0 ) { return "You must be logged in to manage your devices!"; }
	
	// i18n Strings
	$mHeader          = __('RapID Secure Login - No more passwords!', 'rp-securelogin');
	$mInstructions    = __('Use RapID for simple, secure login to WordPress. ', 'rp-securelogin');
	$mInstructions   .= __('Just get the free RapID Secure Login app from <a href=\'https://play.google.com/store/apps/details?id=com.intercede.rapidsl&hl=en_GB\' target=\'_blank\'>Google Play</a> or the <a href=\'https://itunes.apple.com/us/app/rapid-secure-login/id1185934781?mt=8\' target=\'_blank\'>AppStore</a>.<br/>', 'rp-securelogin');
	$mInstructions   .= __('Then click or tap on the RapID logo below and scan the QR code with your RapID Secure Login app. ', 'rp-securelogin');
	$mInstructions   .= __('The next time you login to this site, just scan the code and use your finger or a simple PIN. ', 'rp-securelogin');
	$mInstructions   .= esc_html__('The same app can log into multiple accounts on different WordPress sites.', 'rp-securelogin');
	$mUseRapid        = esc_html__('Use RapID - No More Passwords!', 'rp-securelogin');
	$mClickToAdd      = esc_html__('Click or tap the logo to request a RapID credential', 'rp-securelogin');
	$mNewCredAdded    = esc_html__('Your new RapID credential has been added', 'rp-securelogin');
	$mScanPrompt2     = esc_html__('Scan or tap the QR code with the RapID app to create a new RapID credential', 'rp-securelogin');
	$mChecking        = esc_html__('Waiting for credential collection', 'rp-securelogin');
	$mPleaseWait      = esc_html__('Please wait while a credential is prepared', 'rp-securelogin');
	$mConfirmRemove   = esc_html__('You are about to remove this device from your account. Are you sure?', 'rp-securelogin');
	$mDeniedByRole    = esc_html__('Your role does not let you make use of a RapID login, contact your site administrator to request access.', 'rp-securelogin');
	$mNewName         = esc_html__('Please enter a new name for this device', 'rp-securelogin');

	$content = "";
	if ($showheader) {$content .= "<h5>$mHeader</h5> ";};
	if ($showblurb && $show_credential)  { $content .= "<p>$mInstructions</p> "; };
	if (!$show_credential) { $content .= "<p>$mDeniedByRole</p>"; }
	$content .= "<span name='rpsl_device_list'>" . rpsl_dump_devices_raw($target_user_id) . "</span><br/><br/> ";
	
	if ($showregister && $show_credential) {
		$content .= "<span name='rpsl_assoc_prompt'>$mClickToAdd</span> ";
		$content .= "<p> ";
		$content .= "<div class=\"rpsl_outer_div\"> ";
		$content .= "	<div class=\"rpsl_qr_div\" id=\"rpsl_big_logo\" name=\"rpsl_big_logo\"> ";
		$content .= "		<img class=\"rpsl_qr_img\" src=\"$logobig\" title=\"$mClickToAdd\"  onClick=\"rpsl_newRequest();\" /> ";
		$content .= "	</div> ";
		$content .= "	<div class=\"rpsl_qr_div\" id=\"rpsl_qr_code\" name=\"rpsl_qr_code\" style=\"display:none\"> ";
		$content .= "		<a name=\"rpsl_assoc_url\" href=''> ";
		$content .= "		<img class=\"rpsl_qr_img\"   id=\"rpsl_assoc_qr\" name=\"rpsl_assoc_qr\" src=\"$blank\" title=\"$mClickToAdd\" /> ";
		$content .= "		</a> ";
		$content .= "		<div id=\"rpsl_small_logo\" name=\"rpsl_small_logo\" style=\"display:none\"> ";
		$content .= "			<img class=\"rpsl_logo_img_click\" id=\"rapidlogo\"    name=\"rapidlogo\"    src=\"$logoimg\" title=\"$mUseRapid\" onClick=\"rpsl_newRequest();\" /> ";
		$content .= "		</div> ";
		$content .= "	</div> ";
		$content .= "	<div class=\"rpsl_qr_div\"> ";
		$content .= "	   <span name='rpsl_assoc_error'>&nbsp;</span>";
		$content .= "   </div> ";
		$content .= "</div> ";
		$content .= "</p> ";
		
		$content .= "<script type='text/javascript' > ";
		$content .= "	var rpsl_newIdRequested = false; ";
		$content .= "	var rpsl_assocSession   = ''; ";
		$content .= "	var rpsl_assocDots      = '.'; ";
		$content .= "	var rpsl_user_id        = '$target_user_id'; ";
		$content .= "	var rpsl_admin_ajaxurl  = \"" . admin_url('admin-ajax.php') . '"; ';
		$content .= "	var rpsl_ajaxurl  = \"" . RPSL_Configuration::rpsl_ajax_endpoint() . '"; ';
		$content .= "	var rpsl_checkRegistered_timeout; if (typeof rpsl_checkRegistered_timeout === 'undefined') rpsl_checkRegistered_timeout = 0; ";
		$content .= "	var rpsl_refresh_qrcode = false; ";
		$content .= "	var rpsl_refresh_timeout; ";
		
		$content .= "	function rpsl_setRegistrationQR() { "; // Calls to the server to get the QR code src
		$content .= "		rpsl_assocDots = ''; ";
		$content .= "		jQuery.ajax({";
		$content .= "			url :  rpsl_admin_ajaxurl, type: 'POST', data: {'action' :'rpsl_generate_registration_qrcode'},	dataType: 'json', ";
		$content .= "			success:  function(data) { ";
		$content .= "						if(data.error){";
		$content .= "							rpsl_setAssocErrorMessage(data.error);";
		$content .= "							rpsl_hideQR();";
		$content .= "							return;";
		$content .= "						}";
		$content .= "						rpsl_assocSession = data.uuid; ";
		$content .= "						rpsl_setAssocImages(data.qrbase64, data.qrdata); "; // src and url data for the image ";
		$content .= "						rpsl_showQR(); ";
		$content .= "						rpsl_newIdRequested = true; ";
		$content .= "						rpsl_setAssocErrorMessage('$mChecking' + rpsl_assocDots); ";
		$content .= "						clearTimeout(rpsl_refresh_timeout); ";
		$content .= "						rpsl_refresh_timeout = null; ";
		$content .= "						rpsl_poll_for_registration(); ";
		$content .= "					}, ";
		$content .= "			error:   function(request, statusText, errorText){ ";
		$content .= "						rpsl_setAssocErrorMessage('Error: ' + request.status + ', Retrying...'); ";
		$content .= "						rpsl_poll_for_registration(); ";
		$content .= "					} ";
		$content .= "		});    ";
		$content .= "	};	 ";

		$content .= "	function rpsl_checkRegistered() { "; // Calls to the server to check whether the user has registered their new credential 
		$content .= "		rpsl_assocDots += '.'; if(rpsl_assocDots.length > 10) rpsl_assocDots = '.'; ";
		$content .= "		jQuery.ajax({ ";
		$content .= "			url :  rpsl_ajaxurl, type: 'POST', data: {'action' :'rpsl_check_registered', 'rpsession' : rpsl_assocSession},	dataType: 'json', ";
		$content .= "			success:  function(data) { ";
		$content .= "						if (data.status == 'ok') { ";
		$content .= "							rpsl_setAssocErrorMessage('$mNewCredAdded'); ";
		$content .= "							rpsl_refresh(); ";  // Swap back to static logo ";
		$content .= "						} else if (data.status == 'error') { ";
		$content .= "							rpsl_setAssocErrorMessage(data.message); ";
		$content .= "							rpsl_refresh(); ";  // Swap back to static logo ";
		$content .= "						} else { ";
		$content .= "						    rpsl_setAssocErrorMessage(data.status + rpsl_assocDots); ";
		$content .= "							rpsl_poll_for_registration(); ";
		$content .= "						} ";
		$content .= "					}, ";
		$content .= "			error:   function(request, statusText, errorText){ ";
		$content .= "						rpsl_setAssocErrorMessage('Error: ' + request.status + ', Retrying...'); ";
		$content .= "						rpsl_poll_for_registration(); ";
		$content .= "					} ";
		$content .= "		}); ";
		$content .= "	}; ";

		$content .= "	function rpsl_poll_for_registration() { ";
						// Sessions will timeout, to cover this, refresh the QR code if this token is set
						// Has to be part of poll for login as it will override the assoc session variable
						// Which has to be correct for the form post.
		$content .= "		if(rpsl_refresh_qrcode) {";
		$content .= "			rpsl_refresh_qrcode = false; ";
		$content .= "			rpsl_wplogin_code_requested = false; ";
		$content .= "			rpsl_setRegistrationQR(); ";
		$content .= "			return; ";
		$content .= "		} ";

							// Only set timeout if it is not 0
		$content .= "		if(!rpsl_refresh_timeout) { rpsl_refresh_timeout = setTimeout(rpsl_refreshQRCodeToken, 602000); } ";
							// Avoid multiple polling timeouts from duplicate sections on the form ";
		$content .= "		if (rpsl_checkRegistered_timeout != 0) clearTimeout(rpsl_checkRegistered_timeout); ";
		$content .= "		if (rpsl_newIdRequested) rpsl_checkRegistered_timeout =	setTimeout(rpsl_checkRegistered, " . RPSL_Configuration::$rpsl_ajax_timeout . "); ";
		$content .= "	} ";

		$content .= "	function rpsl_refreshQRCodeToken() { ";
		$content .= "		rpsl_refresh_qrcode = true; ";
		$content .= "	} ";

		$content .= "	function rpsl_setAssocImages(src, data){ ";
		$content .= "		var imgs = document.getElementsByName('rpsl_assoc_qr'); ";
		$content .= "		var i;		for (i = 0; i < imgs.length; i++){imgs[i].src = src; }; ";
		$content .= "		var links = document.getElementsByName('rpsl_assoc_url'); ";
		$content .= "		for (i = 0; i < links.length; i++){links[i].href = 'rapid02://qr?sess=' + data; }; ";
		$content .= "	}; ";
			
		$content .= "	function rpsl_showQR(){ ";
							//Hide the big logo and show the small one plus QRcode ";
		$content .= "		var biglogo   = document.getElementsByName('rpsl_big_logo');  ";
		$content .= "		for (i = 0; i < biglogo.length; i++){biglogo[i].style.display = 'none'}; ";

		$content .= "		var qrCodeDiv = document.getElementsByName('rpsl_qr_code'); ";
		$content .= "		for (i = 0; i < qrCodeDiv.length; i++){qrCodeDiv[i].style.display = 'block'}; ";

		$content .= "		var smalllogo = document.getElementsByName('rpsl_small_logo');  ";
		$content .= "		for (i = 0; i < smalllogo.length; i++){smalllogo[i].style.display = 'block'}; ";

		$content .= "		rpsl_setAssocPromptMessage('$mScanPrompt2'); ";
		$content .= "	}; ";
			
		$content .= "	function rpsl_hideQR(){ ";
							//Hide the big logo and show the small one plus QRcode ";
		$content .= "		var biglogo   = document.getElementsByName('rpsl_big_logo');  ";
		$content .= "		for (i = 0; i < biglogo.length; i++){biglogo[i].style.display = 'block'}; ";

		$content .= "		var qrCodeDiv = document.getElementsByName('rpsl_qr_code'); ";
		$content .= "		for (i = 0; i < qrCodeDiv.length; i++){qrCodeDiv[i].style.display = 'none'}; ";

		$content .= "		var smalllogo = document.getElementsByName('rpsl_small_logo');  ";
		$content .= "		for (i = 0; i < smalllogo.length; i++){smalllogo[i].style.display = 'none'}; ";

		$content .= "		rpsl_setAssocPromptMessage('$mClickToAdd'); ";
		$content .= "	}; ";
			
		$content .= "	function rpsl_setAssocErrorMessage(mess){ ";
		$content .= "		var spans = document.getElementsByName('rpsl_assoc_error'); ";
		$content .= "		var message = mess; ";
		$content .= "		if (message == 'Error: [object Object]') { message = 'Errors: Session has timed out. Please refresh the page'; } ";
		$content .= "		var i;		for (i = 0; i < spans.length; i++){	spans[i].innerHTML = message; }; ";
		$content .= "				}; ";
			
		$content .= "	function rpsl_setAssocPromptMessage(mess){ ";
		$content .= "		var spans = document.getElementsByName('rpsl_assoc_prompt'); ";
		$content .= "		var i;		for (i = 0; i < spans.length; i++){	spans[i].innerHTML = mess; }; ";
		$content .= "	}; ";
			
		$content .= "	function rpsl_setDeviceList(){ ";
		$content .= "		jQuery.ajax({ ";
		$content .= "			url :  rpsl_admin_ajaxurl, type: 'POST', data: {'action' :'rpsl_list_devices', 'user' : rpsl_user_id},	dataType: 'html', ";
		$content .= "			success:  function(data) { ";
		$content .= "					var spans = document.getElementsByName('rpsl_device_list'); ";
		$content .= "					var i;		for (i = 0; i < spans.length; i++){	spans[i].innerHTML = data; }; ";
		$content .= "					}, ";
		$content .= "			error:   function(errorObj, statusText, errorText){ ";
		$content .= "						rpsl_setAssocErrorMessage('Error: ' + statusText + ' - ' + errorText); ";
		$content .= "					} ";
		$content .= "		});    ";
		$content .= "	}; ";
			
		$content .= "	function rpsl_newRequest() {  "; // Creates a new RapID credential request and shows the request ID here ";
		$content .= "		if (!rpsl_newIdRequested) { ";
		$content .= "			rpsl_setAssocPromptMessage('$mPleaseWait'); ";
		$content .= "			rpsl_setAssocImages('$blank'); ";
		$content .= "			rpsl_setRegistrationQR(); ";
		$content .= "		} ";
		$content .= "	}; ";

						// Device Management functions ";
		$content .= "	function rpsl_delete_device(uuid) { ";
						// Delete the given device. Only allowed from a logged in session.  ";
						// Called from the table generated by rpsl_dump_devices ";
		$content .= "		var carryon = confirm('$mConfirmRemove'); ";
		$content .= "		if (carryon) { ";
		$content .= "			jQuery.ajax({ ";
		$content .= "				url :  rpsl_admin_ajaxurl, type: 'POST', data: {'action' :'rpsl_delete_device', 'uuid' : uuid},	dataType: 'html', ";
		$content .= "				success:  function(data) { ";
		$content .= "							rpsl_setDeviceList(); ";  //alert('Device deleted ok'); ";
		$content .= "						}, ";
		$content .= "				error:   function(errorObj, statusText, errorText){ ";
		$content .= "							rpsl_setAssocErrorMessage('Error: ' + statusText + ' - ' + errorText); ";
		$content .= "						} ";
		$content .= "			}); ";
		$content .= "		} ";
		$content .= "	}; ";

		$content .= "	function rpsl_rename_device(uuid, oldname) { ";
						// Rename the given device. Only allowed from a logged in session. ";
						// Called from the table generated by rpsl_dump_devices ";
		$content .= "		var newname = prompt('$mNewName', oldname); ";
		$content .= "		if (newname != null) { ";
		$content .= "			jQuery.ajax({ ";
		$content .= "				url :  rpsl_admin_ajaxurl, type: 'POST', data: {'action' :'rpsl_rename_device', 'uuid' : uuid, 'name' : newname},	dataType: 'html', ";
		$content .= "				success:  function(data) { ";
		$content .= "							rpsl_setDeviceList(); "; //alert(data); ";
		$content .= "						}, ";
		$content .= "				error:   function(errorObj, statusText, errorText){ ";
		$content .= "							rpsl_setAssocErrorMessage('Error: ' + statusText + ' - ' + errorText); ";
		$content .= "						} ";
		$content .= "			}); ";
		$content .= "		} ";
		$content .= "	}; ";
			
		$content .= "	function rpsl_refresh() { ";
		$content .= "		rpsl_hideQR(); rpsl_setDeviceList(); ";
		$content .= "		rpsl_newIdRequested = false; ";
		$content .= "	} ";
			
		$content .= "	jQuery(document).ready(function($) { rpsl_refresh(); }) ";

		$content .= "</script> ";

	};

	return $content;
}

add_shortcode( 'rpsl_my_devices', 'rpsl_my_devices_html' );