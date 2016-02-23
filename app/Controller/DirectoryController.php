<?php
namespace App\Controller;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Slim\Container;
use Slim\Views\Twig;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

class DirectoryController
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
    public function index(Request $request, Response $response, array $arguments)
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
            'index.twig',
            [
                'settings' => $settings,
                'root' => $root,
                'directories' => $finder,
            ]
        );

        return $response;
    }

    /**
     * @param Request $request
     * @param Response $response
     * @param array $arguments
     * @return Response
     */
    public function show(Request $request, Response $response, array $arguments)
    {
        $settings = $request->getAttribute('settings');
        $root = rtrim(strtr($settings['root'], '\\', '/'), '/') . '/';
        $path = $root . trim($arguments['path'], '/') . '/';

        if (!@is_dir($path)) {
            throw new \InvalidArgumentException('Wrong path provided', 1456264866695);
        }

        $finder = new Finder();
        $finder->directories()
            ->ignoreUnreadableDirs(true)
            ->ignoreDotFiles(false)
            ->ignoreVCS(false)
            ->followLinks()
            ->name('.git')
            ->depth('< 4')
            ->sort(function (SplFileInfo $a, SplFileInfo $b) {
                return strcmp($a->getRelativePath(), $b->getRelativePath());
            })
            ->in($path);

        $this->view->render(
            $response,
            'show.twig',
            [
                'settings' => $settings,
                'path' => $path,
                'repositories' => $finder,
            ]
        );

        return $response;
    }
}
