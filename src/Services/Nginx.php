<?php

namespace JumpGate\Commands\Console\Services;

use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Exception\IOExceptionInterface;
use Symfony\Component\Filesystem\Filesystem;


/**
 * Class Nginx
 *
 * This class is meant to generate Nginx configuration files and reload the Nginx service.
 *
 * @package JumpGate\Commands\Console\Services
 */
class Nginx {

    /**
     * @var The server paths need to setup Nginx.
     */
    public $paths;

    /**
     * @var The domain name we will use in the config file.
     */
    public $domain;

    /**
     * @var Filesystem The filesystem instance
     */
    private $fileSystem;

    /**
     * TODO: This feels like it does not belong here...
     *
     * @var OutputInterface Console output BUT WHY?!?!
     */
    private $output;

    /**
     * Nginx constructor.
     *
     * @param array           $paths  The array of paths
     * @param string          $domain The domain we are adding
     * @param OutputInterface $output The output interface used in the command so we can print messages.
     */
    public function __construct($paths, $domain, OutputInterface $output)
    {
        $this->paths      = $paths;
        $this->domain     = $domain;
        $this->fileSystem = new Filesystem();

        $this->output = $output; // I don't like this but oh well for now.

    }

    /**
     * Get the config template and replace the mustache tags.
     *
     * @return mixed The updated template file contents.
     */
    public function buildConfig()
    {
        $template = include('Nginx/configTemplate.php');

        $template = str_replace(
            ['{DOMAIN}', '{SITE_PATH}'],
            [$this->domain, $this->paths['domain']],
            $template
        );

        return $template;
    }

    /**
     * Write the configuration file to disk.
     *
     * @param $configurationFile The contents to write
     */
    public function writeConfig($configurationFile)
    {
        try {
            // Create the configuration file.
            $this->fileSystem->dumpFile($this->paths['nginx']['sites-available'], $configurationFile);
        } catch (IOExceptionInterface $exception) {
            $this->output->writeln('<error>Unable to create the configuration file.</error>');

            // No files was created so roll back is not needed.
            exit(1);
        }
    }

    /**
     * Run the nginx config test command to make sure the next config we
     * just created does not contain any errors.
     *
     * If this fails we will roll back config changes.
     */
    public function configTest()
    {
        $configTest = system('service nginx configtest');

        if (!strstr($configTest, '[ OK ]')) {
            $this->output->writeln('<error>There was something wrong with the nginx config! Reverting changes.</error>');
            $this->output->writeln("Debug: {$configTest}");

            $this->rollbackChanges();
        }
    }

    /**
     * Delete the symlink and config file if they exist.
     */
    private function rollbackChanges()
    {
        // Check if the symlink exists
        if ($this->fileSystem->readlink($this->paths['nginx']['symlink'])) {
            unlink($this->paths['nginx']['symlink']);
        }

        // Check if the config exists
        if ($this->fileSystem->exists($this->paths['nginx']['config'])) {
            $this->fileSystem->remvoe($this->paths['nginx']['config']);
        }

        exit(1);
    }

    /**
     * Run the nginx reload command.
     *
     * If this fails we will roll back config changes.
     */
    public function reload()
    {
        $restartNginx = system('service nginx reload');

        // Normally nginx reload has no output unless there is an error.
        if ($restartNginx) {
            $this->output->writeln('<error>Something went wrong when restart nginx! Reverting changes.</error>');
            $this->output->writeln("Debug: {$restartNginx}");

            // There was a problem with the config so remove our changes.
            $this->rollbackChanges();
        }
    }

    /**
     * Create a symlink between the sites-available and sites-enabled config so that Nginx will see the config file.
     */
    public function enableConfig()
    {
        try {
            // Create the symlink
            $symLink = $this->fileSystem->symlink(
                $this->paths['nginx']['config'],
                $this->paths['nginx']['symlink']
            );
        } catch (IOExceptionInterface $exception) {
            $this->output->writeln('<error>There was an error creating the symlink from sites-available to sites-enabled</error>');

            // There was a problem with the config symlink so remove our changes.
            $this->rollbackChanges();
        }
    }
}