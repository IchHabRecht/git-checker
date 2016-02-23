<?php
namespace GitChecker\Composer\Script;

use Composer\Script\Event;
use Symfony\Component\Filesystem\Filesystem;

class Installer
{
    /**
     * @param Event $event
     */
    public static function postInstall(Event $event)
    {
        $fileSystem = new Filesystem();
        $installationManager = $event->getComposer()->getInstallationManager();
        $localRepository = $event->getComposer()->getRepositoryManager()->getLocalRepository();
        $packages = $localRepository->findPackages('twbs/bootstrap', null);
        foreach ($packages as $package) {
            if ($installationManager->getInstaller($package->getType())->isInstalled($localRepository, $package)) {
                $fileSystem->copy(
                    $installationManager->getInstallPath($package) . '/dist/css/bootstrap.min.css',
                    getcwd() . '/public/css/bootstrap.min.css',
                    true
                );
            }
        }
    }

}
