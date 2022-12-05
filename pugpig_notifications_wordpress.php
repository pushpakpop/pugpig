<?php
/**
 * @file
 * Pugpig Notifications for WordPress
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
Send notification
************************************************************************/
$push_notification_sent = false;
function pugpig_send_push_notification($message, $with_download)
{
  global $push_notification_count;
  if ($push_notification_count) {
      return;
  }
  $push_notification_count = true;

  if (!pugpig_should_send_push()) {
    pugpig_add_debug_notice("Push notifications not enabled");

    return;
  }

  $invalid_request = true;

  $description = '';
  if (empty($message)) {
    if ($with_download) {
      $description = "Sending Newsstand Push with no Message...";
      $invalid_request = false;
    } else {
      $description = "Error: no message or download.";
      $invalid_request = true;
    }
  } else {
    $invalid_request = false;
    if ($with_download) {
      $description = "Sending Newsstand Push with Message ($message)...";
    } else {
      $description = "Sending Message only ($message)...";
    }
  }
  $ret = "<div>$description</div>";

  if ($invalid_request) {
    return $ret;
  }

  $key    = get_option("pugpig_opt_urbanairship_key");
  $secret = get_option("pugpig_opt_urbanairship_secret");

  $report = '';
  if (!empty($key) && !empty($secret)) {
    $proxy_server = '';
    $proxy_port   = '';

    $report = pugpig_send_urban_airship_push(
      $key,
      $secret,
      $num = 1,
      $message,
      $with_download,
      $proxy_server,
      $proxy_port);
  } else {
    $link = _pugpig_push_notification_get_settings_link();
    $report = "Could not send push notification. Urban Airship key/secret not set in settings area. See $link.";
  }
  $ret .= "<div>$report</div>";

  return $ret;
}

function _pugpig_push_notification_get_settings_link()
{
  $url = get_admin_url(null, 'options-general.php?page=pugpig-settings').'#notifications';

  return "<a href='$url'>notification settings</a>";
}

function _pugpig_push_notification_get_message_field($message_type)
{
  return 'pugpig_push_message_' . $message_type;
}

