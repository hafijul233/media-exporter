<?php

namespace Amplify\MediaExporter\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Process\Process;

class IndexFileCommand extends Command
{
    protected function configure()
    {
        $this->setName('create-index')
            ->setDescription("Create a list of all the files of target directory")
            ->addArgument('directory', InputArgument::REQUIRED, 'Target Directory Location');
    }

    /**
     * Execute the console command.
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $logDirectory = __DIR__ . '/../../data/';

        try {

            $target = $input->getArgument('directory');

            $targetStrLength = strlen($target);

            if (!file_exists($target)) {
                $output->writeln("Target directory $target does not exist");
            }

            $output->writeln("<info> INFO </info> Creating index for <comment>[$target]</comment>:");

            $directories = [
                "root" => $target,
                'folders' => []
            ];
            $files = [];
            $count = 1;

            foreach (scandir($target) as $file) {
                if (in_array($file, ['.', '..', '$RECYCLE.BIN', 'System Volume Information', '.DS_Store', '.trash-1000'])) {
                    continue;
                }

                $filePath = str_ends_with($target, DIRECTORY_SEPARATOR)
                    ? $target . $file
                    : $target . DIRECTORY_SEPARATOR . $file;

                if (is_dir($filePath)) {

                    file_put_contents($logDirectory . 'entry' . DIRECTORY_SEPARATOR . "{$count}.txt", "");

                    $output->writeln("<info> INFO </info> Launching new window for <comment>[$filePath]</comment>:");
                    $process = Process::fromShellCommandline("for /R \"{$filePath}\" %i in (*) do @echo %i >> ..\..\data\/entry\/{$count}.txt", __DIR__);
                    $process->setOptions(['create_new_console' => true]);
                    $process->disableOutput();
                    $process->setTimeout(null);
                    $process->start();
                    $directories['folders'][] = ['file' => $count . '.txt', 'folder' => $file];
                    $count++;
                } else {
                    $files[] = $filePath;
                }
            }

            if (!empty($files)) {

                file_put_contents($logDirectory . 'entry' . DIRECTORY_SEPARATOR . '0.txt', implode("\n", $files));

                array_unshift($directories['folders'], ['file' => '0.txt', 'folder' => 'Root']);
            }

            file_put_contents($logDirectory . 'folders.json', json_encode($directories, JSON_PRETTY_PRINT));

            unset($files, $directories, $count);
            $output->writeln("<info> INFO </info> Index created for <comment>[$target]</comment> is successful.");
            return self::SUCCESS;

        } catch (\Exception $exception) {
            $output->writeln('<error>' . $exception->getMessage() . '</error>');
            return self::FAILURE;
        }
    }
}
