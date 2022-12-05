<?php
  header("Content-Type: text/html");
  $title = 'Building packages';
?><html>
  <head>
    <title><?php print wp_kses_post($title); ?></title>
    <style type="text/css">
      .exists, .ok {
        color: green;
      }
      .fail {
        color: red;
      }
      .notexists {
        color: blue;
      }
    </style>
  </head>
  <body>
    <h1><?php print wp_kses_post($title); ?></h1>
<?php

$force = array_key_exists('force', $_GET);
if ($force) {
  print "<h2>Forcing re-building existing packages...</h2>";
}

$scheme = (isset($_SERVER['HTTPS']) && ($_SERVER['HTTPS']!='off')) ? 'https' : 'http';
$root = $scheme.'://'.$_SERVER['SERVER_NAME'].':'.$_SERVER['SERVER_PORT'].'/';

print "<ul>";
for ($edition_num=get_number_of_editions(); $edition_num>0 ; $edition_num--) {
  $server_root = $scheme.'://'.$_SERVER['SERVER_NAME'].':'.$_SERVER['SERVER_PORT'];
  $edition_id = urlencode(get_edition_id($edition_num));
  $save_root = urlencode(get_package_dir($edition_num));
  $relative_path = urlencode(ltrim($_SERVER['SCRIPT_NAME'] . '/edition/' . $edition_id, '/'));
  $scheme = (isset($_SERVER['HTTPS']) && ($_SERVER['HTTPS']!='off')) ? 'https' : 'http';
  $atom = urlencode($server_root . $_SERVER['SCRIPT_NAME'] . '/edition/' . $edition_id . '/content.xml');

  $package_url = $server_root . CONTENT_TEST_PATH . 'build-package.php?edition_id='.$edition_id.'&save_root='.$save_root.'&atom='.$atom.'&relative_path='.$relative_path;
  $package_xml_exists = pugpig_get_edition_package_exists($edition_num);

  print wp_kses_post("<li>$edition_id... (<a href=\"$package_url\">build url</a>) ");

  if ($package_xml_exists) {
    print "<span class=\"exists\">already exists</span>";
  } else {
    print "<span class=\"notexists\">does not exist</span>";
  }
  print " - ";

  if ($package_xml_exists && !$force) {
    _print_immediately("skipping");
  } else {
    _print_immediately("building...");

    $ch = curl_init($package_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    if (!empty($proxy_server) && !empty($proxy_port)) {
      curl_setopt($ch, CURLOPT_PROXY, $proxy_server.':'.$proxy_port);
    }

    $packager_html = curl_exec($ch);
    if (!curl_errno($ch)) {
      $info = curl_getinfo($ch);
      print '<span class="ok">request OK (took ' . intval(round($info['total_time'], 2)) . ' seconds)</span>';
    } else {
      print '<span class="fail">request FAILED (error:'.esc_html(curl_error($ch)).')</span>';
    }
    curl_close($ch);
    print " - ";

    $build_ok = endsWith($packager_html, "BUILD_OK");
    if ($build_ok) {
      print '<span class="ok">build SUCCEEDED</span>';
    } else {
      print '<span class="fail">build FAILED</span>';
    }
  }
  _print_immediately("</li>\n");
}

?>
    </ul>
  </body>
</html>
