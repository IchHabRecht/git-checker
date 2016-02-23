<?php

require_once __DIR__ . '/../vendor/autoload.php';

// Instantiate the app
$app = new \Slim\App();

// Register routes
require __DIR__ . '/../app/routes.php';

// Run!
$app->run();
