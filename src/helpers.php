<?php

/** 
 * Create a webhook for orders/create 
**/
function createOrdersWebhook($accessToken) {
  /* /admin/webhooks/count.json?topic=orders/create */
  /* Count # of webhooks for orders/create
   * if none exists, create 
  */
  $ch = curl_init();
  curl_setopt($ch, CURLOPT_HTTPHEADER, array(
    "X-Shopify-Access-Token: $accessToken"
  ));  
  curl_setopt($ch, CURLOPT_URL, "https://" . $_REQUEST["shop"] . "/admin/webhooks/count.json?topic=orders/create");
  curl_setopt($ch, CURLINFO_HEADER_OUT, true); 
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); // Return the transfer as a string
  
  /* pass to the browser to return results of the request */
  $result = curl_exec($ch);
  $webhookCount = json_decode($result, true);
  /* "webhook": {
    "topic": "orders\/create",
    "address": "http:\/\/whatever.hostname.com\/",
    "format": "json"
  } */
  if ($webhookCount["count"] === 0) {
    /* create webhook since none exists yet */
    $webhook = array(
        "webhook" => array(
          "topic"   => "orders/create",
          "address" => APP_URL . "/giftbasket/webhook/order_create", 
          "format"  => "json"
        )
    );
    
    curl_setopt($ch, CURLOPT_URL, "https://" . $_REQUEST["shop"] . "/admin/webhooks.json");
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($webhook));
    $result = curl_exec($ch);
    curl_close($ch); 
  }
  else {
    /* Don't need to create anything; close resource */
    curl_close($ch);
  }
}

/**
 * Creates a curl request with the specified request path, type
 * and returns results as JSON
 * @param String requestType
 * @param String requestPath
 * @param String accessToken
 * @param Array postFields; default = false
**/
function createCurlRequest($requestType, $requestPath, $accessToken, $postFields = false) {
 
  $ch = curl_init();
  curl_setopt($ch, CURLOPT_URL, $requestPath);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE); // Return the transfer as a string
  
  switch ($requestType) {
    case "GET":

    break;

    case "POST":
      curl_setopt($ch, CURLOPT_POST, 1);
      curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query(
          array(
            "client_id" => API_KEY,
            "client_secret" => API_SECRET,
            "code" => $_REQUEST["code"]
          )
      ));
    break;

    case "PUT":

    break;

    default:
  }

  /* pass to the browser to return results of the request */
  $result = curl_exec($ch);
 
  /* closes cURL resource */
  curl_close($ch);
 
}

/**
 * Verify the webhook received 
 * @param String hash
 * @param Array data
**/
function verify_webhook($data, $hmac_header, $api_secret) {
  
  /* Calculate the passed hmac using data passed through header */
  $calculated_hmac = base64_encode(hash_hmac('sha256', $data, $api_secret, true));
  return ($hmac_header == $calculated_hmac); 
} 
