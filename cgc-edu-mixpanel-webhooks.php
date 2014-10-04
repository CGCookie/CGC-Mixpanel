<?php
define('WEBHOOK_SECRET_KEY', 'ce6LttYcxnvwWXyaQ3XRfsKvRuyNWaffwybAkNgt');

function cgc_verify_helpscout($data, $signature) {
	$calculated = base64_encode(hash_hmac('sha1', $data, WEBHOOK_SECRET_KEY, true));
	return $signature == $calculated;
}

$signature = $_SERVER['HTTP_X_HELPSCOUT_SIGNATURE'];
$data = file_get_contents('php://input');


function cgc_mixpanel_helpscount_listener() {

	if (cgc_verify_helpscout($data, $signature)) {
		// do something

		if ( isset( $_GET['listener'] ) && $_GET['listener'] == 'cgc-helpscout' ) {
 
		// retrieve the request's body and parse it as JSON
		$body         = @file_get_contents( 'php://input' );
		$webhook_data = json_decode( $body );



		// Conversation created in Helpscout
		cgc_helpscount_conversation_created($webhook_data->customer->email, $webhook_data->ticket->number)

		} 
 
	}

}
add_action('init', 'cgc_mixpanel_helpscount_listener')
