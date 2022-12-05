<?php
/**
 * @file
 * Pugpig Common Interface functions
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
Helper functions for streaming a response
************************************************************************/
function _fill_buffer($amount = 4096)
{
  print '<!--';
  for ($n=0; $n<$amount; $n++) {
    print '.';
  }
  print '-->';
}

function _print_immediately($msg)
{
  print esc_html($msg);
  _fill_buffer(512);
  try {
    @ob_end_flush();
    @ob_flush();
    flush();
    ob_start();
  } catch (Exception $e) {
    // ignore
  }
}

/************************************************************************
Output HTML headers and footers for stand-alone pages
************************************************************************/
 function pugpig_interface_output_header($title)
 {
 ?>

  <html>
  <head>
  <title><?php echo wp_kses_post($title) ?></title>
  </head>
  </body>

  <style>
  body { font-family: courier; font-size: small; background-color: #EEE }
  h1, h2, h3, b { color: #583C30; }
  em { font-size: smaller; }
  a { text-decoration:none; }
  p.fail { border: 2px solid red; padding: 2px; }
  span.fail, a.fail  { color: red; }
  span.pass, a.pass { color: green; }
  span.warning, a.warning { color:  #F87217; }
  span.bigwarning, a.bigwarning { color:  #F87217; background-color: pink; }
  span.slowwarning, a.slowwarning { color:  green; background-color: #cccccc; }
  span.veryslowwarning, a.veryslowwarning { color:  green; background-color: #999999; }

  span.pugpig_protected, a.pugpig_protected { border: 1px dashed black; margin: 1px; }
  span.pugpig_unpublished, a.pugpig_unpublished { text-transform:uppercase; }
  span.pugpig_bad_file, a.pugpig_bad_file { color:red; background-color: yellow;  }

  span.skip, a.skip { color: blue; }

  img.cover { padding: 2px; margin: 2px; }
  img.paid { border: 1px solid orange; }
  img.free { border: 1px solid green; }


ul#grid {
  list-style: none;
  margin: 20px auto 0;
  }

#grid li {
  float: left;
  margin: 0 5px 10px 5px;
  }

.portfolio {
  padding: 20px;
  margin-left: auto; margin-right: auto;  margin-top:50px;
  /*background-color: #ffd7ce;*/
  /*these two properties will be inherited by .portfolio h2 and .portfolio p */
  text-align: center;
  }

.portfolio h2 {
  clear: both;
  font-size: 35px;
  font-weight: normal;
  color: #58595b;
  }

.portfolio p {
  font-size: 15px;
  color: #58595b;
  /*text-shadow: 1px 1px 1px #aaa;
  */
  }

#grid li a:hover img {
  opacity:0.3;  filter:alpha(opacity=30);
  }

#grid li img {
  background-color: white;
  padding: 7px; margin: 0;
  border: 1px dotted #58595b;
}

#grid li a {
  display: block;
  }


  </style>

  <script language="JavaScript">
    function toggle_visibility(id)
    {
       var e = document.getElementById(id);
       if(e.style.display == 'block')
          e.style.display = 'none';
       else
          e.style.display = 'block';
    }
  </script>
  <?php

  }

 // Footer
 function pugpig_interface_output_footer()
 {
  ?>
    </body>
    </html>
  <?php
 }
