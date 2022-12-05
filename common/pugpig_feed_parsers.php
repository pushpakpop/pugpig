<?php
/**
 * @file
 * Pugpig Feed Parsers
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
Parse and OPDS feed
************************************************************************/
function _pugpig_package_parse_opds($opds_body)
{

  $opds_ret = array();

  $message = check_xml_is_valid($opds_body);
  if (!empty($message)) {
    $opds_ret['failure'] = "Not Valid XML: $message";
    return $opds_ret;    
  }

  $editions = array();
  $feed_title = '';
  $feed_subtitle = '';

  if ($opds_body != '') {
    $atom = new XMLReader();

    $atom->XML($opds_body);
    while ($atom->read()) {
      if ($atom->localName == 'entry' && $atom->nodeType == XMLReader::ELEMENT) {

        $edition_cover = "";
        $edition_id = "";
        $edition_title = "";
        $edition_summary = "";
        $edition_type = "";
        $edition_url = "";
        $edition_updated = "";
        $edition_free = true;
        $edition_sample = false;
        $edition_draft = false;
        $edition_categories = array();

        while ($atom->read() && $atom->localName != 'entry') {

          // ID of an entry
          if ($atom->localName == 'id' && $atom->nodeType == XMLReader::ELEMENT) {
             $atom->read();
             $edition_id = $atom->value;
          }

          // ID of an entry
          if ($atom->localName == 'updated' && $atom->nodeType == XMLReader::ELEMENT) {
             $atom->read();
             $edition_updated = $atom->value;
          }

          // ID of an entry
          if ($atom->localName == 'title' && $atom->nodeType == XMLReader::ELEMENT) {
             $atom->read();
             $edition_title = $atom->value;
          }

          // ID of an entry
          if ($atom->localName == 'summary' && $atom->nodeType == XMLReader::ELEMENT) {
             $atom->read();
             $edition_summary = $atom->value;
          }

          // ID of an entry
          if ($atom->localName == 'draft' && $atom->nodeType == XMLReader::ELEMENT) {
             $atom->read();
             $edition_draft = true;
          }

          // Categories of an entry
          if ($atom->localName == 'category' && $atom->nodeType == XMLReader::ELEMENT) {
             $edition_categories[$atom->getAttribute('scheme')] = $atom->getAttribute('term');
          }

          // Links in an entry
          if ($atom->localName == 'link'  && $atom->nodeType == XMLReader::ELEMENT) {
               $lrel = $atom->getAttribute('rel');
               $ltype = $atom->getAttribute('type');
               $lurl = $atom->getAttribute('href');

               if ($lrel == 'http://opds-spec.org/image') {
                 $edition_cover = $lurl;
               }

               if ($lrel == 'http://opds-spec.org/acquisition' || $lrel == 'http://opds-spec.org/acquisition/buy') {
                 if ($ltype == 'application/pugpigpkg+xml') {
                  $edition_type = 'package';
                 } elseif ($ltype == 'application/atom+xml') {
                  $edition_type = 'atom';
                 } else {
                  $edition_type = 'Unknown';
                 }
                 $edition_url = $lurl;
               }

               if ($lrel == 'http://opds-spec.org/acquisition/buy') {
                $edition_free = false;
               }

               if ($lrel == 'http://opds-spec.org/acquisition/sample') {
                $edition_sample = true;
               }
          }

        }
        //print_r("Processed edition ".$edition_id." - " . $edition_title. " " . $edition_summary . "  <br />");
        $editions[$edition_id]['cover'] = $edition_cover;
        $editions[$edition_id]['title'] = $edition_title;
        $editions[$edition_id]['summary'] = $edition_summary;
        $editions[$edition_id]['url'] = $edition_url;
        $editions[$edition_id]['type'] = $edition_type;
        $editions[$edition_id]['free'] = $edition_free;
        $editions[$edition_id]['samples'] = $edition_sample;
        $editions[$edition_id]['draft'] = $edition_draft;
        $editions[$edition_id]['categories'] = $edition_categories;
        $editions[$edition_id]['updated'] = $edition_updated;

      } else {

        if ($atom->localName == 'title' && $atom->nodeType == XMLReader::ELEMENT) {
                  $atom->read();
                  $feed_title = $atom->value;
        }
        if ($atom->localName == 'subtitle' && $atom->nodeType == XMLReader::ELEMENT) {
                  $atom->read();
                  $feed_subtitle = $atom->value;
        }       }
    }
    $atom->close();
  }

  // print_r($editions);
  $opds_ret['title'] = $feed_title;
  if ($feed_subtitle != '') $opds_ret['title'] .= ' - ' . $feed_subtitle;
  $opds_ret['editions'] = $editions;

  return $opds_ret;

}

