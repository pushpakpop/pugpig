<?php
/**
 * @file
 * Pugpig Packager
 */
?><?php
/*

Licence:
==============================================================================
(c) 2011, Kaldor Holdings Ltd
This module is released under the GNU General Public License.
See COPYRIGHT.txt and LICENSE.txt

 */?><?php

include_once "pugpig_packager_helpers.php";
include_once "pugpig_interface.php";
include_once "pugpig_utilities.php";
include_once "pugpig_manifests.php";
include_once "pugpig_feed_parsers.php";
include_once "multicurl.php";
include_once "url_to_absolute/url_to_absolute.php";
include_once "url_to_absolute/add_relative_dots.php";

interface IPackagedFileAllocator
{
    public function allocatePageFilesToBuckets($html_files, $manifest_files, $manifest_contents, $context_xml);
    public function allocateAtomFileToBuckets($atom_files);
    public function finaliseBuckets();
    public function bucketOnCDN($bucket_name);
    public function describeAllocator();
    public function setVerbose($verbose);
}

abstract class PackagedFileAllocator implements IPackagedFileAllocator {

  protected $buckets = array();
  protected $verbose = true;

  public function putFileInBuckets($filename_src, $filename_dest, $bucket_names) {
    if (empty($bucket_names)) {
      echo "<br><strong> " . esc_url( $filename_src ) . " has not been allocated to a bucket</strong>\n";
    } else {
      foreach ($bucket_names as $bucket_name) {
        $this->buckets[$bucket_name][$filename_src] = $filename_dest;
        if ($this->verbose) {
          echo "<br><strong>" . esc_html($bucket_name) . "</strong>: adding [" . esc_url( $filename_src ) . "] => [ " . esc_html($filename_dest) . "]";
        }
      }
    }
    return !empty($bucket_names);
  }

  public function getBuckets() {
    return $this->buckets;
  }

  public function finaliseBuckets() {
  }

  public function describeAllocator() {
    return '';
  }

  public function setVerbose($verbose) {
    $this->verbose = $verbose;
  }
}


class PackagedFileAllocatorOriginal extends PackagedFileAllocator {

  public function __construct() {
    $this->buckets = array(
      'html' => array(),
      'assets' => array());
  }

  public function allocatePageFilesToBuckets($html_files, $manifest_files, $manifest_contents, $context_xml) {
    $this->buckets['html'] = array_merge($this->buckets['html'], $html_files,  $manifest_files);
    $this->buckets['assets'] = array_merge($this->buckets['assets'], $manifest_contents);
  }

  public function allocateAtomFileToBuckets($atom_files) {
    $this->buckets['html'] = array_merge($this->buckets['html'], $atom_files);
  }

  public function bucketOnCDN($bucket_name) {
    return $bucket_name === 'assets';
  }

  public function describeAllocator() {
    return <<<BLOCK_HTML_PACKAGE_ORIGINAL_RULES
  <br>
  <em>
    <strong>Bucket Rules</strong>: Original
    <ol>
      <li><strong>html</strong>: html files, manifest files and the atom file (not to be put on cdn)</li>
      <li><strong>assets</strong>: remaining files (to be put on cdn if set)</li>
    </ol>
  </em>
BLOCK_HTML_PACKAGE_ORIGINAL_RULES;
  }
}

class PackagedFileAllocatorBySection extends PackagedFileAllocator {

  protected $atomFiles = null;

  public function allocatePageFilesToBuckets($html_files, $manifest_files, $manifest_contents, $context_xml) {
    $xml = simplexml_load_string($context_xml);
    $xml->registerXPathNamespace('atom', 'http://www.w3.org/2005/Atom');
    $sections = $xml->xpath('atom:category[@scheme="http://schema.pugpig.com/section"]/@term');
    if (count($sections)===0) {
        $sections = array(null);
    }
    foreach ($sections as $section) {
      $this->allocatePageFilesForSection((string)$section, $manifest_contents, $manifest_files, $html_files);
    }
  }

  protected function allocatePageFilesForSection($section, $manifest_contents, $manifest_files, $html_files) {
    $bucket_name = self::slugify((string)$section);
    if (empty($this->buckets[$bucket_name])) {
      $this->buckets[$bucket_name] = array();
    }
    $this->buckets[$bucket_name] = array_merge($this->buckets[$bucket_name],
      $manifest_contents,
      $manifest_files,
      $html_files);
  }

  public function allocateAtomFileToBuckets($atom_files) {
    $this->atomFiles = $atom_files;
  }

  protected function getBucketNameForAtomFiles() {
    $bucket_name = null;
    if (count($this->buckets)>0) {
      $bucket_names = array_keys($this->buckets);
      $bucket_name = $bucket_names[0];
    }
    return $bucket_name;
  }

  public function finaliseBuckets() {
    $atom_files_bucket_name = $this->getBucketNameForAtomFiles();
    if (!empty($atom_files_bucket_name)) {
      $this->buckets[$atom_files_bucket_name] = array_merge($this->buckets[$atom_files_bucket_name], $this->atomFiles);
    }
  }

  public function bucketOnCDN($bucket_name) {
    return true;
  }

  public function describeAllocator() {
    return <<<BLOCK_HTML_PACKAGE_SECTION_RULES
  <br>
  <em>
    <strong>Bucket Rules</strong>: By Section
    <ul>
      <li>Allocate all files relating to all pages in a section in a bucket per section.</li>
      <li>The atom file gets put in the first bucket.</li>
    </ul>
  </em>
BLOCK_HTML_PACKAGE_SECTION_RULES;
  }

  public static function slugify($text) {
    // e.g. over all '_Hello & Wörld_' becomes 'hello_world'

    // transliterate from utf-8 to us-ascii
    // e.g. '_Hello & Wörld_' becomes '_Hello & W"orld_'
    $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);

    // change spaces and multiple spaces together to a single underscore
    // then keep any letters, digits and underscores and remove all other characters
    // e.g. '_Hello & W"orld_' becomes '_Hello__World_'
    $text = preg_replace(array('/\s+/','/[^\\pL\d_\s]+/u'), array('_', ''), $text);

    // change to lowercase
    // e.g. '_Hello__World_' becomes '_hello__world_'
    $text = strtolower($text);

