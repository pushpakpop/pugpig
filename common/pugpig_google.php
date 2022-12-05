<?php
/**
 * @file
 * Pugpig Subscriptions
 */
?><?php
/*

Licence:
==============================================================================
(c) 2011, Kaldor Holdings Ltd
This module is released under the GNU General Public License.
See COPYRIGHT.txt and LICENSE.txt

 */?><?php

include_once 'pugpig_subs.php';
include_once 'pugpig_subs_xml.php';

// http://phpseclib.sourceforge.net/
// Add the path to where you installed seclib to your php.ini include_path setting
try {
  @include 'Crypt/RSA.php';
} catch (Exception $e) {
  // in case of partially installed PHPSecLib, we ensure that the include doesn't bring down the server
}

function _pugpig_google_get_check_order_sku($order, $sku, $subscriptionPrefix, $allowedSubscriptionArray)
{
  return property_exists($order, 'purchaseToken')
    && property_exists($order, 'productId')
    && (
         $order->productId==$sku // receipt product is sku
      || in_array($order->productId, $allowedSubscriptionArray) // $receipt product a known subscription
      || (!empty($subscriptionPrefix)
          && strncmp($order->productId, $subscriptionPrefix, strlen($subscriptionPrefix))==0) // receipt product has the subs prefix
    );
}

/*
  returns the product token if and only iff the receipt's product id or the receipt has a valid subscription product id.
 */
function _pugpig_google_get_sku_product_token($data, $sku, $subscriptionPrefix, $allowedSubscriptionArray)
{
  $json = json_decode($data);

  if (!empty($json)) {
    if (property_exists($json, 'orders')) {
      foreach ($json->orders as $order) {
        if (_pugpig_google_get_check_order_sku($order, $sku, $subscriptionPrefix, $allowedSubscriptionArray)) {
          return $order->purchaseToken;
        }
      }
    } elseif (property_exists($json, 'productId')
      && _pugpig_google_get_check_order_sku($json, $sku, $subscriptionPrefix, $allowedSubscriptionArray)) {
        return $json->purchaseToken;
    }
  }

  return null;
}

function _pugpig_google_verify_token($public_key, $signature, $signed_data, $sku, $base_url, $subscriptionPrefix, $allowedSubscriptionArray)
{
  $comments = array();
  $error    = '';
  $status   = 'unknown';

  if (!class_exists('Crypt_RSA')) {
    $comments[] = 'PHPSecLib is not in the PHP path.';
  }

  // $comments[] = "The public key is '$public_key'";
  // $comments[] = "The signature is '$signature'";
  // $comments[] = "The receipt is '$signed_data'";
  // $comments[] = "The sku is '$sku'";
  // $comments[] = "The base url is '$base_url'";
  // $comments[] = "The subscription prefix is '$subscriptionPrefix'";
  // $comments[] = 'The subscription array is (' . implode(', ', $allowedSubscriptionArray) . ')';

  $purchaseToken = _pugpig_google_get_sku_product_token($signed_data, $sku, $subscriptionPrefix, $allowedSubscriptionArray);
  if (empty($purchaseToken)) {
    $status = 'invalid';
    $error  = 'The SKU is not present in the data.';
  } else {
    $status = 'unverified'; // unverified until verified

    $comments[] = 'The SKU is present in the data.';
    $comments[] = 'The purchase token is ' . str_replace("--", "-\n-", $purchaseToken); // Split any --'s otherwise XML is not well-formed

    // verify the data signature
    if (!class_exists('Crypt_RSA')) {
      $error = 'PHPSecLib is not in the PHP path.';
    } else {
      $rsa = new Crypt_RSA();
      $rsa->setSignatureMode(CRYPT_RSA_SIGNATURE_PKCS1);
      $rsa->loadKey("-----BEGIN PUBLIC KEY-----\n" . $public_key . "\n-----END PUBLIC KEY-----");
      if ($rsa->verify($signed_data, base64_decode($signature))) {
        $comments[] = 'verified ok';
        $status     = 'OK';
      } else {
        $comments[] = 'verification failed';
      }
    }
  }

  return array(
    'status'   => $status,
    'comments' => $comments,
    'error'    => $error
    );
}

function pugpig_send_google_edition_credentials($public_key, $signature, $signed_data, $sku, $base_url, $pugpig_secret, $subscriptionPrefix='', $allowedSubscriptionArray = array())
{
  if ($allowedSubscriptionArray == '') {
    $allowedSubscriptionArray = array();
  }
  $result = _pugpig_google_verify_token($public_key, $signature, $signed_data, $sku, $base_url, $subscriptionPrefix, $allowedSubscriptionArray);
  _pugpig_subs_edition_credentials_response($sku, $pugpig_secret, $result['status']=='OK', $result['status'], $result['comments'], array(), $result['error']);
}

