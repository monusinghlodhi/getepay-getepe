<?php

namespace Getepay\Getepe\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Model\Order\Email\Sender\OrderCommentSender;

class OrderSaveAfter implements ObserverInterface
{

    protected $orderCommentSender;

    public function __construct(
        OrderCommentSender $orderCommentSender
    )
    {
        $this->orderCommentSender = $orderCommentSender;
    }

    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        $order = $observer->getEvent()->getOrder();
        if ($order->getState() == \Magento\Sales\Model\Order::STATE_CANCELED) {
            $this->orderCommentSender->send($order, true);
        }

    }
}