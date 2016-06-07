<?php
namespace IchHabRecht\GitChecker\Tests\Finder;

use IchHabRecht\GitChecker\Finder\RepositoryFinder;
use IchHabRecht\GitWrapper\GitRepository;
use IchHabRecht\GitWrapper\GitWrapper;
use org\bovigo\vfs\vfsStream;

class RepositoryFinderTest extends \PHPUnit_Framework_TestCase
{
    public function testGetGitRepositoriesFindsRepository()
    {
        vfsStream::setup('root');

        $repositoryPath = vfsStream::url('root/repository');
        mkdir($repositoryPath);
        mkdir($repositoryPath . '/.git');

        $settings = [
            'show' => [
                'depth' => '< 2',
            ],
        ];
        $gitWrapper = new GitWrapper();
        $repositoryFinder = new RepositoryFinder($gitWrapper);

        $gitRepository = $repositoryFinder->getGitRepositories($repositoryPath, $settings)->getIterator()->current();

        $this->assertInstanceOf(GitRepository::class, $gitRepository);
        $this->assertSame($repositoryPath, $gitRepository->getDirectory());
    }
}
