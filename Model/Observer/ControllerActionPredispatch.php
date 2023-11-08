<?php
namespace Getepay\Getepe\Model\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Checkout\Model\Session;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\OrderFactory;
use Magento\Framework\UrlInterface; // Import the UrlInterface

class ControllerActionPredispatch implements ObserverInterface {
    protected $checkoutSession;
    protected $orderFactory;
    protected $urlBuilder; // Add a property to hold the UrlInterface

    public function __construct(
        Session $checkoutSession,
        OrderFactory $orderFactory,
        UrlInterface $urlBuilder // Inject the UrlInterface
    ) {
        $this->checkoutSession = $checkoutSession;
        $this->orderFactory = $orderFactory;
        $this->urlBuilder = $urlBuilder; // Assign the UrlInterface
    }

    public function execute(\Magento\Framework\Event\Observer $observer) {
        $request = $observer->getData('request');
        if ($request->getModuleName() == "checkout" && $request->getActionName() == "success") {
            $orderId = $this->checkoutSession->getLastOrderId();
            if ($orderId) {
                $order = $this->orderFactory->create()->load($orderId);
                if ($order->getPayment()->getMethodInstance()->getCode() == "getepay" && $order->getState() == Order::STATE_NEW) {
                    $url = $this->urlBuilder->getUrl("getepay/redirect");
                    header("Location:$url");
                    exit;
                }
            }
        }
    }
}
