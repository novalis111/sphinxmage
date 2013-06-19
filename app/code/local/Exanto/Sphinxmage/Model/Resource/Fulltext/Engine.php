<?php
class Exanto_Sphinxmage_Model_Resource_Fulltext_Engine extends Mage_CatalogSearch_Model_Resource_Fulltext_Engine
{
    /**
     * Multi add entities data to fulltext search table
     *
     * @param int    $storeId
     * @param array  $entityIndexes
     * @param string $entity 'product'|'cms'
     * @return Mage_CatalogSearch_Model_Mysql4_Fulltext_Engine
     */
    public function saveEntityIndexes($storeId, $entityIndexes, $entity = 'product')
    {
        $adapter = $this->_getWriteAdapter();
        $data    = array();
        $storeId = (int)$storeId;
        foreach ($entityIndexes as $entityId => &$index) {
            $data[] = array(
                'product_id'      => (int)$entityId,
                'store_id'        => $storeId,
                'data_index'      => $index['data_index'],
                'name'            => $index['name'],
                'name_attributes' => $index['name_attributes'],
                'category'        => $index['category'],
            );
        }

        if ($data) {
            Mage::getResourceHelper('catalogsearch')
                ->insertOnDuplicate($this->getMainTable(), $data, array('data_index', 'name', 'name_attributes', 'category'));
        }

        return $this;
    }

    /**
     * Prepare index array as a string glued by separator
     *
     * @param array  $index
     * @param string $separator
     * @return string
     */
    public function prepareEntityIndex($index, $separator = ' ', $entity_id = null)
    {
        return Mage::helper('catalogsearch')->prepareIndexdata($index, $separator, $entity_id);
    }
}
