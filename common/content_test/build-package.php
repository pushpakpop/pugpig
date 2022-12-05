<?php

include_once '../pugpig_http.php';
include_once '../pugpig_manifests.php';
include_once '../pugpig_packager.php';
include_once '../http_build_url/http_build_url.php';

class UnregisterableCallback
{
    // Store the Callback for Later
    private $callback;

    // Check if the argument is callable, if so store it
    public function __construct($callback)
    {
        if (is_callable($callback)) {
            $this->callback = $callback;
        } else {
            throw new InvalidArgumentException("Not a Callback");
        }
    }

    // Check if the argument has been unregistered, if not call it
    public function call()
    {
        if ($this->callback == false) {
            return false;
        }
        $callback = $this->callback;
        $callback(); // weird PHP bug
    }

    // Unregister the callback
    public function unregister()
    {
        $this->callback = false;
    }
}

function deleteDirectory($dir)
{
    if (!file_exists($dir)) {
        return true;
    }
    if (!is_dir($dir)) {
        return unlink($dir);
    }
    foreach (scandir($dir) as $item) {
        if ($item == '.' || $item == '..') {
            continue;
        }
        if (!deleteDirectory($dir.DIRECTORY_SEPARATOR.$item)) {
            return false;
        }
    }

    return rmdir($dir);
}

function shutdown()
{
    global $tmp_root;
    deleteDirectory($tmp_root);
    print "\nBUILD_FAIL";
}

$edition_id = $_GET['edition_id'];
$save_root  = $_GET['save_root'];
if (substr($save_root, -1)!=='/') {
    $save_root .= '/';
}
$content_xml_url = $_GET['atom'];
$package_url_base = '';//$_GET['package_url_base'];

date_default_timezone_set('UTC');
define ('PUGPIG_DATE_FORMAT', 'Y-m-d H:i');
if (!defined('PUGPIG_CURL_TIMEOUT')) {
  define('PUGPIG_CURL_TIMEOUT', 20);
}

global $tmp_root;
$tmp_root = sys_get_temp_dir();
if (!endsWith($tmp_root, DIRECTORY_SEPARATOR)) {
  $tmp_root = $tmp_root . DIRECTORY_SEPARATOR;
}
$tmp_root = $tmp_root  . 'pugpig' . DIRECTORY_SEPARATOR . time() . DIRECTORY_SEPARATOR;

$final_package_url      = ''; # Edition Package feed
$relative_path          = $_GET['relative_path'];
$debug                  = false; # Debug Mode
$edition_tag            = '';
$return_manifest_asset_urls = false;
$timestamp              = 0;
$cdn                    = ''; # CDN
$test_mode              = false; # Test Mode
$image_test_mode        = false; # Show All Images
$concurrent_connections = 3; # Max Concurrent Connections

$callback = new UnregisterableCallback("shutdown");
register_shutdown_function(array($callback, "call"));

$package_xml = _pugpig_package_edition_package(
  $final_package_url, $content_xml_url, $relative_path,
  $debug, $edition_tag, $return_manifest_asset_urls, $timestamp,
  $tmp_root, $save_root, $cdn, $package_url_base, $test_mode, $image_test_mode, $concurrent_connections);

$callback->unregister();
deleteDirectory($tmp_root);

print "\nBUILD_OK";
