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

// http://phpseclib.sourceforge.net/
// Add the path to where you installed seclib to your php.ini include_path setting
try {
  @include 'Crypt/AES.php';
} catch (Exception $e) {
  // in case of partially installed PHPSecLib, we ensure that the include doesn't bring down the server
}

function _pugpig_bbappworld_encrypt($plaintext, $password)
{
  $cipher = new Crypt_AES(CRYPT_AES_MODE_ECB);
  // keys are null-padded to the closest valid size
  // longer than the longest key and it's truncated
  $cipher->setKey($password);
  $encrypted = $cipher->encrypt($plaintext);
  $base64_encrypted = base64_encode($encrypted);

  return $base64_encrypted;
}

function _pugpig_bbappworld_decrypt($base64_encrypted, $password)
{
  $cipher = new Crypt_AES(CRYPT_AES_MODE_ECB);
  // keys are null-padded to the closest valid size
  // longer than the longest key and it's truncated
  $cipher->setKey($password);

  return $cipher->decrypt(base64_decode($base64_encrypted));
}

function _pugpig_bbappworld_check_license_secret($license_secret)
{
  if (empty($license_secret)) {
    echo 'The secret phrase needed by the authentication process is not yet set. Please update the license secret in the Pugpig settings.';
    exit;
  }
}

function _pugpig_bbappworld_check_crypt()
{
  if (!class_exists('Crypt_AES')) {
    echo 'PHPSecLib is not installed.';
    exit;
  }
}

function _pugpig_bbappworld_checks($license_secret)
{
  _pugpig_bbappworld_check_license_secret($license_secret);
  _pugpig_bbappworld_check_crypt();
}

function pugpig_bbappworld_send_license($license_secret, $request_args)
{
  _pugpig_bbappworld_checks($license_secret);

  $request_args['date'] = gmdate('Y-m-d H:i:s \E\t\c/\G\M\T');

  $serialized_request = wp_json_encode($request_args);
  $encrypted_request = _pugpig_bbappworld_encrypt($serialized_request, $license_secret);

  header('Content-type: application/www-url-encoded');
  print esc_html(http_build_query(array ('key' => $encrypted_request)));
}

function pugpig_send_bbappworld_edition_credentials($license_secret, $subscription_prefix, $allow_sandbox, $pugpig_auth_secret, $product_id, $sku, $license, $receipt)
{
  // the product_id is the id in the opds atom feed e.g. com.kaldorgroup.edition_141
  // the sku is the id in the BlackBerry App World vendor portal for the virtual good, e.g. com_kaldorgroup_edition_141
  //
  _pugpig_bbappworld_checks($license_secret);

  $comments = array();
  $status   = 'failed';
  $error    = '';

  // todo: handle expiry at all here?

  $comments[] = "Checking product id: '$product_id'";
  $comments[] = "With sku: '$sku'";
  $comments[] = "Subscription prefix: '$subscription_prefix'";
  $comments[] = "license: '$license'";
  $comments[] = "Allow Sandbox: '$allow_sandbox'";

  $decrypted_license = _pugpig_bbappworld_decrypt($license, $license_secret);
  if (empty($decrypted_license)) {
    $error='License will not decrypt.';
  } else {
    $comments[] = 'License data: ' . $decrypted_license;
    $data = json_decode($decrypted_license, true);

    if (!$allow_sandbox && strcasecmp($data['test'], 'true')) {
      $comments[] = "License is for the test (sandbox) environment and this isn't allowed";
    } else {
      $comments[] = 'Request is not for sandbox (or sandbox allowed).';

      $license_sku     = $data['sku'];
      $license_product_name = $data['product'];

      // check to see if the purchase was a subscription - either product name or sku can be matched
      $is_subscription_product = false;
      if (!empty($subscription_prefix)) {
        if (strpos($license_product_name, $subscription_prefix) === 0) {
          $is_subscription_product = true;
          $comments[] = "Subscription found - license product name '$license_product_name' matches '$subscription_prefix'";
        } elseif (strpos($license_sku,     $subscription_prefix) === 0) {
          $is_subscription_product = true;
          $comments[] = "Subscription found - license sku '$license_sku' matches '$subscription_prefix'";
        } else {
          $comments[] = "Subscription not matched";
        }
      }

      $product_allowed = false;
      if (!$is_subscription_product) {
        // it wasn't a subscription purchase, so check the specific sku
        $product_allowed = !strcasecmp($license_sku, $sku);
        if ($product_allowed) {
          $comments[] = "License's sku '$license_sku' matches requested sku '$sku'";
        } else {
          $comments[] = "License's sku '$license_sku' does not match requested sku '$sku'";
        }
      }

      if ($is_subscription_product || $product_allowed) {
        $status = 'OK';
      }
    }
  }

  _pugpig_subs_edition_credentials_response($product_id, $pugpig_auth_secret, ('OK'===$status), $status, $comments, array(), $error);
}

