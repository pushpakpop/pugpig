<?php
/**
 * @file
 * Pugpig WordPress Settings
 */
?><?php
/*

Licence:
==============================================================================
(c) 2011, Kaldor Holdings Ltd
This module is released under the GNU General Public License.
See COPYRIGHT.txt and LICENSE.txt

 */?><?php

function pugpig_is_internal_user()
{
  $ip = getRequestIPAddress();

  if (isset($ip)) {
    if ($ip == "127.0.0.1") return TRUE;

    $internal_ip_range = get_option("pugpig_preview_ip_range");

    return ip_in_ranges($ip, $internal_ip_range);
  }

  return FALSE;
}

function pugpig_get_allowed_types()
{
  $arr = pugpig_get_array_from_comma_separate_string(get_option("pugpig_opt_allowed_types"));
  if (!empty($arr)) return $arr;
  return array("post", "page", PUGPIG_AD_BUNDLE_POST_TYPE);
}

function pugpig_get_hierarchical_types()
{
  $arr = pugpig_get_array_from_comma_separate_string(get_option("pugpig_opt_hierarchical_types"));
  if (!empty($arr)) return $arr;
  return array("page");
}

function pugpig_get_extra_opds_entries()
{
  return stripslashes(get_option("pugpig_opt_extra_opds_entries"));
}

function pugpig_get_category_order()
{
  return pugpig_get_array_from_comma_separate_string(get_option("pugpig_opt_category_order"));
}

function pugpig_should_send_push()
{
  $v = get_option("pugpig_opt_send_push");
  if (empty($v)) return false;
  return true;
}

function pugpig_should_allow_search()
{
  $v = get_option("pugpig_opt_allow_search");
  if (empty($v)) return false;
  return true;
}

function pugpig_should_auto_curate()
{
  $v = get_option("pugpig_opt_auto_curate");
  if (empty($v)) return false;
  return true;
}

function pugpig_should_auto_edition_key()
{
  $v = get_option("pugpig_opt_auto_key");
  if (empty($v)) return false;
  return true;
}

function pugpig_should_auto_tag_edition()
{
  $v = get_option("pugpig_opt_auto_tag");
  if (empty($v)) return false;
  return true;
}

function pugpig_should_show_debug()
{
  $v = get_option("pugpig_opt_show_debug");
  if (empty($v)) return false;
  return true;
}

function pugpig_should_use_thumbs()
{
  return false;
  //$v = get_option("pugpig_use_thumbs");
  //if (empty($v)) return false;
  //return true;
}

function pugpig_validate_theme($theme_name)
{
  $theme_dir = get_theme_root();
  if (!is_dir($theme_dir . "/" . $theme_name) && $theme_name != "") {
      ?>
      <div class="error"><p><strong><?php _e('Error: invalid theme name', 'menu-test' ); ?></strong></p></div>
      <?php
      }
}

/************************************************************************
Settings
************************************************************************/
function pugpig_get_authentication_secret()
{
    return get_option('pugpig_opt_authentication_secret', 'TOPSECRETCHANGEME');
}

function pugpig_get_num_editions()
{
  $v = get_option("pugpig_opt_num_editions");
  if (empty($v) || !is_numeric($v) || $v < 1) return 10;
  if ($v > 300) return 300;
  return $v;
}

function pugpig_get_feed_ttl()
{
  $v = get_option("pugpig_opt_feed_ttl");
  if (empty($v) || !is_numeric($v) || $v < 1) return 60; // 1 min
  return $v;
}

function pugpig_pdf_allowed()
{
  $v = get_option("pugpig_opt_allow_pdf");
  if ($v) return true;
  return false;
}

function pugpig_opt_hide_summary()
{
  $v = get_option("pugpig_opt_hide_summary");
  if ($v) return true;
  return false;
}

function pugpig_get_content_ttl()
{
  $v = get_option("pugpig_opt_content_ttl");
  if (empty($v) || !is_numeric($v) || $v < 1) return 600; // 10 mins
  return $v;
}

