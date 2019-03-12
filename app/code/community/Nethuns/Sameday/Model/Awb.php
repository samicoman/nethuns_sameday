<?php

class Nethuns_Sameday_Model_Awb extends Varien_Object
{

    /** @var Nethuns_Sameday_Model_Awb_Request $this ->_request */
    protected $_request;
    /** @var Mage_Sales_Model_Order_Shipment $_shipment */
    protected $_shipment;

    public function __construct(array $params)
    {
        parent::__construct();

        $this->_shipment = Mage::getModel('sales/order_shipment')->load($params['shipment_id']);

        $this->_setRequest();
        $awbNumber = $this->_generateAwb();
        $this->_setTracking($awbNumber);

        return $this;
    }

    protected function _setRequest()
    {
        /** @var Mage_Sales_Model_Order $order */
        $order = $this->_shipment->getOrder();

        $this->_request = Mage::getModel('nethuns_sameday/awb_request');
        $this->_request->setService($order->getShippingMethod());

        $shippingOrigin = explode(
            '___',
            Mage::getStoreConfig('carriers/nethuns_sameday/shipping_origin', $order->getStoreId())
        );
        $this->_request->setPickupPoint($shippingOrigin[0]);
        $this->_request->setContactPerson($shippingOrigin[1]);
        $this->_request->setPackageType(
            Mage::getStoreConfig('carriers/nethuns_sameday/package_type',$order->getStoreId())
        );

        /* TODO: figure out a way to calculate the package number based on product attributes or max package weight */
        $this->_request->setPackageNumber(Nethuns_Sameday_Model_Carrier_Sameday::DEFAULT_PACKAGE_NUMBER);
        $this->_request->setPackageWeight($order->getWeight());

        $this->_request->setAwbPayment(
            Mage::getStoreConfig('carriers/nethuns_sameday/awb_payment', $order->getStoreId())
        );
        $this->_request->setCashOnDelivery(
            $order->getPayment()->getMethod() == 'cashondelivery' ? $order->getGrandTotal() : 0
        );
        $this->_request->setInsuredValue($order->getGrandTotal());
        $this->_request->setThirdPartyPickup(Nethuns_Sameday_Model_Carrier_Sameday::TPP_NO);
        $this->_request->setClientObservation($order->getCustomerNote());

        $this->_request->setAwbRecipient($order->getShippingAddress());
        $this->_request->setParcels($order->getShippingAddress());
        $this->_request->setServiceTaxes();
    }

    protected function _generateAwb()
    {
        /** @var Mage_Adminhtml_Model_Session $session */
        $session = Mage::getSingleton('adminhtml/session');
        /** @var Nethuns_Sameday_Helper_Data $helper */
        $helper = Mage::helper('nethuns_sameday');
        /** @var Nethuns_Sameday_Model_Api $api */
        $api = Mage::getSingleton('nethuns_sameday/api');

        $response = $api->request(
            'awb',
            Zend_Http_Client::POST,
            array(),
            $this->_request->exportData()
        );

        if (!$response['awbNumber']) {
            $session->addError($helper->__('Unexpected error. Please try again later or create the AWB manually'));
            return false;
        }

        return $response['awbNumber'];
    }

    protected function _setTracking($awbNumber)
    {
        if (!$awbNumber) {
            return;
        }

        /** @var Mage_Adminhtml_Model_Session $session */
        $session = Mage::getSingleton('adminhtml/session');
        /** @var Nethuns_Sameday_Helper_Data $helper */
        $helper = Mage::helper('nethuns_sameday');
        /** @var Mage_Sales_Model_Order $order */
        $order = $this->_shipment->getOrder();

        $trackingDetail = array(
            'number' => $awbNumber,
            'carrier_code' => Nethuns_Sameday_Model_Carrier_Sameday::CODE,
            'title' => Mage::getStoreConfig('carriers/nethuns_sameday/title', $order->getStoreId())
        );

        /** @var Mage_Sales_Model_Order_Shipment_Track $track */
        $track = Mage::getModel('sales/order_shipment_track');
        $track->addData($trackingDetail);

        try {
            $this->_shipment->addTrack($track);
            $this->_shipment->save();
            $session->addSuccess($helper->__('AWB created successfully!'));
        } catch (Exception $e) {
            $session->addError($e->getMessage());
        }
    }
}