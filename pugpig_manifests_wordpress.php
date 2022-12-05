<?php
/**
 * @file
 * Pugpig Manifest Mappings for WordPress
 */
?><?php
/*

Licence:
==============================================================================
(c) 2011, Kaldor Holdings Ltd
This module is released under the GNU General Public License.
See COPYRIGHT.txt and LICENSE.txt

 */?><?php

include 'common/pugpig_manifests.php';
include 'common/pugpig_packager.php';

function pugpig_remove_wordpress_headers()
{
  if (function_exists('header_remove')) {
    // header_remove is available from PHP 5.3.0
    header_remove('Pragma');
    header_remove('X-Pingback');
    header_remove('Link');
    header_remove('Cache-Control');
    header_remove('Expires');
  }
}

function generate_edition_atom_feed($edition_id, $include_hidden = false, $search_term = null)
{
    // Check it exists
    $edition = get_post($edition_id);
    if (empty($edition)) {
        header('HTTP/1.1 404 Not Found');
        exit;
    }

    pugpig_remove_wordpress_headers();

    $modified = pugpig_get_page_modified($edition);
    if ($search_term) $modified = time();

    if ($edition->post_status != 'publish') {
      if (FALSE && !pugpig_is_internal_user()) {
        header('HTTP/1.1 403 Forbidden');
        exit;
      }
      header('X-Pugpig-Status: unpublished');
      pugpig_set_cache_headers($modified, 0);
    } else {
      header('X-Pugpig-Status: published');
      pugpig_set_cache_headers($modified, pugpig_get_feed_ttl());

    }

    $x_entitlement = pugpig_get_edition_entitlement_header($edition);
    if (!empty($x_entitlement)) header('X-Pugpig-Entitlement: ' . $x_entitlement);

    $links = pugpig_get_edition_atom_links($edition, true);

    header('Content-Type: ' . feed_content_type('atom') . '; charset=' . get_option('blog_charset'), true);
    header('Content-Disposition: inline');

    global $wp_query;

    $filter = "";
    $regions = pugpig_get_available_region_array();
    foreach (array_keys($regions) as $region) {
      if (isset($wp_query->query_vars[$region . "_pugpig_atom_contents_manifest"])) {
        $filter = $region;
      }
    }

    $region = "";
    $d = pugpig_get_atom_container($edition_id, $include_hidden, $search_term, $links, $filter);
    $d->formatOutput = true;



    echo $d->saveXML(); //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}

function _package_final_folder()
{
  return PUGPIG_MANIFESTPATH . 'packages/';
}

function get_edition_package($edition_id)
{
  $edition = get_post($edition_id);
  $edition_key = pugpig_get_full_edition_key($edition);
  //print_r("*** $edition_key ***");
  $package_search_expression = PUGPIG_MANIFESTPATH . 'packages/' . $edition_key . '-package-*.xml';

  return _package_get_most_recent_file($package_search_expression);

}

function get_edition_package_url($edition_id)
{
  $packaged_timestamp = get_edition_package_timestamp($edition_id);
  if (empty($packaged_timestamp)) return "";
  $edition = get_post($edition_id);

  return pugpig_strip_domain(pugpig_get_package_manifest_url($edition));

/*
  $wp_ud_arr = wp_upload_dir();
  $package_xml =  get_edition_package($edition_id);
  if (strlen($package_xml) > 0) {
    $url = $wp_ud_arr['baseurl'] . '/pugpig-api/packages' . substr($package_xml, strrpos($package_xml, '/'));
    $url = pugpig_strip_domain($url);
  } else {
    $url = '';
  }

  return $url;
*/
}

function get_edition_package_timestamp($edition_id)
{
  $package_xml =  get_edition_package($edition_id);

  return filemtime($package_xml);

}

// Returns the latest XML package descriptor
function package_edition_package_list($edition)
{
  $edition_tag = pugpig_get_full_edition_key($edition);
  $wp_ud_arr = wp_upload_dir();

  if ($edition->post_status == 'publish') {
    $cdn = get_option('pugpig_opt_cdn_domain');
  } else {
    $cdn = '';
  }

  $xml = _package_edition_package_list_xml(
    PUGPIG_MANIFESTPATH . 'packages/',
    $edition_tag,
    pugpig_strip_domain($wp_ud_arr['baseurl']) . '/pugpig-api/packages/',
    $cdn,
    'string',
    '*',
    PUGPIG_ATOM_FILE_NAME
    );

  if (is_null($xml)) {
    header('HTTP/1.0 404 Not Found');
    echo "This page does not exist. Maybe edition " . esc_html( $edition_tag ) . " has no packages.";
    exit;
  }

  pugpig_remove_wordpress_headers();

  $modified = get_edition_package_timestamp($edition->ID);

  if ($edition->post_status != 'publish') {
      header('X-Pugpig-Status: unpublished');
      pugpig_set_cache_headers($modified, 0);
  } else {
      header('X-Pugpig-Status: published');
      pugpig_set_cache_headers($modified, pugpig_get_feed_ttl());
  }

  $x_entitlement = pugpig_get_edition_entitlement_header($edition);
  if (!empty($x_entitlement)) header('X-Pugpig-Entitlement: ' . $x_entitlement);

  header('Content-Type: application/pugpigpkg+xml; charset=utf-8');
  header('Content-Disposition: inline');

  print $xml; //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

  return NULL;
}

function pugpig_get_post_entitlement_header($post)
{
  $editions = pugpig_get_post_editions($post);

  // TODO: Need a way to open up sample posts

  foreach ($editions as $edition) {
    $custom = get_post_custom($edition->ID);
    if (!isset($custom["edition_free"])) {
          return pugpig_get_full_edition_key($edition);
    }
  }

  return "";
}

function pugpig_get_edition_entitlement_header($edition)
{
  $custom = get_post_custom($edition->ID);
  $has_samples = isset($custom['edition_samples']);

  if (!isset($custom["edition_free"]) && !$has_samples) {
        return pugpig_get_full_edition_key($edition);
  }

  return "";
}

function pugpig_get_edition($id, $include_hidden = true, $is_not_atom = true, $search_term = null)
{
  $edition = get_post($id);

  $attachment_id = get_post_meta( $edition->ID, '_thumbnail_id', true);
  $thumbnail = BASE_URL . "common/images/nocover.jpg";
  if (!empty($attachment_id)) {
    $thumbnail =  wp_get_attachment_url($attachment_id);
  }
  $thumbnail = pugpig_strip_domain($thumbnail);

  // Use the CDN in the feed for the cover if the edition is published
  if ($edition->post_status == 'publish') {
    $cdn = get_option('pugpig_opt_cdn_domain');
    if (!empty($cdn)) $thumbnail = $cdn . $thumbnail;
  }

  $region = (isset($_GET["region"]) ? $_GET["region"] : "");
  $url = pugpig_strip_domain(pugpig_get_edition_atom_url($edition, true, $region));
  $url_type = 'application/atom+xml';

 $packaged_timestamp = '';
  if ($is_not_atom) {
    $url_type = 'application/pugpigpkg+xml';

    // TODO: Check if something exists.
    // If yes, use pugpig_get_package_manifest_url
    $packaged_timestamp = get_edition_package_timestamp($edition->ID);

    $url = get_edition_package_url($edition->ID);
    if ($url != '') {
      $packaged_timestamp = get_edition_package_timestamp($edition->ID);
      $url = pugpig_strip_domain($url);
    }
  }

  $custom = get_post_custom($edition->ID);

  $price = "FREE";
  if (!isset($custom["edition_free"])) {
    $price =  "PAID";
  }

  $has_samples = isset($custom['edition_samples']);

  $deleted = false;
  if (isset($custom["edition_deleted"])) {
    $deleted =  true;
  }

  $pdf_attachement_url = '';
  $pdf_modified = '';
  $is_pdf_edition = false;
  $pdf = get_attached_media('application/pdf', $edition->ID);

  if (count($pdf) > 0){
    $media_id = max(array_keys($pdf));
    $pdf = $pdf[$media_id];
    $pdf_attachement_url = pugpig_strip_domain($pdf->guid);
    $pdf_modified = strtotime($pdf->post_modified_gmt);
    $is_pdf_edition = true;
  }

  $newsstand_cover_attachment_url = "";
  $newsstand_cover_attachment_id = get_post_meta($edition->ID, 'pugpigmb_newsstand_cover', true);
  if (!empty($newsstand_cover_attachment_id)) {
    $newsstand_cover_attachment_url = wp_get_attachment_url($newsstand_cover_attachment_id);
  }

  $custom_categories = pugpig_get_edition_opds_custom_categories($edition, $is_not_atom);

  $page_id_array = pugpig_get_edition_array(get_post_custom($edition->ID));

  // If we have a search term, filter further
  if ($search_term) {
    $search_result_ids = array();

    $args = array(
        'post__in' => $page_id_array,
        's' => $search_term,
    );

    $wp_query = new WP_Query($args);
    while ($wp_query->have_posts() ) :
      $wp_query->the_post();

      global $post;
      $search_result_ids[] = $post->ID;

    endwhile;
    $page_id_array = $search_result_ids;

  }

  $item = array(
    'id' => $edition->ID,
    'title' => $edition->post_title,
    'key' => pugpig_get_full_edition_key($edition), // Need edition value for KEY
    'summary' => $edition->post_excerpt,
    'is_pdf' => $is_pdf_edition,
    'pdf_url' => $pdf_attachement_url,
    'pdf_modified' => $pdf_modified,
    'newsstand_summary' => get_post_meta($edition->ID, 'pugpigmb_newsstand_long_desc', true),
    'newsstand_cover_art_icon_source' => $newsstand_cover_attachment_url,
    'page_ids' => $page_id_array,
    'author' => get_post_meta($edition->ID, 'edition_author', true),
    'price' => $price,
    'has_samples' => $has_samples,
    'date' => get_post_meta( $edition->ID, 'edition_date', true ),
    'status' => ($edition->post_status == 'publish' ? 'published' : $edition->post_status),
    'modified' => pugpig_get_page_modified($edition),
    'thumbnail' => $thumbnail,
    'url' => $url,
    'url_type' => $url_type,
    'sharing_link' => get_post_meta( $edition->ID, 'edition_sharing_link', true ),
    'zip' => $url,
    'packaged' => $packaged_timestamp,
    'custom_categories' => $custom_categories,
    'links' => pugpig_get_edition_opds_links($edition),
    'tombstone' => $deleted
  );

  return $item;
}

// Returns an array of enclosures that go in the edition's entry in the OPDS feed
function pugpig_get_edition_opds_links($edition, $relative = false)
{
  $links = array();
  if (pugpig_should_allow_search()) {
    $links[] = array(
            'rel'   => 'search',
            'href'  => pugpig_strip_domain(home_url() .
             '/editionfeed/' . $edition->ID . '/pugpig_atom_contents.manifest?q={query}'),
            'title' => 'Search',
            'type'  => 'application/atom+xml'
        );
  }

  $links = _pugpig_get_links('pugpig_add_edition_opds_link_items', $links, $relative, $edition);

  return $links;
}

function pugpig_get_edition_opds_custom_categories($edition, $is_not_atom = true){
  $custom_categories = array();
  if ($is_not_atom) {
    $size = _edition_download_size($edition, 0);
    $custom_categories['download_size'] = $size;
  }

  $custom_categories = apply_filters('pugpig_add_opds_custom_categories', $custom_categories, $edition);

  return $custom_categories;
}

// Returns an array of enclosures to at the top of the edition Atom feed
function pugpig_get_edition_atom_links($edition, $relative = false)
{
  return _pugpig_get_links('pugpig_add_edition_atom_link_items', array(), $relative, $edition);
}

/**
 * Utility function to return the links provided by the filter specified.
 * Note that you can add arguments after $links and these will be passed to the filter.
 * @param  string $filter_name     the name of the filter to call
 * @param  array $links            the existing links to filter
 * @param  boolean $make_relative  whether to try to change the hrefs in the links to relative URLs
 * @return array                   the full array of links
 */
function _pugpig_get_links($filter_name, $links = array(), $make_relative = false)
{
  // set filter arguments to be the name, the links and any unnamed arguments passed to the function
  $filter_args = array_merge(array($filter_name, $links), array_slice(func_get_args(), 3));
  $links = call_user_func_array('apply_filters', $filter_args);

  if ($make_relative) {
    // fix any links that might not be relative
    $links = _pugpig_make_links_relative($links);
  }

  return $links;
}

function _pugpig_make_links_relative($links)
{
  $relative_links = array();
  if (!empty($links)) {
    foreach ($links as $link) {
      $link['href'] = url_create_deep_dot_url($link['href']);
      $relative_links[] = $link;
    }
  }

  return $relative_links;
}

function pugpig_get_full_edition_key($edition)
{
  $edition_prefix = pugpig_get_issue_prefix();

  return $edition_prefix . get_post_meta($edition->ID, 'edition_key', true);
}

function _pugpig_add_mimetype($item)
{
  $check = wp_check_filetype($item['href']);
  $item['type'] = $check['type'];

  return $item;
}
function pugpig_get_post_link_sounds($post)
{
  $sounds_info = array();
  $sounds_info = apply_filters('pugpig_add_sounds', $sounds_info, $post);
  $sounds_info = array_map('_pugpig_add_mimetype', $sounds_info);

  return pugpig_get_sounds_links($sounds_info);
}

// Returns an array of enclosures to include in the entry
function pugpig_get_links($post, $content_filter = null)
{
  // TODO: Make this a filter
  $links = pugpig_get_post_link_sounds($post);

  $links = apply_filters('pugpig_add_link_items', $links, $post, $content_filter);

  // Fix any links that might not be relative
  if (isset($links)) foreach ($links as &$link) {
    $link['href'] = url_create_deep_dot_url($link['href']);
  }

  return $links;
}

function pugpig_get_page_modified($post)
{
  $modified = strtotime($post->post_modified_gmt);

  // Override with a hook. For example, section index pages need
  // special logic
  $modified = apply_filters('pugpig_get_post_modified_time', $modified, $post);

  // TODO: Check the date of the theme and use this if more recent?
  return $modified;
}

function pugpig_post_process_pages($pages, $edition_id, $content_filter) {
    return apply_filters('pugpig_post_process_pages', $pages, $edition_id, $content_filter);
}

function pugpig_get_pages($id, $edition_id, $content_filter=null) {
    $page_info = pugpig_get_page($id, $edition_id, $content_filter);
    $pages = empty($page_info) ? array() : array($page_info);
    $pages = apply_filters('pugpig_get_pages', $pages, $id, $edition_id, $content_filter);
    return $pages;
}

function pugpig_get_page($id, $edition_id, $content_filter=null)
{
  $post = get_post($id);

  // Just in case this item has been deleted
  if (!is_object($post)
      || apply_filters('pugpig_filter_page', false, $post, $content_filter)) {
    return null;
  }

  // Get the link for sharing
  // TODO: Allow post specific values in future
  // Get canonical URL for sharing (e.g. Twitter, Facebook)
  $sharing_link = apply_filters( 'pugpig_page_sharing_link', pugpig_get_canonical_url($post), $post);

  $status = $post->post_status;
  // We want everything except draft, pending and trashed posts in an edition
  if ($status != 'draft' && $status != 'trash' && $status != 'pending') {
    $status = 'published'; // We expect the word 'published'
  }

  $stop_id_prefixes = false;
  $page = array(
    'id' => pugpig_get_atom_post_id($post, $stop_id_prefixes),
    'title' => pugpig_get_feed_post_title($post),
    'access' => pugpig_get_atom_post_access($post),
    'summary' =>  pugpig_get_feed_post_summary($post),
    'status' => $status,
    'modified' => pugpig_get_page_modified($post),
    'date' => strtotime ($post->post_date),
    'type' => $post->post_type,
    // layout
    'categories' => pugpig_get_feed_post_categories($post, $content_filter),
    // children
    // style
    'url' => url_create_deep_dot_url(pugpig_strip_domain(pugpig_get_html_url($post, $edition_id))),
    'sharing_link' => $sharing_link,
    'manifest' => url_create_deep_dot_url(pugpig_strip_domain(pugpig_get_manifest_url($post))),
    // name
    // hidden
    'custom_categories' => pugpig_get_feed_post_custom_categories($post, $content_filter),
    'links' => pugpig_get_links($post, $content_filter),
    'author' => pugpig_get_feed_post_author($post)
  );

  $level = pugpig_get_feed_post_level($post, $content_filter);
  if (!empty($level)) {
    $page['level'] = $level;
  }

  if ($stop_id_prefixes) {
    $page['id_prefix'] = '';
  }

  return $page;
}

function pugpig_get_atom_tag($key = '')
{
  return $key;
}

function pugpig_get_opds_extras()
{
  $extras = array();

  // get links for the top of the OPDS feed
  $extras['links'] = _pugpig_get_links('pugpig_add_opds_link_items');

  $auth_endpoints = '';
  $extras['auth_endpoints'] = apply_filters('pugpig_get_opds_auth_endpoints', $auth_endpoints);

  return $extras;
}

if (!function_exists('get_attached_media')) {
  function get_attached_media($type, $post = 0) {
    return pugpig_get_attached_media($type, $post = 0);
  }
}

function pugpig_get_attached_media( $type, $post = 0 ) {
  if ( ! $post = get_post( $post ) )
    return array();

  $args = array(
    'post_parent' => $post->ID,
    'post_type' => 'attachment',
    'post_mime_type' => $type,
    'posts_per_page' => -1,
    'orderby' => 'menu_order',
    'order' => 'ASC',
  );

  $args = apply_filters( 'get_attached_media_args', $args, $type, $post );

  $children = get_children( $args );

  return (array) apply_filters( 'get_attached_media', $children, $type, $post );
}

function _edition_download_size($edition, $precision=2, $type='long')
{

  $pdf = get_attached_media('application/pdf', $edition->ID);
  if (count($pdf) > 0) {
    $media_id = max(array_keys($pdf));
    $pdf = $pdf[$media_id];
    $size = pugpig_bytestosize(filesize(get_attached_file($pdf->ID)));
  } else {
    $edition_tag = pugpig_get_full_edition_key($edition);
    $size = _package_edition_package_size($edition_tag, 0);
  }

  return $size;
}

function pugpig_get_date_timezone()
{
  return get_option('timezone_string');
}
