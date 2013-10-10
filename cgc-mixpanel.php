<?php
/**
 * Plugin Name: CGC Mixpanel Tracking
 * Description: Tracks CGC data through Mixpanel
 * Author: Pippin Williamson
 * Author URI: http://pippinsplugins.com
 * Version: 1.0
 */

if( ! defined( 'CGC_MIXPANEL_API' ) ) {
	define( 'CGC_MIXPANEL_API', '018006ab8a267cc6d0a158dbfe41801a' );
}

if( ! class_exists( 'Mixpanel' ) ) {
	require dirname( __FILE__ ) . '/mixpanel/lib/Mixpanel.php';
}

function cgc_mixpanel_javascript() {
?>
<!-- start Mixpanel -->
<script type="text/javascript">(function(e,b){if(!b.__SV){var a,f,i,g;window.mixpanel=b;a=e.createElement("script");a.type="text/javascript";a.async=!0;a.src=("https:"===e.location.protocol?"https:":"http:")+'//cdn.mxpnl.com/libs/mixpanel-2.2.min.js';f=e.getElementsByTagName("script")[0];f.parentNode.insertBefore(a,f);b._i=[];b.init=function(a,e,d){function f(b,h){var a=h.split(".");2==a.length&&(b=b[a[0]],h=a[1]);b[h]=function(){b.push([h].concat(Array.prototype.slice.call(arguments,0)))}}var c=b;"undefined"!==
typeof d?c=b[d]=[]:d="mixpanel";c.people=c.people||[];c.toString=function(b){var a="mixpanel";"mixpanel"!==d&&(a+="."+d);b||(a+=" (stub)");return a};c.people.toString=function(){return c.toString(1)+".people (stub)"};i="disable track track_pageview track_links track_forms register register_once alias unregister identify name_tag set_config people.set people.set_once people.increment people.append people.track_charge people.clear_charges people.delete_user".split(" ");for(g=0;g<i.length;g++)f(c,i[g]);
b._i.push([a,e,d])};b.__SV=1.2}})(document,window.mixpanel||[]);
mixpanel.init("018006ab8a267cc6d0a158dbfe41801a");
<?php if( is_page( 'membership' ) ) : ?>
	// Send event for landing on membership
	mixpanel.track( 'Page View: membership' );
<?php elseif( is_page( 'shop' ) ) : ?>
	<?php if( is_user_logged_in() ) : $user_data = get_userdata( get_current_user_id() );?>
	mixpanel.identify( '<?php echo $user_data->user_login; ?>' );
	<?php endif; ?>
	mixpanel.track( 'Page View: Shop' );
<?php elseif( is_singular( 'download' ) ) : ?>
	<?php if( is_user_logged_in() ) : $user_data = get_userdata( get_current_user_id() );?>
	mixpanel.identify( '<?php echo $user_data->user_login; ?>' );
	<?php endif; ?>
	mixpanel.track( 'Page View: Shop Product', { 'product': '<?php the_title_attribute(); ?>' } );
<?php endif; ?>
<?php if( is_page( 'registration' ) && ! is_user_logged_in() ) : ?>
mixpanel.track( 'Page View: registration' );
jQuery(document).ready(function($) {
	jQuery('#rcp_registration_form').submit(function( event ) {
		event.preventDefault();
		var $form = $(this);
		var subscription = $form.find('.radio input:checked').next().text();
		mixpanel.track( 'Form Submit: Registration', { 'Subscription' : subscription }, function() {
			mixpanel.alias( jQuery('#rcp_user_login').val() );
			setTimeout(function(){
				$form.get(0).submit();
			}, 2000);
		});
	} );
});
<?php elseif( is_page( 'registration' ) ) : $user_data = get_userdata( get_current_user_id() ); ?>
	mixpanel.identify( '<?php echo $user_data->user_login; ?>' );
	mixpanel.track( 'Page View: registration' );
<?php endif; ?>
</script><!-- end Mixpanel -->
<?php
}
add_action( 'wp_head', 'cgc_mixpanel_javascript');


