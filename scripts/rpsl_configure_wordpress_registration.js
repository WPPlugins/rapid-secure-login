		jQuery(document).ready(function($) {
			rpsl_setSiteRegistrationQR();
		});

		var rpsl_sessionToken   = "";
		var rpsl_dotMessage      = ".";		
		var rpsl_checkSiteRegistration_timeout = 0;
		var rpsl_refresh_qrcode = false;
		var rpsl_refresh_timeout;
		
		function rpsl_setSiteRegistrationQR() {  // Calls to the server to get the QR code src
			rpsl_dotMessage = ""; 
			jQuery('[name="rpsl_reconfigure_message"]').text('');
			jQuery.ajax({
				url :  rpsl_admin_ajaxurl, type: 'POST', data: {'action' :'rpsl_generate_site_registration_qrcode'},	dataType: 'json',
				success:  function(data) {
							rpsl_sessionToken = data.rpsessionid;
							rpsl_setupQRCodeImage(data.qrbase64, data.qrData); // src and url data for the image
							rpsl_showQR(true);
							rpsl_setQRMessage("Waiting for site registration" + rpsl_dotMessage);
							clearTimeout(rpsl_refresh_timeout);
							rpsl_refresh_timeout = null;
							rpsl_poll_for_siteConfiguration();
						},
				error:   function(request, statusText, errorText){
							rpsl_setQRMessage("Error: " + request.status + ", Retrying...");
							rpsl_poll_for_siteConfiguration();
						}
			});   
		}	

		function rpsl_checkSiteConfigured() {  // Calls to the server to check whether the SL App has uploaded the certificates.
			rpsl_dotMessage += "."; if(rpsl_dotMessage.length > 10) rpsl_dotMessage = ".";
			jQuery.ajax({
				url :  rpsl_ajaxurl, type: 'POST', data: {'action' :'rpsl_check_site_configured', 'rpsession' : rpsl_sessionToken},	dataType: 'json',
				success:  function(data) {
							if (data.status == "error" ) {
								rpsl_setQRMessage(data.message);
								rpsl_showQR(false); // Swap back to static logo
							}
							if (data.status == "ok") {
								rpsl_showQR(false); // Swap back to static logo
								rpsl_setQRMessage("Site configured... refreshing...");
								setTimeout(function(){ window.location = window.location.href.split("?")[0] + "?page=rpsl-plugin-settings";}, 2000);								
							} else {
							    rpsl_setQRMessage(data.status + rpsl_dotMessage);
								rpsl_poll_for_siteConfiguration();
							}
						},
				error:   function(request, statusText, errorText){
							rpsl_setQRMessage("Error: " + request.status + ", Retrying...");
							rpsl_poll_for_siteConfiguration();
						}
			});   
		}

		function rpsl_poll_for_siteConfiguration() {
				
			// Sessions will timeout, to cover this, refresh the QR code if this token is set
			// Has to be part of poll for login as it will override the assoc session variable
			// Which has to be correct for the form post.
			if(rpsl_refresh_qrcode)	{
				rpsl_refresh_qrcode = false;
				rpsl_wplogin_code_requested = false;
				rpsl_setSiteRegistrationQR();
				return;
			}

			// Only set timeout if it is not 0
			if(!rpsl_refresh_timeout) { rpsl_refresh_timeout = setTimeout(rpsl_refreshQRCodeToken, 1080000); }
			
			if (rpsl_checkSiteRegistration_timeout !== 0) clearTimeout(rpsl_checkSiteRegistration_timeout)
			rpsl_checkSiteRegistration_timeout =	setTimeout(rpsl_checkSiteConfigured, rpsl_timeout);
		}

		function rpsl_refreshQRCodeToken()
		{
			rpsl_refresh_qrcode = true;
		}
		
		function rpsl_setupQRCodeImage(base64src, data){
			jQuery('[name="rpsl_site_registration_qr"]').attr("src", base64src);
			jQuery('[name="rpsl_site_registration_url"]').attr("href", "rapid02://qr?sess=" + data);
		}
		
		function rpsl_showQR(show){			
			jQuery('[name="rpsl_big_logo"]').toggle(!show);
			jQuery('[name="rpsl_qr_code"]').toggle(show);
			jQuery('[name="rpsl_small_logo"]').toggle(show);
		}
				
		function rpsl_setQRMessage(mess){

			if(mess.indexOf("[object Object]") > 0)
			{
				mess = "Error: Session has timed out. Please refresh the page";
			}
		
			jQuery('[name="rpsl_qr_message"]').text(mess);
		}
		

