<?php

namespace Magefox\GoogleShopping\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Eav\Model\AttributeSetRepository;
use Magefox\GoogleShopping\Helper\Data;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\Product\Visibility;

class Products extends AbstractHelper
{
    /**
     * @var \Magento\Catalog\Model\ResourceModel\Product\CollectionFactory
     */
    protected $_productCollectionFactory;

    /**
     * @var \Magento\Eav\ModelAttributeSetRepository
     */
    protected $_attributeSetRepo;

    /**
     * @var \Magefox\GoogleShopping\Helper\Data
     */
    protected $_helper;

    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    public $_storeManager;

    /**
     * @var \Magento\Catalog\Model\Product\Attribute\Source\Status
     */
    public $_productStatus;

    /**
     * @var \Magento\Catalog\Model\Product\Visibility
     */
    public $_productVisibility;

    public function __construct(
        Context $context,
        CollectionFactory $productCollectionFactory,
        AttributeSetRepository $attributeSetRepo,
        Data $helper,
        StoreManagerInterface $storeManager,
        Status $productStatus,
        Visibility $productVisibility
    ) {
        $this->_productCollectionFactory = $productCollectionFactory;
        $this->_attributeSetRepo = $attributeSetRepo;
        $this->_helper = $helper;
        $this->_storeManager = $storeManager;
        $this->_productStatus = $productStatus;
        $this->_productVisibility = $productVisibility;
        parent::__construct($context);
    }

    public function getFilteredProducts()
    {
        $collection = $this->_productCollectionFactory->create();
        // $collection->addAttributeToFilter('status',\Magento\Catalog\Model\Product\Attribute\Source\Status::STATUS_ENABLED);
        $collection->addAttributeToSelect('*');
        $collection->addAttributeToFilter('status', ['in' => $this->_productStatus->getVisibleStatusIds()]);
        $collection->addAttributeToFilter('visibility', ['eq' => $this->_productVisibility::VISIBILITY_BOTH]);
        $collection->setVisibility($this->_productVisibility->getVisibleInSiteIds());

        return $collection;
    }

    public function getAttributeSet($product)
    {
        $attributeSetId = $product->getAttributeSetId();
        $attributeSet = $this->_attributeSetRepo->get($attributeSetId);

        return $attributeSet->getAttributeSetName();
    }

    public function getProductValue($product, $attributeCode)
    {
        $attributeCodeFromConfig = $this->_helper->getConfig($attributeCode.'_attribute');
        $defaultValue = $this->_helper->getConfig('default_'.$attributeCode);

        if (!empty($attributeCodeFromConfig)) {
            return $product->getAttributeText($attributeCodeFromConfig);
        }

        if (!empty($defaultValue)) {
            return $defaultValue;
        }

        return false;
    }

    public function getCurrentCurrencySymbol()
    {
        return $this->_storeManager->getStore()->getCurrentCurrencyCode();
    }
}
