<?php

class Nethuns_Sameday_Model_Carrier_Source_ShippingOrigin
{
    public function toOptionArray()
    {
        $shippingOriginArray = $this->_getPickupPoints();
        $returnArr = [];
        foreach ($shippingOriginArray as $key => $val) {
            $returnArr[] = [
                'value' => $key,
                'label' => $val
            ];
        }
        return $returnArr;
    }

    protected function _getPickupPoints()
    {
        $pickup_points = [];
        /** @var Nethuns_Sameday_Model_Api $api */
        $api = Mage::getSingleton('nethuns_sameday/api');
        $response = $api->request('client/pickup-points', Nethuns_Sameday_Model_Api::GET);

        foreach ($response['data'] as $pickup_point) {
            $pickup_points[$pickup_point['id'] . '___' . $pickup_point['pickupPointContactPerson'][0]['id']] = $pickup_point['address'];
        }

        return $pickup_points;
    }
}
