<?php
/**
 * @file
 * Pugpig Generic User Auth Test Functions
 */
?><?php
/*

Licence:
==============================================================================
(c) 2011, Kaldor Holdings Ltd
This module is released under the GNU General Public License.
See COPYRIGHT.txt and LICENSE.txt

 */?><?php

if(!defined('PUGPIG_CURL_TIMEOUT')) define('PUGPIG_CURL_TIMEOUT', 20);

function pugpig_subs_test_form($title, $urls, $params, $test_users, $helptext = "", $use_http_post = false, $default_product_id='com.pugpig.edition0100')
{
  if (!headers_sent()) {
    header('Cache-Control: no-cache, must-revalidate, post-check=0, pre-check=0');
  }
  if (isset($urls["base"])) {
    $urls["sign_in"] = $urls["base"] . "sign_in";
    $urls["verify_subscription"] = $urls["base"] . "verify_subscription";
    $urls["edition_credentials"] = $urls["base"] . "edition_credentials";
    // sign_out needs to be set explicitly to be enabled
  }

  $vals = array();

  $user_format = '';
  foreach ($params as $param) {
    if (!empty($user_format)) {
      $user_format .= ', ';
    }
      $user_format .= '<strong>' . $param . '</strong>';
  }

  $params[] = "product_id";
  foreach ($params as $param) {
    if (isset($_REQUEST[$param])) $vals[$param] = htmlspecialchars($_REQUEST[$param]);
  }

  if (empty($vals['product_id'])) {
    $vals['product_id'] = $default_product_id;
  }
  $product_id = $vals['product_id'];

  $authToken = null;
  $error = '';

  $issues = array();
//phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
echo <<< EOT
    <style>
       form {border: 1px solid grey; padding: 2px; margin: 2px;}
      .pugpig_active { color: green;}
      .pugpig_inactive { color: orange;}
      .pugpig_stale { color: gray;}
      .pugpig_unknown { color: red;}
      .testusers { -webkit-column-count: 4; }
    </style>
EOT;

  echo "<h2>Pugpig Authentication Test Console - " . esc_html($title) . "</h2>\n";

  $host = $_SERVER['HTTP_HOST'];
  if (!pugpig_test_ping($host))
    echo "<p><b><font color='red'>" . esc_html($host) . ": Ping Failed. Maybe you need a local host entry?<br />127.0.0.1 " . esc_html($host) . "</b></p>";

  if (!empty($helptext)) echo "<p><em>" . esc_html( $helptext ) . "</em></p>";

  echo "Supplied test users:\n<ul class='testusers'>\n";

  foreach ($test_users as $test_user) {
    $state = strtolower($test_user['state']);
    unset($test_user['state']);
    $p = array();
    $p["product_id"] = $product_id;

    // We need this to retain the position on WordPress
    if (isset($_REQUEST["page"])) $p["page"] = $_REQUEST["page"];
    $query_params = http_build_query(array_merge($test_user, $p));
    $description = implode(", ", $test_user);
    echo "<li><b class='pugpig_" . esc_attr($state) . "'>". esc_html($description) ."</b> - <a href='?".esc_attr($query_params)."'>Test</a></li>\n";
  }
  echo "</ul>\n";

  echo "<form method='GET'>\n";

  // Need to WordPress settings pages
  if (isset($_REQUEST["page"])) {
    echo "<input type='hidden' name='page' value='" . esc_attr($_REQUEST["page"]) . "' />\n";
  }

  echo "Enter test values:<br />\n";
  foreach ($params as $param) {
    if (isset($vals[$param])) { $val = $vals[$param]; } else $val = '';
    echo esc_html($param) . ": <input id='" . esc_attr($param) . "' name='" . esc_attr($param) . "' type='text' value='" . esc_attr($val) . "' /> \n";
  }
  echo "<br /><input type='submit' />\n";
  echo "</form>\n";
  echo "<small>Note that the authorisation parameters for sign in are : " . esc_html( $user_format ) . ". Make sure your client config matches.</small>\n\n";

  // We will always have product_id. Need at least one more.
  echo "<p>Using <em><a href='".esc_url( $urls["sign_in"] )."'>". esc_html($urls["sign_in"])."</a></em><br />\n";
  echo "Using <em><a href='".esc_url( $urls["verify_subscription"] )."'>". esc_html($urls["verify_subscription"])."</a></em><br />\n";
  echo "Using <em><a href='".esc_url( $urls["edition_credentials"] )."'>". esc_html($urls["edition_credentials"])."</a></em><br />\n";
  if (array_key_exists("renew_token", $urls)) {
    echo "Using <em><a href='".esc_url( $urls["renew_token"] )."'>".esc_html($urls["renew_token"])."</a></em><br />\n";
  }
  if (!empty($urls["sign_out"])) {
    echo "Using <em><a href='".esc_url( $urls["sign_out"] )."'>".esc_html($urls["sign_out"])."</a></em><br />\n";
  }
  echo "</p>";

  if (count($vals) > 1) {
    unset($vals['product_id']);
    $sep = (strpos($urls["sign_in"], "?") ? "&" : "?");

    if ($use_http_post) {
      $sign_in_req = $urls["sign_in"];
      $http_status = pugpig_subs_http_request($sign_in_req, $sign_in_response, $vals);
    } else {
      $sign_in_req = $urls["sign_in"] . $sep . http_build_query($vals);
      $http_status = pugpig_subs_http_request($sign_in_req, $sign_in_response);

    }
    $status = "unknown";

    if ($http_status != 200) {
      echo "<b class='pugpig_unknown'>SIGN IN ERROR: Status ". esc_html($http_status) ."</b><br />\n";
    } else {
      $token = pugpig_subs_get_single_xpath_value("/token", $sign_in_response);

      // Backup format to support the Dovetail response format
      if (empty($token)) {
        $token = pugpig_subs_get_single_xpath_value("/result_response/authToken", $sign_in_response);
      }

      if (empty($token)) {
        echo "Credentials not recognised - did not get a token<br />\n";
      } else {
        echo "Auth Token: <b class='pugpig_active'>" . esc_html( $token ) . "</b>";
        if (array_key_exists("renew_token", $urls)) {
          $query_vars = array("token" => $token);
          $sep = (strpos($urls["renew_token"], "?") ? "&" : "?");
          $renew_url = $urls["renew_token"] . $sep . http_build_query($query_vars);

          echo " [<a href='".esc_url($renew_url)."'>renew</a>]";
        }

        $global_auth_password = pugpig_subs_get_single_xpath_value("/token/@global_auth_password", $sign_in_response);
        if (!empty($global_auth_password)) {
          echo " (global auth password: <b class='pugpig_active'>".esc_html($global_auth_password)."</b>)<br/>";
          echo "Authorization: Basic " . esc_html( base64_encode($token . ":" . $global_auth_password) );
        }

        echo "<br />\n";

        $query_vars = array("token" => $token);
        $verify_subscription_req = $urls["verify_subscription"];

        if ($use_http_post) {
          $http_status = pugpig_subs_http_request($verify_subscription_req, $verify_subscription_response, $query_vars);
        } else {
          $sep = (strpos($verify_subscription_req, "?") ? "&" : "?");
          $verify_subscription_req .= $sep . http_build_query($query_vars);
          $http_status = pugpig_subs_http_request($verify_subscription_req, $verify_subscription_response);
        }

        $query_vars['product_id'] = $product_id;
        $edition_creds_req = $urls["edition_credentials"];

        if ($http_status != 200) {
          echo "<b class='pugpig_unknown'>VERIFY SUBSCRIPTION ERROR: Status ".esc_html($http_status) ."</b><br />\n";
        } else {
          $message = pugpig_subs_get_single_xpath_value("/subscription/@message", $verify_subscription_response);
          $status = pugpig_subs_get_single_xpath_value("/subscription/@state", $verify_subscription_response);
          $issues_exists = pugpug_subs_get_xpath_value("/subscription/issues", $verify_subscription_response);
          $issues = pugpug_subs_get_xpath_value("/subscription/issues/issue", $verify_subscription_response);

            if (empty($status)) {
              echo "Status: <b class='pugpig_unknown'>Got a 200, but did not get back the expected response</b><br />\n";
            } elseif (!in_array($status, array('unknown','active','inactive','stale','suspended'))) {
              echo "Status: <b class='pugpig_unknown'>Did not recognise status '".esc_html( $status ) . "'</b><br />";
            } else {
              echo "Status: <b class='pugpig_".esc_attr($status)."'>".esc_html($status)."</b><br />\n";
              if (!empty($message)) echo "Message: <b class='pugpig_". esc_attr($status). "'>".esc_html($message)."</b><br />\n";
              if ($issues_exists == '' || $issues_exists->length == 0) {
                if (strtolower($status) == "active") {
                  echo "<b>Access based: As an active user, you have access to all issues</b><br />\n";
                } else {
                  echo "<b>Access based: As an inactive user, you get nothing</b><br />\n";
                }
              } elseif ($issues->length == 0) {
                echo "<b>Issue based: You do not have access to any issues</b><br />\n";
              } else {
                echo "<b>Issue based: You have access to " . esc_html($issues->length) . " issues</b><br />\n";
                echo "<ul>\n";
                foreach ($issues as $issue) {
                  echo "<li>" . esc_html($issue->textContent) . "</li>\n";
                }
                echo "</ul>\n";
              }
            }
        }

        if ($use_http_post) {
         $http_status = pugpig_subs_http_request($edition_creds_req, $edition_creds_response, $query_vars);
        } else {
          $sep = (strpos($edition_creds_req, "?") ? "&" : "?");
          $edition_creds_req .= $sep . http_build_query($query_vars);
         $http_status = pugpig_subs_http_request($edition_creds_req, $edition_creds_response);
        }
        if ($http_status != 200) {
          echo "<b class='pugpig_unknown'>EDITION CREDENTIALS ERROR: Status ". esc_html($http_status) ."</b>\n";
        } else {
          $userid = pugpig_subs_get_single_xpath_value("/credentials/userid", $edition_creds_response);
          $password = pugpig_subs_get_single_xpath_value("/credentials/password", $edition_creds_response);
          if (!empty($userid) && !empty($password)) {
           echo "Got credentials for <b class='pugpig_active'>".intval($product_id)."</b><br />\n";
          } else {
           $status = pugpig_subs_get_single_xpath_value("/credentials/error/@status", $edition_creds_response);
           $message = pugpig_subs_get_single_xpath_value("/credentials/error/@message", $edition_creds_response);
           echo "Denied credentials for <b class='pugpig_unknown'>".intval($product_id)."</b> (status: <b class='unknown'>".esc_html($status)."</b>)<br />\n";
           if (!empty($message)) echo "Message: <b class='pugpig_unknown'>".esc_html($message)."</b><br />\n";
          }
        }

        if (!empty($urls["sign_out"])) {
          $query_vars = array("token" => $token);
          $sign_out_req = $urls["sign_out"];

          if ($use_http_post) {
            $http_status = pugpig_subs_http_request($sign_out_req, $sign_out_response, $query_vars);
          } else {
            $sep = (strpos($sign_out_req, "?") ? "&" : "?");
            $sign_out_req .= $sep . http_build_query($query_vars);
            $http_status = pugpig_subs_http_request($sign_out_req, $sign_out_response);
          }

          if ($http_status == 501) {
            echo "<b class='pugpig_unknown'>SIGN OUT: Not implemented</b>\n";
          } else if ($http_status != 200) {
            echo "<b class='pugpig_unknown'>SIGN OUT ERROR: Status ".esc_html($http_status)."</b>\n";
          } else {
            // todo: check response content
            echo "Signed out OK\n";
          }
        }
      }
    }

    echo "<h3 class='pugpig_".esc_attr($status)."'>All done</h3><br />\n";
    if (!empty($sign_in_req)) {
      echo "<a href='".esc_url($sign_in_req)."'>Raw Sign In</a> (HTTP " . ($use_http_post ? "POST" : "GET") . ")<br />\n";
        echo "<hr />" . esc_html(htmlspecialchars ($sign_in_response)) . "<hr />\n";
    }
    if (!empty($verify_subscription_req)) {
      echo "<a href='".esc_url($verify_subscription_req)."'>Verify Subscription</a> (HTTP " . ($use_http_post ? "POST" : "GET") . ")<br />\n";
        echo "<hr />" . esc_html(htmlspecialchars ($verify_subscription_response)) . "<hr />\n";
    }
    if (!empty($edition_creds_req)) {
      echo "<a href='".esc_url($edition_creds_req)."'>Edition Credentials</a> (HTTP " . ($use_http_post ? "POST" : "GET") . ")<br />\n";
        echo "<hr />" . esc_html(htmlspecialchars ($edition_creds_response)) . "<hr />\n";
    }
    if (!empty($sign_out_req)) {
      echo "<a href='".esc_url($sign_out_req)."'>Sign Out</a> (HTTP " . ($use_http_post ? "POST" : "GET") . ")<br />\n";
        echo "<hr />" . esc_html(htmlspecialchars ($sign_out_response)) . "<hr />\n";
    }

  }

  print_r("<br /><em style='font-size:small'>Test Form Version: " . pugpig_get_standalone_version() . " </em><br />");

}

