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

namespace Nosto\Tagging\Model\Service\Recommendation;

use Nosto\Object\Signup\Account as NostoAccount;
use Nosto\Service\FeatureAccess;
use Nosto\Tagging\Plugin\Catalog\Model\Config as NostoConfigModel;
use Nosto\Operation\Recommendation\CategoryBrowsingHistory;
use Nosto\Operation\Recommendation\CategoryTopList;
use Nosto\Tagging\Logger\Logger as NostoLogger;

class Category
{
    private $logger;

    /**
     * Category constructor.
     * @param NostoLogger $logger
     */
    public function __construct(NostoLogger $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Return array of personalized products ids
     *
     * @param NostoAccount $nostoAccount
     * @param $nostoCustomerId
     * @param $category
     * @param $type
     * @return array
     * @suppress PhanUndeclaredClassConstant
     */
    public function getSortedProductIds(
        NostoAccount $nostoAccount,
        $nostoCustomerId,
        $category,
        $type
    ) {
        $productIds = [];
        $featureAccess = new FeatureAccess($nostoAccount);
        if (!$featureAccess->canUseGraphql()) {
            return $productIds;
        }

        switch ($type) {
            case NostoConfigModel::NOSTO_PERSONALIZED_KEY:
                $recoOperation = new CategoryBrowsingHistory($nostoAccount, $nostoCustomerId);
                break;
            default:
                $recoOperation = new CategoryTopList($nostoAccount, $nostoCustomerId);
                break;
        }
        $recoOperation->setCategory($category);
        try {
            $result = $recoOperation->execute();
            foreach ($result as $item) {
                if ($item->getProductId()) {
                    $productIds[] = $item->getProductId();
                }
            }
        } catch (\Exception $e) {
            $this->logger->exception($e);
        }

        return $productIds;
    }
}
