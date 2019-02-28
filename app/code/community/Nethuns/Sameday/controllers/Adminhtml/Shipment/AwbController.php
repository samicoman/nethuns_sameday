<?php

class Nethuns_Sameday_Adminhtml_Shipment_AwbController extends Mage_Adminhtml_Controller_Action
{

    public function generateAction()
    {
        $shipment_id = $this->getRequest()->getParam('shipment_id');

        /** @var  Nethuns_Sameday_Model_Awb */
        Mage::getModel('nethuns_sameday/awb', ['shipment_id' => $shipment_id]);

        $this->_redirectReferer();
    }

    public function downloadAction()
    {
        $tracking_id = $this->getRequest()->getParam('tracking_id');

        /** @var Nethuns_Sameday_Model_Api $api */
        $api = Mage::getSingleton('nethuns_sameday/api');

        $content = $api->request(
            'awb/download/' . $tracking_id,
            Nethuns_Sameday_Model_Api::GET,
            [],
            [],
            false
        );

        $this->_prepareDownloadResponse(
            $tracking_id . '.pdf',
            $content,
            'application/pdf'
        );
    }

    protected function _isAllowed()
    {
        return Mage::getSingleton('admin/session')->isAllowed('sales/shipment');
    }
}