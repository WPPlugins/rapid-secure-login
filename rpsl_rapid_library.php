<?php 
//*************************************************************************************
//*************************************************************************************
// Rapid Library Functions for WordPress Rapid Secure Login
//*************************************************************************************
//*************************************************************************************

// *******************************************************************************************************
//  Helper Method to instantiate the Rapid Service
// *******************************************************************************************************
function rpsl_get_rapid_service()
{
	$sac_certificate = rpsl_db_get_active_sac_certificate();

	if(empty($sac_certificate)){
		throw new \Exception("No authentication certificate found to request an identity");
	}

	$rpsl_server_credentials = array(	
									"cert" 		=> $sac_certificate->public_key,
									"key"  		=> $sac_certificate->private_key,
									"passphrase" 	=> rpsl_keyfile_password(),
									"certfolder"	=> rpsl_cert_folder(),
									"tempfolder"	=> rpsl_temp_folder(),
								);

	$rapidservice = new \Intercede\Rapid($rpsl_server_credentials);

	if (!is_object($rapidservice)) {
		throw new \Exception("Unable to create the RapID Service object");
	}

	return $rapidservice;
}


//*************************************************************************************
// Given a UUID, Returns the anon id record from the Rapid Service
//*************************************************************************************
function rpsl_get_credential($rpsl_uuid) {

	try {	
		// Call to the RapID request function to submit the request and get back the requestID
		$rapid_service = rpsl_get_rapid_service();

		$rpsl_credential_response = $rapid_service->get_credential_by_anonid( $rpsl_uuid );
				
		if(isset($rpsl_credential_response->AnonId)) {
			return $rpsl_credential_response;
		}
		//If message is set, an error has occurred, set up the correct message
		elseif(isset($rpsl_credential_response->Message)){
			$rapid_error = rpsl_Rapid_Error::withJsonResponse($rpsl_credential_response);
			return $rapid_error;		
		}
		else
		{
			throw new \Exception("Error: Get Anon ID method returned with no exception, but no message was set");
		}
	} catch (Exception $e) {	
		$exception_message = $e->getMessage();
		rpsl_diag("Error: unable to connect to RapID Service $exception_message \n");
		$rapid_error = rpsl_Rapid_Error::withMessage($exception_message);
		return $rapid_error;
	}
}

//*************************************************************************************
// Given a UUID, generate a RapID credential request
//*************************************************************************************
function rpsl_get_new_rapid_request($rpsl_uuid) {
	
	try {
		// Call to the RapID request function to submit the request and get back the requestID
		$rapid_service = rpsl_get_rapid_service();
		$rpsl_rapid_response = $rapid_service->request( $rpsl_uuid );

		// If the identifier is set, then we return that as request id
		if(isset($rpsl_rapid_response->Identifier)) {
			return $rpsl_rapid_response->Identifier;
		}
		// If message is set, an error has occurred, set up the correct message
		elseif(isset($rpsl_rapid_response->Message)){
			$rapid_error = rpsl_Rapid_Error::withJsonResponse($rpsl_rapid_response);
			return $rapid_error;
		}
		else
		{
			throw new \Exception("request method returned with no exception, but no message or identifier was set");
		}
	} catch (Exception $e) {	
		$exception_message = $e->getMessage();
		rpsl_diag("rpsl_get_new_rapid_request error: $exception_message");
		$rapid_error = rpsl_Rapid_Error::withMessage($exception_message);
		return $rapid_error;
	}
}

/*************************************************************
 * rpsl_Rapid_Error_class
*************************************************************/
class rpsl_Rapid_Error {
	
	public $message;
	public $number;

    public function __construct() {
        
    }

	public static function withJsonResponse($json = false) {
		$instance = new self();
		if ($json) {
			$instance->message = $json->Message;
			$instance->number = $json->Number;
		}

		return $instance;
	}

	public static function withMessage($error) {
		// This is a non RapID Error caused by an exception,
		// We still want to declare an error.
		$instance = new self();		
		$instance->message = $error;
		$instance->number  = 9999;

		return $instance; 
	}

	public function rawErrorMessage() {
		return "Number: $this->number : $this->message";
	}

	//*************************************************************************************
	// Given a Rapid Request Response, Return a normalised error string.
	// Must prefix any error message with Error: for some of the other calling code.
	//*************************************************************************************
	public function rpsl_extract_rapid_service_message()
	{
		switch($this->number)
		{
			case 1402:
				return "Error: please contact your system administrator, there are no more remaining licences for new credentials.";
			case 9999:
				// Generic Wordpress Error, not recoverable.
				return "Error: please contact your system administrator.";
			default:
				return "Error: please contact your system administrator with error code: $this->number";
			break;
		}
	}
}

?>