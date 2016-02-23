<?php
namespace App\Controller;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Slim\Container;
use Slim\Views\Twig;
use Symfony\Component\Finder\Finder;

class RootController
{
    /**
     * @var Twig
     */
    protected $view;

    /**
     * @param Container $container
     */
    public function __construct(Container $container)
    {
        $this->view = $container->get('view');
    }

    /**
     * @param Request $request
     * @param Response $response
     * @param array $arguments
     * @return Response
     */
    public function dispatch(Request $request, Response $response, array $arguments)
    {
        $settings = $request->getAttribute('settings');
        $root = rtrim(strtr($settings['root'], '\\', '/'), '/') . '/';

        $finder = new Finder();
        $finder->directories()
            ->ignoreUnreadableDirs(true)
            ->depth(0)
            ->sortByName()
            ->in($root);

        $this->view->render(
            $response,
            'root.twig',
            [
                'settings' => $settings,
                'root' => $root,
                'directories' => $finder,
            ]
        );

        return $response;
    }
}
