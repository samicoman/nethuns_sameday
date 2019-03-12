<?php

class Nethuns_Sameday_Model_Observer
{
    public function controller_action_layout_render_before_adminhtml_sales_order_shipment_view()
    {
        $this->_showGenerateAwbButtonOnOrderView();
    }

    public function sales_order_save_after(Varien_Event_Observer $observer)
    {
        $this->_autoInvoiceShipCompleteOrder($observer);
    }

    protected function _autoInvoiceShipCompleteOrder($observer)
    {
        if (!Mage::getStoreConfig('carriers/nethuns_sameday/automate')) {
            return $this;
        }

        /** @var  $helper Nethuns_Sameday_Helper_Data */
        $helper = Mage::helper("nethuns_sameday");

        /** @var Mage_Sales_Model_Order $order */
        $order = $observer->getEvent()->getOrder();

        /** @var Mage_Sales_Model_Entity_Order_Invoice_Collection $invoices */
        $invoices = Mage::getModel('sales/order_invoice')
            ->getCollection()
            ->addAttributeToFilter('order_id', array('eq' => $order->getId()));
        $invoice = $invoices->getFirstItem();

        if ($invoice && $invoice->getId()) {
            return $this;
        }

        if (!in_array(
            $order->getState(),
            array(
                Mage_Sales_Model_Order::STATE_NEW,
                Mage_Sales_Model_Order::STATE_PENDING_PAYMENT,
                Mage_Sales_Model_Order::STATE_PROCESSING
            )
        )) {
            return $this;
        }

        try {
            if (!$order->canInvoice()) {
                $order->addStatusHistoryComment($helper->__('Order could not be invoiced automatically'), false);
                $order->save();
                return $this;
            }

            /** @var Mage_Sales_Model_Order_Invoice $invoice */
            $invoice = Mage::getModel('sales/service_order', $order)->prepareInvoice();
            $invoice->setRequestedCaptureCase(Mage_Sales_Model_Order_Invoice::CAPTURE_OFFLINE);
            $invoice->register();

            $order->setCustomerNoteNotify(false);
            $order->setIsInProcess(true);
            $order->addStatusHistoryComment($helper->__('Order invoiced automatically'), false);

            /** @var Mage_Core_Model_Resource_Transaction $transactionSave */
            $transactionSave = Mage::getModel('core/resource_transaction')
                ->addObject($invoice)
                ->addObject($invoice->getOrder());
            $transactionSave->save();

            $shipment = $order->prepareShipment();
            $shipment->register();

            $order->setIsInProcess(true);
            $order->addStatusHistoryComment($helper->__('Order shipped automatically'), false);

            /** @var Mage_Core_Model_Resource_Transaction $transactionSave */
            $transactionSave = Mage::getModel('core/resource_transaction')
                ->addObject($shipment)
                ->addObject($shipment->getOrder());
            $transactionSave->save();

            /** @var  Nethuns_Sameday_Model_Awb */
            Mage::getModel('nethuns_sameday/awb', array('shipment_id' => $shipment->getId()));

        } catch (Exception $e) {
            $order->addStatusHistoryComment(
                $helper->__('Error while automatically processing the order: %s', $e->getMessage()),
                false
            );
            $order->save();
        }

        return $this;
    }

    protected function _showGenerateAwbButtonOnOrderView()
    {
        /** @var Mage_Adminhtml_Block_Sales_Order_Shipment_View $block */
        $block = Mage::app()->getLayout()->getBlock('sales_shipment_view');

        if (!$block) {
            return $this;
        }

        /** @var Mage_Sales_Model_Order_Shipment $shipment */
        $shipment = $block->getShipment();
        /** @var  $helper Nethuns_Sameday_Helper_Data */
        $helper = Mage::helper("nethuns_sameday");

        $trackings = $shipment->getAllTracks();

        if (empty($trackings)) {
            $block->addButton(
            'generate_awb',
                array(
                    'label' => $helper->__('Generate AWB'),
                    'onclick' => 'setLocation(\'' . $block->getUrl(
                        'adminhtml/shipment_awb/generate',
                        array('shipment_id' => $shipment->getId())
                    ) . '\')',
                )
            );
        } else {
            $block->addButton(
                'download_awb',
                array(
                    'label' => $helper->__('Download AWB'),
                    'onclick' => 'setLocation(\'' . $block->getUrl(
                        'adminhtml/shipment_awb/download',
                        array('tracking_id' => $trackings[0]->getNumber())
                    ) . '\')',
                )
            );
        }

        return $this;
    }
}
