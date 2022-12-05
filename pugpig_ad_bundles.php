<?php
/**
 * @file
 * Pugpig WordPress Admin Screens
 */
?><?php
/*

Licence:
==============================================================================
(c) 2011, Kaldor Holdings Ltd
This module is released under the GNU General Public License.
See COPYRIGHT.txt and LICENSE.txt

 */?><?php

define( 'PUGPIG_AD_BUNDLE_POST_TYPE', 'pugpig_ad_bundle');

/************************************************************************
Create a new custom type for ad_bundles
************************************************************************/
add_action( 'admin_notices', 'pugpig_adbundles_admin_notice' );


function pugpig_adbundles_admin_notice() {

  $allowed_types = get_site_option('upload_filetypes');
  if (stripos($allowed_types, "zip") === FALSE) {
      ?><div class="update-nag"><p><?php _e( 'Pugpig - Ad Bundles require zips to be in the allowed upload types.' ); ?></p></div>
      <?php
  }
}

add_action('init', 'pugpig_ad_bundles_register', 50);

function pugpig_ad_bundles_register()
{
  $labels = array(
    'name' => _x('HTML Zips', 'post type general name'),
    'singular_name' => _x('HTML Zip', 'post type singular name'),
    'add_new' => _x('Add New', 'HTML Zip item'),
    'add_new_item' => __('Add New Pugpig HTML Zip'),
    'edit_item' => __('Edit HTML Zip Item'),
    'new_item' => __('New HTML Zip Item'),
    'view_item' => __('View HTML Zip Item'),
    'search_items' => __('Search HTML Zips'),
    'not_found' =>  __('Nothing found'),
    'not_found_in_trash' => __('Nothing found in Trash'),
    'parent_item_colon' => ''
  );

  $args = array(
    'labels' => $labels,
    'singular_label' => $labels['singular_name'],
    'public' => true,
    'publicly_queryable' => true,
    'show_ui' => true,
    'query_var' => true,
    'rewrite' => true,
    'capability_type' => 'post',
    'hierarchical' => false,
    'menu_position' => 5,
    'has_archive' => true,
    'supports' => array('title', 'excerpt','thumbnail') // Custom Fields for debug 'custom-fields'
    );

  register_post_type( PUGPIG_AD_BUNDLE_POST_TYPE , $args );

  register_taxonomy_for_object_type('post_tag', PUGPIG_AD_BUNDLE_POST_TYPE);
  register_taxonomy_for_object_type('category', PUGPIG_AD_BUNDLE_POST_TYPE);

  // Add a taxonomy if the settings say we need one
  $taxonomy_name = get_option("pugpig_opt_taxonomy_name");
  if (!empty($taxonomy_name) && taxonomy_exists($taxonomy_name)) {
    register_taxonomy_for_object_type($taxonomy_name, PUGPIG_AD_BUNDLE_POST_TYPE);
  }
}

/************************************************************************
Custom fields required for an ad_bundle
************************************************************************/
function pugpig_ad_bundle_info()
{
  global $post;
  $custom = get_post_custom($post->ID);

  $zip_id = null;
  if (isset($custom["ad_bundle_zip_file"])) {
    $zip_id = $custom["ad_bundle_zip_file"][0];
  }

  $html_file = null;
  if (isset($custom["ad_bundle_html_file"])) {
    $html_file = $custom["ad_bundle_html_file"][0];
  }

  $html_hidden = FALSE;
  if (isset($custom["ad_bundle_hide_from_toc"]) && $custom["ad_bundle_hide_from_toc"][0] == 'on') {
    $html_hidden = TRUE;
  }

  ?>
  <label>ZIP file:</label>
<?php
     if (!empty($zip_id) && $zip_id != '0') {
       echo ' <b><a href="' . esc_url(wp_get_attachment_url($zip_id)) . '">' . esc_html(basename(get_attached_file($zip_id))) . '</a></b> &nbsp; | &nbsp; Replace with: ';
     }
?>
  <input type="file" name="ad_bundle_zip" />
  <p>The ZIP file containing the HTML and assets. (All paths referenced in the HTML should be relative.)</p>

  <hr />

  <label>HTML file:</label>
  <input name="ad_bundle_html_file" value="<?php echo esc_attr($html_file); ?>" />
  <p>The HTML file from within the ZIP that will be used in the edition. It is automatically detected by scanning through the ZIP file contents for the first HTML file, but you may override it.</p>
  <a href="<?php echo esc_url(pugpig_ad_bundle_get_unzip_url($post->ID)) . esc_attr($html_file) ?>" target="_blank">View the HTML file</a>

  <br><br>
  <label>Hide from Table of Contents:</label>
  <input name="ad_bundle_hide_from_toc" type="checkbox" id="ad_bundle_hide_from_toc" <?php if ($html_hidden) echo "checked"; ?>  />
  <p>Hide the HTML file from the table of contents in the app.</p>
<?php
}

