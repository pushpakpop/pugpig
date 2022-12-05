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

define( 'PUGPIG_EDITION_POST_TYPE', 'pugpig_edition');
define( 'PUGPIG_EDITION_CONTENTS_ARRAY', 'pugpig_edition_contents_array');
define( 'PUGPIG_POST_META_ACCESS_KEY', 'pugpig_post_access');
// define( 'PUGPIG_POST_EDITION_LINK', 'pugpig_post_edition_link');
//define( 'BASE_URL',  WP_PLUGIN_URL.'/'.str_replace(basename( __FILE__),"",plugin_basename(__FILE__)));
define( 'BASE_URL', pugpig_strip_domain(plugins_url('pugpig/'))); // Carlos - BASE_URL definition above doesn't return soft link directories

class PugpigEditionColumns
{
  const Checkbox     = 'cb';
  const Title        = 'title';
  const Actions      = 'pugpig_edition_actions';
  const Date         = 'pugpig_edition_date';
  const Access       = 'pugpig_edition_access';
  const Description  = 'pugpig_edition_description';
  const Cover        = 'pugpig_edition_image';
  const Links        = 'pugpig_edition_links';

  public static function getTaxonomyColumnInfo()
  {
    $name = null;
    $slug = null;
    $label = null;

    $name = pugpig_get_taxonomy_name();
    if (!empty($name) && taxonomy_exists($name)) {
      $taxonomy = get_taxonomy($name);
      $slug = 'pugpig_edition_' . $name;
      $label = $taxonomy->labels->name;
    }

    return array($name, $slug, $label);
  }

  public static function headers()
  {
    $headers = array(
      PugpigEditionColumns::Checkbox    => '<input type="checkbox" />',
      PugpigEditionColumns::Title       => 'Edition Title',
      PugpigEditionColumns::Actions     => 'Actions',
      PugpigEditionColumns::Date        => 'Edition Date',
      PugpigEditionColumns::Access      => 'Access',
      PugpigEditionColumns::Description => 'Description',
      PugpigEditionColumns::Cover       => 'Cover');

    list($taxonomy_name, $taxonomy_slug, $taxonomy_label) = self::getTaxonomyColumnInfo();
    if (!empty($taxonomy_slug)) {
      $headers[$taxonomy_slug] = $taxonomy_label;
    }

    $headers[PugpigEditionColumns::Links] = 'Links';

    return $headers;
  }
};

/************************************************************************
Get all the editions
************************************************************************/
function pugpig_get_editions($status='all', $num_posts = -1)
{
  $results = null;

  $wp_status = null;
  switch ($status){
    case 'all':
      $wp_status = 'any';
      break;
    case 'publish':
      $wp_status = 'publish';
      break;
    case 'preview':
      $wp_status = array('draft', 'pending');
      break;
  }

  if ($wp_status) {
    $args = array(
      'post_type' => PUGPIG_EDITION_POST_TYPE,
      'post_status' => $wp_status,
      'numberposts' => $num_posts);

    $results =  get_posts($args);
    wp_reset_query();
  }

  return $results;
}

/************************************************************************
Get the array of posts against an edition
************************************************************************/
function pugpig_get_edition_array($custom)
{
      // Get the existing array
      $ecr = array();
      if (!empty($custom[PUGPIG_EDITION_CONTENTS_ARRAY][0])) {
         $ecr = maybe_unserialize($custom[PUGPIG_EDITION_CONTENTS_ARRAY][0]);
      }

      return $ecr;
}

/************************************************************************
Get the edition id in which a post sits
************************************************************************/
function pugpig_get_post_editions($post)
{
  $my_editions = array();
  if (!empty($post)) {
    $editions = pugpig_get_editions();

    // Optimized version for new and old WP versions
    // $args = array(
    //   'post_type' => 'pugpig_edition',
    //   'post_status' => 'publish',
    //   'orderby' => 'post_date',
    //   'order' => 'DESC',
    //   'posts_per_page' => -1,
    //   'meta_query' => array(
    //     'relation' => 'OR',
    //     array(
    //       'key'     => 'pugpig_edition_contents_array',
    //       'value'   => serialize($post->ID),
    //       'compare' => 'LIKE',
    //     ),
    //     array(
    //       'key'     => 'pugpig_edition_contents_array',
    //       'value'   => '"' . $post->ID . '"',
    //       'compare' => 'LIKE',
    //     )
    //   ),
    // );

    // $editions = get_posts($args);
    foreach ($editions as $edition) {
      $page_array = pugpig_get_edition_array(get_post_custom($edition->ID));
      if (!empty($post) && in_array($post->ID, $page_array)) {
        $my_editions[]= $edition;
      }
      //print_r($page_array);
      //$option .= $edition->post_title . ' (' . $edition->post_status . ')';
      //echo $option;
    }
    /*
      $post_edition_id = "";
      $custom = get_post_custom($post->ID);
      if (!empty($custom[PUGPIG_POST_EDITION_LINK][0])) {
        $post_edition_id = $custom[PUGPIG_POST_EDITION_LINK][0];
      }

      return $post_edition_id;
  */

  }

 return $my_editions;
}

/************************************************************************
Gets the edition keys of all editions
************************************************************************/
function pugpig_get_all_edition_keys()
{
  $edition_keys = array();

  $args = array(
    'post_type' => array('pugpig_edition'),
    'posts_per_page' => -1,
  );

  $query = new WP_Query($args);
  $all_editions = $query->posts;

  foreach($all_editions as $edition){
    $edition_keys[$edition->post_title] = get_post_meta( $edition->ID, 'edition_key', true );
  }

  return $edition_keys;
}

/************************************************************************
Gets the URL to a path
************************************************************************/
function pugpig_path_to_abs_url($path)
{
  $path = str_replace(DIRECTORY_SEPARATOR, "/", $path);
  $abs_path = str_replace(DIRECTORY_SEPARATOR, "/", ABSPATH);

  return home_url() . "/" . str_replace($abs_path, "", $path);
}

/************************************************************************
Add the ability to filter posts by custom fields
************************************************************************/
add_filter( 'parse_query', 'pugpig_admin_posts_filter' );
function pugpig_admin_posts_filter($query)
{
    global $pagenow;
    if ( is_admin() && $pagenow=='edit.php' && isset($_GET['ADMIN_FILTER_FIELD_NAME']) && $_GET['ADMIN_FILTER_FIELD_NAME'] != '') {
        $query->query_vars['meta_key'] = $_GET['ADMIN_FILTER_FIELD_NAME'];
    if (isset($_GET['ADMIN_FILTER_FIELD_VALUE']) && $_GET['ADMIN_FILTER_FIELD_VALUE'] != '')
        $query->query_vars['meta_value'] = $_GET['ADMIN_FILTER_FIELD_VALUE'];
    }
}

