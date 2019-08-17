<?php
session_start();

// authorized
if (isset($_SESSION["unraid_login"])) {
  http_response_code(200);
  exit;
}

$arrWhitelist = ['/webGui/styles/','/webGui/images/case-model.png'];
foreach ($arrWhitelist as $strWhitelist) {
  if (strpos($_SERVER['REQUEST_URI'], $strWhitelist) === 0) {
    http_response_code(200);
    exit;
  }
}

// non-authorized
//error_log(print_r($_SERVER, true));
http_response_code(401);
exit;