function pugpig_google_check_setup()
{
  $status  = 'OK';
  $message = '';

  if (!class_exists('Crypt_RSA')) {
    $message = 'PHPSecLib (RSA) is not in the PHP include path.  Pugpig Google cannot be used until it is.';
    $status  = 'error';
  }

  return array(
    'status'  => $status,
    'message' => $message
    );
}

function _pugpig_google_unit_test_get_receipt($productId, $productToken)
{
  return <<<"BLOCK_RECEIPT_JSON"
{ "nonce" : 1836535032137741465,
    "orders" :
    [{ "notificationId" : "android.test.purchased",
       "orderId" : "12999556515565155651.5565135565155651",
       "packageName" : "com.example.dungeons",
       "productId" : "$productId",
       "developerPayload" : "bGoa+V7g/yqDXvKRqq+JTFn4uQZbPiQJo4pf9RzJ",
       "purchaseTime" : 1290114783411,
       "purchaseState" : 0,
       "purchaseToken" : "$productToken" }]
}
BLOCK_RECEIPT_JSON;
}

function _pugpig_google_unit_test_get_product_token($description, $subscriptionPrefix, $allowedSubscriptionArray, $receiptProductId, $sku, $successProductTokenEmpty)
{
  $receiptProductToken = 'rojeslcdyyiapnqcynkjyyjh';
  print "\n";
  print wp_kses_post("Test: $description\n");
  $receipt = _pugpig_google_unit_test_get_receipt($receiptProductId, $receiptProductToken);
  $productToken = _pugpig_google_get_sku_product_token($receipt, $sku, $subscriptionPrefix, $allowedSubscriptionArray);
  print "...subscriptionPrefix = '" . esc_html($subscriptionPrefix) . "'\n";
  print "...allowedSubscriptionArray = (" . join(', ', $allowedSubscriptionArray) . ")\n"; //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
  print "...receipt product id = '" . esc_html( $receiptProductId ) . "'\n";
  print "...sku = '". esc_html($sku) . "'\n";
  print "...found productToken = '" . esc_html($productToken) . "'\n";
  print (empty($productToken) === $successProductTokenEmpty) ? "ok" : "FAIL";
  print "\n";
}

