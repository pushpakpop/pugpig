<?php
/*

Licence:
==============================================================================
(c) 2011, Kaldor Holdings Ltd
This module is released under the GNU General Public License.
See COPYRIGHT.txt and LICENSE.txt

*/
?><?php

class PugpigCDSAPIType { /* final with private constructor? */
    const GET  = 'get';
    const POST = 'post';
}

define('PUGPIG_CDS_DEFAULT_API_TYPE', PugpigCDSAPIType::GET);

class PugpigCDSStatus { /* final with private constructor? */
    const NoConnection = 'noconnection';
    const BadParams    = 'badparams';
    const Unknown      = 'unknown';
    const Inactive     = 'inactive';
    const Active       = 'active';
}

class PugpigCDSMessage { /* final with private constructor? */
    const Problem      = 'There is currently a problem retreiving your subscription details';
}

class PugpigCDSServiceResponseStatus { /* final with private constructor? */
    const Current      = 'CURRENT';
}

class PugpigCDSResponseHTTPStatus { /* final with private constructor? */
    const OK           = 200;
}

function _pugpig_subs_cds_http_request($api_type, $url_base, $originator, $urn, $surname, &$comments, $proxy_server='', $proxy_port='')
{
  $ret = array();

  $ch = curl_init();

  $comments[] = "API Type: '$api_type'";
  $comments[] = "Originator: '$originator'";
  $comments[] = "Proxy server: $proxy_server";
  $comments[] = "Proxy port: $proxy_port";

  switch ($api_type) {
    case PugpigCDSAPIType::GET:
      $orignator_param = '';
      if (strpos($url_base, '?originator=')===FALSE) {
        $orignator_param = '?originator='.$originator;
      }
      $url = $url_base . $orignator_param. '&urn=' . urlencode($urn) . '&surname=' . urlencode($surname) . '&type=EXTENDED';
      curl_setopt($ch, CURLOPT_URL, $url);
      $comments[] = "Requesting GET with url '$url'";
    break;
    case PugpigCDSAPIType::POST:
      $url = $url_base;
      curl_setopt($ch, CURLOPT_URL, $url);

      $params = http_build_query(array(
        'originator' => $originator,
        'urn'        => $urn,
        'surname'    => $surname,
        'type'       => 'full'
     ));
      curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
      curl_setopt($ch, CURLOPT_POST, 1);
      $comments[] = "Requesting POST with url '$url' and parameters $params";
    break;
    default:
      $comments[] = "Unknown api type";
    break;
  }

  curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
  curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
  curl_setopt($ch, CURLOPT_TIMEOUT,        PUGPIG_CURL_TIMEOUT);

  if (!empty($proxy_server)) {
    if (empty($proxy_port)) {
      $proxy_port = '8080';
    }
    curl_setopt($ch, CURLOPT_PROXY, "$proxy_server:$proxy_port");
  }

  $ret['xml'] = curl_exec($ch);
  $ret['http_status'] = curl_getinfo($ch, CURLINFO_HTTP_CODE);

  curl_close($ch);

  return $ret;
}

function _pugpig_subs_cds_get_message_for_status($status, $failed, $active_message, $inactive_message)
{
  $message = '';
  switch ($status) {
    default:
    case PugpigCDSStatus::NoConnection:
    case PugpigCDSStatus::BadParams:
    case PugpigCDSStatus::Unknown:
      $message = PugpigCDSMessage::Problem;
    break;
    case PugpigCDSStatus::Inactive:
      $message = $inactive_message;
    break;
    case PugpigCDSStatus::Active:
      $message = $active_message;
    break;
  }

  if ($failed) {
    $message .= ' (f)';
  }

  return $message;
}

function _pugpig_subs_cds_read_status_from_response($xpathvar, &$comments, $fail_open)
{
  $status = PugpigCDSStatus::Unknown;

  $xpath_query = '//SUBSCRIBER/SUBSCRIPTION/Status';
  $statusNodes = $xpathvar->query($xpath_query);

  if ($statusNodes->length==0) {
    // no status to read
    $comments[] =  "Failed to read status in response - did not get $xpath_query in the CDS response";
    if ($fail_open) {
      $status = PugpigCDSStatus::Active;
      $comments[] =  "Failing open";
    } else {
      $status = PugpigCDSStatus::Unknown;
      $comments[] =  "Failing closed";
    }
  } else {
    // status can be read from response
    $cds_status = $statusNodes->item(0)->textContent;
    $comments[] = "CDS status: $cds_status";

    if (empty($cds_status)) {
      $status = PugpigCDSStatus::Unknown;
    } elseif ($cds_status == PugpigCDSServiceResponseStatus::Current) {
      $status = PugpigCDSStatus::Active;
    } else {
      $status = PugpigCDSStatus::Inactive;
    }
  }

  return $status;
}

