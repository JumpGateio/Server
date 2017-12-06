<?php

namespace JumpGate\Installer\Console;

use GuzzleHttp\Client;
use GuzzleHttp\Event\ProgressEvent;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputInterface;

class DownloadCommand extends Command
{
    private $output;

    private $progress = 0;

    /**
     * Configure the command options.
     *
     * @return void
     */
    protected function configure()
    {
        $this->setName('download')
             ->setDescription('Download laravel builds.');
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
        $this->output = $output;

        $output->writeln('<info>Downloading JumpGate application data...</info>');

        $this->download();

        $output->writeln('<comment>Application download complete! Ready to build something amazingly FAST!</comment>');
    }

    /**
     * Download the temporary Zip to the given file.
     *
     * @return $this
     */
    protected function download()
    {
        $md5HashLocation  = 'http://builds.nukacode.com/files.php';
        $slimZipLocation  = dirname(__FILE__) . '/jumpGate_slim.zip';
        $fullZipLocation  = dirname(__FILE__) . '/jumpGate_full.zip';

        if ($this->checkIfServerHasNewerBuild($md5HashLocation, $slimZipLocation)) {
            $this->cleanUp($slimZipLocation);
            $this->downloadFileWithProgressBar('http://builds.nukacode.com/slimSrb/latest.zip', $slimZipLocation);
        }

        if ($this->checkIfServerHasNewerBuild($md5HashLocation, $fullZipLocation)) {
            $this->cleanUp($fullZipLocation);
            $this->downloadFileWithProgressBar('http://builds.nukacode.com/fullSrb/latest.zip', $fullZipLocation);
        }

        return $this;
    }

    /**
     * Clean-up the Zip file.
     *
     * @param the zip file to remove
     *
     * @return $this
     */
    protected function cleanUp($zipFile)
    {
        @chmod($zipFile, 0777);
        @unlink($zipFile);

        return $this;
    }


    /**
     * Download the nukacode build files and display progress bar.
     *
     * @param $buildUrl
     * @param $zipFile
     */
    protected function downloadFileWithProgressBar($buildUrl, $zipFile)
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

        file_put_contents($zipFile, $response->getBody());

        $this->output->writeln("\n<info>File download complete...</info>");
    }

    /**
     * Check if the server has a newer version of the nukacode build.
     *
     * @param string $md5CheckPath The url to check for file md5 hash
     * @param string $zipFile      The zip file we are checking
     *
     * @return bool
     */
    protected function checkIfServerHasNewerBuild($md5CheckPath, $zipFile)
    {
        if (file_exists($zipFile)) {
            $client   = new Client();
            $response = $client->get($md5CheckPath);

            // The downloaded copy is the same as the one on the server.
            if (in_array(md5_file($zipFile), $response->json())) {
                return false;
            }
        }

        return true;
    }
}
