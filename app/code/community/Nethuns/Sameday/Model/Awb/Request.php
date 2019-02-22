<?php

class Nethuns_Sameday_Model_Awb_Request extends Nethuns_Sameday_Model_Rate_Request
{
    /**
     * @param Mage_Sales_Model_Order_Address $address
     */
    public function setAwbRecipient($address)
    {
        $data = [];

        /** @var Nethuns_Sameday_Model_Api $api */
        $api = Mage::getSingleton('nethuns_sameday/api');

        /** @var Mage_Directory_Model_Region $region */
        $region = Mage::getModel('directory/region')->load($address->getRegionId());
        $response = $api->request('geolocation/county', Nethuns_Sameday_Model_Api::GET, [], ['name' => $region->getName()]);
        $data['county'] = $response['data'][0]['id'];
        $data['countyString'] = $response['data'][0]['name'];

        $city = $address->getCity();
        $response = $api->request('geolocation/city', Nethuns_Sameday_Model_Api::GET, [], ['name' => $city, 'county' => $response['data'][0]['id']]);
        $data['city'] = $response['data'][0]['id'];
        $data['cityString'] = $response['data'][0]['name'];

        $data['address'] = implode(',', $address->getStreet());
        $data['name'] = $address->getFirstname() . ' ' . $address->getLastname();
        $data['phoneNumber'] = $address->getTelephone();
        $data['email'] = $address->getEmail();
        $data['personType'] = Nethuns_Sameday_Model_Carrier_Sameday::PERSON_TYPE_INDIVIDUAL;

        $this->setData('awb_recipient', $data);
    }

    /**
     * @param Mage_Sales_Model_Order_Address $address
     */
    public function setParcels($address)
    {
        $data = [];

        $parcel = [
            'height'    => $address->getHeight() ? $address->getHeight() : $this->getDefaultHeight(),
            'length'    => $address->getLength() ? $address->getLength() : $this->getDefaultLength(),
            'width'     => $address->getWidth() ? $address->getWidth() : $this->getDefaultWidth(),
            'weight'    => $address->getWeight() ? $address->getWeight() : $this->getDefaultWeight()
        ];
        $data[] = $parcel;

        $this->setData('parcels', $data);
    }

    /**
     *
     */
    public function setServiceTaxes()
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
}
