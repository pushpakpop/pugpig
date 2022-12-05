<?php
/**
 * @file
 * Pugpig WordPress Logging
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
Logging
*************************************************************************/
function pugpig_writelog()
{
  if (!@is_writable(PUGPIG_LOGPATH) ) {
    return;
  }

  $numargs = func_num_args();
  $arg_list = func_get_args();
  if ($numargs >2) $linenumber=func_get_arg(2); else $linenumber="";
  if ($numargs >1) $functionname=func_get_arg(1); else $functionname="";
  if ($numargs >=1) $string=func_get_arg(0);
  if (!isset($string) or $string=="") return;

  $logFile=PUGPIG_LOGPATH.'/ops-'.date("Y-m").".log";
  $timeStamp = date("d/M/Y:H:i:s O");

  $fileWrite = fopen($logFile, 'a');

  //flock($fileWrite, LOCK_SH);
   $logline="[$timeStamp] ".html_entity_decode($string)." $functionname $linenumber\r\n";  # for debug purposes

  fwrite($fileWrite, utf8_encode($logline));
  //flock($fileWrite, LOCK_UN);
  fclose($fileWrite);
}
