<?php
/**
 * @file
 * Pugpig Auth Test Edition Credentials
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
include_once "../ip_in_range.php";

if (!file_exists('../standalone_config.php')) {
	header("HTTP/1.1 500 Internal Server Error");
	echo "<h2>Warning - standalone_config.php not found</h2>";
	echo "In order to use this page, you will need to configure settings in the file: <code>standalone_config.php</code>";
	exit();
}

include_once "../standalone_config.php";

function pugpig_get_extra_credential_headers($product_id, $user, &$comments)
{
	$comments[] = "Adding extra fake auth headers simulating edge tokens";

	$extra_headers = array();
	$valid_secs = 2 * 60;
	if ($user == strrev('activesome')) $valid_secs = 3;
	$comments[] = "X-Pugpig-Fake-Auth auth token for " . strrev($user) . " valid for $valid_secs secs";
	// Valid for the next 2 mins
    $auth_token = "$product_id|" . (time() + $valid_secs) . "|" . getRequestIPAddress();
	$extra_headers['Cookie'] = "name=FakeAuth&value=$auth_token";
	$extra_headers['X-Pugpig-Fake-Auth'] = $auth_token;
	return $extra_headers;

}

$token = "";
if (!empty($_REQUEST["token"])) $token = $_REQUEST["token"];

$product_id = "";
if (isset($_REQUEST["product_id"])) $product_id = $_REQUEST["product_id"];

if ($token == $longtoken) $user = "longjson";
else {
	$token = preg_replace('/X/', '', $token); // Remove the X
	$user = strrev($token);
}

$state = "";
$comments = array();
$message = '';
$issues = null;

$secret = $pugpigCredsSecret;

if (patcf_is_status_coder($user)) {
	$user = patcf_return_status_code($user, 2);
}

$flip_active = patcf_flip_is_active($seconds);
$deny_issue_based = false;

if ($token == "") {
	$state = "stale";
	$message = "No token!";
	$comments[] = "No token supplied";
} elseif (empty($product_id)) {
	$state = "inactive";
	$message = "No product requested!";
	$comments[] = "No product_id supplied";
} elseif (in_array(strrev($token), array("expiredtoken","blockedtoken","alwaysstaletoken"))) {
	$state = "stale";
	$message = "You need to log in again!";
	$comments[] = "Your token is no longer valid";
} elseif (patcf_is_active($all_users, $user)) {
	$state = "active";
	if (startsWith($user, "flip")) {
		$message = "Your subscription will expire in $seconds seconds.";
	} if (startsWith($user, "yes")) {
		$message = "I've hacked the response to appear active. But I lie.";
	} else {
		$message = "Your subscription is active.";
	}
} else {
	$state = "inactive";
	if (startsWith($user, "flip")) {
		$message = "Your subscription will become active in $seconds seconds.";
		if (!endsWith($user, 'some')) {
			$deny_issue_based = true;
		}
	} else {
		$message = "You do not have an active subscription.";
	}
}

if ($user == "longjson") {
	$state = "active";
	$token = NULL;
} else if ($user == "peter") {
	$state = "active";
} else if (endsWith($user, "all") || endsWith($user, "global")) {
	if ($state == "active") {
		$issues = null;
		$message .= " You should have access to all issues while subscribed.";
	} else {
		$issues = array();
		$message .= " You aren't active. You get nothing.";
	}
} elseif (endsWith($user, "none")) {
	$issues = array();
	$message .= " Sadly you don't have access to any issues anyway.";
} elseif (endsWith($user, "some")) {
	if ($deny_issue_based) {
		$issues = array();
		$message .= " You have lost your issue based access for now.";
	} else {
		$issues = patcf_get_some_issues($all_issues, false);
		$message .= " You have access to every second issue.";
	}
} elseif (endsWith($user, "random")) {
	$issues = patcf_get_some_issues($all_issues, true);
	$message .= " You have access to an ever changing random set. Any download may fail";
} else {
	$issues = array();
	$message .= " We don't know who you are.";
}

if ($issues === NULL || in_array($product_id, $issues)) {
	$entitled = true;
} else {
	$entitled = false;
}

$extra_headers = array();

if ($user == "credserror") {
	$error_message = 'something bad happened';
	
	$writer = _pugpig_subs_start_xml_writer();
	$writer->startElement('credentials');
    $writer->startElement('error');
        if (!empty($error_message)) $writer->writeAttribute('message', $error_message);
    $writer->endElement();
    $writer->endElement();
  	_pugpig_subs_end_xml_writer($writer);
    exit();
}

_pugpig_subs_edition_credentials_response($product_id, $secret, $entitled, $state, $comments, array(), $message, $token, $extra_headers);

