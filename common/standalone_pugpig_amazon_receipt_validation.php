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
include_once "pugpig_amazon.php";

if (!file_exists('standalone_config.php')) {
	echo "<h2>Warning - standalone_config.php not found</h2>";
	echo "In order to use this page, you will need to configure settings in the file: <code>standalone_config.php</code>";
	exit();
}
include_once 'standalone_config.php';

if (!defined('PUGPIG_CURL_TIMEOUT')) {
  define('PUGPIG_CURL_TIMEOUT', 20);
}

if (empty($settings_amazon['base_url']) || empty($settings_amazon['secret']) ) {
		echo "<h2>Warning - base_url and secret need to be set</h2>";
		exit();
}

// settings
$base_url = $settings_amazon['base_url'];
$amazon_secret = $settings_amazon['secret'];

$user_id       = isset($_REQUEST['user_id'])     ? $_REQUEST['user_id']     : '';
$product_sku   = isset($_REQUEST['product_sku']) ? $_REQUEST['product_sku'] : '';
$subs_sku      = isset($_REQUEST['subs_sku'])    ? $_REQUEST['subs_sku']    : '';
$token         = isset($_REQUEST['token'])       ? $_REQUEST['token']       : '';

$fail_copy = array();

if (empty($user_id)) {
  $fail_copy[] = 'user_id';
}

if (empty($token)) {
  $fail_copy[] = 'token';
}

if (empty($product_sku) && empty($subs_sku)) {
  $fail_copy[] = 'either product_sku or subs_sku - depending on whether product is a product or subscription.';
}

if (!empty($fail_copy)){
  header('text/plain');
  if (function_exists("http_response_code")){
    http_response_code(400);
  }
  echo "Please specify the following parameters in a POST or GET request (POST is recommended): \n  " . implode(",  ", $fail_copy);//phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
  exit();
}

$pugpig_secret = $pugpigCredsSecret;

if (empty($settings_amazon['pugpig_creds_secret'])) {
  $pugpig_secret = isset($pugpigCredsSecret) ? $pugpigCredsSecret : '';
} else {
  $pugpig_secret = $settings_amazon['pugpig_creds_secret'];
}

$rvs_version = empty($settings_amazon['rvs_version'])?'2.0':$settings_amazon['rvs_version'];

pugpig_send_amazon_edition_credentials($user_id, $product_sku, $subs_sku, $token, $base_url, $amazon_secret, $pugpig_secret, $proxy_server, $proxy_port, $rvs_version);
