			jQuery(document).ready(function($) {
			    rpsl_setNewAccountQR();

			    jQuery('#rpsl_rapidlogon_container').prependTo('#registerform');
			    jQuery('#rpsl_rapidlogon_container').fadeIn();
			})

			var rpsl_newacc_session = "";
			var rpsl_newacc_dots = "";
			var rpsl_checkUserInfo_timeout;
			if (typeof rpsl_checkUserInfo_timeout === 'undefined') rpsl_checkUserInfo_timeout = 0;
			var rpsl_refresh_qrcode = false;
			var rpsl_refresh_timeout;

			function rpsl_setNewAccountQR() { // Calls to the server to get the QR code src
			    jQuery.ajax({
			        url: rpsl_admin_ajaxurl,
			        type: 'POST',
			        data: {
			            'action': 'rpsl_generate_self_registration_qrcode'
			        },
			        dataType: 'json',
			        success: function(data) {
			            if (data.status == "error") {
			                rpsl_setNewAccountPrompt("Unable to register using RapID Secure Login: " + data.message);
			                jQuery("#rpsl_newacc_qrdiv").hide();
			                return;
			            } else {
			                rpsl_newacc_session = data.rpsessionid;
			                rpsl_setNewAccountImages(data.qrbase64, data.qrdata);
			                clearTimeout(rpsl_refresh_timeout);
			                rpsl_refresh_timeout = null;
			                rpsl_poll_for_user_data();
			            }
			        },
			        error: function(errorThrown) {
			            rpsl_setNewAccountErrorMessage("Error: " + errorThrown);
			        }
			    });
			};

			function rpsl_poll_for_user_data() {

			    // Sessions will timeout, to cover this, refresh the QR code if this token is set
			    // Has to be part of poll for login as it will override the assoc session variable
			    // Which has to be correct for the form post.
			    if (rpsl_refresh_qrcode) {
			        rpsl_refresh_qrcode = false;
			        rpsl_wplogin_code_requested = false;
			        rpsl_setNewAccountQR();
			        return;
			    }

			    // Only set timeout if it is not 0
			    if (!rpsl_refresh_timeout) {
			        rpsl_refresh_timeout = setTimeout(rpsl_refreshQRCodeToken, 1080000);
			    }

			    // Avoid multiple polling timeouts from duplicate sections on the form
			    if (rpsl_checkUserInfo_timeout != 0) clearTimeout(rpsl_checkUserInfo_timeout);
			    rpsl_checkUserInfo_timeout = setTimeout(rpsl_checkUserInfo, rpsl_timeout);
			}

			function rpsl_refreshQRCodeToken() {
			    rpsl_refresh_qrcode = true;
			}

			function rpsl_setNewAccountImages(base64src, data) {
			    jQuery('[name="rpsl_newacc_qr"]').attr("src", base64src);
			    jQuery('[name="rpsl_qr_url"]').attr("href", "rapid02://qr?sess=" + data);
			};

			function rpsl_setNewAccountErrorMessage(mess) {
			    var message = mess;
			    if (message == "Error: [object Object]") {
			        message = "Errors: Session has timed out. Please refresh the page";
			    }

			    jQuery('[name="rpsl_newacc_error"]').html(message);
			};

			function rpsl_setNewAccountPrompt(mess) {
			    jQuery('[name="rpsl_newacc_prompt"]').html(mess);
			};

			function rpsl_checkUserInfo() { // Checks whether user details have been supplied yet
			    jQuery.ajax({
			        url: rpsl_ajaxurl,
			        type: 'POST',
			        data: {
			            'action': 'rpsl_check_self_registration',
			            'rpsession': rpsl_newacc_session
			        },
			        dataType: 'json',
			        success: function(rpsl_newacc_data) {

			            rpsl_newacc_dots += ".";
			            if (rpsl_newacc_dots.length > 10) {
			                rpsl_newacc_dots = ".";
			            }


			            if (rpsl_newacc_data.status == 100) {
			                // waiting registration
			                rpsl_setNewAccountErrorMessage(rpsl_newacc_data.message + rpsl_newacc_dots);
			                rpsl_poll_for_user_data();
			                return;
			            }

			            if (rpsl_newacc_data.status == 200) {
			                var url = [location.protocol, '//', location.host, location.pathname].join('');
                            window.location = url;
			            }
			        },
			        error: function(errorThrown) {
			            rpsl_setNewAccountErrorMessage("Error: " + errorThrown);
			        }
			    });
			};