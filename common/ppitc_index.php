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

 */?>
<?php
header('Content-Type: text/html; charset=utf-8');

include_once "pugpig_packager_helpers.php";
include_once "pugpig_packager.php";
include_once "pugpig_interface.php";
include_once "pugpig_utilities.php";
include_once "pugpig_manifests.php";
include_once "pugpig_feed_parsers.php";
include_once "multicurl.php";
include_once "url_to_absolute/url_to_absolute.php";
include_once "url_to_absolute/add_relative_dots.php";
include_once "ppitc_db_default.php";
@include_once "ppitc_db.php";

define ('PUGPIG_DATE_FORMAT', 'Y-m-d H:i');

if(!defined('PUGPIG_CURL_TIMEOUT')) define('PUGPIG_CURL_TIMEOUT', 20);
$edition_file_root = "";

if (isset($_REQUEST['hosts'])) {
  dumpHosts($apps, $ppdomains);
  exit;
}

$user = (!empty($_REQUEST['user']) ? $_REQUEST['user'] : "");
$opds = (!empty($_REQUEST['opds']) ? $_REQUEST['opds'] : "");
$atom = (!empty($_REQUEST['atom']) ? $_REQUEST['atom'] : "");

// We have an OPDS feed - find the app
$this_app = NULL;
$this_title = NULL;

if (!empty($user)) {
  echo "<h2>Welcome back: ".esc_html( $user )." <a href='?'>[Logout]</a></h2>";
}

