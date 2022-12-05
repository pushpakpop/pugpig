<?php

include_once 'pugpig_http.php';

// Code supplied by Amazon
class cloudfront
{
  public $serviceUrl;
  public $accessKeyId;
  public $responseBody;
  public $responseCode;
  public $distributionId;

  /**
   * Constructs a CloudFront object and assigns required account values
   * @param $accessKeyId    {String} AWS access key id
   * @param $secretKey    {String} AWS secret key
   * @param $distributionId  {String} CloudFront distribution id
   * @param $serviceUrl     {String} Optional parameter for overriding cloudfront api URL
   */
  public function __construct($accessKeyId, $secretKey, $distributionId, $serviceUrl="https://cloudfront.amazonaws.com/")
  {
    $this->accessKeyId    = $accessKeyId;
    $this->secretKey      = $secretKey;
    $this->distributionId = $distributionId;
    $this->serviceUrl     = $serviceUrl;
  }

  /**
   * Invalidates object with passed key on CloudFront
   * @param $key   {String|Array} Key of object to be invalidated, or set of such keys
   */
  public function invalidate($keys)
  {
    $this->responseBody = '';
    if (!is_array($keys)) {
      $keys = array($keys);
    }
    $date       = gmdate("D, d M Y G:i:s T");
    $requestUrl = $this->serviceUrl."2010-08-01/distribution/" . $this->distributionId . "/invalidation";

    // assemble request body
    $body  = "<InvalidationBatch>";
    foreach ($keys as $key) {
      $key   = (preg_match("/^\//", $key)) ? $key : "/" . $key;
      $body .= "<Path>".$key."</Path>";
    }
    $body .= "<CallerReference>".time()."</CallerReference>";
    $body .= "</InvalidationBatch>";

    // make and send request
    $headers = array();
    $headers[] = "Date: " . $date;
    $headers[] = "Authorization: " . $this->makeKey($date);
    $headers[] = "Content-Type: " . "text/xml";

    $this->responseBody = curl_post($requestUrl, $headers, $body);

    return ($this->responseCode === 201);
  }

  /**
   * Returns header string containing encoded authentication key
   * @param   $date     {Date}
   * @return   {String}
   */
  public function makeKey($date)
  {
    return "AWS " . $this->accessKeyId . ":" . base64_encode($this->hmacSha1($this->secretKey, $date));
  }

  /**
   * Returns HMAC string
   * @param   $key     {String}
   * @param   $date    {Date}
   * @return   {String}
   */
  public function hmacSha1($key, $date)
  {
    $blocksize = 64;
    $hashfunc  = 'sha1';
    if (strlen($key)>$blocksize) {
      $key = pack('H*', $hashfunc($key));
    }
    $key  = str_pad($key,$blocksize,chr(0x00));
    $ipad = str_repeat(chr(0x36),$blocksize);
    $opad = str_repeat(chr(0x5c),$blocksize);
    $hmac = pack('H*', $hashfunc( ($key^$opad).pack('H*',$hashfunc(($key^$ipad).$date)) ));

    return $hmac;
  }
}
