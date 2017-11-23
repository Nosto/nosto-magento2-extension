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

namespace Nosto\Tagging\Model;

use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\App\ResourceConnection\SourceProviderInterface;
use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

/**
 * Class AbstractBaseRepository
 * @package Nosto\Tagging\Model
 */
abstract class AbstractBaseRepository
{

    protected $objectResource;
    protected $objectCollectionFactory;
    protected $objectSearchResultsFactory;

    /**
     * AbstractBaseRepository constructor.
     * @param AbstractDb $objectResource
     * @param $objectCollectionFactory
     * @param $objectSearchResultsFactory
     */
    protected function __construct(
        AbstractDb $objectResource,
        $objectCollectionFactory,
        $objectSearchResultsFactory
    )
    {
        // ToDo - add some type safety for factories?
        $this->objectResource = $objectResource;
        $this->objectCollectionFactory = $objectCollectionFactory;
        $this->objectSearchResultsFactory = $objectSearchResultsFactory;
    }

    /**
     * @inheritdoc
     */
    public function search(SearchCriteriaInterface $searchCriteria)
    {
        /** @var CustomerCollection $collection */
        $collection = $this->objectCollectionFactory->create();
        /** @noinspection PhpParamsInspection */
        $this->addFiltersToCollection($searchCriteria, $collection);
        $collection->load();
        $searchResult = $this->objectSearchResultsFactory->create();
        $searchResult->setSearchCriteria($searchCriteria);
        $searchResult->setItems($collection->getItems());
        $searchResult->setTotalCount($collection->getSize());

        return $searchResult;
    }

    /**
     * @inheritdoc
     */
    private function addFiltersToCollection(SearchCriteriaInterface $searchCriteria, SourceProviderInterface $collection)
    {
        foreach ($searchCriteria->getFilterGroups() as $filterGroup) {
            $fields = $conditions = [];
            foreach ($filterGroup->getFilters() as $filter) {
                $fields[] = $filter->getField();
                $conditions[] = [$filter->getConditionType() => $filter->getValue()];
            }
            $collection->addFieldToFilter($fields, $conditions);
        }
    }

    /**
     * @inheritdoc
     */
    public function getById($id)
    {
        /** @var CustomerCollection $collection */
        $collection = $this->objectCollectionFactory->create();
        /** @var CustomerInterface $customer */
        $object = $collection->addFieldToFilter(
            $this->getIdentityKey(),
            (string) $id
        )->setPageSize(1)->setCurPage(1)->getFirstItem();

        if (empty($object)) {
            throw new NoSuchEntityException(new Phrase('Unable to find entry for id. "%1"', [$id]));
        }

        return $object;
    }
}