function pugpig_get_package_concurrent_connections()
{
  $v = get_option("pugpig_opt_package_concurrent_connections");
  if (empty($v) || !is_numeric($v) || $v < 1) return 5;
  if ($v > 20) return 20;
  return $v;
}

function pugpig_get_issue_prefix()
{
    return get_option('pugpig_opt_issue_prefix', 'editions_');
}

function pugpig_opt_remove_files()
{
    return get_option('pugpig_opt_remove_files', "style.css\n*.mustache\n*.sass");
}


function pugpig_opt_add_files()
{
    return get_option('pugpig_opt_add_files');
}


function pugpig_get_taxonomy_name()
{
    return get_option('pugpig_opt_taxonomy_name', 'category');
}

function pugpig_plugin_options()
{
    //must check that the user has the required capability
    if (!current_user_can('manage_options')) {
      wp_die( esc_html__('You do not have sufficient permissions to access this page.') );
    }

    $hidden_field_name = 'mt_submit_hidden';

    $opt_vars = array (
       "pugpig_opt_num_editions" => pugpig_get_num_editions(),
       "pugpig_opt_feed_ttl" => pugpig_get_feed_ttl(),
       "pugpig_opt_content_ttl" => pugpig_get_content_ttl(),
       "pugpig_opt_package_concurrent_connections" => pugpig_get_package_concurrent_connections(),
       "pugpig_preview_ip_range" => "",
       "pugpig_opt_issue_prefix" => pugpig_get_issue_prefix(),
       "pugpig_opt_authentication_secret" => pugpig_get_authentication_secret(),
       "pugpig_opt_theme_switch" => "",
       "pugpig_use_thumbs" => "",
       "pugpig_thumb_service_url" => "",
       "pugpig_thumb_regen_url" => "",
       "pugpig_opt_send_push" => "",
       "pugpig_opt_allow_search" => "",
       "pugpig_opt_allow_pdf" => "",
       "pugpig_opt_auto_curate" => "",
       "pugpig_opt_urbanairship_key" => "",
       "pugpig_opt_urbanairship_secret" => "",
       "pugpig_opt_urbanairship_message" => "",
       "pugpig_opt_cdn_domain" => "",
       "pugpig_opt_show_debug" => "",
       "pugpig_opt_taxonomy_name" => pugpig_get_taxonomy_name(),
       "pugpig_opt_allowed_types" => "",
       "pugpig_opt_hierarchical_types" => "",
       "pugpig_opt_category_order" => "",
       "pugpig_opt_extra_opds_entries" => "",
       "pugpig_opt_auto_key" => "",
       "pugpig_opt_auto_tag" => "",
       "pugpig_opt_hide_summary" => "",
       "pugpig_opt_remove_files" => "",
       "pugpig_opt_add_files" => ""
    );

    foreach (array_keys($opt_vars) as $v) {
      $opt_vars[$v]  = get_option($v, $opt_vars[$v]);
    }

    $theme_dir = get_theme_root();

    if ( isset($_POST[ $hidden_field_name ]) && $_POST[ $hidden_field_name ] == 'Y' ) {
      foreach (array_keys($opt_vars) as $v) {
        if (!empty($_POST[ $v ])) {
          $opt_vars[$v] = $_POST[ $v ];
        } else {
          $opt_vars[$v] = "";
        }
        update_option( $v, $opt_vars[$v] );

      }

    ?>
    <div class="updated"><p><strong><?php _e('Settings saved.', 'menu-test' ); ?></strong></p></div>
    <?php
    }

    pugpig_validate_theme($opt_vars['pugpig_opt_theme_switch']);

    // foreach (array_keys($opt_vars) as $v) echo $v . ":" . $opt_vars[$v] . "<br />";

    // Now display the settings editing screen

    echo '<div class="wrap">';

    // header

    echo '<div id="icon-edit" class="icon32 icon32-posts-pugpig_edition"><br></div>';
    echo "<h2>" . esc_html__( 'Pugpig settings', 'menu-test' ) . "</h2>";

    // settings form

    ?>
<script type='text/javascript' src='//code.jquery.com/jquery-2.1.3.js'></script>

<script type='text/javascript'>

$(document).ready(function(){
  var setadvanced = $('#setadvanced');
  $(setadvanced).click(function(){
    $('#advanced').toggle();
  });

});

</script>

<form name="form1" method="post" action="">
<input type="hidden" name="<?php echo esc_attr( $hidden_field_name ); ?>" value="Y">

<h3>General</h3>

<table class="form-table">

<tr valign="top">
<th scope="row"><label for="pugpig_opt_authentication_secret">Shared auth secret</label></th>
<td><input name="pugpig_opt_authentication_secret" type="text" id="pugpig_opt_authentication_secret" value="<?php echo esc_attr(pugpig_get_authentication_secret()); ?>" class="regular-text" />
<p class="description">This is the secret used to generate and decode edition credentials.</p>
</td>
</tr>

<tr valign="top">
<th scope="row"><label for="pugpig_opt_num_editions">Number of editions to show</label></th>
<td><input name="pugpig_opt_num_editions" type="text" id="pugpig_opt_num_editions" value="<?php echo esc_attr(pugpig_get_num_editions()); ?>" class="regular-text" /></td>
</tr>

<tr valign="top">
<th scope="row"><label for="pugpig_opt_issue_prefix">Issue prefix (e.g. com.pugpig.issue.). </label></th>
<td><input name="pugpig_opt_issue_prefix" type="text" id="pugpig_opt_issue_prefix" value="<?php echo esc_attr($opt_vars["pugpig_opt_issue_prefix"]); ?>" class="regular-text" />
<p class="description"><b>This should never be changed once you have published products</b></p></td>
</tr>

<tr valign="top">
<th scope="row"><label for="pugpig_opt_auto_key">Automatically create the edition key</label></th>
<td><input name="pugpig_opt_auto_key" type="checkbox" id="pugpig_opt_auto_key" <?php if (!empty($opt_vars["pugpig_opt_auto_key"])) echo "checked"; ?>  />
</td>
</tr>

<tr valign="top">
<th scope="row"><label for="pugpig_opt_auto_tag">Automatically create the edition tag</label></th>
<td><input name="pugpig_opt_auto_tag" type="checkbox" id="pugpig_opt_auto_tag" <?php if (!empty($opt_vars["pugpig_opt_auto_tag"])) echo "checked"; ?>  />
</td>
</tr>

<tr valign="top">
<th scope="row"><label for="pugpig_opt_allow_search">Enable edition search endpoint</label></th>
<td><input name="pugpig_opt_allow_search" type="checkbox" id="pugpig_opt_allow_search" <?php if (!empty($opt_vars["pugpig_opt_allow_search"])) echo "checked"; ?>  />
</td>
</tr>

<tr valign="top">
<th scope="row"><label for="pugpig_opt_allow_pdf">Allow PDF upload as edition</label></th>
<td><input name="pugpig_opt_allow_pdf" type="checkbox" id="pugpig_opt_allow_pdf" <?php if (!empty($opt_vars["pugpig_opt_allow_pdf"])) echo "checked"; ?>  />
</td>
</tr>

<tr valign="top">
<th scope="row"><label for="pugpig_opt_remove_files">Theme files to REMOVE from manifests</label></th>
<td>
  <textarea name="pugpig_opt_remove_files" rows="5" cols="50" id="pugpig_opt_remove_files" class="regular-text code"><?php echo wp_kses_post(pugpig_opt_remove_files()) ?></textarea>
<p class="description">One per line. Directories should end in a "<?php echo esc_html(DIRECTORY_SEPARATOR) ?>*", otherwise it will remove a file of the same name. The * wildcard can replace directories and file names.</p>
</td>
</tr>

<tr valign="top">
<th scope="row"><label for="pugpig_opt_add_files">Theme files to ADD to manifests</label></th>
<td>
  <textarea name="pugpig_opt_add_files" rows="5" cols="50" id="pugpig_opt_add_files" class="regular-text code"><?php echo wp_kses_post(pugpig_opt_add_files()) ?></textarea>
  <p class="description">One per line.</p>

</td>
</tr>

<tr valign="top">
<th scope="row"><label for="pugpig_preview_ip_range">Allow preview From IP range</label></th>
<td><input name="pugpig_preview_ip_range" type="text" id="pugpig_preview_ip_range" value="<?php echo esc_attr($opt_vars["pugpig_preview_ip_range"]); ?>" class="regular-text" />
<p class="description">Vistiors from these ranges will be able to see draft editions.
Network ranges can be specified as a) Wildcard format:     1.2.3.* b) CIDR format:         1.2.3/24  OR  1.2.3.4/255.255.255.0
c) Start-End IP format: 1.2.3.0-1.2.3.255<br />Use a ; to separate multiple ranges.<br />

