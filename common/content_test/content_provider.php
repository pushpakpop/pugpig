<?php

require_once "../pugpig_manifests.php";
require_once "../pugpig_utilities.php";
require_once "content_utils.php";

/**
 * This file contains the functions that provide the content data;
 */

if (!function_exists('pugpig_get_opds_extras')) {
  function pugpig_get_opds_extras()
  {
    return array(
      'links' => array(array(
        'rel'   => 'search',
        'href'  => 'search?q={query}&edition={edition_id}',
        'title' => 'Search',
        'type'  => 'application/atom+xml')),
      'custom_categories' => array(
        'scheme' => 'term')
      );
  }
}

if (!function_exists('get_search_edition_id')) {
  function get_search_edition_id($query, $edition_id=null)
  {
    $edition = empty($edition_id) ? '' : $edition_id;
    $query64 = base64_encode($query);

    return "search.edition.$edition.$query64";
  }
}

if (!function_exists('get_search_edition_info')) {
  function get_search_edition_info($search_id)
  {
    $search_info = null;
    try {
      if (preg_match('/search\.edition\.(.*)\.([^\.]*)/', $search_id, $matches)) {
        $search_info = array(
          'edition' => $matches[1],
          'query' => base64_decode($matches[2]));
      }
    } catch (Exception $e) {
    }

    return $search_info;
  }
}

if (!function_exists('get_search_page_info')) {
  function get_search_page_info($search_id)
  {
    $search_info = null;
    try {
      if (preg_match('/search\.page\.(.*)/', $search_id, $matches)) {
        $search_info = array(
          'page_id' => $matches[1]);
      }
    } catch (Exception $e) {
    }

    return $search_info;
  }
}

if (!function_exists('is_search_edition_id')) {
  function is_search_edition_id($edition_id)
  {
    $search_info = get_search_edition_info($edition_id);

    return $search_info!=null;
  }
}

if (!function_exists('is_search_page_id')) {
  function is_search_page_id($edition_id)
  {
    $search_info = get_search_page_info($edition_id);

    return $search_info!=null;
  }
}

if (!function_exists('get_number_of_editions')) {
  function get_number_of_editions()
  {
    if (isset($_REQUEST['num_editions']) && is_numeric($_REQUEST['num_editions']) && $_REQUEST['num_editions'] < 500) {
      return $_REQUEST['num_editions'];
    }

    return 100;
  }
}

if (!function_exists('get_number_of_pages')) {
  function get_number_of_pages($edition_num)
  {
    if (isset($_REQUEST['num_pages']) && is_numeric($_REQUEST['num_pages']) && $_REQUEST['num_pages'] < 500) {
      return $_REQUEST['num_pages'];
    }

    return 100;
  }
}

