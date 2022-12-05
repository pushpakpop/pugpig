<?php
/**
 * @file
 * Pugpig Notifications
 */
?><?php
/*

Licence:
==============================================================================
(c) 2011, Kaldor Holdings Ltd
This module is released under the GNU General Public License.
See COPYRIGHT.txt and LICENSE.txt

 */?><?php

define('PUSHURL', 'https://go.urbanairship.com/api/push/broadcast/');

/************************************************************************
Ping Urban Airship
Code taken from their site and modified
************************************************************************/
function pugpig_push_to_urban_airship_curl($key, $secret, $json, $proxy_server = '', $proxy_port = '')
{
 $session = curl_init(PUSHURL);
 curl_setopt($session, CURLOPT_USERPWD, $key . ':' . $secret);
 curl_setopt($session, CURLOPT_POST, true);
 curl_setopt($session, CURLOPT_SSL_VERIFYPEER, false);
 curl_setopt($session, CURLOPT_POSTFIELDS, $json);
 curl_setopt($session, CURLOPT_HEADER, false);
 curl_setopt($session, CURLOPT_RETURNTRANSFER, true);
 curl_setopt($session, CURLOPT_HTTPHEADER, array('Content-Type:application/json;charset=utf-8'));
 curl_setopt($session, CURLOPT_TIMEOUT, PUGPIG_CURL_TIMEOUT);

 if (!empty($proxy_server) && (!empty($proxy_port))) {
  curl_setopt($session, CURLOPT_PROXY, "$proxy_server:$proxy_port");
}

 $content = curl_exec($session);

 // Check if any error occured
 $response = curl_getinfo($session);
 curl_close($session);

 $response['content'] = $content;

 return $response;
}

/************************************************************************
Send a Newsstand push. If message is supplied, also send an alert.
************************************************************************/
// TODO: These should probably be scheduled so they are further apart
// Urban Airship does allow scheduling in the JSON array
function pugpig_send_urban_airship_push($key, $secret, $num, $message, $content_available = false, $proxy_server = '', $proxy_port = '')
{
  if (empty($message) && !$content_available) {
    // note that we shouldn't get here as the client should
    // not attempt sending without a payload
    return "Did not send a Push as no message nor content specified.";
  }

  $contents = array();

  if (!empty($message)) {
    $contents['alert'] = $message;
  }

  if ($content_available) {
    $contents['badge'] = $num;
    $contents['content-available'] = 1;
  }

  $push = array("aps" => $contents);
  $json = wp_json_encode($push);

  $response = pugpig_push_to_urban_airship_curl($key, $secret, $json, $proxy_server, $proxy_port);

  if ($response['http_code'] != 200) {
    $code    = $response['http_code'];
    $content = $response['content'];

    return "Failed to send Newsstand push. Push to Urban Airship got negative response from server ($code - $content). Please confirm your settings.";
  }

  if ($content_available) {
    if (empty($message)) {
      return "Sent Newsstand Push with no Message";
    } else {
      return "Sent Newsstand Push with Message (". $message.")";
    }
  }

  return "Sent Message only (". $message.")";
}
