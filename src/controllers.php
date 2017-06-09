<?php

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/* Define constants for app */
define("API_KEY", "e9d1353322f57380fc0dc2e699144595");
define("API_SECRET", "feb47cdb251a0e45dc8ceee90986606a");
define("APP_URL", "https://3f62db27.ngrok.io");

//Request::setTrustedProxies(array('127.0.0.1'));
$app->get('/giftbasket/install', function () {
  
  $shop = $_REQUEST['shop'];
  $scopes = "read_orders,read_products,write_products";

  /* construct the installation URL and redirect the merchant */
  $installUrl = "http://$shop/admin/oauth/authorize?client_id=" . API_KEY . "&scope=$scopes&redirect_uri=" . APP_URL . "/giftbasket/auth";
  header("Location: " . $installUrl);
  exit();  
});

$app->get('/giftbasket/auth', function() use ($app) {
  
  /* Remove HMAC & signature parameters from the hash
   * sort keys in hash lexicographically
   * each key/value pair joined by &
   * hash resulting string with SHA256 using API_SECRET
   * compare result with HMAC parameter for successful match
  */
  foreach ($_REQUEST as $key => $value) {
    if ($key !== "hmac" && $key != "signature") {
      $hashArray[] = $key . "=" . $value;
    }
  }

  $hashString = implode($hashArray, "&");
  $hashedString = hash_hmac("sha256", $hashString, API_SECRET);
  
  /* compare resulting hashed string with hmac parameter */
  if ($_REQUEST['hmac'] !== $app['hashedString']) return 403;

  /**** Curl to retrieve access token******/
  $ch = curl_init();
  curl_setopt($ch, CURLOPT_URL, "https://" . $_REQUEST["shop"] . "/admin/oauth/access_token.json");
  curl_setopt($ch, CURLOPT_POST, 3);
  /* From a security standpoint the following CURLOPT_SSL_VERIFYPEER setting is BAD BAD BAD! We're allowing cURL to accept any server certificate. But, if you're currently using SecureTransport rather than OpenSSL, this is the only way this demo app will run. Check your version by using $ php -i | grep "SSL Version" in terminal */
  curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
  curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query(
      array(
        "client_id" => API_KEY,
        "client_secret" => API_SECRET,
        "code" => $_REQUEST["code"]
      )
  ));
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE); // Return the transfer as a string
 
  /* pass to the browser to return results of the request */
  $result = curl_exec($ch);
  
  /* closes cURL resource */
  curl_close($ch);

  // Get the returned access token data
  $tokenResponse = json_decode($result, true);
  
  // Store the token to the session; this is just an example for a dev environment app
  // in production, your token would be stored somewhere more permanent
  $app['session']->set("accessToken", $tokenResponse["access_token"]);

  createOrdersWebhook($tokenResponse["access_token"]);

 
  /* Redirect to bulk edit page */
  $bulkeditUrl = "https://www.shopify.com/admin/bulk?resource_name=ProductVariant&edit=metafields.test.ingredients:string";
  header("Location: " . $bulkeditUrl);
  exit(); 
});

$app->post('/giftbasket/webhook/order_create', function (Request $request) use($app) {
  
  /* compare hmac from header and verify webhook */
  $requesthmac = $request->headers->get('x-shopify-hmac-sha256');
  $data = file_get_contents('php://input');
  
  /* verify the request webhook */
  $webhookVerified = verify_webhook($data, $requesthmac, API_SECRET);

  if ($webhookVerified) { 
    $shop = $request->headers->get('HTTP_X_SHOPIFY_SHOP_DOMAIN');
    
    if (isset($app['session']->get('accessToken'))) {
  
      # parse the request body as JSON data
      $jsonData = json_decode($data);
      $lineItems = $jsonData['line_items']

      $ch = curl_init();
      curl_setopt($ch, CURLOPT_HTTPHEADER, array(
         "X-Shopify-Access-Token: $accessToken"
      ));
      curl_setopt($ch, CURLINFO_HEADER_OUT, true);
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); // Return the transfer as a string

      foreach ($lineItems as $lineItem) {

        $variantId = $lineItem['variant_id'];
        curl_setopt($ch, CURLOPT_URL, "https://" . $shop . "/admin/variants/" . $variantId . ".json");
        $variantResult = curl_exec($ch);
        $variant = json_decode($variantResult, true);
        
        foreach ($variant["metafields"] as $metafield) {
          if ($metafield["key"] == "ingredients") {
            $giftItems = explode(",", $metafield["value"]); 
            
            foreach ($giftItems as $item) {
              // Update decrement the inventory quantity of each item 
              $chVar = curl_init();
              curl_setopt($chVar, CURLOPT_URL, "https://" . $shop . "/admin/variants/" . $item . ".json");
              curl_setopt($chVar, CURLOPT_CUSTOMREQUEST, "PUT"); 
              curl_setopt($chVar, CURLOPT_POSTFIELDS, http_build_query(
                 array("variant" => array(
                    "id" => $item,
                    "inventory_quantity_adjustment" => -1 
                  )
                )
              ));
              // closes cURL resource
              curl_close($chVar);
           }
         }
       }
     }
    } 
  }
  else {
    return new Response("You do not have permission to access this.", 403); 
  }

  return new Response("Success!", 200);
});

$app->get('/', function () {
   return true; 
});
