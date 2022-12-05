<?php
/**
 * @file
 * Standalone Amazon receipt validator
 * You will need to modify the configuration values to suit your environment:
 */
?><?php
/*

Licence:
==============================================================================
(c) 2013, Kaldor Holdings Ltd
This module is released under the GNU General Public License.
See COPYRIGHT.txt and LICENSE.txt

 */?><?php

include_once "pugpig_utilities.php";
include_once "pugpig_google.php";

if (!file_exists('standalone_config.php')) {
	echo "<h2>Warning - standalone_config.php not found</h2>";
	echo "In order to use this page, you will need to configure settings in the file: <code>standalone_config.php</code>";
	exit();
}
include_once 'standalone_config.php';

if (!defined('PUGPIG_CURL_TIMEOUT')) {
  define('PUGPIG_CURL_TIMEOUT', 20);
}

$check = pugpig_google_check_setup();
if ($check['status']!='OK') {
  echo '<h2>' . esc_html( $check['message'] ) . '</h2>';
  exit();
}

$base_url      = $settings_google['base_url'];
$public_key    = $settings_google['public_key'];

$sig           = isset($_GET['sig'])  ? $_GET['sig']  : '';
$data          = isset($_GET['data']) ? $_GET['data'] : '';
$sku           = isset($_GET['sku'])  ? $_GET['sku']  : '';

// allow subscription-specific overrides if required

if (empty($settings_google['pugpig_creds_secret'])) {
  $pugpig_secret = isset($pugpigCredsSecret) ? $pugpigCredsSecret : '';
} else {
  $pugpig_secret = $settings_google['pugpig_creds_secret'];
}

if (empty($settings_google['subscription_prefix'])) {
  $subscription_prefix = isset($subscriptionPrefix) ? $subscriptionPrefix : '';
} else {
  $subscription_prefix = $settings_google['subscription_prefix'];
}

if (empty($settings_google['allowed_subscriptions'])) {
  $allowed_subscription_array = isset($allowedSubscriptionArray) ? $allowedSubscriptionArray : '';
} else {
  $allowed_subscription_array = $settings_google['allowed_subscriptions'];
}

pugpig_send_google_edition_credentials($public_key, $sig, urldecode($data), $sku, $base_url, $pugpig_secret, $subscription_prefix, $allowed_subscription_array);
