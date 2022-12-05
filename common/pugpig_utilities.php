<?php
/**
 * @file
 * Pugpig Utilities
 */
?><?php
/*

Licence:
==============================================================================
(c) 2011, Kaldor Holdings Ltd
This module is released under the GNU General Public License.
See COPYRIGHT.txt and LICENSE.txt

 */?><?php


function pugpig_get_standalone_version()
{
    $standalone_version = "2.3.16";

    return $standalone_version;
}

function pugpig_test_ping($host,$port=80,$timeout=6)
{
        $fsock = @fsockopen($host, $port, $errno, $errstr, $timeout);
        if (! $fsock) {
                return FALSE;
        } else {
                return TRUE;
        }
}

/************************************************************************
Genertic string helper functions (taken from PHP site)
************************************************************************/
if (!function_exists('startsWith')) {
    function startsWith($haystack, $needle)
    {
        $length = strlen($needle);

        return (substr($haystack, 0, $length) === $needle);
    }
}

if (!function_exists('endsWith')) {
    function endsWith($haystack, $needle)
    {
        $length = strlen($needle);
        $start  = $length * -1; //negative

        return (substr($haystack, $start) === $needle);
    }
}

function pugpig_get_array_from_comma_separate_string($v)
{
  if (isset($v) && !empty($v)) {
      $arr = explode(",", $v);
      $arr = array_map('trim', $arr);

      return array_filter($arr);
  }

  return array();
}

/**
 * Convert bytes to human readable format
 *
 * @param integer bytes Size in bytes to convert
 * @return string
 */
if (!function_exists('bytesToSize')){
    function bytesToSize($bytes, $precision = 2, $type = 'long'){
        return pugpig_bytestosize($bytes, $precision = 2, $type = 'long');
    }
}

function pugpig_bytestosize($bytes, $precision = 2, $type = 'long')
{
  $types = array(
    'long'  => array(' B', ' KB', ' MB', ' GB', ' TB'),
    'short' => array('B' , 'K'  , 'M'  , 'G'  , 'T')
    );

  $suffixes = $types[$type];

    $kilobyte = 1024;
    $megabyte = $kilobyte * 1024;
    $gigabyte = $megabyte * 1024;
    $terabyte = $gigabyte * 1024;

    if (($bytes >= 0) && ($bytes < $kilobyte)) {
        return $bytes . $suffixes[0];

    } elseif (($bytes >= $kilobyte) && ($bytes < $megabyte)) {
        return round($bytes / $kilobyte, $precision) . $suffixes[1];

    } elseif (($bytes >= $megabyte) && ($bytes < $gigabyte)) {
        return round($bytes / $megabyte, $precision) . $suffixes[2];

    } elseif (($bytes >= $gigabyte) && ($bytes < $terabyte)) {
        return round($bytes / $gigabyte, $precision) . $suffixes[3];

    } elseif ($bytes >= $terabyte) {
        return round($bytes / $terabyte, $precision) . $suffixes[4];
    } else {
        return $bytes . $suffixes[0];
    }
}

function pugpig_get_current_base_url()
{
  $s = ( !empty( $_SERVER["HTTPS"] ) && $_SERVER["HTTPS"] == "on") ? "s" : "";
  $protocol = substr(
    strtolower($_SERVER["SERVER_PROTOCOL"]),
    0,
    strpos($_SERVER["SERVER_PROTOCOL"], "/")
  ) . $s;
  $port = ($_SERVER["SERVER_PORT"] == "80" || $_SERVER["SERVER_PORT"] == "443" ) ? "" : (":" . $_SERVER["SERVER_PORT"]);

  return $protocol . "://" . $_SERVER['SERVER_NAME'] . $port;
}

function _ago($tm,$rcs = 0,$past=true)
{
    if (function_exists('pugpig_get_current_time')) {
        $cur_tm = pugpig_get_current_time();
    } else {
        $cur_tm = time();
    }

    $dif = $cur_tm-$tm;
    if (!$past) $dif = -$dif;
    $pds = array('second','minute','hour','day','week','month','year','decade');
    $lngh = array(1,60,3600,86400,604800,2630880,31570560,315705600);

    for($v = sizeof($lngh)-1; ($v >= 0)&&(($no = $dif/$lngh[$v])<=1); $v--); if($v < 0) $v = 0; $_tm = $cur_tm-($dif%$lngh[$v]);
        $no = floor($no);
        if($no != 1)
            $pds[$v] .='s';
        $x = sprintf("%d %s ",$no,$pds[$v]);
        if(($rcs == 1)&&($v >= 1)&&(($cur_tm-$_tm) > 0))
            $x .= time_ago($_tm);

        return $x;
}

function pugpig_add_static_entries_from_xml_text($doc, $xml_text, $description, $newsstand_mode) {
  if (empty($xml_text)) {
    return;
  }

  $attributes = array('xmlns="http://www.w3.org/2005/Atom"');

  if ($newsstand_mode) {
      $attributes[] = 'xmlns:news="http://itunes.apple.com/2011/Newsstand';
  }

  $namespaces = pugpig_get_extra_feed_namespaces();
  foreach($namespaces as $namespace => $namespace_url){
    $attributes[] = "${namespace}=\"${namespace_url}\"";
  }

  $attributes_text = implode(' ', $attributes);

  $test_xml_text = "<feed ${attributes_text}>\n${xml_text}\n</feed>";

  $domnode = $doc->getElementsByTagName('feed')->item(0);

  $test_fragment = $doc->createDocumentFragment();
  if (@$test_fragment->appendXML($test_xml_text)) {
    $comment = $doc->createComment(" Adding static entries for ${description} ");
    $domnode->appendChild($comment);
    foreach ($test_fragment->firstChild->childNodes as $child) {
      $domnode->appendChild($child->cloneNode(true));
    }
  } else {
    $comment = $doc->createComment(" Failed to add static entries for ${description}. Maybe it is not valid XML ");
    $domnode->appendChild($comment);
  }

  // reformat the xml
  $doc->preserveWhiteSpace = false;
  $doc->formatOutput = true;
  $doc->loadXML($doc->saveXML());
}
