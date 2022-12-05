<?php
/**
 * @file
 * Pugpig Feed Generation
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
Get the date with correct timezone
 * **********************************************************************/
function pugpig_get_date_default_timezone()
{
  if(function_exists('pugpig_get_date_timezone')) {
    $date_timezone = pugpig_get_date_timezone();
  }

  $ini_timezone = ini_get('date.timezone');
  if(empty($date_timezone) && !empty($ini_timezone)) {
    $date_timezone = ini_get('date.timezone');
  }

  if (empty($date_timezone)) {
    $date_timezone = 'UTC';
  }

  return $date_timezone;
}

function pugpig_date($format = 'UTC', $timestamp = 0)
{
  $original_date_default_timezone = date_default_timezone_get();
  date_default_timezone_set(pugpig_get_date_default_timezone());

  $date = date($format, $timestamp);

  date_default_timezone_set($original_date_default_timezone);

  return $date;
}

/************************************************************************
Output the date in RFC3339 format
 * **********************************************************************/
function pugpig_date3339($timestamp=0)
{
    if (!$timestamp) {
        $timestamp = time();
    }

    $date = pugpig_date(DATE_RFC3339, $timestamp);

    return $date;
}

/************************************************************************
Output the date in RFC2822 format
 * **********************************************************************/
function pugpig_date2822($timestamp=0)
{
    if (!$timestamp) {
        $timestamp = time();
    }
    $date = pugpig_date('r', $timestamp);

    return $date;
}

/************************************************************************
Output the date in Kindle format
 * **********************************************************************/
function pugpig_date_kindle($timestamp=0)
{
    if (!$timestamp) {
        $timestamp = time();
    }
    $date = pugpig_date('Y-d-m', $timestamp);

    return $date;
}

/************************************************************************
Take the domain off a URL
 * **********************************************************************/
function pugpig_strip_domain($path)
{
    $u = wp_parse_url($path);

    if (array_key_exists("path", $u)) {
      $output = $u["path"];
    } else {
      $output = "";
    }
    if (array_key_exists("query", $u)) {
      $output .= '?' . $u["query"];
    }

    // TODO: Do we care about ?query and #fragment?
    return $output;
}

/************************************************************************
* Take a manifest and add the CDN to all the assets
*************************************************************************/
function pupig_add_cdn_to_manifest_lines($manifest_contents, $cdn)
{
    $ret = '';
    $lines = preg_split('/\n/m', $manifest_contents, 0);
    foreach ($lines as $line) {
      if (!in_array($line, array('', '*', 'CACHE MANIFEST', 'CACHE', 'CACHE:', 'NETWORK','NETWORK:'))) {
        preg_match('/\s*([^#]*)/', $line, $matches);
        if (count($matches) > 1) {
          // Only include CDN prefix if the URL starts with a /
          if ($matches[1] != '*' && trim($matches[1]) != '' && startsWith($matches[1], '/'))
            $line = $cdn .  $line;
        }
      }
      $ret .= $line . "\n";
    }

    return $ret;
}

/************************************************************************
Try to make the URLs relative
 * **********************************************************************/
function pugpig_path_to_rel_url($path)
{
  return pugpig_strip_domain(pugpig_path_to_abs_url($path));
}

/************************************************************************
Container for the OPDS XML
 * **********************************************************************/
