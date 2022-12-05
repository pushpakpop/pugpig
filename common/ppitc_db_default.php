<?php
/**
 * @file
 * Pugpig DB
 */
?><?php
/*

Licence:
==============================================================================
(c) 2011, Kaldor Holdings Ltd
This module is released under the GNU General Public License.
See COPYRIGHT.txt and LICENSE.txt

 */?><?php

// $ppitc_domain = ".ppitc.com";

$vanity_urls = array();


$apps = array(
  array(
    'user' => 'Test',
    'name' => 'Testing Utilities',
    'icon' => '',
    'endpoints' => array(
      'Main Static' => 'http://demo.pugpig.com/testingutilities/generic/editions.xml',
      'High Volume' => 'http://demo.pugpig.com/STANDALONE/content_test/entry.php/editions-atom.xml',
      'WordPress 1' => 'http://test.wordpress.demo.pugpig.com/feed/opds/',
      'WordPress 2' => 'http://test.wpnow.demo.pugpig.com/feed/opds/?internal=true',
      'WordPress 3' => 'http://test2.wpnow.demo.pugpig.com/feed/opds/?internal=true',
      'Drupal Store' => 'http://drupalstoretest.demo.pugpig.com/editions-internal.xml'
    ),
  ),
  array(
    'user' => 'Test',
    'name' => 'Nonsense',
    'icon' => '',
    'endpoints' => array(
      'Bad URL' => 'nothing.rubbish',
      'Bad DNS' => 'http://this.is.not.really.at.all',
      'Not Found' => 'http://pugpig.com/complete/nonsense',
      'Not XML' => 'http://demo.pugpig.com/robots.txt',
      'Not OPDS' => 'http://www.xmlfiles.com/examples/note.xml'
    ),
  ),    
);
