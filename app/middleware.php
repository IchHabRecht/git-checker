<?php

$app->add(function(\Psr\Http\Message\ServerRequestInterface $request, \Psr\Http\Message\ResponseInterface $response, callable $next) {
    if (!$next) {
        return $response;
    }

    $routeInfo = $request->getAttribute('routeInfo')[2];

    $processor = new \IchHabRecht\GitChecker\Config\Processor($request->getAttribute('settings'));
    $processor->combine('virtual-host', 'default', isset($routeInfo['virtualHost']) ? urldecode($routeInfo['virtualHost']) : '');

    $request = $request->withAttribute('settings', $processor->getConfiguration());

    return $next($request, $response);
});
$app->add(new IchHabRecht\Psr7MiddlewareYamlParser\YamlParser(__DIR__ . '/settings.yml', 'settings'));
