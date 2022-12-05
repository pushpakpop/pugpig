<?php
/**
 * @file
 * Pugpig Auth Test Common Functions
 */
?><?php
/*

Licence:
==============================================================================
(c) 2011, Kaldor Holdings Ltd
This module is released under the GNU General Public License.
See COPYRIGHT.txt and LICENSE.txt

 */?><?php

include_once "../pugpig_subs.php";
include_once "../pugpig_subs_xml.php";
include_once "../pugpig_subs_test.php";
include_once "../pugpig_utilities.php";

$longtoken = '{access_token: "eyJhbGciOiJub25lIiwiY3R5IjoiYXBwbGljYXRpb25cL2pzb24ifQ.eyJ0b2tlbklkIjoiODU2YmYyMGUtNDU2MC00YjM2LWIwMWMtMGU2OGI0MmU2MjcyIiwicHJvZmlsZVB1YmxpY0lkIjoiODU1NjNmZjEtNjg0Yy00NjU2LWFkY2UtODRhY2MyOGM3ZmNkIiwic2Vzc2lvbiI6eyJpZCI6IjY3OTViZmRiLWIyYTEtNDIwYS1hMDI3LTdlNTc1YTkzNzMyOCIsInNlc3Npb25LZXkiOiJNMnRNYUZwU1ZTODRSUzlVVnpsRlNIbzFXVTVWYkhNelkwVmFMMnhTVkZSeGJYTmpWMjV2Y1VsWGQzaHhZM1ZRU0dObVJGSm1jMjFYYVVGaGNHUktSMWRJY3pZM1UySjZWRU5aUFEiLCJzZWN1cmVTZXNzaW9uS2V5IjoiY25BMlNVUllObkJDZEhWeE1WSktSMGhKVEZST1QxTk5hMWhwZW5GYU4zazRaREpMYW1SQlIwSXZlRTlXZFZKalNXOVpkR2xuUFQwIn0sInBlcm1pc3Npb25zIjpbeyJuYW1lIjoiRmVlZEFwcHMiLCJ1bmlxdWVJZGVudGlmaWVyIjoiVE1HX2NvbnRpbnVvdXNfZmVlZF9hcHBzIiwidHlwZSI6IkZlZWRBcHBzIiwic2NvcGVzIjpbXSwicGVybWlzc2lvblRpY2tldCI6Ik5PTkUiLCJleHBpcmVzX2F0IjoxNDE1NTc4MTQwfSx7Im5hbWUiOiJFZGl0aW9uQXBwcyIsInNjb3BlcyI6W10sInBlcm1pc3Npb25UaWNrZXQiOiJOT05FIiwiZXhwaXJlc19hdCI6MTQxNTU3ODE0MH0seyJuYW1lIjoid2Vic2l0ZSIsInNjb3BlcyI6W10sInBlcm1pc3Npb25UaWNrZXQiOiJOT05FIiwiZXhwaXJlc19hdCI6MTQxNTU3ODE0MH0seyJuYW1lIjoiY29tbWVudHMiLCJzY29wZXMiOltdLCJwZXJtaXNzaW9uVGlja2V0IjoiZXlKcFpDSTZJamcxTlRZelptWXhMVFk0TkdNdE5EWTFOaTFoWkdObExUZzBZV05qTWpoak4yWmpaQ0lzSW5WelpYSnVZVzFsSWpvaWNYZGxjblI1SWl3aWRYSnNJam9pYUhSMGNEcGNMMXd2YlhrdWRHVnNaV2R5WVhCb0xtTnZMblZyWEM5dFpXMWlaWEp6WEM5eGQyVnlkSGtpZlE9PSBiZDAyNDBhMTc3ODVlODI1YjMzYjg0ZjYxZGZjY2I2MTcyYWFjOGJlIDE0MTU1NzYzNDQiLCJleHBpcmVzX2F0IjoxNDE1NTc4MTQwfV0sImlhdCI6IjIwMTQtMTEtMDlUMjM6Mzk6MDQuOTU1WiIsImV4cCI6IjIwMTQtMTEtMTBUMDA6MDk6MDAuMzM4WiJ9."
refresh_token: "eyJhbGciOiJub25lIiwiY3R5IjoiYXBwbGljYXRpb25cL2pzb24ifQ.eyJ0b2tlbklkIjoiNmZiYzRlMmYtNWZhMC00YWMzLTliZjEtYmE3ODcxZGJkN2QxIiwiYWNjZXNzVG9rZW5JZCI6Ijg1NmJmMjBlLTQ1NjAtNGIzNi1iMDFjLTBlNjhiNDJlNjI3MiIsInByb2ZpbGVQdWJsaWNJZCI6Ijg1NTYzZmYxLTY4NGMtNDY1Ni1hZGNlLTg0YWNjMjhjN2ZjZCIsImNsaWVudElkIjoidGN1ayIsImlhdCI6IjIwMTQtMTEtMDlUMjM6Mzk6MDQuOTU1WiIsImV4cCI6IjIwMTQtMTItMDdUMjM6Mzk6MDQuOTU1WiJ9."}';

