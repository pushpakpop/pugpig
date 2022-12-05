<?php

// idea for a new single entrypoint for content_test to make it better structured and more easily customised

/**
 * entry.php
 * The entry point for the content_test functionality.
 * The URL path to control functionality is as follows:
 *
 * /editions.xml      - the editions package OPDS feed
 * /editions-atom.xml - the editions atom OPDS feed
 * /newsstand.xml     - the editions newsstand feed
 * /edition/<edition_num>/content.xml
 * /edition/<edition_num>/<page_num>.html
 * /edition/<edition_num>/<content_path>  - content shared between pages
 * /edition/<edition_num>/<page_num>/<content_path>  - content for pagez
 *
 */

if (!defined('CONTENT_TEST_DIR')) {
  define('CONTENT_TEST_DIR', dirname($_SERVER['SCRIPT_FILENAME']));
}

if (!defined('CONTENT_TEST_PATH')) {
  define('CONTENT_TEST_PATH', $_SERVER['SCRIPT_NAME'] . '/../');
}

require_once "content_provider.php";
include_once "../ip_in_range.php";

final class HttpStatusCode
{
  const OK             = 200;
  const BAD_REQUEST    = 400;
  const UNAUTHORIZED   = 401;
  const FORBIDDEN      = 403;
  const INTERNAL_ERROR = 500;
}

$http_status_code_array = array(
  HttpStatusCode::OK             => 'OK',
    HttpStatusCode::BAD_REQUEST    => 'Bad Request',
    HttpStatusCode::UNAUTHORIZED   => 'Unauthorized',
  HttpStatusCode::FORBIDDEN      => 'Forbidden',
    HttpStatusCode::INTERNAL_ERROR => 'Internal Server Error'
);

define('HTTP_STATUS_CODES', maybe_serialize($http_status_code_array));

if (!function_exists('reply_wth_message')) {
  function reply_wth_message($message = null, $http_status=HttpStatusCode::OK)
  {
    // set the http response
    $http_response = $_SERVER['SERVER_PROTOCOL'] . ' ' . $http_status;
    $http_status_codes = maybe_unserialize(HTTP_STATUS_CODES);
    if (array_key_exists($http_status, $http_status_codes)) {
      $http_response .= ' ' . $http_status_codes[$http_status];
    }
    header($http_response);

    // set the message
    if (!empty($message)) {
      print wp_kses_post($message);
    }

    exit();
  }
}

if (!function_exists('reply_unauthorised')) {
  function reply_unauthorised($message = 'You are not authorized to view this page.')
  {
    header('Cache-Control: no-cache');
    //header('WWW-Authenticate: Basic realm="Pugpig"');
    reply_wth_message($message, HttpStatusCode::FORBIDDEN);
  }
}

if (!function_exists('get_opds_comments')) {
  function get_opds_comments()
  {
    return array("Created by test script");
  }
}

if (!function_exists('get_opds_static_entries')) {
  function get_opds_static_entries()
  {
    return '';
  }
}

if (!function_exists('reply_editions_xml')) {
  function reply_editions_xml($atom_mode, $newsstand_mode)
  {
    header('Vary: Authorization');
    header('Content-Type: application/atom+xml; charset=utf-8');
    global $is_package;
    $is_package = !$atom_mode;
    $edition_ids = $atom_mode ? get_all_atom_edition_ids() : get_all_packaged_edition_ids();
    $internal = false;
    $d = pugpig_get_opds_container($edition_ids, $internal, $atom_mode, $newsstand_mode, get_opds_comments());
    $d->formatOutput = true;
    $out = $d->saveXML();
    $static_entries = get_opds_static_entries();
    if (!empty($static_entries)) {
      $out = str_replace('</feed>', $static_entries."\n</feed>", $out);
    }
    print $out; //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    exit();
  }
}

if (!function_exists('reply_editions_package_xml')) {
  function reply_editions_package_xml()
  {
    reply_editions_xml(false, false);
  }
}

if (!function_exists('reply_editions_atom_xml')) {
  function reply_editions_atom_xml()
  {
    reply_editions_xml(true, false);
  }
}

if (!function_exists('reply_editions_newsstand_xml')) {
  function reply_editions_newsstand_xml()
  {
    reply_editions_xml(false, true);
  }
}

if (!function_exists('add_pugpig_headers')) {
  function add_pugpig_headers($ttl_secs, $status)
  {
    if ($ttl_secs) {
      header("Cache-Control: max-age=" . $ttl_secs);
      header('Expires: ' . gmdate('D, d M Y H:i:s', time() + $ttl_secs) . ' GMT');
    }

    header('X-Pugpig-Status: ' .
        ($status == "draft" ? "unpublished" : "published"));
 }
}