// Track general signups
function cgc_rcp_track_initial_signup( $post_data, $user_id, $price ) {

	if( is_user_logged_in() )
		return;

	$mp = Mixpanel::getInstance( CGC_MIXPANEL_API );

	$user         = get_userdata( $user_id );
	$subscription = rcp_get_subscription( $user_id );
	$rcp_payments = new RCP_Payments;
	$new_user     = $rcp_payments->last_payment_of_user( $user_id );
	$user_time    = strtotime( $user->user_registered );
	$ten_min_ago  = strtotime( '-10 Minutes' );
	$renewal      = ! empty( $new_user );
	$upgrade      = $user_time < $ten_min_ago && ! $renewal ? true : false;


	$person_props                  = array();
	$person_props['$first_name']   = $user->first_name;
	$person_props['$last_name']    = $user->last_name;
	$person_props['$email']        = $user->user_email;
	$person_props['$username']     = $user->user_login;
	$person_props['Subscription']  = $subscription;
	$person_props['Status']        = $price > '0' ? 'Pending' : 'Free';
	$person_props['$ip']            = cgc_mixpanel_get_ip();
	//wp_mixpanel()->track_person( $user_id, $person_props );
	$mp->people->set( $user->user_login, $person_props );

	$event_props                   = array();
	$event_props['distinct_id']    = $user->user_login;
	$event_props['Subscription']   = $subscription;

	$mp->identify( $user->user_login );
	$mp->track( 'User Signup', $event_props );
}
add_action( 'rcp_form_processing', 'cgc_rcp_track_initial_signup', 10, 3 );

// Track PayPal signup
function cgc_rcp_confirm_paid_paypal_signup( $payment_data, $user_id, $posted ) {
	if( ! function_exists( 'rcp_get_subscription_name' ) )
		return;

	$mp = Mixpanel::getInstance( CGC_MIXPANEL_API );

	$user         = get_userdata( $user_id );
	$subscription = rcp_get_subscription( $user_id );
	$rcp_payments = new RCP_Payments;
	$new_user     = $rcp_payments->last_payment_of_user( $user_id );
	$user_time    = strtotime( $user->user_registered );
	$ten_min_ago  = strtotime( '-10 Minutes' );
	$renewal      = ! empty( $new_user );
	$upgrade      = $user_time < $ten_min_ago && ! $renewal ? true : false;

	$mp->identify( $user->user_login );

	$person_props                 = array();
	$person_props['$first_name']  = $user->first_name;
	$person_props['$last_name']   = $user->last_name;
	$person_props['$email']       = $user->user_email;
	$person_props['$username']    = $user->user_login;
	$person_props['Subscription'] = $subscription;
	$person_props['Status']       = 'Active';
	switch( $posted['txn_type'] ) :

		// New subscription
		case 'subscr_signup' :

			$event_props                   = array();
			$event_props['distinct_id']    = $user->user_login;
			$event_props['Subscription']   = $subscription;
			$event_props['$created']       = time();
			$event_props['Payment Method'] = 'PayPal';

			if( $upgrade ) {
				$mp->track( 'Citizen Upgrade', $event_props );
			} elseif( $renewal ) {
				$mp->track( 'Citizen Renewal', $event_props );
			}

			$person_props['Recurring'] = 'Yes';

			break;

	endswitch;

	$mp->people->set( $user->user_login, $person_props );

}
add_action( 'rcp_valid_ipn', 'cgc_rcp_confirm_paid_paypal_signup', 10, 3 );

