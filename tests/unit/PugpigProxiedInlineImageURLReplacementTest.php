<?php

require_once 'pugpig_wordpress_mock.php';
require_once 'pugpig_article_rewrite.php';

class PugpigProxiedInlineImageURLReplacementTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @dataProvider dataProvider
     */
    public function testImageReplacementNormal($body, $prefix, $expected)
    {
    	$this->assertEquals($expected, \pugpig_replace_image_urls($body, $prefix));
    }

    public function dataProvider()
    {
      $content_path = wp_parse_url(content_url(), PHP_URL_PATH);
      $num_dotdots = 2; /* assuming two levels in the permalinks for the test */

    	$prefix = 'PugpigReplaceImageURLsTest';
      return array(
          array('', $prefix, ''),
          array('<img src="http://pugpig.com/banana.jpg">', $prefix, '<img src="'. str_repeat('../', $num_dotdots) . ltrim($content_path, '/') . '/PugpigReplaceImageURLsTest/aHR0cDovL3B1Z3BpZy5jb20vYmFuYW5hLmpwZw__.jpeg">')
        );
    }
}
