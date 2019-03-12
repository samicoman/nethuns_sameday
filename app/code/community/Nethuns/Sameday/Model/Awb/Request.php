<?php

class Nethuns_Sameday_Model_Awb_Request extends Nethuns_Sameday_Model_Rate_Request
{
    protected $_returnPapers;
    protected $_repack;
    protected $_exchangePackage;
    protected $_openPackage;

    /**
     * Nethuns_Sameday_Model_Awb_Request constructor.
     */
    public function __construct()
    {
        parent::__construct();

        $this->_returnPapers = Mage::getStoreConfigFlag('carriers/nethuns_sameday/return_papers');
        $this->_repack = Mage::getStoreConfigFlag('carriers/nethuns_sameday/repack');
        $this->_exchangePackage = Mage::getStoreConfigFlag('carriers/nethuns_sameday/exchange_package');
        $this->_openPackage = Mage::getStoreConfigFlag('carriers/nethuns_sameday/open_package');
    }

    /**
     * @param Mage_Sales_Model_Order_Address $address
     */
    public function setAwbRecipient($address)
    {
        $data = array();

        /** @var Nethuns_Sameday_Model_Api $api */
        $api = Mage::getSingleton('nethuns_sameday/api');

        /** @var Mage_Directory_Model_Region $region */
        $region = Mage::getModel('directory/region')->load($address->getRegionId());
        $response = $api->request(
            'geolocation/county',
            Zend_Http_Client::GET,
            array(),
            array('name' => $region->getName()));
        $data['county'] = $response['data'][0]['id'];
        $data['countyString'] = $response['data'][0]['name'];
        $data['address'] = implode(',', $address->getStreet());

        $city = $address->getCity();
        $response = $api->request(
            'geolocation/city',
            Zend_Http_Client::GET,
            array(),
            array('name' => $city, 'county' => $response['data'][0]['id'], 'address' => $data['address']));
        $data['city'] = $response['data'][0]['id'];
        $data['cityString'] = $response['data'][0]['name'];

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
        $data = array();

        $parcel = array(
            'height' => $address->getHeight() ? $address->getHeight() : $this->getDefaultHeight(),
            'length' => $address->getLength() ? $address->getLength() : $this->getDefaultLength(),
            'width' => $address->getWidth() ? $address->getWidth() : $this->getDefaultWidth(),
            'weight' => $address->getWeight() ? $address->getWeight() : $this->getDefaultWeight()
        );
        $data[] = $parcel;

        $this->setData('parcels', $data);
    }

    /**
     * @param string $method
     * @return Nethuns_Sameday_Model_Rate_Request|void
     */
    public function setService($method)
    {
        $service = str_replace(Nethuns_Sameday_Model_Carrier_Sameday::CODE . "_", "", $method);
        $service = Nethuns_Sameday_Model_Carrier_Sameday::getMethodByCode($service, 'id');

        $this->setData('service', $service);
    }

    /**
     *
     */
    public function setServiceTaxes()
    {
        $data = array();

        /** @var Nethuns_Sameday_Model_Api $api */
        $api = Mage::getSingleton('nethuns_sameday/api');
        $response = $api->request('client/services', Zend_Http_Client::GET, array(), array());

        foreach ($response['data'] as $service) {
            if ($service['id'] != $this->getService()) {
                continue;
            }

            foreach ($service['serviceOptionalTaxes'] as $tax) {
                if ($tax['packageType'] != $this->getPackageType()) {
                    continue;
                }

                switch ($tax['name']) {
                    case 'Deschidere Colet':
                        if ($this->_openPackage) {
                            $data[] = $tax['id'];
                        }
                        break;
                    case 'Reambalare':
                        if ($this->_repack) {
                            $data[] = $tax['id'];
                        }
                        break;
                    case 'Colet la schimb':
                        if ($this->_exchangePackage) {
                            $data[] = $tax['id'];
                        }
                        break;
                    case 'Retur Documente':
                        if ($this->_returnPapers) {
                            $data[] = $tax['id'];
                        }
                        break;
                }
            }
        }

        $this->setData('service_taxes', $data);
    }
}