// Track Stripe signup
function cgc_rcp_confirm_paid_stripe_signup( $user_id, $data ) {

	if(  ! function_exists( 'rcp_get_subscription_name' ) )
		return;

	//wp_mixpanel()->set_api_key( CGC_MIXPANEL_API );

	$mp = Mixpanel::getInstance( CGC_MIXPANEL_API );

	$user         = get_userdata( $user_id );

	$mp->identify( $user->user_login );

	$subscription = rcp_get_subscription( $user_id );
	$rcp_payments = new RCP_Payments;
	$new_user     = $rcp_payments->last_payment_of_user( $user_id );
	$user_time    = strtotime( $user->user_registered );
	$ten_min_ago  = strtotime( '-10 Minutes' );
	$renewal      = ! empty( $new_user );
	$upgrade      = $user_time < $ten_min_ago && ! $renewal ? true : false;

	$person_props                  = array();
	$person_props['$first_name']   = $user->first_name;
	$person_props['$last_name']    = $user->last_name;
	$person_props['$email']        = $user->user_email;
	$person_props['$username']     = $user->user_login;
	$person_props['Subscription']  = $subscription;
	$person_props['Status']        = 'Active';
	$person_props['Recurring']     = isset( $data['auto_renew'] ) ? 'Yes' : 'No';

	$mp->people->set( $user->user_login, $person_props );

	$event_props                   = array();
	$event_props['distinct_id']    = $user->user_login;
	$event_props['Subscription']   = $subscription;
	$event_props['Date']           = time();
	$event_props['Payment Method'] = 'Stripe';


	if( $upgrade ) {
		$mp->track( 'Citizen Upgrade', $event_props );
	} elseif( $renewal ) {
		$mp->track( 'Citizen Renewal', $event_props );
	}

}
add_action( 'rcp_stripe_signup', 'cgc_rcp_confirm_paid_stripe_signup', 10, 2 );

// Track recurring payment
function cgc_rcp_track_payment( $payment_id = 0, $args = array(), $amount ) {

	if( ! function_exists( 'rcp_get_subscription_name' ) )
		return;

	$mp = Mixpanel::getInstance( CGC_MIXPANEL_API );

	$user = get_userdata( $args['user_id'] );

	$mp->identify( $user->user_login );

	if( $args['payment_type'] == 'Credit Card' || $args['payment_type'] == 'subscr_payment' ) {

		$subscription = rcp_get_subscription( $args['user_id'] );

		$person_props                  = array();
		$person_props['$first_name']   = $user->first_name;
		$person_props['$last_name']    = $user->last_name;
		$person_props['$email']        = $user->user_email;
		$person_props['$username']     = $user->user_login;
		$person_props['Subscription']  = $subscription;
		$person_props['Status']        = 'Active';
		$person_props['Recurring']     = rcp_is_recurring( $args['user_id'] ) ? 'Yes' : 'No';

		$mp->people->set( $user->user_login, $person_props );


		$event_props                 = array();
		$event_props['distinct_id']  = $user->user_login;
		$event_props['Subscription'] = $subscription;
		$event_props['Amount']       = $amount;
		$event_props['Date']         = time();
		$event_props['Payment Method']= $args['payment_type'];

		//wp_mixpanel()->track_event( 'Subscription Payment', $event_props );
		$mp->track( 'Subscription Payment', $event_props );

	}

	$mp->people->trackCharge( $user->user_login, $amount );
}
add_action( 'rcp_insert_payment', 'cgc_rcp_track_payment', 10, 3 );


function cgc_rcp_track_status_changes( $new_status, $user_id ) {

	if( ! function_exists( 'rcp_get_subscription_name' ) )
		return;

	$user = get_userdata( $user_id );

	//wp_mixpanel()->set_api_key( CGC_MIXPANEL_API );
	$mp = Mixpanel::getInstance( CGC_MIXPANEL_API );

	$mp->identify( $user->user_login );

	/*
	// We check for $_POST to make sure this only fires on the signup form
	if( 'free' === $new_status && isset( $_POST['rcp_level'] ) ) {

		$user                         = get_userdata( $user_id );

		$person_props                 = array();
		$person_props['$first_name']  = $user->first_name;
		$person_props['$last_name']   = $user->last_name;
		$person_props['$email']       = $user->user_email;
		$person_props['$username']    = $user->user_login;
		$person_props['Subscription'] = rcp_get_subscription( $user_id );
		$person_props['Status']       = 'free';

		$mp->people->set( $user_id, $person_props );
		//wp_mixpanel()->track_person( $user_id, $person_props );

		$event_props                 = array();
		$event_props['distinct_id']  = $user_id;
		$event_props['Subscription'] = rcp_get_subscription( $user_id );
		$event_props['Date']         = time();
		$event_props['renewal']      = 'No';

		//wp_mixpanel()->track_event( 'Signup', $event_props );
		$mp->track( 'Signup', $event_props );

	} else
	*/

	if( 'expired' === $new_status ) {

		$person_props                 = array();
		$person_props['$first_name']  = $user->first_name;
		$person_props['$last_name']   = $user->last_name;
		$person_props['$email']       = $user->user_email;
		$person_props['$username']    = $user->user_login;
		$person_props['Subscription'] = rcp_get_subscription( $user_id );
		$person_props['Status']       = 'Expired';
		$person_props['Recurring']    = 'No';

		//wp_mixpanel()->track_person( $user_id, $person_props );
		$mp->people->set( $user->user_login, $person_props );

		$event_props                 = array();
		$event_props['distinct_id']  = $user->user_login;
		$event_props['Subscription'] = rcp_get_subscription( $user_id );
		$event_props['Reason']       = 'Expired';
		$event_props['Date']         = time();

		$mp->track( 'Lost Citizen', $event_props );

	}
}
add_action( 'rcp_set_status', 'cgc_rcp_track_status_changes', 10, 2 );

