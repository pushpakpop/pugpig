<?php
/**
 * @file
 * Pugpig Subscription Test Page
 */
?><?php
/*

Licence:
==============================================================================
(c) 2011, Kaldor Holdings Ltd
This module is released under the GNU General Public License.
See COPYRIGHT.txt and LICENSE.txt

 */?><?php

include_once 'pugpig_utilities.php';
include_once 'pugpig_interface.php';
include_once 'pugpig_subs_test.php';

if (!file_exists('standalone_config.php')) {
	echo "<h2>Warning - standalone_config.php not found</h2>";
	echo "In order to use this page, you will need to configure settings in the file: <code>standalone_config.php</code>";
	exit();
}
include_once 'standalone_config.php';

pugpig_interface_output_header("Pugpig - Subscription Test Page");

if (!isset($helptext)) $helptext = "This is a generic test form which needs to be configured.";
if (!isset($use_http_post)) $use_http_post = false;
if (!isset($auth_test_default_product_id)) $auth_test_default_product_id = 'com.pugpig.edition0100';

 pugpig_subs_test_form($title, $urls, $params, $test_users,
	$helptext , $use_http_post, $auth_test_default_product_id);
