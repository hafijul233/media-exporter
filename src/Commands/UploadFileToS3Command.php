<?php

namespace Amplify\MediaExporter\Commands;

use Exception;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

class UploadFileToS3Command extends Command
{
    protected function configure()
    {
        $this->setName('upload-to-s3')
            ->setDescription("Upload all the index file to AS three bucket location.")
            ->addArgument('count', InputArgument::OPTIONAL, 'Index for a Directory to Upload from folders.json', 0);
    }

    /**
     * Execute the console command.
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $dataDirectory = __DIR__ . '/../../data/entry';
        $completeDirectory = __DIR__ . '/../../data/complete';
        $failedDirectory = __DIR__ . '/../../data/failed';
        $folderIndexPath = __DIR__ . '/../../data/folders.json';

        try {

            $target = $input->getArgument('count');

            if (!file_exists($folderIndexPath)) {
                $output->writeln("Unable to locate folder.json file at [$folderIndexPath].");
            }

            $folders = json_decode(file_get_contents($folderIndexPath), true);

            if (!isset($folders[$target]) || !file_exists("{$dataDirectory}/{$target}.txt")) {
                throw new \ErrorException("Target index [{$dataDirectory}/{$target}.txt] does not exist");
            }

            $fileHandler = fopen("{$dataDirectory}/{$target}.txt", "r") or throw new \ErrorException("Unable to read file at [{$dataDirectory}/{$target}.txt]");

            if ($fileHandler) {
                while (!feof($fileHandler)) {
                    $output->writeln(trim(fgets($fileHandler)));
                }
                fclose($fileHandler);
            }

//            $output->writeln("<info> INFO </info> Creating index for <comment>[$target]</comment>:");
//
//            $directories = [];
//            $files = [];
//            $count = 1;
//
//            foreach (scandir($target) as $file) {
//                if (in_array($file, ['.', '..', '$RECYCLE.BIN', 'System Volume Information', '.DS_Store', '.trash-1000'])) {
//                    continue;
//                }
//
//                $filePath = str_ends_with($target, DIRECTORY_SEPARATOR)
//                    ? $target . $file
//                    : $target . DIRECTORY_SEPARATOR . $file;
//
//                if (is_dir($filePath)) {
//                    @file_put_contents($dataDirectory . 'entries' . DIRECTORY_SEPARATOR . "{$count}.txt", "");
//                    $output->writeln("<info> INFO </info> Launching new window for <comment>[$filePath]</comment>:");
//                    $process = Process::fromShellCommandline("for /R \"{$filePath}\" %i in (*) do @echo %i >> ..\..\data\/entries\/{$count}.txt", __DIR__);
//                    $process->setOptions(['create_new_console' => true]);
//                    $process->disableOutput();
//                    $process->setTimeout(null);
//                    $process->start();
//                    $directories[] = ['file' => $count . '.txt', 'folder' => $file];
//                    $count++;
//                } else {
//                    $files[] = $filePath;
//                }
//            }
//
//            @file_put_contents($dataDirectory . 'entries' . DIRECTORY_SEPARATOR . '0.txt', implode("\n", $files));
//            @file_put_contents($dataDirectory . 'folders.json', json_encode($directories, JSON_PRETTY_PRINT));
//
//            unset($files, $directories, $count);
//            $output->writeln("<info> INFO </info> Index created for <comment>[$target]</comment> is successful.");
            return self::SUCCESS;

        } catch (Exception $exception) {
            $output->writeln('<error>' . $exception->getMessage() . '</error>');
            return self::FAILURE;
        }
    }
}
