<?php

class Nethuns_Sameday_Model_Carrier_Sameday
    extends Mage_Shipping_Model_Carrier_Abstract
    implements Mage_Shipping_Model_Carrier_Interface
{

    /**
     * Code of the carrier
     *
     * @var string
     */
    const CODE = 'nethuns_sameday';

    /**
     * Code of the carrier
     *
     * @var string
     */
    protected $_code = self::CODE;

    /**
     * Package types
     */
    const PACKAGE_TYPE_REGULAR = 0;
    const PACKAGE_TYPE_ENVELOPE = 1;
    const PACKAGE_TYPE_LARGE = 2;

    /**
     * Service ID
     */
    const SAMEDAY_DELIVERY = 1;
    const NEXTDAY_DELIVERY = 7;

    /**
     * Method
     *
     * @var string
     */
    protected $_methods;

    /**
     * AWB Payment
     */
    const CLIENT = 1;
    const RECIPIENT = 2;
    const THIRD_PARTY = 3;

    /**
     * Person Type
     */
    const PERSON_TYPE_INDIVIDUAL = 0;
    const PERSON_TYPE_BUSINESS = 1;

    /**
     * Default package number
     */
    const DEFAULT_PACKAGE_NUMBER = 1;

    /**
     * Third party pickup
     */
    const TPP_YES = 1;
    const TPP_NO = 0;

    /**
     * Rate request data
     *
     * @var Mage_Shipping_Model_Rate_Request|null
     */
    protected $_request = null;

    /**
     * Raw rate request data
     *
     * @var Nethuns_Sameday_Model_Rate_Request|null
     */
    protected $_rawRequest = null;

    /**
     * Rate result data
     *
     * @var Mage_Shipping_Model_Rate_Result|null
     */
    protected $_result = null;

    /**
     * Flag for check carriers for activity
     *
     * @var string
     */
    protected $_activeFlag = 'active';

    /**
     * Collect and get rates
     *
     * @param Mage_Shipping_Model_Rate_Request $request
     * @return Mage_Shipping_Model_Rate_Result|bool|null
     */
    public function collectRates(Mage_Shipping_Model_Rate_Request $request)
    {
        if (!$this->getConfigFlag($this->_activeFlag)) {
            return false;
        }

        /** @var Mage_Shipping_Model_Rate_Result $result */
        $this->_result = Mage::getModel('shipping/rate_result');

        /** @var Nethuns_Sameday_Model_Rate_Request $this->_rawRequest */
        $this->_rawRequest = Mage::getModel('nethuns_sameday/rate_request');

        $this->_rawRequest->setService(self::NEXTDAY_DELIVERY);
        $this->setRequest($request);
        $this->_getQuote();

        $this->_rawRequest->setService(self::SAMEDAY_DELIVERY);
        $this->setRequest($request);
        $this->_getQuote();

        $this->_updateFreeMethodQuote($request);

        return $this->_result;
    }

    /**
     * Prepare and set request to this instance
     *
     * @param Mage_Shipping_Model_Rate_Request $request
     * @return Nethuns_Sameday_Model_Carrier_Sameday
     */
    public function setRequest(Mage_Shipping_Model_Rate_Request $request)
    {
        $this->_request = $request;

        $shipping_origin = explode('___', Mage::getStoreConfig('carriers/nethuns_sameday/shipping_origin', $this->getStore()));
        $this->_rawRequest->setPickupPoint($request->getPickupPoint() ? $request->getPickupPoint() : $shipping_origin[0]);
        $this->_rawRequest->setContactPerson($request->getContactPerson() ? $request->getContactPerson() : $shipping_origin[1]);
        $this->_rawRequest->setPackageType($request->getPackageType() ? $request->getPackageType() : Mage::getStoreConfig('carriers/nethuns_sameday/package_type', $this->getStore()));
        /* TODO: figure out a way to calculate the package number based on product attributes or max package weight (see USPS) */
        $this->_rawRequest->setPackageNumber($request->getPackageNumber() ? $request->getPackageNumber() : self::DEFAULT_PACKAGE_NUMBER);
        $this->_rawRequest->setPackageWeight($request->getPackageWeight());
        $this->_rawRequest->setAwbPayment($request->getAwbPayment() ? $request->getAwbPayment() : Mage::getStoreConfig('carriers/nethuns_sameday/awb_payment', $this->getStore()));
        $this->_rawRequest->setCashOnDelivery($request->getBaseSubtotalInclTax() ? $request->getBaseSubtotalInclTax() : 0);
        $this->_rawRequest->setInsuredValue($request->getPackageValue() ? $request->getPackageValue() : 0);
        $this->_rawRequest->setThirdPartyPickup(self::TPP_NO);

        $this->_rawRequest->setAwbRecipient($request);
        $this->_rawRequest->setParcels($request);
        $this->_rawRequest->setServiceTaxes($request);


        return $this;
    }

    /**
     *
     */
    protected function _getQuote()
    {
        /** @var Nethuns_Sameday_Model_Api $api */
        $api = Mage::getSingleton('nethuns_sameday/api');

        $response = $api->request(
            'awb/estimate-cost',
            Nethuns_Sameday_Model_Api::POST,
            [],
            $this->_rawRequest->exportData()
        );

        /* TODO: add setting to chose whether to see the error on the checkout or not */
        if (isset($response['code']) && $response['code'] != 200) {
            /** @var Mage_Shipping_Model_Rate_Result_Error $error */
            $error = Mage::getModel('shipping/rate_result_error');
            $error->setCarrier($this->_code);

            $message = '';

            /* TODO: come up with a recursive solution */
            foreach ($response['errors']['children'] as $fields_0) {
                foreach ($fields_0 as $fields_1) {
                    foreach ($fields_1 as $fields_2) {
                        if (!is_array($fields_2)) {
                            $message .= $fields_2 . ' ';
                        } else {
                            foreach ($fields_2 as $fields_3) {
                                foreach ($fields_3 as $fields_4) {
                                    $message .= $fields_4 ? reset($fields_4) : '';
                                }
                            }
                        }
                    }
                }
            }

            $error->setErrorMessage($message);
            $this->_result->append($error);
            return;
        }

        /** @var Mage_Shipping_Model_Rate_Result_Method $method */
        $method = Mage::getModel('shipping/rate_result_method');
        $method->setCarrier($this->_code);
        $method->setCarrierTitle($this->getConfigData('title'));
        $method->setMethod($this->getMethod($this->_rawRequest->getService(), 'code'));
        $method->setMethodTitle($this->getMethod($this->_rawRequest->getService(), 'title'));
        $method->setPrice($response['amount']);
        $method->setCost($response['amount']);

        $this->_result->append($method);
    }

    /**
     * @param Mage_Shipping_Model_Rate_Request $request
     * @return void|null
     */
    protected function _updateFreeMethodQuote($request)
    {
        $freeShipping = false;
        /** @var Mage_Sales_Model_Quote_Item $item */
        foreach($request->getAllItems() as $item) {
            if ($item->getProduct() instanceof Mage_Catalog_Model_Product) {
                if ($item->getFreeShipping()) {
                    $freeShipping = true;
                } else {
                    return;
                }
            }
        }
        if ($freeShipping) {
            $request->setFreeShipping(true);
        }
    }

    /**
     * Get allowed shipping methods
     *
     * @return array
     */
    public function getAllowedMethods()
    {
        return array($this->_code => $this->getConfigData('title'));
    }

    /**
     * @return bool
     */
    public function isTrackingAvailable()
    {
        return true;
    }

    /**
     * @param $method_id
     * @param $key
     * @return string
     */
    public function getMethod($method_id, $key)
    {
        $this->_methods = [
            self::SAMEDAY_DELIVERY => [
                'code'  =>  'sameday',
                'title' => Mage::helper('nethuns_sameday')->__('Same Day Delivery')
            ],
            self::NEXTDAY_DELIVERY => [
                'code'  =>  'nextday',
                'title' => Mage::helper('nethuns_sameday')->__('Next Day Delivery')
            ]
        ];
        return $this->_methods[$method_id][$key];
    }
}