You are an <b><?php echo pugpig_is_internal_user() ? "Internal" : "external" ?></b> user from <b><?php echo esc_html(getRequestIPAddress()) ?></b></p></td>
</tr>

<tr valign="top">
<th scope="row"><label for="pugpig_opt_theme_switch">Alternative theme for Pugpig app</label></th>
<td><input name="pugpig_opt_theme_switch" type="text" id="pugpig_opt_theme_switch" value="<?php echo esc_attr($opt_vars["pugpig_opt_theme_switch"]); ?>" class="regular-text" />
  <p class="description">Enter the theme slug. It is of the format pugpig-NAME-theme. If this is supplied, Pugpig pages will use this theme instead of the selected blog theme. You will need to use this if you want to use a different theme for your web site and your Pugpig app</p>
</td>
</tr>

<tr valign="top">
<th scope="row"><label for="pugpig_opt_package_concurrent_connections">Number of packager concurrent connections</label></th>
<td><input name="pugpig_opt_package_concurrent_connections" type="text" id="pugpig_opt_package_concurrent_connections" value="<?php echo esc_attr(pugpig_get_package_concurrent_connections()); ?>" class="regular-text" /></td>
</tr>


</table>

<p></p>

<!--
<h3>Page Thumbnails</h3>
<p>Thumbnails can be used for the web reader, and also enhance the flatplan sorting view.</p>