if (!function_exists('reply_search_xml')) {
  function reply_search_xml()
  {
    if (empty($_REQUEST['q'])) {
      reply_wth_message('No search query specified.', HttpStatusCode::BAD_REQUEST);
      exit;
    }
    $query = $_REQUEST['q'];
    $edition_id = empty($_REQUEST['edition']) ? null : $_REQUEST['edition'];
    $search_id = get_search_edition_id($query, $edition_id);

    return reply_content_xml($search_id);
  }
}

if (!function_exists('reply_content_xml')) {
  function reply_content_xml($edition_num)
  {
    header('Content-Type: application/atom+xml; charset=utf-8');
    $edition_id = get_edition_id($edition_num);

    $edition = pugpig_get_edition($edition_id);
    add_pugpig_headers($edition['ttl'], $edition['status']);

    $d = pugpig_get_atom_container($edition_id);
    $d->formatOutput = true;
    print $d->saveXML(); //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    exit;
  }
}

if (!function_exists('reply_package_xml')) {
  function reply_package_xml($edition_num)
  {
    $edition_id = get_edition_id($edition_num);
    $now_atom = gmdate(DATE_ATOM, time());
    $now_822 = date(DATE_RFC822);

    $total_size = 0;
    $parts = get_package_parts($edition_num);
    foreach (array_keys($parts) as $part_name) {
      $part = &$parts[$part_name];
      $size = filesize($part['path']);
      $part['size'] = $size;
      $total_size += $size;
    }

    $content = 'content.xml';

    $edition = pugpig_get_edition($edition_id);
    add_pugpig_headers($edition['ttl'], $edition['status']);

    header('Content-Type: application/xml');
    print wp_kses_post("<package root=\"$content\" size=\"$total_size\">\n");
    print wp_kses_post("<!-- Generated: $now_822 -->\n");
    foreach (array_keys($parts) as $part_name) {
      $part = &$parts[$part_name];
      $size = $part['size'];
      $url  = $part['url'];
      print wp_kses_post("  <part name=\"$part_name\" src=\"$url\" size=\"$size\" modified=\"$now_atom\"/>\n");
    }
    print "</package>";
    exit();
  }
}

if (!function_exists('reply_page_html')) {
  function reply_page_html($edition_num, $page_num)
  {
    $url = get_page_url($edition_num, $page_num);
    reply_curl($url);
    exit();
  }
}

