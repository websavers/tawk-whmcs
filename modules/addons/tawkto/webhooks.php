<?php

/**
 * This file is designed to be accessed directly via Tawk.to webhooks
 * Docs: https://developer.tawk.to/webhooks/
 * To use this file, upload it to your WHMCS tawk folder under modules/addons/tawkto
 * 1. Go to dashboard.tawk.to > Administration > Settings > Webhooks
 * 2. Create Webhook and specify New Ticket event
 * 3. Enter Request Endpoint URL as: URL_TO_WHMCS/modules/addons/tawkto/webhooks.php
 * 4. Update the variable below with the Secret Key that Tawk provides (be sure you do this on the live copy of this file)
 * 5. In tawk.to under Settings > Mail Notifications, you probably want to set "Send New Ticket notificatoins to" to Nobody.
**/

const WEBHOOK_SECRET = 'webhook secret key';

$body = file_get_contents('php://input');

function verifySignature ($body, $signature) {
    $digest = hash_hmac('sha1', $body, WEBHOOK_SECRET);
    return $signature === $digest ;
}
if (!verifySignature($body, $_SERVER['HTTP_X_TAWK_SIGNATURE'])) {
    // verification failed
    die('Verification Failed');
}
// verification success

// Import WHMCS
require( "../../../init.php" );

// Database access
//use Illuminate\Database\Capsule\Manager as Capsule;
//use WHMCS\Database\Capsule;

$tawk_data = json_decode($body);

switch($tawk_data->event){ //4 possible events as follows

    case 'chat:start':
    case 'chat:end':
    case 'chat:transcript_created':
        //Do nothing
        break;

    case 'ticket:create':

        if (!filter_var($tawk_data->requester->email, FILTER_VALIDATE_EMAIL)) {
            die('Invalid email address provided');
        }

        $user_id = false;
        $client_id = false;

/* This code associates a user and/or client by email address. It is commented out
 * because we have no way of verifying that the user is authenticated, thus making it consistent
 * with WHMCS's behaviour of not associating ticket with a client unless they're logged into their account.
 * You may be comfortable using it if you only show your tawk widget to logged in customers.
        $w_u_results = localAPI('GetUsers', array('search' => $tawk_data->requester->email));
        if ($w_u_results['result'] === 'success'){
            $user = $w_u_results['users'][0];
            $user_id = $user['id'];
            foreach($user['clients'] as $client){
                if ($client['isOwner']){
                    $client_id = $client['id'];
                }
            }
        }
*/
        $open_ticket_data = array(
            'deptid' => '1',
            'subject' => $tawk_data->ticket->subject,
            'message' => $tawk_data->ticket->message,
            'priority' => 'Medium',
            'markdown' => false,
        );

        if ( $client_id ) $open_ticket_data['clientid'] = $client_id;
        if ( $client_id && $user_id ) $open_ticket_data['userid'] = $user_id;
        if ( !$client_id ){
            $open_ticket_data['email'] = $tawk_data->requester->email;
            $open_ticket_data['name'] = $tawk_data->requester->name;
        }

        $whmcs_results = localAPI('OpenTicket', $open_ticket_data);
        if ( $whmcs_results['result'] == 'success' ){
            logActivity("[Tawk Webhook] Created ticket ID " . $whmcs_results['id']);
        }
        else{
            logActivity("[Tawk Webhook] Failed to create ticket from Tawk ticket ID " . $tawk_data->ticket->id);
        }

        break;

    default: 
}
//http_response_code(200);