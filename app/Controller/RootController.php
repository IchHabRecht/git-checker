<?php
namespace App\Controller;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;

class RootController
{
    /**
     * @param Request $request
     * @param Response $response
     * @param array $arguments
     * @return Response
     */
    public function dispatch(Request $request, Response $response, array $arguments)
    {
        return $response->getBody()->write('Hello World');
    }
}
