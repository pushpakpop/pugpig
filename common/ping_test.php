<?php

function ping($host,$port=80,$timeout=6)
{
        $fsock = @fsockopen($host, $port, $errno, $errstr, $timeout);
        if (! $fsock) {
                return FALSE;
        } else {
                return TRUE;
        }
}

$host = $_SERVER['HTTP_HOST'];

echo "<h1>Testing: ". esc_html($host)."</h1>";
echo "<p>Use this page to confirm that the server can resolve it's own name</p>";

$up = ping($host);

if ($up) echo "<font color='green'>Success.</font>";
else echo "<font color='red'>Failed. Maybe you need a local host entry?</font>";

/*
// Comment this back in for troubleshooting
if (isset($_REQUEST["show_info"])) {
	phpinfo();
}
*/
