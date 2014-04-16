<?php
/**
 * Plugin Name: CGC Mixpanel Tracking
 * Description: Tracks CGC data through Mixpanel
 * Author: Pippin Williamson
 * Author URI: http://pippinsplugins.com
 * Version: 1.0
 */


if( strpos( home_url(), 'staging' ) !== false ) {
	define( 'CGC_MIXPANEL_API', '207a9aafeae87dcf4a66c3b5490bbbc9' );
} else {
	define( 'CGC_MIXPANEL_API', 'bc9dfb4de83d2bf2f968eaa9ce2e0cc7' );
}

if( ! class_exists( 'Mixpanel' ) ) {
	require dirname( __FILE__ ) . '/mixpanel/lib/Mixpanel.php';
}


function cgc_mixpanel_js() {

	global $user_login;
	get_currentuserinfo();

?>
	<!-- Mixpanel -->
	<script type="text/javascript">

	(function(e,b){if(!b.__SV){var a,f,i,g;window.mixpanel=b;a=e.createElement("script");a.type="text/javascript";a.async=!0;a.src=("https:"===e.location.protocol?"https:":"http:")+'//cdn.mxpnl.com/libs/mixpanel-2.2.min.js';f=e.getElementsByTagName("script")[0];f.parentNode.insertBefore(a,f);b._i=[];b.init=function(a,e,d){function f(b,h){var a=h.split(".");2==a.length&&(b=b[a[0]],h=a[1]);b[h] =function(){b.push([h].concat(Array.prototype.slice.call(arguments,0)))}}var c=b;"undefined"!==
	typeof d?c=b[d] =[]:d="mixpanel";c.people=c.people||[];c.toString=function(b){var a="mixpanel";"mixpanel"!==d&&(a+="."+d);b||(a+=" (stub)");return a};c.people.toString=function(){return c.toString(1)+".people (stub)"};i="disable track track_pageview track_links track_forms register register_once alias unregister identify name_tag set_config people.set people.set_once people.increment people.append people.track_charge people.clear_charges people.delete_user".split(" ");for(g=0;g<i.length;g++)f(c,i[g]);
	b._i.push([a,e,d])};b.__SV=1.2}})(document,window.mixpanel||[]);

	mixpanel.init("<?php echo CGC_MIXPANEL_API; ?>", {
		loaded: function() {
			var referrer = mixpanel.get_property( "$initial_referring_domain" );
		
			<?php if( is_page( 'registration' ) && ! is_user_logged_in() ) : ?>
				mixpanel.people.set_once( "Initial Referrer", referrer );
				mixpanel.track( 'Page View: registration' );
			<?php elseif( is_page( 'registration' ) && is_user_logged_in() && strpos( $_SERVER['HTTP_REFERER'], 'registration' ) === false ) : ?>

				mixpanel.identify( '<?php echo $user_login; ?>' );
				// mixpanel.people.set_once( "Initial Referrer", $initial_referring_domain );
				mixpanel.track( 'Page View: registration' );
			<?php endif; ?>
			

			var logged_in = cgc_get_query_vars()["logged-in"];

			if( logged_in ) {
				jQuery.ajax({
					type: "POST",
					data: {
						action: 'cgc_mixpanel_identify'
					},
					dataType: "json",
					url: "<?php echo admin_url( 'admin-ajax.php' ); ?>",
					success: function (response) {

						// mixpanel.alias( response.distinct_id );
						mixpanel.identify( response.user_login );

					}
				}).fail(function (response) {

				}).done(function (response) {

				});
			} 

		}
	});

	function cgc_get_query_vars() {
		var vars = [], hash;
		var hashes = window.location.href.slice(window.location.href.indexOf('?') + 1).split('&');
		for(var i = 0; i < hashes.length; i++) {
			hash = hashes[i].split('=');
			vars.push(hash[0]);
			vars[hash[0]] = hash[1];
		}
		return vars;
	}
	</script>
<?php
}
add_action( 'wp_head', 'cgc_mixpanel_js' );