/************************************************************************
Helpers to generate internal admin links
************************************************************************/
/*
function pugpig_edition_filter_link($edition_id, $text)
{
 return '<a href="edit.php?post_type=post&ADMIN_FILTER_FIELD_NAME='.PUGPIG_POST_EDITION_LINK.'&ADMIN_FILTER_FIELD_VALUE='.$edition_id.'">' . $text . '</a>';
}
*/
function pugpig_edition_edit_link($edition_id, $text)
{
 return '<a href="post.php?post='.$edition_id.'&action=edit">' . $text . '</a>';
}

/************************************************************************
Create a new custom type for editions
We want to do this late, after any other custom taxonomies are registered
************************************************************************/
add_action('init', 'pugpig_editions_register', 50);
function pugpig_editions_register()
{
  $labels = array(
    'name' => _x('Editions', 'post type general name'),
    'singular_name' => _x('Edition', 'post type singular name'),
    'add_new' => _x('Add New', 'Edition item'),
    'add_new_item' => __('Add New Pugpig Edition'),
    'edit_item' => __('Edit Edition Item'),
    'new_item' => __('New Edition Item'),
    'view_item' => __('View Edition Item'),
    'search_items' => __('Search Editions'),
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
    'menu_position' => null,
    'supports' => array('title', 'excerpt','thumbnail') // Custom Fields for debug 'custom-fields'
    );

  register_post_type( PUGPIG_EDITION_POST_TYPE , $args );

  // Add a taxonomy if the settings say we need one
  $taxonomy_name = pugpig_get_taxonomy_name();
  if (!empty($taxonomy_name) && taxonomy_exists($taxonomy_name)) {
    register_taxonomy_for_object_type($taxonomy_name, PUGPIG_EDITION_POST_TYPE);
  }

}

/************************************************************************
Enable post-thumbnails support for edition post type
************************************************************************/
function pugpig_add_featured_image_support()
{
    $supportedTypes = get_theme_support( 'post-thumbnails' );
    if ($supportedTypes === false) {
        add_theme_support( 'post-thumbnails', array( PUGPIG_EDITION_POST_TYPE ) );

    } elseif ( is_array( $supportedTypes ) ) {
        $supportedTypes[0][] = PUGPIG_EDITION_POST_TYPE;
        add_theme_support( 'post-thumbnails', $supportedTypes[0] );
    }
}
add_action( 'after_setup_theme',    'pugpig_add_featured_image_support', 11 );

/************************************************************************
Custom fields required for an edition
************************************************************************/
function pugpig_edition_info()
{
  global $post;
  $custom = get_post_custom($post->ID);
  $edition_date = date("Y-m-d");
  if (isset($custom["edition_date"])) {
    $edition_date = $custom["edition_date"][0];
  }

  $edition_free = true;
  if (isset($custom["edition_free"])) {
    $edition_free = $custom["edition_free"][0];
  } else {
    $edition_free = false;
  }

  $edition_samples = isset($custom["edition_samples"][0])
      ? $custom["edition_samples"][0]
      : false;

  $edition_deleted = true;
  if (isset($custom["edition_deleted"])) {
    $edition_deleted = $custom["edition_deleted"][0];
  } else {
    $edition_deleted = false;
  }

  $edition_key = "";
  $edition_key_label = "Key:";
  $edition_key_disable = "";
  $edition_key_decription = "The internal unique key for the edition, used in the URLs. It should not be changed once published.";
  if (isset($custom["edition_key"])) {
    $edition_key = $custom["edition_key"][0];
  } elseif (pugpig_should_auto_edition_key()){
    $edition_key_label = "";
    $edition_key_disable = 'type="hidden"';
    $edition_key_decription = "";
    $key_transient = get_transient('edition_key_count');
    $edition_key = empty($key_transient)?1:(int)$key_transient+1;
    set_transient('edition_key_count', (int)$edition_key);
    $edition_key = sprintf("%04d", $edition_key);
  }

  $edition_author = "";
  if (isset($custom["edition_author"])) {
    $edition_author = $custom["edition_author"][0];
  }

  $edition_sharing_link = "";
  if (isset($custom["edition_sharing_link"])) {
    $edition_sharing_link = $custom["edition_sharing_link"][0];
  }

  ?>

  <label><?php echo wp_kses_post($edition_key_label) ?></label>
  <input name="edition_key" value="<?php echo esc_attr($edition_key); ?>" <?php echo wp_kses_post($edition_key_disable) ?> />
  <p><?php echo wp_kses_post($edition_key_decription) ?></p>

  <label>Date:</label>
  <input name="edition_date" value="<?php echo esc_attr($edition_date); ?>" />
  <p>The date of the edition or issue.  (YYYY, YYYY-MM or YYYY-MM-DD)</p>

  <label>Author:</label>
  <input name="edition_author" value="<?php echo esc_attr($edition_author); ?>" />
  <p>Author of this edition. Optional.</p>

  <label>Free Edition:</label>
  <input name="edition_free_exists" type="hidden" value="1"  />
  <input name="edition_free" type="checkbox" <?php if ($edition_free) print(" checked");  ?> />
  <p>Is this edition free?</p>

  <label>Has Samples:</label>
  <input name="edition_samples_exists" type="hidden" value="1"  />
  <input name="edition_samples" type="checkbox" <?php if ($edition_samples) print(" checked");  ?> />
  <p>Does the edition have sample pages?</p>

  <label>Deleted Edition:</label>
  <input name="edition_deleted_exists" type="hidden" value="1"  />
  <input name="edition_deleted" type="checkbox" <?php if ($edition_deleted) print(" checked");  ?> />
  <p>In order to delete an edition that has been published, use this field to set a "tombstome"</p>


  <label>Sharing Link:</label>
  <input name="edition_sharing_link"  value="<?php echo esc_attr($edition_sharing_link); ?>" />
  <p>This is an optional sharing links for ALL pages in the edition that do not specify their own</p>

  <?php

}

/**
 * Add action for load scripts in admin area.
 */
add_action('admin_enqueue_scripts', 'pugpig_admin_enqueue_scripts');

function pugpig_admin_enqueue_scripts() {
	// Needed for sortable flatplan
	wp_register_script( 'mootoolscore', plugins_url('pugpig/') . "js/mootools-core-1.3.2-full-compat.js", array('jquery'), '', true);
	wp_register_script( 'mootoolsmore', plugins_url('pugpig/')  . "js/mootools-more-1.3.2.1.js", array('jquery'), '', true);
	wp_enqueue_script( 'mootoolscore' );
	wp_enqueue_script( 'mootoolsmore' );
}

