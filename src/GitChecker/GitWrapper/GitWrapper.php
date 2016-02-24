<?php
namespace GitChecker\GitWrapper;

class GitWrapper
{
    /**
     * @var string
     */
    protected $gitBinary;

    /**
     * @param string|null $gitBinary
     */
    public function __construct($gitBinary = null)
    {
        $this->gitBinary = $gitBinary ?: 'git';
    }

    /**
     * @param string $gitBinary
     */
    public function setGitBinary($gitBinary)
    {
        $this->gitBinary = $gitBinary;
    }

    /**
     * @param string $command
     * @param array $options
     * @param array $arguments
     * @param string|null $directory
     * @return string
     */
    public function execute($command, array $options = [], array $arguments = [], $directory = null)
    {
        $gitCommand = new GitCommand($command, $options, $arguments);
        $gitCommand->setDirectory($directory);

        return $this->run($gitCommand);
    }

    /**
     * @param string $directory
     * @return GitRepository
     */
    public function getRepository($directory)
    {
        return new GitRepository($this, $directory);
    }

    /**
     * @param GitCommand $gitCommand
     * @return string
     */
    protected function run(GitCommand $gitCommand)
    {
        $gitProcess = new GitProcess($this->gitBinary, $gitCommand);
        $gitProcess->run();

        return $gitProcess->getOutput();
    }
}
