<?php
/**
 * @file
 * Pugpig Standalone Configuration
 * $iTunesSecret - Get this value from your iTunes Connect account. It is used to verify receipts with iTunes.
 * $subscriptionPrefix -All iTunes Connect products starting with this prefix will be treated as subscription products. For example com.pugpig.subscription.
 * $pugpigCredsSecret -  This is the secret used to generate and decode edition credentials. It needs to be used in the env config settings for the Varnish config if this is being used.
 */

?><?php
/*

Licence:
==============================================================================
(c) 2011, Kaldor Holdings Ltd
This module is released under the GNU General Public License.
See COPYRIGHT.txt and LICENSE.txt

 */?><?php

  /***** SETTINGS FOR ITUNES RECEIPT VALIDATION *****/
  $iTunesSecret = 'YOUR_ITUNES_SECRET_HERE';

  /***** SETTINGS FOR AMAZON STORE VALIDATION *****/
  $settings_amazon = array(
    'base_url' => 'http://appstore-sdk.amazon.com',
    'secret'   => 'my_amazon_secret',
    'rvs_version' => '2.0' // 2.0 for original functionality (IAP v1) and 1.0 (IAP v2) for the new functionality
    );

  $settings_google = array(
    'base_url' => 'https://www.googleapis.com/androidpublisher/v1/applications/com.kaldorgroup.poc/',
    'public_key'   => 'my_public_google_key',
    //'subscription_prefix' => 'com.mycompany.subscription.google', // don't set if you want to use $subscriptionPrefix
    //'allowed_subscriptions' => 'com.mycompany.subscription.google.monthly_1', // don't set if you want to use $subscriptionPrefix
    );

  /***** SETTINGS EDITION CREDENTIAL VALIDATION *****/
  $subscriptionPrefix = 'com.mycompany.subscription';
  $pugpigCredsSecret = 'MY_TOP_SECRET_PUGPIG_CREDS';

  // $iTunesSubscriptionPrefix = 'com.mycompany.subscription.itunes'; // don't set if you want to use $subscriptionPrefix

  /***** SETTINGS FOR DEBUG (OR RESTRICTED OUTBOUND ACCESS) *****/
  $proxy_server = '';
  $proxy_port = '';

  /***** SETTINGS FOR AUTH TEST FORM *****/
  $title = "My Subscription Test Form";
  $urls["base"] = pugpig_get_current_base_url() . "/mysubssystem/";
  $auth_test_default_product_id = 'com.pugpig.test.issue12345';

  $params = array("username", "password");

  $test_users = array(
  	array("state" => "ACTIVE", "username" => "gooduser", "password" => "password"),
  	array("state" => "INACTIVE", "username" => "lapseduser", "password" => "password"),
  	array("state" => "UNKNOWN", "username" => "rubbish", "password" => "rubbish")
  );
