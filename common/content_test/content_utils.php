<?php

if (!function_exists('get_absolute_path')) {
  function get_absolute_path($path)
  {
    $path = str_replace(array('/', '\\'), DIRECTORY_SEPARATOR, $path);
    $parts = array_filter(explode(DIRECTORY_SEPARATOR, $path), 'strlen');
    $absolutes = array();
    foreach ($parts as $part) {
      if ('.' == $part) {
        continue;
      } elseif ('..' == $part) {
        array_pop($absolutes);
      } else {
        $absolutes[] = $part;
      }
    }

    return implode(DIRECTORY_SEPARATOR, $absolutes);
  }
}

if (!function_exists('startsWith')) {
  function startsWith($haystack,$needle,$case=true)
  {
    if ($case) {
      return strpos($haystack, $needle, 0) === 0;
    }

    return stripos($haystack, $needle, 0) === 0;
  }
}

if (!function_exists('endsWith')) {
  function endsWith($haystack,$needle,$case=true)
  {
    $expectedPosition = strlen($haystack) - strlen($needle);
    if ($case) {
      return strrpos($haystack, $needle, 0) === $expectedPosition;
    }

    return strripos($haystack, $needle, 0) === $expectedPosition;
  }
}

if (!function_exists('_fill_buffer')) {
  function _fill_buffer($amount = 4096)
  {
    print '<!--';
    for ($n=0; $n<$amount; $n++) {
      print '.';
    }
    print '-->';
  }
}

if (!function_exists('_print_immediately')) {
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
}
