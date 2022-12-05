<?php
/**
 * @file
 * Pugpig Edition OPDS feed
 */
?><?php
/*

Licence:
==============================================================================
(c) 2011, Kaldor Holdings Ltd
This module is released under the GNU General Public License.
See COPYRIGHT.txt and LICENSE.txt

 */?><?php

if (function_exists('header_remove')) {
  // header_remove is available from PHP 5.3.0
  header_remove('Pragma');
  header_remove('X-Pingback');
}

// Generate the OPDS feed of the editions

$status = 'publish';

$extra_comments = array();
$extra_comments[] = "Generated by: Pugpig WordPress Plugin " . PUGPIG_CURRENT_VERSION;

header('Content-Type: ' . feed_content_type('atom') . '; charset=' . get_option('blog_charset'), true);
header('Content-Disposition: inline; filename="opds.xml"');

$internal = false;

// Show internal to internal users or if explicit on query string
$override_internal = false;

// We don't want to cache internal feeds
$ttl = 0;

if ( (isset($_GET["internal"]) && $_GET["internal"] == 'false')) $override_internal = true;
if (!$override_internal && (pugpig_is_internal_user() || (isset($_GET["internal"]) && $_GET["internal"] == 'true'))) {
  // Stop plugins caching an internal feed

  if ( ! defined('DONOTCACHEPAGE') ) define('DONOTCACHEPAGE', 'PUGPIG');

  if (!pugpig_is_internal_user()) {
    header('HTTP/1.1 403 Forbidden');
    echo "Access denied to internal feed for external user from " . esc_html( getRequestIPAddress() );
    exit;
  }
  $status = 'all';
  $internal = true;
  header('X-Pugpig-Status: unpublished');

} else {
  $ttl = pugpig_get_feed_ttl();

  header('X-Pugpig-Status: published');
}

$atom_mode = false;
if (isset($_GET["atom"]) && $_GET["atom"] == 'true') {
  $atom_mode = true;
}

$newsstand_mode = false;
if (isset($_GET["newsstand"]) && $_GET["newsstand"] == 'true') {
  $newsstand_mode = true;
}

if (isset($_GET["region"])) {
  $extra_comments[] = "Region Filter: " . $_GET["region"];
}

// This might be needed for caching in the future
// $cache_key = ($internal ? "INT_" : "EXT_") . ($atom_mode ? "ATOM_" : "PACK_") . ($newsstand_mode ? "NEWS" : "OPDS");

$extra_comments[] = "Status: $status. Number of editions to include: " . pugpig_get_num_editions();

$editions = pugpig_get_editions($status, pugpig_get_num_editions());

$edition_ids = array();
$modified = null;
foreach ($editions as $edition) {
  if (pugpig_should_keep_edition_in_feed($edition)) {
    $edition_ids[] = $edition->ID;

    $atom_timestamp  = pugpig_get_page_modified($edition);
    if ($atom_mode) {
      $this_time = $atom_timestamp;
    } else {
      $package_timestamp = pugpig_get_edition_update_date(pugpig_get_edition($edition->ID), false);
      $this_time = max($package_timestamp, $atom_timestamp); // so cover changes etc. are picked up
    }

    if ($modified == NULL || $modified < $this_time) {
      $modified = $this_time;
    }
  }
}

pugpig_set_cache_headers($modified, $ttl);

$d = pugpig_get_opds_container($edition_ids, $internal, $atom_mode, $newsstand_mode, $extra_comments);

// Add any static OPDS entries to the feed
$entry_xml_string = pugpig_get_extra_opds_entries();

pugpig_add_static_entries_from_xml_text(
    $d,
    $entry_xml_string,
    'all feeds',
    $newsstand_mode);

$d->formatOutput = true;
$opds = $d->saveXML();

echo $opds; //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