<table class="form-table">

<tr valign="top">
<th scope="row"><label for="pugpig_use_thumbs">Use thumbnails</label></th>
<td><input name="pugpig_use_thumbs" type="checkbox" id="pugpig_use_thumbs" <?php if (!empty($opt_vars["pugpig_use_thumbs"])) echo "checked"; ?>  /></td>
</tr>

<tr valign="top">
<th scope="row"><label for="pugpig_thumb_service_url">Thumbnail Display URL</label></th>
<td><input name="pugpig_thumb_service_url" type="text" id="pugpig_thumb_service_url" value="<?php echo esc_attr($opt_vars["pugpig_thumb_service_url"]); ?>" class="regular-text" /></td>
</tr>

<tr valign="top">
<th scope="row"><label for="pugpig_thumb_regen_url">Thumbnail Regen URL</label></th>
<td><input name="pugpig_thumb_regen_url" type="text" id="pugpig_thumb_regen_url" value="<?php echo esc_attr($opt_vars["pugpig_thumb_regen_url"]); ?>" class="regular-text" /></td>
</tr>
</table>
-->

<h3>Assign New Posts To Editions</h3>

<table class="form-table">
<tr valign="top">
<th scope="row"><label for="pugpig_opt_taxonomy_name">Taxonomy to allow on editions</label></th>
<td><input name="pugpig_opt_taxonomy_name" type="text" id="pugpig_opt_taxonomy_name" value="<?php echo esc_attr(pugpig_get_taxonomy_name()); ?>" class="regular-text" />
  <p class="description">The taxonomy slug to use for placing items into an edition.
    For categories, use <b>category</b>, for tags use <b>post_tag</b>.
  Otherwise enter the name of your custom taxonomy.<br />
  <?php
    $taxonomy_name = pugpig_get_taxonomy_name();
    if (!empty($taxonomy_name) && taxonomy_exists($taxonomy_name)) {
      $taxonomy = get_taxonomy($taxonomy_name);
      echo "<span style='color:green;'>Taxonomy <b>" . esc_html($taxonomy->labels->name) . "</b> exists</span>";
    } else {
      echo "<span style='color:red;'>Taxonomy does not exist</span>";
    }
  ?>
    </p>
