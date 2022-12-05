<?php
/**
 * @file
 * Multicurl
 */
?><?php
/*

Licence:
==============================================================================
(c) 2011, Kaldor Holdings Ltd
This module is released under the GNU General Public License.
See COPYRIGHT.txt and LICENSE.txt

 */?><?php

class multicurl
{
    private $allToDo;
    private $entries;
    private $filehandles;
    private $headerhandles;
    private $multiHandle;

    private $successes = array();
    private $failures = array();

    private $warningFileSize = 0;
    private $maxConcurrent = 2;
    private $currentIndex  = 0;
    private $processedCount = 0;

    private $info          = array();
    private $options;

    public function __construct($entries, $concurrent, $warning_file_size)
    {

        $this->options = array(
          CURLOPT_RETURNTRANSFER => true,
           CURLOPT_USERAGENT => "PugpigNetwork/Packager",

           CURLOPT_MAXREDIRS      => 3,
           CURLOPT_TIMEOUT        => PUGPIG_CURL_TIMEOUT,
           CURLOPT_ENCODING       => "", // Accept any gzip, deflate, etc that is supported
           CURLOPT_HTTPHEADER     => array('Pragma: akamai-x-cache-on, akamai-x-check-cacheable, akamai-x-get-cache-key, akamai-x-get-true-cache-key, akamai-x-get-extracted-values')

        );

        // We can't follow locations if certain settings are set
        if ((!ini_get('open_basedir') && !ini_get('safe_mode'))) {
          $this->options[CURLOPT_FOLLOWLOCATION] = true;
        }

        $this->entries = $entries;

        $this->allToDo = array_keys($this->entries);
        $this->filehandles = array();
        $this->headerhandles = array();

        if (!is_numeric($concurrent) || $concurrent < 1) $concurrent = 1;
        $this->maxConcurrent = $concurrent;
        $this->warningFileSize = $warning_file_size;
        $this->multiHandle = curl_multi_init();
    }

    public function getFailures()
    {
      return $this->failures;
    }

    public function getSuccesses()
    {
      return $this->successes;
    }

    public function process()
    {
        $running = 0;
        do {
            $this->_addHandles(min(array($this->maxConcurrent - $running, $this->_moreToDo())));
            while ($exec = curl_multi_exec($this->multiHandle, $running) === -1) {
            }

            // _print_immediately('Info Count: ' . count($this->info) . '<br />');
            curl_multi_select($this->multiHandle);
            while ($multiInfo = curl_multi_info_read($this->multiHandle, $msgs)) {
                $this->_processData($multiInfo);

                // Clean up the handle
                curl_multi_remove_handle($this->multiHandle, $multiInfo['handle']);
                curl_close($multiInfo['handle']);

            }

        } while ($running || $this->_moreTodo());

        return $this;
    }

    private function _addHandles($num)
    {
        while ($num > 0) {
            // Get the URL and file name, and create the directory if it doesn't exist
            $url = $this->allToDo[$this->currentIndex];
            $name = $this->entries[$url];
            $dir = dirname($name);
            if (!file_exists($dir)) mkdir($dir, 0777, true);

            // If the file exists, we don't need to add it
            $header_file_name = $name . ".pugpigheaders";
            $curlopt_file_name = $name . ".pugpigcurlopt";
            if (file_exists($name) && file_exists($header_file_name) && file_exists($curlopt_file_name)) {

              $headers = file_get_contents($header_file_name);

              $headers_array = _getHeadersFromString($headers);
              $content_type = "";
              if (isset($headers_array['content-type'])) $content_type = $headers_array['content-type'];
              $char = pugpig_get_download_char($name, $content_type);

              $cinfofile = file_get_contents($curlopt_file_name);
              $cinfo = maybe_unserialize($cinfofile);

              $this->successes[$url]['file'] = $name;
              $this->successes[$url]['headers'] = $headers;
              $this->successes[$url]['curl_info'] = $cinfo;
              $this->successes[$url]['fetched'] = FALSE;

              _print_immediately('<a class="skip" href="'.$url .'" target="_blank" title="Skipped: '.$url . ' ">'. $char.'</a>');
              $this->processedCount++;
              if ($this->processedCount%100 == 0 || $this->processedCount == count($this->entries)) _print_immediately("<br />");
            } else {

              $this->headerhandles[$url]=@fopen ($header_file_name, "w");

              if ($this->headerhandles[$url] === FALSE) {
                // Are these the issue: < > : " / \ | ? * ?
                _print_immediately('<a class="fail" href="'.$url .'" target="_blank" title="Failed: '.$url . ' ">?</a>');
                $this->failures[$url] = "Unable to save file \"$name\" after download. Maybe file name is too long (" . strlen($header_file_name) . " chars) or contains special characters?";

              } else {
                $this->filehandles[$url]=@fopen ($name, "w");

                $handle = curl_init($url);
                curl_setopt_array($handle, $this->options);

                // print_r("Opened handle for ".$url.": " . $this->filehandles[$url] . "<br />");

                curl_setopt ($handle, CURLOPT_FILE, $this->filehandles[$url]);
                curl_setopt ($handle, CURLOPT_WRITEHEADER, $this->headerhandles[$url]);
                curl_multi_add_handle($this->multiHandle, $handle);
                $this->info[(string) $handle]['url'] = $this->allToDo[$this->currentIndex];
              }
              $num--;
            }
            $this->currentIndex++;
            //_print_immediately("c:" . $this->currentIndex . "(".$num.")<br />");
            if ($this->currentIndex >= count($this->allToDo)) break;
        }

    }