if (!function_exists('get_edition_data')) {
  function get_edition_data($edition_num, $is_package)
  {
    $title_parts = array();

    $title_owner_prefix = empty($_SERVER['PHP_AUTH_USER']) ? '' : $_SERVER['PHP_AUTH_USER'].'\'s ';
    if (!empty($title_owner_prefix)) {
      $title_parts[] = $title_owner_prefix;
    }

    $num_editions = get_number_of_editions();
    $edition_key = get_edition_id($edition_num);
    $edition_date = add_date(time(), $edition_num);
    $modified = add_date_timestamp(time(), $edition_num);
    $cycle = (($edition_num % 10) + 1);

    // http response
    $http_responses = array(200, 404, 401, 403, 500);
    $http_reponses_index = $num_editions - 10 - $edition_num;
    $http_status = 0;
    if ($http_reponses_index>=0 && $http_reponses_index<count($http_responses)) {
      $http_status = $http_responses[$http_reponses_index];
    }
    $is_timeout = $http_reponses_index==count($http_responses);
    $is_broken = $is_timeout || $http_status>0;
    if ($is_broken) {
      $title_parts[] = 'Broken Test';
    }

    // edition flags
    $is_published = $edition_num % 3;
    $is_paid      = !$is_broken && $edition_num % 7;
    $is_vampire   = $edition_num == $num_editions - 20;
    if ($is_broken) {
      $title_parts[] = 'Vampire';
    }

    $is_deleted   = $is_vampire && wp_rand(0, 1) == 1;

    // strings
    $price  = $is_paid ? 'PAID' : 'FREE';
    $status = $is_published  ? 'published' : 'draft';

    // url
    if ($http_status==0) {
      if ($is_timeout) {
        $url = '../auth_test/responses/timeout.php';
      } else {
        $query = empty($_SERVER['QUERY_STRING']) ? '' : '?'.$_SERVER['QUERY_STRING'];
        $url =  "edition/$edition_key/" . ($is_package ? 'package.xml' : 'content.xml') . $query;
      }
    } else {
      $url = "../auth_test/responses/$http_status.php"; // todo: fix relative path, so we can find authtest from custom content
    }

    $http_status_summary = array(
      200 => 'All lies. I return a fake 200 code.',
      404 => 'Not Found. I return a 404 code.',
      401 => 'Unauthorized. I return a 401 code.',
      403 => 'Forbidden. I return a 403 code.',
      500 => 'Broken. I return a 500 code.'
      );

    // summary
    $always_updates = $edition_num % 9 == 0;
    $long_ttl       = $edition_num % 27 == 0;

    $summary = "You really should read edition $edition_num. It's the best $status ever. ";
    if ($is_published) {
      $summary .= "It is $price. ";
    } else {
      $summary .= "All draft editions are treated as free. ";
    }
    if ($always_updates) {
      $summary .= "And it ALWAYS updates. ";
    }
    if ($long_ttl) {
      $summary .= "I have a long 5 minute TTL on the Atom and Package feeds. ";
    }
    if ($is_vampire) {
      $summary .= "I get deleted and then raise from the dead. ";
    }
    if ($http_status>0) {
      $summary = $http_status_summary[$http_status] . ' ' . $summary;
    }
    if ($is_timeout) {
      $summary = 'Timeout. I take 60 seconds.' . ' ' . $summary;
    }

    // categories
    $custom_categories = array();
    if ($is_package) {
      $size = 0;
      $parts = get_package_parts($edition_num);
      foreach ($parts as $part_name => $part_info) {
        $size += filesize($part_info['path']);
      }
      $custom_categories['download_size'] = pugpig_bytestosize($size, 0);
    }

    // pages
    $pages = array();
    $num_pages = get_number_of_pages($edition_num);
    for ($page_num=1; $page_num<=$num_pages; $page_num++) {
      $pages[] = get_page_id($edition_num, $page_num);
    }

    $subtitles = array(null, ' ', null, ' ', "Edition $edition_num Sub Title", null, ' ', null, ' ', "This is the stupidly long subtitle for Edition $edition_num which isn't going to fit even on an iPad in portrait, especially since all this extra text was added to make this an even longer stupidly long subtitle.");
    $subtitle = $subtitles[$edition_num % count($subtitles)];
    if ($subtitle!=null) {
      $custom_categories['subtitle'] = $subtitle;
    }

    // pane model
    $pane_models = array(null, 'scrolling', 'horizontal', 'vertical');
    $pane_model_granularity = 4;
    $pane_model = $pane_models[($edition_num / $pane_model_granularity) % count($pane_models)];
    if ($pane_model===null) {
      $summary.= 'With no pane model.';
    } else {
      $custom_categories['pane_model'] = $pane_model;
      $summary.= 'With '.ucwords($pane_model).' pane model.';
    }

    $title_parts[] = "Edition Number $edition_num";
    $title_parts = array_filter($title_parts);
    $title = join(' ', $title_parts);

    return array(
      'key'        => $edition_key,
      'date'       => $edition_date,
      'title'      => $title,
      'modified'   => (!$always_updates) ? $modified : time(),
      'packaged'   => (!$always_updates) ? $modified : time(),
      'ttl'        => ($long_ttl ? 300 : 0),
      'status'     => $status,
      'price'      => $price,
      'thumbnail'  => "http://lorempixel.com/600/800/abstract/$cycle/Issue-$edition_num-$edition_date/",
      'newsstand_cover_art_icon_source' => "http://dummyimage.com/768x1024/000000/ffffff.png?text=Newstand+Cover+768x1024+Issue+$edition_num/",
      'summary'    => $summary,
      'tombstone'  => $is_deleted,
      'url_type'   => $is_package ? 'application/pugpigpkg+xml' : 'application/atom+xml',
      'url'        => $url,
      'newsstand_summary' => 'This is a longer summary that can appear in Newsstand. ' . $summary,
      'page_ids'   => $pages,
      'custom_categories' => $custom_categories
      );
  }
}

