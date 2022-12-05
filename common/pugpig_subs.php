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

include_once "pugpig_subs_xml.php";

function pugpig_generate_password($productId, $username, $secret)
{
  if (!isset($secret) || $secret == '') {
    echo 'The secret phrase needed by the authentication process is not yet set. Please updated your Pugpig settings.';
    exit;
  }

  $password = sha1("$productId:$username:$secret");

  return $password;
}

function pugpig_validate_receipt_with_itunes($itunesUrl, $jsonReceipt, $proxy_server, $proxy_port)
{
  $textResult = null;

  $ch = curl_init();
  curl_setopt($ch, CURLOPT_URL, $itunesUrl);
  curl_setopt($ch, CURLOPT_POST, 1);
  curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonReceipt);
  curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
  curl_setopt($ch, CURLOPT_HEADER, 0);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
  curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
  curl_setopt($ch, CURLOPT_TIMEOUT, PUGPIG_CURL_TIMEOUT);

  // Use a proxy if required
  // Be aware of Drupal patch needed for proxy settings
  // drupal-7881-406-add-proxy-support-for-http-request.patch

  if (!empty($proxy_server) && (!empty($proxy_port))) {
    curl_setopt($ch, CURLOPT_PROXY, "$proxy_server:$proxy_port");
  }

  $textResult = curl_exec($ch);
  curl_close($ch);

  return json_decode($textResult);

}

global $iTunesErrorCodes;
$iTunesErrorCodes = array(
  21000 => "The App Store could not read the JSON object you provided.",
  21002 => "The data in the receipt-data property was malformed.",
  21003 => "The receipt could not be authenticated.",
  21004 => "The shared secret you provided does not match the shared secret on file for your account.",
  21005 => "The receipt server is not currently available.",
  21006 => "This receipt is valid but the subscription has expired. When this status code is returned to your server, the receipt data is also decoded and returned as part of the response.",
  21007 => "This receipt is a sandbox receipt, but it was sent to the production service for verification.",
  21008 => "This receipt is a production receipt, but it was sent to the sandbox service for verification.",
);

// Validate a receipt against iTunes and return edition credentials
function pugpig_send_itunes_edition_credentials($appStorePassword, $subscriptionPrefix, $allowedSubscriptionArray, $binaryReceipt, $secret, $comments = array(), $proxy_server = '', $proxy_port = '')
{
  global $iTunesErrorCodes;
  $itunesUrl = '';
  $jsonResult = null;
  $jsonReceipt = null;
  $status = -1;
  $exception = '';

  if ($binaryReceipt) {
    $base64Receipt = base64_encode($binaryReceipt);
    $jsonReceipt = wp_json_encode(array('receipt-data' => $base64Receipt, 'password' => $appStorePassword));

    // Always verify your receipt first with the production URL; proceed to
    // verify with the sandbox URL if you receive a 21007 status code.
    // Following this approach ensures that you do not have to switch between
    // URLs while your application is being tested or reviewed in the sandbox
    // or is live in the App Store.

    $itunesUrl = 'https://buy.itunes.apple.com/verifyReceipt';
    $jsonResult = pugpig_validate_receipt_with_itunes($itunesUrl, $jsonReceipt, $proxy_server, $proxy_port);

    if ($jsonResult) {
      $status = $jsonResult->status;
      $comments[] = "BUY: Got status $status.";
      if (array_key_exists($status, $iTunesErrorCodes)) $comments[] = "BUY: " . $iTunesErrorCodes[$status];

      if (isset($jsonResult->exception)) {
         $exception = $jsonResult->exception;
      }
    } else {
      $comments[] = "PUGPIG: Failed to connect to production iTunes. Maybe check your outbound rules.";
    }

    if ($status == 21007) {
      $comments[] = "PUGPIG: Trying the Sandbox validator.";
      $status = -1;
      $exception = '';
      $itunesUrl = 'https://sandbox.itunes.apple.com/verifyReceipt';
      $jsonResult = pugpig_validate_receipt_with_itunes($itunesUrl, $jsonReceipt, $proxy_server, $proxy_port);

      if ($jsonResult) {
        $status = $jsonResult->status;
        $comments[] = "SANDBOX: Got status $status.";
        if (array_key_exists($status, $iTunesErrorCodes)) $comments[] = "SANDBOX: " . $iTunesErrorCodes[$status];
        if (isset($jsonResult->exception)) {
           $exception = $jsonResult->exception;
        }
      } else {
        $comments[] = "PUGPIG: Failed to connect to sandbox iTunes. Maybe it is down.";
      }

    }

  } else {
    $comments[] = "PUGPIG: No receipt data sent.";
  }
  $comments[] = "PUGPIG: Validated using: $itunesUrl";

  if ($status == 0) {
    $receiptData = $jsonResult->receipt;
    $productId = $receiptData->product_id;

    $comments[] = "PUGPIG: Receipt Product ID: $productId";

    $purchaseDate = $receiptData->original_purchase_date;
    $restoreDate = $receiptData->purchase_date;

    $expiresDate = '';

    $comments[] = "PUGPIG: Valid receipt. Purchase date: $purchaseDate, Restore date: $restoreDate";

    if (property_exists($receiptData, 'expires_date')) {
      $expiresDate = $receiptData->expires_date;
    }

    if ($expiresDate) {
      $expiresDate = gmdate('Y-m-d H:i:s \E\t\c/\G\M\T', $expiresDate / 1000);
      $comments[] = "PUGPIG: Valid receipt. Expires date: $expiresDate";
    }
    // If this is an allowed subscription product, use the ID in the query string
    // We either match the prefix, or
    $is_subscription_product = false;
    if (!empty($subscriptionPrefix) && strpos($productId, $subscriptionPrefix) === 0) {
      $is_subscription_product = true;
      $comments[] = "PUGPIG: Subscription found - $productId matches  $subscriptionPrefix";
    }
    if (in_array($productId, $allowedSubscriptionArray)) {
      $is_subscription_product = true;
      $comments[] = "PUGPIG: Subscription found - $productId in supplied array";
    }
    if ($is_subscription_product) {
      $productId = $_GET['productid'];
    } else {
      $comments[] = "PUGPIG: Using product ID from receipt data";
    }

    _pugpig_subs_edition_credentials_response($productId, $secret,
      $entitled = true, 'active', $comments, array(), '', '',array());

  } else {
    $writer = new XMLWriter();
    $writer->openMemory();
    $writer->setIndent(true);
    $writer->setIndentString('  ');
    $writer->startDocument('1.0', 'UTF-8');

    $writer->startElement('error');
    $writer->writeAttribute('status', $status);
    $writer->writeAttribute('exception', $exception);
    $writer->writeAttribute('validationurl', $itunesUrl);
    $writer->writeElement('subs_prefix', $subscriptionPrefix);
    $writer->writeElement('subs_list', implode(",", $allowedSubscriptionArray));
    $writer->endElement();

    foreach ($comments as $comment) $writer->writeComment(" " . $comment . " ");
    $writer->endDocument();

    header('Content-type: text/xml');
    echo esc_html( $writer->outputMemory() );
    exit;

  }

}
