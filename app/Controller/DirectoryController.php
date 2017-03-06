<?php
namespace IchHabRecht\GitCheckerApp\Controller;

use IchHabRecht\Filesystem\Filemode;
use IchHabRecht\Filesystem\Filepath;
use IchHabRecht\GitChecker\Finder\RepositoryFinder;
use IchHabRecht\GitWrapper\GitRepository;
use IchHabRecht\GitWrapper\GitWrapper;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
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
        $root = $request->getAttribute('rootPath');

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

        $gitWrapper = $this->getGitWrapper($settings['git-wrapper']);
        $repositoryFinder = new RepositoryFinder($gitWrapper);

        $repositories = [];
        /** @var GitRepository $gitRepository */
        foreach ($repositoryFinder->getGitRepositories($absoluteVirtualHostPath, $settings['virtual-host']) as $gitRepository) {
            $repositories[] = [
                'relativePath' => str_replace($absoluteVirtualHostPath . DIRECTORY_SEPARATOR, '', $gitRepository->getDirectory()),
                'commit' => $gitRepository->log(['1', 'oneline']),
                'status' => $gitRepository->getStatus(),
                'trackingInformation' => $gitRepository->getTrackingInformation(),
            ];
        }

        $this->view->render(
            $response,
            'show.twig',
            [
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
        $repositoryFinder = new RepositoryFinder($gitWrapper);
        $gitRepository = $repositoryFinder->getGitRepositories($absolutePath, $settings['virtual-host'])->getIterator()->current();
        $branches = $gitRepository->branch(['r']);
        $tags = $gitRepository->tag(['l', 'sort=-v:refname']);
        $currentBranch = $gitRepository->getCurrentBranch();

        // remove heads/ prefix if it was an tag
        $currentBranch = str_replace('heads/', '', $currentBranch);

        // Remove all HEAD pointers
        $branches = array_filter($branches, function ($value) {
            return strpos($value, '/HEAD ') === false;
        });

        // add tags string on every tag
        array_walk($tags, function(&$value, $key) { $value = 'tags/'.$value; });

        $this->view->render(
            $response,
            'branch.twig',
            [
                'currentBranch' => $currentBranch,
                'virtualHost' => $request->getAttribute('virtualHostPath'),
                'absoluteVirtualHostPath' => $request->getAttribute('absoluteVirtualHostPath'),
                'repository' => $request->getAttribute('repository'),
                'branches' => $branches,
                'tags' => $tags
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
        $repositoryFinder = new RepositoryFinder($gitWrapper);
        $gitRepository = $repositoryFinder->getGitRepositories($absolutePath, $settings['virtual-host'])->getIterator()->current();
        $remoteBranchName = $requestArguments['branch-name'];
        $localBranchName = substr($remoteBranchName, strpos($remoteBranchName, '/') + 1);
        $currentBranch = $gitRepository->getCurrentBranch();
        if ($currentBranch !== $localBranchName) {
            if (strpos($remoteBranchName, GitRepository::TAG_ORIGIN.'/') !== false) {
                // tags
                $gitRepository->checkout([['B' => $localBranchName]], [$remoteBranchName]);
            } else {
                // branches
                $gitRepository->checkout(['track', ['B' => $localBranchName]], [$remoteBranchName]);
            }

            $filemode = new Filemode();
            $filemode->setPermissions($gitRepository->getDirectory(), $settings['virtual-host']['umask']);
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

        $gitWrapper = $this->getGitWrapper($settings['git-wrapper']);
        $repositoryFinder = new RepositoryFinder($gitWrapper);

        /** @var GitRepository $gitRepository */
        foreach ($repositoryFinder->getGitRepositories($absolutePath, $settings['virtual-host']) as $gitRepository) {
            $trackingInformation = $gitRepository->getTrackingInformation();
            if (empty($trackingInformation['remoteBranch'])) {
                continue;
            }
            $gitRepository->fetch(['tags', 'all', 'prune']);
            $filemode = new Filemode();
            $filemode->setPermissions(rtrim($gitRepository->getDirectory(), '/\\') . '/.git', $settings['virtual-host']['umask']);
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

        $gitWrapper = $this->getGitWrapper($settings['git-wrapper']);
        $repositoryFinder = new RepositoryFinder($gitWrapper);
        $filemode = new Filemode();

        /** @var GitRepository $gitRepository */
        foreach ($repositoryFinder->getGitRepositories($absolutePath, $settings['virtual-host']) as $gitRepository) {
            $trackingInformation = $gitRepository->getTrackingInformation();
            if (!empty($trackingInformation['behind']) && !$gitRepository->hasChanges()) {
                $gitRepository->pull(['ff-only']);
                $filemode->setPermissions($gitRepository->getDirectory(), $settings['virtual-host']['umask']);
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
        $repositoryFinder = new RepositoryFinder($gitWrapper);
        $gitRepository = $repositoryFinder->getGitRepositories($absolutePath, $settings['virtual-host'])->getIterator()->current();
        $trackingInformation = $gitRepository->getTrackingInformation();
        $branch = !empty($trackingInformation['remoteBranch'])
            ? $trackingInformation['remoteBranch']
            : 'HEAD';
        $gitRepository->reset(['hard'], [$branch]);

        $filemode = new Filemode();
        $filemode->setPermissions($gitRepository->getDirectory(), $settings['virtual-host']['umask']);

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

        if (!empty($settings['virtual-host']['add']['allow'])) {
            $folders = array_map(function ($value) {
                return [
                    'relativePathname' => trim($value, '\\/'),
                ];
            }, $settings['virtual-host']['add']['allow']);
        } else {
            $finder = new Finder();
            $finder->directories()
                ->ignoreUnreadableDirs(true)
                ->ignoreDotFiles(true)
                ->ignoreVCS(true)
                ->followLinks()
                ->depth($settings['virtual-host']['show']['depth'])
                ->sort(function (SplFileInfo $a, SplFileInfo $b) {
                    return strcmp($a->getRelativePathname(), $b->getRelativePathname());
                })
                ->in($absoluteVirtualHostPath);

            $excludeShowDirs = !empty($settings['virtual-host']['show']['exclude'])
                ? $settings['virtual-host']['show']['exclude']
                : [];
            $excludeAddDirs = !empty($settings['virtual-host']['add']['exclude'])
                ? $settings['virtual-host']['add']['exclude']
                : [];
            foreach (array_merge($excludeShowDirs, $excludeAddDirs) as $dir) {
                $finder->notPath(strtr($dir, '\\', '/'));
            }

            $folders = iterator_to_array($finder->getIterator());
            array_unshift($folders, ['relativePathname' => '']);
        }

        $this->view->render(
            $response,
            'add.twig',
            [
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

        $filepath = new Filepath(DIRECTORY_SEPARATOR, true);
        $parentDirectory = $filepath->normalize($requestArguments['parent-directory']);

        if (!empty($settings['virtual-host']['add']['allow']) && !in_array($parentDirectory, $settings['virtual-host']['add']['allow'])) {
            throw new \InvalidArgumentException('Unauthorized parent directory "' . $parentDirectory . '"', 1465751663);
        }

        $excludeShowDirs = !empty($settings['virtual-host']['show']['exclude'])
            ? $settings['virtual-host']['show']['exclude']
            : [];
        $excludeAddDirs = !empty($settings['virtual-host']['add']['exclude'])
            ? $settings['virtual-host']['add']['exclude']
            : [];
        foreach (array_merge($excludeShowDirs, $excludeAddDirs) as $dir) {
            if (strpos($parentDirectory, $dir) !== false) {
                throw new \InvalidArgumentException('Unauthorized parent directory "' . $parentDirectory . '"', 1465751917);
            }
        }

        $targetDirectory = $filepath->concatenate($absoluteVirtualHostPath, $parentDirectory);
        if (!is_dir($targetDirectory)) {
            throw new \InvalidArgumentException('Parent directory "' . $targetDirectory . '" does net exist', 1461615365);
        }

        $cloneUrl = $requestArguments['clone-url'];
        $pathinfo = pathinfo($cloneUrl);
        $cloneDirectory = $filepath->concatenate($targetDirectory, $pathinfo['filename']);

        if (is_dir($cloneDirectory)) {
            throw new \InvalidArgumentException('Target directory "' . $cloneDirectory . '" does exist', 1461616033);
        }

        $gitWrapper = $this->getGitWrapper($settings['git-wrapper']);
        $gitRepository = $gitWrapper->cloneRepository($cloneUrl, $cloneDirectory);

        if (!empty($requestArguments['branch-name'])) {
            $branchName = $requestArguments['branch-name'];
            $gitRepository->checkout(['track', ['b' => $branchName]], ['origin/' . $branchName]);
        }

        $filemode = new Filemode();
        $filemode->setPermissions($cloneDirectory, $settings['virtual-host']['umask']);

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
}
