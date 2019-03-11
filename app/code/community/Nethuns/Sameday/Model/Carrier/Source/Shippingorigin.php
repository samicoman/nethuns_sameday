<?php

class Nethuns_Sameday_Model_Carrier_Source_Shippingorigin
{
    public function toOptionArray()
    {
        $shippingOriginArray = $this->_getPickupPoints();
        $returnArr = array();
        foreach ($shippingOriginArray as $key => $val) {
            $returnArr[] = array(
                'value' => $key,
                'label' => $val
            );
        }

        return $returnArr;
    }

    protected function _getPickupPoints()
    {
        $pickupPoints = array();
        /** @var Nethuns_Sameday_Model_Api $api */
        $api = Mage::getSingleton('nethuns_sameday/api');
        $response = $api->request('client/pickup-points', Zend_Http_Client::GET);

        foreach ($response['data'] as $pickupPoint) {
            $pickupPoints[$pickupPoint['id'] . '___' . $pickupPoint['pickupPointContactPerson'][0]['id']]
                = $pickupPoint['address'];
        }

        return $pickupPoints;
    }
}