</td>
</tr>

<?php
  global $wp_post_types;
  $labels = &$wp_post_types['post']->labels;
  $post_type = strtolower($labels->name);
  if (empty($post_type)) {
    $post_type = 'content';
  }
?>

<tr valign="top">
<th scope="row"><label for="pugpig_opt_auto_curate">Automatically include new <?php echo esc_html($post_type) ?> in editions</label></th>
<td><input name="pugpig_opt_auto_curate" type="checkbox" id="pugpig_opt_auto_curate" <?php if (!empty($opt_vars["pugpig_opt_auto_curate"])) echo "checked"; ?>  />
</td>
</tr>

<tr valign="top">
<th scope="row"><label for="pugpig_opt_hide_summary">Hide <?php $post_type ?> summaries in the flatplan</label></th>
<td><input name="pugpig_opt_hide_summary" type="checkbox" id="pugpig_opt_hide_summary" <?php if (!empty($opt_vars["pugpig_opt_hide_summary"])) echo "checked"; ?>  />
</td>
</tr>

<tr valign="top">
<th scope="row"><label for="pugpig_opt_allowed_types">Allowed post types in flatplan<br><small>(comma separated)</small></label></th>
<td><input name="pugpig_opt_allowed_types" type="text" id="pugpig_opt_allowed_types" value="<?php echo implode(", ", pugpig_get_allowed_types()); //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>" class="regular-text" /></td>
</tr>

<tr valign="top">
<th scope="row"><label for="pugpig_opt_hierarchical_types">Post types to treat as hierarchies in the flatplan<br><small>(comma separated)</small></label></th>
<td><input name="pugpig_opt_hierarchical_types" type="text" id="pugpig_opt_hierarchical_types" value="<?php echo implode(", ", pugpig_get_hierarchical_types()) //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>" class="regular-text" /></td>
</tr>

<tr valign="top">
<th scope="row"><label for="pugpig_opt_category_order">Auto curator category order<br><small>(comma separated)</small></label></th>
<td>
  <textarea name="pugpig_opt_category_order" rows="5" cols="50" id="pugpig_opt_category_order" class="regular-text code"><?php echo implode(", ", pugpig_get_category_order()) //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></textarea>
</td>
</tr>
</table>

<table>
<tr>
<td>
<h2>Advanced Options</h2>
</td>
<td>
<span class="button" id="setadvanced">Show</span>
</td>
</tr>
</table>

<span id="advanced" style="display:none;">

<a id="notifications"></a>
<h3>Push Notifications</h3>
<p>We are currently using Urban Airship for background push notifications when new editions arrive. Future versions of this plugin may implement the push functionality itself. If a message is given, a second push containing the message is sent.</p>

<table class="form-table">

<tr valign="top">
<th scope="row"><label for="pugpig_opt_send_push">Send push notifications</label></th>
<td><input name="pugpig_opt_send_push" type="checkbox" id="pugpig_opt_send_push" <?php if (!empty($opt_vars["pugpig_opt_send_push"])) echo "checked"; ?>  />
</td>
</tr>

<tr valign="top">
<th scope="row"><label for="pugpig_opt_urbanairship_key">Urban Airship key</label></th>
<td><input name="pugpig_opt_urbanairship_key" type="text" id="pugpig_opt_urbanairship_key" value="<?php echo esc_attr($opt_vars["pugpig_opt_urbanairship_key"]); ?>" class="regular-text" />
</td>
</tr>

<tr valign="top">
<th scope="row"><label for="pugpig_opt_urbanairship_secret">Urban Airship application master secret</label></th>
<td><input name="pugpig_opt_urbanairship_secret" type="text" id="pugpig_opt_urbanairship_secret" value="<?php echo esc_attr($opt_vars["pugpig_opt_urbanairship_secret"]); ?>" class="regular-text" />
  <p class="description">Use the master secret, not the application secret.</p>
</td>
</tr>

