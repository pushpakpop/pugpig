<?php
/**
 * @file
 * Test a 500 error
 */
?><?php
header("HTTP/1.1 403 Forbidden");
echo "Stay Away!";
exit();
