<?php
namespace IchHabRecht\GitChecker\Finder;

use IchHabRecht\GitWrapper\GitRepository;
use IchHabRecht\GitWrapper\GitWrapper;
use Symfony\Component\Finder\Finder;

class RepositoryIterator implements \Iterator
{
    /**
     * @var array
     */
    protected $container = [];

    /**
     * @var \Iterator[SplFileInfo]
     */
    protected $finder;

    /**
     * @var GitWrapper
     */
    protected $gitWrapper;

    /**
     * @var int
     */
    protected $position = 0;

    /**
     * @param Finder $finder
     * @param GitWrapper $gitWrapper
     */
    public function __construct(Finder $finder, GitWrapper $gitWrapper)
    {
        $this->finder = $finder->getIterator();
        $this->gitWrapper = $gitWrapper;
    }

    /**
     * Return the current element
     *
     * @return GitRepository
     */
    public function current()
    {
        if (!isset($this->container[$this->position])) {
            $this->container[$this->position] = $this->gitWrapper->getRepository(dirname($this->finder->current()->getPathname()));
        }
        return $this->container[$this->position];
    }

    /**
     * Move forward to next element
     */
    public function next()
    {
        $this->finder->next();
        ++$this->position;
    }

    /**
     * Return the key of the current element
     * @return int|null
     */
    public function key()
    {
        return $this->position;
    }

    /**
     * Checks if current position is valid
     *
     * @return bool
     */
    public function valid()
    {
        return $this->finder->valid();
    }

    /**
     * Rewind the Iterator to the first element
     */
    public function rewind()
    {
        $this->finder->rewind();
        $this->position = 0;
    }
}