    // coalesce multiple underscores next to each other into a single underscore
    // e.g. '_hello__world_' becomes '_hello_world_'
    $text = preg_replace('/_+/', '_', $text);

    // remove underscores from the start and end
    // e.g. '_hello_world_' becomes 'hello_world'
    $text = trim($text, '_');

    return $text;
  }
}


function _package_url($url, $base)
{
  if (substr($url, 0, 4) != 'http' && substr($url, 0, 1) != '/')
    return $base . $url;
  else
    return $url;
}

function _print_progress_bar($length)
{
  for ($n=0; $n<$length; $n++)
    print ( intval($n) % 10 == 9 ? '.' : intval( $n ) % 10 + 1);
  print '<br />';
}

function _pugpig_package_url_remove_domain($url)
{
  // Strip off domain
  $colon_pos = strpos($url, '://');
  if ($colon_pos > 0)
    $url = '/' . substr($url, strpos($url, '/', $colon_pos + 3) + 1);

  return $url;
}

function _pugpig_package_item_url($url, $base, $domain)
{
  if (strpos($url, '://') > 0)
    return $url;
  elseif (substr($url, 0, 1) === '/')
    return $domain . $url;
  else
    return $base . $url;
}

function _package_rmdir($dir) { // Recursive directory delete
   if (is_dir($dir)) {
     $objects = scandir($dir);
     foreach ($objects as $object) {
       if ($object != "." && $object != "..") {
         if (filetype($dir."/".$object) == "dir") {
           _package_rmdir($dir."/".$object);
         } else {
           //print_r($dir."/".$object . '<br />');
           $ret = unlink($dir."/".$object);
           if (!$ret) {
             // print_r("Failed to delete file: " . $dir ."/".$object . '<br />');
           }
         }
       }
     }
     reset($objects);
     if (!rmdir($dir)) {
       // print_r("Failed to remove directory: " . $dir  . '<br />');
     };
   }
 }

/************************************************************************
Takes the name of a zip file, and an array of files in the format
src => dest
************************************************************************/
function _package_zip($zip_path, $files)
{
  $zip = new ZipArchive();
  if ($zip->open($zip_path, ZipArchive::CREATE) === TRUE) {
    // _print_immediately('Creating archive: ' . $zip_path . '');
    foreach ($files as $src => $dest) {
      // _print_immediately($src . ' -> ' . $dest . '<br>');
      $zip->addFile($src, $dest);
    }
    $zip->close();
  } else {
    // _print_immediately('Failed to create ' . $zip_path . ' !<br />');
  }
}

/************************************************************************
************************************************************************/
function _package_edition_package_timestamp($edition_tag)
{
  $timestamp = null;
  $base = _package_final_folder();
  $contents = _package_edition_package_contents($base, $edition_tag);
  if (isset($contents['xml_timestamp'])) {
    $timestamp = $contents['xml_timestamp'];
  }
  return $timestamp;
}

/************************************************************************
************************************************************************/
function _package_edition_package_size($edition_tag, $precision=2, $type='long')
{
  return pugpig_bytestosize(_package_edition_package_size_raw($edition_tag), $precision, $type);
}

function _package_edition_package_size_raw($edition_tag)
{
  $base = _package_final_folder();
  $contents = _package_edition_package_contents($base, $edition_tag);
  $size = 0;
  foreach ($contents as $key=>$value) {
    if (endsWith($key, '_size')) {
      $size += $value;
    }
  }

  return $size;
}

function _package_edition_get_package_xml_filename($base, $edition_tag, $timestamp) {
  $filename = "${base}${edition_tag}-package-${timestamp}.xml";
  return _package_get_most_recent_file($filename);
}

/************************************************************************
************************************************************************/
function _package_edition_package_contents($base, $edition_tag, $timestamp = '*')
{
  $contents = array();

  // Get the XML file
  $package_xml_filename = _package_edition_get_package_xml_filename($base, $edition_tag, $timestamp);
  if ($package_xml_filename != null) {
    $contents['xml_timestamp'] = filemtime($package_xml_filename);
    if ($timestamp == '*') {
      $timestamp = str_replace($base . $edition_tag . '-package-', '', $package_xml_filename);
      $timestamp = str_replace('.xml', '', $timestamp);
    }

    $package_xml = simplexml_load_file($package_xml_filename);
    $parts = $package_xml->xpath('/package/part');

    // see if we are just 'html' and 'asset' names
    $is_original = true;
    foreach ($parts as $part) {
      if ($part['name']!='html' && $part['name']!='assets') {
        $is_original = false;
        break;
      }
    }

    foreach ($parts as $part) {
      $name = (string)$part['name'];
      if ($is_original) {
        $is_cdn = $name==='assets';
      } else {
        //todo: any better way we could do this, say by recording the info in a(nother?) file
        $is_cdn = preg_match('/https?:\/\//i', (string)$part['src']);
      }
      $contents = _package_edition_package_contents_entry($contents, $name, "${base}${edition_tag}-${name}-${timestamp}*.zip", $base, $is_cdn);
    }
  }

  return $contents;
}

/*
  2014-02-14, Carlos, sub-zip changes: Refactored the collection format to
  allow for multiple ZIPs of each type, by having a sub-collection for type.
  We still return overall size for compatibility with existing code.
*/
function _package_edition_package_contents_entry($contents, $type, $wildcard, $base, $is_cdn)
{
  $iterator = new GlobIterator($wildcard, FilesystemIterator::KEY_AS_PATHNAME);
  $entry = array();
  $total_size = 0;
  foreach ($iterator as $item) {
    $file = $iterator->key();
    $zip = array();
    $zip['archive'] = basename($file);
    $zip['timestamp'] = file_exists($file) ? filemtime($file) : null;
    $size = file_exists($file) ? filesize($file) : 0;
    $zip['size'] = $size;
    $total_size += $size;
    $zip['url'] = substr($file, strlen($base));
    array_push($entry, $zip);
  }
  $contents[$type . '_size'] = $total_size;
  $contents[$type . '_is_cdn'] = $is_cdn;
  $contents[$type] = $entry;

  return $contents;
}

