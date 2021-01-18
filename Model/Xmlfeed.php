<?php

namespace Magefox\GoogleShopping\Model;

use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory;
use Magefox\GoogleShopping\Helper\Data;
use Magefox\GoogleShopping\Helper\Products;
use Magento\Store\Model\StoreManagerInterface;

class Xmlfeed
{
    /**
     * General Helper
     *
     * @var \Magefox\GoogleShopping\Helper\Data
     */
    private $helper;

    /**
     * Product Helper
     *
     * @var \Magefox\GoogleShopping\Helper\Products
     */
    private $productFeedHelper;

    /**
     * Store Manager
     *
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    private $storeManager;

    public function __construct(
        Data $helper,
        Products $productFeedHelper,
        StoreManagerInterface $storeManager,
        CollectionFactory $categoryCollection
    ) {
        $this->helper = $helper;
        $this->productFeedHelper = $productFeedHelper;
        $this->storeManager = $storeManager;
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
        $fileName = "googleshopping.xml";

        return file_get_contents($fileName); //phpcs:ignore
    }

    public function getXmlHeader()
    {
        header("Content-Type: application/xml; charset=utf-8"); //phpcs:ignore

        $xml =  '<rss version="2.0" xmlns:g="http://base.google.com/ns/1.0">';
        $xml .= '<channel>';
        $xml .= '<title>'.$this->helper->getConfig('google_default_title').'</title>';
        $xml .= '<link>'.$this->helper->getConfig('google_default_url').'</link>';
        $xml .= '<description>'.$this->helper->getConfig('google_default_description').'</description>';

        return $xml;
    }

    public function getXmlFooter()
    {
        return  '</channel></rss>';
    }

    public function getProductsXml()
    {
        $productCollection = $this->productFeedHelper->getFilteredProducts();
        $xml = "";

        foreach ($productCollection as $product) {
            if (!empty($product->getData('ean'))
            && $product->getImage()!=="no_selection"
            && $product->getImage()!=="") {
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
        //$xml .= $this->createNode("g:product_type", $this->productFeedHelper->getAttributeSet($product), true);
        $xml .= $this->createNode(
            "g:image_link",
            $this->storeManager->getStore()->getBaseUrl(
                \Magento\Framework\UrlInterface::URL_TYPE_MEDIA,
                true
            ) . 'catalog/product' . $product->getImage()
        );
        $xml .= $this->createNode(
            'g:google_product_category',
            $this->productFeedHelper->getProductValue($product, 'google_product_category'),
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
            ).' '.$this->productFeedHelper->getCurrentCurrencySymbol()
        );
        if (($product->getSpecialPrice() < $product->getFinalPrice()) && !empty($product->getSpecialPrice())) {
            $xml .= $this->createNode(
                'g:sale_price',
                number_format(
                    $product->getSpecialPrice(),
                    2,
                    '.',
                    ''
                ).' '.$this->productFeedHelper->getCurrentCurrencySymbol()
            );
        }
        $_condition = $this->productFeedHelper->getProductValue($product, 'google_condition');
        if (is_array($_condition)) {
            $xml .= $this->createNode("g:condition", $_condition[0]);
        } elseif ($_condition === "Refurbished") {
            $xml .= $this->createNode("g:condition", "refurbished");
        } else {
            $xml .= $this->createNode("g:condition", $this->helper->getConfig('default_google_condition'));
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
        return mb_convert_encoding($description, 'UTF-8', $encode);
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

        return "<".$nodeName.">".$cDataStart.$value.$cDataEnd."</".$nodeName.">";
    }

    public function getFilteredCollection($categoryIds)
    {
        $collection = $this->categoryCollection->create();
        return $collection
            ->addFieldToSelect('*')
            ->addFieldToFilter(
                'entity_id',
                ['in' => $categoryIds]
            )
            ->setOrder('level', 'ASC')
            ->load();
    }

    private function getProductCategories($product)
    {
        $categoryIds = $product->getCategoryIds();
        $categoryCollection = $this->getFilteredCollection($categoryIds);
        $fullcategory = "";
        $i = 0;
        foreach ($categoryCollection as $category) {
            $i++;
            if ($i !== (int) $categoryCollection->getSize()) {
                $fullcategory .= $category->getData('name') . ' > ';
            } else {
                $fullcategory .= $category->getData('name');
            }
        }
        return $fullcategory;
    }
}
