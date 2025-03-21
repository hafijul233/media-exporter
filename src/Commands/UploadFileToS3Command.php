<?php

namespace Amplify\MediaExporter\Commands;

use Aws\S3\S3Client;
use Exception;
use League\Flysystem\AwsS3V3\AwsS3V3Adapter;
use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class UploadFileToS3Command extends Command
{
    private Filesystem $s3;

    public function __construct(?string $name = null)
    {
        parent::__construct($name);

        $client = new S3Client([
            'version' => 'latest',
            'region' => $_ENV['S3_DEFAULT_REGION'],
            'credentials' => [
                'key' => $_ENV['S3_ACCESS_KEY_ID'],
                'secret' => $_ENV['S3_SECRET_ACCESS_KEY'],
            ],
            "http" => [
                "verify" => false,
            ]
        ]);

        $this->s3 = new Filesystem(
            new AwsS3V3Adapter(
                $client,
                $_ENV['S3_BUCKET'],
            )
        );
    }

    protected function configure()
    {
        $this->setName('upload-to-s3')
            ->setDescription("Upload all the index file to AS three bucket location.")
            ->addArgument('count', InputArgument::OPTIONAL, 'Index for a Directory to Upload from folders.json', 0)
            ->addArgument('destination', InputArgument::OPTIONAL, 'Index for a Directory to Upload from folders.json', null);
    }

    /**
     * Execute the console command.
     * @throws FilesystemException
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $dataDirectory = __DIR__ . '/../../data/entry';
        $completeDirectory = __DIR__ . '/../../data/complete';
        $failedDirectory = __DIR__ . '/../../data/failed';
        $folderIndexPath = __DIR__ . '/../../data/folders.json';
        $prefix = $input->getArgument('destination');

        if ($prefix == null) {
            $prefix = $_ENV['S3_ROOT_PATH'];
        }

        try {

            $target = $input->getArgument('count');

            if (!file_exists($folderIndexPath)) {
                throw new \InvalidArgumentException("<error> ERROR </error> Unable to locate folder.json file at <comment>[$folderIndexPath]</comment>.");
            }

            $folders = json_decode(file_get_contents($folderIndexPath), true, JSON_THROW_ON_ERROR);

            if (!isset($folders['folders'][$target]) || !file_exists("{$dataDirectory}/{$target}.txt")) {
                throw new \ErrorException("<error> ERROR </error> Target index <comment>[{$dataDirectory}/{$target}.txt]</comment> does not exist");
            }

            $entryFileHandler = fopen("{$dataDirectory}/{$target}.txt", "r") or throw new \ErrorException("<error> ERROR </error> Unable to read file at <comment>[{$dataDirectory}/{$target}.txt]</comment>");
            $completeFileHandler = fopen("{$completeDirectory}/{$target}.txt", "a+") or throw new \ErrorException("<error> ERROR </error> Unable to read file at <comment>[{$completeDirectory}/{$target}.txt]</comment>");
            $failedFileHandler = fopen("{$failedDirectory}/{$target}.txt", "a+") or throw new \ErrorException("<error> ERROR </error> Unable to read file at <comment>[{$failedDirectory}/{$target}.txt]</comment>");

            $s3Url = "https://{$_ENV['S3_BUCKET']}.s3.{$_ENV['S3_DEFAULT_REGION']}.amazonaws.com/{$prefix}";

            ($target == '0')
                ? $output->writeln("<info>INFO </info>Uploading from <comment>[{$folders['root']}]</comment> to <comment>[{$s3Url}]</comment>:")
                : $output->writeln("<info>INFO </info>Uploading from <comment>[{$folders['root']}" . DIRECTORY_SEPARATOR . "{$folders['folders'][$target]['folder']}]</comment> to <comment>[{$s3Url}]</comment>:");

            while (!feof($entryFileHandler)) {

                $sourcePath = trim(fgets($entryFileHandler));

                if (!empty($sourcePath)) {

                    $relativePath = str_replace($folders['root'], '', $sourcePath);

                    $relativePath = str_replace('\\', '/', ltrim($relativePath, '\\/'));

                    $targetPath = "{$prefix}/{$relativePath}";
                    $targetUrl = "{$s3Url}/{$targetPath}";

                    if ($this->s3->fileExists($targetPath)) {
                        fwrite($completeFileHandler, "SKIP {$sourcePath}\n");
                        $output->writeln("<fg=yellow>SKIP </>URL <comment>[{$targetUrl}]</comment> already exist.");
                        continue;
                    }

                    $stream = fopen($sourcePath, 'r') or throw new \ErrorException("<error> ERROR </error> Unable to read file at <comment>[{$sourcePath}]</comment>");
                    $this->s3->writeStream($targetPath, $stream);
                    fclose($stream);
                    fwrite($completeFileHandler, "DONE {$sourcePath}\n");
                    $output->writeln("<info>DONE </info>URL <comment>[{$targetUrl}]</comment> uploaded.");
                }
            }

            fclose($entryFileHandler);
            fclose($completeFileHandler);
            fclose($failedFileHandler);

            $output->writeln("<info>DONE </info>Uploading from <comment>[{$folders['root']}]</comment> completed.");

            return self::SUCCESS;

        } catch (Exception $exception) {
            $output->writeln('<error>' . $exception->getMessage() . '</error>');
            return self::FAILURE;
        }
    }
}