function _pugpig_bbappworld_format_request_info($title, $url, $headers_out, $data, $status, $headers_in, $response)
{
  $nice_url         = htmlentities($url);
  $nice_headers_out = htmlentities($headers_out);
  $nice_data        = htmlentities($data);
  $nice_status      = htmlentities($status);
  $nice_headers_in  = htmlentities($headers_in);
  $nice_response    = htmlentities($response);

  return <<<"BLOCK_FORMAT_REQUEST_INFO"
<hr>
<h1>$title</h1>
<h2>Request</h2>
<table border="1">
  <tr><td>URL</td><td><pre>$nice_url</pre></td></tr>
  <tr><td>headers</td><td><pre>$nice_headers_out</pre></td></tr>
  <tr><td>data</td><td><pre>$nice_data</pre></td></tr>
</table>
<h2>Response</h2>
<table border="1">
  <tr><td>HTTP Status</td><td><pre>$nice_status</pre></td></tr>
  <tr><td>headers</td><td><pre>$nice_headers_in</pre></td></tr>
  <tr><td>data</td><td><pre>$nice_response</pre></td></tr>
</table>
BLOCK_FORMAT_REQUEST_INFO;
}

function _pugpig_send_bbappworld_test_curl_put($args)
{
  $data = http_build_query($args['fields']);
  $options = array(
              CURLOPT_URL            => $args['url'],
              CURLOPT_POST           => 1,
              CURLOPT_POSTFIELDS     => $data,
              CURLOPT_RETURNTRANSFER => 1,
              CURLOPT_HEADER         => 1,
              CURLINFO_HEADER_OUT    => 1,
              );

  if (!empty($args['proxy_server']) && (!empty($args['proxy_port']))) {
    $options[CURLOPT_PROXY] = $args['proxy_server'] . ':' . $args['proxy_port'];
  }

  $ch = curl_init();
  curl_setopt_array($ch, $options);
  list($headers_in, $response) = explode("\r\n\r\n", curl_exec($ch), 2);
  $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  $headers_out = curl_getinfo($ch, CURLINFO_HEADER_OUT);
  curl_close($ch);

  print wp_kses_post(_pugpig_bbappworld_format_request_info($args['title'], $args['url'], $headers_out, $data, $status, $headers_in, $response));

  return $response;
}