/************************************************************************
Edition Edit Boxes on the Edition Screen and the Post screen
************************************************************************/
add_action("admin_init", "pugpig_admin_init");
function pugpig_admin_init()
{

  $args=array(
    'public'   => true,
    '_builtin' => false
  );
  $output = 'names'; // names or objects, note names is the default
  $operator = 'and'; // 'and' or 'or'
  $post_types=get_post_types($args);
  foreach ($post_types  as $post_type) {
    if ($post_type != PUGPIG_EDITION_POST_TYPE) {
      add_meta_box("post_info-meta", "Edition", "pugpig_post_info", $post_type, "side", "high");
    }
  }

  add_meta_box("post_info-meta", "Edition", "pugpig_post_info", "post", "side", "high");
  add_meta_box("post_info-meta", "Edition", "pugpig_post_info", "page", "side", "high");

  add_meta_box("pugpig-edition_info-meta", "Edition Info", "pugpig_edition_info", PUGPIG_EDITION_POST_TYPE, "normal", "default");
  add_meta_box("pugpig-edition_flatplan-meta", "Flatplan", "pugpig_flatplan_info", PUGPIG_EDITION_POST_TYPE, "normal", "default");

  // TODO: Something like this:
  // http://shibashake.com/wordpress-theme/switch_theme-vs-theme-switching


}

add_filter('do_meta_boxes','pugpig_rename_features_image');
function pugpig_rename_features_image($post_type){

  if ($post_type == 'pugpig_edition'){
    remove_meta_box( 'postimagediv', $post_type, 'side' );
    add_meta_box('postimagediv', __('Edition Cover'), 'post_thumbnail_meta_box', 'pugpig_edition', 'normal', 'high');
    add_filter('admin_post_thumbnail_html','pugpig_rename_features_image_text');

    remove_meta_box( 'postexcerpt', $post_type, 'side' );
    add_meta_box('postexcerpt', __('Summary'), 'post_excerpt_meta_box', $post_type, 'normal', 'high');
  }
}

function pugpig_rename_features_image_text($post_type){
   return str_replace(__('Set featured image'), __('Set Edition Cover'),$post_type);
}

function pugpig_parent_post_info()
{
  global $post;

  $post_types = array_diff(pugpig_get_allowed_types(), array(PUGPIG_EDITION_POST_TYPE)); // Remove edition type from search

  $parent_id = $post->post_parent;

  if ($parent_id != 0) {
    $parent = get_post($parent_id);
    echo "<p><strong>Parent</strong></p>";
    echo esc_html($parent->post_title);
    echo " [ <a href='" . esc_url(get_edit_post_link( $parent_id, "edit" )) .  "'>edit</a> ]</li>";

    return;
  }

  $child_args = array(
      'post_type' => $post_types,
      'order' => 'ASC',
      'orderby' => 'menu_order',
      'post_parent' => $post->ID
  );

  $children = get_children($child_args);

  if (count($children)) {
    echo "<p><strong>Child Items</strong></p>";
    echo "<ul>";

    foreach ($children as $child) {
      echo "<li>" . esc_html($child->post_title) .
      " [ <a href='" . esc_url(get_edit_post_link( $child->ID, "edit" )) .  "'>edit</a> ]</li>";
    }

    echo "</ul>";
  }

  $vars = array("parent_id" => $post->ID, "sort_order" => 50);
  submit_button("Create new child", "primary", "pugpig_create_child", true, $vars);
}

/************************************************************************
Feeds
************************************************************************/
function pugpig_create_opds_feed()
{
  if ( ! defined('DONOTCDN') ) define('DONOTCDN', 'PUGPIG');
  load_template( WP_PLUGIN_DIR . '/pugpig/feeds/pugpig_feed_opds.php');
}

function pugpig_create_edition_feed()
{
  // Turn off the CDN rewriting for Pugpig URLS. This is needed for W3 Total Cache
  if ( ! defined('DONOTCDN') ) define('DONOTCDN', 'PUGPIG');
  if ( ! defined('DONOTCACHEPAGE') ) define('DONOTCACHEPAGE', 'PUGPIG');  load_template( WP_PLUGIN_DIR . '/pugpig/feeds/pugpig_feed_edition.php');
  load_template( WP_PLUGIN_DIR . '/pugpig/feeds/pugpig_feed_edition.php');
}

add_action('init','pugpig_add_feeds',1);
function pugpig_add_feeds()
{
  add_feed('opds', 'pugpig_create_opds_feed');
  add_feed('edition', 'pugpig_create_edition_feed');
}

/*
The default (package) mode:
http://wordpress.xx.com/pugpig/?feed=opds
Atom mode:
http://wordpress.xx.com/pugpig/?feed=opds&atom=true
Package+internal mode:
http://wordpress.xx.com/pugpig/?feed=opds&internal=true
Atom+internal mode:
http://wordpress.xx.com/pugpig/?feed=opds&atom=true&internal=true
*/

function pugpig_feed_opds_link($internal=false, $atom= false, $newsstand=false, $region=null)
{
  $odps_link = home_url() . "/feed/opds/";
  $first = true;
  if ($internal) {
     $odps_link .=  ($first ? "?" : "&") . "internal=true";
     $first = false;
  }
  if ($atom) {
     $odps_link .= ($first ? "?" : "&") . "atom=true";
     $first = false;
  }
  if ($newsstand) {
     $odps_link .= ($first ? "?" : "&") . "newsstand=true";
     $first = false;
  }
  if ($region) {
     $odps_link .= ($first ? "?" : "&") . "region=$region";
     $first = false;
  }

    return $odps_link;
}

// http://www.howtocreate.in/how-to-create-a-custom-rss-feed-in-wordpress/
function custom_feed_rewrite($wp_rewrite)
{
$feed_rules = array(

'feed/(.+)' => 'index.php?feed=' . $wp_rewrite->preg_index(1)
);
$wp_rewrite->rules = $feed_rules + $wp_rewrite->rules;

//print_r($wp_rewrite); exit();
}
add_filter('generate_rewrite_rules', 'custom_feed_rewrite');

/************************************************************************
Create the edition selector box used on the posts and pages
************************************************************************/
function pugpig_post_info()
{
  global $post;

  // Get the currect editions
  $post_editions =  pugpig_get_post_editions($post);
  if (empty($post_editions)) {
    echo 'No editions';
  } else {
    echo "<ul>";
    foreach ($post_editions as $edition) {
      echo "<li>" . esc_html($edition->post_title) . "</li>";
    }
    echo "</ul>";
  }
  /*
  print_r($post_editions);

  return;

  $pages = pugpig_get_editions();
  echo '<select name="post_edition">';
  echo '<option value="">&mdash; Select &mdash;</option>';

  foreach ($pages as $edition) {
    $option = '<option value="' . $edition->ID . '" ' . ($edition->ID == $post_edition ? " selected" : "") . '>';
    $option .= $edition->post_title . ' (' . $edition->post_status . ')';
    $option .= '</option>';
    echo $option;
  }
  echo '</select>';
*/
  ?>

  <img src="<?php echo esc_url(BASE_URL) ?>common/images/pugpig-32x32.png" />

  <?php
  // echo '<p>Sharing Link:<br /><input type="text" name="post_sharing_link"></p>';

}

function pugpig_should_keep_edition_in_feed($edition)
{
  $keep = true;

  // Allow modules to decide if we should keep the edition in the OPDS feed
  $keep = apply_filters('pugpig_keep_edition_in_feed', $keep, $edition);

  return $keep;
}

