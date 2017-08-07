<?php
class RPSL_Configuration {
    
	// Could be changed on demand to ajax.php to reduce load times.
	public static function rpsl_ajax_endpoint() {
		return esc_url(plugin_dir_url( __FILE__ ) . ('rpsl_ajax.php'));
	}
	
	// Delay before an ajax retry
	public static $rpsl_ajax_timeout = 5000;

	// How long the long poll delays in the loop before retrying.
	public static $rpsl_long_poll_timeout = 3;

	// How long the connection stays open while looping on the server
	public static $rpsl_long_poll_lifetime = 30;

	// capability to detect if they can collect a RapID Credential
	public static $rpsl_can_have_credential_role = "rpsl_can_have_credential";

	// Show Manual Configuration Tab, default is false.
	public static $rpsl_show_manual_configuration_tab = false;
}
?>