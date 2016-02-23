<?php

$app->get('/', \App\Controller\DirectoryController::class . ':index');

$app->get('/show/{path}', \App\Controller\DirectoryController::class . ':show')->setName('show');
