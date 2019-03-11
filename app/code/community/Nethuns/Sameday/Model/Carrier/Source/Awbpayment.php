<?php

class Nethuns_Sameday_Model_Carrier_Source_Awbpayment
{
    public function toOptionArray()
    {
        return array(
            array(
                'value' => Nethuns_Sameday_Model_Carrier_Sameday::CLIENT,
                'label' => Mage::helper('nethuns_sameday')->__('Sender')
            ),
            array(
                'value' => Nethuns_Sameday_Model_Carrier_Sameday::RECIPIENT,
                'label' => Mage::helper('nethuns_sameday')->__('Recipient')
            )
        );
    }
}
