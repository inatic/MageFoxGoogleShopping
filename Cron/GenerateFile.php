<?php
namespace Magefox\GoogleShopping\Cron;

use Psr\Log\LoggerInterface;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem;
use Magefox\GoogleShopping\Model\Xmlfeed;

class GenerateFile
{
    protected $logger;

    public function __construct(
        LoggerInterface $logger,
        Filesystem $filesystem,
        Xmlfeed $xmlFeed
    ) {
        $this->logger = $logger;
        $this->filesystem = $filesystem;
        $this->xmlFeed = $xmlFeed;
    }

   /**
    * Generate XML feed on cron schedule
    *
    * @return void
    */
    public function execute(): void
    {
        try {
            $fileDirectoryPath = $this->filesystem->getDirectoryWrite(DirectoryList::PUB);
            $fileName = 'googleshopping.xml';

            $xmldata = $this->xmlFeed->getFeed();
            $fileDirectoryPath->writeFile($fileName, $xmldata);
        } catch (\Exception $exception) {
            $this->logger->error($exception->getMessage());
        }
    }
}