function pugpig_get_opds_container($edition_ids, $internal = false, $atom_mode = false, $newsstand_mode = false, $extra_comments = array())
{
  $d = new DomDocument('1.0', 'UTF-8');
  $feed = $d->createElement('feed');
  $feed->setAttribute('xmlns', 'http://www.w3.org/2005/Atom');

  if ($newsstand_mode) {
    $feed->setAttribute('xmlns:news', 'http://itunes.apple.com/2011/Newsstand');
  } else {
    $namespaces = pugpig_get_extra_feed_namespaces();
    foreach($namespaces as $namespace => $namespace_url){
      $feed->setAttribute($namespace, $namespace_url);
    }
  }

  $comment = ($atom_mode ? "Atom" : "Package");
  if ($internal) {
    $comment .= ' Internal Feed';
  } else {
    $comment .= ' External Feed';
  }
  if ($newsstand_mode) $comment .= " for Newsstand";

  $feed->appendChild($d->createComment(' ' . $comment . " - Generated: " . date(DATE_RFC822) . ' '));
  foreach ($extra_comments as $extra_comment) {
    $feed->appendChild($d->createComment(' ' . $extra_comment . ' '));
  }

  $feed->appendChild(newElement($d, 'id', pugpig_get_atom_tag('opds')));

  _pugpig_create_xml_link($d, $feed, array(
      'rel' => 'self',
      'type' => 'application/atom+xml;profile=opds-catalog;kind=acquisition',
      'href' => pugpig_self_link()
      ));

  if (function_exists('pugpig_get_opds_extras')) {
    $extras = pugpig_get_opds_extras();
    if (isset($extras['links'])) {
      foreach ($extras['links'] as $link) {
        _pugpig_create_xml_link($d, $feed, $link);
      }
    }

  if (!empty($extras['auth_endpoints'])) {
    $authorisation = $d->createElement('pp:authorisation','');
    $feed->appendChild($authorisation);
    foreach ($extras['auth_endpoints'] as $auth){
      $authorisation = pugpig_get_authorisationprovider($d, $auth, $authorisation);
    }
  }

    if (isset($extras['custom_categories'])) {
      foreach ($extras['custom_categories'] as $scheme => $values) {
          _pugpig_create_atom_category($d, $feed, $scheme, $values);
      }
    }
  }

  $author = $d->createElement('author');
  $author->appendChild($d->createElement('name', 'Pugpig'));
  $feed->appendChild($author);

  $feed->appendChild($d->createElement('title', 'All Issues'));
  $feed->appendChild($d->createElement('subtitle', $comment));

  $editions = array();
  $updated = 1; // 1 second into UNIX epoch (0 == current time?)

  foreach ($edition_ids as $key => $edition_id) {
    $editions[] = $edition_id;
    $edition_updated_atom = pugpig_get_edition_update_date(pugpig_get_edition($edition_id, true, !$atom_mode), true);
    $edition_updated_package = pugpig_get_edition_update_date(pugpig_get_edition($edition_id, true, !$atom_mode), false);

    $edition_updated = max($edition_updated_atom, $edition_updated_package);

    if ($edition_updated > $updated)
      $updated = $edition_updated;
  }

  $feed->appendChild(newElement($d, 'updated', pugpig_date3339($updated) ));

  foreach ($editions as $edition) {
    $edition = pugpig_get_edition($edition, true, !$atom_mode);
    if (!$atom_mode && array_key_exists('zip', $edition) && $edition['zip'] == '' && !$edition['is_pdf'])
      $feed->appendChild($d->createComment(' ' . ucfirst($edition['status']) . ' ' . pugpig_get_atom_tag($edition['key']) . ' does not have an up to date package '));
    else
      $feed->appendChild(pugpig_get_opds_entry($d, $edition, $internal, $atom_mode, $newsstand_mode));
  }

  $d->appendChild($feed);

  return $d;
}

function pugpig_get_extra_feed_namespaces()
{
  $namespaces = array(
    'xmlns:dcterms' => 'http://purl.org/dc/terms/',
    'xmlns:opds' => 'http://opds-spec.org/2010/catalog',
    'xmlns:app' => 'http://www.w3.org/2007/app',
    'xmlns:at' => 'http://purl.org/atompub/tombstones/1.0',
    'xmlns:pp' => 'http://pugpig.com/2014/atom/'
  );

  return $namespaces;
}
/************************************************************************
************************************************************************/
function pugpig_get_edition_update_date($edition, $atom_mode)
{
  // TODO: Ensure we get the edition object if we've just been given a key
  // The line below doesn't work - get's the wrong atom mode
  if ($atom_mode) {
    return $edition['modified'];
  } else {
    if (array_key_exists('is_pdf', $edition) && ($edition['is_pdf'])) {
      return $edition['pdf_modified'];
    } else {
      return $edition['packaged'];
    }
  }
}

function _pugpig_get_atom_tag_internal($key)
{
  if (function_exists('_pugpig_get_atom_tag')) {
    $id = _pugpig_get_atom_tag($key);
  } else {
    $id = pugpig_get_atom_tag($key);
  }

  return $id;
}

