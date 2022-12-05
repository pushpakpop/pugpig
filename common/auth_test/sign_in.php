<?php
/**
 * @file
 * Pugpig Auth Test Sign In
 */
?><?php
/*

Licence:
==============================================================================
(c) 2011, Kaldor Holdings Ltd
This module is released under the GNU General Public License.
See COPYRIGHT.txt and LICENSE.txt

 */?><?php

include_once "auth_test_inc.php";

if (!file_exists('../standalone_config.php')) {
    header("HTTP/1.1 500 Internal Server Error");
    echo "<h2>Warning - standalone_config.php not found</h2>";
    echo "In order to use this page, you will need to configure settings in the file: <code>standalone_config.php</code>";
    exit();
}

include_once "../standalone_config.php";

// Take the first param that isn't one of the special set
$params = $_REQUEST;
unset($params["product_id"]);
unset($params["issue_prefix"]);
unset($params["issue_start"]);
unset($params["issue_end"]);

$test_users = patcf_get_test_users($all_users);

// Making Params case insensitive
$params['password'] = !empty($params['password']) ? strtolower($params['password']) : '';
if (empty($params['username'])) {
    echo 'Please supply a username';
    http_response_code(400);
    exit;
}
$params['username'] = strtolower($params['username']);
$cred = $params['username'];

$comments = array();

if (patcf_is_status_coder($cred)) {
    patcf_return_status_code($cred, 0);
    $comments[] = "User follows special pattern";
    $token = strrev($cred);
} elseif ($cred == "expiredtoken") {
    $comments[] = "This token has expired. Renew it.";
    $token = strrev($cred);
} elseif ($cred == "alwaysstaletoken") {
    $comments[] = "This token has expired, and the renew one will also be stale";
    $token = strrev($cred);
} elseif ($cred == "blockedtoken") {
    $comments[] = "This token has been cancelled. It won't renew";
    $token = strrev($cred);
} elseif ($cred == "longjson") {
    $comments[] = "Very long JSON token";
    $token = $longtoken;
} elseif (in_array($cred, $all_users)) {
    $comments[] = "User is in the predefined list";
    $token = strrev($cred);
} elseif ($cred == "peter") {
    if (!empty($params['password'])) {
        if (patcf_is_valid_password($params, $test_users)) {
            $comments[] = "Password is valid";
            $token = strrev($cred);
        } else {
            $comments[] = "Password is invalid.";
            $token = null;
        }
    } else {
        $comments[] = "Password is not set.";
        $token = null;
    }
} else {
    $comments[] = "User not in the predefined list";
    $token = null;
}

// Set global auth creds for the global users
$secret = null;

if (endsWith($params['username'], "global")) {
    $comments[] = "Using a Global Auth Token";
    $secret = empty($pugpigCredsSecret) ? null : $pugpigCredsSecret;
}
_pugpig_subs_sign_in_response($token, $comments, 'notrecognised', 'Invalid credentials', $secret);
