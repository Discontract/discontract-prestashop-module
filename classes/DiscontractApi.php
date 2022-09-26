<?php

if (!defined('_PS_VERSION_')) exit;

class DiscontractApi
{
  public static $instance;
  private $apiKey;

  public static function getInstance()
  {
    if (!isset(self::$instance)) {
      self::$instance = new DiscontractApi(Configuration::get('DISCONTRACT_API_KEY'));
    }
    return self::$instance;
  }

  function __construct($apiKey)
  {
    if (!$apiKey) {
      throw new ErrorException('Api key is not defined');
    }
    $this->apiKey = $apiKey;
  }

  private function performRequest($path, $method = 'GET', $data = array(), $anonymous = false)
  {
    // if ($path === '/carts/') {
    //   die(json_encode($data));
    // }
    $curl = curl_init();
    curl_setopt_array($curl, array(
      // CURLOPT_URL => 'https://b2b-stage.discontract.com/api/v1'.$path,
      CURLOPT_URL => Configuration::get('DISCONTRACT_API_URL') . $path,
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_ENCODING => '',
      CURLOPT_MAXREDIRS => 10,
      CURLOPT_TIMEOUT => 0,
      CURLOPT_FOLLOWLOCATION => true,
      CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
      CURLOPT_CUSTOMREQUEST => $method,
      CURLOPT_POSTFIELDS => json_encode($data),
      CURLOPT_HTTPHEADER => $anonymous ? array("Content-Type: application/json") : array(
        "Authorization: Bearer " . $this->apiKey,
        "Content-Type: application/json"
      )
    ));

    $response = curl_exec($curl);
    curl_close($curl);
    return json_decode($response);
  }

  public function getLocations($postCode)
  {
    return $this->performRequest('/locations?address=' . urlencode(utf8_encode($postCode)));
  }

  public function getPriceQuote($jobId, $body)
  {
    return $this->performRequest('/jobs/' . $jobId . '/price', 'POST', $body);
  }

  public function getJob($jobId)
  {
    return $this->performRequest('/jobs/' . $jobId . '?language=lt');
  }

  public function getCart($cartId)
  {
    return $this->performRequest('/carts/' . $cartId);
  }

  public function getJobs()
  {
    return $this->performRequest('/jobs?language=lt');
  }

  public function createCart($jobItems, $cartId = false)
  {
    $body = new stdClass;
    $body->items = $jobItems;
    if ($cartId) {
      $body->externalCartId = $cartId . "";
    }
    // die(json_encode($body));
    return $this->performRequest('/carts/', 'POST', $body);
  }

  public function purchaseCart($cartId, $body)
  {
    // die(json_encode($body));
    return $this->performRequest('/carts/' . $cartId . '/purchase', 'POST', $body);
  }

  public function deliverCart($cartId, $time)
  {
    // die(json_encode($body));
    $body = new stdClass();
    $body->deliveredAt = $time;
    return $this->performRequest('/carts/' . $cartId . '/deliver', 'POST', $body);
  }

  public function validatePhoneNumber($phoneNumber)
  {
    // die(json_encode($body));
    $body = new stdClass;
    $body->phoneNumber = $phoneNumber;
    return $this->performRequest('/phoneNumbers/validate', 'POST', $body);
  }
}