function pugpig_send_bbappworld_test_setup($entrypoints, $subscription_prefix, $pugpig_auth_secret, $proxy_server, $proxy_port)
{
  $product_id     = 'com.kaldorgroup.mag.issue.418';
  $sku            = 'com_kaldorgroup_mag_issue_418';
  $bad_product_id = 'com.kaldorgroup.mag.issue.400';
  $bad_sku        = 'com_kaldorgroup_mag_issue_400';

  $response = _pugpig_send_bbappworld_test_curl_put(array(
    'title'        => 'Testing Generate Product License',
    'url'          => $entrypoints['generate_license'],
    'fields'       => array(
      'PIN'           => '12341234',
      'email'         => 'customeremail@email.com',
      'product'       => 'Issue 418',
      'sku'           => $sku,
      'version'       => '1.0',
      'transactionid' => '123',
      'test'          => 'false',
    ),
    'proxy_server' => $proxy_server,
    'proxy_port'   => $proxy_port,
    ));

  $result = array();
  parse_str($response, $result);
  if (!array_key_exists('key', $result)) {
    print "<p><strong>Generate license did not return a key, so not testing edition credential check</strong></p>";
    exit;
  }
  $response = _pugpig_send_bbappworld_test_curl_put(array(
    'title'        => 'Testing Edition Credentials Check',
    'url'          => $entrypoints['edition_credentials'].'?sku='.$sku.'&product_id='.$product_id,
    'fields'       => array(
      'license'       => $result['key'],
    ),
    'proxy_server' => $proxy_server,
    'proxy_port'   => $proxy_port,
    ));
  pugpig_send_bbappworld_test_response_password($product_id, $response, $pugpig_auth_secret);

  $response = _pugpig_send_bbappworld_test_curl_put(array(
    'title'        => 'Testing Edition Credentials Check - for unauthorised product - should fail',
    'url'          => $entrypoints['edition_credentials'].'?sku='.$bad_sku.'&product_id='.$bad_product_id,
    'fields'       => array(
      'license'       => $result['key'],
    ),
    'proxy_server' => $proxy_server,
    'proxy_port'   => $proxy_port,
    ));
  pugpig_send_bbappworld_test_response_password_failure($bad_product_id, $response, $pugpig_auth_secret);

  $response = _pugpig_send_bbappworld_test_curl_put(array(
    'title'        => 'Testing Generate Subscription License',
    'url'          => $entrypoints['generate_license'],
    'fields'       => array(
      'PIN'           => '12341234',
      'email'         => 'customeremail@email.com',
      'product'       => $subscription_prefix . '30',
      'sku'           => 'com_kaldorgroup_mag_subscription_30',
      'version'       => '1.0',
      'transactionid' => '123',
      'test'          => 'false',
    ),
    'proxy_server' => $proxy_server,
    'proxy_port'   => $proxy_port,
    ));

  $result = array();
  parse_str($response, $result);
  if (!array_key_exists('key', $result)) {
    print "<p><strong>Generate license did not return a key, so not testing edition credential check</strong></p>";
    exit;
  }
  $response = _pugpig_send_bbappworld_test_curl_put(array(
    'title'        => 'Testing Edition Credentials Check against Subscription',
    'url'          => $entrypoints['edition_credentials'].'?sku='.$sku.'&product_id='.$product_id,
    'fields'       => array(
      'license'       => $result['key'],
    ),
    'proxy_server' => $proxy_server,
    'proxy_port'   => $proxy_port,
    ));
  pugpig_send_bbappworld_test_response_password($product_id, $response, $pugpig_auth_secret);

  exit;
}

function pugpig_send_bbappworld_test_response_password_failure($product_id, $response, $pugpig_auth_secret)
{
  $xml = new SimpleXMLElement($response);
  $response_users      = $xml->xpath('/credentials/userid');
  $response_passwords  = $xml->xpath('/credentials/password');
  $response_productids = $xml->xpath('/credentials/productid');
  $response_subscriptions = $xml->xpath('/credentials/subscription/@state');
  $response_subscription = $response_subscriptions[0];

  if (count($response_users)>0
    || count($response_passwords)>0
    || count($response_productids)>0
    || $response_subscription=='OK') {
    print "<p><strong>Data in response that shouldn't be.</strong></p>";
  } else {
    print "<p>Response valid - ". esc_html($response_subscription) .".</p>";
  }
}

function pugpig_send_bbappworld_test_response_password($product_id, $response, $pugpig_auth_secret)
{
  $xml = new SimpleXMLElement($response);
  $response_users      = $xml->xpath('/credentials/userid');
  $response_user       = $response_users[0];
  $response_passwords  = $xml->xpath('/credentials/password');
  $response_password   = $response_passwords[0];
  $response_productids = $xml->xpath('/credentials/productid');
  $response_productid  = $response_productids[0];

  if ($product_id==$response_productid) {
    print "<p>Product ID in response (" . esc_html( $response_productid ) . ") is expected value (" . intval($product_id) . ")</p>";
  } else {
    print "<p><strong>Product ID in response (".intval($response_productid)." ) is not expected value (".intval($product_id).")</strong></p>";
  }

  $calc_password=sha1("$response_productid:$response_user:$pugpig_auth_secret");
  $correct = $calc_password==$response_password;
  if ($correct) {
    print "<p>Response username, password and product id do match</p>";
  } else {
    print "<p><strong>Response username, password and product id do not match</strong></p>";
  }

  return $correct;
}

function pugpig_bbappworld_check_setup()
{
  $status  = 'OK';
  $message = '';

  if (!class_exists('Crypt_AES')) {
    $message = 'PHPSecLib (AES) is not in the PHP include path.  Pugpig BlackBerry App World cannot be used until it is.';
    $status  = 'error';
  }

  return array(
    'status'  => $status,
    'message' => $message
    );
}