function _pugpig_subs_cds_read_issues_from_response($xpathvar, $issue_prefix)
{
  $issues = array();

  // adding issues accessible to the user
  $xpath_query = '//SUBSCRIBER/ISSUEHISTORY/Issue/LabelID';
  $rissues = $xpathvar->query($xpath_query);

  // Get all the issues that CDS give us
  foreach ($rissues as $x) {
     $issue_tag = $issue_prefix . $x->nodeValue;
     $issues[] = $issue_tag;
  }

  return $issues;
}

function _pugpig_subs_cds_find_user_issues(&$xpathvar, $issue_prefix, $status, $published_edition_tags, &$comments)
{
  $issues = _pugpig_subs_cds_read_issues_from_response($xpathvar, $issue_prefix);

  // Loop over all issues the user has. If we have one that is in our published issue
  // list, ensure they also have the two previous issues
  for ($i = (count($issues)-1); $i>=0; $i--) {
    if (in_array($issues[$i], $published_edition_tags)) {
      $pos = array_search($issues[$i], $published_edition_tags);
      if (count($published_edition_tags) > $pos+1 && !in_array($published_edition_tags[$pos+1], $issues)) {
        $comments[] = "Adding grace issue " . $published_edition_tags[$pos+1] . " as they already have " . $issues[$i];
        array_push($issues,$published_edition_tags[$pos+1]);
      }
      if (count($published_edition_tags) > $pos+2 && !in_array($published_edition_tags[$pos+2], $issues)) {
        $comments[] = "Adding grace issue " . $published_edition_tags[$pos+2] . " as they already have " . $issues[$i];
        array_push($issues,$published_edition_tags[$pos+2]);
      }
    }
  }

  // Ensure an active user has the two most recent editions
  if ($status == PugpigCDSStatus::Active) {
   $comments[] = 'User is active. Ensuring they have the most recent 2 published';
   if (count($published_edition_tags) > 0 && !in_array($published_edition_tags[0], $issues)) {
     $comments[] = 'Adding grace recent issue ' . $published_edition_tags[0];
     array_push($issues, $published_edition_tags[0]);
   }
   if (count($published_edition_tags) > 1&& !in_array($published_edition_tags[1], $issues)) {
     $comments[] = 'Adding grace recent issue ' . $published_edition_tags[1];
     array_push($issues, $published_edition_tags[1]);
   }
  } else {
   $comments[] = "No grace issues for inactive user";
  }

   return $issues;
}

function _pugpig_subs_cds_get_token_status_and_xml($url_base, $token, $fail_open, &$comments, $proxy_server, $proxy_port, $api_type, $originator)
{
  $status   = PugpigCDSStatus::NoConnection;
  $xpathvar = null;
  $failed   = false;

  if (empty($originator)) {
    // extract from URL instead
    $query = parse_url($url, PHP_URL_QUERY);
    $params = array();
    parse_str($get_string, $params);
    if (array_key_exists('originator', $params)) {
      $originator = $params['originator'];
    }
  }

  $urn     = '';
  $surname = '';
  $issues  = array();

  $tokens  = explode(':', $token);
  if (count($tokens) == 2) {
    $urn     = $tokens[0];
    $surname = $tokens[1];
  }

  if (empty($urn) || empty($surname)) {
    $status  = PugpigCDSStatus::BadParams;
  } else {
    $ret = _pugpig_subs_cds_http_request($api_type, $url_base, $originator, $urn, $surname, $comments, $proxy_server, $proxy_port);

    if (!is_array($ret) || $ret['http_status']!=PugpigCDSResponseHTTPStatus::OK) {
      $failed = true;
      if (!is_array($ret) || !array_key_exists('http_status', $ret)) {
        $comments[] =  'Failed to get valid response!';
      } else {
        $comments[] =  'Failed  - did not get a HTTP 200 response from CDS (got status '. $ret['http_status'] . ')';
      }
      if ($fail_open) {
        $comments[] =  'Failing open';
        $status = PugpigCDSStatus::Active;
      } else {
        $comments[] =  'Failing closed';
        $status = PugpigCDSStatus::Unknown;
      }
    } else {
       // got a successful response, so read the XML
      $xmldoc = new DOMDocument();
      $xmldoc->loadXML($ret['xml'], LIBXML_NOCDATA);
      $xpathvar = new DOMXPath($xmldoc);

      // find the status
      $status = _pugpig_subs_cds_read_status_from_response($xpathvar, $comments, $fail_open);
    }
  }

  return array(
    'status'   => $status,
    'xpathvar' => $xpathvar,
    'failed'   => $failed
    );
}