if (!function_exists('get_page_data')) {
  function get_page_data($edition_num, $page_num)
  {
    $num_pages = get_number_of_pages($edition_num);

    $custom_categories = array();
    if ($page_num % 7 == 0) {
      $custom_categories['toc_style'] = 'hidden';
    }

    $spectrum_colours = array('black', 'blue', 'red', 'magenta', 'cyan', 'yellow', 'white', null);
    $colour_index = ($edition_num + $page_num) % count($spectrum_colours);
    $colour = $spectrum_colours[$colour_index];
    if ($colour != null) {
      $custom_categories['custom_analytics/col#22'] = $colour;
    }

    $page_data = array(
      'id'         => get_page_id($edition_num, $page_num),
      'title'      => "Page Number $page_num",
      'categories' => array("Section " . ceil($page_num / ($num_pages / 5))),
      'summary'    => "Page Number $page_num is really interesting",
      'status'     => 'published',
      'url'        => "page-$page_num.html",
      'manifest'   => "page-$page_num.manifest",
      'custom_categories' => $custom_categories
      );

    if ($page_num % 5 == 0) {
      $page_data['sharing_link'] = 'http://pugpig.com';
    }

    return $page_data;
  }
}

if (!function_exists('get_page_url')) {
  function get_page_url($edition_num, $page_num)
  {
    return 'book.html';
  }
}

if (!function_exists('get_page_manifest_path')) {
  function get_page_manifest_path($edition_num, $page_num)
  {
    return implode(DIRECTORY_SEPARATOR, array(dirname($_SERVER['SCRIPT_FILENAME']), 'content', 'book.manifest'));
  }
}

if (!function_exists('get_file_url')) {
  function get_file_url($path)
  {
    $depth = substr_count($path, '/');

    return str_repeat('../', 3+$depth) . 'content/' . $path;
  }
}

/**
 *  these function aren't generally needed to be overridden...
 */

if (!function_exists('get_edition_prefix')) {
  function get_edition_prefix()
  {
    return 'com.pugpig.edition';
  }
}

if (!function_exists('get_edition_id')) {
  function get_edition_id($edition_num)
  {
    $edition_id = $edition_num;
    if (is_int($edition_num)) {
      $edition_id = get_edition_prefix() . str_pad($edition_num, 4, "0", STR_PAD_LEFT);
    }

    return $edition_id;
  }
}

if (!function_exists('get_page_id')) {
  function get_page_id($edition_num, $page_num)
  {
    return get_edition_id($edition_num) . '.' . str_pad($page_num, 4, "0", STR_PAD_LEFT);
  }
}

if (!function_exists('get_packages_xml_path')) {
  function get_packages_xml_path($edition_num)
  {
    $edition_key = get_edition_id($edition_num);
    $ret = implode(DIRECTORY_SEPARATOR, array(get_packages_dir(), $edition_key, $edition_key.'-package-0.xml'));

    return $ret;
  }
}

if (!function_exists('get_package_dir')) {
  function get_package_dir($edition_num)
  {
    $edition_id = get_edition_id($edition_num);

    return implode(DIRECTORY_SEPARATOR, array(dirname(dirname(dirname($_SERVER['SCRIPT_FILENAME']))), 'contenttest-packages', $edition_id)).DIRECTORY_SEPARATOR;
  }
}

if (!function_exists('get_package_path')) {
  function get_package_path($edition_num)
  {
    $edition_id = get_edition_id($edition_num);

    return "../../../contenttest-packages/$edition_id/";
  }
}

if (!function_exists('get_package_parts')) {
  function get_package_parts($edition_num)
  {
    $edition_id = get_edition_id($edition_num);
    $zip_root_url = $edition_id;
    $zip_root_path = get_package_dir($edition_num) . DIRECTORY_SEPARATOR . $edition_id;

    return array(
      'html' => array(
        'url'  => $zip_root_url  . '-html-001.zip',
        'path' => $zip_root_path . '-html-001.zip',
        ),
      'assets' => array(
        'url'  => $zip_root_url  . '-assets-001.zip',
        'path' => $zip_root_path . '-assets-001.zip',
        ),
      );
  }
}

/**
 * Utility functions for content provider functionality
 */

if (!function_exists('get_files_from_manifest')) {
  function get_files_from_manifest($edition_num, $page_num)
  {
    $files = array();

    $path = get_page_manifest_path($edition_num, $page_num);
    $file_handle = fopen($path, "rb");
    fgets($file_handle); // 'CACHE MANIFEST' line
    while (!feof($file_handle)) {
      $line = trim(fgets($file_handle));
      if (!empty($line) && !startsWith($line, '#') && !startsWith($line, '#')) {
        $files[] = $line;
      }
    }

    return $files;
  }
}

