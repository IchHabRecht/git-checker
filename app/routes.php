<?php

$app->get('/', IchHabRecht\GitCheckerApp\Controller\DirectoryController::class . ':index');
$app->group('/show/{virtualHost}', function () {
    $this->map(['GET'], '', IchHabRecht\GitCheckerApp\Controller\DirectoryController::class . ':show')
        ->setName('show');
    $this->get('/add', IchHabRecht\GitCheckerApp\Controller\DirectoryController::class . ':add')
        ->setName('add');
    $this->post('/create', IchHabRecht\GitCheckerApp\Controller\DirectoryController::class . ':create')
        ->setName('create');
    $this->get('/branch/{repository:.+}', IchHabRecht\GitCheckerApp\Controller\DirectoryController::class . ':branch')
        ->setName('branch');
    $this->post('/checkout/{repository:.+}', IchHabRecht\GitCheckerApp\Controller\DirectoryController::class . ':checkout')
        ->setName('checkout');
    $this->get('/fetch/[{repository:.+}]', IchHabRecht\GitCheckerApp\Controller\DirectoryController::class . ':fetch')
        ->setName('fetch');
    $this->get('/pull/[{repository:.+}]', IchHabRecht\GitCheckerApp\Controller\DirectoryController::class . ':pull')
        ->setName('pull');
    $this->get('/reset/{repository:.+}', IchHabRecht\GitCheckerApp\Controller\DirectoryController::class . ':reset')
        ->setName('reset');
});
