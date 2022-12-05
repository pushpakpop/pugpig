<?php
/**
 * @file
 * Pugpig Dovetails common code
 */
?><?php
/*

Licence:
==============================================================================
(c) 2011, Kaldor Holdings Ltd
This module is released under the GNU General Public License.
See COPYRIGHT.txt and LICENSE.txt

 */?><?php
include_once "split_url.php";

function url_add_dots_for_relative($base, $url)
{
    if (empty($url) || empty($base)) return $url;

    $parts = split_url($base);
    $base_path = $parts['path'];
    $parts = split_url($url);
    $url_path = empty($parts['path']) ? '' : $parts['path'];

    if (startsWith($base_path, "/") && startsWith($url, "/")) {
        // Remove the slash and create the ../../.. going to the root

        $url_segments = explode("/", $url_path);
        $path_segments = explode("/", $base_path);

        //print_r ($url_segments);
        //print_r ($path_segments);

        while (count($url_segments) && $url_segments[0] == $path_segments[0]) {
            array_shift($url_segments);
            array_shift($path_segments);
        }

        //print_r ($url_segments);
        //print_r ($path_segments);

        $depth = count($path_segments) - 1;

        //$url = substr($url, 1);
        $url = implode("/", $url_segments);
        while ($depth > 0) {
            $url = '../' . $url;
            $depth--;
        }
    }

    if (empty($url)) return ".";
    return $url;
}

function url_echo_test_results()
{
    $tests = array(
        array('/editions/edition-860/content.xml', '/editions/edition-860/content.xml'),
        array('/editions/edition-860/content.xml', '/editions/edition-860/data/2/assets/styles/ipad/public/CROPPEDCover-Howard-McWilliam-WK_Rob-from-the-rich.jpg'),
        array('/editions/edition-860/content.xml', '/editions/edition-861/data/2/assets/styles/ipad/public/CROPPEDCover-Howard-McWilliam-WK_Rob-from-the-rich.jpg'),
        array('/editions/edition-860/content.xml', '/pookie/edition-861/data/2/assets/styles/ipad/public/CROPPEDCover-Howard-McWilliam-WK_Rob-from-the-rich.jpg'),
        array('/1/2/3/4/5/6/7.html', '/bob.html'),
        array('/1/2/3/4/5/6/7.html', 'bob.html'),

    );

    foreach ($tests as $test) {
        echo "BASE: " . esc_html( $test[0] ) . "\n";
        echo "URL: " . esc_url( $test[1] ) . "\n";
        echo "OUTPUT: " . wp_kses_post( url_add_dots_for_relative($test[0], $test[1]) ) . "\n\n";
    }

    exit();
}

function url_create_deep_dot_url($url)
{
    return url_add_dots_for_relative($_SERVER["REQUEST_URI"], $url);
}
