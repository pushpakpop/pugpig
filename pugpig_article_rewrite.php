<?php
/**
 * @file
 * Pugpig Article Rewrite
 */
?><?php
/*

Licence:
==============================================================================
(c) 2011, Kaldor Holdings Ltd
This module is released under the GNU General Public License.
See COPYRIGHT.txt and LICENSE.txt

 */?><?php

/************************************************************************
Change the permalink to rewrite outgoing links
*************************************************************************/

require_once 'common/url_to_absolute/add_relative_dots.php';
require_once 'common/pugpig_manifests.php';
require_once 'common/pugpig_utilities.php';
require_once 'pugpig_url_rewrites.php';

if (!class_exists('\simple_html_dom_node')) {
  require_once 'vendor/simple_html_dom.php';
}

add_filter('post_link','pugpig_permalink');
add_filter('post_type_link','pugpig_permalink');
add_filter('page_link','pugpig_permalink');
add_filter('category_link','pugpig_permalink');
add_filter('tag_link','pugpig_permalink');
add_filter('author_link','pugpig_permalink');
add_filter('day_link','pugpig_permalink');
add_filter('month_link','pugpig_permalink');
add_filter('year_link','pugpig_permalink');

function pugpig_permalink($permalink, $endpoint = null)
{
  // Ignore this if we are outputting a feed
  if (pugpig_is_pugpig_url()) {
    $permalink = pugpig_rewrite_html_url($permalink);
  }

  return $permalink;
}

/************************************************************************
Show the Pugpig theme if required
*************************************************************************/
add_filter( 'template', 'pugpig_theme_switcher' );
add_filter( 'stylesheet', 'pugpig_theme_switcher' );

function pugpig_theme_switcher($theme)
{
  // See if we have a setting for theme override
  $theme_switch = get_option("pugpig_opt_theme_switch");
  if (empty($theme_switch)) return $theme;

  if (pugpig_is_pugpig_url()) {
    return $theme_switch;
  }

  return $theme;
}

/************************************************************************
Register a buffer handlers so we can search/replace all the output
TODO: Find better places to register these hooks
shutdown doesn't seem to work
************************************************************************/
add_action('template_redirect','pugpig_ob_start');
function pugpig_ob_start()
{
    if (pugpig_is_pugpig_url() && !isset($_REQUEST['norewrite'])) {
        ob_start('pugpig_rewrite_content');
    }
}

add_action('wp_print_footer_scripts','pugpig_ob_end');
function pugpig_ob_end()
{
    if (pugpig_is_pugpig_url()) {
        ob_end_flush();
    }
}

/************************************************************************
Get the root so we can make relative links
We want everything up to the third slash
************************************************************************/
function pugpig_get_root()
{
  $url = get_home_url();

  while (substr_count($url, '/') > 2) { // all we need is the :// from the protocol
    $array = explode('/', $url);
    array_pop($array);
    $url = implode('/', $array);
  }

  return $url;
}

/************************************************************************
Remove domain from URLs that could be root relative
************************************************************************/
function pugpig_rewrite_content($content)
{
  // This is no longer required as the link to the manifest is in the ATOM feed

  // $id = 'post-' . get_the_ID() . ".manifest";
  // $manifest = pugpig_path_to_rel_url(PUGPIG_MANIFESTPATH . $id);
  // $pattern = '/<html/i';
  // $content = preg_replace($pattern,'<html manifest="' . $manifest . '"',$content);

  // TODO: Need to make this less aggresive - we want absolute URLs for non html cases
  //if (strpos($content, '<html') !== FALSE) {
  $absoluteprefix = pugpig_get_root();
  $content = str_replace($absoluteprefix, '', $content);
    // $content = str_replace(" site", " Puggers site", $content);
    //}
  $content = pugpig_rewrite_wpcontent_links($content);

  return $content;
}

/************************************************************************
Returns a block of markup with image URLs fixed
This will also remove a ?ver=123 from the query string
************************************************************************/
function pugpig_rewrite_wpcontent_links($markup)
{
  $content_fragment = preg_quote(pugpig_strip_domain(content_url()), '#');
  $regex = '#([\'"])('.$content_fragment.'/.*?)\1#i';

  $index = 0;
  if (preg_match_all($regex, $markup, $matches)) {
    foreach ($matches[2] as $src) {
      $quote = $matches[1][$index];
      $new_uri = url_create_deep_dot_url($src);

      // Strip version number?
      $new_uri = remove_query_arg( 'ver', $new_uri );
      $markup= str_replace($quote.$src.$quote, $quote.$new_uri.$quote, $markup);
      ++$index;
    }
  }

  return _pugpig_rewrite_pugpig_html_links($markup);
}

