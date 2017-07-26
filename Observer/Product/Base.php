<?php
/**
 * Copyright (c) 2017, Nosto Solutions Ltd
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without modification,
 * are permitted provided that the following conditions are met:
 *
 * 1. Redistributions of source code must retain the above copyright notice,
 * this list of conditions and the following disclaimer.
 *
 * 2. Redistributions in binary form must reproduce the above copyright notice,
 * this list of conditions and the following disclaimer in the documentation
 * and/or other materials provided with the distribution.
 *
 * 3. Neither the name of the copyright holder nor the names of its contributors
 * may be used to endorse or promote products derived from this software without
 * specific prior written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND
 * ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
 * WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
 * DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT HOLDER OR CONTRIBUTORS BE LIABLE FOR
 * ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
 * (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 * LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON
 * ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS
 * SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * @author Nosto Solutions Ltd <contact@nosto.com>
 * @copyright 2017 Nosto Solutions Ltd
 * @license http://opensource.org/licenses/BSD-3-Clause BSD 3-Clause
 *
 */

namespace Nosto\Tagging\Observer\Product;

use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\ProductRepository;
use Magento\ConfigurableProduct\Model\ResourceModel\Product\Type\Configurable as ConfigurableProduct;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Module\Manager as ModuleManager;
use Magento\Store\Model\Store;
use Nosto\NostoException;
use Nosto\Operation\UpsertProduct;
use Nosto\Request\Http\HttpRequest;
use Nosto\Tagging\Helper\Account as NostoHelperAccount;
use Nosto\Tagging\Helper\Data as NostoHelperData;
use Nosto\Tagging\Helper\Scope as NostoHelperScope;
use Nosto\Tagging\Model\Product\Builder as NostoProductBuilder;
use Psr\Log\LoggerInterface;

abstract class Base implements ObserverInterface
{
    const NEWS_FROM_DATE = 'news_from_date';
    const NEWS_TO_DATE = 'news_to_date';

    private $nostoHelperData;
    private $nostoHelperAccount;
    private $logger;
    private $moduleManager;
    private $configurableProduct;
    private $nostoHelperScope;
    protected $nostoProductBuilder;
    protected $productRepository;

    /**
     * Constructor.
     *
     * @param NostoHelperData $nostoHelperData
     * @param NostoHelperAccount $nostoHelperAccount
     * @param NostoProductBuilder $nostoProductBuilder
     * @param NostoHelperScope $nostoHelperScope
     * @param LoggerInterface $logger
     * @param ModuleManager $moduleManager
     * @param ConfigurableProduct $configurableProduct
     * @param ProductRepository $productRepository
     */
    public function __construct(
        NostoHelperData $nostoHelperData,
        NostoHelperAccount $nostoHelperAccount,
        NostoProductBuilder $nostoProductBuilder,
        NostoHelperScope $nostoHelperScope,
        LoggerInterface $logger,
        ModuleManager $moduleManager,
        ConfigurableProduct $configurableProduct,
        ProductRepository $productRepository
    ) {
        $this->nostoHelperData = $nostoHelperData;
        $this->nostoHelperAccount = $nostoHelperAccount;
        $this->nostoProductBuilder = $nostoProductBuilder;
        $this->logger = $logger;
        $this->moduleManager = $moduleManager;
        $this->configurableProduct = $configurableProduct;
        $this->productRepository = $productRepository;

        HttpRequest::buildUserAgent(
            NostoHelperData::PLATFORM_NAME,
            $nostoHelperData->getPlatformVersion(),
            $nostoHelperData->getModuleVersion()
        );
        $this->nostoHelperScope = $nostoHelperScope;
    }

    /**
     * Event handler for the "catalog_product_save_after" and  event.
     * Sends a product update API call to Nosto.
     *
     * @param Observer $observer
     * @return void
     * @suppress PhanDeprecatedFunction
     */
    public function execute(Observer $observer)
    {
        if ($this->moduleManager->isEnabled(NostoHelperData::MODULE_NAME)) {
            /* @var \Magento\Catalog\Model\Product $product */
            /** @noinspection PhpUndefinedMethodInspection */
            $product = $this->extractProduct($observer);
            if ($this->productPresent($product) && $product instanceof Product) {
                // Figure out if we're updating a parent product
                $parentProducts
                    = $this->configurableProduct->getParentIdsByChild($product->getId());
                if (!empty($parentProducts[0]) && is_numeric($parentProducts[0])) {
                    $parentProduct = $this->productRepository->getById((int)$parentProducts[0]);
                    if ($parentProduct instanceof Product) {
                        $product = $parentProduct;
                    }
                }
                foreach ($product->getStoreIds() as $storeId) {
                    $store = $this->nostoHelperScope->getStore($storeId);
                    $account = $this->nostoHelperAccount->findAccount($store);
                    if ($account === null) {
                        continue;
                    }

                    if (!$this->nostoHelperData->isProductUpdatesEnabled($store)) {
                        continue;
                    }

                    if (!$this->validateProduct($product)) {
                        continue;
                    }

                    $productInStore = $this->productRepository->getById(
                        $product->getId(),
                        false,
                        $storeId,
                        false
                    );
                    // Load the product model for this particular store view.
                    $metaProduct = $this->buildProduct($productInStore, $store);
                    if ($metaProduct === null) {
                        continue;
                    }

                    try {
                        $op = new UpsertProduct($account);
                        $op->addProduct($metaProduct);
                        $this->doRequest($op);
                    } catch (NostoException $e) {
                        $this->logger->error($e->__toString());
                    }
                }
            }
        }
    }

    /**
     * Checks if product is scheduled / staged or currently active one
     *
     * @param Product $product
     * @return bool
     */
    protected function productPresent(Product $product)
    {
        $productPresent = true;
        if ($product->hasData(self::NEWS_FROM_DATE)) {
            $activeFromDateStr = $product->getData(self::NEWS_FROM_DATE);
            $today = new \DateTime("now");
            $activeFromDate = \DateTime::createFromFormat('Y-m-d H:i:s', $activeFromDateStr);

            if ($today > $activeFromDate) {
                if ($product->hasData(self::NEWS_TO_DATE)) {
                    $activeToDateStr = $product->getData(self::NEWS_TO_DATE);
                    $activeToDate = \DateTime::createFromFormat('Y-m-d H:i:s', $activeToDateStr);
                    if ($activeToDate > $today) {
                        $productPresent = false;
                    }
                }
            } else {
                $productPresent = false;
            }
        }

        return $productPresent;
    }

    /**
     * Default method for extracting product from the observer
     * @param Observer $observer
     * @return mixed
     */
    protected function extractProduct(Observer $observer)
    {
        return $observer->getProduct();
    }

    /**
     * Validate whether the event should be handled or not
     *
     * @param Product $product the product from the event
     */
    abstract public function validateProduct(Product $product);

    /**
     * Builds the product object for the operation using the builder
     *
     * @param Product $product the product to be built
     * @param Store $store the store for which to build the product
     * @return \Nosto\Object\Product\Product the built product
     */
    public function buildProduct(Product $product, Store $store)
    {
        return $this->nostoProductBuilder->build($product, $store);
    }

    /**
     * @param UpsertProduct $operation
     * @return mixed
     */
    abstract public function doRequest(UpsertProduct $operation);
}