function cgc_rcp_track_cancelled_paypal( $user_id ) {
	$user                         = get_userdata( $user_id );
	$person_props                 = array();
	$person_props['$first_name']  = $user->first_name;
	$person_props['$last_name']   = $user->last_name;
	$person_props['$email']       = $user->user_email;
	$person_props['$username']    = $user->user_login;
	$person_props['Subscription'] = rcp_get_subscription( $user_id );
	$person_props['Status']       = 'Cancelled';
	$person_props['Recurring']    = 'No';

	//wp_mixpanel()->track_person( $user_id, $person_props );
	$mp->people->set( $user->user_login, $person_props );

	$event_props                 = array();
	$event_props['distinct_id']  = $user->user_login;
	$event_props['Subscription'] = rcp_get_subscription( $user_id );
	$event_props['Reason']       = 'Cancelled';
	$event_props['Date']         = time();

	$mp->track( 'Lost Citizen', $event_props );
}
add_action( 'rcp_ipn_subscr_cancel', 'cgc_rcp_track_cancelled_paypal' );

function cgc_rcp_track_cancelled_stripe( $invoice ) {

	$user_id                      = rcp_stripe_get_user_id( $invoice->customer );
	$user                         = get_userdata( $user_id );
	$person_props                 = array();
	$person_props['$first_name']  = $user->first_name;
	$person_props['$last_name']   = $user->last_name;
	$person_props['$email']       = $user->user_email;
	$person_props['$username']    = $user->user_login;
	$person_props['Subscription'] = rcp_get_subscription( $user_id );
	$person_props['Status']       = 'Cancelled';
	$person_props['Recurring']    = 'No';

	//wp_mixpanel()->track_person( $user_id, $person_props );
	$mp->people->set( $user->user_login, $person_props );

	$event_props                 = array();
	$event_props['distinct_id']  = $user->user_login;
	$event_props['Subscription'] = rcp_get_subscription( $user_id );
	$event_props['Reason']       = 'Cancelled';
	$event_props['Date']         = time();

	$mp->track( 'Lost Citizen', $event_props );
}
add_action( 'rcp_strip_customer.subscription.deleted', 'cgc_rcp_track_cancelled_stripe' );

function cgc_mixpanel_user_login( $logged_in_cookie, $expire, $expiration, $user_id, $status = 'logged_in' ) {

	//if( ! class_exists( 'WP_Mixpanel' ) )
	//	return;

	//wp_mixpanel()->set_api_key( CGC_MIXPANEL_API );
	$mp = Mixpanel::getInstance( CGC_MIXPANEL_API );

	$user = get_userdata( $user_id );

	$mp->identify( $user->user_login );

	$person_props                 = array();
	$person_props['$first_name']  = $user->first_name;
	$person_props['$last_name']   = $user->last_name;
	$person_props['$username']    = $user->user_login;
	$person_props['$email']       = $user->user_email;
	$person_props['$ip']           = cgc_mixpanel_get_ip();

	if( function_exists( 'rcp_get_subscription' ) ) {
		$person_props['Subscription'] = rcp_get_subscription( $user_id );
	}

	//wp_mixpanel()->track_person( $user_id, $person_props );
	$mp->people->set( $user->user_login, $person_props );

	$event_props                  = array();
	$event_props['distinct_id']   = $user->user_login;
	$event_props['sign_on_page']  = $_SERVER['HTTP_REFERER'];
	$event_props['Date']          = time();
	if( function_exists( 'rcp_get_subscription' ) ) {
		$event_props['Subscription'] = rcp_get_subscription( $user_id );
		$event_props['Status']       = rcp_get_status( $user_id );
		$event_props['Recurring']    = rcp_is_recurring( $user_id ) ? 'Yes' : 'No';
	}

	//wp_mixpanel()->track_event( 'Login', $event_props );
	$mp->track( 'Login', $event_props );

}
add_action( 'set_auth_cookie', 'cgc_mixpanel_user_login', 10, 5 );


