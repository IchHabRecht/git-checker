<?php
namespace IchHabRecht\GitChecker\Tests\App\Controller;

use IchHabRecht\GitCheckerApp\Controller\DirectoryController;
use org\bovigo\vfs\vfsStream;

class DirectoryControllerTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @return array
     */
    public function ensureFileOrFolderPermissionsDataProvider()
    {
        return [
            'Permissions are set' => [
                '555',
                '777',
                '777',
            ],
            'Permissions are ensured' => [
                '555',
                [
                    'user' => 6,
                    'group' => 6,
                    'others' => 0,
                ],
                '775',
            ],
        ];
    }

    /**
     * @param string $defaultPermissions
     * @param string|array $permissions
     * @param string $expectedPermissions
     * @dataProvider ensureFileOrFolderPermissionsDataProvider
     */
    public function testEnsureFileOrFolderPermissions($defaultPermissions, $permissions, $expectedPermissions)
    {
        vfsStream::setup('root');

        $filePath = vfsStream::url('root/foo');
        touch($filePath);
        chmod($filePath, octdec($defaultPermissions));

        $mockController = $this->getMock(DirectoryController::class, ['dummy'], [], '', false);

        $reflection = new \ReflectionClass(get_class($mockController));
        $method = $reflection->getMethod('ensureFileOrFolderPermissions');
        $method->setAccessible(true);

        $method->invokeArgs($mockController, [$filePath, $permissions]);

        $this->assertSame($expectedPermissions, decoct(fileperms($filePath) & 0777));
    }
}
