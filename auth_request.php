<?php
session_start();

// authorized
if (isset($_SESSION["unraid_login"])) {
  http_response_code(200);
  exit;
}

$arrWhitelist = [
  '/webGui/styles/clear-sans-bold-italic.eot',
  '/webGui/styles/clear-sans-bold-italic.woff',
  '/webGui/styles/clear-sans-bold-italic.ttf',
  '/webGui/styles/clear-sans-bold-italic.svg',
  '/webGui/styles/clear-sans-bold.eot',
  '/webGui/styles/clear-sans-bold.woff',
  '/webGui/styles/clear-sans-bold.ttf',
  '/webGui/styles/clear-sans-bold.svg',
  '/webGui/styles/clear-sans-italic.eot',
  '/webGui/styles/clear-sans-italic.woff',
  '/webGui/styles/clear-sans-italic.ttf',
  '/webGui/styles/clear-sans-italic.svg',
  '/webGui/styles/clear-sans.eot',
  '/webGui/styles/clear-sans.woff',
  '/webGui/styles/clear-sans.ttf',
  '/webGui/styles/clear-sans.svg',
  '/webGui/styles/default-cases.css',
  '/webGui/images/case-model.png'
];
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
