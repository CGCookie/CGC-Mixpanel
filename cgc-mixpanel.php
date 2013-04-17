<?php
/**
 * Plugin Name: CGC Mixpanel Tracking
 * Description: Tracks CGC data through Mixpanel
 * Author: Pippin Williamson
 * Author URI: http://pippinsplugins.com
 * Version: 1.0
 */



function cgc_rcp_mixpanel_tracking( $payment_data, $user_id, $posted ) {
	if( ! function_exists( 'wp_mixpanel' ) || ! function_exists( 'rcp_get_subscription_name' ) )
		return;

	if( $posted['txn_type'] == 'web_accept' || $posted['txn_type'] == 'subscr_signup' ) {

		$user  = get_userdata( $user_id );

		$props = array();
		$props['user_login']   = $user->user_login;
		$props['user_email']   = $user->user_email;
		$props['subscription'] = rcp_get_subscription( $user_id );
		$props['signup_date']  = $payment_data['date'];

		wp_mixpanel()->track( 'RCP Signup', $props );

	}
}
add_action( 'rcp_valid_ipn', 'cgc_rcp_mixpanel_tracking', 10, 3 );