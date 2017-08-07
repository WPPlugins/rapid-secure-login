<?php 
//*************************************************************************************
//*************************************************************************************
// Role Management Methods for WordPress Rapid Secure Login
//*************************************************************************************
//*************************************************************************************
function rpsl_initialise_roles() {
	// If this is the first time RapID roles have been applied, enable all roles
	global $wp_roles;
	$role_to_check = get_role('administrator');
	if($role_to_check->has_cap( RPSL_Configuration::$rpsl_can_have_credential_role )  ){ return; }
	
	// Admin not set, which means this is the first time. Set all roles to enabled.
	$roles = $wp_roles->get_names();
	foreach ($roles as $role_key => $role_name) {
		$role_to_set = get_role($role_key);
		$role_to_set->add_cap( RPSL_Configuration::$rpsl_can_have_credential_role );
	}
}

function rpsl_set_allowed_roles($allowed_roles) {
	
	// Administrator must always be an allowed role.
	$allowed_roles[] = 'administrator';

	global $wp_roles;
	$roles = $wp_roles->get_names();
	
	foreach ($roles as $role_key => $role_name) {
		
		$role_to_set = get_role($role_key);
		if(in_array( $role_key, $allowed_roles ) ){
			$role_to_set->add_cap( RPSL_Configuration::$rpsl_can_have_credential_role );
		} else	{
			$role_to_set->remove_cap( RPSL_Configuration::$rpsl_can_have_credential_role );	
		}
	}
}
?>