function _pugpig_subs_cds_get_token_status_and_issues($url_base, $issue_prefix, $ignore_issue_based, $token, &$issues, &$comments, &$failed, $published_edition_tags, $proxy_server, $proxy_port, $api_type, $originator)
{
  $verified  = _pugpig_subs_cds_get_token_status_and_xml($url_base, $token, true, $comments, $proxy_server, $proxy_port, $api_type, $originator);
  $issues    = array();

  if ($ignore_issue_based) {
    $comments[] = "Ignoring issue based authentication";
    $issues = null; // allow access to all issues if active
  } else {
    $comments[] = "Using issue based authentication";
    $xpathvar  = $verified['xpathvar'];
    if (isset($xpathvar)) {
      $issues = _pugpig_subs_cds_find_user_issues($xpathvar, $issue_prefix, $verified['status'], $published_edition_tags, $comments);
    }
  }

  $failed = $verified['failed'];

  return $verified['status'];
}

function _pugpig_subs_cds_get_verified_token($url_base, $urn, $surname, &$comments, $proxy_server, $proxy_port, $api_type, $originator)
{
  $token = ''; // Any other status means they don't exist

  $verified = _pugpig_subs_cds_get_token_status_and_xml($url_base, "$urn:$surname", false, $comments, $proxy_server, $proxy_port, $api_type, $originator);
  $status = $verified['status'];

  if ($status == PugpigCDSStatus::Active
    || $status == PugpigCDSStatus::Inactive) {
    $token = "$urn:$surname";
  }

  return $token;
}

function pugpig_subs_cds_sign_in($url_base, $urn, $surname, $proxy_server=null, $proxy_port=null, $comments=array(), $api_type=PUGPIG_CDS_DEFAULT_API_TYPE, $originator=null)
{
  $comments[] = "URN: $urn SURNAME: $surname";

  $token = _pugpig_subs_cds_get_verified_token($url_base, $urn, $surname, $comments, $proxy_server, $proxy_port, $api_type, $originator);

  _pugpig_subs_sign_in_response($token, $comments);
}

function pugpig_subs_cds_verify_subscription($url_base, $issue_prefix, $ignore_issue_based, $token, $published_edition_tags, $active_message, $inactive_message, $proxy_server=null, $proxy_port=null, $comments=array(), $api_type=PUGPIG_CDS_DEFAULT_API_TYPE, $originator=null)
{
  $comments[] = "Token is $token";
  $issues = array();
  $failed = false;
  $status = _pugpig_subs_cds_get_token_status_and_issues($url_base, $issue_prefix, $ignore_issue_based, $token, $issues, $comments, $failed, $published_edition_tags, $proxy_server, $proxy_port, $api_type, $originator);
  $message = _pugpig_subs_cds_get_message_for_status($status, $failed, $active_message, $inactive_message);

  _pugpig_subs_verify_subscription_response($status, $comments, $message, $issues);
}

function pugpig_subs_cds_edition_credentials($url_base, $issue_prefix, $ignore_issue_based, $token, $product_id, $published_edition_tags, $secret, $proxy_server=null, $proxy_port=null, $comments=array(), $api_type=PUGPIG_CDS_DEFAULT_API_TYPE, $originator=null)
{
  $comments[] = "Token is $token";
  $issues   = array();
  $failed   = false;
  $status   = _pugpig_subs_cds_get_token_status_and_issues($url_base, $issue_prefix, $ignore_issue_based, $token, $issues, $comments, $failed, $published_edition_tags, $proxy_server, $proxy_port, $api_type, $originator);

  $error_message = '';
  $entitled = true;

  if ($status!=PugpigCDSStatus::Active && $status!=PugpigCDSStatus::Inactive) {
    $error_message = 'User not recognised or suspended.';
    $entitled = false;
  } elseif ($issues === NULL) {
    $comments[] = 'User has access to all issues';
    $entitled = true;
  } else {
    $comments[] = 'User has access to only some issues';
    if (in_array($product_id, $issues)) {
      $comments[] = 'This issue is in the allowed list';
      $entitled = true;
    } else {
      $comments[] = 'This issue is not in the allowed list';
      $error_message = 'Your subscription does not entitle you to this issue.';
      $entitled = false;
    }
  }

  _pugpig_subs_edition_credentials_response($product_id, $secret, $entitled, $status, $comments, array(), $error_message);
}
