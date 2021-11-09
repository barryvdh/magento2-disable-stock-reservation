<?php

namespace Ampersand\DisableStockReservation\Test\Integration;

use PHPUnit\Framework\TestCase;
use Magento\TestFramework\ObjectManager;
use Magento\Store\Model\ResourceModel\Group;
use Magento\Store\Model\ResourceModel\Store;
use Magento\Store\Model\ResourceModel\Website;
use Magento\Store\Model\GroupFactory;
use Magento\Store\Model\StoreFactory;
use Magento\Store\Model\WebsiteFactory;
use Magento\InventoryApi\Api\Data\SourceInterfaceFactory;
use Magento\CatalogInventory\Model\StockFactory;
use Magento\CatalogInventory\Api\Data\StockInterfaceFactory;
use Magento\InventoryApi\Api\Data\StockSourceLinkInterfaceFactory;
use Magento\Catalog\Model\ProductFactory;
use Magento\InventoryApi\Api\Data\SourceItemInterface;
use Magento\InventoryApi\Api\SourceItemsSaveInterface;

class MultipleSourceOrder extends TestCase
{
    /** @var ObjectManager $objectManager */
    private $objectManager;
    /**
     * @var WebsiteFactory
     */
    private $websiteFactory;
    /**
     * @var StoreFactory
     */
    private $storeFactory;
    /**
     * @var GroupFactory
     */
    private $groupFactory;

    /**
     * @var SourceInterfaceFactory
     */
    private $sourceInterfaceFactory;

    /**
     * @var StockFactory
     */
    private $stockFactory;

    /**
     * @var StockSourceLinkInterfaceFactory
     */
    private $stockSourceLinkFactory;

    /**
     * @var ProductFactory
     */
    private $productFactory;

    /**
     * @var SourceItemInterface
     */
    private $sourceItemInterface;

    public function setUp(): void
    {
        $this->websiteFactory = $this->objectManager->get(WebsiteFactory::class);
        $this->groupFactory = $this->objectManager->get(GroupFactory::class);
        $this->storeFactory = $this->objectManager->get(StoreFactory::class);
        $this->sourceInterfaceFactory = $this->objectManager->get(SourceInterfaceFactory::class);
        $this->stockFactory = $this->objectManager->get(StockFactory::class);
        $this->stockSourceLinkFactory = $this->objectManager->get(StockSourceLinkInterfaceFactory::class);
        $this->productFactory = $this->objectManager->get(ProductFactory::class);

        $website_uk = $this->createWebsite('uk_website','Uk Website');
        $group_uk = $this->createGroup($website_uk, 'Uk Group','uk_group', 1);
        $store_uk = $this->createStoreView('uk_store','Uk store view', $group_uk, $website_uk, '1');

        $website_nl = $this->createWebsite('nl_website','NL Website');
        $group_nl = $this->createGroup($website_nl, 'NL Group','nl_group', 1);
        $store_nl = $this->createStoreView('nl_store','Nl store view', $group_nl, $website_nl, '1');

        $source_uk = $this->createSource('Uk Source', 'uk_source', 1,'11111','1');
        $source_nl = $this->createSource('Nl Source', 'nl_source', 1,'22222','2');

        $stock_uk = $this->createStock('Uk Stock', $website_uk->getId());
        $stock_nl = $this->createStock('Nl Stock', $website_nl->getId());

        $stockSourceLink_uk = $this->linkStockAndSource($source_uk->getSourceCode(), $stock_uk->getId(), 1);
        $stockSourceLink_nl = $this->linkStockAndSource($source_uk->getSourceCode(), $stock_uk->getId(), 1);

    }

    public function createWebsite($code, $name) {
        $website = $this->websiteFactory->create();
        $website->setCode($code);
        $website->setName($name);

        return $website;
    }

    public function createGroup($website, $name, $code, $rootCategory) {
        $group = $this->groupFactory->create();
        $group->setWebsite($website);
        $group->setName($name);
        $group->setCode($code);
        $group->setRootCategoryId($rootCategory);

        return $group;
    }

    public function createStoreView($code, $name, $group, $website, $status) {
        $store = $this->storeFactory->create();
        $store->setCode($code);
        $store->setName($name);
        $store->setWebsite($website);
        $store->setGroup($group);
        $store->setData('is_active', $status);

        return $store;
    }

    public function createSource($name, $sourceCode, $status, $postcode, $countryId) {
        $source= $this->sourceInterfaceFactory->create();
        $source->setName($name);
        $source->setSourceCode($sourceCode);
        $source->isEnabled($status);
        $source->setPostcode($postcode);
        $source->setCountryId($countryId);

        return $source;
    }

    public function createStock($name, $websiteId) {
        $stock = $this->stockFactory->create();
        $stock->setStockName($name);
        $stock->setWebsiteId($websiteId);

        return $stock;
    }

    public function linkStockAndSource($sourceCode,$stockId,$priority)
    {
        $stockSourceLink = $this->stockSourceLinkFactory->create();
        $stockSourceLink->setSourceCode($sourceCode);
        $stockSourceLink->setStockId($stockId);
        $stockSourceLink->setPriority($priority);

        return $stockSourceLink;
    }

    public function createProduct() {
        $product = $this->productFactory->create();
        $product->setSku('test-sku'); // Set your sku here
        $product->setName('Test Simple Product'); // Name of Product
        $product->setAttributeSetId(4); // Attribute set id
        $product->setStatus(1); // Status on product enabled/ disabled 1/0
        $product->setWeight(10); // weight of product
        $product->setVisibility(4); // visibilty of product (catalog / search / catalog, search / Not visible individually)
        $product->setTaxClassId(0); // Tax class id
        $product->setTypeId('simple'); // type of product (simple/virtual/downloadable/configurable)
        $product->setPrice(100); // price of product
        $product->setStockData(
            array(
                'use_config_manage_stock' => 0,
                'manage_stock' => 1,
                'is_in_stock' => 1,
                'qty' => 100
            )
        );
        return $product;
    }

    public function assignProductToSource($firstSourceCode, $secondSourceCode) {
        $sourceItem1 = $this->objectManager->create('Magento\InventoryApi\Api\Data\SourceItemInterface');
        $sourceItem2 = $this->objectManager->create('Magento\InventoryApi\Api\Data\SourceItemInterface');
        $product = $this->createProduct();

        $sourceItem1 = $this->sourceItemInterface->setSku($product->getSku());
        $sourceItem1->setSourceCode($firstSourceCode);
        $sourceItem1->setQuantity(40);
        $sourceItem1->setStatus(1);

        $sourceItem2 = $this->sourceItemInterface->setSku($product->getSku());
        $sourceItem2->setSourceCode($secondSourceCode);
        $sourceItem2->setQuantity(60);
        $sourceItem2->setStatus(1);

        $sourceItemSave = $this->objectManager->create('\Magento\InventoryApi\Api\SourceItemsSaveInterface');
        $sourceItemSave->execute([ $sourceItem1, $sourceItem2]);
    }
}
