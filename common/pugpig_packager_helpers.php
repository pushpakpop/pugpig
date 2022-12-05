<?php
/**
 * @file
 * Pugpig Packager Helpers
 */
?><?php
/*

Licence:
==============================================================================
(c) 2011, Kaldor Holdings Ltd
This module is released under the GNU General Public License.
See COPYRIGHT.txt and LICENSE.txt

 */?><?php

function _getHeadersFromString($s) {
  $headers_array = array();
  foreach (explode("\n", $s) as $h) {
    $x = explode(": ", $h);
    if (count($x) == 2) $headers_array[strtolower(trim($x[0]))] = trim($x[1]);
    /*
    if (count($x) == 1) {
    	$v = trim($x[0]);
    	if (startsWith($v, "HTTP")) {
    		$parts = explode(" ", $v);
    		$headers_array['http_response'] = $parts[1];
    	}
    }
    */

  }

  return $headers_array;
}

// Get the disk path
function pugpig_get_local_save_path($root, $absolute_download_url) {
      return $root . _pugpig_package_url_remove_domain($absolute_download_url);
}

function pugpig_validate_file($file, $mime) {
	// CHECK UTF-8??
	if (!file_exists($file)) return "No file";

	if (FALSE) {
		return "File encoding is not UTF-8";
	}

	// Check XML
	if (strpos($mime, 'xml') !== FALSE || endsWith($file, '.xml')) {
		$f = file_get_contents($file);
      	return check_xml_is_valid($f);
	}

	// Check JSON
	if (startsWith($mime, 'application/json') || endsWith($file, '.json')) {
		$f = file_get_contents($file);
 		$json = json_decode($f);
 		$err = json_last_error();
 		if ($err == JSON_ERROR_NONE) return "";
 		return "Error: $err";
	}

	// Check Manifests
	if (startsWith($mime, 'text/cache-manifest') || endsWith($file, '.manifest')  || endsWith($file, '.appcache')) {
		$f = file_get_contents($file);
      	if (!startsWith($f, "CACHE MANIFEST")) return "Manifest did not start with CACHE Manifest. Instead got:\n$f";
	}

	return "";
}


function pugpig_get_download_char($path, $mime = NULL)
 {
  $char = '';

  if (!empty($mime)) {
    $mime = strtolower($mime);
    if (startsWith($mime, 'application/atom+xml')) $char = 'a';
    if (startsWith($mime, 'application/pugpigpkg+xml')) $char = 'p';
    if (startsWith($mime, 'application/xml')) $char = 'x';
    if (startsWith($mime, 'application/pdf')) $char = 'p';
    if (startsWith($mime, 'audio/mpeg')) $char = 'a';
    if (startsWith($mime, 'text/xml')) $char = 'x';
    if (startsWith($mime, 'text/html')) $char = 'h';
    if (startsWith($mime, 'text/plain')) $char = 't';
    if (startsWith($mime, 'text/cache-manifest')) $char = 'm';
    if (startsWith($mime, 'application/x-font')) $char = 'f';
    if (startsWith($mime, 'image')) $char = 'i';
    if (startsWith($mime, 'text/css')) $char = 'c';
    if (startsWith($mime, 'application/json')) $char = 'j';
    if (startsWith($mime, 'text/javascript')) $char = 'j';
    if (startsWith($mime, 'application/javascript')) $char = 'j';
    if (startsWith($mime, 'application/x-javascript')) $char = 'j';
    if (startsWith($mime, 'application/octet-stream')) $char = '?';
  }
  if (!empty($char) && $char != 't') return $char;

  if (!empty($path)) {
 	$extension = '';
    $path_parts = pathinfo($path);
    if (isset($path_parts['extension'])) {
      $extension = $path_parts['extension'];
    }
    $extension = strtolower($extension);

    if (startsWith($extension, 'xml')) $char = 'x';
    if (startsWith($extension, 'manifest')) $char = 'm';
    if (startsWith($extension, 'appcache')) $char = 'm';
    if (startsWith($extension, 'html')) $char = 'h';
    if (startsWith($extension, 'mp3')) $char = 'a';
    if (startsWith($extension, 'pdf')) $char = 'p';
    if (startsWith($extension, 'ttf')) $char = 'f';
    if (startsWith($extension, 'otf')) $char = 'f';
    if (startsWith($extension, 'txt')) $char = 't';
    if (startsWith($extension, 'jpg')) $char = 'i';
    if (startsWith($extension, 'jpeg')) $char = 'i';
    if (startsWith($extension, 'png')) $char = 'i';
    if (startsWith($extension, 'gif')) $char = 'i';
    if (startsWith($extension, 'css')) $char = 'c';
    if (startsWith($extension, 'js')) $char = 'j';
    if (startsWith($extension, 'sass')) $char = '!';
    if (startsWith($extension, 'db')) $char = '!';
  }
  if (!empty($char)) return $char;


  return '*';

}

