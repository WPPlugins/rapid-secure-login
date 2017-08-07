<?php
/**************************************************************************************
Plugin Name:       RapID Secure Login
Description:       Fast and secure login to WordPress using RapID mobile credentials
Version:           2.0.8
Plugin URI:        https://www.intercede.com/rapidsl
Author:            Intercede
Author URI:        http://www.intercede.com
License:           GPL2
Text Domain:       rp-securelogin
**************************************************************************************/
//*************************************************************************************
// Important note:    As this plugin is concerned with security aspects of your site,
//                    any modifications to is should be made with care!
//*************************************************************************************

//*************************************************************************************
if ( !defined('ABSPATH')) exit;  // Security trap to block direct access to this script
//*************************************************************************************
 
require plugin_dir_path( __FILE__) . 'includes/Rapid.php';
require plugin_dir_path( __FILE__) . 'includes/httpful/Bootstrap.php';
require plugin_dir_path( __FILE__) . 'includes/phpqrcode.php';

require plugin_dir_path( __FILE__) . 'rpsl_configuration.php';              // Contains global configuration values
require plugin_dir_path( __FILE__) . 'rpsl_utils.php';                      // Utility functions
require plugin_dir_path( __FILE__) . 'rpsl_rapid_library.php';              // Rapid Library
require plugin_dir_path( __FILE__) . 'rpsl_database_layer.php';             // Rapid Database Layer
require plugin_dir_path( __FILE__) . 'rpsl_security_events.php';            // Rapid Polling and QR Code Generation
require plugin_dir_path( __FILE__) . 'rpsl_settings.php';                   // WordPress Settings page
require plugin_dir_path( __FILE__) . 'rpsl_devices.php';                    // Device management
require plugin_dir_path( __FILE__) . 'rpsl_authenticate.php';               // Login / Authentication
require plugin_dir_path( __FILE__) . 'rpsl_registration.php';               // Registration and association
require plugin_dir_path( __FILE__) . 'rpsl_self_registration.php';          // Create a new account
require plugin_dir_path( __FILE__) . 'rpsl_login.php';                      // Implements the [rpsl_secure_login] shortcode
require plugin_dir_path( __FILE__) . 'rpsl_adduser.php';                    // Implements the add user ajax call
require plugin_dir_path( __FILE__) . 'rpsl_useraccess.php';                 // Implements the user access ajax calls
require plugin_dir_path( __FILE__) . 'rpsl_my_devices.php';                 // Implements the [rpsl_my_devices] shortcode
require plugin_dir_path( __FILE__) . 'rpsl_diagnostics.php';                // Implements Diagnostic functions
require plugin_dir_path( __FILE__) . 'rpsl_schema_updates.php';             // Implements Updates to Database
require plugin_dir_path( __FILE__) . 'rpsl_roles.php';                      // Implements Roles

//*************************************************************************************
// Ensure we have an HTTP session
//*************************************************************************************
function register_session() { if( !session_id() ) session_start(); }
add_action('init', 'register_session');

//*************************************************************************************
// Initializes the plugin.
// For fast initialization, only add action hooks in the constructor.
//*************************************************************************************
class rpsl_WordPress_Plugin {
        public static $rpsl_plugin_db_version_number = 3;
    
