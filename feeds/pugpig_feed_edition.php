<?php
/**
 * @file
 * Pugpig WordPress Edition Feeds
 */
?><?php
/*

Licence:
==============================================================================
(c) 2011, Kaldor Holdings Ltd
This module is released under the GNU General Public License.
See COPYRIGHT.txt and LICENSE.txt

 */?><?php

// Generate the ATOM feed for an edition. Send a 404 if the edition doesn't exist, or a 403 if not public

$edition_id = "";
if (isset($_GET["edition_id"]) && !empty($_GET["edition_id"])) {
  $edition_id = $_GET["edition_id"];
} else {
    header('HTTP/1.1 404 Not Found');
    exit;
}

$include_hidden = false;
if (isset($_GET["include_hidden"]) && !empty($_GET["include_hidden"])) {
  $include_hidden = true;
}

generate_edition_atom_feed($edition_id, $include_hidden);