function _pugpig_rewrite_pugpig_html_links($markup) {
  $html = new simple_html_dom();
  $html->load($markup, false, false);
  $anchors = $html->find('a[href$="/'.PUGPIG_HTML_FILE_NAME.'"]');
  foreach ($anchors as $anchor) {
    $anchor->href = url_create_deep_dot_url($anchor->href);
  }
  return $html->save();
}

/************************************************************************
Utility functions for when we want to proxy to images that are served
from another domain. We need to:
- replace the paths of all images in the body to be local URLS
- include these new paths in the manifest
- intercept all calls to these new paths and redirect to the source
************************************************************************/
// Extract all image paths from the block
function pugpig_get_image_urls($markup)
{
   $assets = array();
   if (preg_match_all('#<img.*?src=([\'"])(.*?)\1#i', $markup, $matches)) {

     foreach ($matches[2] as $src) {
        array_push($assets, $src);
     }
   }

   return $assets;
}

function pugpig_get_encoded_image_url($url, $prefix, $make_relative=true)
{
  $url = apply_filters('pugpig_rewrite_external_image_url', $url);
  $content_fragment = pugpig_strip_domain(content_url());
  $out = "${content_fragment}/${prefix}/" . base64_encode($url) . '.jpeg';
  $out = str_replace('=', '_', $out);
  if ($make_relative) {
    $out = url_create_deep_dot_url($out);
  }

  return $out;
}

class _PugpigImageReplacer
{
  public $prefix;
  public $base;
  public $make_relative;

  public function pugpig_replace_image_urls($markup, $prefix, $base='', $make_relative=true)
  {
    $this->prefix = $prefix;
    $this->base = $base;
    $this->make_relative = $make_relative;

    $out = preg_replace_callback('/(<img\s+.*?src=([\'"]))(.*?)(\2)/mi',
      array($this, "pugpig_replace_image_callback"),
      $markup
    );

   return $out;
  }

  public function pugpig_replace_image_callback($matches)
  {
    $orig_src = $matches[3];
    if (strncmp($orig_src, '/', 1)===0) {
      $orig_src = $this->base .$orig_src;
    }
    $new_src = pugpig_get_encoded_image_url($orig_src, $this->prefix, $this->make_relative);

    return $matches[1] . $new_src . $matches[4];
  }
}

// Replace all image paths to the generic serve-from-source base64 path
// Creates . relative URLs
function pugpig_replace_image_urls($markup, $prefix, $base='', $make_relative=true)
{
  $image_replacer = new _PugpigImageReplacer();

  return $image_replacer->pugpig_replace_image_urls($markup, $prefix, $base, $make_relative);
}

function pugpig_add_inline_images_to_manifest($markup, $prefix, $base='')
{
  $fixed_markup = pugpig_replace_image_urls($markup, $prefix, $base, false);
  $images = pugpig_get_image_urls($fixed_markup);
  $ret = "\n# Adding images found in the body\n";
  $ret .= implode("\n", $images) . "\n\n";

  return $ret;
}

// Check if we are on one of our paths and redirect to the source if yes
function pugpig_intercept_remote_image_urls($prefix)
{
   $uri =  $_SERVER['REQUEST_URI'];
  if (empty($uri)) {
    return;
  }

  $parts = split_url($uri);

  $matches = array();
  $content_fragment = preg_quote(pugpig_strip_domain(content_url()), '/');
  $pattern = "/${content_fragment}\/${prefix}\/(.*)\.([^\.]+)$/i";
  
  $results = preg_match($pattern, $uri, $matches);
  if ($results > 0) {
    $id = $matches[1];
    $new_id = str_replace("_", "=", $id);

    $image_url = base64_decode($new_id);
    //echo("DEBUG. I SHOULD REDIRECT TO\nLocation: ". $image_url); exit();
    header('Location: '. $image_url);
    exit();
  }
}
