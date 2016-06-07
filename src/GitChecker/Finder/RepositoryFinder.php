<?php
namespace IchHabRecht\GitChecker\Finder;

use IchHabRecht\GitWrapper\GitWrapper;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

class RepositoryFinder implements \IteratorAggregate
{
    /**
     * @var Finder
     */
    protected $finder;

    /**
     * @var GitWrapper
     */
    protected $gitWrapper;

    /**
     * @param GitWrapper $gitWrapper
     */
    public function __construct(GitWrapper $gitWrapper)
    {
        $this->gitWrapper = $gitWrapper;
    }

    /**
     * @param string $absolutePath
     * @param array $settings
     * @return RepositoryFinder
     */
    public function getGitRepositories($absolutePath, array $settings)
    {
        if (!@is_dir($absolutePath)) {
            throw new \InvalidArgumentException('Wrong path provided', 1456264866);
        }

        if (!isset($settings['show']['depth'])) {
            throw new \InvalidArgumentException('Missing default repository configuration', 1456425560);
        }

        $this->finder = new Finder();
        $this->finder->directories()
            ->ignoreUnreadableDirs(true)
            ->ignoreDotFiles(false)
            ->ignoreVCS(false)
            ->followLinks()
            ->name('.git')
            ->depth($settings['show']['depth'])
            ->sort(function (SplFileInfo $a, SplFileInfo $b) {
                return strcmp($a->getRelativePathname(), $b->getRelativePathname());
            })
            ->in(rtrim($absolutePath, '/\\'));

        $excludeDirs = (isset($settings['show']['exclude']))
            ? $settings['show']['exclude']
            : [];
        if (!empty($excludeDirs) && is_array($excludeDirs)) {
            foreach ($excludeDirs as $dir) {
                $this->finder->notPath(strtr($dir, '\\', '/'));
            }
        }

        return $this;
    }

    /**
     * @return \Iterator
     */
    public function getIterator()
    {
        return new RepositoryIterator($this->finder, $this->gitWrapper);
    }
}