function cgc_mixpanel_invalid_captcha() {
	global $user_ID;
	$codes = rcp_errors()->get_error_codes();
	if( $codes ) {
		foreach( $codes as $code ) {
			if( $code == 'invalid_recaptcha' ) {
				$mp = Mixpanel::getInstance( CGC_MIXPANEL_API );

				if( is_user_logged_in() ) {

					$user = get_userdata( $user_ID );

					$mp->identify( $user->user_login );

					$person_props                 = array();
					$person_props['$first_name']  = $user->first_name;
					$person_props['$last_name']   = $user->last_name;
					$person_props['$username']    = $user->user_login;
					$person_props['$email']       = $user->user_email;
					$person_props['$ip']           = cgc_mixpanel_get_ip();

					if( function_exists( 'rcp_get_subscription' ) ) {
						$person_props['Subscription'] = rcp_get_subscription( $user_ID );
					}

					$mp->people->set( $user->user_login, $person_props );

				}

				$event_props = array();
				if( is_user_logged_in() ) {
					$event_props['distinct_id']  = $user->user_login;
					$event_props['Subscription'] = rcp_get_subscription( $user_ID );
				} else {
					$event_props['Subscription']       = rcp_get_subscription_name( $_POST['rcp_level'] );
					$event_props['Requested Username'] = $_POST['rcp_user_login'];
				}

				$mp->track( 'Form Submit: reCaptcha Fail', $event_props );
			}
		}
	}
}
add_action( 'rcp_form_errors', 'cgc_mixpanel_invalid_captcha', 999 );



// Track when customers add items to the cart
function cgc_edd_track_added_to_cart( $download_id = 0, $options = array() ) {

	$mp = Mixpanel::getInstance( CGC_MIXPANEL_API );

	if( is_user_logged_in() ) {

		$user = get_userdata( get_current_user_id() );

		$person_props       = array();
		$person_props['ip'] = edd_get_ip();
		$mp->people->set( $user->user_login, $person_props );

		$mp->identify( $user->user_login );
	}

	$event_props = array();

	if( is_user_logged_in() )
		$event_props['distinct_id'] = $user->user_login;

	$event_props['$ip']           = edd_get_ip();
	$event_props['session_id']   = EDD()->session->get_id();
	$event_props['product_name'] = get_the_title( $download_id );
	$event_props['product_price']= edd_get_cart_item_price( $download_id, $options );
	if( function_exists( 'rcp_get_subscription' ) && is_user_logged_in() ) {
		$event_props['subscription'] = rcp_get_subscription( get_current_user_id() );
	}

	$mp->track( 'EDD Added to Cart', $event_props );
}
add_action( 'edd_post_add_to_cart', 'cgc_edd_track_added_to_cart' );

