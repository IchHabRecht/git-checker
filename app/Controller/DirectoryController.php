<?php
namespace App\Controller;

use GitChecker\GitWrapper\GitWrapper;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Slim\App;
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
        $root = rtrim($settings['root'], '/\\') . DIRECTORY_SEPARATOR;

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
        $root = rtrim($settings['root'], '/\\') . DIRECTORY_SEPARATOR;
        $virtualHost = trim($arguments['virtualHost'], '/\\') . DIRECTORY_SEPARATOR;

        $finder = $this->getRepositoryFinder($root, $virtualHost, $settings['virtual-hosts']);

        $gitWrapper = $this->getGitWrapper($settings['git-wrapper']);
        $repositories = [];
        /** @var SplFileInfo $directory */
        foreach ($finder as $directory) {
            $relativePath = trim($directory->getRelativePath(), '/\\');
            $gitRepository = $gitWrapper->getRepository(dirname($directory->getPathname()));
            $repositories[] = [
                'relativePath' => $relativePath,
                'status' => $gitRepository->getStatus(),
                'trackingInformation' => $gitRepository->getTrackingInformation(),
            ];
        }

        $this->view->render(
            $response,
            'show.twig',
            [
                'settings' => $settings,
                'root' => $root,
                'virtualHost' => $virtualHost,
                'repositories' => $repositories,
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
    public function fetch(Request $request, Response $response, array $arguments)
    {
        $settings = $request->getAttribute('settings');
        $root = rtrim($settings['root'], '/\\') . DIRECTORY_SEPARATOR;
        $virtualHost = trim($arguments['virtualHost'], '/\\') . DIRECTORY_SEPARATOR;

        $repository = null;
        if (isset($arguments['repository'])) {
            $repository = trim($arguments['repository'], '/\\') . DIRECTORY_SEPARATOR;
        }

        $finder = $this->getRepositoryFinder($root, $virtualHost, $settings['virtual-hosts'], $repository);

        $gitWrapper = $this->getGitWrapper($settings['git-wrapper']);
        /** @var SplFileInfo $directory */
        foreach ($finder as $directory) {
            $gitRepository = $gitWrapper->getRepository(dirname($directory->getPathname()));
            $gitRepository->fetch(['no-tags'], ['origin']);
        }

        return $this->redirectTo('show', $response, ['virtualHost' => $arguments['virtualHost']]);
    }

    /**
     * @param Request $request
     * @param Response $response
     * @param array $arguments
     * @return Response
     */
    public function pull(Request $request, Response $response, array $arguments)
    {
        $settings = $request->getAttribute('settings');
        $root = rtrim($settings['root'], '/\\') . DIRECTORY_SEPARATOR;
        $virtualHost = trim($arguments['virtualHost'], '/\\') . DIRECTORY_SEPARATOR;
        $repository = trim($arguments['repository'], '/\\') . DIRECTORY_SEPARATOR;

        $finder = $this->getRepositoryFinder($root, $virtualHost, $settings['virtual-hosts'], $repository);

        $gitWrapper = $this->getGitWrapper($settings['git-wrapper']);
        /** @var SplFileInfo $directory */
        foreach ($finder as $directory) {
            $gitRepository = $gitWrapper->getRepository(dirname($directory->getPathname()));
            $gitRepository->pull(['ff-only']);
        }

        return $this->redirectTo('show', $response, ['virtualHost' => $arguments['virtualHost']]);
    }

    /**
     * @return App
     */
    protected function getApplication()
    {
        return $GLOBALS['app'];
    }

    /**
     * @param array $settings
     * @return GitWrapper
     */
    protected function getGitWrapper(array $settings)
    {
        $gitWrapper = new GitWrapper();
        if (!empty($settings['git-binary'])) {
            $gitWrapper->setGitBinary($settings['git-binary']);
        }
        if (!empty($settings['env-vars'])) {
            $gitWrapper->setEnvVars($settings['env-vars']);
        }

        return $gitWrapper;
    }

    /**
     * @param string $root
     * @param string $virtualHost
     * @param array $settings
     * @param string|null $repository
     * @return Finder
     */
    protected function getRepositoryFinder($root, $virtualHost, array $settings, $repository = null)
    {
        $root = rtrim($root, '/\\');
        $virtualHost = trim($virtualHost, '/\\');
        $absolutePath = $root . DIRECTORY_SEPARATOR . $virtualHost;
        if (!@is_dir($absolutePath)) {
            throw new \InvalidArgumentException('Wrong path provided', 1456264866695);
        }

        if (!isset($settings['default']['show']['depth'])) {
            throw new \InvalidArgumentException('Missing default repository configuration', 1456425560002);
        }
        $depth = $settings['default']['show']['depth'];
        if (isset($settings[$virtualHost]['show']['depth'])) {
            $depth = $settings[$virtualHost]['show']['depth'];
        }

        $finder = new Finder();
        $finder->directories()
            ->ignoreUnreadableDirs(true)
            ->ignoreDotFiles(false)
            ->ignoreVCS(false)
            ->followLinks()
            ->name('.git')
            ->depth($depth)
            ->sort(function (SplFileInfo $a, SplFileInfo $b) {
                return strcmp($a->getRelativePath(), $b->getRelativePath());
            })
            ->in($absolutePath);

        if ($repository) {
            $repository = trim($repository, '/\\');
            $absolutePath .= DIRECTORY_SEPARATOR . $repository;
            if (!@is_dir($absolutePath)) {
                throw  new \InvalidArgumentException('Wrong repository path provided', 1456433944431);
            }

            $finder->depth(0)
                ->in($absolutePath);

            if (count($finder) !== 1) {
                throw new \RuntimeException('Unexpected repository count found', 1456433999553);
            }
        }

        return $finder;
    }

    /**
     * @param string $route
     * @param Response $response
     * @param array $arguments
     * @return Response
     */
    protected function redirectTo($route, Response $response, array $arguments = [])
    {
        return $response->withStatus(301)
            ->withHeader(
                'Location',
                $this->getApplication()
                    ->getContainer()
                    ->get('router')
                    ->PathFor(
                        $route,
                        $arguments
                    )
            );
    }
}