        public function __construct() {

        //-------------------------------------------------------------------------------------
        // Login filters
        //-------------------------------------------------------------------------------------
        // Add the Ajax filter to generate a new Login QR code
        add_action( 'wp_ajax_nopriv_rpsl_generate_login_qrcode', 'rpsl_generate_login_qrcode' );
        add_action( 'wp_ajax_rpsl_generate_login_qrcode',        'rpsl_generate_login_qrcode' );
          
        // Add the Ajax filters for authorizing a login
        add_action( 'wp_ajax_nopriv_rpsl_login_authorization', 'rpsl_login_authorization' );
        add_action( 'wp_ajax_rpsl_login_authorization',        'rpsl_login_authorization' );

        // Add the Ajax filters for adding a user
        add_action( 'wp_ajax_nopriv_rpsl_add_user', 'rpsl_add_user' );
        add_action( 'wp_ajax_rpsl_add_user',        'rpsl_add_user' );

        // Add the Ajax filters for user access      
        add_action( 'wp_ajax_nopriv_rpsl_prevent_access', 'rpsl_revoke_device' );
        add_action( 'wp_ajax_rpsl_prevent_access',        'rpsl_revoke_device' );

        //-------------------------------------------------------------------------------------
        // Registration filters - add a new RapID credential to your account
        //-------------------------------------------------------------------------------------
        // Add the Ajax filter for generating an account registration QR code (only if logged on user)
        add_action( 'wp_ajax_rpsl_generate_registration_qrcode',        'rpsl_generate_registration_qrcode' );

        add_action( 'wp_ajax_rpsl_register',              'rpsl_register' );
        add_action( 'wp_ajax_nopriv_rpsl_register',       'rpsl_register' );

        add_action( 'wp_ajax_rpsl_credential_confirmation',              'rpsl_credential_confirmation' );
        add_action( 'wp_ajax_nopriv_rpsl_credential_confirmation',       'rpsl_credential_confirmation' );

        //-------------------------------------------------------------------------------------
        // Enrolment filters - create a new WP user account and associate a new RapID credential
        //-------------------------------------------------------------------------------------
        // Add the Ajax filters to generate a "self registration" QR code
        add_action( 'wp_ajax_nopriv_rpsl_generate_self_registration_qrcode', 'rpsl_generate_self_registration_qrcode' );
        add_action( 'wp_ajax_rpsl_generate_self_registration_qrcode',        'rpsl_generate_self_registration_qrcode' );
                    
        // Add the Ajax filters to add a "new account" to the site
        add_action( 'wp_ajax_nopriv_rpsl_self_registration_create_user', 'rpsl_self_registration_create_user' );
        add_action( 'wp_ajax_rpsl_self_registration_create_user',        'rpsl_self_registration_create_user' );
          
        //-------------------------------------------------------------------------------------
        // Site Registration filters - Configure Rapid for your Wordpress Account
        //-------------------------------------------------------------------------------------
        // Add the Ajax filter for generating an account registration QR code (only if logged on user)
        add_action( 'wp_ajax_rpsl_generate_site_registration_qrcode',        'rpsl_generate_site_registration_qrcode' );
          
        // Add the Ajax filters for configuring the wordpress site and receiving certificates
        add_action( 'wp_ajax_nopriv_rpsl_site_registration', 'rpsl_site_registration' );
        add_action( 'wp_ajax_rpsl_site_registration',        'rpsl_site_registration' );
          
        //-------------------------------------------------------------------------------------
        // Administration hooks
        //-------------------------------------------------------------------------------------
        // Add the RapID Settings configuration page
        add_action( 'admin_menu',  'rpsl_admin_menu' );
          
        // Personal device administration Ajax functions. Users may only affect their own devices
        add_action( 'wp_ajax_rpsl_rename_device',     'rpsl_rename_device' );
        add_action( 'wp_ajax_rpsl_delete_device',     'rpsl_delete_device_record' );
        add_action( 'wp_ajax_rpsl_list_devices',      'rpsl_list_devices'  );

        // Add the RapID WordPress login page extensions
        add_action( 'login_footer',  'rpsl_wplogin_page_html'   );
        add_action( 'login_footer',  'rpsl_wplogin_page_script' );
          
        // Add the RapID WordPress user profile show/edit extensions
        add_action( 'show_user_profile',  'rpsl_wp_registration_qrcode' );
        add_action( 'edit_user_profile',  'rpsl_wp_registration_qrcode' );
          
        // Add the RapID WordPress registration page insertion points
        add_action( 'signup_extra_fields',  'rpsl_newregistration_qrcode' ); // For Multi-site WordPress
        add_action( 'register_form',        'rpsl_newregistration_qrcode' ); // For single site WordPress
          
        // Adding the custom javascript files for rpsl_auth
        add_action( 'admin_enqueue_scripts', 'rpsl_adding_admin_scripts' );
        add_action( 'login_enqueue_scripts', 'rpsl_adding_login_scripts');
          
        // Plugin Settings Link
        add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'rpsl_plugin_action_links' );

        // Update Plugin DB and Certificates if required.
        add_action( 'plugins_loaded', 'rpsl_check_version');
          
        // Backwards-compatibility Ajax Hook for existing Apps
        // Only uncomment these as a temporary measure if you are still on V1 of the mobile apps
        // add_action( 'wp_ajax_rp_register',              'rpsl_register' );
        // add_action( 'wp_ajax_nopriv_rp_register',       'rpsl_register' );

        // add_action( 'wp_ajax_nopriv_rapid_login_authorization', 'rpsl_login_authorization' );
        // add_action( 'wp_ajax_rapid_login_authorization',        'rpsl_login_authorization' );

        // add_action( 'wp_ajax_nopriv_rapid_set_account_info', 'rpsl_set_account_info' );
        // add_action( 'wp_ajax_rapid_set_account_info',        'rpsl_set_account_info' );        
    }

}
//*************************************************************************************
// End of Class Definition
//*************************************************************************************

// Create the extra database table at activation
register_activation_hook( __FILE__, 'rpsl_login_create_db' );

$rpsl_wordpress_plugin = new rpsl_WordPress_Plugin(); // Instantiate the plugin


