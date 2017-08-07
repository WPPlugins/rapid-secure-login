<?php
//*************************************************************************************
//*************************************************************************************
//   DEVICE ADMINISTRATION
//*************************************************************************************
//*************************************************************************************

//*************************************************************************************
// List Devices as HTML via an Ajax call
// No data needed from caller as it only lists devices for the current user
//*************************************************************************************
function rpsl_list_devices() {
// Dumps the contents of the rpsl_devices table
	if ( isset($_REQUEST) ) {
		// Get the http and Rapid session IDs 
		$user_id = sanitize_text_field( $_REQUEST["user"] );
		rpsl_dump_devices($user_id);
	}
	die;
}

//*************************************************************************************
// Rename a device owned by the currently connected user
// Ajax data contains 'uuid' and 'name'
//*************************************************************************************
function rpsl_rename_device () {
	global $wpdb;
 	
	$updates = 0;
	if ( isset($_REQUEST) ) {
		// Get the http and Rapid session IDs, both strings.
		$device_name = sanitize_text_field( $_REQUEST["name"] );
		$rpsl_uuid     = sanitize_text_field( $_REQUEST["uuid"] );   
		$user        = wp_get_current_user();
		if ($user->exists()) {
			if(!empty($device_name)) {
				$device_name = substr($device_name, 0, 80);
			}
			$table_name      = $wpdb->prefix . "rpsl_devices";
			$set_device_name = $wpdb->prepare(
								"UPDATE " . $table_name . " SET devicename = %s WHERE rpsl_uuid = %s ",
								array($device_name, $rpsl_uuid)
							);
			$updates = $wpdb->query( $set_device_name );
		}
	}
	if ($updates > 0) {
		printf( esc_html__('Device renamed to %s', 'rp-securelogin'), $device_name);
	} else {
		printf( esc_html__('Error - device not updated for user id: %s', 'rp-securelogin'), $user_id);
	}
	die; // Always die in functions echoing Ajax content
}

//*************************************************************************************
// Delete a device owned by the currently connected user
// Ajax data contains 'uuid' 
//*************************************************************************************
function rpsl_delete_device_record () {
	global $wpdb;
 	
	$deletes = 0;
	if ( isset($_REQUEST) ) {
		// Get the http and Rapid session IDs 
		$rpsl_uuid     = sanitize_text_field( $_REQUEST["uuid"] );   
		$user        = wp_get_current_user();
		if ($user->exists()) {
			$deletes = rpsl_delete_device($rpsl_uuid);
		}
	}
	if ($deletes > 0) {
		esc_html_e('Device deleted ok', 'rp-securelogin');
	} else {
		esc_html_e('Error - device not deleted', 'rp-securelogin');
	}
	die; // Always die in functions echoing Ajax content
}


//*************************************************************************************
// Dump Devices as HTML
//*************************************************************************************
function rpsl_dump_devices($user_id) {
	echo rpsl_dump_devices_raw($user_id);
}

//*************************************************************************************
// Dump Devices as HTML - Inner function (needed for shortcode)
//*************************************************************************************
function rpsl_dump_devices_raw($user_id) {

	if(empty($user_id)) {
		// if no user, we don't return anything.
		return;
	}

	global $wpdb;
	$device_query = $wpdb->prepare("SELECT user_id, rpsl_uuid, devicename, created, last_used FROM " . $wpdb->prefix . "rpsl_devices WHERE user_id = %s ORDER BY last_used desc", $user_id);
	
	$devices = $wpdb->get_results( $device_query );

	if($devices == null)
	{
		// if no devices, we do not output a table.
		return;
	}

	//i18n strings
	$mDevice = esc_html__('Device', 'rp-securelogin');
	$mRegd   = esc_html__('Registered on (UTC)', 'rp-securelogin');
	$mUsed   = esc_html__('Last Used (UTC)', 'rp-securelogin');
	$devices = stripslashes_deep($devices);

	$html  = "<table style='border-spacing:10px'>";
	$html .= "<tr style='text-align:left'><th >$mDevice</th><th>$mRegd</th><th>$mUsed</th><th></th><th></th>"; 
	$html .= "</tr>";
	foreach ($devices as $device) {
		$html .= '<tr>';
		$html .=   '<td style="white-space:nowrap">' . esc_html($device->devicename) . '</td>';
		$html .=   '<td style="white-space:nowrap">' . esc_html($device->created)    . '</td>';
		$html .=   '<td style="white-space:nowrap">' . esc_html($device->last_used)  . '</td>';
		$html .=   '<td><input type="button" class="button button-secondary" ';
		$html .=        'value="Delete" onClick="rpsl_delete_device(\'' . esc_js($device->rpsl_uuid) . '\');"></td>';
		$html .=   '<td><input type="button" class="button button-secondary" ';
		$html .=   		'value="Rename" onClick="rpsl_rename_device(\'' . esc_js($device->rpsl_uuid) . "','" . esc_js($device->devicename) . '\');"></td>';
		$html .= '</tr>';
	}
	$html .= '</table>';
	return $html;
}