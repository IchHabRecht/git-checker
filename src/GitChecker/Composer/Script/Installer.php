<?php
namespace GitChecker\Composer\Script;

use Composer\Installer\InstallationManager;
use Composer\Repository\InstalledRepositoryInterface;
use Composer\Script\Event;
use Symfony\Component\Filesystem\Filesystem;

class Installer
{
    /**
     * @var Filesystem
     */
    protected static $fileSystem;

    /**
     * @var InstallationManager
     */
    protected static $installationManager;

    /**
     * @var InstalledRepositoryInterface
     */
    protected static $localRepository;

    /**
     * @param Event $event
     */
    public static function postInstall(Event $event)
    {
        static::$installationManager = $event->getComposer()->getInstallationManager();
        static::$localRepository = $event->getComposer()->getRepositoryManager()->getLocalRepository();

        $rootDirectory = __DIR__ . '/../../../../';
        static::copyFile('twbs/bootstrap', 'dist/css/bootstrap.min.css', $rootDirectory . 'public/css/bootstrap.min.css');
        static::getFileSystem()->copy($rootDirectory . 'app/settings.example.yml', $rootDirectory . 'app/settings.yml');
    }

    /**
     * @param string $packageName
     * @param string $sourceFile
     * @param string $targetFile
     * @param bool $override
     */
    protected static function copyFile($packageName, $sourceFile, $targetFile, $override = false)
    {
        $packages = static::$localRepository->findPackages($packageName, null);
        foreach ($packages as $package) {
            if (static::$installationManager->getInstaller($package->getType())->isInstalled(static::$localRepository, $package)) {
                static::getFileSystem()->copy(
                    static::$installationManager->getInstallPath($package) . '/' . ltrim($sourceFile, '/'),
                    $targetFile,
                    $override
                );
                return;
            }
        }
    }

    /**
     * @return Filesystem
     */
    protected static function getFileSystem()
    {
        if (static::$fileSystem === null) {
            static::$fileSystem = new Filesystem();
        }

        return static::$fileSystem;
    }
}