// Track customers landing on the checkout page
function cgc_edd_track_checkout_loaded() {

	if( ! function_exists( 'edd_is_checkout' ) )
		return;

	// Only track the checkout page when the cart is not empty
	if( ! edd_is_checkout() || ! edd_get_cart_contents() )
		return;

	$mp = Mixpanel::getInstance( CGC_MIXPANEL_API );

	if( is_user_logged_in() ) {

		$user = get_userdata( get_current_user_id() );

		$person_props       = array();
		$person_props['ip'] = edd_get_ip();

		$mp->people->set( $user->user_login, $person_props );
	}

	$event_props = array();

	if( is_user_logged_in() )
		$event_props['distinct_id'] = $user->user_login;

	$event_props['$ip']         = edd_get_ip();
	$event_props['session_id'] = EDD()->session->get_id();

	$products = array();
	foreach( edd_get_cart_contents() as $download ) {
		$products[] = get_the_title( $download['id'] );
	}
	$event_props['products']   = implode( ', ', $products );
	$event_props['cart_count'] = edd_get_cart_quantity();
	$event_props['cart_sum']   = edd_get_cart_subtotal();
	if( function_exists( 'rcp_get_subscription' ) && is_user_logged_in() ) {
		$event_props['subscription'] = rcp_get_subscription( get_current_user_id() );
	}

	$mp->track( 'EDD Checkout Loaded', $event_props );

}
add_action( 'template_redirect', 'cgc_edd_track_checkout_loaded' );

// Track completed purchases
function cgc_edd_track_purchase( $payment_id, $new_status, $old_status ) {

	if ( $old_status == 'publish' || $old_status == 'complete' )
		return; // Make sure that payments are only completed once

	// Make sure the payment completion is only processed when new status is complete
	if ( $new_status != 'publish' && $new_status != 'complete' )
		return;

	$mp = Mixpanel::getInstance( CGC_MIXPANEL_API );

	$user_info = edd_get_payment_meta_user_info( $payment_id );
	$user_id   = edd_get_payment_user_id( $payment_id );
	$downloads = edd_get_payment_meta_cart_details( $payment_id );
	$amount    = edd_get_payment_amount( $payment_id );

	if( $user_id <= 0 ) {
		$distinct = $user_info['email'];
	} else {
		$user = get_userdata( $user_id );
		$distinct = $user->user_login;
	}

	$person_props                  = array();
	$person_props['$first_name']   = $user_info['first_name'];
	$person_props['$last_name']    = $user_info['last_name'];
	$person_props['$email']        = $user_info['email'];

	$mp->people->set( $distinct, $person_props );

	$event_props                  = array();
	$event_props['distinct_id']   = $distinct;
	$event_props['amount']        = $amount;
	$event_props['session_id']    = EDD()->session->get_id();
	$event_props['purchase_date'] = strtotime( get_post_field( 'post_date', $payment_id ) );
	$event_props['cart_count']    = edd_get_cart_quantity();

	$products = array();
	foreach( $downloads as $download ) {
		$products[] = get_the_title( $download['id'] );
	}
	$event_props['products'] = implode( ', ', $products );

	$mp->track( 'EDD Sale', $event_props );

	$mp->people->trackCharge( $distinct, $amount );
}
add_action( 'edd_update_payment_status', 'cgc_edd_track_purchase', 100, 3 );


/**
 * Get User IP
 *
 * Returns the IP address of the current visitor
 *
 * @since 1.0.8.2
 * @return string $ip User's IP address
*/
function cgc_mixpanel_get_ip() {
	if ( ! empty( $_SERVER['HTTP_CLIENT_IP'] ) ) {
		//check ip from share internet
	  $ip = $_SERVER['HTTP_CLIENT_IP'];
	} elseif ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
		//to check ip is pass from proxy
	  $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
	} else {
	  $ip = $_SERVER['REMOTE_ADDR'];
	}
	return $ip;
}


/**
 * Get the current page URL
 *
 * @since 1.3
 * @global $post
 * @return string $page_url Current page URL
 */
function cgc_mixpanel_get_current_page_url() {
	global $post;

	if ( is_front_page() ) :
		$page_url = home_url();
	else :
		$page_url = 'http';

		if ( isset( $_SERVER["HTTPS"] ) && $_SERVER["HTTPS"] == "on" )
			$page_url .= "s";

		$page_url .= "://";

		if ( $_SERVER["SERVER_PORT"] != "80" )
			$page_url .= $_SERVER["SERVER_NAME"].":".$_SERVER["SERVER_PORT"].$_SERVER["REQUEST_URI"];
		else
			$page_url .= $_SERVER["SERVER_NAME"].$_SERVER["REQUEST_URI"];
	endif;

	return $page_url;
}