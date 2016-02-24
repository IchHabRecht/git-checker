<?php
namespace GitChecker\GitWrapper;

use Symfony\Component\Process\Process;
use Symfony\Component\Process\ProcessUtils;

class GitProcess extends Process
{
    /**
     * @param string $gitBinary
     * @param GitCommand $gitCommand
     */
    public function __construct($gitBinary, GitCommand $gitCommand)
    {
        $commandLine = ProcessUtils::escapeArgument($gitBinary) . ' ' . $gitCommand->getCommandLine();
        $directory = realpath($gitCommand->getDirectory());

        parent::__construct($commandLine, $directory);
    }
}