function _pugpig_create_xml_link(&$d, &$entry, $link_info, $comment=null)
{
  if (!empty($comment)) {
    $entry->appendChild($d->createComment($comment));
  }
  $link_atom = $d->createElement('link');
  $allowed_attributes = array('type', 'href', 'rel', 'title', 'dcterms:creator', 'icon', 'dcterms:modified');
  foreach ($allowed_attributes as $attribute) {
    if (isset($link_info[$attribute])) {
      $link_atom->setAttribute($attribute, $link_info[$attribute]);
    }
  }
  $entry->appendChild($link_atom);
}

/************************************************************************
An ODPS entry for an edition
We need the atom_mode to determine the updated date
************************************************************************/
function pugpig_get_opds_entry($d, $edition, $internal = false, $atom_mode = false, $newsstand_mode = false)
{
  // http://tools.ietf.org/html/rfc6721
  // We don't send tombstones to Newsstand
  if (!empty($edition['tombstone']) && $edition['tombstone']) {
      if ($newsstand_mode) {
        return $d->createComment("Edition '".$edition['title']."' has been deleted.");
      } else {
        $tombstone = $d->createElement('at:deleted-entry');
        $tombstone->setAttribute('ref', _pugpig_get_atom_tag_internal($edition['key']));
        $tombstone->setAttribute('when', pugpig_date3339(pugpig_get_edition_update_date($edition, $atom_mode)));
        $tombstone->appendChild(newElement($d, 'at:comment', "Edition '".$edition['title']."' has been deleted."));

        return $tombstone;
    }
  }

  $entry = $d->createElement('entry');

  $entry->appendChild(newElement($d, 'title', $edition['title']));
  $entry->appendChild(newElement($d, 'id', _pugpig_get_atom_tag_internal($edition['key'])));
  $entry->appendChild(newElement($d, 'updated', pugpig_date3339(pugpig_get_edition_update_date($edition, $atom_mode))));
  $publish_date = $edition['date'];
  if (count(explode("-", $publish_date)) == 1) $publish_date = $publish_date . "-01";

  $entry->appendChild(newElement($d, 'published', pugpig_date3339(strtotime($publish_date))));
  if ($newsstand_mode) {
    // $entry->appendChild(newElement($d, 'news:end_date', pugpig_date3339(pugpig_get_edition_update_date($edition, $atom_mode))));

    $summary = $edition['summary'];
    if (!empty($edition['newsstand_summary'])) $summary = $edition['newsstand_summary'];
    $summary = newElement($d, 'summary', $summary);
    $summary->setAttribute('type', 'text');
    $entry->appendChild($summary);

    // Cover
    $cover_art = $d->createElement('news:cover_art_icons');
    if (!empty($edition['newsstand_cover_art_icon_source'])) {
      $cover_art_icon = $d->createElement('news:cover_art_icon');
      $cover_art_icon->setAttribute('size', "SOURCE");
      $cover_art_icon->setAttribute('src', $edition['newsstand_cover_art_icon_source']);
      $cover_art->appendChild($cover_art_icon);
    }
    $entry->appendChild($cover_art);

  } else {

    if (!empty($edition['author'])) {
      $author = $d->createElement('author');
      $author->appendChild(newElement($d, 'name', $edition['author']));
      $entry->appendChild($author);
    }

    if (!empty($edition['date'])) {
      $entry->appendChild(newElement($d, 'dcterms:issued', $edition['date']));
    }

    // Show draft editions
    if ($edition['status'] != "published") {
      $appcontrol = $d->createElement('app:control');
      $appcontrol->appendChild($d->createElement('app:draft', 'yes'));
      $entry->appendChild($appcontrol);
    }

    if (isset($edition['custom_categories'])) {
      foreach ($edition['custom_categories'] as $scheme=>$values) {
          _pugpig_create_atom_category($d, $entry, $scheme, $values);
      }
    }

    $summary = newElement($d, 'summary', $edition['summary']);
    $summary->setAttribute('type', 'text');
    $entry->appendChild($summary);

    // Cover
    _pugpig_create_xml_link($d, $entry, array(
      'rel' => 'http://opds-spec.org/image',
      'type' => 'image/jpg',
      'href' => $edition['thumbnail']
      ));

    $comment = null;

    if (array_key_exists('is_pdf', $edition) && $edition['is_pdf']){

      $edition_link_info = array(
        'href'  => $edition['pdf_url'],
        'type'  => 'application/octet-stream'
        );

    } else {

      $edition_link_info = array(
        'href'  => $edition['url'],
        'type'  => array_key_exists('url_type', $edition) ? $edition['url_type'] : 'application/atom+xml',
        );
    }

    if (empty($edition['price']) || $edition['price'] == 'FREE') {
      // Free edition
      $comment = "Free edition";
      $edition_link_info['rel'] = 'http://opds-spec.org/acquisition';
    } elseif ($edition['status'] != "published") {
      // Act as if all are DRAFT editions free
      $comment = "Treating paid for draft edition as free";
      $edition_link_info['rel'] = 'http://opds-spec.org/acquisition';
    } else {
      // Paid for edition
      $comment = "Paid for edition";
      $edition_link_info['rel'] = 'http://opds-spec.org/acquisition/buy';
    }

    _pugpig_create_xml_link($d, $entry, $edition_link_info, $comment);
    $edition_alt_link_info = $edition_link_info;
    $edition_alt_link_info['rel'] = 'alternate';
    _pugpig_create_xml_link($d, $entry, $edition_alt_link_info);

    if (isset($edition['has_samples']) && $edition['has_samples'] && (!array_key_exists('is_pdf', $edition) || !$edition['is_pdf'])) {
      $edition_sample_link_info = $edition_alt_link_info;
      $edition_sample_link_info['rel'] = 'http://opds-spec.org/acquisition/sample';
      _pugpig_create_xml_link($d, $entry, $edition_sample_link_info, 'Has Samples');
    }

    // Enclosures
    if (isset($edition['links'])) {
      foreach ($edition['links'] as $link) {
        _pugpig_create_xml_link($d, $entry, $link);
      }
    }
  }

  return $entry;
}