function pugpig_get_available_region_array()
{
  $regions = array();

  // Allow modules to decide if we should keep the edition in the OPDS feed
  $regions = apply_filters('pugpig_get_available_region_array', $regions);

  return $regions;
}

function pugpig_get_feed_post_title($post)
{
  $title = $post->post_title;

  // Allow modules to change the title
  $title = apply_filters('pugpig_feed_post_title', $title, $post);

  return $title;
}

function pugpig_get_feed_post_level($post, $content_filter)
{
  $level = "1";

  // Allow modules to change the level
  $level = apply_filters('pugpig_feed_post_level', $level, $post, $content_filter);

  return $level;
}

function pugpig_get_feed_post_summary($post)
{
  $summary = $post->post_excerpt;

  // Allow modules to change the title
  $summary = apply_filters('pugpig_feed_post_summary', $summary, $post);

  return $summary;
}

function pugpig_get_feed_post_author($post)
{
  $author = "";
  $this_author = $post->post_author;
  if (!empty($this_author)) {
    $author = get_userdata($this_author)->display_name;
  }

  // Allow modules to change the title
  $author = apply_filters('pugpig_feed_post_author', $author, $post);

  return $author;
}

function pugpig_get_atom_post_id($post, &$stop_id_prefixes)
{
  $id = apply_filters('pugpig_feed_post_id', $post->ID, $post);
  $stop_id_prefixes = $id != $post->ID;

  return $id;
}

function pugpig_get_atom_post_access($post)
{
  $access = get_post_meta( $post->ID, PUGPIG_POST_META_ACCESS_KEY, true );

  // Allow modules to change the access
  $access = apply_filters('pugpig_feed_post_access', $access, $post);

  return $access;
}

function pugpig_get_feed_post_categories($post, $content_filter=null)
{
  $categories = array();
  $page_categories = wp_get_post_categories( $post->ID );

  $categories = apply_filters('pugpig_feed_post_categories', $categories, $post, $content_filter);

  // If no module has helped out, use the slug
  if (count($categories) == 0) {
    foreach ($page_categories as $c) {
      $cat = get_category( $c );
      if ($cat->slug != 'uncategorized') {
        $categories[] = $cat->slug;
      }
    }
  }

  return $categories;
}

function pugpig_get_feed_post_custom_categories($post, $content_filter=null)
{
  $categories = array();

  // Allow modules to change the title
  $categories = apply_filters('pugpig_feed_post_custom_categories', $categories, $post, $content_filter);

  return $categories;
}

/* Get the Top Level category */
function pugpig_get_category_parent( $id, $visited = array() )
{
   $parent = get_category( $id );
   if ( is_wp_error( $parent ) )
           return $parent;

  $name = $parent->slug;

   if ( $parent->parent && ( $parent->parent != $parent->term_id ) && !in_array( $parent->parent, $visited ) ) {
           $visited[] = $parent->parent;

           return pugpig_get_category_parent( $parent->parent, $visited );
   }

   return $parent;
}

function pugpig_get_first_category($post)
{
  $cats = get_the_category($post->ID);
  if (!empty($cats)) {
    return $cats[0];
  }

  return "";
}

function pugpig_get_first_category_slug($post)
{
  $cats = get_the_category($post->ID);
  if (!empty($cats)) {
    return $cats[0]->slug;
  }

  return "";
}

function pugpig_get_flatplan_style($post)
{
  $style = "font-weight: bold;";

  // Allow modules to change the title
  $style = apply_filters('pugpig_flatplan_style', $style, $post);

  return $style;
}

function pugpig_get_flatplan_description($post)
{
  $type = get_post_type_object($post->post_type);
  $edit_link = get_edit_post_link( $post->ID, "edit" );
  $desc = "[<b style='" . pugpig_get_flatplan_style($post) . "'><a href='$edit_link'>" . $type->labels->singular_name . "</a></b>] ";

  $desc .= pugpig_get_feed_post_title($post);

  $taxonomy_name = pugpig_get_taxonomy_name();
  if ($taxonomy_name === 'category'){
    $first_category = pugpig_get_first_category($post);
    if (!empty($first_category)) {

      $category_desc = $first_category->slug;

      // Add the top level category
      $parent_category = pugpig_get_category_parent($first_category->term_id);

      if (isset($parent_category) && $parent_category->slug != $first_category->slug) {
         $category_desc =  $parent_category->slug . " -> " . $category_desc;
      }
      $desc .= " (<span style='color:blue'>". $category_desc . "</span>)";
    }
  }

  $author = pugpig_get_feed_post_author($post);
  if (is_array($author)) {
    $author = implode(', ', $author);
  }
  $desc .=  "<span style='color:green'> " . $author . "</span>";

  $hide_summary = pugpig_opt_hide_summary();
  if (!$hide_summary){
    $summary = wp_strip_all_tags(pugpig_get_feed_post_summary($post));
    $desc .=  "<span style='color:orange'> " . $summary . "</span>";
  }

  $desc .= " (" . _ago(pugpig_get_page_modified($post)) . "ago)";

  $desc = apply_filters('pugpig_flatplan_description', $desc, $post);

  return $desc;
}

// This is used to order a query set by categories
function pugpig_flatplan_orderby($a, $b)
{
      // Use the category ordering if we can
      $category_order_arr = pugpig_get_category_order();

      $apos = array_search( pugpig_get_first_category_slug($a), $category_order_arr );
      $bpos   = array_search( pugpig_get_first_category_slug($b), $category_order_arr );
      if ($apos === FALSE) $apos = 10000;
      if ($bpos === FALSE) $bpos = 10000;

      $ret = null;
      if ($apos == $bpos) $ret = 0;
      else $ret = ( $apos < $bpos ) ? -1 : 1;

      // Allow an additional sort
      $custom_ret = apply_filters('pugpig_custom_flatplan_sort', $ret, $a, $b);

      if (!empty($custom_ret)) $ret = $custom_ret;
      return $ret;
  }

/************************************************************************
Autocurate Flatplan
************************************************************************/
add_action('save_post', 'pugpig_auto_curate_editions', 20);
function pugpig_auto_curate_editions($post_id)
{
  if (!pugpig_should_auto_curate()) return;
  $post = get_post($post_id);

  if ( $post->post_type !== 'product' && $post->post_type !== 'pugpig_edition'){
    $taxonomy = pugpig_get_taxonomy_name();
    $terms = pugpig_get_post_terms($post);

    $args = array(
      'post_type' => array('pugpig_edition'),
      'post_status' => 'any',
      'posts_per_page' => -1,
      'tax_query' => array(
        array(
          'taxonomy' => $taxonomy,
          'field'    => 'slug',
          'terms'    => $terms,
          'operator' => 'NOT IN'
        )
      )
    );

    $query = new WP_Query($args);
    $all_editions = $query->posts;
    foreach($all_editions as $edition){
      pugpig_delete_edition_array($edition->ID, $post_id);
    }

    wp_reset_query();

    $args = array(
      'post_type' => array('pugpig_edition'),
      'post_status' => 'any',
      'posts_per_page' => -1,
      'tax_query' => array(
        array(
          'taxonomy' => $taxonomy,
          'field'    => 'slug',
          'terms'    => $terms,
          'operator' => 'IN'
        )
      )
    );

    $query = new WP_Query($args);
    $editions = $query->posts;
    foreach($editions as $edition){
      pugpig_add_edition_array($edition->ID, $post_id);
    }

    wp_reset_query();
  }
}

