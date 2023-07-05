<?php 
/**
 * Plugin Name: Woocommerce Instore Environment
 * Description: This plugin makes a public Woocommerce store suitable for placing orders in an instore environment with an dedicated instore payment method. You can set a IP address and user agent to identify the instore environment.
 * Author: Erik Molenaar
 * Author URI: https://erikmolenaar.nl
 * Version: 1.11
 */


// Exit if accessed directly
if ( ! defined ( 'ABSPATH' ) ) exit;


// Define constants
define ( 'EMNL_INSTORE_ENVIRONMENT_IP', '217.100.16.98' );
define ( 'EMNL_INSTORE_ENVIRONMENT_USER_AGENT', 'Windows NT 10.0; WOW64' ); // Note: case sensitive!!!
define ( 'EMNL_INSTORE_ENVIRONMENT_PAYMENT_METHOD', 'pay_gateway_instore' );


// Function to set instore compatible environment
add_action ( 'wp', 'emnl_wie_set_instore_environment' );
function emnl_wie_set_instore_environment() {

    // Detecting an instore environment. If not, stop here
    if ( ! emnl_wie_detect_instore_environment() ) { return; }

    // Step 1 - Change Woocommerce core behaviour not compatible with instore environment

        // Enable guest checkout
        add_filter ( 'pre_option_woocommerce_enable_guest_checkout', 'emnl_wie_return_yes' );

        // Disable login to existing account during checkout
        add_filter ( 'pre_option_woocommerce_enable_checkout_login_reminder', 'emnl_wie_return_no' );

        // Disable create account during checkout
        add_filter ( 'pre_option_woocommerce_enable_signup_and_login_from_checkout', 'emnl_wie_return_no' );

        // Disable create account on the "My account" page
        add_filter ( 'pre_option_woocommerce_enable_myaccount_registration', 'emnl_wie_return_no' );

        // Do not remember previous filled in checkout fields (except for country)
        add_filter ( 'woocommerce_checkout_get_value', 'emnl_wie_empty_checkout_fields_except_country', 10, 2 );
        function emnl_wie_empty_checkout_fields_except_country ( $value, $input ) {

            if ( 'billing_country' === $input || 'shipping_country' === $input ) {
                return $value;
            } else {
                return '';
            }
        
        }

    // Step 2 - Disable plugins or code which are not compatible or desired in a instore environment

        // Disable plugin "Cookie notification"
        add_filter ( 'catapult_cookie_content', '__return_false' );

        // Disable plugin "Optinmonster"
        add_filter ( 'optinmonster_pre_campaign_should_output', '__return_false' );

    // Useful functions for returning strings "no" of "yes" to filters easily
    
    function emnl_wie_return_no() { return "no"; }
    function emnl_wie_return_yes() { return "yes"; }

}


// Function to show/hide the instore payment method during checkout
add_filter ( 'woocommerce_available_payment_gateways','emnl_wie_filter_payment_methods', 99 );
function emnl_wie_filter_payment_methods ( $gateways_available ) {

    // Check if the instore payment method is available. If not show ALL payment methods
    if ( ! array_key_exists ( EMNL_INSTORE_ENVIRONMENT_PAYMENT_METHOD, $gateways_available ) ) {
        return $gateways_available;
    }

    // For debugging purposes: for administrators show ALL payment methods
    $role = '';
    if ( is_user_logged_in() ) {

        $user = wp_get_current_user();
        $roles = ( array ) $user->roles;
        $role = $roles[0];

    }

    if ( is_user_logged_in() && 'administrator' === $role ) {
        return $gateways_available;
    }

    // Unset ALL payment methods EXCEPT instore
    if ( emnl_wie_detect_instore_environment() ) {

        $gateway_instore = $gateways_available[EMNL_INSTORE_ENVIRONMENT_PAYMENT_METHOD];
        $gateways_available = array();
        $gateways_available[EMNL_INSTORE_ENVIRONMENT_PAYMENT_METHOD] = $gateway_instore;
        
    // Unset ONLY instore payment method
    } else {
        unset ( $gateways_available[EMNL_INSTORE_ENVIRONMENT_PAYMENT_METHOD]);
    }

    return $gateways_available;

}


// Function to detect the instore environment
function emnl_wie_detect_instore_environment () {

    // Get IP address
    $ip = getenv('HTTP_CLIENT_IP')?:
    getenv('HTTP_X_FORWARDED_FOR')?:
    getenv('HTTP_X_FORWARDED')?:
    getenv('HTTP_FORWARDED_FOR')?:
    getenv('HTTP_FORWARDED')?:
    getenv('REMOTE_ADDR');

    // Get user agent
    $user_agent = $_SERVER['HTTP_USER_AGENT']??null;

    // If the instore IP annd user agent are found, return true
    if ( strpos ( $ip, EMNL_INSTORE_ENVIRONMENT_IP ) !== false && strpos ( $user_agent, EMNL_INSTORE_ENVIRONMENT_USER_AGENT ) !== false ) {
        return true;
    }

    // Fallback
    return false;

}