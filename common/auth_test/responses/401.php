<?php
/**
 * @file
 * Test a 500 error
 */
?><?php
header("HTTP/1.1 401 Unauthorized");
echo "Who are you?";
exit();
