<?php

namespace slejnej\AssetInstaller;

use Composer\Composer;
use Composer\IO\IOInterface;
use Composer\Script\Event;
use Exception;
use RuntimeException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;
use Symfony\Component\Yaml\Parser;
use Symfony\Component\Yaml\Yaml;

/**
 * Installer handler for composer
 */
class InstallerHandler
{
  /**
   * Installs npm assets
   *
   * @param Event $event A instance
   * @throws Exception
   */
  public static function installAssets(Event $event): void
  {
    $options = self::getOptions($event);
    $npmAssets = self::collectNpmAssets($event->getComposer());

    if (!$npmAssets) {
      return;
    }

    $filesystem = new Filesystem();

    if ($filesystem->exists('package.json')) {
      try {
        $packageJsonContent = file_get_contents('package.json');
      } catch (Exception $exception) {
        throw new Exception('Can not read "package.json" file, ' .
          'make sure the user has permission to read it', 0, $exception);
      }

      try {
        $packageJson = json_decode($packageJsonContent, false, 512, JSON_THROW_ON_ERROR);
      } catch (Exception $exception) {
        throw new Exception('Can not parse "package.json" file, ' .
          'make sure it has valid JSON structure', 0, $exception);
      }

      $packageJson->dependencies = $npmAssets;
    } else {
      // File package.json with actual dependencies is required
      $packageJson = [
        'description' =>
          'THE FILE IS GENERATED PROGRAMMATICALLY, ALL MANUAL CHANGES IN DEPENDENCIES SECTION WILL BE LOST',
        'homepage' => 'https://doc.oroinc.com/master/frontend/javascript/composer-js-dependencies/',
        'dependencies' => $npmAssets,
        'private' => true,
      ];
    }

    $filesystem
      ->dumpFile('package.json', json_encode($packageJson, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES) . "\n");

    $isVerbose = $event->getIO()->isVerbose();
    if (!$filesystem->exists('package-lock.json')) {
      // Creates lock file, installs assets.
      self::npmInstall($event->getIO(), $options['process-timeout'], $isVerbose);
    } else {
      // Installs assets using lock file.
      self::npmCi($event->getIO(), $options['process-timeout'], $isVerbose);
    }
  }

  /**
   * Updates npm assets
   *
   * @param Event $event
   * @throws Exception
   */
  public static function updateAssets(Event $event): void
  {
    $filesystem = new Filesystem();
    $filesystem->remove('package-lock.json');

    self::installAssets($event);
  }

  /**
   * Collects npm assets from extra.npm section of installed packages
   *
   * @param Composer $composer
   * @return array
   * @throws Exception
   */
  private static function collectNpmAssets(Composer $composer): array
  {
    $rootPackage = $composer->getPackage();

    // Gets array of installed packages.
    $packages = $composer->getRepositoryManager()->getLocalRepository()->getCanonicalPackages();

    $npmAssets = [];
    $rootNpmAssets = $rootPackage->getExtra()['npm'] ?? [];
    if (!is_array($rootNpmAssets)) {
      $rootNpmAssets = [];
    }

    foreach ($packages as $package) {
      $packageNpm = $package->getExtra()['npm'] ?? [];

      if ($packageNpm && is_array($packageNpm) && !isset($npmAssets[$package->getName()])) {
        $conflictingPackages = array_diff_key(array_intersect_key($packageNpm, $npmAssets), $rootNpmAssets);

        if (!empty($conflictingPackages)) {
          throw new Exception('Where are some conflicting npm packages "' .
            implode('", "', array_keys($conflictingPackages)) . '". To how resolve conflicts, see ' .
            'https://doc.oroinc.com/master/frontend/javascript/composer-js-dependencies' .
            '#resolving-conflicting-npm-dependencies/');
        }

        $npmAssets = array_merge($npmAssets, $packageNpm);
      }
    }

    $npmAssets = array_merge($npmAssets, $rootNpmAssets);
    ksort($npmAssets);

    return $npmAssets;
  }

  /**
   * Runs "npm install", updates package-lock.json, installs
   *
   * @param IOInterface $inputOutput
   * @param int $timeout
   * @param bool $verbose
   */
  private static function npmInstall(IOInterface $inputOutput, int $timeout = 60, bool $verbose = false): void {
    $logLevel = $verbose ? 'info' : 'error';
    $npmInstallCmd = ['npm', 'install', '--no-audit', '--save-exact', '--no-optional', '--loglevel', $logLevel];

    if (self::runProcess($inputOutput, $npmInstallCmd, $timeout) !== 0) {
      throw new RuntimeException('Failed to generate package-lock.json');
    }
  }

  /**
   * execution of the command
   *
   * @param IOInterface $inputOutput
   * @param array $cmd
   * @param int $timeout
   * @return int
   */
  private static function runProcess(IOInterface $inputOutput, array $cmd, int $timeout): int
  {
    $inputOutput->write(implode(' ', $cmd));

    $npmInstall = new Process($cmd, null, null, null, $timeout);
    $npmInstall->run(function ($outputType, string $data) use ($inputOutput) {
      if ($outputType === Process::OUT) {
        $inputOutput->write($data, false);
      } else {
        $inputOutput->writeError($data, false);
      }
    });

    return $npmInstall->getExitCode();
  }

  /**
   * Runs "npm ci", installs assets to "node_modules/" using only package-lock.json
   *
   * @param IOInterface $inputOutput
   * @param int $timeout
   * @param bool $verbose
   */
  private static function npmCi(IOInterface $inputOutput, int $timeout = 60, bool $verbose = false): void
  {
    $logLevel = $verbose ? 'info' : 'error';
    $npmCiCmd = ['npm', 'ci', '--loglevel', $logLevel];

    if (self::runProcess($inputOutput, $npmCiCmd, $timeout) !== 0) {
      throw new RuntimeException('Failed to install npm assets');
    }
  }

  /**
   * get composer configuration
   *
   * @param Event $event
   * @return array
   */
  protected static function getOptions(Event $event): array
  {
    $composer = $event->getComposer();
    $config = $composer->getConfig();

    return array_merge(
      ['symfony-web-dir' => 'public'],
      $composer->getPackage()->getExtra(),
      [
        'process-timeout' => $config->get('process-timeout'),
        'vendor-dir' => $config->get('vendor-dir'),
      ]
    );
  }
}