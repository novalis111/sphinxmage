<?php
class Exanto_Sphinxmage_Model_Resource_Fulltext extends Mage_CatalogSearch_Model_Resource_Fulltext
{
    /**
     * Prepare results for query
     *
     * @param Mage_CatalogSearch_Model_Fulltext $object
     * @param string                            $queryText
     * @param Mage_CatalogSearch_Model_Query    $query
     * @return Mage_CatalogSearch_Model_Mysql4_Fulltext
     */
    public function prepareResult($object, $queryText, $query)
    {
        $searchType = $object->getSearchType($query->getStoreId());

        /* @var $stringHelper Mage_Core_Helper_String */
        $stringHelper = Mage::helper('core/string');

        $bind = array(
            ':query' => $queryText
        );
        $like = array();
        $fulltextCond = '';
        $likeCond     = '';
        $separateCond = '';

        if ($searchType == Mage_CatalogSearch_Model_Fulltext::SEARCH_TYPE_LIKE || $searchType == Mage_CatalogSearch_Model_Fulltext::SEARCH_TYPE_COMBINE) {
            $words = $stringHelper->splitWords($queryText, true, $query->getMaxQueryWords());
            $likeI = 0;
            foreach ($words as $word) {
                $like[]                  = '`s`.`data_index` LIKE :likew' . $likeI;
                $bind[':likew' . $likeI] = '%' . $word . '%';
                $likeI++;
            }
            if ($like) {
                $likeCond = '(' . join(' AND ', $like) . ')';
            }
        }
        if ($searchType == Mage_CatalogSearch_Model_Fulltext::SEARCH_TYPE_FULLTEXT
            || $searchType == Mage_CatalogSearch_Model_Fulltext::SEARCH_TYPE_COMBINE
        ) {
            $fulltextCond = 'MATCH (`s`.`data_index`) AGAINST (:query IN BOOLEAN MODE)';
        }
        if ($searchType == Mage_CatalogSearch_Model_Fulltext::SEARCH_TYPE_COMBINE && $likeCond) {
            $separateCond = ' OR ';
        }

        $results = Mage::helper('sphinxmage')->getSphinxResults($queryText);

        // Loop through our Sphinx results
        foreach ($results as $item) {

            // Don't log empty results or autosuggest queries
            if (empty($item['matches']) || !$query->getId()) {
                continue;
            }

            foreach ($item['matches'] as $doc => $docinfo) {
                // Ensure we log query results into the Magento table.
                $sql = sprintf("INSERT INTO `{$this->getTable('catalogsearch/result')}` "
                        . " (`query_id`, `product_id`, `relevance`) VALUES "
                        . " (%d, %d, %f) "
                        . " ON DUPLICATE KEY UPDATE `relevance` = %f",
                    $query->getId(),
                    $doc,
                    $docinfo['weight'] / 1000,
                    $docinfo['weight'] / 1000
                );
                $this->_getWriteAdapter()->query($sql, $bind);
            }
        }

        $query->setIsProcessed(1);
        return $this;
    }

    /**
     * Prepare Fulltext index value for product
     *
     * @param array $indexData
     * @param array $productData
     * @return string
     */
    protected function _prepareProductIndex($indexData, $productData, $storeId)
    {
        $index = array();
        foreach ($this->_getSearchableAttributes('static') as $attribute) {
            if (isset($productData[$attribute->getAttributeCode()])) {
                if ($value = $this->_getAttributeValue($attribute->getId(), $productData[$attribute->getAttributeCode()], $storeId)) {
                    //For grouped products
                    if (isset($index[$attribute->getAttributeCode()])) {
                        if (!is_array($index[$attribute->getAttributeCode()])) {
                            $index[$attribute->getAttributeCode()] = array($index[$attribute->getAttributeCode()]);
                        }
                        $index[$attribute->getAttributeCode()][] = $value;
                    } //For other types of products
                    else {
                        $index[$attribute->getAttributeCode()] = $value;
                    }
                }
            }
        }
        foreach ($indexData as $attributeData) {
            foreach ($attributeData as $attributeId => $attributeValue) {
                if ($value = $this->_getAttributeValue($attributeId, $attributeValue, $storeId)) {
                    $code = $this->_getSearchableAttribute($attributeId)->getAttributeCode();

                    //For grouped products
                    if (isset($index[$code])) {
                        if (!is_array($index[$code])) {
                            $index[$code] = array($index[$code]);
                        }
                        $index[$code][] = $value;
                    } //For other types of products
                    else {
                        $index[$code] = $value;
                    }
                }
            }
        }

        $product      = $this->_getProductEmulator()
            ->setId($productData['entity_id'])
            ->setTypeId($productData['type_id'])
            ->setStoreId($storeId);
        $typeInstance = $this->_getProductTypeInstance($productData['type_id']);
        if ($data = $typeInstance->getSearchableData($product)) {
            $index['options'] = $data;
        }

        if (isset($productData['in_stock'])) {
            $index['in_stock'] = $productData['in_stock'];
        }

        if ($this->_engine) {
            return $this->_engine->prepareEntityIndex($index, $this->_separator, $productData['entity_id']);
        }
        return Mage::helper('catalogsearch')->prepareIndexdata($index, $this->_separator, $productData['entity_id']);
    }
}