/************************************************************************
Parse the ATOM XML to extract the edition tag, the manifests, the HTML pages
************************************************************************/
function _pugpig_package_parse_atom($atom_xml)
{
  $atom_ret =  array();
  $html_urls =  array();
  $edition_tag = '';
  $edition_title = '';

  // list of pages for each manifest (which virtually always be a 1 to 1 mapping, but to be safe, we'll allow multiple)
  $manifest_pages = array();

  $contextualised_urls = array();
  $context = null;
  $page_id = null;

  if ($atom_xml != '') {
    $atom = new XMLReader();
    $atom->XML($atom_xml);
    while ($atom->read()) {
      if ($atom->localName == 'entry') {
        switch ($atom->nodeType) {
          case XMLReader::END_ELEMENT:
            $contextualised_urls[$page_id] = $context;
            foreach ($context['manifest_urls'] as $manifest_url) {
              if (empty($manifest_pages[$manifest_url])) {
                $manifest_pages[$manifest_url] = array($page_id);
              } else {
                $manifest_pages[$manifest_url][] = $page_id;
              }
            }
            break;
          case XMLReader::ELEMENT: 
            $context = array(
              'entry' => $atom->readOuterXML(),
              'manifest_urls' => array(),
              'html_urls' => array());
            break;
        }
      } elseif ($atom->localName == 'id') {
        if ($context && $atom->nodeType === XMLReader::ELEMENT) {
          $page_id = $atom->readString();
        } elseif ($atom->nodeType === XMLReader::ELEMENT) {
          $edition_tag = $atom->readString();
        }
      }  elseif ($atom->localName == 'title' && $edition_title == '') {
        $edition_title = $atom->readString();
      } elseif ($atom->localName == 'link') {
        $url = $atom->getAttribute('href');
        $rel = $atom->getAttribute('rel');
        $type = $atom->getAttribute('type');
        switch ($rel) {
          case 'related':
            if ($type == "text/cache-manifest") {
              $context['manifest_urls'][] = $url;
            }
            break;
          case 'alternate': 
            if ($type == "text/html") {
              $html_urls[] = $url; 
              $context['html_urls'][] = $url;
            }
            break;
        }
      }
    }
    $atom->close();
  }

  $atom_ret['edition_title'] = $edition_title;
  $atom_ret['edition_tag'] = $edition_tag;
  $atom_ret['manifest_urls'] = array_keys($manifest_pages);
  $atom_ret['manifest_pages'] = $manifest_pages;
  $atom_ret['html_urls'] = $html_urls;
  $atom_ret['contextualised_urls'] = $contextualised_urls;

  return $atom_ret;
}

/************************************************************************
Validation of XML
************************************************************************/
function _pugpig_package_get_asset_urls_from_manifest($manifest_contents, $entries = array(), $base_url, $mode = 'all')
{
  $active = false;
  $last_line_was_ad = false;
  if ($mode != 'theme') $active = true;

  $found_manifest_start = false;
  $lines = preg_split('/\n/m', $manifest_contents, 0, PREG_SPLIT_NO_EMPTY);
  foreach ($lines as $line) {

    // Temporary hacks to determine what is a theme asset
    // These will work with our Drupal and WordPress connector only
    // In the longer term, we need a better way to mark assets as Theme assets
    if (!$last_line_was_ad && startsWith($line, '# Theme assets')) {
      if ($mode == 'theme') $active = true;
      if ($mode == 'page')  $active = false;
    }
    if (startsWith($line, '# Ad Package Zip Contents') || startsWith($line, '# Package Zip Contents')) $last_line_was_ad = true;
    else  $last_line_was_ad = false;

    preg_match('/\s*([^#]*)/', $line, $matches);
    if (count($matches) > 1) {
      $m = trim($matches[1]);

      // Ignore all lines until we find the "CACHE MANIFEST one"
      // Can't do this as it is currently used to scan partial manifests too
      /*
      if ($m == "CACHE MANIFEST") $found_manifest_start = TRUE;
      if (!$found_manifest_start) {
        continue;
      }
      */

      if (!empty($m)
        && !in_array($m, $entries)
        && substr($m, 0, strlen('CACHE')) != 'CACHE'
        && substr($m, 0, strlen('NETWORK')) != 'NETWORK'
        && $m != '*') {
          if (!startsWith($m, "/")) {
            // We have a relative URL
            $m =   pugpig_strip_domain(url_to_absolute($base_url, $m));
          }
          if (!empty($m)) {
            if ($active) $entries[] = $m;
          }
        }
      }
  }
  return $entries;
}

/************************************************************************
Parse the atom feed nicely
************************************************************************/
function pugpig_parser_process_atom($atom) {

    $xml = new SimpleXMLElement($atom);
    $xml->registerXPathNamespace('atom', 'http://www.w3.org/2005/Atom');
    $x = $xml->xpath('/atom:feed');

    return $x;
}

/************************************************************************
Validation of XML
************************************************************************/
function check_xml_is_valid($xml_input)
{
  // Check that they are valid
  libxml_use_internal_errors(true);
  $ret = simplexml_load_string($xml_input);
  if ($ret == FALSE) {
    $errors = libxml_get_errors();
    $xml = explode("\n", $xml_input);

    $msg = "";
    foreach ($errors as $error) {
        $msg .= htmlspecialchars(display_xml_error($error, $xml)) . "<br />";
    }

    libxml_clear_errors();

    return ($msg);
  }

  return '';
}

/************************************************************************
************************************************************************/
function display_xml_error($error, $xml)
{
    $return  = $xml[$error->line - 1] . "\n";

    switch ($error->level) {
        case LIBXML_ERR_WARNING:
            $return .= "Warning $error->code: ";
            break;
         case LIBXML_ERR_ERROR:
            $return .= "Error $error->code: ";
            break;
        case LIBXML_ERR_FATAL:
            $return .= "Fatal Error $error->code: ";
            break;
    }

    $return .= trim($error->message) .
               "\n  Line: $error->line" .
               "\n  Column: $error->column";

    if ($error->file) {
        $return .= "\n  File: $error->file";
    }

    return "$return\n\n";
}
