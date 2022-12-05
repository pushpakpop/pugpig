<?php
/**
 * @file
 * Pugpig Auth Test Form
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

//$active = patcf_flip_is_active($seconds_left);
//print "SECONDS: $seconds_left<br />";

$title = "Generic Test Stub";
$v["issue_prefix"] = "com.pugpig.edition";
$v["issue_start"] = 1;
$v["issue_end"] = 100;
$base = pugpig_get_current_base_url() .  $_SERVER["SCRIPT_NAME"] . "?" . http_build_query($v);

$urls["sign_in"] = str_replace("test_form", "sign_in", $base);
$urls["verify_subscription"] = str_replace("test_form", "verify_subscription", $base);
$urls["edition_credentials"] = str_replace("test_form", "edition_credentials", $base);
$urls["renew_token"] = str_replace("test_form", "renew_token", $base);
$urls["sign_out"] = str_replace("test_form", "sign_out", $base);
$params = array("username", "password");
$test_users = patcf_get_test_users($all_users);
$helptext = "Users of the form XXX-XXX-XXX will return the status code XXX specified for the three calls in the given order (Sign In, Verify, Edition Creds). 000 means return a 200 with rubbish. ZZZ returns a 200 but takes 60 seconds. Peter is the only user requiring a password.<br/> Issues listed below may be free, empty or broken. Users with names ending in global set global auth creds. Please check your OPDS feed to make sure the issues function correctly.";

pugpig_subs_test_form($title, $urls, $params, $test_users, $helptext);
