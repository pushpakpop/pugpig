<?php
/**
 * @file
 * Test a 500 error
 */
?><?php
header("HTTP/1.1 500 Internal Server Error");
echo "Ooops. That's bad";
exit();
