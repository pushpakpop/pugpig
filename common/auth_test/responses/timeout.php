<?php
/**
 * @file
 * Test a 500 error
 */
?><?php

//header("HTTP/1.1 200 OK");
header('Content-Length: 100000');

echo "This will never finish ...\n\n";

for ($i = 0; $i < 1000; $i++) {
	echo "Getting somewhere.\n";
}

// Sleep for 60 seconds
sleep(60);
exit();
