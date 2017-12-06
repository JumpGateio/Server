<?php

namespace JumpGate\Commands\Console;

ini_set('memory_limit', '1G');

use GuzzleHttp\Client;
use GuzzleHttp\Event\ProgressEvent;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Process\Process;
use ZipArchive;

class NewCommand extends Command {
    private $input;

    private $output;

    private $directory;

    private $zipFile;

    private $progress = 0;

    /**
     * Configure the command options.
     *
     * @return void
     */
    protected function configure()
    {
        $this->setName('new')
             ->setDescription('Create a new Nginx site')
             ->addArgument('domain', InputArgument::REQUIRED);
    }

    /**
     * Execute the command.
     *
     * @param  InputInterface  $input
     * @param  OutputInterface $output
     *
     * @return void
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->input  = $input;
        $this->output = $output;

        $domain = $input->getArgument('domain');

        // Make sure the domain name conforms to sub.domain.tld
        // With is to make sure we don't break the nginx config
        if (!$this->validateDomainName($domain)) {
            $this->output->writeln('<error>Invalid domain name. It must conform to sub.domain.tld</error>');

            exit(1);
        }

        $siteDirectory = $this->getSiteDirectory($domain);
//        $nginxConfig   = $this->buildNginxConfig($domain, $siteDirectory);


        $this->output->writeln(getenv("HOME"));


        // create sites directory
        // Validate domain
        // create domain config
        // symlink config
        // reload nginx config


        exit();

        $helper   = $this->getHelper('question');
        $question = new ChoiceQuestion('Select a Customer:', [1 => 'Re-Rentals', 2 => 'Cast Locations', 3 => 'Beth Tezedec']);

        $customer = $helper->ask($input, $output, $question);

        $question = new ChoiceQuestion('Select a Server', [1 => 'Staging', 2 => 'Production']);

        $server = $helper->ask($input, $output, $question);

        exit($customer . ' ' . $server);


        $this->directory = getcwd() . '/' . $input->getArgument('name');
        $this->zipFile   = $this->makeFilename();

        $output->writeln('<info>Crafting JumpGate application...</info>');

        $this->verifyApplicationDoesntExist();

        $this->download();

        $this->extract();

        $output->writeln('<comment>Application ready! Build something amazing.</comment>');


    }

    /**
     * Validate a domain name to make sure it wont break Nginx
     *
     * @param $domain The domain to validate
     *
     * @return bool If valid return true otherwise return false
     */
    protected function validateDomainName($domain)
    {
        if (preg_match('/^[a-z0-9][a-z0-9\-]+[a-z0-9](\.[a-z]{2,5})+$/i', $domain)) {
            return true;
        }

        return false;
    }

    /**
     * Get the path for the sites directory and then find or create the folder for the domain.
     *
     * @param $domain The domain for the site we are adding to the server.
     *
     * @return string The path of our domain directory.
     */
    protected function getSiteDirectory($domain)
    {
        $basePath   = getenv("HOME");
        $sitesPath  = $basePath . '/sites/';
        $domainPath = $sitesPath . $domain;

        // Home directory (/home/username) not found
        if (!is_dir($basePath)) {
            $this->output->writeln('<error>Unable to find home folder: ' . $basePath . '.</error>');

            exit(1);
        }

        // Sites directory (/home/username/sites) not found
        if (!is_dir($sitesPath)) {
            $this->output->writeln('<error>Unable to find sites folder in ' . $basePath . '.</error>');

            exit(1);
        }

        // If the site directory does not exist create it.
        if (!is_dir($domainPath)) {
            mkdir($domainPath, 0775);

            // Unable to create sites folder for domain.
            if (!is_dir($domainPath)) {
                $this->output->writeln('<error>Unable to create folder: ' . $domainPath . '.</error>');

                exit(1);
            }
        }

        // Return the site directory
        return $domainPath;
    }

    protected function buildNginxConfig($domain)
    {

    }

    protected function getNginxConfigTemplate()
    {

    }

    protected function writeNginxConfig($config, $path)
    {

    }

    protected function enableNginxSite()
    {

    }

    /**
     * Generate a random temporary filename.
     *
     * @return string
     */
    protected function makeFilename()
    {
        if ($this->input->getOption('slim')) {
            return dirname(__FILE__) . '/jumpGate_slim.zip';
        }

        return dirname(__FILE__) . '/jumpGate_full.zip';
    }

