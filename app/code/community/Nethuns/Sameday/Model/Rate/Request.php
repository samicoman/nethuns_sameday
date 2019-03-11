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
 * @method string getClientObservation()
 * @method Nethuns_Sameday_Model_Rate_Request setClientObservation(string $value)
 * @method array getAwbRecipient()
 * @method array getParcels()
 * @method array getServiceTaxes()
 *
 */
class Nethuns_Sameday_Model_Rate_Request extends Varien_Object
{
    protected $_packageType;

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
     * @param Mage_Shipping_Model_Rate_Request $request
     */
    public function setAwbRecipient($request)
    {
        $data = array();

        /** @var Nethuns_Sameday_Model_Api $api */
        $api = Mage::getSingleton('nethuns_sameday/api');

        /** @var Mage_Directory_Model_Region $region */
        $region = Mage::getModel('directory/region')->load($request->getDestRegionId());
        $response = $api->request(
            'geolocation/county',
            Zend_Http_Client::GET,
            array(),
            array('name' => $region->getName())
        );
        $data['county'] = $response['data'][0]['id'];

        $city = $request->getDestCity();
        $response = $api->request(
            'geolocation/city',
            Zend_Http_Client::GET,
            array(),
            array('name' => $city, 'county' => $response['data'][0]['id'], 'address' => $request->getDestStreet())
        );
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
        $data = array();

        $parcel = array(
            'height' => $request->getHeight() ? $request->getHeight() : $this->getDefaultHeight(),
            'length' => $request->getLength() ? $request->getLength() : $this->getDefaultLength(),
            'width' => $request->getWidth() ? $request->getWidth() : $this->getDefaultWidth(),
            'weight' => $request->getPackageWeight() ? $request->getPackageWeight() : $this->getDefaultWeight()
        );
        $data[] = $parcel;

        $this->setData('parcels', $data);
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

    /**
     * @return array
     */
    public function exportData()
    {
        $data = $this->getData();
        $response = array();
        foreach ($data as $key => $value) {
            $response[lcfirst(str_replace('_', '', ucwords($key, '_')))] = $value;
        }

        return $response;
    }

    /**
     * @return int
     */
    public function getConfigPackageType()
    {
        return $this->_packageType
            ? $this->_packageType
            : Mage::getStoreConfig('carriers/nethuns_sameday/package_type');
    }

    /**
     * @return int
     */
    public function getDefaultHeight()
    {
        $default = Mage::getStoreConfig('carriers/nethuns_sameday/default_height');

        if ($default) {
            return $default;
        }

        switch ($this->getConfigPackageType()) {
            case Nethuns_Sameday_Model_Carrier_Sameday::PACKAGE_TYPE_ENVELOPE:
                $default = 1;
                break;
            case Nethuns_Sameday_Model_Carrier_Sameday::PACKAGE_TYPE_REGULAR:
                $default = 25;
                break;
            case Nethuns_Sameday_Model_Carrier_Sameday::PACKAGE_TYPE_LARGE:
                $default = 100;
                break;
            default:
                $default = 50;
                break;
        }

        return $default;
    }

    /**
     * @return int
     */
    public function getDefaultLength()
    {
        $default = Mage::getStoreConfig('carriers/nethuns_sameday/default_length');

        if ($default) {
            return $default;
        }

        switch ($this->getConfigPackageType()) {
            case Nethuns_Sameday_Model_Carrier_Sameday::PACKAGE_TYPE_ENVELOPE:
                $default = 30;
                break;
            case Nethuns_Sameday_Model_Carrier_Sameday::PACKAGE_TYPE_REGULAR:
                $default = 25;
                break;
            case Nethuns_Sameday_Model_Carrier_Sameday::PACKAGE_TYPE_LARGE:
                $default = 100;
                break;
            default:
                $default = 50;
                break;
        }

        return $default;
    }

    /**
     * @return int
     */
    public function getDefaultWidth()
    {
        $default = Mage::getStoreConfig('carriers/nethuns_sameday/default_width');

        if ($default) {
            return $default;
        }

        switch ($this->getConfigPackageType()) {
            case Nethuns_Sameday_Model_Carrier_Sameday::PACKAGE_TYPE_ENVELOPE:
                $default = 20;
                break;
            case Nethuns_Sameday_Model_Carrier_Sameday::PACKAGE_TYPE_REGULAR:
                $default = 25;
                break;
            case Nethuns_Sameday_Model_Carrier_Sameday::PACKAGE_TYPE_LARGE:
                $default = 100;
                break;
            default:
                $default = 50;
                break;
        }

        return $default;
    }

    /**
     * @return int|float
     */
    public function getDefaultWeight()
    {
        $default = Mage::getStoreConfig('carriers/nethuns_sameday/default_weight');

        if ($default) {
            return $default;
        }

        switch ($this->getConfigPackageType()) {
            case Nethuns_Sameday_Model_Carrier_Sameday::PACKAGE_TYPE_ENVELOPE:
                $default = 0.5;
                break;
            case Nethuns_Sameday_Model_Carrier_Sameday::PACKAGE_TYPE_REGULAR:
                $default = 3;
                break;
            case Nethuns_Sameday_Model_Carrier_Sameday::PACKAGE_TYPE_LARGE:
                $default = 50;
                break;
            default:
                $default = 5;
                break;
        }

        return $default;
    }
}
