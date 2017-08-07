<?php
/*
 * This file is part of the MyID RapID package.
 */
namespace Intercede;

class Rapid 
{
    // A Rapid object talks to the RapID Auth service.  RapID uses TLS to validate
    // authenticity of messages and must be provided with an authentication certificate.
    // Service authentication certificates are downloaded from the Customer Portal. 
    public function __construct($key)
    {
        $this->rapid_client_key = $key;
        $rapid_base_url = $this->parse_rapid_url("request.rapidauth.com/rapid");
        $this->rapid_request_url = $rapid_base_url  . "1.0/RequestCredentialEx";
        $this->rapid_credentials_url = $rapid_base_url . "credentials/";
    }

    public function request($subject) {
        $payload = array("SubjectName" => $subject);
        $response = $this->send_request(\Httpful\Http::POST, json_encode($payload), $this->rapid_request_url);

        if(isset($response->body->Identifier) || isset($response->body->Message)) {
            // If a valid json identifier, or message is set 
            // The request was satisfied by the Rapid Service but we have an error or a valid
            // Request Id, so return the json object that's been decoded.
            return $response->body;
        }

        // If we have neither of the above, something worse has gone wrong.
        throw new \Exception("Requesting credential failed with HTTP status " . $response->code);
    }

    public function get_credential_by_anonid($uuid) {
        
        $get_credential_url = $this->rapid_credentials_url . $uuid;
        $response = $this->send_request(\Httpful\Http::GET, null, $get_credential_url);

        if(isset($response->body) || isset($response->body->Message)) {
            // If a valid json identifier, or message is set 
            // The request was satisfied by the Rapid Service but we have an error or a valid
            // Request Id, so return the json object that's been decoded.
            return $response->body;
        }

        // If we have neither of the above, something worse has gone wrong.
        throw new \Exception("Requesting AnonID failed with HTTP status " . $response->code);
    }

    /****** INTERNAL METHODS *****/

    private function parse_rapid_url($url) {
        $parsed = parse_url($url);

        if(!isset($parsed['host']) && !isset($parsed['path'])) 
            throw new \Exception("Invalid RapID URL");
        if(!isset($parsed['host']) && !trim($parsed['path'])) 
            throw new \Exception("Invalid RapID URL");

        $hostPath = "";
        if(isset($parsed['host'])) $hostPath .= $parsed['host'];
        if(isset($parsed['port'])) $hostPath .= ":" . $parsed['port'];
        if(isset($parsed['path'])) $hostPath .= $parsed['path'];

        return "https://$hostPath/";        
    }

    private function setup_temporary_cert_files(){
        
        $public_key = $this->rapid_client_key['cert'];
        $private_key = $this->rapid_client_key['key'];
        $passphrase = $this->rapid_client_key['passphrase'];
        $certfolder = $this->rapid_client_key['certfolder'];
        $tempfolder = $this->rapid_client_key['tempfolder'];

        $public_key_path = $this->write_temporary_file($public_key, $tempfolder);

	    if($this->curl_compiled_with_nss()){
            
            $key = openssl_pkey_get_private($private_key, $passphrase);
            
            $sslconf = $certfolder . '/openssl.cnf';
		    if(file_exists($sslconf)) {	
		        $config_args = array('config' => $sslconf); 
		    } else {
			    $config_args = null;
		    }

            $output = "";
            $key_ok = openssl_pkey_export($key, $output, null, $config_args);
            
            if(!$key_ok) {
                $opensslerror = "";
	            while ($msg = openssl_error_string())	$opensslerror .= $msg . "\n";
			    
                if(strpos($opensslerror, 'error:02001002:system library:fopen:No such file or directory') !== false) {
                    // if this error is detected, it is due to the server platform not having access to the default openssl_open cnf file.
                    // move sample default conf into position and retry method.
                    $sample_sslconf = $certfolder . '/sample-openssl.cnf';
                    $sslconf = $certfolder . '/openssl.cnf';
					
					if(file_exists($sample_sslconf)) {
						rename($sample_sslconf, $sslconf);
                        clearstatcache();
		                if(file_exists($sslconf)) {	$config_args = array('config' => $sslconf); }
						// retry
						$key_ok = openssl_pkey_export($key, $output, null, $config_args);
					}
			    }
            }
       	
            if(!$key_ok) {
                throw new \Exception("Unable to read private key. Please check the password.");
            }

            $private_key_path = $this->write_temporary_file($output, $tempfolder);
            $passphrase = null;
        }
        else {
            $private_key_path = $this->write_temporary_file($private_key, $tempfolder);       
        }
        $result = array('public' => $public_key_path, 'private' => $private_key_path, 'passphrase' => $passphrase);

        return $result;
    }

    private function curl_compiled_with_nss() {
        $curlinfo = curl_version();
        $curl_compile_version = $curlinfo['ssl_version'];
        // If we don't have a version, can't do anything anyway so fall back to standard behaviour
        if(!empty($curl_compile_version)){

            $curl_ssl_parts = explode("/",$curl_compile_version);
            $tls_version = reset($curl_ssl_parts);

            if($tls_version === false) { return false; }

            if($tls_version === "NSS")
            {
                return true;
            }
        }

        return false;
    }

    private function write_temporary_file($contents, $tempfolder)
    {
        $temp_key_path   = tempnam($tempfolder, "cr-");
        $temp_key_handle = fopen($temp_key_path, "w");
        fwrite($temp_key_handle, $contents);
        fclose($temp_key_handle);

        return $temp_key_path;
    }

    private function clean_up_temp_cert_files($cert_paths) {
        
        if(empty($cert_paths)){ return; }

        $public_path = isset($cert_paths['public']) ? $cert_paths['public'] : null;
        $private_path = isset($cert_paths['private']) ? $cert_paths['private'] : null;

        if(!empty($public_path) && file_exists($public_path)) {
            unlink($public_path);
        }

        if(!empty($private_path) && file_exists($private_path)) {
            unlink($private_path);
        }

    }
    
    private function send_request($method, $payload, $rapid_url) {

        $cert_paths = $this->setup_temporary_cert_files();

        if($cert_paths == null || empty($cert_paths['public']) || empty($cert_paths['private'])){
            $this->clean_up_temp_cert_files($cert_paths);
            throw new \Exception("Requesting credential failed, unable to parse server credentials.");
        }
    
        try {

            $response = \Httpful\Request::init($method)
                ->uri($rapid_url)
                ->authenticateWithCert(
                    $cert_paths['public'],
                    $cert_paths['private'],
                    $cert_paths['passphrase']
                )
                ->body($payload)
                ->sendsJson()
                ->expectsJson()
                ->send();

        } catch(Exception $e) {
            $this->clean_up_temp_cert_files($cert_paths);
        }

        // clean up after intercept if required
        $this->clean_up_temp_cert_files($cert_paths);

        return $response;
    }

    private $key;
}
?>