function pugpig_google_unit_tests()
{
  print "Running Google unit tests...\n";

  $editionPrefix = 'com.kaldorgroup.edition.';
  $subs_prefix   = 'com.kaldorgroup.subs.';
  $subs_1month   = $subs_prefix . '1month';
  $subs_3month   = $subs_prefix . '3months';
  $subs_1year    = $subs_prefix . '1year';
  $all_subs      = array($subs_1month, $subs_3month, $subs_1year);
  $edition1      = $editionPrefix . '1';
  $edition2      = $editionPrefix . '2';

  _pugpig_google_unit_test_get_product_token(
      'receipt has sku', '', array(), $edition1, $edition1, false);

  _pugpig_google_unit_test_get_product_token(
      'receipt does NOT have sku', '', array(), $edition1, $edition2, true);

  _pugpig_google_unit_test_get_product_token(
      'receipt receipt has subscription but no sub prefix set', '', array(), $subs_3month, $edition2, true);

  _pugpig_google_unit_test_get_product_token(
      'receipt receipt has subscription subs prefix set', $subs_prefix, array(), $subs_3month, $edition2, false);

  _pugpig_google_unit_test_get_product_token(
      'receipt receipt has subscription subs array', '', $all_subs, $subs_3month, $edition2, false);

  print "\n";
  print "Test: Example receipt - subs prefix ok\n";
  $successProductTokenEmpty = false;
  $subscriptionPrefix = 'com.pugpig.subscription.';
  $allowedSubscriptionArray = array();
  $receiptProductToken = 'vopnckchdotlxyiambasjkob.AO-J1OynLeBrlA8jKIKbcW8zh_hf2DVwslWLx2KfhorDWcOM255e50dVN0ecvniaSi__XKLKmAWKFqgf-TxK6GlNGwh0g3xvko2d_qn-Aqb5OkIEEg6XP0fm-LSCgJobO2gzChy3W2d5Hblfvn8Faau758Xy6bsu4g';
  $receipt = '{"orderId":"12999763169054705758.1303733092958143","packageName":"com.kaldorgroup.pugpig.substest","productId":"com.pugpig.subscription.7daysa","purchaseTime":1377681728680,"purchaseState":0,"developerPayload":"1001","purchaseToken":"vopnckchdotlxyiambasjkob.AO-J1OynLeBrlA8jKIKbcW8zh_hf2DVwslWLx2KfhorDWcOM255e50dVN0ecvniaSi__XKLKmAWKFqgf-TxK6GlNGwh0g3xvko2d_qn-Aqb5OkIEEg6XP0fm-LSCgJobO2gzChy3W2d5Hblfvn8Faau758Xy6bsu4g"}';
  $sku = 'com.pugpig.edition0094';
  $signature = 'L1huL0ulJacYVXdX3nVusJWxWF0ZOGTp1OfGLN0DYZGxWI759/meXMmABhZnC51fduXQWi6AzdlCbi7AdFXjONhZteC6LFalmea9LvyRcZYpfptQnR/X4djQmHEdXsMUBc/kjdFaWXLLomC8Ze7ZvTyZZws0+XzenjGwbde3B5ibeDX78MDOak4chXvUAgiuky0PFkxftNE0YQzE2OGokfSzWewgo2FoA0Z7OMOQzQ7PTSJ/GTdsC4WvKmKQNPmMafhhGFncBB9f8hkAk6WJh/qbdJtlAUp76U8Lv5HIaFFXd0vNZgwoZzttNMGRFYZ+GjusLiZc1ztcBV2AgW0Ojg==';
  $productToken = _pugpig_google_get_sku_product_token($receipt, $sku, $subscriptionPrefix, $allowedSubscriptionArray);
  print "...subscriptionPrefix = '" . esc_html($subscriptionPrefix) . "'\n";
  print "...allowedSubscriptionArray = (" . join(', ', $allowedSubscriptionArray) . ")\n"; //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
  print "...sku = '" . esc_html($sku) . "'\n";
  print "...found productToken = '" . esc_html($productToken) . "'\n";
  print (empty($productToken) === $successProductTokenEmpty) ? "ok" : "FAIL";
  print "\n";

  // for verificiation function:
  //
  // $rsa = new Crypt_RSA();
  // $rsa->setPublicKeyFormat(CRYPT_RSA_PUBLIC_FORMAT_OPENSSH);
  // $rsa->setSignatureMode(CRYPT_RSA_SIGNATURE_PKCS1);
  // extract($rsa->createKey());
  // $rsa->loadKey($privatekey);

  // $headerlessPrivatekey = str_replace(
  //   array(
  //     "-----BEGIN RSA PRIVATE KEY-----\r\n",
  //     "\r\n-----END RSA PRIVATE KEY-----"),
  //   '', $privatekey);

  // $headerlessPublickey = str_replace(
  //   array(
  //     "ssh-rsa ",
  //     " phpseclib-generated-key"),
  //   '', $publickey);

  // print "using public key ($headerlessPublickey)\n";
  // print "using private key ($headerlessPrivatekey)\n";

  // to sign:
  // $signed_data = $rsa->sign($receipt);
  // $signed_data_base64 = base64_encode($signed_data);
}

/* TODO : USe OAuth2 API etc to check the subscription details
  // http://stackoverflow.com/questions/11115381/unable-to-get-the-subscription-information-from-google-play-android-developer-ap
  // https://code.google.com/p/google-api-php-client/wiki/OAuth2
  // https://code.google.com/p/google-api-php-client/source/browse/trunk/examples/plus/index.php
  // Subscriptions
  if ($result === 'OK' && isset($purchaseToken) and $purchaseToken !== '') {
    // GET https://www.googleapis.com/androidpublisher/v1/applications/<var class="apiparam">packageName</var>/subscriptions/<var class="apiparam">subscriptionId</var>/purchases/<var class="apiparam">token</var>
    $url = $base_url . 'subscriptions/' . $sku . '/purchases/' . $purchaseToken;

    $options = array('timeout' => PUGPIG_CURL_TIMEOUT);
    $response = drupal_http_request($url, $options);

    // https://developers.google.com/android-publisher/v1/purchases#resource
    // {
    //   "kind": "androidpublisher#subscriptionPurchase",
    //   "initiationTimestampMsec": long,
    //   "validUntilTimestampMsec": long,
    //   "autoRenewing": boolean
    // }


    if ($response->code == 200) {
      $json = json_decode($response->data);
      $expiration_date = $json->validUntilTimestampMsec;
      $now = (microtime(true) * 1000);
      if ($expiration_date > $now)
        $result = 'OK';
      else
        $result = 'just_expired';
    } else {
      $result = 'expired';
    }
  }
*/
