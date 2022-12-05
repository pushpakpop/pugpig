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

$token = "";
if (!empty($_REQUEST["token"])) $token = $_REQUEST["token"];

if ($token == $longtoken) $user = "longjson";
else {
	$token = preg_replace('/X/', '', $token); // Remove the X
	$user = strrev($token);
}

$state = "";
$comments = array();
$message = '';
$issues = null;

if (patcf_is_status_coder($user)) {
	$user = patcf_return_status_code($user, 1);
}

$flip_active = patcf_flip_is_active($seconds);
$deny_issue_based = false;

if ($token == "") {
	$state = "stale";
	$message = "No token!";
	$comments[] = "No token supplied";
} elseif (in_array(strrev($token), array("expiredtoken","blockedtoken","alwaysstaletoken"))) {
	$state = "stale";
	$message = "You need to log in again!";
	$comments[] = "Your token is no longer valid";
} elseif (patcf_is_active($all_users, $user)) {
	$state = "active";
	if (startsWith($user, "flip")) {
		$message = "Your subscription will expire in $seconds seconds.";
	} elseif (startsWith($user, "yes")) {
		$message = "I've hacked the response to appear active. But I lie.";
	} else {
		$message = "Your subscription is active.";
	}
} elseif (strrev($token) == 'peter'){
	$state = "active";
	$message = "Your subscription is active.";
} else {
	$state = "inactive";
	if (startsWith($user, "flip")) {
		$message = "Your subscription will become active in $seconds seconds.";
		if (!endsWith($user, "some")) {
			$deny_issue_based = true;  //Flipsome changed to issue based
        }
	} else {
		$message = "You do not have an active subscription.";
	}
}

if (endsWith($user, "all")) {
	$issues = null;
	if ($state == "active") {
		$message .= " You should have access to all issues while subscribed.";
	} else {
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
}

$userinfo = array('categories' => array(
    "http://schema.pugpig.com/custom_analytics/username#15" => $user));

_pugpig_subs_verify_subscription_response($state, $comments, $message, $issues, $userinfo);