<tr valign="top">
<th scope="row"><label for="pugpig_opt_urbanairship_message">Urban Airship message</label></th>
<td><input name="pugpig_opt_urbanairship_message" type="text" id="pugpig_opt_urbanairship_message" value="<?php echo esc_attr($opt_vars["pugpig_opt_urbanairship_message"]); ?>" class="regular-text" />
  <p class="description">If this is set, the module will show this as the default message option when sending a push notification.</p>
</td>
</tr>

</table>

<h3>Content Delivery Network</h3>
<p>The module supports using a CDN to serve static assets and packages</p>
<table class="form-table">

<tr valign="top">
<th scope="row"><label for="pugpig_opt_cdn_domain">CDN domain</label></th>
<td><input name="pugpig_opt_cdn_domain" type="text" id="pugpig_opt_cdn_domain" value="<?php echo esc_attr($opt_vars["pugpig_opt_cdn_domain"]); ?>" class="regular-text" />
  <p class="description">Use this if you wish to serve static assets from a content delivery network. No trailing slash, e.g. http://my.cdn.com</p>
</td>
</tr>

<tr valign="top">
<th scope="row"><label for="pugpig_opt_feed_ttl">Time-To-Live (secs) of XML feeds</label></th>
<td><input name="pugpig_opt_feed_ttl" type="text" id="pugpig_opt_feed_ttl" value="<?php echo esc_attr(pugpig_get_feed_ttl()); ?>" class="regular-text" /></td>
</tr>

<tr valign="top">
<th scope="row"><label for="pugpig_opt_content_ttl">Time-To-Live (secs) of HTML files and manifests</label></th>
<td><input name="pugpig_opt_content_ttl" type="text" id="pugpig_opt_content_ttl" value="<?php echo esc_attr(pugpig_get_content_ttl()); ?>" class="regular-text" /></td>
</tr>

</table>

<h3>Debug and Testing</h3>
<p>Various settings for debugging and testing. By default, the plugin will only rewrite post markup for requests from a Pugpig client.</p>

If you include a static set of OPDS entries which will be included in the feed.

<table class="form-table">

</tr>
<tr valign="top">
<th scope="row"><label for="pugpig_opt_extra_opds_entries">Extra OPDS entries</label></th>
<td>
  <textarea name="pugpig_opt_extra_opds_entries" rows="5" cols="50" id="pugpig_opt_extra_opds_entries" class="regular-text code"><?php echo wp_kses_post(pugpig_get_extra_opds_entries()); ?></textarea>
</td>
</tr>

<tr valign="top">
<th scope="row"><label for="pugpig_opt_show_debug">Output debug admin messages</label></th>
<td><input name="pugpig_opt_show_debug" type="checkbox" id="pugpig_opt_show_debug" <?php if (!empty($opt_vars["pugpig_opt_show_debug"])) echo "checked"; ?>  /></td>
</tr>
</table>

</span>

<p class="submit">
<input type="submit" name="Submit" class="button-primary" value="<?php esc_attr_e('Save Changes') ?>" />
</p>

</form>
</div>

<img style="float:right;"width="100" src="<?php echo esc_url(BASE_URL) ?>common/images/pugpig-large.png" />

<p>Thank you for using the Pugpig Connector for WordPress.</p>
<p>See the <a href="https://pugpig.zendesk.com/hc/en-us/articles/202488153">Pugpig for WordPress Release Notes</a></p>
<p>Go to <a href='http://pugpig.com/resources'>The Pugpig Resources Page</a> to get:<ul><li>- the latest version of this module</li><li>- other software in the Pugpig suite</li><li>- tutorials and product documentation</li></ul></p>
<p>If you have any feedback, suggestions or product bug reports, drop us a line at <a href='mailto:info@pugpig.com?subject=I have been using your WordPress module and ...'>info@pugpig.com</a></p>

<p style='font-size: smaller;'>&copy; (c) 2011, Kaldor Holdings Ltd. All rights reserved.</p>
<p style='font-size: smaller;'>This module is released under the GNU General Public License.</p>
<p style='font-size: smaller;'>See COPYRIGHT.txt and LICENSE.txt</p>

<?php

}