function cgc_mixpanel_ajax() {
	if( is_user_logged_in() ) {
		global $user_login;
		get_currentuserinfo();
		echo json_encode( array( 
			'user_login' => $user_login,
			'distinct_id' => cgc_mixpanel_get_id_from_cookie()
			) );
		die();
	}
	die('-2');
}
add_action( 'wp_ajax_cgc_mixpanel_identify', 'cgc_mixpanel_ajax' );

function cgc_mixpanel_user_login( $logged_in_cookie, $expire, $expiration, $user_id, $status = 'logged_in' ) {

	$mp = Mixpanel::getInstance( CGC_MIXPANEL_API );

	$user = get_userdata( $user_id );

	$mp->identify( $user->user_login );

	$person_props                 = array();
	$person_props['$first_name']  = $user->first_name;
	$person_props['$last_name']   = $user->last_name;
	$person_props['$username']    = $user->user_login;
	$person_props['$email']       = $user->user_email;
	$person_props['$country_code'] = cgc_mixpanel_get_ip();

	if( function_exists( 'rcp_get_subscription' ) ) {

		$person_props['Account Type'] = rcp_is_active( $user_id ) ? 'Citizen' : 'Basic';
		$person_props['Account Level'] = rcp_get_subscription( $user_id );
		$person_props['Account Status'] = ucwords( rcp_get_status( $user_id ) );
		$person_props['Expiration']   = rcp_get_expiration_date( $user_id );
		$person_props['Recurring']    = rcp_is_recurring( $user_id ) ? 'Yes' : 'No';

	}

	$mp->people->set( $user->user_login, $person_props, $person_props, array( '$ip' => cgc_mixpanel_get_ip() ) );

	$event_props                  = array();
	$event_props['distinct_id']   = $user->user_login;
	$event_props['$ip']           = cgc_mixpanel_get_ip();
	if( function_exists( 'rcp_get_subscription' ) ) {
		$event_props['Account Type']   = rcp_is_active( $user_id ) ? 'Citizen' : 'Basic';
		$event_props['Account Status'] = ucwords( rcp_get_status( $user_id ) );
		$event_props['Account Level']  = rcp_get_subscription( $user_id );
	}
	$mp->track( 'Login', $event_props );

}
add_action( 'set_auth_cookie', 'cgc_mixpanel_user_login', 10, 5 );

// Track general signups
function cgc_rcp_track_account_created( $user_id, $newsletters ) {

	$mp = Mixpanel::getInstance( CGC_MIXPANEL_API );

	$user = get_userdata( $user_id );

	$person_props                  = array();
	$person_props['$first_name']   = $user->first_name;
	$person_props['$last_name']    = $user->last_name;
	$person_props['$email']        = $user->user_email;
	$person_props['$username']     = $user->user_login;
	$person_props['Account Type']  = 'Basic';
	$person_props['Account Status'] = 'Free';
	$person_props['newsletters']   = implode( ',', $newsletters );
	$person_props['$created']      = date( 'Y-m-d H:i:s' );

	$event_props                   = array();
	$event_props['distinct_id']    = $user->user_login;
	$event_props['Account Type']   = 'Basic';
	$event_props['Account Status'] = 'Free';
	$event_props['Email']          = $user->user_email;
	$event_props['Account Created Date'] = date( 'Y-m-d H:i:s' );
	$event_props['newsletters']    = implode( ',', $newsletters );

	$mp->createAlias( cgc_mixpanel_get_id_from_cookie(), $user->user_login );
	$mp->identify( $user->user_login, cgc_mixpanel_get_id_from_cookie() );

	$mp->people->set( $user->user_login, $person_props, array( '$ip' => cgc_mixpanel_get_ip() ) );

	$mp->track( 'Account Created', $event_props );
}
add_action( 'cgc_rcp_account_created', 'cgc_rcp_track_account_created', 10, 2 );

