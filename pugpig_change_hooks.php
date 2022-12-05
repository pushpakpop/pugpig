<?php
/**
 * @file
 * Pugpig Change Hooks
 */
?><?php
/*

Licence:
==============================================================================
(c) 2011, Kaldor Holdings Ltd
This module is released under the GNU General Public License.
See COPYRIGHT.txt and LICENSE.txt

 */?><?php

/************************************************************************
Update the last modified date of a single editions
************************************************************************/
function pugpig_touch_edition($edition_id)
{
  wp_update_post( array("ID" => $edition_id, "post_modified" => current_time('mysql')) );
}

/************************************************************************
Update the last modified date of all editions
************************************************************************/
function pugpig_touch_all_editions()
{
 foreach (pugpig_get_editions() as $edition) {
    pugpig_touch_edition($edition->ID);
 }
}
/************************************************************************
Change theme means rebuild the theme manifest and update all editions
************************************************************************/
add_action('switch_theme', 'pugpig_theme_activate');
function pugpig_theme_activate($new_theme)
{
  // pugpig_save_theme_manifest();
  // pugpig_add_admin_notice("Changed theme. Updating date of all pugpig HTML5 manifests.", "updated");
  // pugpig_build_all_html5_manifests();
  // pugpig_touch_all_editions();
}

/************************************************************************
Publish status transition handler
It doesn't seem like we need this at the moment
function pugpig_transition_post_status($new_status, $old_status, $post)
{

  if (!isset($post)) return;
  if ($post->post_type == "revision") return;
  if ($post->post_type == "attachment") return;
  if(  ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) || $post->post_status == 'auto-draft' ) return;

  // No point if nothing has happened
  if ($new_status == $old_status) return;


  if ($old_status == 'publish') {
  } else if ($new_status == 'publish') {
  } else {
  }

}
add_action('transition_post_status', 'pugpig_transition_post_status', 100, 3);
************************************************************************/

/************************************************************************
Manipulate the array of posts in an edition
************************************************************************/
function pugpig_add_edition_array($edition_id, $post_id)
{
      // Get the existing array
      $ecr = pugpig_get_edition_array(get_post_custom($edition_id));

      // Add the new item if it doesn't already exist
      if (!in_array($post_id, $ecr)) {
        array_push($ecr, $post_id);
        pugpig_save_edition_array($edition_id, $ecr);
      }
}

function pugpig_delete_edition_array($edition_id, $post_id)
{
     // Get the existing array
      $ecr = pugpig_get_edition_array(get_post_custom($edition_id));

      // Add the new item if it doesn't already exist
      if (in_array($post_id, $ecr)) {
        $ecr = array_diff($ecr, array($post_id));
        pugpig_save_edition_array($edition_id, $ecr);
      }
}

function pugpig_save_edition_array($edition_id, $ecr, $touch = true)
{
        // Update the edition. Needs to update Last Modified Time
        if (count($ecr) == 0) {
          delete_post_meta($edition_id, PUGPIG_EDITION_CONTENTS_ARRAY);
        } else {
          update_post_meta($edition_id, PUGPIG_EDITION_CONTENTS_ARRAY, $ecr);
        }

        if ($touch) {
          pugpig_touch_edition($edition_id);
        }
}

/************************************************************************
Save Hook
************************************************************************/
add_filter('pugpig_get_post_modified_time', 'pugpig_get_post_modified_time_edition', 11, 2);
function pugpig_get_post_modified_time_edition($modified, $post)
{
  // Get the most recent modified date of all pages in editions
  if (!empty($post) && $post->post_type == PUGPIG_EDITION_POST_TYPE) {
    $page_ids = pugpig_get_edition_array(get_post_custom($post->ID));

    foreach ($page_ids as $page_id) {
      $page = get_post($page_id);
      if ($page === null) {
        $page_modified = 0;
      } elseif ($post->post_type != PUGPIG_EDITION_POST_TYPE){
        $page_modified = pugpig_get_page_modified($page);
      } else {
        $page_modified = strtotime($page->post_modified_gmt);
      }
      if ($page_modified > $modified) {
        $modified = $page_modified;
      }
    }
  }

  return $modified;
}

