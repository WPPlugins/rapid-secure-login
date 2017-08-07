<?php
function rpsl_add_user () {
//
// Gets a request to add a user in a PKCS#7 post signed by a trusted user account
//
// Minimum expected data is:
//   userinfo->contact->uuid
//   userinfo->contact->firstname
//   userinfo->contact->lastname
//   userinfo->contact->email
//   userinfo->contact->roles
//   userinfo->billing-> ( list of fields for billing address - can be empty)
//   userinfo->shipping->( list of fields for shipping address - can be empty)
//
try {	
	rpsl_diag("Processing request to add RapID User");
	$posted = rpsl_rapid_verify();
	if ($posted['status'] != 0) {
		echo "Error {$posted['status']} - unable to process request<br/>";
		echo $posted['message'];
		die(0);
	}
	
	// All ok - get the data
	$dataJson = $posted['data'];
	$authuuid = $posted['uuid'];                     // This is the authorising UUID - we could check this relates to a specific WP admin account or role
	// Insert additional validation of the authorising user in here.
	// Like this..
	$authuser = rpsl_get_user_by_uuid($authuuid);  
	if (!is_object($authuser)) {
		rpsl_echo("Unable to add RapID User -the requestor does not have an account " . $authuuid);
		die;
	}
	if (!in_array('administrator', (array) $authuser->roles)){
		rpsl_echo("Unable to add RapID User - the requestor does not have rights to create an account " . $authuser->user_login);
		die;
	}
	
	// All good.  Now get the supplied user details for the account we need to create
	$data     = json_decode($dataJson);
	
	$userinfo = $data->userinfo;
	$uuid     = $userinfo->uuid;
	
	// Create the new user acount
	try {
		// Probably a good idea to use the same datastructure that RapID-SL uses for extended attribubtes - handy for 3rd parties!
		$billing  = $userinfo->billing;
		$shipping = $userinfo->shipping;
		$contact  = $userinfo->contact;
		$email    = sanitize_text_field( $contact->email );
		$roles    = $contact->roles;
		
		// Check whether the user already exists - UUID
		$user = rpsl_get_user_by_uuid($uuid);
		if(is_a($user, 'WP_User')){
			//User exists, update.
			rpsl_update_user($user, $roles);
			die;
		}
		
		// Check whether the user already exists - email		
		// When updating the user by email, we have a device to add as well.
		$user = get_user_by('login', sanitize_user($email, true));	
		if(is_a($user, 'WP_User')){
			//User exists, update.
			rpsl_update_user($user, $roles, $uuid, $contact->phoneName);
			die;
		}
		
		// user does not exist, add as per normal route.
		$password = wp_generate_password(12,true);
		$userid   = wp_create_user($email, $password, $email);
		if (is_wp_error($userid)) {
			rpsl_echo("Error: Unable to create user " . $userid->get_error_message($userid->get_error_code));
			die;
		}
		echo "===========================<br/>";
		echo " User account created      <br/>";
		echo "===========================<br/>";
		rpsl_diag("User account created for " . $userinfo->contact->email);
		
		$showAdminBar = "true";
		if(isset($userinfo->showAdminBar) && !$userinfo->showAdminBar){
			$showAdminBar = "false";
		}
				
		wp_update_user( array(
								'ID'					=> $userid,
								'first_name'			=> sanitize_text_field( $contact->firstName ),
								'last_name'				=> sanitize_text_field( $contact->lastName ),
								'nickname'				=> sanitize_text_field( $contact->firstName ),
								'display_name'			=> sanitize_text_field( $contact->firstName ),
								'user_nicename'			=> rpsl_UUID::v4(),
								'show_admin_bar_front'	=> $showAdminBar,
							)
						);
		$user = get_user_by("ID", $userid);
		
		// Set default contributor role and any additional roles.
		$user->set_role('contributor');
		rpsl_update_user($user, $roles, $uuid, $contact->phoneName);

		// It would be useful to add a 'do_action' here to enable metadata to be associated with the new account
		// This is the sort of thing we might want to add - example form woocommerce.
		//update_user_meta( $userid, 'shipping_first_name', $contact->firstName );
		//update_user_meta( $userid, 'shipping_last_name',  $contact->lastName  );
		//update_user_meta( $userid, 'shipping_company',    ""                  );
		//update_user_meta( $userid, 'shipping_phone',      $contact->phone     );
		//update_user_meta( $userid, 'shipping_address_1',  $shipping->house . " " . $shipping->street );
		//update_user_meta( $userid, 'shipping_address_2',  ""                  );
		//update_user_meta( $userid, 'shipping_city',       $shipping->city     );
		//update_user_meta( $userid, 'shipping_state',      $shipping->state    );
		//update_user_meta( $userid, 'shipping_postcode',   $shipping->zip      );
		//update_user_meta( $userid, 'shipping_country',    $shipping->country  );

		//update_user_meta( $userid, 'billing_first_name',  $contact->firstName );
		//update_user_meta( $userid, 'billing_last_name',   $contact->lastName  );
		//update_user_meta( $userid, 'billing_company',     ""                  );
		//update_user_meta( $userid, 'billing_phone',       $contact->phone     );
		//update_user_meta( $userid, 'billing_address_1',   $billing->house . " " . $billing->street );
		//update_user_meta( $userid, 'billing_address_2',   ""                  );
		//update_user_meta( $userid, 'billing_city',        $billing->city      );
		//update_user_meta( $userid, 'billing_state',       $billing->state     );
		//update_user_meta( $userid, 'billing_postcode',    $billing->zip       );
		//update_user_meta( $userid, 'billing_country',     $billing->country   );
		
		} catch (Exception $e) {echo "Exception: ", $e->getMessage() ; die;
			
		}

  	} catch (Exception $e) {echo "Exception: ", $e->getMessage() ;}

	die; // Always die in functions echoing Ajax content
}

function rpsl_add_roles($user, $roles) {
	
	if(!isset($roles)){	return;	}
	
	foreach($roles as $role) {
		rpsl_trace("Add Role: $role");
		$user->add_role(sanitize_text_field($role));
	}
}

function rpsl_update_user($user, $roles, $uuid = null, $phoneName = null) {
	// if $user is a WP_User then the user already exists, lets update the roles / add a new device.
		
	rpsl_add_roles($user, $roles);
	
	if(isset($uuid)){
		// Associate the UUID with this account
		$phone = isset($phoneName) ? sanitize_text_field( $phoneName ): "My Phone";
		
		rpsl_add_device($user->ID, $uuid, $phone);
	}
	rpsl_echo("Updated user: $user->ID");
}