<?php

$app->add(new IchHabRecht\Psr7MiddlewareYamlParser\YamlParser(__DIR__ . '/settings.yml', 'settings'));

