<?php
require_once __DIR__ . '/vendor/autoload.php';
require_once 'config.inc';

session_start();

$client = new Google_Client();
$client->setAuthConfigFile('client_secret.json');
$client->setRedirectUri('http://' . $_SERVER['HTTP_HOST'] . APP_PATH . 'oauth2callback.php');
$client->addScope(Google_Service_Gmail::GMAIL_READONLY);

if (!isset($_GET['code'])) {
    $auth_url = $client->createAuthUrl();
    header('Location: ' . filter_var($auth_url, FILTER_SANITIZE_URL));
} else {
    $client->authenticate($_GET['code']);
    $_SESSION['access_token'] = $client->getAccessToken();
    $redirect_uri = 'http://' . $_SERVER['HTTP_HOST'] . APP_PATH;
    header('Location: ' . filter_var($redirect_uri, FILTER_SANITIZE_URL));
}