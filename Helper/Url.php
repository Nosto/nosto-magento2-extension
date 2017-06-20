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

namespace Nosto\Tagging\Helper;

use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory as CategoryCollectionFactory;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\Store;
use Nosto\Request\Http\HttpRequest;

/**
 * Url helper class for common URL related tasks.
 */
class Url extends AbstractHelper
{
    const MAGENTO_PATH_SEARCH_RESULT = 'catalogsearch/result';
    /**
     * Path to Magento's cart controller
     */
    const MAGENTO_PATH_CART = 'checkout/cart';

    /**
     * The ___store parameter in Magento URLs
     */
    const MAGENTO_URL_PARAMETER_STORE = '___store';

    /**
     * The SID (session id) parameter in Magento URLs
     */
    const MAGENTO_URL_PARAMETER_SID = 'SID';

    /**
     * The array option key for scope in Magento's URLs
     */
    const MAGENTO_URL_OPTION_SCOPE = '_scope';

    /**
     * The array option key for using secure URLs in Magento
     */
    const MAGENTO_URL_OPTION_SECURE = '_secure';

    /**
     * The array option key for store to url in Magento's URLs
     */
    const MAGENTO_URL_OPTION_SCOPE_TO_URL = '_scope_to_url';

    /**
     * The array option key for URL type in Magento's URLs
     */
    const MAGENTO_URL_OPTION_LINK_TYPE = '_type';

    /**
     * Path to Nosto's restore cart controller
     */
    const NOSTO_PATH_RESTORE_CART = 'nosto/frontend/restoreCart';

    /**
     * The array option key for no session id in Magento's URLs.
     * The session id should be included into the URLs which are potentially
     * used during the same session, e.g. Oauth redirect URL. For example for
     * product URLs we cannot include the session id as the product URL should
     * be the same for all visitors and it will be saved to Nosto.
     */
    const MAGENTO_URL_OPTION_NOSID = '_nosid';

    /**
     * The url type to be used for links.
     *
     * This is the only URL type that works correctly the URls when
     * "Add Store Code to Urls" setting is set to "Yes"
     *
     * UrlInterface::URL_TYPE_WEB
     * - returns an URL without rewrites and without store codes
     *
     * UrlInterface::URL_TYPE_LINK
     * - returns an URL with rewrites and with store codes in URL (if
     * setting "Add Store Code to Urls" set to yes)
     *
     * UrlInterface::URL_TYPE_DIRECT_LINK
     * - returns an URL with rewrites but without store codes
     *
     * @see UrlInterface::URL_TYPE_LINK
     *
     * @var string
     */
    public static $urlType = UrlInterface::URL_TYPE_LINK;

    private $productCollectionFactory;
    private $categoryCollectionFactory;
    private $productVisibility;
    private $urlBuilder;

    /** @noinspection PhpUndefinedClassInspection */
    /**
     * Constructor.
     *
     * @param Context $context the context.
     * @param ProductCollectionFactory $productCollectionFactory auto generated product collection factory.
     * @param CategoryCollectionFactory $categoryCollectionFactory auto generated category collection factory.
     * @param Visibility $productVisibility product visibility.
     * @param \Magento\Framework\Url $urlBuilder frontend URL builder.
     */
    public function __construct(
        Context $context,
        /** @noinspection PhpUndefinedClassInspection */
        ProductCollectionFactory $productCollectionFactory,
        /** @noinspection PhpUndefinedClassInspection */
        CategoryCollectionFactory $categoryCollectionFactory,
        Visibility $productVisibility,
        \Magento\Framework\Url $urlBuilder
    ) {
        parent::__construct($context);

        $this->productCollectionFactory = $productCollectionFactory;
        $this->categoryCollectionFactory = $categoryCollectionFactory;
        $this->productVisibility = $productVisibility;
        $this->urlBuilder = $urlBuilder;
    }

    /**
     * Gets the absolute preview URL to a given store's product page.
     * The product is the first one found in the database for the store.
     * The preview url includes "nostodebug=true" parameter.
     *
     * @param Store $store the store to get the url for.
     * @return string the url.
     */
    public function getPreviewUrlProduct(Store $store)
    {
        /** @var \Magento\Catalog\Model\ResourceModel\Product\Collection $collection */
        /** @noinspection PhpUndefinedMethodInspection */
        $collection = $this->productCollectionFactory->create();
        $collection->addStoreFilter($store->getId());
        $collection->setVisibility($this->productVisibility->getVisibleInSiteIds());
        $collection->addAttributeToFilter('status', ['eq' => '1']);
        $collection->setCurPage(1);
        $collection->setPageSize(1);
        $collection->load();

        $url = '';
        foreach ($collection->getItems() as $product) {
            /** @var \Magento\Catalog\Model\Product $product */
            $url = $product->getUrlInStore(
                [
                    self::MAGENTO_URL_OPTION_NOSID => true,
                    self::MAGENTO_URL_OPTION_SCOPE_TO_URL => true,
                    self::MAGENTO_URL_OPTION_SCOPE => $store->getCode(),
                ]
            );
            $url = $this->addNostoDebugParamToUrl($url);
        }

        return $url;
    }

    /**
     * Adds the `nostodebug` parameter to a url.
     *
     * @param string $url the url.
     * @return string the updated url.
     */
    public function addNostoDebugParamToUrl($url)
    {
        return HttpRequest::replaceQueryParamInUrl(
            'nostodebug',
            'true',
            $url
        );
    }

