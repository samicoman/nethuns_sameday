<?php
/**
 * @method int getPickupPoint()
 * @method Nethuns_Sameday_Model_Rate_Request setPickupPoint(int $value)
 * @method int getContactPerson()
 * @method Nethuns_Sameday_Model_Rate_Request setContactPerson(int $value)
 * @method int getPackageType()
 * @method Nethuns_Sameday_Model_Rate_Request setPackageType(int $value)
 * @method int getPackageNumber()
 * @method Nethuns_Sameday_Model_Rate_Request setPackageNumber(int $value)
 * @method float getPackageWeight()
 * @method Nethuns_Sameday_Model_Rate_Request setPackageWeight(float $value)
 * @method int getService()
 * @method Nethuns_Sameday_Model_Rate_Request setService(int $value)
 * @method int getAwbPayment()
 * @method Nethuns_Sameday_Model_Rate_Request setAwbPayment(int $value)
 * @method float getCashOnDelivery()
 * @method Nethuns_Sameday_Model_Rate_Request setCashOnDelivery(float $value)
 * @method float getInsuredValue()
 * @method Nethuns_Sameday_Model_Rate_Request setInsuredValue(float $value)
 * @method int getThirdPartyPickup()
 * @method Nethuns_Sameday_Model_Rate_Request setThirdPartyPickup(int $value)
 * @method int getDeliveryInterval()
 * @method Nethuns_Sameday_Model_Rate_Request setDeliveryInterval(int $value)
 * @method array getAwbRecipient()
 * @method array getParcels()
 * @method array getServiceTaxes()
 *
 */
class Nethuns_Sameday_Model_Rate_Request extends Varien_Object
{
    /**
     * @param Mage_Shipping_Model_Rate_Request $request
     */
    public function setAwbRecipient($request)
    {
        $data = [];

        /** @var Nethuns_Sameday_Model_Api $api */
        $api = Mage::getSingleton('nethuns_sameday/api');

        /** @var Mage_Directory_Model_Region $region */
        $region = Mage::getModel('directory/region')->load($request->getDestRegionId());
        $response = $api->request('geolocation/county', Nethuns_Sameday_Model_Api::GET, [], ['name' => $region->getName()]);
        $data['county'] = $response['data'][0]['id'];

        $city = $request->getDestCity();
        $response = $api->request('geolocation/city', Nethuns_Sameday_Model_Api::GET, [], ['name' => $city, 'county' => $response['data'][0]['id']]);
        $data['city'] = $response['data'][0]['id'];

        $data['address'] = $request->getDestStreet() . ' ' . $request->getDestStreetLine2();
        $data['name'] = $request->getDestPersonName() ? $request->getDestPersonName() : 'Dummy';
        $data['phoneNumber'] = $request->getDestPhoneNumber() ? $request->getDestPersonName() : '0123456789';
        $data['personType'] = Nethuns_Sameday_Model_Carrier_Sameday::PERSON_TYPE_INDIVIDUAL;

        $this->setData('awb_recipient', $data);
    }

    /**
     * @param Mage_Shipping_Model_Rate_Request $request
     */
    public function setParcels($request)
    {
        $data = [];

        $parcel = [
            'height'    => $request->getHeight(),
            'length'    => $request->getLength(),
            'width'     => $request->getWidth(),
            'weight'    => $request->getPackageWeight()
        ];
        $data[] = $parcel;

        $this->setData('parcels', $data);
    }

    /**
     * @param Mage_Shipping_Model_Rate_Request $request
     */
    public function setServiceTaxes($request)
    {
        $data = [];

        /** @var Nethuns_Sameday_Model_Api $api */
        $api = Mage::getSingleton('nethuns_sameday/api');

        $return_papers = Mage::getStoreConfigFlag('carriers/nethuns_sameday/return_papers');
        $repack = Mage::getStoreConfigFlag('carriers/nethuns_sameday/repack');
        $exchange_package = Mage::getStoreConfigFlag('carriers/nethuns_sameday/exchange_package');
        $open_package = Mage::getStoreConfigFlag('carriers/nethuns_sameday/open_package');

        $response = $api->request('client/services', Nethuns_Sameday_Model_Api::GET, [], []);

        foreach ($response['data'] as $service) {
            if($service['id'] == $this->getService()) {
                foreach ($service['serviceOptionalTaxes'] as $tax) {
                    if($tax['packageType'] != $this->getPackageType()) {
                        continue;
                    }
                    switch ($tax['name']) {
                        case 'Deschidere Colet':
                            if($open_package) {
                                $data[] = $tax['id'];
                            }
                            break;
                        case 'Reambalare':
                            if($repack) {
                                $data[] = $tax['id'];
                            }
                            break;
                        case 'Colet la schimb':
                            if($exchange_package) {
                                $data[] = $tax['id'];
                            }
                            break;
                        case 'Retur Documente':
                            if($return_papers) {
                                $data[] = $tax['id'];
                            }
                            break;
                    }
                }
            }
        }

        $this->setData('service_taxes', $data);
    }

    /**
     * @return array
     */
    public function exportData()
    {
        $data = $this->getData();
        $response = [];
        foreach($data as $key => $value) {
            $response[lcfirst(str_replace('_', '', ucwords($key, '_')))] = $value;
        }
        return $response;
    }
}