/************************************************************************
Ad Bundle Edit Boxes on the Ad Bundle Screen and the Post screen
************************************************************************/
add_action("admin_init", "pugpig_ad_bundle_init");
function pugpig_ad_bundle_init()
{
  add_meta_box("pugpig-ad_bundle_info-meta", "HTML Zip Info", "pugpig_ad_bundle_info", PUGPIG_AD_BUNDLE_POST_TYPE, "normal", "high");
}

/************************************************************************
Icons
************************************************************************/
add_action( 'admin_head', 'pugpig_ad_bundle_icons' );

function pugpig_ad_bundle_icons()
{
    ?>
    <style type="text/css" media="screen">
        #menu-posts-pugpig_ad_bundle .wp-menu-image {
            background: url(<?php echo esc_url(BASE_URL) ?>images/pugpig_ad_bundle-icon.png) no-repeat 6px -16px !important;
        }
        #menu-posts-pugpig_ad_bundle:hover .wp-menu-image, #menu-posts-pugpig_ad_bundle.wp-has-current-submenu .wp-menu-image {
            background-position:6px 8px !important;
        }
    #icon-edit.icon32-posts-pugpig_ad_bundle {background: url(<?php echo esc_url(BASE_URL) ?>common/images/pugpig-32x32.png) no-repeat;}
    </style>
<?php }

/****
Add the multipart enctype to the form tag so that we can upload a file
 ***/
add_action('post_edit_form_tag', 'pugpig_ad_bundle_form_tag');
function pugpig_ad_bundle_form_tag()
{
    echo ' enctype="multipart/form-data"';
}

/****
Save uploaded ZIP
****/
add_action('save_post', 'pugpig_ad_bundle_save_post');
function pugpig_ad_bundle_save_post()
{
    global $post;
    if (empty($post) || !isset($post->post_type) || $post->post_type !== PUGPIG_AD_BUNDLE_POST_TYPE){
      return;
    }

    if (isset($_POST['post_type']) && strtolower($_POST['post_type']) === 'page') {
        if (isset($post_id) && !current_user_can('edit_page', $post_id)) {
            return $post_id;
        }
    } else {
        if (isset($post_id) && !current_user_can('edit_post', $post_id)) {
            return $post_id;
        }
    }

    if (!empty($_FILES['ad_bundle_zip']) && $_FILES['ad_bundle_zip']['size'] > 0) {
        $file   = $_FILES['ad_bundle_zip'];
        $upload = wp_handle_upload($file, array('test_form' => false));

        if (!isset($upload['error']) && isset($upload['file'])) {
            $wp_filetype = wp_check_filetype(basename($upload['file']), null);
            $title       = $file['name'];
            $ext         = strrchr($title, '.');
            $title       = ($ext !== false) ? substr($title, 0, -strlen($ext)) : $title;
            $attachment  = array(
                'post_mime_type'    => $wp_filetype['type'],
                'post_title'        => addslashes($title),
                'post_content'      => '',
                'post_status'       => 'inherit',
                'post_parent'       => $post->ID
            );

            $attach_id  = wp_insert_attachment($attachment, $upload['file']);
            $existing_download = (int) get_post_meta($post->ID, 'ad_bundle_zip_file', true);

            if (is_numeric($existing_download)) {
                wp_delete_attachment($existing_download);
            }

            update_post_meta($post->ID, 'ad_bundle_zip_file', $attach_id);

      $html_file = pugpig_ad_bundle_process($attach_id);

      update_post_meta($post->ID, 'ad_bundle_html_file', $html_file);
      }
    } elseif (isset($_POST['ad_bundle_html_file'])) {
        $html_file = $_POST['ad_bundle_html_file'];
        update_post_meta($post->ID, 'ad_bundle_html_file', $html_file);
    }

    $html_hidden = '';
    if (isset($_POST['ad_bundle_hide_from_toc'])) {
      $html_hidden = $_POST['ad_bundle_hide_from_toc'];
    }
    update_post_meta($post->ID, 'ad_bundle_hide_from_toc', $html_hidden);
}

/*****
AD BUNDLE methods
 ****/
// Keep an array of failed attempts so we don't try more than once on the same request
global $_unzip_failures;
$_unzip_failures = array();

/****
AD BUNDLE UNZIP "MAIN": The process of unzipping, finding the first HTML file and returning the path to it
 ****/
