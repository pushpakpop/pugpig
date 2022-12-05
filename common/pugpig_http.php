<?php
/**
 * @file
 * Pugpig HTTP functions
 */
?><?php
/*

Licence:
==============================================================================
(c) 2011, Kaldor Holdings Ltd
This module is released under the GNU General Public License.
See COPYRIGHT.txt and LICENSE.txt

 */?><?php

/************************************************************************
HTTP Error codes
************************************************************************/
 function _http_not_found()
 {
   header('HTTP/1.0 404 Not Found');
   echo "This page does not exist.";
   exit;
 }

function _http_forbidden()
{
  header('HTTP/1.0 403 Forbidden');
  echo 'Access denied.';
  exit;
}

function _http_unauthorised($msg = '')
{
  header('WWW-Authenticate: Basic realm="Pugpig secure content"');
  header('HTTP/1.0 401 Unauthorized');
  echo "You must enter a valid login ID and password to access this resource.\n" . esc_html($msg);
  exit;
}

/************************************************************************
Download a file and return or save it
This should be avoided where possible
************************************************************************/
function curl_download($Url, $returnMode = 'stdout', $attempts = 0)
{
  $attempts++;

  // is cURL installed yet?
  if (!function_exists('curl_init')) {
    die('Sorry cURL is not installed!');
  }

  if ($returnMode == NULL)
    $returnMode = 'string';

  $fileHandle = null;
  if ($returnMode != 'stdout' && $returnMode != 'string') {
    $dir = dirname($returnMode);
    if (!file_exists($dir))
      mkdir($dir, 0777, true);
    if (file_exists($returnMode) && $attempts == 1)
      return '.<!--Exists - ' . $Url . ' => ' . $returnMode . '-->';
    else
      $fileHandle = fopen($returnMode, 'w');
  }

  $ch = curl_init();

  // Now set some options (most are optional)

  // Set URL to download
  curl_setopt($ch, CURLOPT_URL, $Url);

  // Set a referer
  // curl_setopt($ch, CURLOPT_REFERER, "http://www.example.org/yay.htm");

  // User agent
  curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 PugpigNetwork");
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_BINARYTRANSFER, true);
  curl_setopt($ch, CURLOPT_TIMEOUT, PUGPIG_CURL_TIMEOUT);
  if ($returnMode != 'stdout' && $returnMode != 'string') {
    curl_setopt($ch, CURLOPT_FILE, $fileHandle);
    curl_exec($ch);

    $error = curl_error($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    fclose($fileHandle);

    if ($error != '') {
      if ($attempts <= 5) {
        _fill_buffer();
        _print_immediately('<!-- ' . $error . ' retrying...->');

        return curl_download($Url, $returnMode, $attempts);
      } else {
        unlink($returnMode);

        return 'CURL ERROR: ' . $error . ' (' . $Url . ') [attempts: ' . $attempts . ']';
      }
    } elseif ($http_code >= 400) {
      unlink($returnMode);

      return 'CURL HTTP ERROR ' . $http_code . ' (' . $Url . ')';
    } else

      return ($attempts == 1 ? '*' : $attempts) . '<!--OK - ' . $Url . ' => ' . $returnMode . '-->';
  }
  curl_setopt($ch, CURLOPT_HEADER, true);
  $output = curl_exec($ch);

  $error = curl_error($ch);
  $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);

  if ($error != '') {
    if ($attempts < 5)
      return curl_download($Url, $returnMode, $attempts);
    else
      return 'CURL ERROR: ' . $error . ' (' . $Url . ')';
  } elseif ($http_code >= 400)

    return 'CURL HTTP ERROR ' . $http_code . ' (' . $Url . ') [attempts: ' . $attempts . ']';

  $headerend = strpos($output, "\r\n\r\n");

  if ($headerend === false) {
  } else {
    $headers = explode("\r\n", substr($output, 0, $headerend));
    $output = substr($output, $headerend+4);
  }

  if ($returnMode == 'stdout') {
    //header_remove();
    ob_end_clean();
    ob_start();

    if (isset($headers)) {
      foreach ($headers as $h) {
        if (strcasecmp(str_replace(' ','',$h), 'transfer-encoding:chunked') == 0) continue;
        header($h);
      }
    }
    echo wp_kses_post($output);
    exit;
  } elseif ($returnMode == 'string') {
    return $output;
  } else {
    return 'Unknown return method in curl_download: ' . $returnMode;
  }
}

/************************************************************************
Download a file and return or save it
************************************************************************/
function curl_post($url, $headers, $body)
{
  //extract data from the post
  extract($_POST);

  //set POST variables
  /*
  $url = 'http://domain.com/get-post.php';
  $fields = array(
              'lname'=>urlencode($last_name),
              'fname'=>urlencode($first_name),
              'title'=>urlencode($title),
              'company'=>urlencode($institution),
              'age'=>urlencode($age),
              'email'=>urlencode($email),
              'phone'=>urlencode($phone)
            );
  $headers = array("Host: ".$urldata['host']);
   */

  $fields = array();
  if (is_array($body))
    $fields = $body;

  //url-ify the data for the POST
  $fields_string = '';
  foreach ($fields as $key=>$value) { $fields_string .= $key.'='.$value.'&amp;'; }
  rtrim($fields_string,'&amp;');

  //open connection
  $ch = curl_init();

  //set the url, number of POST vars, POST data
  curl_setopt($ch, CURLOPT_URL, $url);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_BINARYTRANSFER, true);
  if (!is_array($body)) {
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
  } else {
    curl_setopt($ch, CURLOPT_POST, count($fields));
    curl_setopt($ch, CURLOPT_POSTFIELDS, $fields_string);
  }
  curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
  curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

  curl_setopt($ch, CURLOPT_TIMEOUT, PUGPIG_CURL_TIMEOUT);

  //execute post
  $result = curl_exec($ch);
  $error = curl_error($ch);
  if ($error != '')
    $result = 'CURL ERROR: ' . $error;

  //close connection
  curl_close($ch);

  return $result;
}