function pugpig_check_suspect_path($http_url) {
 if (strpos($http_url, "/docs/") !== FALSE
    || strpos($http_url, "/static/") !== FALSE
    || strpos($http_url, "/examples/") !== FALSE
    || strpos($http_url, "screenshot.png") !== FALSE
    || strpos($http_url, ".mustache") !== FALSE
    || strpos($http_url, ".sass") !== FALSE
    || strpos($http_url, ".map") !== FALSE
    || strpos($http_url, ".md") !== FALSE
    || strpos($http_url, ".bak") !== FALSE
    || strpos($http_url, ".php") !== FALSE
    || strpos($http_url, ".rb") !== FALSE
    || strpos($http_url, ".db") !== FALSE
  ) return TRUE;
 return FALSE;
}

function _pugpig_get_cache_layer($headers_array) {

        if (isset($headers_array["x-cache"])) {
           $ah = $headers_array["x-cache"];
           if (strpos($ah, 'Akamai') !== FALSE) if (strpos($ah, "HIT") !== FALSE) return "AKAMAI";
           if (strpos($ah, 'cloudfront') !== FALSE) if (strpos($ah, "Hit") !== FALSE) return "CLOUDFRONT";
         }

        if (isset($headers_array["x-pugpigintheclouds"]) && isset($headers_array["x-cache"])) {
            $ah = $headers_array["x-cache"];
            if (strpos($ah, "HIT") !== FALSE) return "PPITC";
        }


        if (isset($headers_array["x-varnish"])) {
           $vh = $headers_array["x-varnish"];
           if (strpos($vh, " ")) return "VARNISH";
        }

        if (isset($headers_array["x-powered-by"])) {
          $powered_by = $headers_array["x-powered-by"];
           if (strpos($powered_by, "PHP") !== FALSE) return "PHP";
        }

        if (isset($headers_array["server"])) return strtoupper($headers_array["server"]);


        return "";
}


