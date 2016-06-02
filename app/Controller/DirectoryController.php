<?php
namespace IchHabRecht\GitCheckerApp\Controller;

use IchHabRecht\GitWrapper\GitWrapper;
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
        $root = $request->getAttribute('absoluteRootPath');

        $finder = new Finder();
        $finder->directories()
            ->ignoreUnreadableDirs(true)
            ->depth(0)
            ->sortByName()
            ->in($root);

        if (!empty($settings['virtual-host']['index']['exclude'])
            && is_array($settings['virtual-host']['index']['exclude'])
        ) {
            foreach ($settings['virtual-host']['index']['exclude'] as $dir) {
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
        $virtualHost = $request->getAttribute('virtualHostPath');
        $absoluteVirtualHostPath = $request->getAttribute('absoluteVirtualHostPath');

        $finder = $this->getRepositoryFinder($absoluteVirtualHostPath, $settings['virtual-host']);

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
                'virtualHost' => $virtualHost,
                'absoluteVirtualHostPath' => $absoluteVirtualHostPath,
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
    public function branch(Request $request, Response $response, array $arguments)
    {
        $settings = $request->getAttribute('settings');
        $absolutePath = $request->getAttribute('absoluteRepositoryPath');

        $gitWrapper = $this->getGitWrapper($settings['git-wrapper']);
        $finder = $this->getRepositoryFinder($absolutePath, $settings['virtual-host']);
        $directory = $finder->getIterator()->current();
        $gitRepository = $gitWrapper->getRepository(dirname($directory->getPathname()));
        $branches = $gitRepository->branch(['r']);

        // Remove all HEAD pointers
        $branches = array_filter($branches, function ($value) {
            return strpos($value, '/HEAD ') === false;
        });

        $this->view->render(
            $response,
            'branch.twig',
            [
                'settings' => $settings,
                'virtualHost' => $request->getAttribute('virtualHostPath'),
                'absoluteVirtualHostPath' => $request->getAttribute('absoluteVirtualHostPath'),
                'repository' => $request->getAttribute('repository'),
                'branches' => $branches,
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
    public function checkout(Request $request, Response $response, array $arguments)
    {
        $requestArguments = $request->getParsedBody();
        if (!isset($requestArguments['branch-name'])) {
            throw new \InvalidArgumentException('branch name is missing', 1463217674);
        }

        $settings = $request->getAttribute('settings');
        $absolutePath = $request->getAttribute('absoluteRepositoryPath');

        $gitWrapper = $this->getGitWrapper($settings['git-wrapper']);
        $finder = $this->getRepositoryFinder($absolutePath, $settings['virtual-host']);
        $directory = $finder->getIterator()->current();
        $gitRepository = $gitWrapper->getRepository(dirname($directory->getPathname()));
        $remoteBranchName = $requestArguments['branch-name'];
        $localBranchName = substr($remoteBranchName, strpos($remoteBranchName, '/') + 1);
        $currentBranch = $gitRepository->getCurrentBranch();
        if ($currentBranch !== $localBranchName) {
            $gitRepository->checkout(['track', ['B' => $localBranchName]], [$remoteBranchName]);
            $this->setUmask($directory->getPathname(), $settings['virtual-host']);
        }

        return $this->redirectTo('show', $response, ['virtualHost' => $arguments['virtualHost']]);
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

        $absolutePath = isset($arguments['repository'])
            ? $request->getAttribute('absoluteRepositoryPath')
            : $request->getAttribute('absoluteVirtualHostPath');

        $finder = $this->getRepositoryFinder($absolutePath, $settings['virtual-host']);

        $gitWrapper = $this->getGitWrapper($settings['git-wrapper']);
        /** @var SplFileInfo $directory */
        foreach ($finder as $directory) {
            $gitRepository = $gitWrapper->getRepository(dirname($directory->getPathname()));
            $trackingInformation = $gitRepository->getTrackingInformation();
            if (empty($trackingInformation['remoteBranch'])) {
                continue;
            }
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

        $absolutePath = isset($arguments['repository'])
            ? $request->getAttribute('absoluteRepositoryPath')
            : $request->getAttribute('absoluteVirtualHostPath');

        $finder = $this->getRepositoryFinder($absolutePath, $settings['virtual-host']);

        $gitWrapper = $this->getGitWrapper($settings['git-wrapper']);
        /** @var SplFileInfo $directory */
        foreach ($finder as $directory) {
            $gitRepository = $gitWrapper->getRepository(dirname($directory->getPathname()));
            $trackingInformation = $gitRepository->getTrackingInformation();
            if (!empty($trackingInformation['behind']) && !$gitRepository->hasChanges()) {
                $gitRepository->pull(['ff-only']);
                $this->setUmask(dirname($directory->getPathname()), $settings['virtual-host']);
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

        $absolutePath = isset($arguments['repository'])
            ? $request->getAttribute('absoluteRepositoryPath')
            : $request->getAttribute('absoluteVirtualHostPath');

        $gitWrapper = $this->getGitWrapper($settings['git-wrapper']);
        $finder = $this->getRepositoryFinder($absolutePath, $settings['virtual-host']);
        $directory = $finder->getIterator()->current();
        $gitRepository = $gitWrapper->getRepository(dirname($directory->getPathname()));
        $trackingInformation = $gitRepository->getTrackingInformation();
        $branch = !empty($trackingInformation['remoteBranch'])
            ? $trackingInformation['remoteBranch']
            : 'HEAD';
        $gitRepository->reset(['hard'], [$branch]);
        $this->setUmask(dirname($directory->getPathname()), $settings['virtual-host']);

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
        $virtualHost = $request->getAttribute('virtualHostPath');
        $absoluteVirtualHostPath = $request->getAttribute('absoluteVirtualHostPath');

        if (!@is_dir($absoluteVirtualHostPath)) {
            throw new \InvalidArgumentException('Wrong path provided', 1461609455);
        }

        $folders = isset($settings['virtual-host']['add']['allow'])
            ? $settings['virtual-host']['add']['allow']
            : [];
        if (!empty($folders)) {
            array_walk($folders, function (&$value) {
                $value = [
                    'relativePathname' => trim($value, '\\/'),
                ];
            });
        } else {
            $folders = new Finder();
            $folders->directories()
                ->ignoreUnreadableDirs(true)
                ->ignoreDotFiles(true)
                ->ignoreVCS(true)
                ->followLinks()
                ->depth($settings['virtual-host']['show']['depth'])
                ->sort(function (SplFileInfo $a, SplFileInfo $b) {
                    return strcmp($a->getRelativePathname(), $b->getRelativePathname());
                })
                ->in($absoluteVirtualHostPath);

            $excludeShowDirs = isset($settings['virtual-host']['show']['exclude'])
                ? $settings['virtual-host']['show']['exclude']
                : [];
            $excludeAddDirs = isset($settings['virtual-host']['add']['exclude'])
                ? $settings['virtual-host']['add']['exclude']
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
                'virtualHost' => $virtualHost,
                'absoluteVirtualHostPath' => $absoluteVirtualHostPath,
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
        $absoluteVirtualHostPath = $request->getAttribute('absoluteVirtualHostPath');

        $targetDirectory = $absoluteVirtualHostPath . trim($requestArguments['parent-directory'], '/\\') . DIRECTORY_SEPARATOR;
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

        if (!empty($requestArguments['branch-name'])) {
            $branchName = $requestArguments['branch-name'];
            $gitRepository->checkout(['track', ['b' => $branchName]], ['origin/' . $branchName]);
        }
        $this->setUmask($targetDirectory . $cloneDirectory, $settings['virtual-host']);

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
     * @param string $absolutePath
     * @param array $settings
     * @return Finder
     */
    protected function getRepositoryFinder($absolutePath, array $settings)
    {
        if (!@is_dir($absolutePath)) {
            throw new \InvalidArgumentException('Wrong path provided', 1456264866695);
        }

        if (!isset($settings['show']['depth'])) {
            throw new \InvalidArgumentException('Missing default repository configuration', 1456425560002);
        }

        $finder = new Finder();
        $finder->directories()
            ->ignoreUnreadableDirs(true)
            ->ignoreDotFiles(false)
            ->ignoreVCS(false)
            ->followLinks()
            ->name('.git')
            ->depth($settings['show']['depth'])
            ->sort(function (SplFileInfo $a, SplFileInfo $b) {
                return strcmp($a->getRelativePathname(), $b->getRelativePathname());
            })
            ->in($absolutePath);

        $excludeDirs = (isset($settings['show']['exclude']))
            ? $settings['show']['exclude']
            : [];
        if (!empty($excludeDirs) && is_array($excludeDirs)) {
            foreach ($excludeDirs as $dir) {
                $finder->notPath(strtr($dir, '\\', '/'));
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
     */
    protected function setUmask($directoryPath, array $settings)
    {
        $fileUmask = !empty($settings['umask']['file'])
            ? $settings['umask']['file']
            : 0;
        $fileUmask = is_array($fileUmask) ? array_filter($fileUmask) : $fileUmask;
        $folderUmask = !empty($settings['umask']['folder'])
            ? $settings['umask']['folder']
            : 0;
        $folderUmask = is_array($folderUmask) ? array_filter($folderUmask) : $folderUmask;

        if (empty($fileUmask) && empty($folderUmask)) {
            return;
        }

        $repositoryFinder = new Finder();
        $repositoryFinder->ignoreUnreadableDirs(true)
            ->ignoreDotFiles(false)
            ->ignoreVCS(true)
            ->in($directoryPath);
        if (empty($fileUmask)) {
            $repositoryFinder->directories();
        } elseif (empty($folderUmask)) {
            $repositoryFinder->files();
        }
        /** @var SplFileInfo $repositoryItem */
        foreach ($repositoryFinder as $repositoryItem) {
            if (!empty($fileUmask) && $repositoryItem->isFile()) {
                $this->ensureFileOrFolderPermissions($repositoryItem->getPathname(), $fileUmask);
            } elseif (!empty($folderUmask) && $repositoryItem->isDir()) {
                $this->ensureFileOrFolderPermissions($repositoryItem->getPathname(), $folderUmask);
            }
        }
    }

    /**
     * @param string $fileOrFolderPath
     * @param string|array $permissions
     */
    protected function ensureFileOrFolderPermissions($fileOrFolderPath, $permissions)
    {
        if (!is_array($permissions)) {
            chmod($fileOrFolderPath, octdec($permissions));
        } else {
            $fileOrFolderPermissions = fileperms($fileOrFolderPath);
            $fileOrFolderPermissionArray = array_combine(
                ['user', 'group', 'others'],
                array_pad(str_split(decoct($fileOrFolderPermissions & 0777)), 3, 0)
            );
            foreach ($permissions as $key => $value) {
                if (($fileOrFolderPermissionArray[$key] & $value) !== $value) {
                    $fileOrFolderPermissionArray[$key] |= $value;
                }
            }
            $newFileOrFolderPermissions = octdec(implode('', $fileOrFolderPermissionArray));
            if (($fileOrFolderPermissions & $newFileOrFolderPermissions) !== $newFileOrFolderPermissions) {
                chmod($fileOrFolderPath, $newFileOrFolderPermissions);
            }
        }
    }
}
