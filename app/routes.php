<?php

$app->get('/', \App\Controller\DirectoryController::class . ':index');
$app->group('/show/{virtualHost}', function () {
    $this->map(['GET'], '', \App\Controller\DirectoryController::class . ':show')
        ->setName('show');
    $this->get('/add', \App\Controller\DirectoryController::class . ':add')
        ->setName('add');
    $this->post('/create', \App\Controller\DirectoryController::class . ':create')
        ->setName('create');
    $this->get('/fetch/[{repository:.+}]', \App\Controller\DirectoryController::class . ':fetch')
        ->setName('fetch');
    $this->get('/pull/[{repository:.+}]', \App\Controller\DirectoryController::class . ':pull')
        ->setName('pull');
    $this->get('/reset/{repository:.+}', \App\Controller\DirectoryController::class . ':reset')
        ->setName('reset');
});