function pugpig_subs_get_test_user_array($params, $test_user_string)
{
  $test_users = array();

  $lines = preg_split('/\n/m', $test_user_string, 0);
  foreach ($lines as $line) if (!empty($line)) {
    $x = explode(",", $line);
    $y = array();
    $state = trim(array_shift($x));
    if (in_array($state, array("ACTIVE", "INACTIVE", "UNKNOWN"))) {
      if (count($x) == count($params)) {
        $y["state"] = $state;
        for ($i = 0; $i < count($params); $i++) {
          $y[$params[$i]] = trim(array_shift($x));
        }
        $test_users[] = $y;
      }
    }
  }

  return $test_users;
}

// Ooops. Committed with wrong name and is being used.
function pugpug_subs_get_single_xpath_value($expr, $body)
{
  $ret = pugpig_subs_get_xpath_value($expr, $body);
  if ($ret == '' || count($ret) == 0) return '';
  $item = $ret->item(0);
  if (!isset($item)) return '';
  $value = $item->textContent;

  return $value;
}

function pugpug_subs_get_xpath_value($expr, $body)
{
  if (empty($body)) return '';
  $xmldoc = new DOMDocument();
  $xmldoc->loadXML($body, LIBXML_NOCDATA | LIBXML_NOWARNING | LIBXML_NOERROR );
  $xpathvar = new DOMXPath($xmldoc);

  return $xpathvar->query($expr);
}

function pugpig_subs_get_single_xpath_value($expr, $body)
{
  return pugpug_subs_get_single_xpath_value($expr, $body);
}

function pugpig_subs_get_xpath_value($expr, $body)
{
  return pugpug_subs_get_xpath_value($expr, $body);
}

function pugpig_subs_http_request($url, &$response, $post_fields = array())
{
  $ret = array();

  $ch = curl_init();
  curl_setopt($ch, CURLOPT_URL, $url);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
  curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
  curl_setopt($ch, CURLOPT_TIMEOUT, PUGPIG_CURL_TIMEOUT);
  curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 PugpigAuthTestForm");

  // Do an HTTP POST (used for sign in)
  if (count($post_fields)) {
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post_fields));
  }

  // Use a proxy if required
  // Be aware of Drupal patch needed for proxy settings
  // drupal-7881-406-add-proxy-support-for-http-request.patch

  $response = curl_exec($ch);

  $http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);

  curl_close($ch);

  return $http_status;
}