//*************************************************************************************
// Include the rapid stylesheet in the Admin Settings page
//*************************************************************************************
function rpsl_load_css() {
    $css_file = plugin_dir_url( __FILE__ ) . 'css/rapid.css';
    wp_register_style('rapid-cascading-styles', $css_file, false, '1.0.0');
    wp_enqueue_style('rapid-cascading-styles', $css_file);
    wp_enqueue_script('jquery'); // We always need JQuery
}
add_action( 'admin_enqueue_scripts', 'rpsl_load_css' );
add_action( 'login_enqueue_scripts', 'rpsl_load_css' );
add_action( 'wp_enqueue_scripts'   , 'rpsl_load_css' );

//*************************************************************************************
// Create the Rapid database tables
// Note: the spacing of the SQL statement is critical - see dbDelta Wordpress documentation
//*************************************************************************************
function rpsl_login_create_db() {

    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();
    // Create the rpsl_SESSIONS table to hold temporary session authentication data
    // Beware of vey fussy layout constraints for this sql code - do not add or remove spaces or blank lines.
    $table_name = $wpdb->prefix . 'rpsl_sessions';
    $sql = "CREATE TABLE $table_name (
        rpsession varchar(80) NOT NULL ,
        sessionid varchar(80) NOT NULL ,
        requested datetime ,
        loginname varchar(80),
        action    varchar(20),
        uuid      varchar(80),
        userdata  varchar(2048),
        PRIMARY KEY  (rpsession)
    ) $charset_collate;";
    dbDelta( $sql );
        
    // Create the rpsl_DEVICES table to hold UUID data for each user's phones
    // Beware of vey fussy layout constraints for this sql code - do not add or remove spaces or blank lines.
    $table_name = $wpdb->prefix . 'rpsl_devices';
    $sql = "CREATE TABLE $table_name (
        rpsl_uuid    varchar(80) NOT NULL,
        user_id    integer NOT NULL,
        devicename varchar(80),
        status     varchar(20),
        created    datetime,
        last_used  datetime,
        PRIMARY KEY  (rpsl_uuid,user_id)
    ) $charset_collate;";
    dbDelta( $sql );

    // Create the rpsl_CERTIFICATES table to hold credentials 
    // type is Tic or Sac
    $table_name = $wpdb->prefix . 'rpsl_certificates';
    $sql = "CREATE TABLE $table_name (
        certificate_id   int NOT NULL AUTO_INCREMENT,
        public_key       varchar(3000) NOT NULL,
        private_key      varchar(3000),
        type             varchar(3),
        created          datetime NOT NULL,
        status           varchar(20) NOT NULL,
        PRIMARY KEY (certificate_id)
    ) $charset_collate;";
    dbDelta( $sql );
    
    // Create the rpsl_CONFIG table to hold system configuration data 
    // Beware of vey fussy layout constraints for this sql code - do not add or remove spaces or blank lines.
    // We only expect one row in this table
    // Site_id is no longer needed
    // site_name = name included in the QR code and shown to the app user
    // show_login = Yes/No/Click
    $table_name = $wpdb->prefix . 'rpsl_config';
    $sql = "CREATE TABLE $table_name (
        site_id    varchar(10) NOT NULL,
        site_name  varchar(80) NOT NULL,
        show_login varchar(8)  NOT NULL,
        secretkey  varchar(36) NOT NULL,
        PRIMARY KEY  (site_id)
    ) $charset_collate;";
    dbDelta( $sql );

    $sql = "SELECT site_id FROM $table_name ;";
    $site_name = get_bloginfo();
    $site_name = empty($site_name) ? 'Please set the site name' : $site_name;
    $secretkey = rpsl_UUID::v4();

    $site = $wpdb->get_var( $sql );
    if ($site == "") {    
        $wpdb->insert($table_name, array(
                                        'site_id'   =>'000000', 
                                        'site_name'   =>$site_name, 
                                        'show_login'=>'Yes', 
                                        'secretkey' => $secretkey,
                                    ));
    }

    rpsl_initialise_roles();
    add_option("rpsl_plugin_db_version_number", rpsl_WordPress_Plugin::$rpsl_plugin_db_version_number);
}

//*************************************************************************************
// Add link to plugins.php page for the plugin settings.
//*************************************************************************************
function rpsl_plugin_action_links( $links ) {
    return array_merge( array(
        '<a href="' . admin_url( 'options-general.php?page=rpsl-plugin-settings' ) . '">' . 'Settings' . '</a>'
    ), $links );
}

/*************************************************************
 * Scripts to be registered for the plugin
/*************************************************************/

function rpsl_adding_admin_scripts() {
	wp_register_script('rpsl_configure_wordpress_registration', plugins_url('scripts/rpsl_configure_wordpress_registration.js', __FILE__), array('jquery'),'1.9', true);
}

function rpsl_adding_login_scripts() {
	wp_register_script('rpsl_self_registration', plugins_url('scripts/rpsl_self_registration.js', __FILE__), array('jquery'),'1.9', true);
}
