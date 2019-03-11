<?php

class Nethuns_Sameday_Model_Api
{
    /**
     * API client token
     * @var string $_token
     */
    protected $_token;

    /**
     * API URL
     * @var string $_apiUrl
     */
    protected $_apiUrl;

    /**
     * API Username
     * @var string $_username
     */
    protected $_username;

    /**
     * API Password
     * @var string $_password
     */
    protected $_password;

    /**
     * Initialize API class
     */
    public function __construct()
    {
        $this->_token = Mage::getStoreConfig('carriers/nethuns_sameday/token');
        $this->_apiUrl = Mage::getStoreConfig('carriers/nethuns_sameday/api_url');
        $this->_username = Mage::getStoreConfig('carriers/nethuns_sameday/username');
        $this->_password = Mage::getStoreConfig('carriers/nethuns_sameday/password');
        $this->_httpUser = Mage::getStoreConfig('carriers/nethuns_sameday/http_user');
        $this->_httpPass = Mage::getStoreConfig('carriers/nethuns_sameday/http_pass');

        /* A dummy check to see if the token is valid */
        if ($this->_token) {
            $response = $this->request('geolocation/county', Zend_Http_Client::GET, array(), array('name' => 'ilfov'));
            if (!empty($response['total'])) {
                return;
            }
        }

        $response = $this->request(
            'authenticate',
            Zend_Http_Client::POST,
            array(
                'X-Auth-Username' => $this->_username,
                'X-Auth-Password' => $this->_password
            ),
            array(
                'remember_me' => 'true'
            )
        );

        if (empty($response['token'])) {
            if (Mage::getDesign()->getArea() == 'adminhtml') {
                $error = Mage::helper('nethuns_sameday')->__("There's something wrong with the API connection.
                 Please check the settings!");
                /** @var Mage_Adminhtml_Model_Session $session */
                $session = Mage::getSingleton('adminhtml/session');
                $session->addError($error);
            } else {
                $error = Mage::helper('nethuns_sameday')->__("Something is wrong. Please try again later!");
                /** @var Mage_Customer_Model_Session $session */
                $session = Mage::getSingleton('customer/session');
                $session->addError($error);
            }
        }

        $this->_token = $response['token'];
        Mage::getConfig()->saveConfig('carriers/nethuns_sameday/token', $this->_token, 'default', 0);
    }

    public function request($path, $type, $headers = array(), $params = array(), $decode = true)
    {
        $url = rtrim($this->_apiUrl, '/') . '/api/' . $path . '?_format=json';

        /* Funky hack because the API does not handle Bucuresti */
        if($path == 'geolocation/city') {
            $params = $this->prepareCityExceptions($params);
        }

        switch ($type) {
            case Zend_Http_Client::POST:
                $ch = curl_init($url);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
                break;
            case Zend_Http_Client::GET:
                $url .= !empty($params) ? '&' . http_build_query($params) : '';
                $ch = curl_init($url);
                break;
        }


        foreach ($headers as $key => $value) {
            $headers[] = $key . ': ' . $value;
        }

        $headers[] = 'Content-type: application/x-www-form-urlencoded';
        $headers[] = 'X-AUTH-TOKEN: ' . ($this->_token ? $this->_token : '2c19bb06b63523b0bab931e81d04a41aba9b1c9e');

        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_URL, $url);

        if ($this->_httpUser && $this->_httpPass) {
            curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
            curl_setopt($ch, CURLOPT_USERPWD, $this->_httpUser . ':' . $this->_httpPass);
        }

        /* TODO: test & improve error handling */
        if (!$result = curl_exec($ch)) {
            /** @var Mage_Adminhtml_Model_Session $session */
            $session = Mage::getSingleton('adminhtml/session');
            $session->addError(curl_error($ch));
        }

        curl_close($ch);

        return $decode ? json_decode($result, true) : $result;

    }

    function prepareCityExceptions($params)
    {
        if (!strtolower(iconv('UTF-8','ASCII//TRANSLIT', $params['name'])) == 'bucuresti')
        {
            return $params;
        }

        $address = $params['address'];

        if(empty($address)) {
            return $params;
        }

        $address = strtolower($address);
        $pattern = '/sector(ul)*(\s)(\d){1}/mi';
        $matches = array();

        preg_match($pattern, $address, $matches);

        /* We don't need this anymore */
        unset($params['address']);
        $params['name'] = !empty($matches[0]) ? 'Sectorul ' . end($matches) : $params['name'];

        return $params;
    }
}
