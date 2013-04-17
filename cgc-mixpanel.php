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

	$user         = get_userdata( $user_id );
	$subscription = rcp_get_subscription( $user_id );
	$rcp_payments = new RCP_Payments;
	$new_user     = $rcp_payments->last_payment_of_user( $user_id );

	$person_props                 = array();
	$person_props['first_name']   = $user->first_name;
	$person_props['last_name']    = $user->last_name;
	$person_props['user_login']   = $user->user_login;
	$person_props['user_email']   = $user->user_email;
	$person_props['subscription'] = $subscription;
	$person_props['status']       = 'Active';

	wp_mixpanel()->track_person( $user_id, $person_props );

	switch( $posted['txn_type'] ) :

		// New subscription
		case 'subscr_signup' :


			$event_props                 = array();
			$event_props['distinct_id']  = $user_id;
			$event_props['subscription'] = $subscription;

			$type = ! empty( $new_user ) ? 'Renewal' : 'New Signup';

			wp_mixpanel()->track( $type, $event_props );

			$person_props['recurring'] = 'Yes';
			wp_mixpanel()->track_person( $user_id, $person_props );

			break;

		// Subscription payment
		case 'subscr_payment' :

			$trans_props = array(
				'amount' => $payment_data['amount']
			);
			wp_mixpanel()->track_transaction( $user_id, $trans_props );

			break;

		case 'web_accept' :

			if( strtolower( $posted['payment_status'] ) === 'completed' ) {

				$event_props                 = array();
				$event_props['distinct_id']  = $user_id;
				$event_props['subscription'] = $subscription;

				$type = ! empty( $new_user ) ? 'Renewal' : 'New Signup';

				wp_mixpanel()->track( $type, $event_props );

				$trans_props = array(
					'amount' => $payment_data['amount']
				);
				wp_mixpanel()->track_transaction( $user_id, $trans_props );

				$person_props['recurring'] = 'No';
				wp_mixpanel()->track_person( $user_id, $person_props );

			}

			break;

	endswitch;

}
add_action( 'rcp_valid_ipn', 'cgc_rcp_mixpanel_tracking', 10, 3 );


function cgc_rcp_track_status_changes( $new_status, $user_id ) {

	// We check for $_POST to make sure this only fires on the signup form
	if( 'free' === $new_status && isset( $_POST['rcp_level'] ) ) {

		$user                         = get_userdata( $user_id );

		$person_props                 = array();
		$person_props['first_name']   = $user->first_name;
		$person_props['last_name']    = $user->last_name;
		$person_props['user_login']   = $user->user_login;
		$person_props['user_email']   = $user->user_email;
		$person_props['subscription'] = rcp_get_subscription( $user_id );
		$person_props['status']       = 'free';

		wp_mixpanel()->track_person( $user_id, $person_props );

		$event_props                 = array();
		$event_props['distinct_id']  = $user_id;
		$event_props['subscription'] = $subscription;

		wp_mixpanel()->track( 'Free Signup', $event_props );

	} else if( 'expired' === $new_status ) {

		$user                         = get_userdata( $user_id );

		$person_props                 = array();
		$person_props['first_name']   = $user->first_name;
		$person_props['last_name']    = $user->last_name;
		$person_props['user_login']   = $user->user_login;
		$person_props['user_email']   = $user->user_email;
		$person_props['subscription'] = rcp_get_subscription( $user_id );
		$person_props['status']       = 'Expired';
		$person_props['recurring']    = 'No';

		wp_mixpanel()->track_person( $user_id, $person_props );

		$event_props                 = array();
		$event_props['distinct_id']  = $user_id;
		$event_props['subscription'] = $subscription;

		wp_mixpanel()->track( 'Expired Membership', $event_props );

	} elseif( 'cancelled' === $new_status ) {

		$user                         = get_userdata( $user_id );

		$person_props                 = array();
		$person_props['first_name']   = $user->first_name;
		$person_props['last_name']    = $user->last_name;
		$person_props['user_login']   = $user->user_login;
		$person_props['user_email']   = $user->user_email;
		$person_props['subscription'] = rcp_get_subscription( $user_id );
		$person_props['status']       = 'Cancelled';
		$person_props['recurring']    = 'No';

		wp_mixpanel()->track_person( $user_id, $person_props );

		$event_props                 = array();
		$event_props['distinct_id']  = $user_id;
		$event_props['subscription'] = $subscription;

		wp_mixpanel()->track( 'Cancelled Membership', $event_props );

	}
}
add_action( 'rcp_set_status', 'cgc_rcp_track_free_signup', 10, 2 );