function _package_edition_package_list_xml($base, $edition_tag, $url_root = '', $cdn = '', $output_type = 'string', $timestamp = '*', $content_xml_url = "content.xml") {
  return _package_edition_package_list_xml_using_files($base, $edition_tag, $url_root, $cdn, $output_type, $timestamp, $content_xml_url);
}

function _package_edition_package_list_xml_using_files($base, $edition_tag, $url_root = '', $cdn = '', $output_type = 'string', $timestamp = '*', $content_xml_url = "content.xml")
{
  $content = _package_edition_package_contents($base, $edition_tag, $timestamp);

  $bucket_infos = array();

  $url_root_without_domain = pugpig_strip_domain($url_root);
  foreach ($content as $key=>$values) {
    if ($key === 'xml_timestamp'
        || endsWith($key, '_size')
        || endsWith($key, '_is_cdn')) {
      continue;
    }
    $bucket_infos[$key] = array(
      'zips' => array(),
      'is_cdn' => empty($content[$key.'_is_cdn'])?false:$content[$key.'_is_cdn']);
    foreach ($values as $value) {
      $bucket_infos[$key]['zips'][] = $value['archive'];
    }
  }

  return _package_edition_package_list_xml_core($base, $edition_tag, $bucket_infos, $url_root, $cdn, $output_type, $timestamp, $content_xml_url);
}

/************************************************************************
$content_xml_url is the location of the ATOM feed relative to the location
of the package.xml file
************************************************************************/
function _package_edition_package_list_xml_using_buckets($base, $edition_tag, $bucket_allocator, $buckets_zips, $url_root = '', $cdn = '', $output_type = 'string', $timestamp = '*', $content_xml_url = "content.xml")
{
  $bucket_infos = array();
  foreach ($buckets_zips as $bucket_name=>$bucket_zips) {
    $bucket_infos[$bucket_name] = array(
      'zips' => $bucket_zips,
      'is_cdn' => $bucket_allocator->bucketOnCDN($bucket_name));
  }
  return _package_edition_package_list_xml_core($base, $edition_tag, $bucket_infos, $url_root, $cdn, $output_type, $timestamp, $content_xml_url);
}

/************************************************************************
$content_xml_url is the location of the ATOM feed relative to the location
of the package.xml file
************************************************************************/
function _package_edition_package_list_xml_core($base, $edition_tag, $bucket_infos, $url_root = '', $cdn = '', $output_type = 'string', $timestamp = '*', $content_xml_url = "content.xml")
{
  $d = new DomDocument('1.0', 'UTF-8');
  $d->formatOutput = true;

  $package = $d->createElement('package');
  $package->setAttribute('root', $content_xml_url);
  $package->appendChild($d->createComment("Generated: " . date(DATE_RFC822)));

  $url_root_without_domain = pugpig_strip_domain($url_root);
  $total_size = 0;
  foreach ($bucket_infos as $bucket_name=>$bucket_info) {
    $bucket_cdn = $bucket_info['is_cdn'] ? $cdn : '';
    $total_size += _package_edition_package_list_xml_part($package, $d, $url_root_without_domain, $bucket_info['zips'], $bucket_name, $bucket_cdn, $base);
  }
  $package->setAttribute('size', $total_size);

  $d->appendChild($package);

  $out = null;
  if ($output_type === 'string') {
    $out = $d->saveXML();
  } else {
    $d->save($output_type);
    $out = 'string';
  }
  return $out;
}

/*
  2014-02-14, Carlos, sub-zip changes: Creates package XML using the updated
  collections that allow for multiple ZIPs per type.
*/
function _package_edition_package_list_xml_part($package, $d, $url_root, $bucket_zips, $bucket_name, $cdn, $base)
{
  $total_size = 0;
  foreach ($bucket_zips as $bucket_zip) {
    $filename = $base.$bucket_zip;
    $file_size = 0;
    $file_timestamp = null;
    if (file_exists($filename)) {
      $file_size = filesize($filename);
      $total_size += $file_size;
      $file_timestamp = filemtime($filename);
    }

    $cdn_join = '';
    if (!empty($cdn) && substr($cdn, -1)!='/' && substr($url_root, 0, 1)!='/') {
      $cdn_join = '/';
    }

    $part = $d->createElement('part');
    $part->setAttribute('name', $bucket_name);
    $part->setAttribute('src', $cdn . $cdn_join . $url_root . $bucket_zip);
    $part->setAttribute('size', $file_size);
    $part->setAttribute('modified', gmdate(DATE_ATOM, $file_timestamp));
    $package->appendChild($part);
  }

  return $total_size;
}

function _package_get_date_ordered_file_matches($path_with_wildcard)
{
  $list = glob($path_with_wildcard);
  $found = array();
  if ($list === FALSE) {
    if (ini_get('open_basedir')) {
      // Don't report anything
    } elseif (function_exists("pugpig_set_message")) {
      pugpig_set_message("GLOB ERROR: _package_get_date_ordered_file_matches - $path_with_wildcard", 'error');
    } else {
      print("GLOB ERROR: _package_get_date_ordered_file_matches - " . esc_html( $path_with_wildcard ) );
      exit();
      // Need to report this somehow
    }

    return $found;
  }
  foreach ($list as $file) {
    $mtime = filemtime($file);
    $found[$mtime] = $file;
  }
  ksort($found, SORT_NUMERIC);

  return $found;
}

// This is slow. Ensure we never call it more than once per request per edition
global $_package_recent_files;
$_package_recent_files = array();
function _package_get_most_recent_file($path_with_wildcard)
{
  global $_package_recent_files;
  if (isset($_package_recent_files[$path_with_wildcard])) return $_package_recent_files[$path_with_wildcard];

  $matches = _package_get_date_ordered_file_matches($path_with_wildcard);
  if (count($matches) > 0) {
    $ret = array_pop($matches);
    $_package_recent_files[$path_with_wildcard] = $ret;

    return $ret;
  }

  return NULL;
}

/*
Need a URL in the form: http://domain/base/relativeurl
Handles inputs of the form:
  http://domain/base/relativeurl
  http://cdn/base/relativeurl
  /base/relativeurl
  relativeurl
*/
/*
function _pugpig_package_url($url, $domain, $base)
{
  return url_to_absolute($domain . $base, $url);

  if (startsWith($url, $domain . $base)) return $url;
  if (startsWith($url, $base)) return $domain . $url;

  // We had a relative URL
  return $domain . $base . $url;
}
*/