    private function _moreToDo()
    {
        return count($this->allToDo) - $this->currentIndex;
    }



    private function _processData($multiInfo)
    {
        $handleString = (string) $multiInfo['handle'];
        $this->info[$handleString]['multi'] = $multiInfo;
        $this->info[$handleString]['curl']  = curl_getinfo($multiInfo['handle']);

        $http_url = $this->info[$handleString]['url'];
        $http_code = $this->info[$handleString]['curl']['http_code'];
        $content_type = $this->info[$handleString]['curl']['content_type'];
        $content_length = $this->info[$handleString]['curl']['download_content_length'];
        $total_time = $this->info[$handleString]['curl']['total_time'];
        $starttransfer_time = $this->info[$handleString]['curl']['starttransfer_time'];
        $connect_time = $this->info[$handleString]['curl']['connect_time'];

        $cinfo = $this->info[$handleString]['curl'];

        // $request_header = $this->info[$multiInfo['handle']]['curl']['request_header'];
        // print_r($this->info[$multiInfo['handle']]['curl']);

        // Close the file we're downloading into
        fclose ($this->filehandles[$http_url]);
        fclose ($this->headerhandles[$http_url]);

        $name = $this->entries[$http_url];
        $file_exists = file_exists($name);
        $file_size = $file_exists ? filesize($name) : 0;


        if ($http_code != 200) {
          $this->failures[$http_url] = "HTTP Error after " . $total_time . " seconds: " . $http_code;
          if ($http_code == 0) $this->failures[$http_url] .= " (possibly too many concurrent connections).";
          unlink($name);
        } else {
          if (!$file_exists) {
            $this->failures[$http_url] = "Unable to save file after download. Maybe file name is too long?";
          } elseif ($file_size == 0) {
            // Delete it so that we have to download it again if the user refreshes
            unlink($name);
            $this->failures[$http_url] = "The file is $file_size bytes in length.";
          }

        }


        $char = pugpig_get_download_char($name, $content_type);

        $headerfile = $name . ".pugpigheaders";
        $curlopt_file_name =  $name . ".pugpigcurlopt";

        file_put_contents($curlopt_file_name, serialize($cinfo));

        $headers = file_get_contents($headerfile);
        $headers_array = _getHeadersFromString($headers);

        _print_immediately('<a class="pass" href="'.$http_url .'" target="_blank" title="'.$http_url .
         "\n" . '[Response: '.$http_code.', Size: '.$content_length.' ('. pugpig_bytestosize($file_size).'), Type: ' . $content_type .
        ', Time: '. $total_time .', TTFB: '.$starttransfer_time.', Connect Time: ' . $connect_time . ']'
        . "\n" . htmlspecialchars($headers)
        . ' ">'.$char.'</a>');

        $this->successes[$http_url]['file'] = $name;
        $this->successes[$http_url]['headers'] = $headers;
        $this->successes[$http_url]['curl_info'] = $cinfo;
        $this->successes[$http_url]['fetched'] = TRUE;


        $this->processedCount++;
        if ($this->processedCount%100 == 0 || $this->processedCount == count($this->entries)) _print_immediately("<br />");

    }
  }