    /**
     * Gets the absolute preview URL to a given store's category page.
     * The category is the first one found in the database for the store.
     * The preview url includes "nostodebug=true" parameter.
     *
     * @param Store $store the store to get the url for.
     * @return string the url.
     *
     */
    public function getPreviewUrlCategory(Store $store)
    {
        $rootCatId = (int)$store->getRootCategoryId();
        /** @noinspection PhpUndefinedClassInspection */
        /** @var \Magento\Catalog\Model\ResourceModel\Category\Collection $collection */
        /** @noinspection PhpUndefinedMethodInspection */
        $collection = $this->categoryCollectionFactory->create();
        $collection->addAttributeToFilter('is_active', ['eq' => 1]);
        $collection->addAttributeToFilter('path', ['like' => "1/$rootCatId/%"]);
        $collection->setCurPage(1);
        $collection->setPageSize(1);
        $collection->load();

        foreach ($collection->getItems() as $category) {
            /** @var \Magento\Catalog\Model\Category $category */
            $url = $category->getUrl();
            $url = $this->replaceQueryParamsInUrl(
                ['___store' => $store->getCode()],
                $url
            );
            return $this->addNostoDebugParamToUrl($url);
        }

        return '';
    }

    /**
     * Replaces or adds a query parameters to a url.
     *
     * @param array $params the query params to replace.
     * @param string $url the url.
     * @return string the updated url.
     */
    public function replaceQueryParamsInUrl(array $params, $url)
    {
        return HttpRequest::replaceQueryParamsInUrl($params, $url);
    }

    /**
     * Gets the absolute preview URL to the given store's search page.
     * The search query in the URL is "q=nosto".
     * The preview url includes "nostodebug=true" parameter.
     *
     * @param Store $store the store to get the url for.
     * @return string the url.
     */
    public function getPreviewUrlSearch(Store $store)
    {
        $url = $this->urlBuilder->getUrl(
            self::MAGENTO_PATH_SEARCH_RESULT,
            [
                self::MAGENTO_URL_OPTION_NOSID => true,
                self::MAGENTO_URL_OPTION_SCOPE_TO_URL => true,
                self::MAGENTO_URL_OPTION_SCOPE => $store->getCode(),
            ]
        );
        $url = $this->replaceQueryParamsInUrl(['q' => 'nosto'], $url);
        return $this->addNostoDebugParamToUrl($url);
    }

    /**
     * Gets the absolute preview URL to the given store's cart page.
     * The preview url includes "nostodebug=true" parameter.
     *
     * @param Store $store the store to get the url for.
     * @return string the url.
     */
    public function getPreviewUrlCart(Store $store)
    {
        $url = $this->urlBuilder->getUrl(
            self::MAGENTO_PATH_CART,
            [
                self::MAGENTO_URL_OPTION_NOSID => true,
                self::MAGENTO_URL_OPTION_SCOPE_TO_URL => true,
                self::MAGENTO_URL_OPTION_SCOPE => $store->getCode(),
            ]
        );
        return $this->addNostoDebugParamToUrl($url);
    }

    /**
     * Gets the absolute preview URL to the given store's front page.
     * The preview url includes "nostodebug=true" parameter.
     *
     * @param Store $store the store to get the url for.
     * @return string the url.
     */
    public function getPreviewUrlFront(Store $store)
    {
        $url = $this->urlBuilder->getUrl(
            '',
            [
                self::MAGENTO_URL_OPTION_NOSID => true,
                self::MAGENTO_URL_OPTION_SCOPE_TO_URL => true,
                self::MAGENTO_URL_OPTION_SCOPE => $store->getCode(),
            ]
        );
        return $this->addNostoDebugParamToUrl($url);
    }

    /**
     * Gets the absolute URL to the current store view cart page.
     *
     * @param Store $store the store to get the url for.
     * @param string $currentUrl restore cart url
     * @return string the url.
     */
    public function getUrlCart(Store $store, $currentUrl)
    {
        // @codingStandardsIgnoreLine
        $userQuery = parse_url($currentUrl, PHP_URL_QUERY);
        if (is_string($userQuery)) {
            parse_str($userQuery, $urlParameters); // @codingStandardsIgnoreLine
        } else {
            $urlParameters = [];
        }

        $defaultParams = $this->getUrlOptionsWithNoSid();
        $url = $store->getUrl(
            self::MAGENTO_PATH_CART,
            $defaultParams
        );

        if (!empty($urlParameters)) {
            foreach ($urlParameters as $key => $val) {
                $url = HttpRequest::replaceQueryParamInUrl(
                    $key,
                    $val,
                    $url
                );
            }
        }

        return $url;
    }

    /**
     * Returns restore cart url
     *
     * @param string $hash
     * @param Store $store
     * @return string
     */
    public function generateRestoreCartUrl($hash, Store $store)
    {
        $params = $this->getUrlOptionsWithNoSid();
        $params['h'] = $hash;
        $url = $store->getUrl(
            self::NOSTO_PATH_RESTORE_CART,
            $params
        );

        return $url;
    }

    /**
     * Returns the default options for fetching Magento urls with no session id
     *
     * @return array
     */
    private function getUrlOptionsWithNoSid()
    {
        $params = [
            self::MAGENTO_URL_OPTION_SCOPE_TO_URL => true,
            self::MAGENTO_URL_OPTION_NOSID => true,
            self::MAGENTO_URL_OPTION_LINK_TYPE => self::$urlType
        ];

        return $params;
    }
}
