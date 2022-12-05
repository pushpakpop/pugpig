<?php

function get_number_of_editions()
{
  return 20;
}

function get_number_of_pages($edition_num)
{
  return $edition_num/4 + 5;
}

function get_edition_data($edition_num, $is_package)
{
  $pages = array();
  $num_pages = get_number_of_pages($edition_num);
  for ($page_num=1; $page_num<=$num_pages; $page_num++) {
    $pages[] = get_page_id($edition_num, $page_num);
  }

  $cycle = (($edition_num % 10) + 1);
  $edition_date = add_date(time(), $edition_num);

  return array(
    'key'       => get_edition_id($edition_num),
    'date'      => $edition_date,
    'title'     => "Edition Number $edition_num",
    'modified'  => time(), // now
    'packaged'  => time(), // now
    'status'    => 'published',
    'price'     => 'FREE',
    'thumbnail' => "http://lorempixel.com/600/800/abstract/$cycle/Issue-$edition_num-$edition_date/",
    'newsstand_cover_art_icon_source' => "http://dummyimage.com/768x1024/000000/ffffff.png?text=Newstand+Cover+768x1024+Issue+$edition_num/",
    'summary'   => "Summary Number $edition_num",
    'tombstone' => FALSE,
    'url_type'  => $is_package ? 'application/pugpigpkg+xml' : 'application/atom+xml',
    'url'       => "edition/$edition_num/" . ($is_package ? 'package.xml' : 'content.xml'),
    'newsstand_summary' => "Newsstand sumamry number $edition_num",
    'page_ids'  => $pages
    );
}

if (!function_exists('get_page_data')) {
  function get_page_data($edition_num, $page_num)
  {
    $num_pages = get_number_of_pages($edition_num);

    return array(
      'id'         => get_edition_id($edition_num) . '.' . str_pad($edition_num, 4, "0", STR_PAD_LEFT),
      'title'      => "Page Number $page_num",
      'categories' => array("Section " . ceil($page_num / ($num_pages / 5))),
      'summary'    => "Page Number $page_num is really interesting",
      'status'     => 'published',
      'url'        => "page-$page_num.html",
      'manifest'   => "page-$page_num.manifest",
      'sharing_link' => 'http://pugpig.com'
      );
  }
}

function get_page_url($edition_num, $page_num)
{
  return 'book.html';
}

function get_page_manifest_path($edition_num, $page_num)
{
  return implode(DIRECTORY_SEPARATOR, array(dirname(__FILE__), 'content', 'book.manifest'));
}

define('CONTENT_TEST_DIR', implode(DIRECTORY_SEPARATOR, array(dirname(dirname(__FILE__)), 'content_test')));
chdir(CONTENT_TEST_DIR);
require_once basename($_SERVER['SCRIPT_FILENAME']);