if (!function_exists('reply_page_manifest')) {
  function reply_page_manifest($edition_num, $page_num)
  {
    $files = get_page_manifest_files($edition_num, $page_num);
    header('Content-Type: text/cache-manifest');
    print "CACHE MANIFEST\n";
    foreach ($files as $file) {
      print "$file\n"; //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }
    exit;
  }
}

if (!function_exists('get_current_url')) {
  function get_current_url()
  {
   $pageURL = 'http';
   if (!empty($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] == "on") {
     $pageURL .= "s";
   }
   $pageURL .= "://";
   if ($_SERVER["SERVER_PORT"] != "80") {
    $pageURL .= $_SERVER["SERVER_NAME"].":".$_SERVER["SERVER_PORT"].$_SERVER["REQUEST_URI"];
   } else {
    $pageURL .= $_SERVER["SERVER_NAME"].$_SERVER["REQUEST_URI"];
   }

   return $pageURL;
  }
}

if (!function_exists('get_absolute_url')) {
  function get_absolute_url($relative_url, $base)
  {
      /* return if already absolute URL */
      if (wp_parse_url($relative_url, PHP_URL_SCHEME) != '') return $relative_url;

      /* queries and anchors */
      if ($relative_url[0]=='#' || $relative_url[0]=='?') return $base.$relative_url;

      /* parse base URL and convert to local variables:
         $scheme, $host, $path */
      extract(wp_parse_url($base));

      /* remove non-directory element from path */
      $path = preg_replace('#/[^/]*$#', '', $path);

      /* destroy path if relative url points to root */
      if ($relative_url[0] == '/') $path = '';

      /* dirty absolute URL // with port number if exists */
      if (wp_parse_url($base, PHP_URL_PORT) != '') {
          $abs = "$host:".wp_parse_url($base, PHP_URL_PORT)."$path/$relative_url";
      } else {
          $abs = "$host$path/$relative_url";
      }
      /* replace '//' or '/./' or '/foo/../' with '/' */
      $re = array('#(/\.?/)#', '#/(?!\.\.)[^/]+/\.\./#');
      for ($n=1; $n>0; $abs=preg_replace($re, '/', $abs, -1, $n)) {}

      /* absolute URL is ready! */

      return $scheme.'://'.$abs;
  }
}

function reply_curl($relative_url)
{
  $base = get_current_url();
  $absolute_url = get_absolute_url($relative_url, $base);
  $ch = curl_init($absolute_url);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
  curl_setopt($ch, CURLOPT_HEADER, 1);
  if (!empty($_SERVER['PHP_AUTH_USER']) && !empty($_SERVER['PHP_AUTH_PW'])) {
    curl_setopt($ch, CURLOPT_USERPWD, $_SERVER['PHP_AUTH_USER'] . ':' . $_SERVER['PHP_AUTH_PW']);
  }
  if (!empty($_SERVER['HTTP_USER_AGENT'])) {
    curl_setopt($ch, CURLOPT_USERAGENT, $_SERVER['HTTP_USER_AGENT']);
  }
  $response = curl_exec($ch);
  $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
  $header = substr($response, 0, $header_size);
  $body = substr($response, $header_size);
  curl_close($ch);
  if (preg_match('/(Last\-Modified:\s+(.*))/i', $header, $matches)) {
    header($matches[1]);
  }
  if (preg_match('/(Content\-Type:\s+(.*))/i', $header, $matches)) {
    header($matches[1]);
  }
  if (preg_match('/(Content\-Disposition:\s+(.*))/i', $header, $matches)) {
    header($matches[1]);
  }
  print wp_kses_post($body);
}

if (!function_exists('reply_sub_file')) {
  function reply_sub_file($path, $edition_num)
  {
    $url = get_file_url($path);
    global $redirect_for_sub_file;
    if ($redirect_for_sub_file) {
      header('Location: ' . $url);
      exit();
    } else {
      reply_curl($url);
    }
  }
}

if (!function_exists('reply_options')) {
  function reply_options()
  {
    echo "My pleasure";
    exit();
  }
}

if (!function_exists('is_authorised')) {
  function is_authorised($edition_num, $pugpigCredsSecret)
  {
    $edition_id = get_edition_id($edition_num);
    $edition = pugpig_get_edition($edition_id);
    $free_or_draft = ($edition['price'] == 'FREE' || $edition['status'] == 'draft');
    $is_packager_request = array_key_exists('HTTP_USER_AGENT', $_SERVER) && $_SERVER['HTTP_USER_AGENT'] === 'PugpigNetwork/Packager';
    $authorised = $free_or_draft || $is_packager_request;
    if (!$free_or_draft && isset($_SERVER['PHP_AUTH_USER'])
        && isset($_SERVER['PHP_AUTH_PW'])&& isset($pugpigCredsSecret)) {

      $username = $_SERVER['PHP_AUTH_USER'];
      $password = sha1("$edition_id:$username:$pugpigCredsSecret");
      $authorised = $password == $_SERVER['PHP_AUTH_PW'];
    }

    // We can't use the Authorisation Header.
    // Try X-Akamai
    $queryname = "X-Pugpig-Akamai"; // converted by PHP
    $headername = "X_PUGPIG_AKAMAI";

    if (isset($_SERVER["HTTP_" . $headername])) $header = $_SERVER["HTTP_" . $headername];
    elseif (isset($_REQUEST[$queryname])) $header = $_REQUEST[$queryname];

    if (!$authorised && !empty($header)) {
      list($ak_path, $ak_time, $ak_ip) = explode("|",$header);
      if ($ak_ip != getRequestIPAddress()) {
        echo "Akamai Header Token IP address " . esc_html(getRequestIPAddress()) . wp_kses_post(" does not match token $ak_ip<br />\n");
      } elseif ($ak_time < time()) {
        echo "Akamai Header Token expired " . (intval(time()) - $ak_time) . " seconds ago<br />\n";
      } elseif ($ak_path !== $edition_id) {
        echo wp_kses_post("Akamai Header Token not valid for $edition_id (only $ak_path)<br />\n");
      } else {
        $authorised = true;
      }

    }

    return $authorised;
  }
}

if (!function_exists('reply_package_zip')) {
  function reply_package_zip($leaf)
  {
    if (preg_match('/((.*?)-.*\.zip)/', $leaf, $matches)>0) {
      $edition_id = $matches[2];
      $url = '../../'.get_package_path($edition_id).$leaf;
      $base = get_current_url();
      $absolute_url = get_absolute_url($url, $base);
      header('Content-Type: application/octet-stream');
      header("Content-Disposition: attachment; filename=$leaf");
      $ch = curl_init($absolute_url);
      if (!empty($_SERVER['PHP_AUTH_USER']) && !empty($_SERVER['PHP_AUTH_PW'])) {
        curl_setopt($ch, CURLOPT_USERPWD, $_SERVER['PHP_AUTH_USER'] . ':' . $_SERVER['PHP_AUTH_PW']);
      }
      curl_exec($ch);
      curl_close($ch);
    }
  }
}

if (!function_exists('handle_unknown_command')) {
  function handle_unknown_command($command, $request_params)
  {
    reply_wth_message('Unknown command specified : '.$command, HttpStatusCode::BAD_REQUEST);
  }
}

// Required for cross domain.
// See https://code.google.com/p/twitter-api/issues/detail?id=2273
if ($_SERVER['REQUEST_METHOD'] == "OPTIONS") {
  reply_options();
}

// todo: check what we actually need from standalone as may have been set in the custom entry php
//e.g. pugpigCredsSecret
if (!file_exists('../standalone_config.php')) {
  reply_wth_message('<h1>Warning - standalone_config.php not found</h1>'
      . 'In order to use this page, you will need to configure settings in the file: <code>standalone_config.php</code>',
      HttpStatusCode::INTERNAL_ERROR);
}
include_once '../standalone_config.php';

// check that path has been specif
if (!array_key_exists("PATH_INFO", $_SERVER) || empty($_SERVER["PATH_INFO"])) {
  reply_wth_message('No path specified.', HttpStatusCode::BAD_REQUEST);
}

$request_params = explode("/", $_SERVER["PATH_INFO"]);
array_shift($request_params); // skip the first slash

$path_root = array_shift($request_params);

global $is_package;
$is_package = false;

$default_redirect_for_sub_file = false;
global $redirect_for_sub_file;
if (isset($_REQUEST['redirect'])) {
  $redirect_for_sub_file = filter_var($_REQUEST['redirect'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
  if ($redirect_for_sub_file===NULL) {
    $redirect_for_sub_file = $default_redirect_for_sub_file;
  }
  print filter_var($_REQUEST['redirect'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) . wp_kses_post(" = $redirect_for_sub_file\n");
  exit;
} else {
  $redirect_for_sub_file = $default_redirect_for_sub_file;
}

switch ($path_root) {
  case 'editions.xml':
    reply_editions_package_xml();
  break;
  case 'editions-atom.xml':
    reply_editions_atom_xml();
  break;
  case 'newsstand.xml':
    reply_editions_newsstand_xml();
  break;
  case 'search':
    reply_search_xml();
  break;
  case '':
    reply_wth_message('No command specified', HttpStatusCode::BAD_REQUEST);
  break;
  case 'build-packages':
    include_once 'build-packages.php';
    exit;
  break;
  // case 'build-package':
  //   global $build_id;
  //   $edition_num = array_shift($request_params);
  //   $build_id = $edition_num;
  //   include_once 'build-package.php';
  //   exit;
  // break;
  case 'edition':
      $edition_num = array_shift($request_params);
      if (empty($edition_num)) {
        reply_wth_message('No edition number specified', HttpStatusCode::BAD_REQUEST);
      }
      if (!is_authorised($edition_num, $pugpigCredsSecret)) {
        reply_unauthorised();
      }

      $subpath = implode("/", $request_params);
      $file = end($request_params);

      $matches = null;

      if ($file==="content.xml") {
        reply_content_xml($edition_num);
      } elseif ($file==="package.xml") {
        reply_package_xml($edition_num);
      } elseif ((preg_match('/((.*?)-.*\.zip)/', $file, $matches)>0)
          && get_edition_prefix($matches[2], get_edition_prefix())) {
        reply_package_zip($matches[1]);
      } elseif (preg_match('/page\-(\d+).manifest/', $file, $matches)>0) {
        reply_page_manifest($edition_num, intval($matches[1]), $subpath);
      } elseif (preg_match('/page\-(\d+).html/', $file, $matches)>0) {
        reply_page_html($edition_num, intval($matches[1]));
      } elseif (!empty($subpath)) {
        reply_sub_file($subpath, $edition_num);
      } else {
       reply_wth_message('No edition request specified', HttpStatusCode::BAD_REQUEST);
      }
  break;
  default:
    handle_unknown_command($path_root, $request_params);
  break;
}