if (!empty($opds)) {

  // Find our app
  foreach ($apps as $app) foreach ($app['endpoints'] as $title => $endpoint) {
    if ($opds == $endpoint) {
      $this_app = $app;
      $this_title = $title;
    }
  }

  $edition_root = $disk_root . DIRECTORY_SEPARATOR . $user . DIRECTORY_SEPARATOR . $this_app['name'] . DIRECTORY_SEPARATOR .  $this_title . DIRECTORY_SEPARATOR;

  $edition_file_root = $edition_root . 'ep';
  $edition_zip_root = $edition_root . 'zips';
  $edition_thumbs_root = $edition_root . 'thumbs';

  $q = http_build_query(array('user'=>$user));

  echo "<h3>App:". esc_html($this_app['name']) . " [<a href='?" . esc_attr( $q ) ."'>Change</a/>] [<a href='" . esc_url($opds) . "' target='_blank'>Origin OPDS Feed</a>]</h3>";
     foreach ($ppdomains as $mode => $ppitc_domain) {
        echo " [<a target='_blank' href='http://". esc_attr( $this_title ) . esc_attr( $this_app['name'] ) . esc_attr($user) . esc_attr($ppitc_domain)."'>". esc_html($mode) . " WEB READER</a>] ";
      }

  if ($url = wp_parse_url($opds)) {
    foreach ($ppdomains as $mode => $ppitc_domain) {
        echo " [<a target='_blank' href='http://". esc_attr( $this_title). esc_attr( $this_app['name']. esc_attr($user) . esc_attr($ppitc_domain) . esc_attr($url['path']) . "'>". esc_html($mode) . " OPDS FEED</a>] ";
    }
  }
  // echo "<h3>DISK ROOT: $edition_file_root</h3>";
  echo " [<a target='_blank' href='http://". esc_attr( $this_title ). esc_attr( $this_app['name']. esc_attr($user) . esc_attr($ppitc_domain) . esc_attr($url['path']). "'>". esc_html($mode) . " OPDS FEED</a>] ";
}

if (!empty($atom)) {
  $q = http_build_query(array('user'=>$user, 'opds'=>$opds));

  echo "<h3>Edition: ".esc_html($atom) ." [<a href='?".esc_attr($q)."'>Back to Editions</a>] <a href='" . esc_url($atom). "' target='_blank'>[Origin Atom Feed]</a></h3>";

  $atom_path = wp_parse_url($atom, PHP_URL_PATH);

  if ( !empty( $atom_path ) ) {
    foreach ($ppdomains as $mode => $ppitc_domain) {
      $atom_url = "http://${this_title}.".$this_app['name'].".${user}${ppitc_domain}$atom_path";
      echo " [<a target='_blank' href='". esc_url( $atom_url ) . "'>".esc_html($mode)." ATOM FEED</a>] ";
      if ($mode === 'PREVIEW') {
        echo " [<a target='_blank' href='".esc_url($admin_url)."vfp/static/flatplan.html?atom=".rawurlencode($atom_url)."'>".esc_html($mode)." Visual Flat Plan</a>]";
      }
    }
  }
}

if (!empty($atom)) {
  $preview_base = 'http://'.$this_title.'.'.$this_app['name'].'.'.$this_app['user'].$ppdomains['PREVIEW'].dirname(wp_parse_url($atom, PHP_URL_PATH)).'/';
	showSingleEdition($user, $opds, $atom, $edition_file_root, $edition_zip_root, $preview_base, $edition_thumbs_root);
} elseif (!empty($opds)) {
   showEditionsAndCovers($user, $opds, $edition_file_root);
} elseif (!empty($user)) {
	showAppList($apps, $user);
} else {
	showLogin($apps);
}

if (count($_REQUEST)===0) {
  echo '<br><a href="?hosts">[hosts file entries]</a>';
}
echo "<h3>That's all folks</h3>";

function showSingleEdition($user, $opds, $atom, $edition_file_root, $edition_zip_root, $preview_base, $edition_thumbs_root) {

  $entries = array();

  //$save_path = $edition_file_root . 'opds/' . hash('md5', $opds). '/atom/' . hash('md5', $atom). 'contents.xml';
  $save_path = pugpig_get_local_save_path($edition_file_root, $atom);
  //echo "** Atom: $save_path<br />";

  $entries[$atom] = $save_path;

  $entries = _pugpig_package_download_batch("Atom Feed", $entries);

	// Read the ATOM from the file
	$fhandle = fopen($entries[$atom], 'r');
	$atom_body = fread($fhandle, filesize($entries[$atom]));
	fclose($fhandle);

	// Parse the Atom file
	$atom_ret = pugpig_parser_process_atom($atom_body);
	$theme_updated_ts = strtotime((String)$atom_ret[0]->updated);
  $assets = array();
  echo "Edition Last Updated: " . esc_html( _ago($theme_updated_ts) ) . "<br />\n";
	echo "<table>";
   	$entries = array();
  	foreach ($atom_ret[0]->entry as $n) {
  		$icon = "";
  		$thumbnail = "";
  		$html = "";
      $href = "";
  		$manifest = "";
  		$manifest_save_path = "";
  		$updated_ts = strtotime($n->updated);

    	foreach ($n->link as $l) {
    		if ($l['rel'] == 'related' && $l['type'] == 'text/cache-manifest') {
    			$manifest = url_to_absolute($atom, $l['href']);

  				$manifest_save_path = pugpig_get_local_save_path($edition_file_root, $manifest);
  				//echo "** Manifest: $manifest_save_path<br />";

  				$entries[$manifest] = $manifest_save_path;
  			 }
    		if ($l['rel'] == 'alternate' && $l['type'] == 'text/html') {
          $href = $l['href'];
    			$html = url_to_absolute($atom, $href);

  				$save_path = pugpig_get_local_save_path($edition_file_root, $html);
  				$entries[$html] = $save_path;
    		}


    		if ($l['rel'] == 'icon') $icon = url_to_absolute($atom, $l['href']);
    		if ($l['rel'] == 'thumbnail') $thumbnail = url_to_absolute($atom, $l['href']);

    	}

    	$assets[$html]['title'] = $n->title;
      $assets[$html]['href'] = $href;
    	$assets[$html]['entries'][$manifest] = $manifest_save_path;
    	$assets[$html]['entries'][$html] = $save_path;
    	$assets[$html]['manifest_file'] = $manifest_save_path;
    	$assets[$html]['manifest_url'] = $manifest;
    	$assets[$html]['updated'] = $updated_ts;

  		echo "<tr>";
    	echo "<td><a href='".esc_url($html)."' target='_blank'>".esc_html($n->title)."</a></td>";

    	echo "<td>".esc_html(_ago($updated_ts))." ago.</td>";
    	echo "<td>";
    	echo count($n->category) . " categories";
    	/*
    	foreach ($n->category as $c) {
    		echo "<b>" . $c['scheme'] . "</b>: " . $c['term'];
    		echo "<br />";
    	}
    	*/
    	echo "</td>";
		echo "<td>";
    	if (!empty($thumbnail)) echo "<img src='".esc_url($thumbnail)."' height='60' />";
    	else echo "-";
    	echo "</td>";
    	echo "<td>";
    	if (!empty($icon)) echo "<img src='".esc_url($icon)."' height='60' />";
    	else echo "-";
    	echo "</td>";
    	echo "<td></td>";
    	echo "</tr>";
   	}
   	echo "</table>";

  $entries = _pugpig_package_download_batch("HTML and Manifests", $entries);
  foreach ($assets as $html=>$value) {
  	$fhandle = fopen($value['manifest_file'], 'r');
  	$manifest_body = fread($fhandle, filesize($value['manifest_file']));
  	fclose($fhandle);

  	$assets[$html]['assets'] = _pugpig_package_get_asset_urls_from_manifest($manifest_body, array(), $value['manifest_url'], 'page');
  	$assets[$html]['theme'] = _pugpig_package_get_asset_urls_from_manifest($manifest_body, array(), $value['manifest_url'], 'theme');
  }

  $asset_entries = array();
  $theme_entries = array();

  foreach ($assets as $html=>$value) {
  	//echo "<a href='".$value['manifest_url']."'>*</a>" . count($value['assets']) . "[theme: " . count($value['theme']) . "]: " . $html . "<br />";
  	foreach ($value['assets'] as $asset_url) {
    	$asset_url = url_to_absolute($value['manifest_url'], $asset_url);
  		$asset_entries[$asset_url] = pugpig_get_local_save_path($edition_file_root, $asset_url);
  		$assets[$html]['entries'][$asset_url] = pugpig_get_local_save_path($edition_file_root, $asset_url);
  	}
  	foreach ($value['theme'] as $asset_url) {
    	$asset_url = url_to_absolute($value['manifest_url'], $asset_url);
  		$theme_entries[$asset_url] = pugpig_get_local_save_path($edition_file_root, $asset_url);
  	}
  }

  // Download and ZIP the theme assets
  $theme_entries = _pugpig_package_download_batch("Theme Static Files", $theme_entries);
  echo esc_html(pugpig_ppitc_create_zip($edition_zip_root, "pp-theme", $theme_entries, $theme_updated_ts));

  // Download the static files
  $asset_entries = _pugpig_package_download_batch("Asset Static Files", $asset_entries);

  // Create a ZIP for each HTML page
  foreach ($assets as $html=>$value) {
    $hash = hash('md5', $value['href']);
    // create the thumbnail
    list($thumb_leaf, $thumb_log) = _pugpig_ppitc_render_thumbnail($edition_thumbs_root, $preview_base, $value['href'], $hash, $value['updated']);
    $thumb_url = $preview_base . $thumb_leaf;

    echo "<div style=\"clear:left;padding-top:4px;padding-bottom:4px;\">";
    echo "<a href='".esc_url($thumb_url)."' target='blank'><img style=\"float:left;width:64px;height:72px;margin-right:4px;border:1px solid #043080\" src=\"".esc_url($thumb_url)."\"></a>";
    echo "<a href='".esc_url($html)."' target='blank'>(".esc_url($value['href']).") ".esc_html($value['title'])."</a>:<br>";
    // create the zip (todo: optionally add thumbnail to zip?)
    echo esc_html($thumb_log);
    pugpig_ppitc_create_zip($edition_zip_root, "pp-article-${hash}", $value['entries'], $value['updated']);
    echo "</div>";
  }
}

function _pugpig_ppitc_render_thumbnail($edition_thumbs_root, $preview_base, $path, $hash, $ts) {
  $thumb_root  = $edition_thumbs_root . DIRECTORY_SEPARATOR . date('Y\\'. DIRECTORY_SEPARATOR . 'm\\'. DIRECTORY_SEPARATOR . 'd' ,$ts) . DIRECTORY_SEPARATOR;
  $thumb_leaf_original = "pp-thumb-${hash}-${ts}-original.png";
  $thumb_leaf = "pp-thumb-${hash}-${ts}.jpg";
  $thumb_filename_original = $thumb_root . $thumb_leaf_original;
  $thumb_filename = $thumb_root . $thumb_leaf;
  $render_response = '';
  $convert_response = '';

  if (file_exists($thumb_filename)) {
    $colour = 'grey';
    $verb = 'Found';
  } else {
    $colour = 'green';
    $verb = 'Created';
    if (!file_exists($thumb_root)) {
      mkdir($thumb_root, 0777, true);
    }
    $js = __DIR__ . DIRECTORY_SEPARATOR . 'ppitc_thumbs.js';
    $page_preview_url = $preview_base . $path;
    $create_response = shell_exec("phantomjs $js $page_preview_url $thumb_filename_original");

    if (file_exists($thumb_filename_original)) {
      $convert_response = shell_exec("convert $thumb_filename_original -quality 75 -resize 200 $thumb_filename");
    }
  }

  if (file_exists($thumb_filename)) {
    $log_message = "<font color='$colour'>$verb thumb $thumb_filename [" . pugpig_bytestosize(filesize($thumb_filename)) . "]<br />";
  } else {
    $thumb_leaf = '';
    if (file_exists($thumb_filename_original)) {
      $log_message = "<font color='red'>Failed to convert to $thumb_filename [".htmlspecialchars($convert_response)."]<br />";
    } else {
      $log_message = "<font color='red'>Failed to render $thumb_filename [".htmlspecialchars($render_response)."]<br />";
    }
  }

  if (file_exists($thumb_filename_original)) {
    unlink($thumb_filename_original);
  }

  return array($thumb_leaf, $log_message);
}


/************************************************************************
Takes the name of a zip file, and an array of files in the format
src => dest
************************************************************************/

function pugpig_ppitc_create_zip($edition_zip_root, $zip_prefix, $entries, $ts) {
  $edition_zip_root  .= DIRECTORY_SEPARATOR . date('Y\\'. DIRECTORY_SEPARATOR . 'm\\'. DIRECTORY_SEPARATOR . 'd' ,$ts) . DIRECTORY_SEPARATOR;
 	$zip_path = pugpig_get_local_save_path($edition_zip_root,  $zip_prefix . "-" . $ts . ".zip");

  echo "<font color='green'>ZIP ".esc_html($zip_path)."</font><br />";

  // Make the folder
  if (!file_exists($edition_zip_root)) {
    mkdir($edition_zip_root, 0777, true);
  }

 	if (!file_exists($zip_path)) {
		$zip = new ZipArchive();
		if ($zip->open($zip_path, ZipArchive::CREATE) === TRUE) {
      foreach ($entries as $src => $dest) {
        $src = pugpig_strip_domain($src);
        if (startsWith($src, '/')) {
          $src = substr($src, 1);
        }
        $zip->addFile($dest, $src);
      }
      $zip->close();
      if (file_exists($zip_path)) {
        echo "<font color='green'>Created ZIP ".esc_html($zip_path).":<br>".count($entries)." file(s)</font> [". esc_html(pugpig_bytestosize(filesize($zip_path))). "]<br />";
      }
    }
	} else {
    echo "<font color='grey'>Found ZIP ".esc_html($zip_path).":<br>".count($entries)." file(s)</font> [". esc_html(pugpig_bytestosize(filesize($zip_path))). "]<br />";
	}
}

function showEditionsAndCovers($user, $opds, $edition_file_root) {

  $entries = array();
  $save_path = pugpig_get_local_save_path($edition_file_root, $opds);
  // Remove the query string
  $save_path = preg_replace('/\/?\?.*/', '', $save_path);

  $entries[$opds] = $save_path;
  $entries = _pugpig_package_download_batch("OPDS Feeds", $entries);

  $format_failures = array();
  foreach (array_keys($entries) as $entry) {

    // Read the ATOM from the file
    $fhandle = fopen($entries[$entry], 'r');
    $opds_body = fread($fhandle, filesize($entries[$entry]));
    fclose($fhandle);

      // Parse the OPDS file
    $opds_ret = _pugpig_package_parse_opds($opds_body);
    if (!empty($opds_ret['failure'])) {
      echo "<font color='red'>Not Valid OPDS: ".esc_html($opds_ret['failure'])."</font>";
      return;
    }

    echo "<h1>Your Editions</h1>";

    $covers = array();
    echo "<table>";
    foreach ($opds_ret['editions'] as $edition) {
      echo "<tr>";

      $cover_url = url_to_absolute($opds, $edition['cover']);
      $atom_url = url_to_absolute($opds, $edition['url']);

	  $cover_save_path = pugpig_get_local_save_path($edition_file_root, $cover_url);

      // $save_path = $edition_file_root . 'cover/' . hash('md5', $edition['cover']). '.jpg';
      if (count($covers) < 10) {
      	$covers[$cover_url] = $cover_save_path;
      	// showSingleEdition($user, $opds, $atom_url, $edition_file_root);

      }
        echo("<td><img height='80' src='".esc_url($cover_url)."' /></td>");
        echo "<td>";
        echo "<b>" . esc_html($edition['title']) . "</b><br />";
        echo "<i>" . wp_kses_post($edition['summary']) . "</i><br />";
        $updated_ts = strtotime($edition['updated']);
        echo esc_html(_ago($updated_ts)) ." ago) - (".  esc_html($edition['updated'])  .") ( ".esc_html( $updated_ts) ." )<br />";
        echo ($edition['draft'] ? "<font color='orange'>DRAFT</font> " : "");
        echo ($edition['free'] ? "free" : "paid") .  ($edition['samples'] ? " with samples" : "") ;
        echo "<br />";
        echo "</td>";
        echo "<td>";
        //echo count($edition['categories']) . " categories";

        foreach ($edition['categories'] as $schema=>$term) {
          echo "<b>".esc_html($schema)."</b>: ".esc_html($term)."<br />";
        }

        echo "</td>";
        echo "<td>";

        if ($edition['type'] == 'atom') {
        	$q = http_build_query(array('opds'=>$opds, 'atom'=>$atom_url, 'user'=>$user));
        	echo "<a href='?".esc_attr($q)."'>TEST PAGES</a><br />\n";
        } else {
        	echo "EPUB<br />";
        }
        echo "<a href='". esc_url(url_to_absolute($opds, $atom_url)) . "' target='_blank'>FEED</a></br />";
        echo "FLATPLAN</br />";
        echo "PREVIEW IN WEB<br />";
      echo "</tr>";

    }
    echo "</table>";

    $entries = _pugpig_package_download_batch("Valdating Covers (only 10)", $covers);

  }

}


function showLogin($apps) {
	echo "<h2>Please log in:</h2>";
	$users = array();
	foreach ($apps as $app) $users[$app['user']] = $app['user'];
	foreach ($users as $user) {
		$q = http_build_query(array('user'=>$user));
		echo "<a href='?".esc_attr($q)."'>".esc_html($user)."</a><br />\n";
	}
}


function showAppList($apps, $user) {
	  echo "<h2>Your Apps</h2>";

  foreach ($apps as $app) if ($app['user'] == $user) {

    echo "<h3>".esc_html($app['name'])."</h3>";

    foreach ($app['endpoints'] as $title => $endpoint) {
      $q = http_build_query(array('opds'=>$endpoint, 'user'=>$user));
      echo "<a href='?".esc_attr($q)."'>".esc_html($title)."</a>: ".esc_html($endpoint)."<br />\n";
    }
  }

}


function dumpHosts($apps, $ppdomains) {
  header('Content-Type: text/plain');
  echo "# PPitC development hosts\n";
  echo "#########################\n";
  foreach ($apps as $app) {
    $user = $app["user"];
    $name = $app["name"];
    foreach ($app['endpoints'] as $title => $endpoint) {
      foreach ($ppdomains as $mode => $ppitc_domain) {
        echo esc_html(str_pad("127.0.0.1 ${title}.${name}.${user}${ppitc_domain} ", 72)) . "# ".esc_html($endpoint)."\n";
      }
    }
  }
  echo "#########################\n";
}