/*
// No longer using this. A simple PAID or FREE replaces it
function pugpig_get_edition_prices($edition)
{
  $result = array();

  if (isset($edition) && array_key_exists('price', $edition) && isset($edition['price']) && $edition['price'] != '') {
    $prices = explode(',', $edition['price']);

    foreach ($prices as $price) {
      $price = trim($price);
      $currency = 'GBP';
      $amount = mb_substr($price, 1);

      switch (mb_substr($price, 0, 1)) {
        case '$': $currency = 'USD'; break;
        case '£': $currency = 'GBP'; break;
        case '€': $currency = 'EUR'; break;
        default:
          $currency = mb_substr($price, 0, 3);
          $amount = mb_substr($price, 3); // default
      }

      if (is_numeric($amount) && $amount > 0) {
        $result[] = array(
          'currency' => $currency,
          'value' => $amount
        );
      }
    }
  }

  return $result;
}
*/

/************************************************************************
The top level container for an edition ATOM feed
 * **********************************************************************/
function pugpig_get_atom_container($edition_id, $include_hidden = false,
  $search_term = null, $links = null, $content_filter = null) {
  $edition = pugpig_get_edition($edition_id, $include_hidden, true, $search_term, $content_filter);

  $d = new DomDocument('1.0', 'UTF-8');
  $feed = $d->createElement('feed');
  $feed->setAttribute('xmlns', 'http://www.w3.org/2005/Atom');
  $feed->setAttribute('xmlns:app', 'http://www.w3.org/2007/app');
  $feed->setAttribute('xmlns:dcterms', 'http://purl.org/dc/terms/');

  $atom_edition_id = _pugpig_get_atom_tag_internal($edition['key']);
  // Generate unqiue IDs for each search
  if ($search_term) {
    $atom_edition_id .= "." . time();
  }

  $feed->appendChild(newElement($d, 'id', $atom_edition_id));

  $comment = ' ' . ($include_hidden ? "Atom Including Hidden Files - " : "Atom Contents Feeds - ");
  $comment .= "Generated: " . date(DATE_RFC822) . ' ';

  $feed->appendChild($d->createComment($comment));

   if ($content_filter) {
     $feed->appendChild($d->createComment(" Filter: " . $content_filter . ' '));
   }

  _pugpig_create_xml_link($d, $feed, array(
    'rel' => 'self',
    'type' => 'application/atom+xml',
    'href' => pugpig_self_link()));

  // Enclosures
  if ($links) {
    foreach ($links as $link) {
      _pugpig_create_xml_link($d, $feed, $link);
    }
  }

  if ($search_term) {
    $feed->appendChild(newElement($d, 'title', "Search results for: '$search_term' - " . $edition['title']));
    $feed->appendChild(newElement($d, 'updated', pugpig_date3339()));
  } else {
    $feed->appendChild(newElement($d, 'title', $edition['title']));
    $feed->appendChild(newElement($d, 'updated', pugpig_date3339(pugpig_get_edition_update_date($edition, true))));
  }

  $author = $d->createElement('author');
  $author->appendChild($d->createElement('name', 'Pugpig'));
  $feed->appendChild($author);

  $all_pages = array();
  foreach ($edition['page_ids'] as $page_id) {
    $pages = pugpig_get_pages($page_id, $edition_id, $content_filter);
    $all_pages = array_merge($all_pages, $pages);
  }
  $all_pages = pugpig_post_process_pages($all_pages, $edition_id, $content_filter);

  foreach ($all_pages as $page) {
    if ($page['status'] == 'published') {
      // We only ever want published pages in these feeds
      $entry = pugpig_get_atom_entry($d, $page, $edition);
      $feed->appendChild($entry);
    }
  }

  $d->appendChild($feed);
  return $d;
}

