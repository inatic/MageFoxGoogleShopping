<?php
namespace Magefox\GoogleShopping\Cron;

use Psr\Log\LoggerInterface;
use Magento\Framework\File\Csv;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem;
use Magefox\GoogleShopping\Model\Xmlfeed;

class GenerateFile
{
    protected $logger;

    public function __construct(
        LoggerInterface $logger,
        Csv $csvProcessor,
        DirectoryList $directoryList,
        Filesystem $filesystem,
        Xmlfeed $xmlFeed
    ) {
        $this->logger = $logger;
        $this->filesystem = $filesystem;
        $this->directoryList = $directoryList;
        $this->csvProcessor = $csvProcessor;
        $this->xmlFeed = $xmlFeed;
    }

   /**
    * Sync all products assigned to 'axitech' source to PCRT
    *
    * @return void
    */
    public function execute()
    {
        try {
            $fileDirectoryPath = $this->filesystem->getDirectoryWrite(
                \Magento\Framework\App\Filesystem\DirectoryList::PUB
            );
            $fileName = 'googleshopping.xml';

            $xmldata = $this->xmlFeed->getFeed();
            $fileDirectoryPath->writeFile($fileName, $xmldata);
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
        }
    }
}
