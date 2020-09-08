<?php

namespace Magefox\GoogleShopping\Controller\Index;

use Magefox\GoogleShopping\Model\Xmlfeed;
use Magento\Framework\App\ActionInterface;
use Magefox\GoogleShopping\Helper\Data;
use Magento\Framework\Controller\Result\ForwardFactory;

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
     * @var \Magefox\GoogleShopping\Helper\Data
     */
    private $resultForward;

    public function __construct(
        Xmlfeed $xmlFeed,
        Data $helper,
        ForwardFactory $resultForwardFactory
    ) {
        $this->xmlFeed = $xmlFeed;
        $this->helper = $helper;
        $this->resultForwardFactory = $resultForwardFactory;
    }

    public function execute()
    {
        $resultForward = $this->resultForwardFactory->create();

        if (!empty($this->helper->getConfig('enabled'))) {
            //phpcs:ignore
            echo $this->xmlFeed->getFeedFile();
        } else {
            $resultForward->forward('noroute');
        }
    }
}