function _pugpig_create_atom_category(&$d, &$entry, $scheme, $values)
{
  $values = (is_array($values) && !isset($values['term'])) ? $values : array($values);

  foreach ($values as $val) {
    $category = $d->createElement('category');
    $category->setAttribute('scheme', "http://schema.pugpig.com/$scheme");
    $label = "";

    if (is_array($val) && isset($val['term'])) {
      $term = $val['term'];
      if (isset($val['label'])) $label = $val['label'];
    } else {
      $term = $val;
    }

    $category->setAttribute('term', $term);
    if (!empty($label)) {
      $category->setAttribute('label', $label);
    }
    $entry->appendChild($category);
  }

}

function _pugpig_process_sound_to_link($sound_info)
{
  $sound_info['rel'] = 'enclosure';
  if (empty($sound_info['dcterms:creator']) && !empty($sound_info['creator'])) {
    $sound_info['dcterms:creator'] = $sound_info['creator'];
  }

  return $sound_info;
}

function pugpig_get_sounds_links($sounds_info)
{
  $sounds_links = array();
  if (!empty($sounds_info['href'])) {
    $sounds_info = array($sounds_info);
  }

  if (isset($sounds_info) && count($sounds_info)>0) {
    $sounds_links = array_map('_pugpig_process_sound_to_link', $sounds_info);
  }

  return $sounds_links;
}

/************************************************************************
An ATOM entry for a post in an edition
 * **********************************************************************/
