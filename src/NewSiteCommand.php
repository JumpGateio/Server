<?php

namespace JumpGate\Server\Console;

use JumpGate\Server\Console\Services\Nginx;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Exception\IOExceptionInterface;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Class NewSiteCommand
 *
 * This command will create an nginx config for a new site.
 * You must have the ability to run 'service nginx configtest|reload'.
 *
 * @package JumpGate\Commands\Console
 */
class NewSiteCommand extends Command {

    /**
     * @var The input instance.
     */
    private $input;

    /**
     * @var The output instance.
     */
    private $output;

    /**
     * @var array The site paths.
     */
    private $paths = [];

    /**
     * @var The filesystem instance.
     */
    private $fileSystem;

    /**
     * Configure the command options.
     *
     * @return void
     */
    protected function configure()
    {
        $this->setName('new-site')
             ->setDescription('Create a new Nginx site')
             ->addArgument('domain', InputArgument::REQUIRED);
    }

    /**
     * Execute the command.
     *
     * @param  InputInterface  $input  The input instance.
     * @param  OutputInterface $output The output instance.
     *
     * @return void
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $output->writeln('<info>Creating a new site just for you!</info>');

        $this->input  = $input;
        $this->output = $output;

        $domain = $input->getArgument('domain');

        // Make sure the domain name conforms to sub.domain.tld
        // With is to make sure we don't break the nginx config
        $this->validateDomainName($domain);

        // Setup filesystem
        $this->fileSystem = new Filesystem();

        // Build up all the paths we will need to create a new site
        $this->buildPaths($domain);

        // Make sure all the paths we need exists.
        $this->validatePaths();

        // Setup Nginx
        $nginx = new Nginx($this->paths, $domain, $this->output);

        // Run config test first to make sure the system is not broken to begin with.
        $nginx->configTest();

        // Build the configuration file from a template.
        $configurationFile = $nginx->buildConfig();

        // Write the configuration file to disk.
        $nginx->writeConfig($configurationFile);

        // Create a symlink in the site-enabled directory so nginx will see the config file.
        $nginx->enableConfig();

        // Test the config to make sure there are no issues.
        $nginx->configTest();

        // Reload nginx configs.
        $nginx->reload();

        $output->writeln('<comment>Your new site has been created. Make sure to update your DNS.</comment>');

        exit(0);
    }

    /**
     * Validate a domain name to make sure it wont break Nginx
     *
     * @param $domain The domain to validate
     */
    protected function validateDomainName($domain)
    {
        if (!preg_match(
            '/^(([a-zA-Z]{1})|([a-zA-Z]{1}[a-zA-Z]{1})|([a-zA-Z]{1}[0-9]{1})|([0-9]{1}[a-zA-Z]{1})|([a-zA-Z0-9][a-zA-Z0-9-_]{1,61}[a-zA-Z0-9]))\.([a-zA-Z]{2,6}|[a-zA-Z0-9-]{2,30}\.[a-zA-Z]{2,3})$/m',
            $domain
        )) {
            $this->output->writeln('<error>Invalid domain name. The domain must conform to sub.domain.tld</error>');
            exit(1);
        }
    }

    /**
     * Build up all the paths we will need to create a new site
     *
     * @param $domain The domain of the site we are adding to the server
     */
    private function buildPaths($domain)
    {
        // TODO: Should we turn this into a class?
        $this->paths['base']          = getenv("HOME") . '/';
        $this->paths['site']          = $this->paths['base'] . 'sites/';
        $this->paths['domain']        = $this->paths['site'] . $domain;
        $this->paths['nginx']['base'] = $this->paths['base'] . 'nginx/';

        $this->paths['nginx']['sites-available'] = $this->paths['nginx']['base'] . 'sites-available/';
        $this->paths['nginx']['sites-enabled']   = $this->paths['nginx']['base'] . 'sites-enabled/';
        $this->paths['nginx']['config']          = $this->paths['nginx']['sites-available'] . $domain;
        $this->paths['nginx']['symlink']         = $this->paths['nginx']['sites-enabled'] . $domain;
    }

    /**
     * Check if the folders needed for nginx exist.
     */
    protected function validatePaths()
    {
        // Check if the home directory exists
        // /home/USERNAME
        $this->findOrCreateDirectory($this->paths['base'], false);

        // Check if the sites directory exists. If not create it.
        // /home/USERNAME/sites
        $this->findOrCreateDirectory($this->paths['site']);

        // Check if the domain directory exists. If not create it.
        // /home/USERNAME/sites/DOMAIN
        $this->findOrCreateDirectory($this->paths['domain']);

        // Check if the nginx directory exists. If not create it.
        // /home/USERNAME/nginx
        $this->findOrCreateDirectory($this->paths['nginx']['base']);

        // Check if the sites-available directory exists. If not create it.
        // /home/USERNAME/nginx/sites-available
        $this->findOrCreateDirectory($this->paths['nginx']['sites-available']);

        // Check if the sites-enabled directory exists. If not create it.
        // /home/USERNAME/nginx/sites-enabled
        $this->findOrCreateDirectory($this->paths['nginx']['sites-enabled']);
    }

    /**
     * Check if a directory exists. If not create it if create directory is set to true;
     *
     * @param      $path            The path we are checking.
     * @param bool $createDirectory Should we create the directory if it does not exists
     */
    private function findOrCreateDirectory($path, $createDirectory = true)
    {
        if (!$this->fileSystem->exists($path)) {
            if ($createDirectory) {
                $this->output->writeln("<info>Folder not found. Attempting to create: {$path}</info>");

                try {
                    $this->fileSystem->mkdir($path, 0775);
                } catch (IOExceptionInterface $e) {
                    $this->output->writeln("<error>Unable to create folder: {$path}</error>");
                    $this->output->writeln("<info>Error: {$e->getMessage()}</info>");

                    exit(1);
                }
            } else {
                $this->output->writeln("<error>Unable to find folder: {$path}.</error>");

                exit(1);
            }
        }
    }
}