/*
If the path starts with the base, we need to strip it off
*/
function _pugpig_package_path($path, $base)
{
  if (startsWith($path, $base)) return substr($path, strlen($base));
  return $path;
}

// Take a set of relative URLs and convert them into an array of absolute URLs and save paths for the packager
function _pugpig_relative_urls_to_download_array($relative_path, $relative_urls, $base_url, $base_path)
{
  $entries = array();
  foreach ($relative_urls as $relative_url) {
    // Remove any domains
    $relative_url = _pugpig_package_url_remove_domain($relative_url);

    // Get the  URL that needs to be CURLed
    $url = url_to_absolute($base_url, $relative_url);

    // Catch rare errors when we can't split the URL (for example very bad characters)
    if (empty($url)) echo "<span class='fail'>ERROR: Could not process URL:  " . esc_html($relative_url) . "</span><br />\n";

    // Take the domain off
    $root_url = _pugpig_package_url_remove_domain($url);

    // Get the path to save the file at
    $path =  $base_path . _pugpig_package_path($root_url, '/' . $relative_path);

    // In case we've got 2 slashes next to each other in the disk path
    $path = str_replace("//", "/", $path);

    // Convert folders to index.html files
    if (substr($path, -1) === '/') {
      $path = $path . 'index.html';
    }

    // We need to store the files on disk without %20s and the like
    // At present there is a bug in the client that appears to need these escaped
    // It does mean URLs with spaces don't work in a web browser after unzipping
    // $entries[$url] = rawurldecode($path);
    $entries[$url] = $path;

  }

  return $entries;

}

/*
  Cleans the package folder
  Old version - leaving only the last two files from each edition
  2014-02-14, Carlos, sub-zip changes: removes files for each
    package that don't have the latest timestamp. It doesn't remove files
    that don't have an associated package XML file as we don't want to
    inadvertantly delete other files in the folder not related to
    packaging. Although the assumption is that only the package files have
    the naming convention:
    ($edition_id)-(package|html|assets)-($timestamp)(?$subzip_index).(xml|zip)
*/
function _pugpig_clean_package_folder($path_to_package_folder)
{
  $deleted = array();

  // Find all XML files - these are the package XML files
  // Take note of the most recent timestamp for each edition ID
  $packages = glob($path_to_package_folder.'*-package-*.xml', GLOB_BRACE);
  $latest = array();
  foreach ($packages as $index => $name) {
    $info = _pugpig_package_folder_package_info($name);
    if (!isset($latest[$info['edition_id']]) || $info['timestamp'] > $latest[$info['edition_id']])
      $latest[$info['edition_id']] = $info['timestamp'];
  }

  // Now loop through each edition, list all files starting with the ID
  // and delete those without the latest timestamp
  foreach ($latest as $edition_id => $latest_timestamp) {
    $matches = glob($path_to_package_folder.$edition_id.'-*', GLOB_BRACE);
    foreach ($matches as $index => $name) {
      $info = _pugpig_package_folder_package_info($name);
      if ($info['edition_id'] === $edition_id
          && $info['timestamp'] > -1 && $info['timestamp'] < $latest_timestamp) {
        array_push($deleted, $name);
        unlink($name);
      }
    }
  }

  return $deleted;
}

function _pugpig_get_package_filename($dir, $edition_id, $lastpart)
{
  return $dir . DIRECTORY_SEPARATOR . $edition_id . '-package-' . $lastpart . '.xml';
}

/*
  Returns an associative array with edition_id and timestamp.
  As the globs can be too greedy (SPLUGCOMMON-49) we work right to left to
  get the timestamp, the package file type, and then the edition_id:
  ($edition_id)-(package|html|assets)-($timestamp)(?$subzip_index).(xml|zip)
  Note: this timestamp logic might well fail on 2038-01-19 (32-bit signed int)
  Also, if we cannot verify the timestamp on the file i.e. we cannot find the package file
  relating to the file, then the timestamp is returned as -1 to indicate it could not be found.
*/
function _pugpig_package_folder_package_info($name)
{
  $path = pathinfo($name);
  $filename = $path['filename'];
  $parts = explode('-', $filename); // filename without extension
  $lastpart = array_pop($parts);    // ($timestamp)(?$subzip_index)
  $type = array_pop($parts);        // (package|html|assets)
  $edition_id = join('-', $parts);  // ($edition_id)

  $timestamp = -1; // -1 means couldn't determine timestamp
  if ($type === 'package') {
    // the last part is just the timestamp:
    $timestamp = intval($lastpart);
  } else {
    $test_package_filename = _pugpig_get_package_filename($path['dirname'], $edition_id, $lastpart);
    if (file_exists($test_package_filename)) {
      // the package file exists with this timestamp, so we know the timestamp is valid
      $timestamp = intval($lastpart);
    } else {
      $lastpart_sanitised = substr($lastpart, 0, strlen($lastpart)-2);
      $test_package_filename = _pugpig_get_package_filename($path['dirname'], $edition_id, $lastpart_sanitised);
      if (file_exists($test_package_filename)) {
        // the truncated form of the lastpart is the valid timestamp
        $timestamp = intval($lastpart_sanitised);
      }
    }
  }

  return array(
    'edition_id' => $edition_id,
    'timestamp'  => $timestamp);
}

/*
Takes an array of the form $url => $path and turn it into $srcpath => $targetpath
If the URL is not relative to the URL where the packages will sit (package_url_base), the path in
the zip must be absolute and start with a /
Otherwise, it is relative to the location of the zip file
*/
function _pugpig_package_zip_paths($entries, $base_path, $package_url_base, $relative_path, $debug)
{
    $zip_paths = array();
    foreach ($entries as $url => $path) {
     // print_r("PREP: $url -> $path<br />");

     $zip_location = substr($path, strlen($base_path));

     // If this URL does not start with the base URL, it is absolute. Stick a the relative path in front
     if (!startsWith($url, $package_url_base)) $zip_location = $relative_path . $zip_location;

     // If this URL does not start with a / but the package_url_base does, it needs a / in front.
     if (startsWith($package_url_base, "/") && !startsWith($zip_location, "/"))
      $zip_location = "/" . $zip_location;

     // print_r("INTO: $path -> $zip_paths[$path]<br />");
      $zip_paths[$path] = $zip_location;
    }
    /*
    if ($debug) {
      _print_immediately('<h3>DEBUG: These will go into the zip</h3>');
      var_dump($zip_paths);
    }
    */

    return $zip_paths;
}

