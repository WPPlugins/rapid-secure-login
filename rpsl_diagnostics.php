<?php
//*************************************************************************************
//*************************************************************************************
//   Rapid Diagnostics Logging
//*************************************************************************************
//*************************************************************************************
$rpsl_diagnostics_on = true;  // First level diagnostics
$rpsl_trace_on       = false;  // More verbose diagnostics
$rpsl_trace_certs    = false; // Include Cert info

// Rapid Diagnostics Logging
function rpsl_diag ( $log )  {
	global $rpsl_diagnostics_on;
	if ( true === WP_DEBUG && $rpsl_diagnostics_on ) {
		if ( is_array( $log ) || is_object( $log ) ) {
			error_log( "RapID: " . print_r( $log, true ) );
		} else {
			error_log( "RapID: " .  $log . rpsl_memory());
		}
	}
}

function rpsl_trace ( $log )  {
	global $rpsl_trace_on;
	if ( true === WP_DEBUG && $rpsl_trace_on ) {
		if ( is_array( $log ) || is_object( $log ) ) {
			error_log( "RapID: " . print_r( $log, true ) );
		} else {
			error_log( "RapID: " .  $log . rpsl_memory() );
		}
	}
}

// Use this to log output that must be echoed too
function rpsl_echo ( $message )  {
	rpsl_diag($message);
	echo $message;
}

// Get memory usage to append to log messages
function rpsl_memory ()  {
	$message = " (M=" . memory_get_usage() . "/" . memory_get_peak_usage(true) . "/" . ini_get('memory_limit') . ")" ;
	return $message;
}

function rpsl_log_openssl_error()
{
	$opensslerror = "";
	while ($msg = openssl_error_string())	$opensslerror .= $msg . "\n";
	rpsl_trace("OpenSSL Err: $opensslerror");
	return $opensslerror;
}
?>