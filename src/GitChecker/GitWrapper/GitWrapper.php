<?php
namespace GitChecker\GitWrapper;

class GitWrapper
{
    /**
     * @var string
     */
    protected $gitBinary;

    /**
     * @var array
     */
    protected $envVars = [];

    /**
     * @param string|null $gitBinary
     */
    public function __construct($gitBinary = null)
    {
        $this->gitBinary = $gitBinary ?: 'git';
    }

    /**
     * @return string
     */
    public function getGitBinary()
    {
        return $this->gitBinary;
    }

    /**
     * @param string $gitBinary
     */
    public function setGitBinary($gitBinary)
    {
        $this->gitBinary = $gitBinary;
    }

    /**
     * @return array
     */
    public function getEnvVars()
    {
        return $this->envVars;
    }

    /**
     * @param array $envVars
     */
    public function setEnvVars(array $envVars)
    {
        $this->envVars = $envVars;
    }

    /**
     * @param string $key
     * @param mixed $value
     */
    public function setEnvVar($key, $value)
    {
        $this->envVars[$key] = $value;
    }

    /**
     * @param string $command
     * @param array $options
     * @param array $arguments
     * @param string|null $directory
     * @param int|null $timeout
     * @return string
     */
    public function execute($command, array $options = [], array $arguments = [], $directory = null, $timeout = 60)
    {
        $gitCommand = new GitCommand($command, $options, $arguments);
        $gitCommand->setDirectory($directory);
        $gitCommand->setTimeout($timeout);

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
     * @param string $cloneUrl
     * @param string $directory
     * @param array $options
     * @param array $arguments
     * @return GitRepository
     */
    public function cloneRepository($cloneUrl, $directory = null, array $options = [], array $arguments = [])
    {
        if ($directory === null) {
            $pathinfo = pathinfo($cloneUrl);
            $directory = getcwd() . DIRECTORY_SEPARATOR . $pathinfo['filename'];
        }
        $gitRepository = new GitRepository($this, $directory);
        $gitRepository->create($cloneUrl, $options, $arguments);

        return $gitRepository;
    }

    /**
     * @param GitCommand $gitCommand
     * @return string
     */
    protected function run(GitCommand $gitCommand)
    {
        $gitProcess = new GitProcess($this, $gitCommand);
        $gitProcess->run();

        if (!empty($gitProcess->getExitCode())) {
            throw new \RuntimeException(
                'Git command "' . $gitCommand->getCommandLine() . '" in "' . $gitCommand->getDirectory() . '" failed with error "' . $gitProcess->getErrorOutput() . '"',
                1456365593091
            );
        }

        return $gitProcess->getOutput();
    }
}
