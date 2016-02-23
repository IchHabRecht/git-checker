<?php

$app->add(new \GitChecker\Middleware\YamlParser(__DIR__ . '/settings.yml', 'settings'));