function pugpig_ad_bundle_process($zip_id)
{
  global $post;
  $zip_file = get_attached_file($zip_id);

  $unzipped_path = pugpig_ad_bundle_get_unzip_dir($post->ID);
  pugpig_ad_bundle_remove_zip_dir($zip_file, $unzipped_path);

  pugpig_ad_bundle_unzip($zip_file, $unzipped_path);

  $html_file = pugpig_ad_bundle_html_file($unzipped_path);

  return $html_file;
}

/****
 ****/
function pugpig_ad_bundle_get_unzip_dir($post_id)
{
  return PUGPIG_MANIFESTPATH . 'ad_bundles/' . $post_id;
}

/****
 ****/
function pugpig_ad_bundle_get_unzip_url($post_id)
{
  return PUGPIG_MANIFESTURL . 'ad_bundles/' . $post_id;
}

/****
 ****/
function pugpig_ad_bundle_remove_zip_dir($zip_file, $unzipped_path)
{
  if (file_exists($unzipped_path) && filemtime($zip_file) > filemtime($unzipped_path)) {
    // Echo below results in "header already sent" error
    //echo('Ad Packager: Deleting out of date files for "<b>' . $zip_file . '</b>" [' . $unzipped_path . ']');
    try {
      _package_rmdir($unzipped_path);
    } catch (Exception $e) {
      echo('Could not remove directory' . esc_html($e->getMessage()) . '<br />');
      exit();
      $_unzip_failures[] = $zip_file;

      return;
    }
  } else {
    // Echo below results in "header already sent" error
    //echo('Nothing to remove');
  }
}

/****
 ****/
function pugpig_ad_bundle_unzip($zip_file, $unzipped_path)
{
  // Make the unzipped directory and extract the files if it doesn't exist
  if (!file_exists($unzipped_path)) {
    mkdir($unzipped_path, 0777, true);
    pugpig_add_admin_notice('Ad Packager: Unzipping files for "<b>' . $zip_file . "</b>");
    $zip = new ZipArchive();

    if ($zip->open($zip_file) === TRUE) {
      $zip->extractTo($unzipped_path);
      $zip->close();
    } else {
      pugpig_add_admin_notice('Ad Packager: Error opening zip file: ' . $zip_file, "error");
      _package_rmdir($unzipped_path);
      $_unzip_failures[] = $zip_file;

      return;
    }
  }
}

/****
 ****/
function pugpig_ad_bundle_html_file($unzipped_path)
{
  // Get the index path and ensure it exists
  $index = ''; //pugpig_value($node, 'field_ad_index');
  $orig_index = $index;

  if ($index != '' && !file_exists($unzipped_path . '/' . $index)) {
    pugpig_add_admin_notice('Ad Packager: Deleting bad index path: ' . $index, "error");
    $index = '';
  }

  if ($index != '' && !is_file($unzipped_path . '/' . $index)) {
    pugpig_add_admin_notice('Ad Packager: Path should point to a file. Deleting directory path: ' . $index, "error");
    $index = '';
  }

  if ($index == '') {
    $index =  _pugpig_get_first_file($unzipped_path, array('htm', 'html'));
    $slashpos = strrpos($index, "/");
    if (strpos($index, " ", $slashpos) != FALSE) {
      pugpig_add_admin_notice("$index <br /><strong>Error:</strong> html filename cannot contain spaces: $index", "error");
    }

    if ($index != '') {
      $index  = substr($index, 0, 1) == '/' ? $index : '/' . $index;
      pugpig_add_admin_notice('Ad Packager: Found index: ' . $index);
    }

  }

  if ($index == '') {

    pugpig_add_admin_notice('Ad Packager: [' . $unzipped_path . '] Could not find any index HTML file', "error");
    //watchdog('Pugpig: Unpacking Ad', 'Error: Could not find any index HTML file in ' . $zip_name, array(), WATCHDOG_WARNING, 'admin/reports/dblog/pugpig');
    $_unzip_failures[] =$unzipped_path;
    _package_rmdir($unzipped_path);

    return;
  }

  // Save the new value of  the index if it has changed
  if ($index != $orig_index) {
   $node->field_ad_index['und'] = array(array('value' => $index));
   //$node->field_ad_manifest['und'] = array(array('value' => (substr($manifest, 0, 1) == '/' ? $manifest : '/' . $manifest)));
  }

  return $index;
}

/****
 ****/
function _pugpig_get_first_file($dir, $ext = '', $rel = '')
{
  if (!is_array($ext)) $ext = array($ext);
  $contents = scandir($dir);
  if (!$contents) return '';
  $files = array();
  $dirs = array();

  foreach ($contents as $item)
    if ($item != '.' && $item != '..')
      if (is_dir($dir . '/' . $item))
        $dirs[] = $item;
      else
        $files[] = $item;

  foreach ($files as $file)
    if (in_array(pathinfo(strtolower($file), PATHINFO_EXTENSION), $ext) && substr($file, 0, 1) != '.')
      return $rel . '/' . $file;

  foreach ($dirs as $child) {
    $subdir = $dir . '/' . $child;
    $check = _pugpig_get_first_file($subdir, $ext, $child);
    if ($check !== '')
      return $rel . ($rel == '' ? '' : '/') . $check;
  }

  return '';
}

