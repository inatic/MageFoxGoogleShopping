<?php

namespace Magefox\GoogleShopping\Controller\Index;

use Magefox\GoogleShopping\Model\Xmlfeed;
use Magefox\GoogleShopping\Helper\Data;
use Magento\Framework\Controller\Result\ForwardFactory;
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

    public function execute(): void
    {
        $resultForward = $this->resultForwardFactory->create();

        if (!empty($this->helper->getConfig('enabled'))) {
            echo $this->xmlFeed->getFeedFile(); //phpcs:ignore
        } else {
            $resultForward->forward('noroute');
        }
    }
}
