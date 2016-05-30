<?php

return [
    'settings' => [
        'determineRouteBeforeAppMiddleware' => true,
        'view' => [
            'template_path' => __DIR__ . '/../resources/templates',
            'twig' => [
                'cache' => __DIR__ . '/../var/cache/twig',
            ],
        ],
    ],
];