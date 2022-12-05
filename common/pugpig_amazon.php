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

function _amazon_build_endpoint_url($cmd, $user_id, $token, $url, $amazonSecret, $amazon_rvs = '2.0')
{
  if (!isset($url) || $url == '') {
    header('HTTP/1.1 500 Internal Server Error');
    echo 'The Amazon settings for URL is not set.';
    exit;
  }

  if (!isset($amazonSecret) || $amazonSecret == '') {
    header('HTTP/1.1 500 Internal Server Error');
    echo 'The Amazon settings for shared secret is not set.';
    exit;
  }

  $url = trim($url);
  if (substr($url, -1) !== '/') $url = $url . '/';

  if ($amazon_rvs == '1.0'){
    $url = $url . 'version/1.0/' . $cmd . 'ReceiptId/developer/' . urlencode($amazonSecret) . '/user/' . urlencode($user_id) . '/receiptId/' . urlencode($token);
  } else {
    $url = $url . 'version/2.0/' . $cmd . '/developer/' . urlencode($amazonSecret) . '/user/' . urlencode($user_id) . '/purchaseToken/' . urlencode($token);
  }
  return $url;
}

/**
 * Send a GET requst using cURL
 * @param string $url to request
 * @param array $get values to send
 * @param array $options for cURL
 * @return string
 */
function curl_get($url, $proxy_server='', $proxy_port='', array $get = null, array $options = array())
{
    $request_url = $url . (empty($get) ? '' : (strpos($url, '?') === FALSE ? '?' : ''). http_build_query($get));
    $defaults = array(
        CURLOPT_URL => $request_url,
        CURLOPT_HEADER => 0,
        CURLOPT_RETURNTRANSFER => TRUE,
        CURLOPT_TIMEOUT => PUGPIG_CURL_TIMEOUT,
        CURLOPT_SSL_VERIFYPEER => false
    );

    if (!empty($proxy_server) && (!empty($proxy_port))) {
      $defaults[CURLOPT_PROXY] = "$proxy_server:$proxy_port";
    }

    $error = '';
    $ch = curl_init();
    curl_setopt_array($ch, ($options + $defaults));
    if ( ! $result = curl_exec($ch)) {
        $error = curl_error($ch);
    }

    $info = curl_getinfo($ch);

    curl_close($ch);

    return array('info'=>$info, 'data'=>$result, 'error'=>$error);
}

function curl_get_json($url, $proxy_server='', $proxy_port='', array $get = null, array $options = array())
{
  $options[CURLOPT_HTTPHEADER] = array('Content-Type: application/json');
  $result = curl_get($url, $proxy_server, $proxy_port, $get, $options);
  if ($result['data']!=false && $result['info']['http_code']==200) {
    $result['json'] = json_decode($result['data']);
  }

  return $result;
}

function _amazon_renew_token($user_id, $product_sku, $subs_sku, $token, $base_url, $amazonSecret, $proxy_server='', $proxy_port='', $amazon_rvs = '2.0')
{
  $url = _amazon_build_endpoint_url('renew', $user_id, $token, $base_url, $amazonSecret, $amazon_rvs);

  $result = curl_get_json($url, $proxy_server, $proxy_port);
  if (array_key_exists('json', $result)) {
    if (!empty($result['json']->receiptId)){
      return $result['json']->receiptId;
    }
    return $result['json']->purchaseToken;
  }

  return '';
}

function _amazon_verify_token($user_id, $product_sku, $subs_sku, $token, $base_url, $amazonSecret, $proxy_server='', $proxy_port='', $amazon_rvs = '2.0')
{
  // see https://developer.amazon.com/sdk/in-app-purchasing/documentation/rvs.html
  $comments = array();

  $url = _amazon_build_endpoint_url('verify', $user_id, $token, $base_url, $amazonSecret, $amazon_rvs);
  
  $comments[] = 'RVS: ' . $amazon_rvs;
  $comments[] = "Base URL: $base_url";

  $status = 'UNKNOWN';

  $result = curl_get_json($url, $proxy_server, $proxy_port);
  $http_code  = $result['info']['http_code'];
  $comments[] = "HTTP code: $http_code";
  $comments[] = 'HTTP error: '.$result['error'];

  switch ($http_code) {
    case 400:
      $status = 'SUBSCRIPTIONEXPIRED';
      break;
    case 498:
      $status = 'SUBSCRIPTIONEXPIRED';
      if (isset($token) && strlen($token) > 0) {
        $comments[] = "Error: Invalid token.";
      } else {
        $comments[] = "Error: No token.";
      }
      break;
    case 499:
      $token = _amazon_renew_token($user_id, $product_sku, $subs_sku, $token, $base_url, $amazonSecret, $amazon_rvs);
      if (isset($token) && strlen($token) > 0) {
        return _amazon_verify_token($user_id, $product_sku, $subs_sku, $token, $base_url, $amazonSecret, $amazon_rvs);
      }
      break;
    case 200:
      $status = 'SUBSCRIPTIONEXPIRED';
      $response_json = $result['json'];
      if (empty($response_json)) {
        $comments[] = "Error: Did not receive valid JSON from the response.";
      } else if ($amazon_rvs == '1.0') {    //IAP v2 is RVS v1
        $productType = $response_json->productType;
        $productId = $response_json->productId;
        if ($productType == 'ENTITLED' && $productId == $product_sku) {
          $status = 'OK';
        } elseif ($productType == 'SUBSCRIPTION' && $productId == $subs_sku) {
          $cancelDate = $response_json->cancelDate;
          if (!isset($cancelDate) || $cancelDate == null || $cancelDate == '')
            $status = 'OK';
        }
      } else { //IAP v1 is RVS v2
        if (!empty($response_json->itemType)){
          $itemType = $response_json->itemType;
          $sku = $response_json->sku;
          if ($itemType == 'ENTITLED' && $sku == $product_sku) {
            $status = 'OK';
          } elseif ($itemType == 'SUBSCRIPTION' && $sku == $subs_sku) {
            $endDate = $response_json->endDate;
            if (!isset($endDate) || $endDate == null || $endDate == '')
              $status = 'OK';
          }
        }
      }
      break;  
  }

  return array('status'=>$status, 'comments'=>$comments);
}

function pugpig_send_amazon_edition_credentials($user_id, $product_sku, $subs_sku, $token, $base_url, $amazon_secret, $pugpig_secret, $proxy_server='', $proxy_port='', $amazon_rvs = '2.0')
{
  $result = _amazon_verify_token($user_id, $product_sku, $subs_sku, $token, $base_url, $amazon_secret, $proxy_server, $proxy_port, $amazon_rvs);
  $status   = $result['status'];
  $comments = $result['comments'];
  _pugpig_subs_edition_credentials_response($product_sku, $pugpig_secret, $status=='OK', $status, $comments);
}
