<?php
/**
 * Autocomplete queries list
 */
class Exanto_Sphinxmage_Block_Autocomplete extends Mage_Core_Block_Abstract
{
    protected $_suggestData = null;
    protected $_options = array();
    protected $_queryText = null;

    public function getCacheKeyInfo()
    {
        return array(
            'Exanto_Sphinxmage_Block_Autocomplete',
            Mage::app()->getStore()->getId(),
            $this->_queryText
        );
    }

    protected function _toHtml()
    {
        $html = '';

        if (!$this->_beforeToHtml()) {
            return $html;
        }

        $suggestData = $this->getSuggestData();
        if (!($count = count($suggestData))) {
            return $html;
        }

        $count--;

        $html = '<ul><li style="display:none"></li>';
        foreach ($suggestData as $index => $item) {
            if ($index == 0) {
                $item['row_class'] .= ' first';
            }

            if ($index == $count) {
                $item['row_class'] .= ' last';
            }

            $title = $img = false;
            $name  = $this->escapeHtml($item['name']);
            if (!isset($item['url'])) {
                // showAll <li> at the bottom
                $title = "title='" . $this->helper('catalogsearch')->getQueryText() . "'";
            } else {
                $name = "<a href='{$item['url']}' title='$name'>$name</a>";
                $img  = "<a href='{$item['url']}' title='{$this->escapeHtml($item['name'])}'>"
                    . "<img class='sugImg' src='{$item['img']}' alt='{$this->escapeHtml($item['name'])}' />"
                    . "</a>";
            }

            $html .= "<li $title class='{$item['row_class']}'>"
                . $img
                . ($name ? "<span class='name'>$name</span>" : '')
                . "<div class='text'>{$item['text']}</div>"
                . "</li>";
        }

        $html .= '</ul>';

        return $html;
    }

    public function getSuggestData()
    {
        return $this->_getLiveSuggestData();
    }

    /**
     * Not used atm, searches through old queries and returns the results from the first match
     *
     * @return array|null
     */
    protected function _getHistorySuggestData()
    {
        if (!$this->_suggestData) {
            /* @var Mage_CatalogSearch_Model_Resource_Query_Collection $queries */
            $queries = $this->helper('catalogsearch')->getSuggestCollection();
            $query   = $queries->getFirstItem();
            // Prepare collection from a previous query
            /* @var Mage_CatalogSearch_Model_Resource_Fulltext_Collection $collection */
            // See Mage_Catalog_Model_Layer::prepareProductCollection
            $collection  = Mage::getResourceModel('catalogsearch/fulltext_collection')
                ->addSearchFilter($query)
                ->addUrlRewrite()
                ->addStoreFilter()
                ->addAttributeToSelect(Mage::getSingleton('catalog/config')->getProductAttributes())
                ->addAttributeToSelect($this->_getOption('textattr'))
                ->addAttributeToFilter('visibility', array(
                'in' => array(
                    Mage_Catalog_Model_Product_Visibility::VISIBILITY_IN_SEARCH,
                    Mage_Catalog_Model_Product_Visibility::VISIBILITY_BOTH
                )));
            $totalValues = $collection->count();
            $collection->clear()->setPageSize($this->_getOption('numitems'))->load();
            if ($collection->count() == 0) {
                return $this->_getLiveSuggestData();
            }
            $this->_suggestData = $this->_prepareCollectionData($collection, $totalValues);
        }
        return $this->_suggestData;
    }

    /**
     * Gets live results from Sphinx Search Engine and returns configured amount of items
     *
     * @return array
     */
    protected function _getLiveSuggestData()
    {
        $this->_queryText = $this->helper('catalogsearch')->getQueryText();
        $results = Mage::helper('sphinxmage')->getSphinxResults($this->_queryText);
        $result  = reset($results);
        $matches = isset($result['matches']) ? array_keys((array)$result['matches']) : array();
        if (!$matches) {
            return array();
        } else {
            $totalValues = count($matches);
            $matches     = array_slice($matches, 0, $this->_getOption('numitems'), true);
        }
        $collection = Mage::getModel('catalog/product')
            ->getCollection()
            ->addIdFilter($matches)
            ->addUrlRewrite()
            ->addStoreFilter()
            ->addAttributeToSelect(Mage::getSingleton('catalog/config')->getProductAttributes())
            ->addAttributeToSelect($this->_getOption('textattr'))
            ->addAttributeToFilter('visibility', array(
            'in' => array(
                Mage_Catalog_Model_Product_Visibility::VISIBILITY_IN_SEARCH,
                Mage_Catalog_Model_Product_Visibility::VISIBILITY_BOTH
            )));
        return $this->_prepareCollectionData($collection, $totalValues);
    }

    /**
     * Prepare product collection for display in autosuggest box
     *
     * @param mixed $collection
     * @param int   $totalValues
     * @return array
     */
    protected function _prepareCollectionData($collection, $totalValues)
    {
        $counter = 0;
        $data    = array();
        foreach ($collection as $item) {
            /* @var Mage_Catalog_Model_Product $item */
            $text = $this->stripTags($item->getData($this->_getOption('textattr')));
            if (strlen($text) > $this->_getOption('textlen')) {
                $text = mb_substr($text, 0, $this->_getOption('textlen') - 3) . '...';
            }
            /* @var Mage_Catalog_Helper_Image $img */
            $_data  = array(
                'name'      => $item->getName(),
                'text'      => $text,
                'img'       => $item->getSmallImageUrl($this->_getOption('imgdim'), $this->_getOption('imgdim')),
                'url'       => $item->getProductUrl(),
                'row_class' => (++$counter) % 2 ? 'odd' : 'even',
            );
            $data[] = $_data;
        }
        if ($totalValues > $this->_getOption('numitems')) {
            $data[] = array(
                'name'      => false,
                'text'      => $this->__("Show %s remaining results &raquo;", $totalValues - $this->_getOption('numitems')),
                'title'     => $this->helper('catalogsearch')->getQueryText(),
                'row_class' => 'showAll',
            );
        }
        return $data;
    }

    protected function _getOption($key)
    {
        if (!$this->_options) {
            $this->_options = array(
                'numitems' => Mage::getStoreConfig('exanto_sphinxmage/options/numitems'),
                'textattr' => Mage::getStoreConfig('exanto_sphinxmage/options/textattr'),
                'textlen'  => Mage::getStoreConfig('exanto_sphinxmage/options/textlen'),
                'imgdim'   => Mage::getStoreConfig('exanto_sphinxmage/options/imgdim'),
            );
        }
        if (!isset($this->_options[$key])) {
            return null;
        }
        return $this->_options[$key];
    }
}