/************************************************************************
************************************************************************/
function pugpig_get_post_terms($post)
{
  // Get posts in the categories for the right hand side
  $taxonomy_name = pugpig_get_taxonomy_name();
  if (empty($taxonomy_name)) return array();
  $term_objects = wp_get_post_terms( $post->ID, $taxonomy_name);
  $terms = array();
  foreach ($term_objects as $term_object) {
    $terms[] = $term_object->slug;
  }

  return $terms;
}

/************************************************************************
Flatplan editing interface
Need the ability to drag recent post/pages that are not currently into
an edition into the current edition
************************************************************************/
function pugpig_flatplan_info()
{
  global $post;

  ?>

  <style>


  img.thumb { border: 1px solid green; margin: 2px; width: 71px; height: 102px; }

#pugpig_included LI  {
  cursor: move;
  padding: 3px;
}

#pugpig_included UL {
  border: 1px solid #000;
  float: left;
  min-height: 200px;
  margin: 5px;
  min-width: 200px;
}

  </style>

  <script>
    // http://mootools.net/docs/more/Drag/Sortables
    window.addEvent('domready', FlatPlan);

    function FlatPlan()
    {
      var eca_hidden = document.getElementById("edition_contents_array");
      var mySortables = new Sortables('#pugpig_included UL', {
        clone: true,
        revert: true,
        opacity: 0.5,
        onComplete: function () {
           eca_hidden.value = mySortables.serialize(0);
        }
      });
      eca_hidden.value = mySortables.serialize(0);
    }

    function AddAll()
    {
      var uls = jQuery('#pugpig_included ul');
      var $left = jQuery(uls[0]);
      // $left.children('li').remove();
      $left.append(jQuery('#pugpig_same_terms_posts li'));
      FlatPlan();

      return;
    }

    function RemoveAll()
    {
      var uls = jQuery('#pugpig_included ul');
      var $right = jQuery(uls[1]);
      // $left.children('li').remove();
      $right.append(jQuery('#edition_included li'));
      FlatPlan();

      return;
    }

  </script>

  <input type="hidden" id="edition_contents_array" name="edition_contents_array" />

  <?php
  // Show all posts

  $ecr = pugpig_get_edition_array(get_post_custom($post->ID));
  $post_types = array_diff(pugpig_get_allowed_types(), array(PUGPIG_EDITION_POST_TYPE)); // Remove edition type from search

  $autocurate = pugpig_should_auto_curate();

  if (!$autocurate){

    echo "<div id='pugpig_included'>";

    echo "<p>Drag posts between the boxes below to add, remove, or rearrange them:</p>";

    echo "<div style='float:left;'>";

    echo "<p style='text-align:center;' id = 'pugpig_same_terms_posts'>Posts in this edition:<br /></p>";

    echo "<ul id='edition_included'>";
    foreach ($ecr as $post_id) {
      $p = get_post($post_id);
      //$thumb = "/wordpress/kaldor/raster.php?url=" . get_permalink($edition_id);
      //echo "<li id='x_$edition->ID'><img class='thumb' title='$edition->post_title' src='$thumb'  /></li>";
      if (is_object($p)) {

        // Don't show children of certain types
        if ($p->post_parent != 0
            && in_array($p->post_type, pugpig_get_hierarchical_types())) continue;

        echo wp_kses_post("<li id='$p->ID'>") . wp_kses_post(pugpig_get_flatplan_description($p));
        if ($p->post_status == 'trash' || $p->post_status == 'draft' || $p->post_status == 'pending') {
          echo "\n<span style='color:orange'>(". esc_html($p->post_status) .")</span>";
        }
        echo "<br />";
        $child_args = array(
            'post_type' => $post_types,
            'order' => 'ASC',
            'orderby' => 'menu_order',
            'post_parent' => $post_id
        );

        $children = get_children($child_args);

        foreach ($children as $child) echo "&nbsp; --> " . esc_html($child->post_title) . "<br />";

        echo "</li>";
      }
    }

    echo "</ul>";

    echo '<div name="Remove_all" href="" class="button-primary" value="Add All" onclick="RemoveAll();">Remove all posts from edition</div>';

    echo "</div>";

    echo "<div style='float:left;'>";

    echo "<p style='text-align:center;' id = 'pugpig_same_terms_posts'>Posts sharing your terms:<br /></p>";

    echo '<ul id="pugpig_same_terms_posts">';

    // Get posts in the categories for the right hand side
    $terms = pugpig_get_post_terms($post);

    if (count($terms) > 0) {

      // Search everything except $p->post_type = PUGPIG_EDITION_POST_TYPE and attachment
      $taxonomy_name = pugpig_get_taxonomy_name();
      $args = array(
          'post_type' => $post_types,
          'post_status' => 'any',
          'tax_query' => array(
            array(
                'taxonomy'  => $taxonomy_name,
                'field'     => 'slug',
                'terms'     => $terms ,
                ),
           ),
          'nopaging'  => TRUE,
          'orderby'   => 'date',
          'order'     => 'DESC',
      );

      $wp_query = new WP_Query($args);

      usort( $wp_query->posts, "pugpig_flatplan_orderby" );
      $orig_post = $post;

      while ($wp_query->have_posts() ) :
        $wp_query->the_post();

        global $post;

         // Don't show children of certain types
        if ($post->post_parent != 0
            && in_array($post->post_type, pugpig_get_hierarchical_types())) continue;

        if (!in_array($post->ID, $ecr)) {
          echo wp_kses_post("<li id='$post->ID'>") . wp_kses_post(pugpig_get_flatplan_description($post));
          if ($post->post_status == 'trash' || $post->post_status == 'draft' || $post->post_status == 'pending') {
            echo "\n<span style='color:orange'>(". esc_html($post->post_status) .")</span>";
          }
          echo "<br />";
            $child_args = array(
                'post_type' => $post_types,
                'order' => 'ASC',
                'orderby' => 'menu_order',
                'post_parent' => $post->ID
            );
            $children = get_children($child_args);
          foreach ($children as $child) echo "&nbsp; --> " . esc_html($child->post_title) . "<br />";
          echo "</li>";

        }

      endwhile;
      $post = $orig_post;
      wp_reset_postdata();
    }

    echo "</ul>";

    if (count($terms) > 0) {
      echo '<div name="Add_all" href="" class="button-primary" onclick="AddAll();" >Add these posts to edition</div>';
    } else {
      echo "This edition has no terms yet.";
    }

    echo "</div>";

    echo "</div>";

    echo "<p class='reorder'><br />Posts will automatically be ordered by taxonomy.  If you have rearranged them and want to recover the original order, remove all posts from the edition, update, then add all posts in again.</p>";

    echo "<p class='save_warning'><strong>You must hit Update to save your changes!</strong></p>";

    echo "<div style='clear: both;'></div>";

  } else {

    echo "<div id='pugpig_included'>";

    echo "<div style='float:left;'>";

    echo "<ul id='edition_included'>";
    foreach ($ecr as $post_id) {
      $p = get_post($post_id);

      if (is_object($p)) {

        // Don't show children of certain types
        if ($p->post_parent != 0
            && in_array($p->post_type, pugpig_get_hierarchical_types())) continue;

        echo wp_kses_post("<li id='$p->ID'>") . wp_kses_post(pugpig_get_flatplan_description($p));
        if ($p->post_status == 'trash' || $p->post_status == 'draft' || $p->post_status == 'pending') {
          echo "\n<span style='color:orange'>(". esc_html($p->post_status) .")</span>";
        }
        echo "<br />";
        $child_args = array(
            'post_type' => $post_types,
            'order' => 'ASC',
            'orderby' => 'menu_order',
            'post_parent' => $post_id
        );

        $children = get_children($child_args);

        foreach ($children as $child) echo "&nbsp; --> " . esc_html($child->post_title) . "<br />";

        echo "</li>";
      }
    }

    echo "</ul>";

    echo "</div>";

    echo "</div>";

    echo "<div style='clear: both;'></div>";
  }
}