function pugpig_get_atom_entry($d, $page, $edition)
{
  $entry = $d->createElement('entry');

  $entry->appendChild(newElement($d, 'title', wp_strip_all_tags($page['title'])));

  $id = $page['id'];
  if (isset($page['id_prefix'])) {
    // we are explicitly setting the id_prefix ourselves, so don't add the atom tag
    $id = $page['id_prefix'] . $id;
  } else {
    $id = pugpig_get_atom_tag('page-' . $id);
  }
  $entry->appendChild(newElement($d, 'id', $id));

  if (empty($page['modified'])) $page['modified'] = time();
  $entry->appendChild(newElement($d, 'updated', pugpig_date3339( $page['modified'] )));

  if (empty($page['date'])) $page['date'] = time();
  $entry->appendChild(newElement($d, 'published', pugpig_date3339( $page['date'] )));

  // Author
  if (isset($page['author']) && !empty($page['author'])) {
    $page_authors = $page['author'];
    $authors = is_array($page_authors) ? $page_authors : array($page_authors);
    foreach ($authors as $author) {
      $author_element = $d->createElement('author');
      $author_element->appendChild($d->createElement('name', $author));
      $entry->appendChild($author_element);
    }
  }

  // Show draft editions
  if ($page['status'] != "published") {
    $appcontrol = $d->createElement('app:control');
    $appcontrol->appendChild($d->createElement('app:draft', 'yes'));
    $entry->appendChild($appcontrol);
  }

  $summary = newElement($d, 'summary', $page['summary']);
  $summary->setAttribute('type', 'text');
  $entry->appendChild($summary);

  // Categories should be first for the default client ToC to work normally
  if (isset($page['categories'])) foreach ($page['categories'] as $cat) {
    _pugpig_create_atom_category($d, $entry, 'section', $cat);
  }

  // access
  if (isset($page['access'])) {
    _pugpig_create_atom_category($d, $entry, 'access', $page['access']);
  }

  // Page Type
  if (empty($page['type'])) $page['type'] = 'Page';
  _pugpig_create_atom_category($d, $entry, 'pagetype', $page['type']);

  // Level
  if (isset($page['level'])) {
    if (empty($page['level'])) {
      $page['level'] = 1;
    }
    _pugpig_create_atom_category($d, $entry, 'level', $page['level']);
  }

  if (isset($page['custom_categories'])) {
    foreach ($page['custom_categories'] as $scheme=>$val) {
        _pugpig_create_atom_category($d, $entry, $scheme, $val);
    }
  }

  _pugpig_create_xml_link($d, $entry, array(
    'rel' => 'related',
    'type' => 'text/cache-manifest',
    'href' => $page['manifest']
    ));

  _pugpig_create_xml_link($d, $entry, array(
    'rel' => 'alternate',
    'type' => 'text/html',
    'href' => $page['url']
    ));

  // Take the sharing link from the edition if it isn't on the page
  $sharing_link = '';
  if (isset($page['sharing_link'])) $sharing_link = $page['sharing_link'];
  if ($sharing_link == '' && isset($edition['sharing_link'])) $sharing_link = $edition['sharing_link'];

  if (!empty($sharing_link)) {
    _pugpig_create_xml_link($d, $entry, array(
      'rel' => 'bookmark',
      'type' => 'text/html',
      'href' => $sharing_link
      ));
  }

  // Enclosures
  if (isset($page['links'])) {
    foreach ($page['links'] as $link) {
      _pugpig_create_xml_link($d, $entry, $link);
    }
  }

  return $entry;
}

function pugpig_self_link()
{
  $serverrequri = pugpig_request_uri();

  if (!isset($serverrequri)) {
    $serverrequri = $_SERVER['PHP_SELF'];
  }

  return pugpig_get_current_base_url() . $serverrequri;
}

/************************************************************************
 * **********************************************************************/
function pugpig_abs_link($pugpig_path, $cdn = "")
{
  $base = base_path();
  // If it starts with /<base>/ then we shouldn't be adding the base
  if (substr($pugpig_path, 0, strlen($base)) == $base)
    $base = '';
  else
    $pugpig_path = trim($pugpig_path, "/"); // base has suffix "/" so remove prefix from path if it exists

  $cdn = trim($cdn, "/");
  $serverrequri = pugpig_request_uri();

  if (!isset($serverrequri)) {
    $serverrequri = $_SERVER['PHP_SELF'];
  }

  $s = ( !empty( $_SERVER["HTTPS"] ) && $_SERVER["HTTPS"] == "on") ? "s" : "";
  $protocol = substr(
    strtolower($_SERVER["SERVER_PROTOCOL"]),
    0,
    strpos($_SERVER["SERVER_PROTOCOL"], "/")
  ) . $s;
  $port = ($_SERVER["SERVER_PORT"] == "80") ? "" : (":" . $_SERVER["SERVER_PORT"]);

  if ($cdn != "") {
   return  $cdn . $base . $pugpig_path;
  }

  return ($protocol . "://" . $_SERVER['SERVER_NAME'] . $port . $base . $pugpig_path);
}

/************************************************************************
The top level feed for the Kindle RSS
 * **********************************************************************/
