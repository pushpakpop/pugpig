<?php

set_include_path(get_include_path() . PATH_SEPARATOR . dirname(__FILE__) . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..'); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.runtime_configuration_set_include_path

// mocking PHP/Wordpress elements
//

if (empty($_SERVER['REQUEST_URI'])) {
    $_SERVER['REQUEST_URI'] = 'http://www.example.com/contents_page/contents/pugpig_index.html';
}

global $wp_version;
if (empty($wp_version)) {
    $wp_version = 4.0;
}

define('WP_CONTENT_DIR', __DIR__ . '/pp-content');
define('WP_CONTENT_URL', 'http://www.example.com/asd/dsf/sdf/sd/fs/pp-content');

if (!function_exists('content_dir')) {
    function content_dir()
    {
      return WP_CONTENT_DIR;
    }
}

if (!function_exists('content_url')) {
    function content_url()
    {
      return WP_CONTENT_URL;
    }
}

if (!function_exists('add_filter')) {
    function add_filter()
    {
    }
}

if (!function_exists('add_action')) {
    function add_action()
    {
    }
}

if (!function_exists('apply_filters')) {
    function apply_filters($a, $b)
    {
        return $b;
    }
}

if (!function_exists('site_url')) {
    function site_url()
    {
        return 'http://www.example.com';
    }
}

if (!function_exists('home_url')) {
    function home_url()
    {
        return 'http://www.example.com';
    }
}


if (!function_exists('is_admin')) {
    function is_admin()
    {
        return true;
    }
}