/************************************************************************
Show admin notices from the session
************************************************************************/
function pugpig_add_admin_notice($message, $severity = "updated")
{
  if (empty($_SESSION['pugpig_admin_notices'])) $_SESSION['pugpig_admin_notices'] = "";
  $_SESSION['pugpig_admin_notices'] .= '<div class="' . $severity . '"><p>' .  $message . '</p></div>';
}

function pugpig_add_debug_notice($message)
{
  if (!pugpig_should_show_debug()) return;
  pugpig_add_admin_notice("<b>PUGPIG DEBUG</b> " . $message, "error");
}

function pugpig_admin_notices()
{
  if(!empty($_SESSION['pugpig_admin_notices'])) print  esc_html($_SESSION['pugpig_admin_notices']);
  unset ($_SESSION['pugpig_admin_notices']);
}
add_action( 'admin_notices', 'pugpig_admin_notices' );

/************************************************************************
Very last thing - regenerate ODPS
************************************************************************/
// Global for tracking the changes that happen in the lifecycle that need a push notification
$pugpig_edition_changed = 0;

function pugpig_shutdown()
{
 global $pugpig_edition_changed;

 if ($pugpig_edition_changed > 0) {
    pugpig_add_admin_notice($pugpig_edition_changed . " published edition(s) have been updated", "updated");
 }

}
add_action( 'shutdown', 'pugpig_shutdown' );

/************************************************************************
Custom Columns for Blog Posts and pages
************************************************************************/

add_filter('manage_pages_columns', 'pugpig_edition_columns');
add_filter('manage_posts_columns', 'pugpig_edition_columns');
function pugpig_edition_columns($columns)
{
    $columns['edition'] = 'Pugpig';

    return $columns;
}

add_action('manage_pages_custom_column',  'pugpig_edition_show_columns');
add_action('manage_posts_custom_column',  'pugpig_edition_show_columns');

function pugpig_edition_name_map($n)
{
  return $n->post_title;
}

function pugpig_edition_show_columns($name)
{
    global $post;
    switch ($name) {
        case 'edition':
          $post_type = 'post';
          $post_type = get_post_type( get_the_ID() );

          switch ($post_type) {
            case 'product':
              $extras = array();
              $extras = apply_filters('pugpig_admin_column_text_product', $extras, $post);

              break;
            case 'post':
            default:

              //echo "<a href='" . pugpig_path_to_abs_url(PUGPIG_MANIFESTPATH . "post-" . $post->ID) .".manifest'>View manifest</a><br />";

              // If the permalink structure ends in slash we add "pugpig.manifest"
              // If the permalink structure ends without a slash we add "/pugpig.manifest"
              // If we have a query string, add it

              $manifest_url = pugpig_get_manifest_url($post);
              $html_url = pugpig_get_html_url($post) . '?preview';

              echo wp_kses_post("<a href='$manifest_url'>View manifest</a><br />");
              echo wp_kses_post("<a href='$html_url'>View HTML</a><br />");

              // Add a hook to allow other links
              $extra_links = array();
              $original_post = $post;
              $extra_links = apply_filters('pugpig_admin_column_text_post', $extra_links, $post);
              foreach ($extra_links as $extra_link) echo wp_kses_post($extra_link) . "<br />";

              // Get the current editions
              $post_editions =  pugpig_get_post_editions($original_post);
              echo implode(", ", array_map('pugpig_edition_name_map', $post_editions)); //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

              $x_post_ent = pugpig_get_post_entitlement_header($post);
              if (!empty($x_post_ent))
                echo wp_kses_post("<br /><span style=\"color:grey; font-size: smaller;\">$x_post_ent</span>");

               /*
                $edition_id = get_post_meta($post->ID, PUGPIG_POST_EDITION_LINK, true);
                XXXXX
                if (!empty($edition_id)) {
                  $edition = get_post($edition_id);
                  echo pugpig_edition_filter_link($edition_id,  $edition->post_title) . "<br />";
                  echo pugpig_edition_edit_link($edition_id, "Edit edition") . "<br />";
                }
                */

                if (pugpig_should_use_thumbs()) {
                  $thumb = "/wordpress/kaldor/raster.php?url=" . get_permalink($post->ID);
                  echo wp_kses_post("<img width='60' height='80' src='$thumb'  />");
                }

              break;
          }

        break;
    }
}

/************************************************************************
Custom Columns for Editions
************************************************************************/
add_action("manage_posts_custom_column",  "pugpig_edition_custom_columns");
add_filter("manage_edit-pugpig_edition_columns", "pugpig_edition_edit_columns");

function pugpig_term_link_map($c)
{
$taxonomy_name = pugpig_get_taxonomy_name();
$filter_name = $taxonomy_name;
if ($taxonomy_name == "post_tag") $filter_name = "tag";

 $ret = sprintf( '<a href="%s">%s</a>',
            esc_url( add_query_arg( array( $filter_name => $c->slug ), 'edit.php' ) ),
            esc_html( sanitize_term_field( 'name', $c->name, $c->term_id, $taxonomy_name, 'display' ) ));

 return $ret;
}

function pugpig_edition_edit_columns($columns)
{
  return PugpigEditionColumns::headers();
}

