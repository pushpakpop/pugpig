<?php
/**
 * @file
 * Pugpig rewrite rules for WordPress
 */
?><?php
/*

Licence:
==============================================================================
(c) 2011, Kaldor Holdings Ltd
This module is released under the GNU General Public License.
See COPYRIGHT.txt and LICENSE.txt

 */?><?php

// We add this to the end of the HTML files so we can save them
// We use query string name if we have draft posts or no peramalinks
define( 'PUGPIG_HTML_FILE_NAME', 'pugpig_index.html');
define( 'PUGPIG_HTML_MANIFEST_NAME', 'pugpig.manifest'); // WP won't allow dots: index.manifest (Boo!)
define( 'PUGPIG_EDITION_PACKAGE_FILE_NAME', 'pugpig_package_list.manifest');
define( 'PUGPIG_ATOM_FILE_NAME', 'pugpig_atom_contents.manifest');

// This encodes URLs as wordpress doesn't!
// See: https://core.trac.wordpress.org/ticket/14148
add_filter('wp_get_attachment_url', 'pugpig_escape_attachement_urls');
function pugpig_escape_attachement_urls($url)
{
  $parts = explode("/", $url);
  $filename = array_pop($parts);
  $esc = urlencode($filename);

  return str_replace($filename, $esc, $url);
}

add_filter('pugpig_get_inline_image_url', 'pugpig_get_inline_image_urls');
function pugpig_get_inline_image_urls($url)
{
  $parts = explode("/", $url);
  $filename = array_pop($parts);
  $esc = urlencode(urldecode($filename));

  return str_replace($filename, $esc, $url);
}

add_action('wp', 'pugpig_set_custom_post_headers' );

function pugpig_set_cache_headers($modified_time, $expires)
{
    $last_modified = gmdate('D, d M Y H:i:s', $modified_time) . ' GMT';
    $etag = '"' . md5($last_modified) . '"';

    header("Cache-Control: max-age=" . $expires);
    header('Expires: ' . gmdate('D, d M Y H:i:s', time()+$expires) . ' GMT');

    header("Last-Modified: " . $last_modified);
    header("ETag: " . $etag);
    header("X-Pugpig-LM-Ago: " . _ago($modified_time) . "ago");
}

function pugpig_set_custom_post_headers()
{
  pugpig_remove_wordpress_headers();

  global $post;
  if (isset($post) && is_single()) {
    pugpig_set_cache_headers(pugpig_get_page_modified($post), pugpig_get_content_ttl());
  }

  // Set the headers for upstream auth
  $x_entitlement = pugpig_get_post_entitlement_header($post);
  if (!empty($x_entitlement)) header('X-Pugpig-Entitlement: ' . $x_entitlement);

}

/************************************************************************
Check if this is a Pugpig HTML page
*************************************************************************/
function pugpig_is_pugpig_endpoint($endpoint)
{
  if (strpos($_SERVER["REQUEST_URI"], $endpoint)) return TRUE;
  if (isset($_REQUEST[str_replace(".","_", $endpoint)])) return TRUE;
  return FALSE;
}

function pugpig_rewrite_endpoint_url($url, $endpoint)
{
  if (endsWith($url, "/")) {
    $url .= $endpoint;
  } elseif (strpos($url, "?")) {
    // No permalinks so packager won't work
    $url .= "&" . str_replace(".","_", $endpoint) . "=true";
  } else {
    $url .= "/" . $endpoint;
  }

  return $url;
}

function pugpig_is_pugpig_url()
{
  return pugpig_is_pugpig_endpoint(PUGPIG_HTML_FILE_NAME);
}

function pugpig_is_pugpig_manifest()
{
  return pugpig_is_pugpig_endpoint(PUGPIG_HTML_MANIFEST_NAME);
}

function pugpig_is_pugpig_package_xml()
{
  return pugpig_is_pugpig_endpoint(PUGPIG_EDITION_PACKAGE_FILE_NAME);
}

function pugpig_is_pugpig_edition_atom_xml()
{
  return pugpig_is_pugpig_endpoint(PUGPIG_ATOM_FILE_NAME);
}

function pugpig_rewrite_html_url($url)
{
 return pugpig_rewrite_endpoint_url($url, PUGPIG_HTML_FILE_NAME);
}

/*
function pugpig_rewrite_atom_xml($url)
{
 return pugpig_rewrite_endpoint_url($url, PUGPIG_ATOM_FILE_NAME);
}
*/
function pugpig_get_html_url($post, $edition_id = "")
{
  // Ad bundles should return the path to the actual bundle HTML file - not the slug URL which then results in a redirect
  // (and a different relative URL to the ad bundle contents)

    $atom_url = pugpig_permalink(pugpig_get_permalink($post));
    $return_url =  pugpig_rewrite_html_url($atom_url);

    $return_url = apply_filters('pugpig_get_content_html_url', $return_url, $post, $edition_id);

    return $return_url;

/*
  // TODO: MOVE THESE INTO A HOOK TO BE IMPLEMENTED BY AD BUNDLE AND CUSTOM
  if (isset($post) && $post->post_type == PUGPIG_AD_BUNDLE_POST_TYPE) {
    return pugpig_ad_bundle_url($post);
  } elseif (isset($post) && $post->post_type == 'section_index') {
      $atom_url = pugpig_permalink(get_permalink($post));

    return pugpig_rewrite_html_url($atom_url) . "/" . $edition_id;
  } else {
    $atom_url = pugpig_permalink(get_permalink($post));

    return pugpig_rewrite_html_url($atom_url);
  }
*/
}

