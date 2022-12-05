<?php
/**
 * @file
 * Pugpig Packager and Test Page
 */
?><?php
/*

Licence:
==============================================================================
(c) 2011, Kaldor Holdings Ltd
This module is released under the GNU General Public License.
See COPYRIGHT.txt and LICENSE.txt

 */?><?php

date_default_timezone_set('UTC');
define ('PUGPIG_DATE_FORMAT', 'Y-m-d H:i');
if(!defined('PUGPIG_CURL_TIMEOUT')) define('PUGPIG_CURL_TIMEOUT', 20);

include_once 'pugpig_interface.php';
include_once 'pugpig_http.php';
include_once 'pugpig_manifests.php';
include_once 'pugpig_packager.php';
include_once 'http_build_url/http_build_url.php';

if (!isset($_GET['action'])) {
  $entrypoints = '';
  if (isset($_GET['entrypoints'])) $entrypoints = $_GET['entrypoints'];

  $timestamp = '';
  $testfeed = 'http://example.com/feed/opds/';
  $temproot = sys_get_temp_dir ();
  if (!endsWith($temproot, DIRECTORY_SEPARATOR)) $temproot = $temproot . DIRECTORY_SEPARATOR;
  $temproot = $temproot  . 'pugpig' . DIRECTORY_SEPARATOR;

  pugpig_interface_output_header("Pugpig - Select Action");

?>


<h1>Check End Points</h1>
Use this function to ensure the package OPDS end points are working as expected.
<form method="get">
<input type="hidden" name="action" value="checkendpoints" />
<table border="0">
<tr><td><label for="f">Top Level Feeds</label></td><td> <textarea name="f" rows="4" cols="90" /><?php echo esc_html($entrypoints); ?><?php echo esc_html($testfeed); ?></textarea></td></tr>
<tr><td><label for="t">Timestamp</label></td><td> <input type="text" name="t" value="<?php echo esc_attr($timestamp); ?>" size="120" /></td></tr>
<tr><td><label for="tf">Temp folder</label></td><td> <input type="text" name="tf" value="<?php echo esc_attr($temproot) . 'tmp' . esc_attr(DIRECTORY_SEPARATOR); ?>" size="120" /></td></tr>
<tr><td><label for="debug">Debug Mode</label></td><td> <input type="checkbox" name="debug" /></td></tr>
<tr><td></td><td><input type="submit" value="Test" /></td></tr>
</table>
</form>

<h1>Generate package files</h1>
Use this function to ensure that the ATOM feed for a particular edition is working. This uses the same logic as the PHP packager. If test mode is set, it will not create a package file.

<form method="get">
<input type="hidden" name="action" value="generatepackagefiles" />
<table border="0">
<tr><td><label for="c">Edition ATOM feed</label></td><td> <input type="text" name="c" value="http://example.com/editionfeed/editionid/pugpig_atom_contents.manifest" size="120" /></td></tr>
<tr><td><label for="pbp">Package Base path</label></td><td> <input type="text" name="pbp" value="/" size="120" /></td></tr>
<tr><td><label for="p">URL to generated package</label></td><td> <input type="text" name="p" value="http://example.com/editionfeed/editionid/pugpig_package_list.manifest" size="120" /></td></tr>
<tr><td><label for="conc">Max Concurrent Connections</label></td><td> <input type="text" name="conc" value="3" size="5" /></td></tr>
<tr><td><label for="testmode">Test Mode</label></td><td> <input type="checkbox" name="testmode" checked /></td></tr>
<tr><td><label for="debug">Debug Mode</label></td><td> <input type="checkbox" name="debug" /></td></tr>
<tr><td><label for="image_test_mode">Show All Images</label></td><td> <input type="checkbox" name="image_test_mode"  /></td></tr>
<tr><td><label for="t">Timestamp</label></td><td> <input type="text" name="t" value="<?php echo esc_attr($timestamp); ?>" size="120" /></td></tr>
<tr><td><label for="tf">Temp folder</label></td><td> <input type="text" name="tf" value="<?php echo esc_attr($temproot) . 'tmp' . esc_attr(DIRECTORY_SEPARATOR); ?>" size="120" /></td></tr>
<tr><td><label for="pf">Package folder</label></td><td> <input type="text" name="pf" value="<?php echo esc_attr($temproot) . 'package-' . esc_attr($timestamp) . esc_attr(DIRECTORY_SEPARATOR); ?>" size="120" /></td></tr>
<tr><td><label for="cdn">CDN</label></td><td> <input type="text" name="cdn" value="" size="120" /> (Optional)</td></tr>
<tr><td><label for="urlbase">Package URL base</label></td><td> <input type="text" name="urlbase" value="" size="120" /> (Optional)</td></tr>
<tr><td></td><td><input type="submit" value="Generate" /></td></tr>
</table>
</form>

<?php
} else {

  $action = $_GET['action'];
  $timestamp = (isset($_GET['t']) ? $_GET['t'] : '');

  // Redirect with a timestamp if we didn't get one.
  if ($timestamp == '') {
    $timestamp = time();
    $url = http_build_url(pugpig_self_link(),
      array("query" => "t=$timestamp"),
      HTTP_URL_JOIN_QUERY
    );

    header("Location: " . $url);
    exit();
  }
  $tmp_root = $_GET['tf'];

  if ($action == 'generatepackagefiles') {

    $content_xml_url = $_GET['c'];
    $final_package_url = $_GET['p'];
    $concurrent_connections = $_GET['conc'];
    $relative_path = $_GET['pbp']; // We have to package from the root
    $edition_tag = '';
    $return_manifest_asset_urls = false;
    $save_root = $_GET['pf'];
    $cdn = (isset($_GET['cdn']) ? $_GET['cdn'] : '');
    $package_url_base = (isset($_GET['urlbase']) ? $_GET['urlbase'] : '');

    $debug = false;
    if (isset($_GET['debug'])) {
      $debug = true;
    }

    $test_mode = false;
    if (isset($_GET['testmode'])) {
      $test_mode = true;
    }

    $image_test_mode = false;
    if (isset($_GET['image_test_mode'])) {
      $image_test_mode = true;
    }

    // Get the XML for the package
    $package_xml = _pugpig_package_edition_package(
      $final_package_url, $content_xml_url, $relative_path,
      $debug, $edition_tag, $return_manifest_asset_urls, $timestamp,
      $tmp_root, $save_root, $cdn, $package_url_base, $test_mode, $image_test_mode, $concurrent_connections);
  } elseif ($action == 'checkendpoints') {
    $feeds = $_GET['f'];
    $endpoints = explode("\r\n", $feeds);

    _pugpig_package_test_endpoints($endpoints, $timestamp, $tmp_root);

  }

  print '<br><br>Done.';
}

print_r("<br /><em style='font-size:small'>Packager Version: " . pugpig_get_standalone_version() . " </em><br />");
