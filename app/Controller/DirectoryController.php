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

        if (!empty($settings['virtual-hosts']['default']['index']['exclude'])
            && is_array($settings['virtual-hosts']['default']['index']['exclude'])
        ) {
            foreach ($settings['virtual-hosts']['default']['index']['exclude'] as $dir) {
                $finder->notPath(strtr($dir, '\\', '/'));
            }
        }

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
                'commit' => $gitRepository->log(['1', 'oneline']),
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
        $repository = isset($arguments['repository'])
            ? trim($arguments['repository'], '/\\') . DIRECTORY_SEPARATOR
            : null;

        $finder = $this->getRepositoryFinder($root, $virtualHost, $settings['virtual-hosts'], $repository);

        $gitWrapper = $this->getGitWrapper($settings['git-wrapper']);
        /** @var SplFileInfo $directory */
        foreach ($finder as $directory) {
            $gitRepository = $gitWrapper->getRepository(dirname($directory->getPathname()));
            $trackingInformation = $gitRepository->getTrackingInformation();
            if (!empty($trackingInformation['behind']) && !$gitRepository->hasChanges()) {
                $gitRepository->pull(['ff-only']);
                $this->setUmask($directory->getPathname(), $settings['virtual-hosts'], $virtualHost);
            }
        }

        return $this->redirectTo('show', $response, ['virtualHost' => $arguments['virtualHost']]);
    }

    /**
     * @param Request $request
     * @param Response $response
     * @param array $arguments
     * @return Response
     */
    public function reset(Request $request, Response $response, array $arguments)
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
            $trackingInformation = $gitRepository->getTrackingInformation();
            $branch = !empty($trackingInformation['remoteBranch'])
                ? $trackingInformation['remoteBranch']
                : empty($trackingInformation['branch'])
                    ? $trackingInformation['branch']
                    : '';
            if (!empty($branch)) {
                $gitRepository->reset(['hard'], ['origin/' . $branch]);
                $this->setUmask($directory->getPathname(), $settings['virtual-hosts'], $virtualHost);
            }
        }

        return $this->redirectTo('show', $response, ['virtualHost' => $arguments['virtualHost']]);
    }

    /**
     * @param Request $request
     * @param Response $response
     * @param array $arguments
     * @return Response
     */
    public function add(Request $request, Response $response, array $arguments)
    {
        $settings = $request->getAttribute('settings');
        $root = rtrim($settings['root'], '/\\') . DIRECTORY_SEPARATOR;
        $virtualHost = trim($arguments['virtualHost'], '/\\') . DIRECTORY_SEPARATOR;

        $absolutePath = $root . $virtualHost;
        if (!@is_dir($absolutePath)) {
            throw new \InvalidArgumentException('Wrong path provided', 1461609455);
        }

        $folders = isset($settings['virtual-hosts'][$virtualHost]['add']['allow'])
            ? $settings['virtual-hosts'][$virtualHost]['add']['allow']
            : isset($settings['virtual-hosts']['default']['add']['allow'])
                ? $settings['virtual-hosts']['default']['add']['allow']
                : [];
        if (!empty($folders)) {
            array_walk($folders, function (&$value) {
                $value = [
                    'relativePathname' => trim($value, '\\/'),
                ];
            });
        } else {
            $depth = isset($settings['virtual-hosts'][$virtualHost]['show']['depth'])
                ? $settings['virtual-hosts'][$virtualHost]['show']['depth']
                : $settings['virtual-hosts']['default']['show']['depth'];

            $folders = new Finder();
            $folders->directories()
                ->ignoreUnreadableDirs(true)
                ->ignoreDotFiles(true)
                ->ignoreVCS(true)
                ->followLinks()
                ->depth($depth)
                ->sort(function (SplFileInfo $a, SplFileInfo $b) {
                    return strcmp($a->getRelativePathname(), $b->getRelativePathname());
                })
                ->in($absolutePath);

            $excludeShowDirs = isset($settings['virtual-hosts'][$virtualHost]['show']['exclude'])
                ? $settings['virtual-hosts'][$virtualHost]['show']['exclude']
                : isset($settings['virtual-hosts']['default']['show']['exclude'])
                    ? $settings['virtual-hosts']['default']['show']['exclude']
                    : [];
            $excludeAddDirs = isset($settings['virtual-hosts'][$virtualHost]['add']['exclude'])
                ? $settings['virtual-hosts'][$virtualHost]['add']['exclude']
                : isset($settings['virtual-hosts']['default']['add']['exclude'])
                    ? $settings['virtual-hosts']['default']['add']['exclude']
                    : [];
            foreach (array_merge($excludeShowDirs, $excludeAddDirs) as $dir) {
                $folders->notPath(strtr($dir, '\\', '/'));
            }
        }

        $this->view->render(
            $response,
            'add.twig',
            [
                'settings' => $settings,
                'root' => $root,
                'virtualHost' => $virtualHost,
                'folders' => $folders,
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
    public function create(Request $request, Response $response, array $arguments)
    {
        $requestArguments = $request->getParsedBody();
        if (!isset($requestArguments['clone-url'])) {
            throw new \InvalidArgumentException('clone url is missing', 1461615135);
        }
        if (!isset($requestArguments['parent-directory'])) {
            throw new \InvalidArgumentException('Parent folder is missing', 1461615212);
        }

        $settings = $request->getAttribute('settings');
        $root = rtrim($settings['root'], '/\\') . DIRECTORY_SEPARATOR;
        $virtualHost = trim($arguments['virtualHost'], '/\\') . DIRECTORY_SEPARATOR;
        $parentDirectory = trim($requestArguments['parent-directory'], '/\\') . DIRECTORY_SEPARATOR;

        $targetDirectory = $root . $virtualHost . $parentDirectory;
        if (!is_dir($targetDirectory)) {
            throw new \InvalidArgumentException('Parent directory "' . $targetDirectory . '" does net exist', 1461615365);
        }

        $cloneUrl = $requestArguments['clone-url'];
        $pathinfo = pathinfo($cloneUrl);
        $cloneDirectory = trim($pathinfo['filename'], '/\\') . DIRECTORY_SEPARATOR;

        if (is_dir($targetDirectory . $cloneDirectory)) {
            throw new \InvalidArgumentException('Target directory "' . $targetDirectory . $cloneDirectory . '" does exist', 1461616033);
        }

        $gitWrapper = $this->getGitWrapper($settings['git-wrapper']);
        $gitRepository = $gitWrapper->cloneRepository($cloneUrl, $targetDirectory . $cloneDirectory);
        $this->setUmask($targetDirectory . $cloneDirectory, $settings['virtual-hosts'], $virtualHost);

        if (!empty($requestArguments['branch-name'])) {
            $branchName = $requestArguments['branch-name'];
            $gitRepository->checkout(['track', 'b'], [$branchName, 'origin/' . $branchName]);
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
                return strcmp($a->getRelativePathname(), $b->getRelativePathname());
            })
            ->in($absolutePath);

        $excludeDirs = null;
        if (isset($settings['default']['show']['exclude'])) {
            $excludeDirs = $settings['default']['show']['exclude'];
        }
        if (isset($settings[$virtualHost]['show']['exclude'])) {
            $excludeDirs = $settings[$virtualHost]['show']['exclude'];
        }
        if (!empty($excludeDirs) && is_array($excludeDirs)) {
            foreach ($excludeDirs as $dir) {
                $finder->notPath(strtr($dir, '\\', '/'));
            }
        }

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
        return $response->withStatus(303)
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

    /**
     * @param string $directoryPath
     * @param array $settings
     * @param string $virtualHost
     */
    protected function setUmask($directoryPath, array $settings, $virtualHost)
    {
        $fileUmask = !empty($settings[$virtualHost]['umask']['file'])
            ? $settings[$virtualHost]['umask']['file']
            : !empty($settings['default']['umask']['file'])
                ? $settings['default']['umask']['file']
                : 0;
        $folderUmask = !empty($settings[$virtualHost]['umask']['folder'])
            ? $settings[$virtualHost]['umask']['folder']
            : (!empty($settings['default']['umask']['folder'])
                ? $settings['default']['umask']['folder']
                : 0);
        if (empty($fileUmask) && empty($folderUmask)) {
            return;
        }

        $repositoryFinder = new Finder();
        $repositoryFinder->ignoreUnreadableDirs(true)
            ->ignoreDotFiles(false)
            ->ignoreVCS(false)
            ->in($directoryPath);
        if (empty($fileUmask)) {
            $repositoryFinder->directories();
        } elseif (empty($folderUmask)) {
            $repositoryFinder->files();
        }
        /** @var SplFileInfo $repositoryItem */
        foreach ($repositoryFinder as $repositoryItem) {
            if (!empty($fileUmask) && $repositoryItem->isFile()) {
                chmod($repositoryItem->getPathname(), octdec($fileUmask));
            } elseif (!empty($folderUmask) && $repositoryItem->isDir()) {
                chmod($repositoryItem->getPathname(), octdec($folderUmask));
            }
        }
    }
}