// Track account upgrade
function cgc_rcp_account_upgrade( $user_id, $data ) {

	if(  ! function_exists( 'rcp_get_subscription_name' ) )
		return;

	$mp = Mixpanel::getInstance( CGC_MIXPANEL_API );

	$user         = get_userdata( $user_id );

	$mp->identify( $user->user_login );

	$subscription = rcp_get_subscription( $user_id );
	$expiration   = rcp_get_expiration_date( $user_id );
	$recurring    = rcp_is_recurring( $user_id ) ? 'Yes' : 'No';
	$rcp_payments = new RCP_Payments;
	$new_user     = $rcp_payments->last_payment_of_user( $user_id );
	$user_time    = strtotime( $user->user_registered, current_time( 'timestamp' ) );
	$renewal      = ! empty( $new_user );
	$upgrade      = $user_time < $ten_min_ago && ! $renewal ? true : false;

	$person_props                  = array();
	$person_props['$first_name']   = $user->first_name;
	$person_props['$last_name']    = $user->last_name;
	$person_props['$email']        = $user->user_email;
	$person_props['$username']     = $user->user_login;
	$person_props['Account Type']  = 'Citizen';
	$person_props['Account Status'] = 'Active';
	$person_props['Account Level'] = $subscription;
	$person_props['Recurring']	   = $recurring;
	$person_props['Expiration']	   = $expiration;
	$person_props['$created']      = date( 'Y-m-d H:i:s' );

	$mp->people->set( $user->user_login, $person_props, array( '$ip' => cgc_mixpanel_get_ip() ) );

	$event_props                   = array();
	$event_props['distinct_id']    = $user->user_login;
	$event_props['Account Type']   = 'Citizen';
	$event_props['Account Status'] = 'Active';
	$event_props['Account Level']  = $subscription;
	$event_props['Recurring']	   = $recurring;
	$event_props['Expiration']	   = $expiration;
	$event_props['Renewal']        = $renewal ? 'Yes' : 'No';
	$event_props['Time Since Creation'] = human_time_diff( $user_time, current_time( 'timestamp' ) );

	$mp->track( 'Account Upgraded', $event_props );

}
add_action( 'rcp_stripe_signup', 'cgc_rcp_account_upgrade', 10, 2 );

// Track account upgrade via gift redemption
function cgc_rcp_account_upgrade_via_gift( $user_id, $discount, $subscription ) {

	if(  ! function_exists( 'rcp_get_subscription_name' ) )
		return;

	$mp = Mixpanel::getInstance( CGC_MIXPANEL_API );

	$user         = get_userdata( $user_id );

	$mp->identify( $user->user_login );

	$subscription = rcp_get_subscription( $user_id );
	$expiration   = rcp_get_expiration_date( $user_id );
	$rcp_payments = new RCP_Payments;
	$new_user     = $rcp_payments->last_payment_of_user( $user_id );
	$user_time    = strtotime( $user->user_registered, current_time( 'timestamp' ) );
	$renewal      = ! empty( $new_user );
	$upgrade      = $user_time < $ten_min_ago && ! $renewal ? true : false;

	$person_props                  = array();
	$person_props['$first_name']   = $user->first_name;
	$person_props['$last_name']    = $user->last_name;
	$person_props['$email']        = $user->user_email;
	$person_props['$username']     = $user->user_login;
	$person_props['Account Type']  = 'Citizen';
	$person_props['Account Status'] = 'Active';
	$person_props['Account Level'] = $subscription;
	$person_props['Recurring']	   = 'No';
	$person_props['Expiration']	   = $expiration;
	$person_props['$created']      = date( 'Y-m-d H:i:s' );

	$mp->people->set( $user->user_login, $person_props, array( '$ip' => cgc_mixpanel_get_ip() ) );

	$event_props                   = array();
	$event_props['distinct_id']    = $user->user_login;
	$event_props['Account Type']   = 'Citizen';
	$event_props['Account Status'] = 'Active';
	$event_props['Recurring']	   = 'No';
	$event_props['Expiration']	   = $expiration;
	$event_props['Account Level']  = $subscription;
	$event_props['Renewal']        = $renewal ? 'Yes' : 'No';
	$event_props['Time Since Creation'] = human_time_diff( $user_time, current_time( 'timestamp' ) );

	$mp->track( 'Account Upgraded', $event_props );

}
add_action( 'cgc_gift_redeemed', 'cgc_rcp_account_upgrade_via_gift', 10, 3 );