function pugpig_get_canonical_url($post)
{
  $url = '';
  // Ad bundles should return the path to the actual bundle HTML file - not the slug URL which then results in a redirect
  // (and a different relative URL to the ad bundle contents)
  if (isset($post) && $post->post_type == PUGPIG_AD_BUNDLE_POST_TYPE) {
    $url = pugpig_ad_bundle_url($post);
  } else {
    $url = pugpig_permalink(pugpig_get_permalink($post));
  }

  if (substr($url, 0, 4) !== 'http') {
    $root_url = wp_parse_url(get_home_url());

    return $root_url['scheme'] . '://' . $root_url['host'] . (isset($root_url['port']) ? ':' . $root_url['port'] : '') . $url;
  } else {
    return $url;
  }
}

function pugpig_get_manifest_url($post)
{
  $manifest_url = pugpig_permalink(pugpig_get_permalink($post));

  return pugpig_rewrite_endpoint_url($manifest_url, PUGPIG_HTML_MANIFEST_NAME);
}

function pugpig_get_package_manifest_url($edition, $strip_domain=true)
{
  //$edition_url = pugpig_permalink(get_permalink($edition));
  $ret = home_url() . "/editionfeed/".$edition->ID . "/" . PUGPIG_EDITION_PACKAGE_FILE_NAME;
  if ($strip_domain) $ret = pugpig_strip_domain($ret);
  return $ret;
    // return pugpig_rewrite_endpoint_url($edition_url, PUGPIG_EDITION_PACKAGE_FILE_NAME);
}

function pugpig_get_edition_atom_url($edition, $strip_domain=true, $region='')
{
  //$edition_url = pugpig_permalink(get_permalink($edition));
  $ret =  home_url() . "/editionfeed/" . $edition->ID . "/";
  if (!empty($region)) $ret .= $region . "_";
  $ret .= PUGPIG_ATOM_FILE_NAME;
  if ($strip_domain) $ret = pugpig_strip_domain($ret);
  return $ret;
}

function print_filters_for($hook = '')
{
    global $wp_filter;
    if( empty( $hook ) || !isset( $wp_filter[$hook] ) )

        return;

    print '<pre>';
    print_r( $wp_filter[$hook] );
    print '</pre>';
}

function pugpig_get_permalink($post) {
  $status = $post->post_status;
  if ($post->post_status != 'draft' && $post->post_status != 'auto-draft' && $post->post_status != 'pending'){
    $post->post_status = 'publish';
  }

  $link = get_permalink( $post );

  //Reset post status
  $post->post_status = $status;

  return $link;
}

/************************************************************************
Get the attachment URL without the domain. This is needed for CDN plugins
that might rewrite the URL to include the CDN domain. We want this to run very late.

We must NOT do this with the primary domain as if we do later filters will stick
the domain back WITH AN EXTRA /wordpress/ if not installed at root
************************************************************************/
function pugpig_wp_get_attachment_url($url)
{
  $absoluteprefix = pugpig_get_root();
  if (startsWith($url, $absoluteprefix)) return $url;

  if (!empty($url)) $url = pugpig_strip_domain($url);
  return $url;
}

