<?php

$app->get('/', \App\Controller\DirectoryController::class . ':index');
$app->group('/show/{virtualHost}', function () {
    $this->map(['GET'], '', \App\Controller\DirectoryController::class . ':show')
        ->setName('show');
    $this->get('/fetch/[{repository:.+}]', \App\Controller\DirectoryController::class . ':fetch')
        ->setName('fetch');
});