function pugpig_get_rss_root($edition_id)
{
  $edition = pugpig_get_edition($edition_id, false);

  $d = new DomDocument('1.0', 'UTF-8');
  $feed = $d->createElement('rss');
  $feed->setAttribute('version', '2.0');

  $channel = $d->createElement('channel');
  $feed->appendChild($channel);

  $channel->appendChild(newElement($d, 'title', $edition['title']));
  $channel->appendChild(newElement($d, 'link', pugpig_self_link()));
  $channel->appendChild(newElement($d, 'pubDate', pugpig_date_kindle(pugpig_get_edition_update_date($edition, true))));

  $item = null;

  foreach (pugpig_get_kindle_page_array($edition) as $page) {
    if ($page['level'] == 1) {
      $item = $d->createElement('item');
      $abs_path = pugpig_abs_link('editions/' .pugpig_get_atom_tag($edition['key']) . '/data/' . $page['id'] . '/kindle.rss');
      $item->appendChild(newElement($d, 'link', $abs_path));
      // $channel->appendChild($item);
    }
    // If we have a Level 1 node, attach it once we know we have a child
    // Having a section without any children breaks everything
    if ($page['level'] > 1 && $item != null) {
      // print_r($page['title']);
      $channel->appendChild($item);
      $item = null;
    }
  }

  $d->appendChild($feed);

  return $d;
}

/************************************************************************
Section feed for the kindle RSS
* **********************************************************************/
function pugpig_get_rss_section($edition_id, $nid)
{
  // print_r('pugpig_get_rss_section(' . $edition_id . ',' . $nid . ')');

  $edition = pugpig_get_edition($edition_id, false);
  $section = pugpig_get_page($nid);

  $d = new DomDocument('1.0', 'UTF-8');
  $feed = $d->createElement('rss');
  $feed->setAttribute('version', '2.0');

  $channel = $d->createElement('channel');
  $feed->appendChild($channel);

  $channel->appendChild(newElement($d, 'title', $section['title']));
  $in_section = false;

  foreach (pugpig_get_kindle_page_array($edition) as $page) {

    if ($page['id'] == $nid && $page['level'] == 1 && !$in_section) {
      $in_section = true;

    } else {

      if ($page['level'] == 1) {
        // Bail when we hit the section higher level page
        $in_section = false;
      } elseif ($in_section) {
        $item = $d->createElement('item');
        $abs_path = pugpig_abs_link('editions/' .pugpig_get_atom_tag($edition['key']) . '/data/' . $page['id'] . '/kindle.html');
        $item->appendChild(newElement($d, 'link', $abs_path));
        $channel->appendChild($item);
      }

    }

 }

  $d->appendChild($feed);

  return $d;
}

/************************************************************************
Gets all files that match a pattern
 * **********************************************************************/
// Get all files in a directory and all of its children
if ( ! function_exists('glob_recursive')) {
    // Does not support flag GLOB_BRACE
    function glob_recursive($pattern, $flags = 0)
    {
        $files = glob($pattern, $flags);

         // Ignore FALSE if open_basedir is set
        if ($files === FALSE && ini_get('open_basedir')) $files = array();

        if ( $dirs = glob(dirname($pattern).'/*', GLOB_ONLYDIR|GLOB_NOSORT) ) {

          // Ignore FALSE if open_basedir is set
          if ($dirs === FALSE && ini_get('open_basedir')) $dirs = array();

          foreach ($dirs as $dir) {
            $files = array_merge($files, glob_recursive($dir.'/'.basename($pattern), $flags));
          }
        }

        return $files;
    }
}

/************************************************************************
Gets all the files in a directory
 * **********************************************************************/
function _pugpig_directory_get_files($directory)
{
  if (substr($directory, -1) != '/')
    $directory = $directory . '/';

  $match = $directory . "*.*";

  $f = glob_recursive($match, GLOB_ERR);
  // print_r(implode($f, "<br />")); exit();
  return $f;
}

/************************************************************************
Generate a fragment of a manifest file for all static assets in the current
theme
 * **********************************************************************/