function _pugpig_edition_get_column_text($key, $value, $short_form = false, $title = null)
{
  $title_attribute = empty($title) ? '' : "title=\"$title\"";
  $separator = $short_form ? ' ' : '<br>';

  return "<span style=\"color:grey; font-size: smaller;\">$key:</span>$separator<span $title_attribute style=\"display:inline-block; word-break: break-all;\"><strong>$value</strong></span><br>";
}

function pugpig_edition_custom_columns($column)
{
  global $post;
  $wp_ud_arr = wp_upload_dir();
  $custom = get_post_custom();

  $use_short_form = !empty($_REQUEST['mode']) && $_REQUEST['mode'] === 'list';
  $group_separator = $use_short_form ? '' : '<br>';

  $edition_key = "";
  if (isset($custom["edition_key"])) {
    $edition_key = $custom["edition_key"][0];
  }

  list($taxonomy_name, $taxonomy_slug, $taxonomy_label) = PugpigEditionColumns::getTaxonomyColumnInfo();

  $package_url = get_edition_package_url($post->ID);

  switch ($column) {
    case PugpigEditionColumns::Description:
      echo wp_kses_post(_pugpig_edition_get_column_text("Key", $edition_key, $use_short_form));

      $page_count = count(pugpig_get_edition_array($custom));
      if ($page_count === 0) {
        $page_count = "<span style=\"color: red;\">NO PAGES</span>";
      }
      echo wp_kses_post($group_separator). wp_kses_post(_pugpig_edition_get_column_text("Page Count", $page_count, $use_short_form));

      if (!empty($custom["edition_author"][0])) {
        $first_author = $custom["edition_author"][0];
        echo wp_kses_post($group_separator) . wp_kses_post(_pugpig_edition_get_column_text("Author", $first_author, $use_short_form));
      }
      break;

    case PugpigEditionColumns::Date:
      if (isset($custom["edition_date"])) {
        echo wp_kses_post($custom["edition_date"][0]) . '<br>';
      }

      $last_modified_string = pugpig_date3339(pugpig_get_page_modified($post));
      $last_modified_ago = _ago(strtotime($last_modified_string));
      echo wp_kses_post($group_separator) . wp_kses_post(_pugpig_edition_get_column_text("Last Modified", "$last_modified_ago ago", $use_short_form, $last_modified_string));

      if ($package_url != '') {
        $packaged_date = pugpig_date3339(get_edition_package_timestamp($post->ID));
        $packaged_ago = _ago(strtotime($packaged_date));
        $packaged_size = _package_edition_package_size(pugpig_get_full_edition_key($post));
        echo wp_kses_post($group_separator) . wp_kses_post(_pugpig_edition_get_column_text("Packaged", "$packaged_ago ago", $use_short_form, $packaged_date));
        echo wp_kses_post(_pugpig_edition_get_column_text("Size", "<strong>$packaged_size</strong>", $use_short_form));
      }
      break;

    case PugpigEditionColumns::Access:
      $has_samples = isset($custom["edition_samples"]);
      $is_free = isset($custom["edition_free"]);

      $cost = $is_free ? "<strong>FREE</strong>" : "PAID";

      if ($has_samples) {
        $samples_color = $is_free ? 'red' : 'green';
        $title_attribute = $is_free ? 'title="free editions do not usually have samples"' : '';
        $samples = " <span $title_attribute style=\"color:$samples_color\">(with samples)</span>";
      } else {
        $samples_color = $is_free ? 'green' : 'orange';
        $samples = " <span style=\"color:$samples_color\">(without samples)</span>";
      }

      echo wp_kses_post(_pugpig_edition_get_column_text("Protection", "$cost $samples", $use_short_form));

      $is_deleted = isset($custom["edition_deleted"]);
      if ($is_deleted) {
        echo wp_kses_post("$group_separator<span style='color:red'>DELETED TOMBSTONE</span><br>");
      }

      $x_entitlement = pugpig_get_edition_entitlement_header($post);
      if (!empty($x_entitlement)) {
        echo wp_kses_post($group_separator) . wp_kses_post(_pugpig_edition_get_column_text("Entitlement", $x_entitlement, $use_short_form));
      }

      $post_status = get_post_status($post->ID);
      if ($post_status === 'publish') {
        $post_status = '<em>published</em>';
      } else {
        $post_status = "<strong>$post_status</strong>";
      }
      echo wp_kses_post($group_separator) . wp_kses_post(_pugpig_edition_get_column_text('Status', $post_status, $use_short_form));
      break;

    case PugpigEditionColumns::Actions:
      $common_package_vars = array(
        'action' => 'generatepackagefiles',
        'p' => pugpig_get_package_manifest_url($post, false),
        'c' => pugpig_get_edition_atom_url($post, false),
        'conc' => pugpig_get_package_concurrent_connections(),
        'pbp' => '/',
        'tf' => PUGPIG_MANIFESTPATH . 'temp/packages/',
        'pf' => PUGPIG_MANIFESTPATH . 'packages/',
        'cdn' => get_option('pugpig_opt_cdn_domain'),
        'urlbase' => pugpig_strip_domain($wp_ud_arr['baseurl']) . '/pugpig-api/packages/');

      $images_package_vars = $common_package_vars;
      $images_package_vars['image_test_mode'] = 'true';

      $test_package_vars = $common_package_vars;
      $test_package_vars["testmode"] = "yes";
      $test_package_vars["debug"] = "yes";

      $package_url = BASE_URL . 'common/pugpig_packager_run.php?';
      $actions_list = array(
        'Package' => $package_url . http_build_query ($common_package_vars),
        'Images' => $package_url . http_build_query ($images_package_vars),
        'Test' => $package_url . http_build_query ($test_package_vars),
        'Web Preview' => BASE_URL . "reader/reader.html?atom=" . urlencode(pugpig_get_edition_atom_url($post, false)),
      );

      $actions_list = apply_filters( 'manage_edit-pugpig_edition_column_actions', $actions_list, $post );

      $action_links = array();
      foreach ($actions_list as $key => $value) {
          $action_links[] = "<a target='_blank' href='$value'>$key</a>";
      };

      $action_separator = $use_short_form ? ' | ' : '<br>';
      echo implode($action_separator, $action_links); //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
      break;

    case $taxonomy_slug:
      // TODO: Get the tags
      $terms = wp_get_post_terms( $post->ID, $taxonomy_name);
      echo implode(", ", array_map('pugpig_term_link_map', $terms)); //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
      break;

    case PugpigEditionColumns::Cover:
      $width = $use_short_form ? 45 : 90;
      $height = $use_short_form ? 60 : 120;
      set_post_thumbnail_size($width, $height);
      $cover_html = get_the_post_thumbnail($post->ID);
      if (empty($cover_html)) {
        $thumbnail = BASE_URL . "common/images/nocover.jpg";
        $cover_html = "<img width=\"$width\" height=\"$height\" src=\"$thumbnail\" class=\"attachment-post-thumbnail wp-post-image\" alt=\"No cover specified\">";
      }
      echo wp_kses_post($cover_html);
      break;

    case PugpigEditionColumns::Links:
      echo "<a href='" . esc_url(pugpig_get_edition_atom_url($post))."'>ATOM feed</a><br />";

      if ($package_url != '') {
        echo "<a href='" . esc_url($package_url)."'>Latest Package</a><br />";
      }

      $links = pugpig_get_edition_opds_links($post);
      foreach ($links as $link) {
        echo "<a href='" . esc_url($link['href'])."'>" . wp_kses_post($link['title']) . "</a><br />";
      }
      $links = pugpig_get_edition_atom_links($post);
      foreach ($links as $link) {
        echo "<a href='" . esc_url($link['href']) ."'>" . wp_kses_post($link['title']) . "</a><br />";
      }
      break;
  }
}

