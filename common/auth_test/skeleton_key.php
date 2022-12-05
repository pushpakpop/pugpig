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

if (!file_exists('../standalone_config.php')) {
	header("HTTP/1.1 500 Internal Server Error");
	echo "<h2>Warning - standalone_config.php not found</h2>";
	echo "In order to use this page, you will need to configure settings in the file: <code>standalone_config.php</code>";
	exit();
}

include_once "../standalone_config.php";

if (empty($_REQUEST["product_id"])) {
	header("HTTP/1.1 401 Unauthorized");
	echo "Please include a product_id on the query string";
	exit();
}
$product_id = $_REQUEST["product_id"];

$state = "active";
$comments = array();
$comments[] = "Used skeleton key.";

$entitled = true;
_pugpig_subs_edition_credentials_response($product_id, $pugpigCredsSecret, $entitled, $state, $comments);
