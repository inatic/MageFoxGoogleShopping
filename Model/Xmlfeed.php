<?php

namespace Magefox\GoogleShopping\Model;

use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory;
use Magefox\GoogleShopping\Helper\Data;
use Magefox\GoogleShopping\Helper\Products;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\App\Filesystem\DirectoryList;

class Xmlfeed
{
    /**
     * General Helper
     *
     * @var \Magefox\GoogleShopping\Helper\Data
     */
    private $_helper;

    /**
     * Product Helper
     *
     * @var \Magefox\GoogleShopping\Helper\Products
     */
    private $_productFeedHelper;

    /**
     * Store Manager
     *
     * @var \Magefox\GoogleShopping\Helper\Products
     */
    private $_storeManager;

    public function __construct(
        Data $helper,
        Products $productFeedHelper,
        StoreManagerInterface $storeManager,
        DirectoryList $directoryList,
        CollectionFactory $categoryCollection
    ) {
        $this->_helper = $helper;
        $this->_productFeedHelper = $productFeedHelper;
        $this->_storeManager = $storeManager;
        $this->directoryList = $directoryList;
        $this->categoryCollection = $categoryCollection;
    }

    public function getFeed()
    {
        $xml = $this->getXmlHeader();
        $xml .= $this->getProductsXml();
        $xml .= $this->getXmlFooter();

        return $xml;
    }

    public function getFeedFile()
    {
        $pubDir = $this->directoryList->getPath(\Magento\Framework\App\Filesystem\DirectoryList::PUB);

        $fileName = "googleshopping.xml";
        //phpcs:ignore
        $fileData = file_get_contents($fileName);

        return $fileData;
    }

    public function getXmlHeader()
    {
        //phpcs:ignore
        header("Content-Type: application/xml; charset=utf-8");

        $xml =  '<rss version="2.0" xmlns:g="http://base.google.com/ns/1.0">';
        $xml .= '<channel>';
        $xml .= '<title>'.$this->_helper->getConfig('google_default_title').'</title>';
        $xml .= '<link>'.$this->_helper->getConfig('google_default_url').'</link>';
        $xml .= '<description>'.$this->_helper->getConfig('google_default_description').'</description>';

        return $xml;
    }

    public function getXmlFooter()
    {
        return  '</channel></rss>';
    }

    public function getProductsXml()
    {
        $productCollection = $this->_productFeedHelper->getFilteredProducts();
        $xml = "";

        foreach ($productCollection as $product) {
            if (!empty($product->getData('ean')) && $product->getImage()!=="no_selection") {
                $xml .= "<item>".$this->buildProductXml($product)."</item>";
            }
        }

        return $xml;
    }

    public function buildProductXml($product)
    {
        $storeId = 21;
        $_description = $this->fixDescription($product->getShortDescription());
        $xml = $this->createNode("title", $product->getName(), true);
        $xml .= $this->createNode(
            "link",
            $product->setStoreId($storeId)->getUrlModel()->getUrlInStore($product, ['_escape' => true])
        );
        $xml .= $this->createNode("description", $_description, true);
        //$xml .= $this->createNode("g:product_type", $this->_productFeedHelper->getAttributeSet($product), true);
        $xml .= $this->createNode(
            "g:image_link",
            $this->_storeManager->getStore()->getBaseUrl(
                \Magento\Framework\UrlInterface::URL_TYPE_MEDIA,
                true
            ) . 'catalog/product' . $product->getImage()
        );
        $xml .= $this->createNode(
            'g:google_product_category',
            $this->_productFeedHelper->getProductValue($product, 'google_product_category'),
            true
        );
        $xml .= $this->createNode("g:availability", 'in stock');
        $xml .= $this->createNode(
            'g:price',
            number_format(
                $product->getFinalPrice(),
                2,
                '.',
                ''
            ).' '.$this->_productFeedHelper->getCurrentCurrencySymbol()
        );
        if (($product->getSpecialPrice() < $product->getFinalPrice()) && !empty($product->getSpecialPrice())) {
            $xml .= $this->createNode(
                'g:sale_price',
                number_format(
                    $product->getSpecialPrice(),
                    2,
                    '.',
                    ''
                ).' '.$this->_productFeedHelper->getCurrentCurrencySymbol()
            );
        }
        $_condition = $this->_productFeedHelper->getProductValue($product, 'google_condition');
        if (is_array($_condition)) {
            $xml .= $this->createNode("g:condition", $_condition[0]);
        } elseif ($_condition === "Refurbished") {
            $xml .= $this->createNode("g:condition", "refurbished");
        } else {
            $xml .= $this->createNode("g:condition", $this->_helper->getConfig('default_google_condition'));
        }
        $xml .= $this->createNode("g:gtin", $product->getData('ean'));
        $xml .= $this->createNode("g:id", $product->getId());
        $xml .= $this->createNode("g:brand", $product->getAttributeText('brand'));
        $xml .= $this->createNode("g:mpn", $product->getData('mpn'));
        $xml .= $this->createNode("g:product_type", $this->getProductCategories($product), true);

        return $xml;
    }

    public function fixDescription($data)
    {
        $description = $data;
        $encode = mb_detect_encoding($data);
        $description = mb_convert_encoding($description, 'UTF-8', $encode);

        return $description;
    }

    public function createNode($nodeName, $value, $cData = false)
    {
        if (empty($value) || empty($nodeName)) {
            return false;
        }

        $cDataStart = "";
        $cDataEnd = "";

        if ($cData === true) {
            $cDataStart = "<![CDATA[";
            $cDataEnd = "]]>";
        }

        $node = "<".$nodeName.">".$cDataStart.$value.$cDataEnd."</".$nodeName.">";

        return $node;
    }

    public function getFilteredCollection($categoryIds)
    {
        $collection = $this->categoryCollection->create();
        $filtered_colection = $collection
            ->addFieldToSelect('*')
            ->addFieldToFilter(
                'entity_id',
                ['in' => $categoryIds]
            )
            ->setOrder('level', 'ASC')
            ->load();
        return $filtered_colection;
    }

    private function getProductCategories($product)
    {
        $categoryIds = $product->getCategoryIds();
        $categoryCollection = $this->getFilteredCollection($categoryIds);
        $fullcategory = "";
        $i = 0;
        foreach ($categoryCollection as $category) {
            $i++;
            if ($i != $categoryCollection->getSize()) {
                $fullcategory .= $category->getData('name') . ' > ';
            } else {
                $fullcategory .= $category->getData('name');
            }
        }
        return $fullcategory;
    }
}