/*
Create a zip file and copy it from the temp dir to the real dir
*/

function _pugpig_package_create_zip($partname, $filename, $tmp_path, $real_path, $zip_paths, $zip_base_url)
{
  if (!startsWith($zip_base_url, '/')) {
    $zip_base_url = '/' . $zip_base_url;
  }

  if (count($zip_paths) > 0) {
    $archive_tmp = $tmp_path  . $filename;
    $archive_real = $real_path . $filename;

    _print_immediately('<em>Zipping ' . count($zip_paths) . ' items into ' . $archive_tmp . '</em><br />');
    _package_zip($archive_tmp, $zip_paths);
    if (file_exists($archive_tmp)) {
      _print_immediately('<em>Copying to ' . $archive_real . '</em><br />');
      copy($archive_tmp, $archive_real ); // move from /tmp to default/files
      $zipleaf = basename($archive_real);
      _print_immediately("<a target='_blank' href='" . $zip_base_url . $filename . "'>View ZIP file $zipleaf</a><br />");

      return true;
    } else {
       _print_immediately('<em>Error creating package file '.$archive_tmp.'</em><br />');

       return false;
    }
  } else {
    return false;
  }
}

function _pugpig_package_show_failures($failures)
{
  if (count($failures) > 0) {
    print_r("<h4>Error Summary</h4>");
    foreach ($failures as $failure => $reason) {
      print_r("<p class='fail'><a href='". $failure."' target='_blank'>". htmlentities($failure)."</a><br/><span class='fail'>" . htmlentities($reason) . "</span></p>");
    }
    _print_immediately('<b>Aborting</b><br /><a href="javascript:location.reload(true);">Refresh this page to reload and try again. (It will resume from where it last succeeded.)</a><br />');
    exit();
  }
}

function _pugpig_package_validate_download_url($url, $filename, &$format_failures) {
  $valid = true;
  if (!filter_var($url, FILTER_VALIDATE_URL)) {
    $valid = false;
    if (isset($format_failures)) {
      $format_failures[$url] = "Invalid URL format: " . $url;
    }
  } else {
    // and will be saved to allowed filenames
    if (strpos($filename, "?") || strpos($filename, "&")) {
      $valid = false;
      if (isset($format_failures)) {
        $format_failures[$url] = "Invalid local disk path: " . $filename;
      }
    }
  }
  return $valid;
}

function _pugpig_package_download_batch($heading, $entries, $debug = false, $concurrent = 3)
{
  print_r("<h3>Downloading " .$heading . " - ". count($entries) . " files</h3>");

  // Check the URLs we're about to download are real URLs
  $format_failures = array();
  foreach (array_keys($entries) as $entry) {
    if (!_pugpig_package_validate_download_url($entry, $entries[$entry], $format_failures)) {
      unset($entries[$entry]);
    }
  }

  _pugpig_package_show_failures($format_failures);

  // Both of these should become a settings, as well as timeout value
  $warning_file_size = 150 * 1024; // 150 Kb
  $mc = new MultiCurl($entries, $concurrent, $warning_file_size);

  $mc->process();
  $successes = $mc->getSuccesses();
  $failures = $mc->getFailures();

  _pugpig_show_batch($successes);

  // _pugpig_array_to_table($successes);

  if (count($failures) > 0) {
    _pugpig_package_show_failures($failures);
    _print_immediately('<b>Aborting</b><br /><a href="javascript:location.reload(true);">Refresh this page to reload and try again. (It will resume from where it last succeeded.)</a><br />');
    exit();
  } else {
    print_r("<em>Done</em><br />");
  }

  return $entries;
}

function _pugpig_package_test_endpoints($endpoints, $timestamp, $tmp_root)
{
  pugpig_interface_output_header("Pugpig - Endpoint Checker");

  print_r("<h1>Checking Pugpig End Points</h1>");

  $tmp_root = str_replace(DIRECTORY_SEPARATOR, '/', $tmp_root);
  $tmp_path = $tmp_root . 'package-' . $timestamp . '/';

  $entries = array();

  $c = 0;
  foreach ($endpoints as $endpoint) if ($endpoint != '') {
    $save_path = $tmp_path . 'opds/' . hash('md5', $endpoint). '.xml';
    $entries[$endpoint] = $save_path;
  }

  $debug = false;
  $concurrent = 1;
  $entries = _pugpig_package_download_batch("OPDS Feeds", $entries, $debug, $concurrent);

  $format_failures = array();
  foreach (array_keys($entries) as $entry) {
    // print_r($entry . " ---> " . $entries[$entry] . "<br />");

    // Read the ATOM from the file
    $fhandle = fopen($entries[$entry], 'r');
    $opds_atom = fread($fhandle, filesize($entries[$entry]));
    fclose($fhandle);

    $msg = check_xml_is_valid($opds_atom);
    if ($msg != '') {
      $format_failures[$entry] = "OPDS XML Invalid: " . $msg;
      $opds_atom = '';
    }

    $opds_ret = _pugpig_package_parse_opds($opds_atom);

    $edition_roots = array();
    $package_roots = array();

    print_r("<h2>" . $entry .  "(".$opds_ret['title'].")</h2>");
    foreach ($opds_ret['editions'] as $edition) {
      $cover = url_to_absolute($entry, $edition['cover']);

      print_r("<img class='cover ".($edition['free'] ? "free" : "paid")."' height='60' title='" . $edition['title'] . ': ' . $edition['summary'] . "' src='".$cover."' />");
      $edition_root = url_to_absolute($entry, $edition['url']);

      $save_path = $tmp_path . $edition['type'] . '/' . hash('md5', $edition_root). '.xml';
      $edition_roots[$edition_root] = $save_path;
      if ($edition['type'] == 'package') {
        $package_roots[] = $edition_root;
      }
    }
    $edition_roots = _pugpig_package_download_batch("Edition Roots", $edition_roots, $debug, $concurrent);

    $format_failures = array();
    foreach ($package_roots as $package_root) {
      $save_path = $edition_roots[$package_root];
      $fhandle = fopen($save_path , 'r');
      $package_xml_body = fread($fhandle, filesize($save_path));
      fclose($fhandle);

      $msg = check_xml_is_valid($package_xml_body);
      if ($msg != '') {
        $format_failures[$package_root] = "Package XML Invalid: " . $msg;
        $opds_atom = '';
      }

    }

    // Show package format errros
    _pugpig_package_show_failures($format_failures);

  }

  _pugpig_package_show_failures($format_failures);

}

