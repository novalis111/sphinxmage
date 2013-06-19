<?php
class Exanto_Sphinxmage_Helper_Data extends Mage_CatalogSearch_Helper_Data
{
    /**
     * Internal cache to avoid duplicates
     * @var array
     */
    protected $_valueCache = array();

    /**
     * Join index array to string by separator
     * Support 2 level array gluing
     *
     * @param array  $index
     * @param string $separator
     * @param int    $entity_id
     * @return string
     */
    public function prepareIndexdata($index, $separator = ' ', $entity_id = null)
    {
        $index = $this->_cleanData($index);
        $_attributes = array();
        $_index = array();
        foreach ($index as $key => $value) {
            // Skip some default attributes, add the rest
            $avoid = array('in_stock');
            if (in_array($key, $avoid)) {
                continue;
            }
            $skip = array('sku', 'name', 'description', 'short_description', 'meta_keywords', 'meta_title', 'in_stock');
            if (!in_array($key, $skip)) {
                $_attributes[$key] = $value;
            }
            if (!is_array($value)) {
                $_index[] = $value;
            } else {
                $_index = array_merge($_index, $value);
            }
        }
        $_index = array_unique($_index);

        if (is_array($index['name'])) {
            // Configurable Product Name
            $name = $index['name'][0];
        } else {
            // Simple Product Name
            $name = $index['name'];
        }

        // Combine the name with each non-standard attribute
        $name_attributes = array();
        foreach ($_attributes as $code => $value) {
            if (!is_array($value)) {
                $value = array($value);
            }

            // Loop through each simple product's attribute values and assign to
            // product name.
            foreach ($value as $key => $item_value) {
                if (in_array($item_value, $this->_valueCache)) {
                    continue;
                }
                $this->_valueCache[] = $item_value;
                if (isset($name_attributes[$key])) {
                    $name_attributes[$key] .= ' ' . $item_value;
                } else {
                    // The first time we see this add the name to start.
                    $name_attributes[] = $name . ' ' . $item_value;
                }
            }
        }

        $category = '';
        if ($entity_id) {
            /* @var Varien_Db_Adapter_Pdo_Mysql $read */
            $read      = Mage::getSingleton('core/resource')->getConnection('core/read');
            $entity_id = (int)$entity_id;
            // Get category
            $select = $read->select()->from(array('ccev' => 'catalog_category_entity_varchar'), array('value'))
                ->join(array('cce' => 'catalog_category_entity'), 'ccev.entity_id = cce.entity_id', array())
                ->join(array('ccp' => 'catalog_category_product'), 'cce.entity_id = ccp.category_id', array())
                ->where('ccp.product_id = ?', $entity_id)
                ->where('ccev.attribute_id = ?', 35)
                ->order('cce.level DESC')
                ->limit(1);
            $category = $read->fetchOne($select);
        }

        $data = array(
            'name'            => $name,
            'name_attributes' => join('. ', $name_attributes),
            'data_index'      => join($separator, $_index),
            'category'        => $category,
        );

        return $data;
    }

    public function getSphinxResults($queryText)
    {
        // Perform configured search-replace
        $lines = explode("\n", Mage::getStoreConfig('exanto_sphinxmage/sphinx/wordforms'));
        foreach ($lines as $line) {
            $bits = explode('#', trim($line, "#\r\n"));
            if (count($bits) != 2) {
                continue;
            }
            if (strpos($bits[0], '/') === 0) {
                // regex
                $queryText = preg_replace($bits[0], $bits[1], $queryText);
            } else {
                $queryText = str_ireplace($bits[0], $bits[1], $queryText);
            }
        }
        // Connect to our Sphinx Search Engine and run our queries
        $sphinx = new Exanto_Sphinxmage_Model_SphinxClient();
        $sphinx->SetServer('127.0.0.1', 9312);
        $sphinx->SetMatchMode(SPH_MATCH_EXTENDED);
        $sphinx->setFieldWeights(array(
            'name'            => 7,
            'category'        => 1,
            'name_attributes' => 3,
            'data_index'      => 1
        ));
        $sphinx->setLimits(0, 900, 1000, 5000);
        $sphinx->SetRankingMode(SPH_RANK_PROXIMITY_BM25);
        $sphinx->AddQuery($queryText, "fulltext");
        return $sphinx->RunQueries();
    }

    /**
     * Retrieve suggest url
     *
     * @return string
     */
    public function getSuggestUrl()
    {
        return $this->_getUrl('sphinxmage/ajax/suggest', array(
            '_secure' => Mage::app()->getFrontController()->getRequest()->isSecure()
        ));
    }

    /**
     * Clean up the given data to avoid duplicates, unwanted stuff etc.
     *
     * @param array $arr
     * @return array
     */
    protected function _cleanData(Array $arr)
    {
        // Clean up child names to not repeat parent
        if (isset($arr['name']) && is_array($arr['name'])) {
            $parentName = reset($arr['name']);
            foreach ($arr['name'] as $key => &$name) {
                if ($key == 0) {
                    continue;
                }
                if (strpos($name, $parentName) === 0) {
                    unset($arr['name'][$key]);
                } else {
                    $name = preg_replace("/$parentName/i", '', $name);
                }
            }
        }
        return $arr;
    }

}
