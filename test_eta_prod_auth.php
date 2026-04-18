<?php
require __DIR__ . '/config.php';
$settings = app_eta_einvoice_settings($conn);
$res = app_eta_einvoice_request_access_token($conn, true);
var_export([
  'environment' => $settings['environment'],
  'base_url' => $settings['base_url'],
  'token_url' => $settings['token_url'],
  'result' => $res,
]);