/****
Return the ad bundle HTML to the browser
 ****/
add_filter('template_include', 'pugpig_ad_bundles_render', 1, 1);
function pugpig_ad_bundles_render($template)
{
  global $post;

  if (is_singular(PUGPIG_AD_BUNDLE_POST_TYPE)) {
    $custom = get_post_custom($post->ID);
    $html_file = null;
    if (isset($custom["ad_bundle_html_file"])) {
      $html_file = $custom["ad_bundle_html_file"][0];
    }

    wp_redirect(pugpig_ad_bundle_get_unzip_url($post->ID) . $html_file);
	exit();
  } else {
    return $template;
  }
}

/****
Return the manifest contents
 ****/

add_filter('pugpig_extra_manifest_items', 'pugpig_ad_extra_manifest_items',10,2);
function pugpig_ad_extra_manifest_items($output, $post)
{
  if ($post->post_type != PUGPIG_AD_BUNDLE_POST_TYPE) return $output;

  // Exlude the entry HTML from the manifest
  $theme_path = pugpig_ad_bundle_get_unzip_url($post->ID);
  $theme_dir = pugpig_ad_bundle_get_unzip_dir($post->ID);
  $index = pugpig_ad_bundle_index_file($post);

  // Try to extract again if we've failed
  if (!is_dir($theme_dir)) {
    $attach_id = get_post_meta($post->ID, 'ad_bundle_zip_file', true);
    if (!empty($attach_id)) pugpig_ad_bundle_process($attach_id);
  }

  $exclusions = array(trim($index, "/"));
  $manifest_items = pugpig_theme_manifest_string($theme_path, $theme_dir, '', $exclusions);

  return $output . $manifest_items;
}

// Ignore theme assets
add_filter('pugpig_theme_manifest_items', 'pugpig_ad_ignore_theme_manifest_items',10,2);
function pugpig_ad_ignore_theme_manifest_items($output, $post)
{
  if ($post->post_type != PUGPIG_AD_BUNDLE_POST_TYPE) return $output;
  return "\n# AD BUNDLE: Ignoring theme manifest\n";
}

// Ignore attachments
add_filter('pugpig_attachment_manifest_items', 'pugpig_ad_ignore_attachment_manifest_items',10,2);
function pugpig_ad_ignore_attachment_manifest_items($output, $post)
{
  if ($post->post_type != PUGPIG_AD_BUNDLE_POST_TYPE) return $output;
  return "\n# AD BUNDLE: Ignoring attachment manifest items\n";
}

add_filter('pugpig_flatplan_style', 'pugpig_ad_bundle_pugpig_flatplan_style', 10, 2);
function pugpig_ad_bundle_pugpig_flatplan_style($output, $post)
{
  if ($post->post_type != PUGPIG_AD_BUNDLE_POST_TYPE) return $output;
  return $output . "color: green;";
}

add_filter('pugpig_get_content_html_url', 'pugpig_ad_bundle_pugpig_get_content_html_url', 10, 3);
function pugpig_ad_bundle_pugpig_get_content_html_url($url, $post, $edition_id)
{
  if (isset($post) && $post->post_type == PUGPIG_AD_BUNDLE_POST_TYPE) {
     return pugpig_ad_bundle_url($post);
  }

  return $url;
}

add_filter('pugpig_feed_post_custom_categories', 'pugpig_ad_bundle_pugpig_feed_post_custom_categories', 10, 2);
function pugpig_ad_bundle_pugpig_feed_post_custom_categories($custom_categories, $post) {
  if (isset($post) && $post->post_type == PUGPIG_AD_BUNDLE_POST_TYPE) {
    $hide = get_post_meta($post->ID, 'ad_bundle_hide_from_toc', TRUE);
    if ($hide) {
      $custom_categories['toc_style'] = "hidden";
    }
  }

  return $custom_categories;
}

function pugpig_ad_bundle_index_file($post)
{
  $custom = get_post_custom($post->ID);
  $html_file = null;
  if (isset($custom["ad_bundle_html_file"])) {
    $html_file = $custom["ad_bundle_html_file"][0];
  }

  return $html_file;
}

/****
Get the URL to the HTML file in the unzipped folder
 ****/
function pugpig_ad_bundle_url($post)
{
  return pugpig_ad_bundle_get_unzip_url($post->ID) . pugpig_ad_bundle_index_file($post);
}
