<?php

namespace Magefox\GoogleShopping\Model;

use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory;
use Magefox\GoogleShopping\Helper\Data;
use Magefox\GoogleShopping\Helper\Products;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Tax\Api\TaxCalculationInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

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

    /**
     * Category Collection
     *
     * @var \Magento\Catalog\Model\ResourceModel\Category\Collection
     */
    private $categoryCollection;

    public function __construct(
        Data $helper,
        Products $productFeedHelper,
        StoreManagerInterface $storeManager,
        CollectionFactory $categoryCollection,
        TaxCalculationInterface $taxCalculation,
        ScopeConfigInterface $scopeConfig
    ) {
        $this->helper = $helper;
        $this->productFeedHelper = $productFeedHelper;
        $this->storeManager = $storeManager;
        $this->categoryCollection = $categoryCollection;
        $this->taxCalculation = $taxCalculation;
        $this->scopeConfig = $scopeConfig;
    }

    public function getFeed(): string
    {
        $xml = $this->getXmlHeader();
        $xml .= $this->getProductsXml();
        $xml .= $this->getXmlFooter();

        return $xml;
    }

    public function getFeedFile(): string
    {
        $fileName = "googleshopping.xml";
        $xml = file_get_contents($fileName); //phpcs:ignore
        // commented out for testing
        //if (strlen($xml) < 500) {
            $xml = $this->getFeed();
        //}
        return $xml;
    }

    public function getXmlHeader(): string
    {
        header("Content-Type: application/xml; charset=utf-8"); //phpcs:ignore

        $xml =  '<rss version="2.0" xmlns:g="http://base.google.com/ns/1.0">';
        $xml .= '<channel>';
        $xml .= '<title>'.$this->helper->getConfig('google_default_title').'</title>';
        $xml .= '<link>'.$this->helper->getConfig('google_default_url').'</link>';
        $xml .= '<description>'.$this->helper->getConfig('google_default_description').'</description>';

        return $xml;
    }

    public function getXmlFooter(): string
    {
        return  '</channel></rss>';
    }

    public function getProductsXml(): string
    {
        $productCollection = $this->productFeedHelper->getFilteredProducts();
        $xml = "";

        foreach ($productCollection as $product) {
            if ($this->isValidProduct($product)) {
                $xml .= "<item>".$this->buildProductXml($product)."</item>";
            }
        }

        return $xml;
    }

    private function isValidProduct($product): bool
    {
        if ($product->getImage() === "no_selection"
            || (string) $product->getImage() === ""
            || $product->getVisibility() === Visibility::VISIBILITY_NOT_VISIBLE
        ) {
            return false;
        }
        if (empty($product->getData('ean'))) {
                return false;
        }

        return true;
    }

    public function buildProductXml($product): string
    {
        $storeId = 1;

        $xml = $this->createNode("title", $product->getName(), true);
        $xml .= $this->createNode("link", $product->getProductUrl());
        //$xml .= $this->createNode(
        //    "link",
        //    $product->setStoreId($storeId)->getUrlModel()->getUrlInStore($product, ['_escape' => true])
        //);
        $_description = $this->fixDescription($product->getDescription());
        $xml .= $this->createNode("description", $_description, true);
        //$xml .= $this->createNode("g:product_type", $this->productFeedHelper->getAttributeSet($product), true);
        $xml .= $this->createNode(
            "g:image_link",
            $this->storeManager->getStore()->getBaseUrl(
                \Magento\Framework\UrlInterface::URL_TYPE_MEDIA,
                true
            ) . 'catalog/product' . $product->getImage()
        );
        //$xml .= $this->createNode(
        //    'g:google_product_category',
        //    $this->productFeedHelper->getProductValue($product, 'google_product_category'),
        //    true
        //);
        $xml .= $this->createNode("g:availability", $this->isInStock($product));
        // tax calculation
        if ($taxAttribute = $product->getTaxClassId()) {
            $productRateId = (int) $taxAttribute;
        }
        $rate = $this->taxCalculation->getCalculatedRate($productRateId);
        if ((int) $this->scopeConfig->getValue('tax/calculation/price_includes_tax', ScopeInterface::SCOPE_STORE) === 1) {
            // Product price in catalog is including tax.
            $regularPriceExcludingTax = $product->getPriceInfo()->getPrice('regular_price')->getValue() / (1 + ($rate / 100));
            $specialPriceExcludingTax = $product->getPriceInfo()->getPrice('final_price')->getValue() / (1 + ($rate / 100));
        } else {
            // Product price in catalog is excluding tax.
            $regularPriceExcludingTax = $product->getPriceInfo()->getPrice('regular_price')->getValue();
            $specialPriceExcludingTax = $product->getPriceInfo()->getPrice('final_price')->getValue();
        }
        $regularPriceIncludingTax = $regularPriceExcludingTax * (1 + $rate / 100);
        $specialPriceIncludingTax = $specialPriceExcludingTax * (1 + $rate / 100);
        //$regularPrice = $product->getPriceInfo()->getPrice('regular_price')->getValue();
        //$specialPrice = $product->getPriceInfo()->getPrice('special_price')->getValue();
        $regularPrice = $regularPriceIncludingTax;
        $specialPrice = $specialPriceIncludingTax;

        $xml .= $this->createNode(
            'g:price',
            number_format(
                $regularPrice,
                2,
                '.',
                ''
            ).' '.$this->productFeedHelper->getCurrentCurrencySymbol()
        );
        if (($specialPrice < $regularPrice) && !empty($specialPrice)) {
            $xml .= $this->createNode(
                'g:sale_price',
                number_format(
                    $specialPrice,
                    2,
                    '.',
                    ''
                ).' '.$this->productFeedHelper->getCurrentCurrencySymbol()
            );
        }
        //$xml .= $this->createNode("g:condition", $this->getCondition($product));
        //Unique identifier Logic
        //EAN and MPN are both unique identifiers, but only check EAN since MPN always exists
        if (!empty($product->getData('ean'))) {
            $xml .= $this->createNode("g:gtin", $product->getData('ean'));
        //    $xml .= $this->createNode("g:mpn", $product->getData('mpn'));
        //} elseif ($this->getCondition($product) === 'refurbished') {
        //    $xml .= $this->createNode("g:mpn", $product->getData('mpn'));
        } else {
            $xml .= $this->createNode("g:identifier_exists", 'false');
        }

        $xml .= $this->createNode("g:id", $product->getId());
        $xml .= $this->createNode("g:brand", $product->getAttributeText('merk'));
        $xml .= $this->createNode("g:color", ucfirst($product->getAttributeText('kleur')));
        $xml .= $this->createNode("g:product_type", $this->getProductCategories($product), true);
        $xml .= $this->createNode("g:custom_label_0", $this->getProductCategories($product), true);

        return $xml;
    }

    private function isInStock($product): string
    {
        $inStock = 'out of stock';
        if ($product->isSaleable()) {
            $inStock = 'in stock';
        }
        return $inStock;
    }

    private function getCondition($product)
    {
        $_condition = $this->productFeedHelper->getProductValue($product, 'google_condition');
        if (is_array($_condition)) {
            $condition = $_condition[0];
        } elseif ($_condition === "Refurbished") {
            $condition = "refurbished";
        } else {
            $condition = $this->helper->getConfig('default_google_condition');
        }
        return $condition;
    }

    public function fixDescription($data): string
    {
        $description = $data;
        $encode = mb_detect_encoding($data);
        return mb_convert_encoding($description, 'UTF-8', $encode);
    }

    public function createNode(string $nodeName, string $value, bool $cData = false): string
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

    public function getFilteredCollection(array $categoryIds)
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

    private function getProductCategories($product): string
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
