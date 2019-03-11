<?php

class Nethuns_Sameday_Model_Carrier_Source_Packagetype
{
    public function toOptionArray()
    {
        return array(
            array(
                'value' => Nethuns_Sameday_Model_Carrier_Sameday::PACKAGE_TYPE_REGULAR,
                'label' => Mage::helper('nethuns_sameday')->__('Package')
            ),
            array(
                'value' => Nethuns_Sameday_Model_Carrier_Sameday::PACKAGE_TYPE_ENVELOPE,
                'label' => Mage::helper('nethuns_sameday')->__('Envelope')
            ),
            array(
                'value' => Nethuns_Sameday_Model_Carrier_Sameday::PACKAGE_TYPE_LARGE,
                'label' => Mage::helper('nethuns_sameday')->__('Large package')
            )
        );
    }
}
