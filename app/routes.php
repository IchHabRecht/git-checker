<?php

$app->get('/', function ($request, $response) {
	return $response->getBody()->write('Hello World');
});
