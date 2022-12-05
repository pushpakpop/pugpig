<?php
/**
 * @file
 * Pugpig Standalone PHP tests
 */
?><?php
/*

Licence:
==============================================================================
(c) 2011, Kaldor Holdings Ltd
This module is released under the GNU General Public License.
See COPYRIGHT.txt and LICENSE.txt

 */?><?php

include_once 'pugpig_utilities.php';
include_once 'pugpig_interface.php';

  pugpig_interface_output_header("Pugpig - Standalone Tests");

?>

<h1><img src="images/pugpig-32x32.png" style="vertical-align: text-bottom;"/> Pugpig PHP Suite (Version <?php echo esc_html( pugpig_get_standalone_version() ); ?>)</h1>

<?php

if (!file_exists('standalone_config.php')) {
	echo "<h2>Warning - standalone_config.php not found</h2>";
	echo "In order to use these pages, you will need to configure settings in the file: <code>standalone_config.php</code>";
} else {
	include_once 'standalone_config.php';
}
?>

<h2>Testing Tools</h2>

<p>Sample High Volume Content Endpoint: <a href="content_test/entry.php/editions-atom.xml">(Atom)</a> <a href="content_test/entry.php/editions.xml">(Package)</a> <a href="content_test/entry.php/newsstand.xml">(Newsstand Atom)</a> <br/>
Point your app at the endpoint used in this example to test how your app copes with many editions.
In particular, ensure your edition selector doesn't run out of memory and crash.</p>
<p>You can configure the endpoint to include as many editions as you'd like by adding ?start_num=xxx&amp;num_editions=yyy to the URL.</p>
<p>The package feed only lists packaged editions and so may not show all the entries that you are expecting. To build the packages, you can browse to <a href="content_test/entry.php/build-packages">content_test/entry.php/build-packages</a>.  Parameters can be used to set the start and end editions to package as well as forcing re-packaging of content that has already been packaged.  To do this, use a URL request like this - <a href="content_test/entry.php/build-packages?start_num=1&num_editions=5&force">content_test/entry.php/build-packages?start_num=1&num_editions=5&force</a> - minimum one edition.</p>
<p>Also, please note the following:
<ul>
  <li>Every 3rd edition is draft</li>
  <li>Every 7th edition is free</li>
  <li>Every 9th edition updates on every request</li>
  <li>Every 5th article has a sharing link</li>
  <li>Every 7th article is set to hidden from the table of contents</li>
  <li>The edition is split into 5 sections</li>
  <li>Every edition number ending in 0, 2, 5, or 7 has no subtitle and displays the date</li>
  <li>Every edition number ending in 1, 3, 6, or 8 has an empty subtitle and does not displays the date</li>
  <li>Every edition number ending in 4 has a normal subtitle and does not displays the date</li>
  <li>Every edition number ending in 9 has a long subtitle and does not displays the date</li>
  <li>The 10th most recent edition and ones following intentionally cause errors</li>
  <li>The 20th most recent edition is a tombstone 50% of the time</li>
</ul>
</p>

<p>
<a href="auth_test/test_form.php">Sample Auth Endpoint with Test Form and Test Data</a><br />
Point your app at the endpoint used in this example to test all the edge cases. This includes simulating
all manner of server errors and timeouts, as well as subscribers that continually change their states.
</p>

<p>
<a href="standalone_pugpig_subs_test_page.php">Configurable External Subscription Test Page</a><br />
This can be use to test a third party subscription integration that follows <a href="https://pugpig.zendesk.com/hc/en-us/articles/201239965-Server-Side-Security-interfaces"> the specification</a>. You will need to set configuration values the standalone_config.php file:
<ul>
  <li>Your subscription endpoint URLs</li>
  <li>The credential parameters your endpoint accepts (e.g. username and password)</li>
  <li>Any test users you wish to use</li>
  <li>Your Pugpig secret used to generate Pugpig credentials</li>
 </ul>
</p>

<p>Sample EPUB endpoint: <a href="epub_test/index.xml">Royalty-free Gutenburg books</a>

<h2>Receipt Validators</h2>

<p>
<a href="standalone_pugpig_itunes_edition_credentials.php">Configurable iTunes Receipt Validator</a><br />
This will fail unless a receipt is POSTed to the URL. In order to use this, you'll need to provide
configuration in the standalone_config.php file:
<ul>
  <li>Your iTunes app store password</li>
  <li>The common iTunes prefix used by all subscription products</li>
  <li>Your Pugpig secret used to generate Pugpig credentials</li>
 </ul>
</p>

<p><a href="standalone_pugpig_google_receipt_validation.php">Configurable Google Receipt Validator</a><br />
Performs a very simple validation of the setup including a PHPSecLib installation check.</p>

<p>
  <a href="standalone_pugpig_amazon_receipt_validation.php">Configurable Amazon Receipt Validator</a><br />
  In order to use this, you'll need to provide configuration in the standalone_config.php file:
  <ul>
    <li>The Amazon store base URL</li>
    <li>Your Amazon store shared secret</li>
  </ul>
</p>

<p><a href="auth_test/skeleton_key.php?product_id=com.test.12345">Skeleton Key Creds Generator</a><br />
Generates valid credentials blindly. Don't make this public facing with your real secret!</p>

<p>
<a href="stubs/fake_edition_credentials.php">Fake Credential Generator</a><br />
You can POST anything you want to this if you're happy with rubbish credentials that won't be checked.
Only use it while testing your app.
</p>

<h2>Utilities</h2>

<p>
<a href="pugpig_packager_run.php">Packager</a><br />
This can be used to create a package from any external ATOM feed. You can also use it to test any endpoint.
The config values are entered into this form.<br />
</p>