if (!function_exists('get_files_from_links')) {
  function get_files_from_links($edition_num, $page_num)
  {
    $page_data = get_page_data($edition_num, $page_num);

    $files = array();
    if (!empty($page_data['links'])) foreach ($page_data['links'] as $link) {
      if ($link['rel']!='related' && $link['rel']!='alternate' && $link['rel']!='bookmark'
        && preg_match('/^http(s):/i', $link['href'])===0) {
        $url_prefix = startsWith($link['href'], '/') ? '' : $_SERVER["SCRIPT_NAME"] . "/edition/$edition_num/";
        $files[] = $url_prefix . $link['href'];
      }
    }

    return $files;
  }
}

if (!function_exists('get_page_manifest_files')) {
  function get_page_manifest_files($edition_num, $page_num)
  {
    $files = get_files_from_manifest($edition_num, $page_num);
    $linked_files = get_files_from_links($edition_num, $page_num);

    return array_merge($files, $linked_files);
  }
}

if (!function_exists('pugpig_get_edition_num_from_id')) {
  function pugpig_get_edition_num_from_id($edition_id)
  {
    $edition_prefix = get_edition_prefix();
    $edition_num_string = str_replace($edition_prefix, '', $edition_id);

    return intval($edition_num_string);
  }
}

if (!function_exists('pugpig_get_edition_and_page_num_from_id')) {
  function pugpig_get_edition_and_page_num_from_id($page_id)
  {
    $page_num = -1;
    $edition_num = -1;

    $edition_prefix = get_edition_prefix();
    $prefix_stripped_string = str_replace($edition_prefix, '', $page_id);
    $dot_pos = strpos($prefix_stripped_string, '.');
    if ($dot_pos>0) {
      $edition_num = intval(substr($prefix_stripped_string, 0, $dot_pos));
      $page_num = intval(substr($prefix_stripped_string, $dot_pos+1));
    }

    return array(
      'edition_num' => $edition_num,
      'page_num'    => $page_num
      );
  }
}

if (!function_exists('pugpig_get_edition_package_exists')) {
  function pugpig_get_edition_package_exists($edition_num)
  {
    return file_exists(get_packages_xml_path($edition_num));
  }
}

if (!function_exists('get_edition_start_num')) {
  function get_edition_start_num()
  {
    $start_num = 1;
    if (!empty($_REQUEST['start_num'])) {
      $start_num = max(1, intval($_REQUEST['start_num']));
    }

    return $start_num;
  }
}

if (!function_exists('get_extra_atom_ids')) {
  function get_extra_atom_ids()
  {
    return array(
      get_search_edition_id('10', 'com.pugpig.edition0099'),
      get_search_edition_id('85'));
  }
}

if (!function_exists('get_all_packaged_edition_ids')) {
  function get_all_packaged_edition_ids()
  {
    $ids = array();
    $start_num = get_edition_start_num();
    $end_num = get_number_of_editions() + $start_num -1;
    $edition_num = $start_num;
    do {
      if (pugpig_get_edition_package_exists($edition_num)) {
        $ids[] = get_edition_id($edition_num);
      }
    } while ($edition_num++<$end_num);

    return $ids;
  }
}

if (!function_exists('get_all_atom_edition_ids')) {
  function get_all_atom_edition_ids()
  {
    $ids = array();
    $start_num = get_edition_start_num();
    $end_num = get_number_of_editions() + $start_num -1;
    $edition_num = $start_num;
    do {
      if (pugpig_get_edition_package_exists($edition_num)) {
        $ids[] = get_edition_id($edition_num);
      }
    } while ($edition_num++<$end_num);

    return $ids;
  }
}

if (!function_exists('get_packages_dir')) {
  function get_packages_dir()
  {
    $packages_relative_dir = '../../contenttest-packages/';
    $ret = get_absolute_path(dirname($_SERVER['SCRIPT_FILENAME']) . DIRECTORY_SEPARATOR . $packages_relative_dir);
    // Don't start with a slash on Windows
    return (DIRECTORY_SEPARATOR == "/" ? DIRECTORY_SEPARATOR : "") . $ret;
  }
}

