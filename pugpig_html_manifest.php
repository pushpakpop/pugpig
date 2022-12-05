<?php
/**
 * @file
 * Pugpig WordPress HTML Manifests
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
Get WordPress attachment manifests
*************************************************************************/
function pugpig_get_attachments($post)
{
 $output = "";

 $featured_image_id = get_post_thumbnail_id($post->ID);
 if (isset($featured_image_id) && !empty($featured_image_id)) {
   $output .= "# Featured image\n";
   $output .= pugpig_strip_domain(wp_get_attachment_url($featured_image_id)) . "\n";
   $output .= "\n";
 }

$added = array();

 $args = array(
      'post_type' => 'attachment',
      'numberposts' => -1,
      'post_status' => null,
      'post_parent' => $post->ID
      );
 $attachments = get_posts($args);

 $output .= "# Post Attachments (" . count($attachments) . " original files, maybe not all used)\n";

  $post_body  = $post->post_content;
  $post_body = apply_filters('the_content', $post_body);

   foreach ($attachments as $attachment) {

    if (wp_attachment_is_image($attachment->ID)) {
     // For each attachment, we find all the possible sizes
     // Then we parse the body and include all those that are used
     $output .=  "# Checking: " . pugpig_strip_domain(wp_get_attachment_url($attachment->ID)) . "\n";

     $image_urls = array();
      foreach (array('thumbnail', 'medium', 'large', 'full') as $size) {
        $image_info = wp_get_attachment_image_src($attachment->ID, $size);
        $image_urls[] = $image_info[0];
      }

      $found_image = false;
      foreach (array_unique($image_urls) as $img) {
        if (strstr($post_body, $img)) {
          $output .= "# Image found in body text\n";
          $output .=  pugpig_get_inline_image_urls(pugpig_strip_domain($img)) . "\n";
          $added[] = pugpig_get_inline_image_urls(pugpig_strip_domain($img));
          $found_image = true;
        }
      }

    } else {
      // Not an image. Just include it
      $output .=  pugpig_strip_domain(wp_get_attachment_url($attachment->ID)) . "\n";

    }
   }

  // Find any image URLS in the main body that are from
  // our domain but not in the manifest
  $image_urls = pugpig_get_image_urls($post_body);
  $base = pugpig_get_current_base_url();

  foreach ($image_urls as $i) {
    if (startsWith($i, $base ) && !in_array(pugpig_strip_domain($i), $added)) {
      $output .= "# Extra absolute on our domain in markup " . pugpig_get_inline_image_urls($i) . "\n";
      $output .= pugpig_get_inline_image_urls(pugpig_strip_domain($i)) . "\n";
    } elseif (startsWith($i, "/")) {
      $output .= "# Extra relative in markup " . $i . "\n";
      $output .= pugpig_get_inline_image_urls($i) . "\n";
    } else {
      $output .= "# Rejecting: " . pugpig_get_inline_image_urls($i) . "\n";

    }
  }

 return $output;
}

/************************************************************************
Generate WordPress static theme file
*************************************************************************/
function pugpig_get_theme_manifest()
{
    $theme_name = get_option("pugpig_opt_theme_switch");

    if (!isset($theme_name) || $theme_name == '')
      $theme_name = get_template();

    $theme_dir = get_theme_root();
    $theme_url = get_theme_root_uri();

    if (!is_dir($theme_dir . "/$theme_name") && $theme_name != '') {echo "ERROR: Invalid theme name: " . esc_html($theme_name);
      exit();}

    $theme_path = pugpig_strip_domain($theme_url . "/" . $theme_name);
    $theme_dir = $theme_dir . "/" . $theme_name . "/";

    $output = pugpig_theme_manifest_string($theme_path, $theme_dir, $theme_name);

    if (is_child_theme()) {
      $output .= "\n# Child Theme Assets\n";

      $theme_name = get_stylesheet();

      $theme_dir = get_theme_root();

      if (!is_dir($theme_dir . "/$theme_name") && $theme_name != '') {echo "ERROR: Invalid child theme name: " . esc_html($theme_name);
        exit();}

      $theme_path = pugpig_strip_domain($theme_url . "/" . $theme_name);
      $theme_dir = $theme_dir . "/" . $theme_name . "/";

      $output .= pugpig_theme_manifest_string($theme_path, $theme_dir, $theme_name);
    }

    return $output;
}

/************************************************************************
Extensions allowed in the theme manifest
*************************************************************************/
function isAllowedExtension($filename)
{
  $blockedExtensions = array('info', 'php', 'inc', 'scss', 'manifest', 'module', 'exe', 'com', 'bat', 'sh', 'dll', 'db');

  $parts = explode('.', $filename);
  $extension = end($parts);
  $result = !(in_array($extension, $blockedExtensions));

  return $result;
}