/************************************************************************
Menus
************************************************************************/
add_action('admin_menu', 'pugpig_plugin_menu');
function pugpig_plugin_menu()
{
 $capability = 'manage_options';

 add_submenu_page( 'tools.php', 'Pugpig Push Notification', 'Pugpig Push Notification', 'manage_options', 'pugpig-push-notification', 'pugpig_push_notification_form' );

 add_options_page( 'Pugpig Settings', 'Pugpig', $capability, 'pugpig-settings',  'pugpig_plugin_options');
}

/************************************************************************
Icons
************************************************************************/
add_action( 'admin_head', 'pugpig_edition_icons' );

function pugpig_edition_icons()
{
    ?>
    <style type="text/css" media="screen">
        #menu-posts-pugpig_edition .wp-menu-image {
            background: url(<?php echo esc_url(BASE_URL) ?>images/pugpig_edition-icon.png) no-repeat 6px -16px !important;
        }
        #menu-posts-pugpig_edition:hover .wp-menu-image, #menu-posts-pugpig_edition.wp-has-current-submenu .wp-menu-image {
            background-position:6px 8px !important;
        }
        #icon-edit.icon32-posts-pugpig_edition {background: url(<?php echo esc_url(BASE_URL) ?>common/images/pugpig-32x32.png) no-repeat;}

        div.button-primary {
          margin:0 auto;
          width:205px;
          border-color: #298cba;
          font-weight: bold;
          color: #fff;
          background: #21759B;
          text-shadow: rgba(0,0,0,0.3) 0 -1px 0;
          text-align:center;
          clear:both;
        }

        div.button-primary:active {
          background: #21759b;
          color: #eaf2fa;
        }

        div.button-primary:hover {
          border-color: #13455b;
          color: #eaf2fa;
        }

        p.save_warning {
          clear: both;
          color: #B40404;
        }

        p.reorder {
          clear: both;
        }
    </style>
<?php }

function pugpig_dashboard_display_feed($feed_name="Main Feed", $region=null)
{
     echo wp_kses_post("<h2>$feed_name</h2>");

     echo "Published: [<a target='_blank' href='" . esc_url(BASE_URL) . "reader/reader.html?opds=" . urlencode(pugpig_feed_opds_link(false, true, false, $region)) ."'>Preview</a>]";
     echo " [<a target='_blank' href='".esc_url(pugpig_feed_opds_link(false, false, false, $region))."'>OPDS Package Feed</a>]";
     echo " [<a target='_blank' href='".esc_url(pugpig_feed_opds_link(false, true, false, $region))."'>OPDS Atom Feed</a>]";
     if ( class_exists( 'RW_Meta_Box' ) ) {
       echo " [<a target='_blank' href='".esc_url(pugpig_feed_opds_link(false, true, true, $region))."'>Newsstand Atom Feed</a>]<br />";
     }

     echo "Draft: [<a target='_blank' href='" . esc_url(BASE_URL) . "reader/reader.html?opds=" . urlencode(pugpig_feed_opds_link(true, true, false, $region)) ."'>Preview</a>]";
     echo " [<a target='_blank' href='". esc_url(pugpig_feed_opds_link(true, false, false, $region)) ."'>OPDS Package Feed</a>]";
     echo " [<a target='_blank' href='". esc_url(pugpig_feed_opds_link(true, true, false, $region)) ."'>OPDS Atom Feed</a>]";
     if ( class_exists( 'RW_Meta_Box' ) ) {
       echo " [<a target='_blank' href='". esc_url(pugpig_feed_opds_link(true, true, true, $region)) ."'>Newsstand Atom Feed</a>]";
     }
     echo "<br /><br />";
}

/************************************************************************
Dashboard Widget
************************************************************************/
function pugpig_dashboard_widget_function()
{
    ?>

    <img style="float:right; padding: 5px;" src="<?php echo esc_url(BASE_URL) ?>common/images/pugpig-32x32.png" />

    <h4>Summary</h4>
    <?php
     $ecr = pugpig_get_editions();
    echo '<p>Total editions: ' . count($ecr) . '<br />';

    pugpig_dashboard_display_feed();

    $regions = pugpig_get_available_region_array();
    foreach (array_keys($regions) as $region) {
      pugpig_dashboard_display_feed($regions[$region] . " Feed", $region);
    }

     '</p>';
     if (count($ecr) > 0) {
       echo '<h4>Recent editions: ' . '</h4><p><ul>';
       foreach ($ecr as $post_id) {
          $post = get_post($post_id);
          echo '<li>' .  esc_html($post->post_title) . ' (' . esc_html($post->post_status) . ")</li>";
       }
       echo '</ul></p>';
     }

     if (pugpig_should_allow_search()) {
      echo "<p>Search is enabled</p>";
     } else {
      echo "<p>Search is disabled</p>";
     }

     if (!class_exists( 'RW_Meta_Box' ) ) {
      echo "<p><b>You need the metabox plugin enabled to support the Newsstand feeds</b></p>";
     }

     echo '[<a href="options-general.php?page=pugpig-settings">Settings</a>]<br />';

     $entrypoints = array(
      urlencode(pugpig_feed_opds_link(false, false) . "\r\n"),
      urlencode(pugpig_feed_opds_link(false, true) . "\r\n"),
      urlencode(pugpig_feed_opds_link(true, false) . "\r\n"),
      urlencode(pugpig_feed_opds_link(true, true) . "\r\n")
     );

     echo "[<a target='_blank' href='" . esc_url(BASE_URL) . "common/pugpig_packager_run.php?entrypoints=" . join($entrypoints) ."'>Test Entry Points</a>]<br />";

}

function pugpig_add_dashboard_widgets()
{
  wp_add_dashboard_widget('pugpig_dashboard_widget', 'Pugpig for WordPress Version ' .PUGPIG_CURRENT_VERSION, 'pugpig_dashboard_widget_function');
}
add_action('wp_dashboard_setup', 'pugpig_add_dashboard_widgets' );

/************************************************************************
Set current time
************************************************************************/
function pugpig_get_current_time()
{
  return (int)current_time('timestamp', 1);
}