// Track recurring payment
function cgc_rcp_track_payment( $payment_id = 0, $args = array(), $amount ) {

	if( ! function_exists( 'rcp_get_subscription_name' ) )
		return;

	global $wpdb;

	$mp = Mixpanel::getInstance( CGC_MIXPANEL_API );

	$user = get_userdata( $args['user_id'] );

	$mp->identify( $user->user_login );

	$rcp_payments = new RCP_Payments;
	$subscription = rcp_get_subscription( $user_id );

	// Get the last payment of the user
	$this_payment = $rcp_payments->get_payment( $payment_id );
	$last_payment = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM " . rcp_get_payments_db_name() . " WHERE `user_id`='%d' AND id < '%d' ORDER BY id DESC LIMIT 1;", $args['user_id'], $payment_id ) );
	$renewal      = ! empty( $last_payment );

	// get expiration
	$expiration   = rcp_get_expiration_date( $user->ID );

	$person_props                  = array();
	$person_props['$first_name']   = $user->first_name;
	$person_props['$last_name']    = $user->last_name;
	$person_props['$email']        = $user->user_email;
	$person_props['$username']     = $user->user_login;
	$person_props['Account Type']  = 'Citizen';
	$person_props['Account Status'] = 'Active';
	$person_props['Expiration']	   = $expiration;
	$person_props['Account Level'] = rcp_get_subscription( $user->ID );
	$person_props['Last Payment Date'] = $this_payment->date;

	$mp->people->set( $user->user_login, $person_props );

	$event_props                 = array();
	$event_props['distinct_id']  = $user->user_login;
	$event_props['Value']        = $amount;
	$event_props['Account Level'] = rcp_get_subscription( $user->ID );
	$event_props['Payment Type'] = $renewal ? 'Renewal' : 'Initial';
	$event_props['Last Payment Date'] = $this_payment->date;
	$event_props['Expiration']	   = $expiration;

	$mp->track( 'Membership Payment', $event_props );

	$mp->people->trackCharge( $user->user_login, $amount );

	// reset downloads for monthly members
	if  ( $subscription == 'Monthly' || $subscription == 'Citizen Monthly' ){
		$mp->people->set( $user->user_login, "Number of Downloads", 0 );
	}
	
}
add_action( 'rcp_insert_payment', 'cgc_rcp_track_payment', 10, 3 );

function cgc_rcp_track_cancelled_paypal( $user_id ) {
	$user                          = get_userdata( $user_id );
	$person_props                  = array();
	$person_props['$first_name']   = $user->first_name;
	$person_props['$last_name']    = $user->last_name;
	$person_props['$email']        = $user->user_email;
	$person_props['$username']     = $user->user_login;
	$person_props['Account Status'] = 'Cancelled';

	$mp->people->set( $user->user_login, $person_props );

	$event_props                 = array();
	$event_props['distinct_id']  = $user->user_login;
	$event_props['Account Level'] = $subscription;
	$event_props['Reason']       = 'Cancelled';

	$mp->track( 'Membership Termination', $event_props );
}
add_action( 'rcp_ipn_subscr_cancel', 'cgc_rcp_track_cancelled_paypal' );