/************************************************************************
Generate a fragment of an HTML5 manifest file for a custom field attachment
*************************************************************************/
function pugpig_get_custom_field_manifest_item($post, $field)
{
  $attach_ids = get_post_meta( $post->ID, $field);

  $ret = "";
  foreach ($attach_ids as $attach_id) {
  // We have a custom image
    $ret .=  "\n# Custom Meta Field: $field\n";
    $ret .= pugpig_strip_domain(wp_get_attachment_url($attach_id)) . "\n";
  }

  return $ret;
}

/************************************************************************
Generate a fragment of an HTML5 manifest file for a single WordPress post
*************************************************************************/
function pugpig_build_post_manifest_contents($post)
{
  $output = "CACHE MANIFEST\n";

  $output .= "# Post: " . trim(preg_replace('/\s+/', ' ', $post->post_title)) . "\n";
  $output .= "# Generated by the PugPig WordPress plugin" . "\n";
  $output .= "# Generated at: " . pugpig_date3339() . "\n";
  $output .= "# Last Modified: " . _ago(pugpig_get_page_modified($post)) . "ago\n";
  $output .= "\nCACHE:\n";

  if (!is_null($post)) {

    $attachment_manifest_items = pugpig_get_attachments($post);
    $attachment_manifest_items = apply_filters('pugpig_attachment_manifest_items', $attachment_manifest_items, $post);
    $output .= $attachment_manifest_items;

    // Get level one children if required
    if (in_array($post->post_type, pugpig_get_hierarchical_types())) {
        $child_args = array(
          'post_type' => $post->post_type,
          'order' => 'ASC',
          'orderby' => 'menu_order',
          'post_parent' => $post->ID
        );
        $children = get_children($child_args);
        foreach ($children as $child) {
        $output .= "# Checking child post: $child->post_title\n";
          $attachment_manifest_items = pugpig_get_attachments($child);
          $attachment_manifest_items = apply_filters('pugpig_attachment_manifest_items', $attachment_manifest_items, $child);
          $attachment_manifest_items = apply_filters('pugpig_extra_manifest_items', $attachment_manifest_items, $child);
          $output .= $attachment_manifest_items;
        }
      }

    // Extra custom items
    $output .= apply_filters('pugpig_extra_manifest_items', '', $post);

    // The theme assets
    $theme_manifest_items = '';
    $theme_manifest_items .= "\n" . pugpig_get_theme_manifest();
    $theme_manifest_items = apply_filters('pugpig_theme_manifest_items', $theme_manifest_items, $post);
    $output .= $theme_manifest_items;

    // If we're a published edition, add the CDN if we have one
    if (true) {
      $cdn = get_option('pugpig_opt_cdn_domain');
      $output = pupig_add_cdn_to_manifest_lines($output, $cdn);
    }

  }

  $output .="\nNETWORK:\n*\n";

  return $output;
}

add_filter('pugpig_theme_manifest_items', '_pugpig_ignore_theme_manifest_items',10,2);
function _pugpig_ignore_theme_manifest_items($output, $post) {
  $regex = _pugpig_ignore_theme_manifest_regex();
  if (!empty($regex)) {
      $output_lines = explode("\n", $output);
      $output_lines = array_map(function ($item) use ($regex) {
        return preg_match($regex, $item) ? '' : $item;
      }, $output_lines);

      $output = implode("\n", array_filter($output_lines));
      $output .= "\n\n# Removed from theme:\n# " . preg_replace('!\n!s', "\n# ", pugpig_opt_remove_files());
  }
  return $output;
}

add_filter('pugpig_theme_manifest_items', '_pugpig_add_custom_manifest_items',10,2);
function _pugpig_add_custom_manifest_items($output, $post) {
  $output .= "\n\n# Custom manifest items:\n" . pugpig_opt_add_files();
  return $output;
}


function _pugpig_ignore_theme_manifest_regex(){
  $files_string = pugpig_opt_remove_files();

  if (!empty($files_string)){
    $files = explode("\n", $files_string);

    $content_fragment = preg_quote(pugpig_strip_domain(content_url(), '!'));
    $regex = "!^${content_fragment}\/themes\/[^/]*\/(?:";

    $x = 0;
    foreach($files as $file){
      $or = '|';
      if ($x == 0) $or = ''; $x++;

      if (substr($file, -2, 1) == DIRECTORY_SEPARATOR){
        $file = substr($file, 0, -1) . "*\n";
      }

      $file = preg_replace('!"!', '', $file);
      $file = preg_replace("!'!", '', $file);
      $file = preg_replace('@!@', '', $file);

      $file = preg_replace('!\.!', '\.', $file);
      $file = preg_replace('!\\' . DIRECTORY_SEPARATOR . '!', '\\' . DIRECTORY_SEPARATOR, $file);
      $file = preg_replace('!\*!', '.*', $file);

      $file .= '$';

      $regex .= $or . $file;
    }
    $regex .= ')!';
    $regex = preg_replace("!\s!", '', $regex);
  } else {
    $regex = null;
  }

  return $regex;
}
