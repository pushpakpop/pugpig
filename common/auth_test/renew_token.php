<?php
/**
 * @file
 * Pugpig Auth Test Token Renew
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

$token = "";
if (!empty($_REQUEST["token"])) $token = $_REQUEST["token"];
$orig = $token;

$user = "";
if ($token == $longtoken) $user = "longjson";
else {
  $token = preg_replace('/X/', '', $token); // Remove the X
  $user = strrev($token);
}

$comments = array();

if ($token == "") {
    $token = null;
    $comments[] = "No token supplied";
} elseif (in_array(strrev($token), array("blockedtoken"))) {
    $comments[] = "Your token is no longer valid";
    $token = null;
} elseif (in_array(strrev($token), array("expiredtoken"))) {
    $comments[] = "Your token has been renewed";
    $token = strrev("activeall");
} elseif (in_array(strrev($token), array("alwaysstaletoken"))) {
    $comments[] = "Have another stale token. Sorry.";
    $token = $orig . "X";
}

$secret = null;

if (endsWith($user, "global")) {
  $comments[] = "Using a Global Auth Token";
  $secret = empty($pugpigCredsSecret) ? null : $pugpigCredsSecret;
}

_pugpig_subs_sign_in_response($token, $comments, 'notrecognised', 'Invalid credentials', $secret);