// Incoming URLs...
add_action('init', 'pugpig_add_endpoints');
function pugpig_add_endpoints()
{

  $regions = pugpig_get_available_region_array();
  foreach (array_merge(array(''), array_keys($regions)) as $region) {
    $prefix = ($region ? $region . "_" : "");
    $endpoint = str_replace(".","_", $prefix . PUGPIG_ATOM_FILE_NAME);
    add_rewrite_tag('%'.$endpoint.'%','');
    add_rewrite_rule(
        'editionfeed/([^/]+)/'. $prefix . PUGPIG_ATOM_FILE_NAME.'$',
        'index.php?post_type=pugpig_edition&p=$matches[1]&'. $endpoint.'=true',
        "top");
  }

  $endpoint = str_replace(".","_", PUGPIG_EDITION_PACKAGE_FILE_NAME);
  add_rewrite_tag('%'.$endpoint.'%','');
  add_rewrite_rule(
      'editionfeed/([^/]+)/'.PUGPIG_EDITION_PACKAGE_FILE_NAME.'$',
      'index.php?post_type=pugpig_edition&p=$matches[1]&'. $endpoint.'=true',
      "top");

//global $wp_rewrite;
//$wp_rewrite->flush_rules();


  // Stop WordPress redirecting our lovely URLs and putting a / on the end
  if (pugpig_is_pugpig_url() || pugpig_is_pugpig_manifest()
      || pugpig_is_pugpig_package_xml() || pugpig_is_pugpig_edition_atom_xml()) {

   // Turn off the CDN rewriting for Pugpig URLS. This is needed for W3 Total Cache
    if ( ! defined('DONOTCDN') ) define('DONOTCDN', 'PUGPIG');
    if ( ! defined('DONOTCACHEPAGE') ) define('DONOTCACHEPAGE', 'PUGPIG');

    // Don't redirect - we don't want the slash on the end
    remove_filter('template_redirect', 'redirect_canonical');

    // Ensure we don't get URLs to different domains for attachments
    add_filter('wp_get_attachment_url', 'pugpig_wp_get_attachment_url', 2);
    add_filter('stylesheet_directory_uri', 'pugpig_wp_get_attachment_url', 2);
    add_filter('template_directory_uri', 'pugpig_wp_get_attachment_url', 2);
  }

  // We need these so that WordPress strips the bits off and still matches the post
  add_rewrite_endpoint(PUGPIG_HTML_FILE_NAME, EP_PERMALINK | EP_ROOT | EP_SEARCH | EP_PAGES); // Adds pugpig.html as default document to permalinks
  add_rewrite_endpoint(PUGPIG_HTML_MANIFEST_NAME, EP_PERMALINK | EP_ROOT | EP_SEARCH | EP_PAGES); // Adds manifest to permalinks
  add_rewrite_endpoint(PUGPIG_EDITION_PACKAGE_FILE_NAME, EP_PERMALINK | EP_ROOT | EP_SEARCH | EP_PAGES); // Adds package files
  add_rewrite_endpoint(PUGPIG_ATOM_FILE_NAME, EP_PERMALINK | EP_ROOT | EP_SEARCH | EP_PAGES); // Adds ATOM XML files
}

add_action('template_redirect', 'pugpig_catch_request');
function pugpig_catch_request()
{
  // HTML manifest
  if (pugpig_is_pugpig_manifest()) {

    if (!is_singular()) {
      header('HTTP/1.1 403 Forbidden');
      echo "Not a valid pugpig request";
      exit();
    }

    header("Content-Type: text/cache-manifest");
    $post = get_queried_object();

    if ($post->post_status != 'publish') {
      header('X-Pugpig-Status: unpublished');
    } else {
      header('X-Pugpig-Status: published');
    }

    echo pugpig_build_post_manifest_contents($post); //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    exit();
  }

  // Package XML file
  if (pugpig_is_pugpig_package_xml()) {

   if (!is_singular()) {
      header('HTTP/1.1 403 Forbidden');
      echo "Not a valid package request";
      print_r($vars);
      exit();
    }
    $post = get_queried_object();
    if ($post->post_type != PUGPIG_EDITION_POST_TYPE) {
      header('HTTP/1.1 403 Forbidden');
      echo "Not a valid package XML request - object is not an edition";
      exit();
    }

    if ($post->post_status != 'publish') {
      header('X-Pugpig-Status: unpublished');
    } else {
      header('X-Pugpig-Status: published');
    }

    package_edition_package_list($post);
    exit();
  }

  if (pugpig_is_pugpig_edition_atom_xml()) {

   if (!is_singular()) {
      header('HTTP/1.1 403 Forbidden');
      echo "Not a singular valid atom feed request";
      exit();
    }
    $post = get_queried_object();
    if ($post->post_type != PUGPIG_EDITION_POST_TYPE) {
      header('HTTP/1.1 403 Forbidden');
      echo "Not a valid atom XML request - object is not an edition";
      exit();
    }

    // TODO: Think about different cache headers for searches?
    $search_term = null;
    if (isset($_GET["q"]) && !empty($_GET["q"])) {
      if (pugpig_should_allow_search()) $search_term = $_GET["q"];
    }

    generate_edition_atom_feed($post->ID, false, $search_term);
    exit();
  }

}

/************************************************************************
All pugpig posts to public
************************************************************************/

function pugpig_see_the_future($query_obj = '')
{
    global $wp_post_statuses;

    //echo 'FUNCTION RUNNING';
    // Make future posts and manifests visible to app
    if (pugpig_is_pugpig_url() || pugpig_is_pugpig_manifest()
      || pugpig_is_pugpig_package_xml() || pugpig_is_pugpig_edition_atom_xml()) {
        $wp_post_statuses[ 'future' ]->public = true;
      }

    if (pugpig_is_pugpig_package_xml() || pugpig_is_pugpig_edition_atom_xml()) {
        // Need to see drafts for preview
        $wp_post_statuses[ 'draft' ]->public = true;
        $wp_post_statuses[ 'pending' ]->public = true;
    }

}

if ( ! is_admin( ) ) {
        add_action( 'pre_get_posts', 'pugpig_see_the_future' );
}