function cgc_rcp_track_cancelled_stripe( $invoice ) {

	$user_id                       = rcp_stripe_get_user_id( $invoice->customer );
	$user                          = get_userdata( $user_id );
	$person_props                  = array();
	$person_props['$first_name']   = $user->first_name;
	$person_props['$last_name']    = $user->last_name;
	$person_props['$email']        = $user->user_email;
	$person_props['$username']     = $user->user_login;
	$person_props['Account Status'] = 'Cancelled';

	$mp->people->set( $user->user_login, $person_props );

	$event_props                 = array();
	$event_props['distinct_id']  = $user->user_login;
	$event_props['Account Level'] = $subscription;
	$event_props['Reason']       = 'Cancelled';

	$mp->track( 'Membership Termination', $event_props );
}
add_action( 'rcp_stripe_customer.subscription.deleted', 'cgc_rcp_track_cancelled_stripe' );


function cgc_rcp_track_status_changes( $new_status, $user_id ) {

	if( ! function_exists( 'rcp_get_subscription_name' ) )
		return;

	$user = get_userdata( $user_id );

	$mp = Mixpanel::getInstance( CGC_MIXPANEL_API );

	$mp->identify( $user->user_login );

	$subscription = rcp_get_subscription( $user_id );

	if( 'expired' === $new_status ) {

		$person_props                 = array();
		$person_props['$first_name']  = $user->first_name;
		$person_props['$last_name']   = $user->last_name;
		$person_props['$email']       = $user->user_email;
		$person_props['$username']    = $user->user_login;

		if ( $subscription === 'Trial' ) {
			$person_props['Account Status'] = 'Trial Expired';
		}
		else {
			$person_props['Account Status'] = 'Expired';
		}

		$mp->people->set( $user->user_login, $person_props );

		$event_props                 = array();
		$event_props['distinct_id']  = $user->user_login;
		$event_props['Account Level'] = $subscription;
	
		if ( $subscription === 'Trial' ) {
				$event_props['Account Status'] = 'Trial Expired';
		}
		else {
			$event_props['Account Status'] = 'Expired';
		}

		$mp->track( 'Membership Termination', $event_props );

	}
}
add_action( 'rcp_set_status', 'cgc_rcp_track_status_changes', 10, 2 );

function cgc_track_file_download( $filename, $url, $type, $user_id, $post_id ) {

	if( ! function_exists( 'rcp_get_subscription_name' ) )
		return;

	$user = get_userdata( $user_id );

	$mp = Mixpanel::getInstance( CGC_MIXPANEL_API );

	$mp->identify( $user->user_login );

	$mp->people->increment( $user->user_login, "Number of Downloads", 1 );

	$event_props                 = array();
	$event_props['distinct_id']  = $user->user_login;
	$event_props['Type']         = $type;
	$event_props['File Name']    = $filename;
	$event_props['Parent Name']  = get_the_title( $post_id );

	$mp->track( 'Download File', $event_props );

}
add_action( 'cgc_file_download', 'cgc_track_file_download', 10, 5 );

/*

// Track when customers add items to the cart
function cgc_edd_track_added_to_cart( $download_id = 0, $options = array() ) {

	$mp = Mixpanel::getInstance( CGC_MIXPANEL_API );

	if( is_user_logged_in() ) {

		$user = get_userdata( get_current_user_id() );

		$person_props       = array();
		$person_props['$ip'] = edd_get_ip();
		$mp->people->set( $user->user_login, $person_props );

		$mp->identify( $user->user_login );
	}

	$event_props = array();

	if( is_user_logged_in() )
		$event_props['distinct_id'] = $user->user_login;

	$event_props['$ip']          = edd_get_ip();
	$event_props['session_id']   = EDD()->session->get_id();
	$event_props['product_name'] = get_the_title( $download_id );
	$event_props['product_price'] = edd_get_cart_item_price( $download_id, $options );
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
		$person_props['$ip'] = edd_get_ip();

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
*/

/**
 * Get ID from Cookie
 *
 * Returns the unique ID of in teh users cookie
 *
 * @since 1.0.8.2
 * @return string $ip User's IP address
*/
function cgc_mixpanel_get_id_from_cookie() {
	foreach($_COOKIE as $name => $value) {
		if(strpos($name, 'mp_') === 0) {		
			$cookie = json_decode(stripslashes(urldecode($value)));
			return($cookie->distinct_id);
		}
	}
}

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