function _pugpig_package_create_chunked_zips_for_buckets($buckets, $edition_tag, $timestamp, $tmp_path, $save_root, $zip_base_url, $prefered_max_size, $verbose=true) {
  $zips = array();
  foreach ($buckets as $bucket_name=>$filenames) {
    $zip_leafname = "${edition_tag}-{$bucket_name}-${timestamp}%s.zip";
    $zips[$bucket_name] = _pugpig_package_create_chunked_zips($bucket_name, $zip_leafname, $tmp_path, $save_root, $filenames, $zip_base_url, $prefered_max_size, $verbose);
  }
  return $zips;
}

function _pugpig_validate_saved_feed($content_xml_file_path, $url, &$format_failures) {
  $out = null;
  if (file_exists($content_xml_file_path)) {
    $fhandle = fopen($content_xml_file_path, 'r');
    $atom_contents = fread($fhandle, filesize($content_xml_file_path));
    fclose($fhandle);

    $msg = check_xml_is_valid($atom_contents);
    if (empty($msg)) {
      $out = $atom_contents;
    } else {
      $format_failures[$url] = "XML Invalid: " . $msg;
    }
  }
  return $out;
}

// $return_manifest_asset_urls = TRUE is used by Cloudfront purge code
// TODO: Why do we need to pass in the edition tag?
function _pugpig_package_edition_package($final_package_url, $content_xml_url, $relative_path,
  $debug=false, $edition_tag = '', $return_manifest_asset_urls = false,
  $timestamp = '', $tmp_root, $save_root,  $cdn = '', $package_url_base = '',
  $test_mode = false, $image_test_mode = false, $concurrent = 5, $bucket_allocator = null
) {
  $verbose = $debug;

  if (empty($bucket_allocator)) {
    // create default bucket allocator
    $bucket_allocator = new PackagedFileAllocatorOriginal();
  }
  $bucket_allocator->setVerbose($verbose);

  // sanitise inputs
  $save_root = str_replace(DIRECTORY_SEPARATOR, '/', $save_root);
  $tmp_root = str_replace(DIRECTORY_SEPARATOR, '/', $tmp_root);

  // process inputs
  $domain = '/';
  $colon_pos = strpos($content_xml_url, '://');
  if ($colon_pos > 0) {
    $domain = substr($content_xml_url, 0, strpos($content_xml_url, '/', $colon_pos + 3));
  }
  $last_slash = strrpos($content_xml_url, '/');
  $content_xml_leaf = $last_slash===false ? $content_xml : substr($content_xml_url, $last_slash+1);

  $tmp_path = $tmp_root . 'package-' . $timestamp . '/';

  // ensure save root folder exists
  if (!$test_mode && !file_exists($save_root)) {
    mkdir($save_root, 0777, true);
  }

  pugpig_interface_output_header("Pugpig - Edition Packager");
  if ($test_mode) {
    echo "<h1>Performing Pugpig Package Test Run</h1>";
  } elseif ($image_test_mode) {
    echo "<h1>Performing Pugpig Package Image Preview</h1>";
  } else {
    echo "<h1>Creating Pugpig Package</h1>";
  }

  $host = $_SERVER['HTTP_HOST'];
  if (!pugpig_test_ping($host)) {
    echo "<p><b><font color='red'>" .esc_html($host). ": Ping Failed. Maybe you need a local host entry?<br />127.0.0.1 " . esc_html($host) . "</b></p>";
  }

  print_r("<button style='cursor: pointer;' onclick=\"toggle_visibility('info');\">Info</button> ");
  print_r("<button style='cursor: pointer;' onclick=\"toggle_visibility('key');\">Key</button> ");
  print_r("<br />Packager version " . pugpig_get_standalone_version() . " <br />");

  print_r("<span id='key' style='display:none;'>");
  print_r("<span class='pass'>* - downloaded</span><br />");
  print_r("<span class='skip'>* - skipped as already downloaded</span><br />");
  print_r("<span class='fail'>* - failed to fetch or save resource</span><br />");
  print_r("</span>");

  print_r("<span id='info' style='display:none;'>");
  print_r("<em>Final Package URL: <a href='$final_package_url'>" . $final_package_url . '</a></em><br />');
  print_r("<em>Packaging ATOM URL: <a href='$content_xml_url'>" . $content_xml_url . '</a></em><br />');
  print_r("<em>Content leaf: $content_xml_leaf</em><br />");
  print_r("<em>Domain is: " . $domain . '</em><br />');
  print_r("<em>Relative path is: " . $relative_path . '</em><br />');
  print_r("<em>Package URL base is: " . $package_url_base . '</em><br />');
  print_r("<em>Save root is: " . $save_root . '</em><br />');
  print_r("<em>Temp path is: " . $tmp_path . '</em><br />');
  print_r("<em>CDN is: " . $cdn . '</em><br />');
  print_r("<em>Debug Mode is: " . ($debug ? "ON" : "OFF") . '</em><br />');
  print_r("<em>Test Mode is: " . ($test_mode ? "ON" : "OFF") . '</em><br />');
  print_r("<em>Image Mode is: " . ($image_test_mode ? "ON" : "OFF") . '</em><br />');
  print_r("<em>cURL timeout is: " . PUGPIG_CURL_TIMEOUT . ' seconds with ' . $concurrent . ' concurrent requests</em><br />');
  echo wp_kses_post($bucket_allocator->describeAllocator());
  print_r("</span>");

  print_r("<h1>Retrieving: $content_xml_url</h1>");
  _print_immediately('Package ' . $timestamp  . ' started at ' . date(PUGPIG_DATE_FORMAT, $timestamp) . '<br />');

  // Array used to store errors in the responses
  $format_failures = array();

  // Get the ATOM feeds - the real and and the one that might contain hidden extras
  $entries = array();

  $content_xml_hidden_save_path = $tmp_path . 'content-hidden.xml';
  $content_xml_hidden_path = $content_xml_url . (strpos($content_xml_url, '?') > 0 ? '&' : '?') . 'include_hidden=yes';

  // get the entry for the content xml
  $entries = _pugpig_relative_urls_to_download_array($relative_path, array($content_xml_url), $domain, $tmp_path);
  // and the hidden version
  $entries[$content_xml_hidden_path]  = $content_xml_hidden_save_path;

  $entries = _pugpig_package_download_batch("Public and Hidden ATOM Feeds", $entries, $debug, $concurrent);

  // validate feed that doesn't have hidden entries
  _pugpig_validate_saved_feed($entries[$content_xml_url], $content_xml_url, $format_failures);

  // validate feed that has hidden entries
  $feed_contents_with_hidden = _pugpig_validate_saved_feed($content_xml_hidden_save_path, $content_xml_hidden_path, $format_failures);

  $atom_filenames = null;
  $atom_ret = null;
  if (!empty($feed_contents_with_hidden)) {
    $atom_ret = _pugpig_package_parse_atom($feed_contents_with_hidden);
    unset($entries[$content_xml_hidden_path]); // we only want the real atom in the zip
    $atom_filenames = _pugpig_package_zip_paths($entries, $tmp_path, $package_url_base, $relative_path, $debug);
  }

  _pugpig_package_show_failures($format_failures);

  if (!$atom_ret) {
    return;
  }

  $contextualised_urls = $atom_ret['contextualised_urls'];
  foreach ($contextualised_urls as $page_id=>&$context) {
    $context['manifest_urls'] = _pugpig_relative_urls_to_download_array($relative_path, $context['manifest_urls'], $content_xml_url, $tmp_path);
    $context['html_urls'] = _pugpig_relative_urls_to_download_array($relative_path, $context['html_urls'], $content_xml_url, $tmp_path);
  }
  unset($context);

  $manifest_pages = $atom_ret['manifest_pages'];
  $manifest_pages_absolute = array();
  foreach ($manifest_pages as $url=>$page_ids) {
    $filenames = array_keys(_pugpig_relative_urls_to_download_array($relative_path, array($url), $content_xml_url, $tmp_path));
    if (count($filenames>0)) {
      $absolute_filename = $filenames[0];
      $manifest_pages_absolute[$absolute_filename] = $page_ids;
    }
  }

  // Get the Edition Tag if we don't have it
  if (!strlen($edition_tag)) {
    $edition_tag = $atom_ret['edition_tag'];
  }

  // Update the edition tag if we have something from the feed
  _print_immediately('<h2>Edition: ' . $atom_ret['edition_title'] . ' ('. $edition_tag .')</h2>');

  // Process the manifests - these are relative to the ATOM content XML
  $entries = _pugpig_relative_urls_to_download_array($relative_path, $atom_ret['manifest_urls'], $content_xml_url, $tmp_path);
  $entries = _pugpig_package_download_batch("Manifests", $entries, $debug, $concurrent);

  // Getting the list of static files from the manifests
  $manifest_entries = array();
  $format_failures = array();
  foreach ($entries as $url => $sfile) {
      $fhandle = fopen($sfile, 'r');
      $fcontents = trim(fread($fhandle, filesize($sfile)));
      fclose($fhandle);
      if (!startsWith($fcontents, "CACHE MANIFEST")) {
        // This is dodgy. We have a 200 that isn't a manifest.
        // Sometimes under really high concurrency, Drupal doesn't load includes properly
        // Delete the saved file in case it is better next time.
        $format_failures[$url] = "Manifest format not correct - CACHE MANIFEST not at start of response. Got: " . $fcontents;
        unlink($sfile);
      } else {
      //print_r("Read: " . $sfile . " - " . filesize($sfile) . " bytes<br />");
      //
        $this_manifest_files = _pugpig_package_get_asset_urls_from_manifest($fcontents, array(), $url);

        $this_manifest_files_src_dest = _pugpig_relative_urls_to_download_array($relative_path, $this_manifest_files, $content_xml_url, $tmp_path);
        //$this_manifest_files_src_dest = _pugpig_package_zip_paths($this_manifest_files_src_dest, $tmp_path, $package_url_base, $relative_path, $debug);

        $manifest_pages = $manifest_pages_absolute[$url];
        foreach($manifest_pages as $manifest_page) {
          if (empty($contextualised_urls[$manifest_page]['manifest_files'])) {
            $contextualised_urls[$manifest_page]['manifest_files'] = $this_manifest_files_src_dest;
          } else {
            $contextualised_urls[$manifest_page]['manifest_files'][] = $this_manifest_files_src_dest;
          }
        }

        $entries = _pugpig_relative_urls_to_download_array($relative_path, $atom_ret['manifest_urls'], $content_xml_url, $tmp_path);
        $manifest_entries = _pugpig_package_get_asset_urls_from_manifest($fcontents, $manifest_entries, $url);
      }
  }
  _pugpig_package_show_failures($format_failures);

  $manifest_entries = array_unique($manifest_entries);

  // Stop now and return the list of manifest items if required
  if ($return_manifest_asset_urls) {
  _print_immediately('<em>Returning ' . count($manifest_entries) . ' assets</em><br />');

   return $manifest_entries;
  }

  // Process the static files
  $entries = _pugpig_relative_urls_to_download_array($relative_path, $manifest_entries, $domain, $tmp_path);

  if ($image_test_mode) {
    _pugpig_package_show_images_in_package($entries);
  } else {
    $entries = _pugpig_package_download_batch("Static Files", $entries, $debug, $concurrent);

    // Process the HTML files
    $entries = _pugpig_relative_urls_to_download_array($relative_path, $atom_ret['html_urls'], $content_xml_url, $tmp_path);
    $entries = _pugpig_package_download_batch("HTML Pages", $entries, $debug, $concurrent);

    if (!$test_mode) {
      print_r("<h2>Packaging files</h2>");

      $bucket_allocator->allocateAtomFileToBuckets($atom_filenames);
      foreach($contextualised_urls as $page_id => $info) {
        $html_files = _pugpig_package_zip_paths($info['html_urls'], $tmp_path, $package_url_base, $relative_path, $debug);
        $manifest_files = _pugpig_package_zip_paths($info['manifest_urls'], $tmp_path, $package_url_base, $relative_path, $debug);
        $manifest_contents = _pugpig_package_zip_paths($info['manifest_files'], $tmp_path, $package_url_base, $relative_path, $debug);
        $context_xml = $info['entry'];
        $bucket_allocator->allocatePageFilesToBuckets($html_files, $manifest_files, $manifest_contents, $context_xml);
      }
      $bucket_allocator->finaliseBuckets();

      // Figure put where the packages will live
      $zip_base_url = $relative_path;
      if (!empty($package_url_base)) {
        $zip_base_url = $package_url_base;
      }
      $prefered_max_size = 20*1024*1024;
      $bucket_zips = _pugpig_package_create_chunked_zips_for_buckets($bucket_allocator->getBuckets(), $edition_tag, $timestamp, $tmp_path, $save_root, $zip_base_url, $prefered_max_size, $verbose);

      // Create package - TODO: Check on why we save this
      print_r("<h3>Creating Package XML</h3>");

      $package_name = "${edition_tag}-package-${timestamp}.xml";
      _print_immediately("<em>Saving package xml to ${save_root}${package_name}</em><br />");

      $package_xml = _package_edition_package_list_xml_using_buckets($save_root, $edition_tag, $bucket_allocator, $bucket_zips, $package_url_base, $cdn, $save_root . $package_name, $timestamp, $content_xml_leaf);

      _print_immediately("<a target='_blank' href='" . $final_package_url . "'>View XML file</a><br />");

      if (is_null($package_xml)) {
        _print_immediately('Error in saving package file.<br /><br /><b>Aborting!</b><br /><a href="javascript:location.reload(true);">Refresh this page to reload and try again. (It will resume from where it last succeeded.)</a><br />');
        exit;
      }

      $deleted_files = _pugpig_clean_package_folder($save_root);

      if (count($deleted_files)) {

        print_r("<h3>Deleting old package files</h3>");
        _print_immediately("<b>Deleted " . count($deleted_files) . " old files</b><br />");

        foreach ($deleted_files as $f) {
          _print_immediately("Deleted $f<br />");
        }

      }

    }

   }

  // Delete the temp area
  if (!$debug) {
    _package_rmdir($tmp_path);
  } else {
    _print_immediately("<p><b>Debug mode - not deleting temp files</b></p>");
  }

  _fill_buffer(16000);

  if (!$test_mode && !$image_test_mode) {
    print_r("<h2>Packaging Complete</h2>");
  } else {
    print_r("<h2>Test Run Complete</h2>");
  }

  return $edition_tag . '-package-' . $timestamp . '.xml';
}