function pugpig_push_notification_form()
{
  if (!empty($_POST['pugpig_push_message_type'])) {
    // push notification requested...
    $message_type = $_POST['pugpig_push_message_type'];
    $message_field = _pugpig_push_notification_get_message_field($message_type);
    $with_download = $_POST['pugpig_push_tab']=='download';
    $message = $_POST[$message_field];
    echo esc_html( pugpig_send_push_notification($message, $with_download));
    $push_notification_menu = admin_url('admin.php?page=pugpig-push-notification');
    echo '<div><a href="' . esc_url($push_notification_menu) . '">Return</a></div>';
    exit;
  }

  // display form...
  $last_edition_summary = '';
  $editions = pugpig_get_editions('publish', 1);
  if (count($editions) > 0) {
    $last_edition_summary = $editions[0]->post_excerpt;
  }

  $hidden_field_name = 'mt_submit_hidden';

  if (empty($_GET['tab'])) {
    $current_tab = 'download';
  } else {
    $current_tab = $_GET['tab'];
  }

  $is_tab_message  = $current_tab=='message';
  $is_tab_download = $current_tab=='download';

  $default_message = get_option("pugpig_opt_urbanairship_message");

  $key = get_option("pugpig_opt_urbanairship_key");
  $secret = get_option("pugpig_opt_urbanairship_secret");
  $send_disabled = !pugpig_should_send_push();
  $key_unset     = empty($key);
  $secret_unset  = empty($secret);

  $is_disabled   = $send_disabled | $key_unset | $secret_unset;

  $notifications_link = _pugpig_push_notification_get_settings_link();
?>

<div id="icon-edit" class="icon32 icon32-posts-pugpig_edition"><br /></div><h2>Pugpig Push Notification</h2>

<h2 class="nav-tab-wrapper">
  <a class='nav-tab<?php if ($is_tab_download) { print ' nav-tab-active'; } ?>' href='?page=<?php print !empty( $_GET['page'] ) ? esc_attr( $_GET['page'] ) : ""; //phpcs:ignore here is not coming full links WordPress.Security.EscapeOutput.OutputNotEscaped ?>&amp;tab=download' onclick='return check()'>Background Download Push</a>
  <a class='nav-tab<?php if ($is_tab_message) { print ' nav-tab-active'; } ?>' href='?page=<?php print !empty( $_GET['page'] ) ? esc_attr( $_GET['page'] ) : ""; //phpcs:ignore here is not coming full links WordPress.Security.EscapeOutput.OutputNotEscaped?>&amp;tab=message'>Message Push</a>
</h2>

<?php if ($is_tab_download): ?>
<p>For Newsstand applications, use this form* to get the all the readers'** applications to perform a background download of the new content and optionally display a message when the download has completed.</p>
<p>It should only be used:
  <ul style="list-style:disc inside">
    <li>Once a new edition has been packaged, published and <strong>checked on suitable devices</strong></li>
    <li><strong>Once</strong> per edition</li>
    <li>Just <strong>after publishing</strong> an edition</li>
    <li><strong>Evenings</strong> are best as users tend to be plugged in and on wifi</li>
  </ul>
</p>
<?php else: ?>
  <p>Use this form* to send a message to all the readers**.</p>
<?php endif; ?>

<form name="formpush" method="post" action="" onsubmit="return validateForm()">
  <input type="hidden" name="<?php echo esc_attr( $hidden_field_name); ?>" value="Y">
  <input type="hidden" name="pugpig_push_tab" value="<?php print esc_attr( $current_tab); ?>">
  <table class="form-table">

    <?php if ($is_tab_download): ?>
    <tr valign="top">
      <th scope="row"><label for="pugpig_push_message_type">No message</label></th>
      <td>
        <input type="radio" name="pugpig_push_message_type" value="none" />
        <input type="hidden" name="pugpig_push_message_none" value="" />
      </td>
    </tr>
    <?php endif; ?>

    <tr valign="top">
      <th scope="row"><label for="pugpig_push_message_type">The last edition summary</label></th>
      <td>
        <input type="radio" name="pugpig_push_message_type" value="edition" <?php if (empty($last_edition_summary)) {print 'disabled';} ?> />
        <input type="hidden" name="pugpig_push_message_edition" value="<?php echo esc_attr($last_edition_summary); ?>" />
      </td>
      <td><?php if (empty($last_edition_summary)): ?>
        <em style="color:red;">last edition summary unset</em>
      <?php else:
        print esc_html($last_edition_summary);
      endif; ?>
      </td>
    </tr>

    <tr valign="top">
      <th scope="row"><label for="pugpig_push_message_type">The default notification message</label></th>
      <td>
        <input type="radio" name="pugpig_push_message_type" value="default" <?php if (empty($default_message)) {print 'disabled';} ?>/>
        <input type="hidden" name="pugpig_push_message_default" value="<?php print esc_attr($default_message); ?>" />
      </td>
      <td><?php if (empty($default_message)): ?>
        <em style="color:red;">no message set</em> (<?php print esc_url($notifications_link); ?>)
      <?php else:
        print esc_html($default_message);
      endif; ?>
      </td>
    </tr>

    <tr valign="top">
      <th scope="row"><label for="pugpig_push_message_type">Your own message</label></th>
      <td><input type="radio" name="pugpig_push_message_type" value="custom" /></td>
      <td><input name="pugpig_push_message_custom" type="text" style="width:400px" /></td>
    </tr>

    <tr valign="top">
      <th scope="row"></th>
      <td></td>
      <td><input type="submit" value="<?php print $is_tab_download ? 'Push update' : 'Send message'?>" <?php if ($is_disabled) { print 'disabled'; } ?>/>
        <?php if ($is_disabled) {
          print '<br/>';
          if ($send_disabled) {
            print '<em>Send push notifications</em> is not enabled.<br/>';
          } else {
            if ($key_unset) {
              print '<em>Urban Airship Key</em> is not set.<br/>';
            }
            if ($secret_unset) {
              print '<em>Urban Airship Application Master Secret</em> is not set.<br/>';
            }
          }
          print "See " . esc_url( $notifications_link ) . ".";
        } ?>
        </td>
    </tr>
  </table>
</form>

<br/>
<small>* Please note that this form uses your Urban Airship account and you may be charged for notifications.<br/>
** Messages will only be received by readers who have push notifications enabled on their device.</small>

<script>
  function validateForm()
  {
    // check an option is selected
    var $checked = jQuery("input[type=radio]:checked");
    if ($checked.size()==0) {
      alert("Select a message to send.");

      return false;
    }

    // check that selected message is not empty
    var type='pugpig_push_message_' + $checked.first().val();
    if (type!='pugpig_push_message_none') {
      var message=jQuery("input[name='"+type+"']").first().val();
      if (message.length==0) {
        alert("No message text entered.");

        return false;
      }
    }

    return true;
  }
</script>
<?php
  exit();
} ?>