function _pugpig_show_batch($rows, $file_warning_size_kb = 250, $file_warning_time_secs = 10) {
  echo "<table style='font-size:small;''>\n";
  echo "<tr>";
  echo "<th colspan='5'>Metrics</th>";
  echo "<th colspan='2'>Cache Headers</th>";
  echo "<th colspan='4'>Edge</th>";
  echo "<th></th>";
  echo "</tr>";
  echo "<tr>";
  echo "<th>HTTP</th>";
  echo "<th>Time</th>";
  echo "<th>Type</th>";
  echo "<th>Size</th>";
  echo "<th>Valid</th>";
  echo "<th>Modified</th>";
  echo "<th>Expires</th>";
  echo "<th>Server</th>";
  echo "<th>Encoding</th>";
  echo "<th>Auth</th>";
  echo "<th>Status</th>";
  echo "<th>URL</th>";
  echo "</tr>";
  foreach ($rows as $key => $vals) {
    echo "<tr>";
	$vals['h']  = _getHeadersFromString($vals['headers']);

    if (isset($vals['curl_info'])) {
    	$http_code = $vals['curl_info']['http_code'];
    	$http_code_style = '';
    	if ($http_code != 200) $http_code_style = 'background:#ff6600';
      	echo wp_kses_post("<td style='$http_code_style'>$http_code</td>\n");
		$time = $vals['curl_info']['total_time'];
		$percentage= $time / $file_warning_time_secs * 100;
		$barcolor = "lightblue";
		if ($percentage > 100) $barcolor = "orange";
		if ($percentage > 200) $barcolor = "red";
		$time_style = "white-space: nowrap; background: -webkit-gradient(linear, left top,right top, color-stop($percentage%,$barcolor), color-stop($percentage%,white))";

      	echo "<td style='" . esc_attr( $time_style ) . "'>" . esc_html( $vals['curl_info']['total_time'] ) ."</td>\n";
    } else {
      	echo "<td> - </td>\n";
      	echo "<td> - </td>\n";
    }

    $content_type = "";
    $content_type_style = "";

    if (isset($vals['h']['content-type'])) $content_type = $vals['h']['content-type'];
    else $content_type_style = 'background: pink';

    $char = pugpig_get_download_char($key, $content_type) ;
    echo "<td style=' " . esc_attr($content_type_style) . "'>" . esc_html($char) . "</td>";

    $bytes = 0;
    if (file_exists($vals['file'])) $bytes =  filesize($vals['file']);
	$percentage= $bytes / (1024*$file_warning_size_kb) * 100;
	$barcolor = "lightblue";
	if ($percentage > 100) $barcolor = "orange";
	if ($percentage > 200) $barcolor = "red";

	$size_style = "white-space: nowrap; background: -webkit-gradient(linear, left top,right top, color-stop($percentage%,$barcolor), color-stop($percentage%,white))";

    echo "<td style='" . esc_attr($size_style) . "'>". esc_html( pugpig_bytestosize($bytes) ) . "</td>\n";

    $error = pugpig_validate_file($vals['file'], $content_type);
    if (!empty($error)) {
    	echo "<td style='background: pink'>" . wp_kses_post($error) . "</td>\n";
    } else {
    	echo "<td>Y</td>\n";
    }

	if (isset($vals['h']['last-modified'])) {
	    echo "<td>". esc_html( _ago(strtotime($vals['h']['last-modified']),  0, true) ) ." ago</td>\n";
    } else {
    	echo "<td style='background: pink'>?</td>\n";
    }

    if (isset($vals['h']['expires'])) {
    	echo "<td>in ". esc_html( _ago(strtotime($vals['h']['expires']), 0, false) ) ."</td>\n";
    } else if (isset($vals['h']['etag'])) {
	    echo "<td>eTag</td>\n";
    } else {
    	echo "<td style='background: pink'>?</td>\n";
    }

    $cache_layer =  _pugpig_get_cache_layer($vals['h']);

    $cache_layer_style = "";
    if (in_array($cache_layer, array('PPITC', 'AKAMAI','CLOUDFRONT'))) $cache_layer_style = 'background:#6ce1c4';
    elseif (in_array($cache_layer, array('VARNISH'))) $cache_layer_style = 'background:#66ccff';
    elseif (in_array($cache_layer, array('PHP'))) $cache_layer_style = 'background:#b20047';
    echo "<td style='" . esc_attr( $cache_layer_style ) . "'> ". wp_kses_post( $cache_layer ) . "</td>\n";

    $content_encoding = "";
    $content_encoding_style = '';
    if (isset($vals['h']['content-encoding'])) $content_encoding .= $vals['h']['content-encoding'] . " ";
    if (isset($vals['h']['transfer-encoding'])) $content_encoding .= $vals['h']['transfer-encoding'] . " ";
    if (in_array($content_encoding, array('gzip'))) $content_encoding_style = 'background:#6ce1c4';
    echo "<td style='" . esc_attr( $content_encoding_style ) . "'>" . wp_kses_post( $content_encoding ) ."</td>\n";

    if (isset($vals['h']["x-pugpig-entitlement"])) {
    	echo "<td>LOCKED</td>\n";
    } else {
    	echo "<td>-</td>\n";
    }

    $status = "";
    if (isset($vals['h']["x-pugpig-status"])) $status .= $vals['h']["x-pugpig-status"];
    echo "<td>" . esc_html( $status ) . "</td>\n";

    $suspect_style = pugpig_check_suspect_path($key) ? 'background:#ff6600' : ($char == 'f' ? 'background:#66ccff' : '');
    echo "<td style=' " . esc_attr( $suspect_style ) ."'><a title='".esc_attr( $vals['headers'] )."' target='_blank' href='". esc_url( $key ) ."'>". esc_html( pugpig_strip_domain($key) ) ."</a></td>\n";

    echo "</tr>";
  }
  echo "</table>\n";

}
