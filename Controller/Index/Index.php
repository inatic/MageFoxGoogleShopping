<?php

namespace Magefox\GoogleShopping\Controller\Index;

use Magefox\GoogleShopping\Model\Xmlfeed;
use Magefox\GoogleShopping\Helper\Data;
use Magento\Framework\Controller\Result\ForwardFactory;
use Magento\Framework\Controller\Result\RawFactory;
use Magento\Framework\App\ActionInterface;

class Index implements ActionInterface
{
    /**
     * XmlFeed Model
     *
     * @var \Magefox\GoogleShopping\Model\Xmlfeed
     */
    protected $xmlFeed;

    /**
     * General Helper
     *
     * @var \Magefox\GoogleShopping\Helper\Data
     */
    private $helper;

    /**
     * Result Forward Factory
     *
     * @var \Magento\Framework\Controller\Result\ForwardFactory
     */
    private $resultForwardFactory;

    public function __construct(
        Xmlfeed $xmlFeed,
        Data $helper,
        ForwardFactory $resultForwardFactory,
        RawFactory $resultRawFactory
    ) {
        $this->xmlFeed = $xmlFeed;
        $this->helper = $helper;
        $this->resultForwardFactory = $resultForwardFactory;
        $this->resultRawFactory = $resultRawFactory;
    }

    public function execute()
    {
        $resultForward = $this->resultForwardFactory->create();
        $resultRaw = $this->resultRawFactory->create();

        if (!empty($this->helper->getConfig('enabled'))) {
            $resultRaw->setHeader('Content-Type', 'text/xml');
            $resultRaw->setContents($this->xmlFeed->getFeedFile());
            return $resultRaw;
        }
        return $resultForward->forward('noroute');
    }
}