$all_users = array(
"activeall", "activenone", "activesome", "activeglobal", "activerandom",
"lapsedall", "lapsednone", "lapsedsome", "lapsedglobal", "lapsedrandom",
"flipall", "flipnone", "flipsome", "flipglobal", "fliprandom",
"yesbyproxy", "longjson", "credserror",
"zzz-200-200", "200-zzz-200", "200-200-zzz", "200-200-500", "200-500-500", "500-500-500", "500-200-200", "500-500-200",
"200-200-200", "200-200-000", "200-000-000", "000-000-000", "000-200-200", "000-000-200",

);

function patcf_is_status_coder($cred)
{
    $a = explode("-", $cred);
    if (count($a) == 3) return TRUE;
    return FALSE;
}

function patcf_return_status_code($cred, $pos)
{
    $requests = array("Sign In", "Verify Subscription", "Edition Credentials");
    $a = explode("-", $cred);
    $status = $a[$pos];
    if ($status == "zzz") {
        sleep(60);
        $status = "200";
    }
    if ($status == "200") return "activeall";

    if ($status == "000") {
        echo "I'm pretending to be a valid response with my 200 for " . esc_html( $requests[$pos] ) . ", but I'm rubbish";
        exit();
    }

    header("HTTP/1.1 $status Internal Server Error");
    echo "Ooops. I made a " . esc_html( $status ) . " for " . esc_html( $requests[$pos] ) . " . Is  that bad?";
    exit();
}

function patcf_get_issue_list($prefix, $start, $end)
{
    $issues = array();
    if (!is_numeric($start) || !is_numeric($end) || $end < $start) return $issues;
    for ($i = $start; $i<=$end; $i++) {
        $issues[] = $prefix . str_pad($i, 4, "0", STR_PAD_LEFT);
    }

    return $issues;
}

function patcf_get_some_issues($issues, $random = true)
{
    $my_issues = array();
    $keep = true;
    foreach ($issues as $issue) {
        if (!$random && $keep) $my_issues[] = $issue;
        if ($random && wp_rand(0,1)) $my_issues[] = $issue;
        $keep = !$keep;
    }

    return $my_issues;
}

function patcf_is_odd($n)
{
    return (boolean) ($n % 2);
}

function patcf_flip_is_active(&$seconds_left)
{
    $flip_seconds = 300;
    $date_array = getdate();

    $seconds_past_hour = $date_array['minutes'] * 60 + $date_array['seconds'];
    $pos = floor($seconds_past_hour / $flip_seconds);
    $seconds_left = $flip_seconds - ($seconds_past_hour % $flip_seconds);
    //echo $seconds_past_hour . " *** " . ($pos) . " ** " . patcf_is_odd($pos) . " **<br />";
    return patcf_is_odd($pos);
}

function patcf_is_active($all_users, $user)
{
    if (!in_array($user, $all_users)) return FALSE;
    if (startsWith($user, "active")) return TRUE;
    if (startsWith($user, "yes")) return TRUE;
    if (startsWith($user, "longjson")) return TRUE;
    if (startsWith($user, "credserror")) return TRUE;
    if (startsWith($user, "flip")) return  patcf_flip_is_active($seconds);
    if (startsWith($user, "200")) return TRUE;
    if (startsWith($user, "zzz")) return TRUE;
    return FALSE;
}

function patcf_get_test_users($all_users)
{
    $test_users = array();
    $test_users[] = array("state" => "UNKNOWN", "username" => "rubbish", "password" => "");
    $test_users[] = array("state" => "STALE", "username" => "expiredtoken", "password" => "");
    $test_users[] = array("state" => "STALE", "username" => "blockedtoken", "password" => "");
    $test_users[] = array("state" => "STALE", "username" => "alwaysstaletoken", "password" => "");

    foreach ($all_users as $user) {
        $active = patcf_is_active($all_users, $user);
        $test_users[] = array("state" => $active ? "ACTIVE" : "INACTIVE", "username" => $user, "password" => '');
    }

    $test_users[] = array("state" => "ACTIVE", "username" => "peter", "password" => "pan");
    return $test_users;
}

function patcf_is_valid_password ($params, $test_users){
    foreach ($test_users as $test_user){
        if((array_search($params['username'], $test_user) == 'username') && (array_search($params['password'], $test_user) == 'password')){
            return true;
        }
    }
    return false;
}

if (isset($_REQUEST["issue_prefix"])) $issue_prefix = $_REQUEST["issue_prefix"];
if (empty($issue_prefix)) $issue_prefix = "com.pugpig.edition";
if (isset($_REQUEST["issue_start"])) $issue_start = $_REQUEST["issue_start"];
if (empty($issue_start)) $issue_start = "1";
if (isset($_REQUEST["issue_end"])) $issue_end = $_REQUEST["issue_end"];
if (empty($issue_end)) $issue_end = "100";

$all_issues = patcf_get_issue_list($issue_prefix, $issue_start, $issue_end);