add_action('save_post', 'pugpig_save_post', 10);
function pugpig_save_post($post_id)
{
  $post = get_post($post_id);
  if (
    !isset($post)
    || $post->post_type == "revision"
    || $post->post_type == "attachment"
    || ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE )
    || $post->post_status == 'auto-draft') {
    return;
  }

  pugpig_add_debug_notice("Saving " . $post_id . " (" . $post->post_type . ") -> status: " . $post->post_status);

  if ($post->post_type == PUGPIG_EDITION_POST_TYPE) {
    // Save Edition
    pugpig_save_edition($post);

    // We've come via the quick edit. Maybe need something extra?
    if (defined('DOING_AJAX') && DOING_AJAX) {
      pugpig_add_debug_notice("QUICK EDIT: Status " . $post->post_status, "error");
    }
  }

  wp_reset_query();

  // Redirect if we want to create a child page
  if (isset($_REQUEST['pugpig_create_child'])) {
    unset($_REQUEST['pugpig_create_child']);

    // Count number of childrrn
    $children = get_children( array('post_parent' => $post->ID));

      // Get the taxonomy name Pugpig uses and any terms
    // Use these for the create post
    $taxonomy_name = pugpig_get_taxonomy_name();
    $tax_input = array();
    if (!empty($taxonomy_name)) {
      $tax_input[$taxonomy_name] = pugpig_get_post_terms($post);
    }

    $post_insert = array(
      'post_title'    => "{subarticle " . (count($children) + 1) .  "}",
      'post_type'  => $post->post_type,
      'post_parent'   => $post->ID,
      'menu_order' => 50,
      'tax_input' => $tax_input
    );

    // var_dump($post_insert);
    $post_id = wp_insert_post( $post_insert );
    $edit_url = get_edit_post_link( $post_id, "edit" ) ;
    wp_redirect( $edit_url );
    exit();
  }
}

/************************************************************************
ADD PRIVATE/DRAFT/FUTURE/PENDING PAGES TO PARENT DROPDOWN IN PAGE ATTRIBUTES AND QUICK EDIT
http://wpsmith.net/2013/wp/add-privatedraftfuturepending-pages-to-parent-dropdown-in-page-attributes-and-quick-edit/
************************************************************************/
add_filter( 'page_attributes_dropdown_pages_args', 'wps_dropdown_pages_args_add_parents' );
add_filter( 'quick_edit_dropdown_pages_args', 'wps_dropdown_pages_args_add_parents' );
/**
 * Add private/draft/future/pending pages to parent dropdown.
 */
function wps_dropdown_pages_args_add_parents($dropdown_args, $post = null)
{
    $dropdown_args['post_status'] = array( 'publish', 'draft', 'pending', 'future', 'private', );

    return $dropdown_args;
}

/************************************************************************
Delete Hook
************************************************************************/
add_action('delete_post', 'pugpig_delete_post');
function pugpig_delete_post($post_id)
{
  $post = get_post($post_id);
  if ($post->post_type == "revision") return;

  pugpig_add_debug_notice("Deleting " . $post_id . " (" . $post->post_type . ") -> status: " . $post->post_status);
}

/************************************************************************
Save Custom Edition
************************************************************************/
function pugpig_save_edition($post)
{
  if ($post->post_status == "trash" || isset($_POST["edition_contents_array"])) {

    $newarr = array();

    if ($post->post_status != "trash") {
      // Get the new array if we're not trashing
      $newarr = explode(",", $_POST["edition_contents_array"]);
      $newarr = array_diff($newarr, array(""));
    }

    $oldarr = pugpig_get_edition_array(get_post_custom($post->ID));
    $added = array_diff($newarr, $oldarr);
    $removed = array_diff($oldarr, $newarr);

    foreach ($added as $p) {
      //pugpig_save_post_edition_id($p, $post->ID);
    }

    foreach ($removed as $p) {
      //pugpig_save_post_edition_id($p, "");
    }

    if ($post->post_status != "trash") {
      // Replace with new array if we'renot trashing
      pugpig_save_edition_array($post->ID, $newarr, false);
    }

  }

  if (isset($_POST["edition_date"])) {
     update_post_meta($post->ID, "edition_date", $_POST["edition_date"]);
   }

  if (isset($_POST["edition_free_exists"])) {
    if (empty($_POST["edition_free"])) {
      delete_post_meta($post->ID, "edition_free");
    } else {
      update_post_meta($post->ID, "edition_free", $_POST["edition_free"]);
    }
  }

  if (isset($_POST["edition_samples_exists"])) {
    if (empty($_POST["edition_samples"])) {
      delete_post_meta($post->ID, "edition_samples");
    } else {
      update_post_meta($post->ID, "edition_samples", $_POST["edition_samples"]);
    }
  }

 if (isset($_POST["edition_deleted_exists"])) {
    if (empty($_POST["edition_deleted"])) {
      delete_post_meta($post->ID, "edition_deleted");
    } else {
      update_post_meta($post->ID, "edition_deleted", $_POST["edition_deleted"]);
    }
  }

   if (isset($_POST["edition_key"])) {
     update_post_meta($post->ID, "edition_key", trim($_POST["edition_key"]));
   }

   if (isset($_POST["edition_author"])) {
     update_post_meta($post->ID, "edition_author", $_POST["edition_author"]);
   }

   if (isset($_POST["edition_sharing_link"])) {
     update_post_meta($post->ID, "edition_sharing_link", $_POST["edition_sharing_link"]);
   }

}