if (!function_exists('pugpig_get_search_edition')) {
  function pugpig_get_search_edition($query, $edition_id=null)
  {
    $title = "Search for \"$query\"";
    $all_page_ids = array();

    $search_edition_ids = array();

    if (empty($edition_id)) {
      $search_edition_ids = get_all_atom_edition_ids();
    } else {
      $title.=" inside \"$edition_id\"";
      $search_edition_ids[] = $edition_id;
    }
    global $is_package;
    foreach ($search_edition_ids as $search_edition_id) {
      $edition = get_edition_data(pugpig_get_edition_num_from_id($search_edition_id), $is_package);
      $all_page_ids = array_merge($all_page_ids, $edition['page_ids']);
    }

    $keep_every = intval($query);
    if ($keep_every < 1) {
      $keep_every = 2;
    }
    $page_ids = array();
    for ($index = 0; $index<count($all_page_ids); $index++) {
      if ($index%$keep_every == 0) {
        $page_ids[] = 'search.page.'.$all_page_ids[$index];
      }
    }

    $edition_key = get_search_edition_id($query, $edition_id);

    return array(
      'key'        => $edition_key,
      'date'       => date('Y-m-d'),
      'title'      => $title,
      'modified'   => time(),
      'packaged'   => time(),
      'ttl'        => 0,
      'status'     => 'published',
      'price'      => 'FREE',
      'thumbnail'  => "http://lorempixel.com/600/800/abstract/0/Search/",
      'newsstand_cover_art_icon_source' => "http://dummyimage.com/768x1024/000000/ffffff.png?text=Search/",
      'summary'    => '',
      'tombstone'  => FALSE,
      'url_type'   => 'application/atom+xml',
      'url'        => "edition/$edition_key/" . ($is_package ? 'package.xml' : 'content.xml'),
      'newsstand_summary' => '',
      'page_ids'   => $page_ids
      );
  }
}

if (!function_exists('get_search_page_data')) {
  function get_search_page_data($page_id)
  {
    $data = pugpig_get_page($page_id);
    $edition_id = get_edition_id_from_page_id($page_id);
    $edition_num = pugpig_get_edition_num_from_id($edition_id);

    if ($data['custom_categories']==null) {
      $data['custom_categories'] = array();
    }
    $script = $_SERVER['SCRIPT_NAME'];
    $data['custom_categories']['edition'] = $edition_id;
    $data['url'] = "$script/edition/$edition_id/".$data['url'];
    $data['manifest'] = "$script/edition/$edition_id/".$data['manifest'];

    return $data;
  }
}

if (!function_exists('get_edition_id_from_page_id')) {
  function get_edition_id_from_page_id($page_id)
  {
    $edition_id = null;
    if (preg_match('/(.*)\.(\d{4})/', $page_id, $matches)) {
      $edition_id = $matches[1];
    }

    return $edition_id;
  }
}

/**
 *  below are standard pugpig functions that have been implemented for content_test specific functionality
 */

if (!function_exists('pugpig_get_edition')) {
  function pugpig_get_edition($key)
  {
    $data = null;
    if (is_search_edition_id($key)) {
      $search_info = get_search_edition_info($key);
      $edition = empty($search_info['edition']) ? null : $search_info['edition'];
      $data = pugpig_get_search_edition($search_info['query'], $edition);
    } else {
      global $is_package; // could indicate is package feed in the edition key
      $data = get_edition_data(pugpig_get_edition_num_from_id($key), $is_package);
    }

    return $data;
  }
}

if (!function_exists('pugpig_get_pages')) {
  function pugpig_get_pages($key)
  {
    return array(pugpig_get_page($key));
  }
}

if (!function_exists('pugpig_post_process_pages')) {
  function pugpig_post_process_pages($all_pages, $edition_id, $content_filter)
  {
    return $all_pages;
  }
}

if (!function_exists('pugpig_get_page')) {
  function pugpig_get_page($key)
  {
    $data = null;
    if (is_search_page_id($key)) {
      $info = get_search_page_info($key);
      $data = get_search_page_data($info['page_id']);
    } else {
      $nums = pugpig_get_edition_and_page_num_from_id($key);
      $data = get_page_data($nums['edition_num'], $nums['page_num']);
    }

    return $data;
  }
}

if (!function_exists('add_date_raw')) {
  function add_date_raw($cd, $months=0, $days=0, $years=0)
  {
    return mktime(0, 0, 0, date('m', $cd) + $months, date('d', $cd) + $days, date('Y', $cd) + $years);
  }
}

if (!function_exists('add_date')) {
  function add_date($cd, $months=0, $days=0, $years=0)
  {
    return date('Y-m-d', add_date_raw($cd, $months, $days, $years));
  }
}

if (!function_exists('add_date_timestamp')) {
  function add_date_timestamp($cd, $months=0, $days=0, $years=0)
  {
    return mktime(0, 0, 0, date('m', $cd)+$months, date('d',$cd) + $days, date('Y',$cd)+$years);
  }
}

if (!function_exists('pugpig_get_atom_tag')) {
  function pugpig_get_atom_tag($key)
  {
    return $key == 'opds' ? "com.pugpig.opds" : $key;
  }
}

if (!function_exists('pugpig_get_current_base_url')) {
  function pugpig_get_current_base_url()
  {
   return "";
  }
}
