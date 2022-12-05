<?php

require_once 'pugpig_wordpress_mock.php';
require_once 'pugpig_article_rewrite.php';

class PugpigRewritePugpigHTMLLinksTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @dataProvider dataProvider
     */
    public function test($body, $expected)
    {
      $this->assertEquals($expected, \_pugpig_rewrite_pugpig_html_links($body));
    }

    public function dataProvider()
    {
        return array(
          array('', ''),
          array('<a href="/2014/11/top-10-op-risks-city-failure/pugpig_index.html">', '<a href="../../2014/11/top-10-op-risks-city-failure/pugpig_index.html">'),
          array('<a href="/2014/11/top-10-op-risks-city-failure/pugpig_index.html"><a href="/2014/11/top-10-op-risks-city-failure/pugpig_index.html">', '<a href="../../2014/11/top-10-op-risks-city-failure/pugpig_index.html"><a href="../../2014/11/top-10-op-risks-city-failure/pugpig_index.html">')
        );
    }
}
