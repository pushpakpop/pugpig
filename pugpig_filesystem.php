<?php
/**
 * @file
 * Pugpig WordPress Filesystem Tools
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
Create a directory at a specific path
*************************************************************************/
function pugpig_create_writable_directory($path)
{
  if (!@is_dir ($path)) {
    if (!@mkdir ($path, 0777)) {
      return false;
    } else {
    }
  }

  # check if folder is writeable
  if (!@is_writable($path) ) {

    # trying to set permissions
    if (!@chmod($path, 0777)) {
      return false;
    } else {
    }
  } else {
  }

  return true;
}

/************************************************************************
Delete a directory
*************************************************************************/
function pugpig_delete_directory($f)
{
  # delete log folder and logs
  if (@is_dir($f)) {
    pugpig_deltree($f);
  }
}

function pugpig_deltree($f)
{
  if (@is_dir($f)) {
    foreach (glob($f.'/*') as $sf) {
      if (@is_dir($sf) && !is_link($sf)) {
        @pugpig_deltree($sf);
      } else {
        @unlink($sf);
      }
    }
  }
  if (@is_dir($f)) rmdir($f);
  return true;
}

/************************************************************************
Write a file to disk
*************************************************************************/
function pugpig_create_file($path, $file, $contents)
{
  $f = $path . $file;
  $fileWrite = fopen($f, 'w');
  fwrite($fileWrite, utf8_encode($contents));
  fclose($fileWrite);
}
