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

    const GET = 'get';
    const POST = 'post';
    const PUT = 'put';
    const DELETE = 'delete';

    /**
     * Initialize API class
     */
    public function __construct()
    {
        $this->_token = Mage::getStoreConfig('carriers/nethuns_sameday/token');
        $this->_apiUrl = Mage::getStoreConfig('carriers/nethuns_sameday/api_url');
        $this->_username = Mage::getStoreConfig('carriers/nethuns_sameday/username');
        $this->_password = Mage::getStoreConfig('carriers/nethuns_sameday/password');

        /* A dummy check to see if the token is valid */
        if($this->_token) {
            $response = $this->request('geolocation/county', self::GET, [], ['name' => 'ilfov']);
            if(!empty($response['total'])) {
                return;
            }
        }

        $response = $this->request(
            'authenticate',
            self::POST,
            [
                'X-Auth-Username' => $this->_username,
                'X-Auth-Password' => $this->_password
            ],
            [
                'remember_me' => 'true'
            ]
        );

        if(empty($response['token'])) {
            if(Mage::getDesign()->getArea() == 'adminhtml') {
                $error = Mage::helper('nethuns_sameday')->__("There's something wrong with the API connection. Please check the settings!");
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

    public function request($path, $type, $headers = [], $params = [])
    {
        $url = rtrim($this->_apiUrl, '/') . '/api/' . $path . '?_format=json';

        switch ($type) {
            case self::POST:
                $ch = curl_init($url);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
                break;
            case self::GET:
                $url .= '&' . http_build_query($params);
                $ch = curl_init($url);
                break;
        }

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        foreach ($headers as $key => $value) {
            $headers[] = $key . ': ' . $value;
        }

        $headers[] = 'Content-type: application/x-www-form-urlencoded';
        $headers[] = 'X-AUTH-TOKEN: ' . $this->_token;

        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);


        /* TODO: test & improve error handling */
        if (!$result = curl_exec($ch)) {
            /** @var Mage_Adminhtml_Model_Session $session */
            $session = Mage::getSingleton('adminhtml/session');
            $session->addError((curl_error($ch)));
        }

        curl_close($ch);

        return json_decode($result, true);

    }
}
