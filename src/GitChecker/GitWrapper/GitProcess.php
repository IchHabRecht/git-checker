<?php
namespace GitChecker\GitWrapper;

use Symfony\Component\Process\Process;
use Symfony\Component\Process\ProcessUtils;

class GitProcess extends Process
{
    /**
     * @param GitWrapper $gitWrapper
     * @param GitCommand $gitCommand
     */
    public function __construct(GitWrapper $gitWrapper, GitCommand $gitCommand)
    {
        $commandLine = ProcessUtils::escapeArgument($gitWrapper->getGitBinary()) . ' ' . $gitCommand->getCommandLine();
        $directory = realpath($gitCommand->getDirectory());

        $envVars = null;
        $wrapperEnvVars = $gitWrapper->getEnvVars();
        if (!empty($wrapperEnvVars)) {
            $envVars = array_merge($_ENV, $_SERVER, $wrapperEnvVars);
        }

        parent::__construct($commandLine, $directory, $envVars, null, $gitCommand->getTimeout());
    }
}
