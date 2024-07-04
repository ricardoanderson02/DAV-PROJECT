<?php
// config.php
require 'vendor/autoload.php';

define('BASE_URL', 'http://localhost/');

$uri = 'mongodb://localhost:27017/';

// Create a new client and connect to the server
$client = new MongoDB\Client($uri);

// You can add additional configuration or utility functions here if needed
