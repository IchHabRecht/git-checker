<?php

$app->add(new IchHabRecht\GitChecker\Middleware\Configuration\PreProcessor('settings'));
$app->add(new IchHabRecht\Psr7MiddlewareYamlParser\YamlParser(__DIR__ . '/settings.yml', 'settings'));