function _pugpig_package_show_images_in_package($entries)
{
  _print_immediately('<div class="portfolio"><ul id="grid">');
  foreach (array_keys($entries) as $entry) {
    $extension = "";
    $path_parts = pathinfo($entry);
    if (isset($path_parts['extension'])) {
      $extension = $path_parts['extension'];
    }
    $char = pugpig_get_download_char($extension, 'EXT');

    if ($char == 'i') {
      _print_immediately("<li><a href='$entry'><img src='$entry'></a></li>\n");
    }
  }
  _print_immediately('</ul></div><p style="clear: both;">End of images</p>');

}

/*
  2014-02-14, Carlos, sub-zip changes: 3G downloads have been failing on iOS
  due to assumed 50MB file size limit. Instead of creating one big ZIP, we
  now try to restrict the size of any file to download to $suggested_max_bytes
  As the ZIP protocol does not allow files to be split across archives, it
  may exceed the specified size. (e.g. 60MB mp4). Also, we could optimise
  this a bit more by sorting all files by size before splitting, which will
  reduce (on average) the number of sub-ZIPs.
*/
function _pugpig_package_create_chunked_zips($partname, $filename, $tmp_path, $real_path, $zip_paths, $zip_base_url, $suggested_max_bytes, $verbose=true)
{
  _print_immediately("<h3>Creating ZIPs for $partname (with sizes &lt; $suggested_max_bytes)</h3>");
  $zips = array();
  // Split the paths into groups approx <= $suggested_max_bytes each; works with uncompressed files
  // can go over if individual file too large as ZIP can't handle the file being split?
  $group = array();
  $group_index = 0;
  $group_size = 0;
  foreach ($zip_paths as $src => $dest) {
    $file_size = filesize($src);
    if (($group_size + $file_size) <= $suggested_max_bytes) {
      $group_size += $file_size;
    } else {
      $zips = _pugpig_package_create_chunked_zip($zips, $partname, $filename, $group_index, $tmp_path, $real_path, $group, $zip_base_url);
      $group = array();
      $group_index++;
      $group_size = $file_size;
    }
    $group[$src] = $dest;
    if ($verbose) {
      _print_immediately("<strong>$partname group $group_index</strong>: size [$group_size] after adding [$src] <br>");
    }
  }
  $zips = _pugpig_package_create_chunked_zip($zips, $partname, $filename, $group_index, $tmp_path, $real_path, $group, $zip_base_url);
  if (count($zips) == 0)
    _print_immediately('<em>No assets to be zipped.</em><br />');

  return $zips;
}

/*
  2014-02-14, Carlos, sub-zip changes: The sub-zips are named by adding a zero-
  padded two digit number to the end of the timestamp number. This way, the
  patterns already defined by external consumers (like Apache/Nginx rewrite
  rules) don't have to be changed. It does mean that the digits at the end
  of the filename != timestamp, but one can use starts-with to maintain the
  relationship with the package XML.
*/
function _pugpig_package_create_chunked_zip($zips, $partname, $filename, $group_index, $tmp_path, $real_path, $group, $zip_base_url)
{
  $zip = sprintf($filename, str_pad($group_index, 2, '0', STR_PAD_LEFT));
  _pugpig_package_create_zip($partname, $zip, $tmp_path, $real_path, $group, $zip_base_url);
  array_push($zips, $zip);

  return $zips;
}