/************************************************************************
Validate is called after save. Ensure we can't publish incomplete editions
************************************************************************/
add_action('save_post', 'pugpig_validate_post', 20);
function pugpig_validate_post($post_id)
{
  global $SKIP_EDITION_VALIDATION;
  if ($SKIP_EDITION_VALIDATION) return;

  $post = get_post($post_id);

  if (!isset($post)) return;
  if ($post->post_type == "revision") return;
  if(  ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) || $post->post_status == 'auto-draft' ) return;

  // Validate an edition publish
  if ($post->post_type == PUGPIG_EDITION_POST_TYPE && ( isset( $_POST['publish'] ) || isset( $_POST['save'] ) ) && $_POST['post_status'] == 'publish' ) {
    pugpig_add_debug_notice("Validating " . $post_id . " (" . $post->post_type . ") -> status: " . $post->post_status, "error");

    // init completion marker (add more as needed)
    $publish_errors = array();

    // retrieve meta to be validated
    $meta_edition_key = get_post_meta( $post_id, 'edition_key', true );
    if (empty($meta_edition_key)) {
      array_push($publish_errors, "Edition Key must be supplied.");
    } else if (preg_match('|' . '^[0-9a-zA-Z.,-_]+$' . '|', $meta_edition_key, $matches) === 0){
      array_push($publish_errors, "Edition Key may only contain letters (a-Z), digits (0-9), fullstop (.), comma (,), hyphen (-) and underscore(_).");
    } else {
      $edition_keys = pugpig_get_all_edition_keys();
      $duplicates_edition_keys = array_keys($edition_keys, $meta_edition_key);
      if (count($duplicates_edition_keys) > 1) {
        $duplicates_edition_keys = array_keys($edition_keys, $meta_edition_key);
        $original_edition = array_values(array_diff($duplicates_edition_keys, array($post->post_title)));
        array_push($publish_errors, "Edition keys must be uniques. Edition key \"${meta_edition_key}\" already used in \"${original_edition[0]}\".");
      }
    }

    $meta_edition_date = get_post_meta( $post_id, 'edition_date', true );
    if ( empty( $meta_edition_date ) ) {
        array_push($publish_errors, $post->post_title . ": Edition date must be supplied.");
    } else {
      if (!pugpig_check_date_format($meta_edition_date)) {
          array_push($publish_errors, $post->post_title . ": Edition date " . $meta_edition_date .  " is not valid.");
      }
    }

    $taxonomy_name = pugpig_get_taxonomy_name();
    if (pugpig_should_auto_tag_edition() && !empty($taxonomy_name) && taxonomy_exists($taxonomy_name)){
      wp_set_object_terms($post_id, $post->post_name, $taxonomy_name, true);
    } else if (pugpig_should_auto_tag_edition()){
      array_push($publish_errors, "Cannot automatically tag edition as the taxonomy does not exist");
    }

    $is_pdf_edition = false;
    $pdf = get_attached_media('application/pdf', $post->ID);
    if (count($pdf) > 0){
      $media_id = max(array_keys($pdf));
      $pdf = $pdf[$media_id];
      $is_pdf_edition = true;
    }

    $ecr = pugpig_get_edition_array(get_post_custom($post->ID));
    if (count($ecr) == 0 && !$is_pdf_edition) {
        array_push($publish_errors, "You cannot publish an empty edition.");
    }

    // on attempting to publish - check for completion and intervene if necessary
        //  don't allow publishing while any of these are incomplete

    $wordpress_title = get_the_title($post->ID);

    if ( count($publish_errors) > 0 ) {
      if (!empty($wordpress_title)){
        array_push($publish_errors, "<b>Please fix these errors before publishing this edition. The post status for edition '". $wordpress_title . "' has been set to 'Pending'.</b>");
      } else {
        array_push($publish_errors, "<b>Please fix these errors before publishing this edition. The post status for this edition has been set to 'Pending'.</b>");
      }
        global $wpdb;
        $wpdb->update( $wpdb->posts, array( 'post_status' => 'pending' ), array( 'ID' => $post_id ) );
        foreach ($publish_errors as $e) pugpig_add_admin_notice($e, "error");

        // filter the query URL to change the published message
        add_filter( 'redirect_post_location', function( $location ) { return add_query_arg("message", "4", $location); } );

        return;
    }

    // Rebuild the edition manifests
    // pugpig_build_edition_manifests($post);

    // Increase the count of items needed for push notifications
    if ($post->post_status == "publish") {
      global $pugpig_edition_changed;
      $pugpig_edition_changed++;
      pugpig_add_debug_notice("Published edition changed: " . $post->ID);
    }

  }
}

/************************************************************************
Validation Helpers
************************************************************************/
function pugpig_check_date_format($str)
{
  $ptn = "/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/";
  if (preg_match($ptn, $str, $matches) > 0) return true;
  $ptn = "/^[0-9]{4}-[0-9]{2}$/";
  if (preg_match($ptn, $str, $matches) > 0) return true;
  $ptn = "/^[0-9]{4}$/";
  if (preg_match($ptn, $str, $matches) > 0) return true;
  return false;
}

/************************************************************************
Filter to allow modules to add items to the credential response headers
************************************************************************/
function pugpig_get_extra_credential_headers($product_id, $user, &$comments)
{
  $extra_headers = array();
  $extra_headers = apply_filters('pugpig_extra_credential_headers', $extra_headers, $product_id, $user);

  if (count($extra_headers)) $comments[] = "Hooks added " . count($extra_headers) . " extra headers";
  return $extra_headers;

}