function pugpig_theme_manifest_string($theme_path, $theme_dir, $theme_name = '', $exclude_paths = array(), $theme_version = null)
{
  $cache = array();

  // Normalise the slashes
  $theme_dir = str_replace(DIRECTORY_SEPARATOR, '/', $theme_dir);
  if (substr($theme_dir, -1) != '/') {
    $theme_dir = $theme_dir . '/';
  }

  if ($theme_path != '' && substr($theme_path, -1) != '/') {
    $theme_path = $theme_path . '/';
  }

  $theme_comment = "# Theme assets: ";
  if (!empty($theme_name)) {
    $theme_comment.= $theme_name;
  }
  if (!empty($theme_version)) {
    $theme_comment.= " ($theme_version)";
  }
  $cache[] = $theme_comment;

  // Get all the static assets for the theme
  $separator = (substr($theme_dir, -1) == '/') ? '' : '/';
  // array_push($cache, "# From Path: " . $theme_path . "\n");
  // array_push($cache, "# From Dir: " . $theme_dir . "\n");


  $files = _pugpig_directory_get_files($theme_dir);

  if (!$files) {
    $cache[] = "# ERROR: Failed to read $theme_dir";
  }
  $cache[] = "# Total Directory File Count: " . count($files);

  $c = 0;
  if ($files) {
    foreach ($files as $file) {
      if (!is_dir($file) && !strpos($file, '/.svn/')) {
        if ($file != "./manifest.php" && substr($file, 0, 1) != ".") {
          //$stamp = '?t=' . $file->getMTime();
         //$clean_path = str_replace($file, rawurlencode($file), $file) ;
         $clean_path = str_replace(DIRECTORY_SEPARATOR, '/', $file);

         $clean_url = str_replace($theme_dir, $separator, $clean_path);
         $clean_url = str_replace(DIRECTORY_SEPARATOR, '/', $clean_url);

         if (!in_array($clean_url, $exclude_paths) && isAllowedExtension($file)) {
             $parts = explode("/", $theme_path . $clean_url);
             $parts = array_map("rawurlencode", $parts);
             $clean = implode("/", $parts);
             $cache[] = $clean;
             $c++;
          } else {
             // array_push($cache, "# Skipped: " . $theme_path . $clean_url . "\n");
          }
        }
      }
    }

  }

  $cache[] = "# Got $c assets before custom filtering";
  $cache[] = "\n";

  return implode("\n", $cache);
}

/*
 * http://api.drupal.org/api/drupal/includes--bootstrap.inc/function/request_uri/7
 */
function pugpig_request_uri()
{
  if (isset($_SERVER['REQUEST_URI'])) {
    $uri = $_SERVER['REQUEST_URI'];
  } else {
    if (isset($_SERVER['argv'])) {
      $uri = $_SERVER['SCRIPT_NAME'] . '?' . $_SERVER['argv'][0];
    } elseif (isset($_SERVER['QUERY_STRING'])) {
      $uri = $_SERVER['SCRIPT_NAME'] . '?' . $_SERVER['QUERY_STRING'];
    } else {
      $uri = $_SERVER['SCRIPT_NAME'];
    }
  }
  // Prevent multiple slashes to avoid cross site requests via the Form API.
  $uri = '/' . ltrim($uri, '/');

  return $uri;
}

function newElement($d, $name, $value)
{
  $element = $d->createElement($name);
  $element->appendChild($d->createTextNode($value));

  return $element;
}

function pugpig_get_authorisationprovider($d, $auth, $authorisation){

  $authorisationprovider = $d->createElement('pp:authorisationprovider');
  if (isset($auth['name'])){
    $name = $auth['name'];
    $authorisationprovider->setAttribute('name', $name);
  }
  $authorisation->appendChild($authorisationprovider);

  $signin = $d->createElement('pp:endpoint');
  $signin->setAttribute('type', 'signin');
  $signin->setAttribute('method', 'POST');
  $signin->setAttribute('template', $auth['signin']);
  $authorisationprovider->appendChild($signin);

  $verify = $d->createElement('pp:endpoint');
  $verify->setAttribute('type', 'verify');
  $verify->setAttribute('method', 'POST');
  $verify->setAttribute('template', $auth['verify']);
  $authorisationprovider->appendChild($verify);

  $editioncredentials = $d->createElement('pp:endpoint');
  $editioncredentials->setAttribute('type', 'editioncredentials');
  $editioncredentials->setAttribute('method', 'POST');
  $editioncredentials->setAttribute('template', $auth['editioncredentials']);
  $authorisationprovider->appendChild($editioncredentials);

  if (isset($auth['renewtoken'])){
    $renewtoken = $d->createElement('pp:endpoint');
    $renewtoken->setAttribute('type', 'renewtoken');
    $renewtoken->setAttribute('method', 'POST');
    $renewtoken->setAttribute('template', $auth['renewtoken']);
    $authorisationprovider->appendChild($renewtoken);
  }

  return $authorisation;
}