    /**
     * Verify that the application does not already exist.
     *
     * @return void
     */
    protected function verifyApplicationDoesntExist()
    {
        $this->output->writeln('<info>Checking application path for existing site...</info>');

        if (is_dir($this->directory)) {
            $this->output->writeln('<error>Application already exists!</error>');

            exit(1);
        }

        $this->output->writeln('<info>Check complete...</info>');
    }

    /**
     * Download the temporary Zip to the given file.
     *
     * @return $this
     */
    protected function download()
    {
        $buildUrl = $this->getBuildFileLocation();

        if ($this->input->getOption('force')) {
            $this->output->writeln('<info>--force command given. Deleting old build files...</info>');

            $this->cleanUp();

            $this->output->writeln('<info>complete...</info>');
        }

        if ($this->checkIfServerHasNewerBuild()) {
            $this->cleanUp();
            $this->downloadFileWithProgressBar($buildUrl);
        }

        return $this;
    }

    /**
     * Get the build file location based on the flags passed in.
     *
     * @return string
     */
    protected function getBuildFileLocation()
    {
        if ($this->input->getOption('slim')) {
            return 'http://builds.nukacode.com/slimSrb/latest.zip';
        }

        return 'http://builds.nukacode.com/fullSrb/latest.zip';
    }

    /**
     * Clean-up the Zip file.
     *
     * @return $this
     */
    protected function cleanUp()
    {
        @chmod($this->zipFile, 0777);
        @unlink($this->zipFile);

        return $this;
    }

    /**
     * Check if the server has a newer version of the nukacode build.
     *
     * @return bool
     */
    protected function checkIfServerHasNewerBuild()
    {
        if (file_exists($this->zipFile)) {
            $client   = new Client();
            $response = $client->get('http://builds.nukacode.com/files.php');

            // The downloaded copy is the same as the one on the server.
            if (in_array(md5_file($this->zipFile), $response->json())) {
                return false;
            }
        }

        return true;
    }

    /**
     * Download the nukacode build files and display progress bar.
     *
     * @param $buildUrl
     */
    protected function downloadFileWithProgressBar($buildUrl)
    {
        $this->output->writeln('<info>Begin file download...</info>');

        $progressBar = new ProgressBar($this->output, 100);
        $progressBar->start();

        $client  = new Client();
        $request = $client->createRequest('GET', $buildUrl);
        $request->getEmitter()->on('progress', function (ProgressEvent $e) use ($progressBar) {
            if ($e->downloaded > 0) {
                $localProgress = floor(($e->downloaded / $e->downloadSize * 100));

                if ($localProgress != $this->progress) {
                    $this->progress = (integer)$localProgress;
                    $progressBar->advance();
                }
            }
        });

        $response = $client->send($request);

        $progressBar->finish();

        file_put_contents($this->zipFile, $response->getBody());

        $this->output->writeln("\n<info>File download complete...</info>");
    }

    /**
     * Extract the zip file into the given directory.
     *
     *
     * @return $this
     */
    protected function extract()
    {
        $this->output->writeln('<info>Extracting files...</info>');

        $archive = new ZipArchive;

        $archive->open($this->zipFile);

        $archive->extractTo($this->directory);

        $archive->close();

        $this->output->writeln('<info>Extracting complete...</info>');

        return $this;
    }

    /**
     * Run post install composer commands
     *
     * @return void
     */
    protected function runComposerCommands()
    {
        $this->output->writeln('<info>Running post install scripts...</info>');

        $composer = $this->findComposer();

        $commands = [
            $composer . ' run-script post-install-cmd',
            $composer . ' run-script post-create-project-cmd',
        ];

        $process = new Process(implode(' && ', $commands), $this->directory, null, null, null);

        $process->run(function ($type, $line) {
            $this->output->write($line);
        });

        $this->output->writeln('<info>Scripts complete...</info>');
    }

    /**
     * Get the composer command for the environment.
     *
     * @return string
     */
    protected function findComposer()
    {
        if (file_exists(getcwd() . '/composer.phar')) {
            return '"' . PHP_BINARY . '" composer.phar';
        }

        return 'composer';
    }
}
