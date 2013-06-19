<?php

/**
 * Catalog Search Controller
 *
 * @category   Mage
 * @package    Exanto_Sphinxmage
 * @module     Catalog
 */
class Exanto_Sphinxmage_AjaxController extends Mage_Core_Controller_Front_Action
{
    public function suggestAction()
    {
        if (!$this->getRequest()->getParam('q', false)) {
            $this->getResponse()->setRedirect(Mage::getSingleton('core/url')->getBaseUrl());
        }

        $this->getResponse()->setBody($this->getLayout()->createBlock('sphinxmage/autocomplete')->toHtml());
    }
}
