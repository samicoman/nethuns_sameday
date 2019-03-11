<?php

class Nethuns_Sameday_Adminhtml_Shipment_AwbController extends Mage_Adminhtml_Controller_Action
{

    public function generateAction()
    {
        $shipmentId = $this->getRequest()->getParam('shipment_id');

        /** @var  Nethuns_Sameday_Model_Awb */
        Mage::getModel('nethuns_sameday/awb', array('shipment_id' => $shipmentId));

        $this->_redirectReferer();
    }

    public function downloadAction()
    {
        $trackingId = $this->getRequest()->getParam('tracking_id');

        /** @var Nethuns_Sameday_Model_Api $api */
        $api = Mage::getSingleton('nethuns_sameday/api');

        $content = $api->request(
            'awb/download/' . $trackingId,
            Zend_Http_Client::GET,
            array(),
            array(),
            false
        );

        $this->_prepareDownloadResponse(
            $trackingId . '.pdf',
            $content,
            'application/pdf'
        );
    }

    protected function _isAllowed()
    {
        return Mage::getSingleton('admin/session')->isAllowed('sales/shipment');
    }
}