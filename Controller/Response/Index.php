<?php
namespace Getepay\Getepe\Controller\Response;

use Magento\Framework\App\Action\Action;
use Magento\Framework\View\Result\PageFactory;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Checkout\Model\Session;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\OrderFactory;
use Getepay\Getepe\Logger\Logger;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Response\Http;
use Magento\Sales\Model\Order\Payment\Transaction\Builder as TransactionBuilder;
use Magento\Sales\Model\Order\Payment\Transaction;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;

class Index extends Action implements CsrfAwareActionInterface
{
	protected $_objectmanager;
	protected $_checkoutSession;
	protected $_orderFactory;
	protected $_transactionBuilder;
	protected $urlBuilder;
	private $logger;
	protected $response;
	protected $config;
	protected $messageManager;
	protected $transactionRepository;
	protected $cart;
	protected $inbox;


    // /**
    //  * @var \Magento\Checkout\Model\Session
    //  */
    // protected $checkoutSession;

    /**
     * @param Context $context
     * @param \Magento\Checkout\Model\Session $checkoutSession
     * @param OrderFactory $orderFactory
     * @param Logger $logger
     * @param ScopeConfigInterface $scopeConfig
     * @param Http $response
     * @param TransactionBuilder $transactionBuilder // Use a class property for transactionBuilder
     * @param \Magento\Checkout\Model\Cart $cart
     * @param \Magento\AdminNotification\Model\Inbox $inbox
     * @param \Magento\Sales\Api\TransactionRepositoryInterface $transactionRepository
     */
    public function __construct(
        Context $context,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Sales\Model\OrderFactory $orderFactory, // Change to OrderFactory
        Logger $logger,
        ScopeConfigInterface $scopeConfig,
        Http $response,
        TransactionBuilder $transactionBuilder,
        \Magento\Checkout\Model\Cart $cart,
        \Magento\AdminNotification\Model\Inbox $inbox,
        \Magento\Sales\Api\TransactionRepositoryInterface $transactionRepository
    ) {
        parent::__construct($context);
        $this->_checkoutSession = $checkoutSession;
        $this->_orderFactory = $orderFactory;
        $this->response = $response;
        $this->config = $scopeConfig;
        $this->_transactionBuilder = $transactionBuilder; // Assign the transactionBuilder to the class property
        $this->logger = $logger;
        $this->cart = $cart;
        $this->inbox = $inbox;
        $this->transactionRepository = $transactionRepository;
        $this->urlBuilder = \Magento\Framework\App\ObjectManager::getInstance()->get('Magento\Framework\UrlInterface');
        
    }

    /**
     * @return ResultInterface
     * @throws \Exception
     */
    public function execute()
    {
		$responseData = $this->getRequest()->getParams();
		if($responseData){
			if($responseData['status'] == 'SUCCESS' || $responseData['status'] == 'FAILED'){
				// Extract values into PHP variables
				$status = $responseData['status'];
				$message = $responseData['message'];
				$mid = $responseData['mid'];
				$response = $responseData['response'];
				$terminalId = $responseData['terminalId'];

				# get Client credentials from configurations.
				$storeScope = \Magento\Store\Model\ScopeInterface::SCOPE_STORE;
				$req_url = $this->config->getValue("payment/getepay/req_url",$storeScope);
				$getepay_mid = $this->config->getValue("payment/getepay/getepay_mid",$storeScope);
				$terminalId = $this->config->getValue("payment/getepay/terminalId",$storeScope);
				$getepay_key = $this->config->getValue("payment/getepay/getepay_key",$storeScope);
				$getepay_iv = $this->config->getValue("payment/getepay/getepay_iv",$storeScope);
				$key = base64_decode($getepay_key);
				$iv = base64_decode($getepay_iv);
				$ciphertext_raw = $ciphertext_raw = hex2bin($response);
				$original_plaintext = openssl_decrypt($ciphertext_raw,  "AES-256-CBC", $key, $options=OPENSSL_RAW_DATA, $iv);
				$json = json_decode(json_decode($original_plaintext,true),true);
				$orderId = $json["merchantOrderNo"];
				$getepayTxnId = $json["getepayTxnId"];

				$this->_checkoutSession->setGetepayPaymentId($getepayTxnId);
				# get order and payment objects
				$order = $this->_orderFactory->create()->load($orderId);
				$payment = $order->getPayment();                

				if ($json["txnStatus"] == "SUCCESS") {
					
                    $order->setState(Order::STATE_PROCESSING)->setStatus($order->getConfig()->getStateDefaultStatus(Order::STATE_PROCESSING));
                    //$order->setState('pending')->setStatus('pending');
                    $transaction = $this->transactionRepository->getByTransactionId("-1", $payment->getId(), $order->getId());

                    if ($transaction) {
                        $transaction->setTxnId($getepayTxnId);
                        $transaction->setAdditionalInformation("Getepay Transaction Id", $getepayTxnId);
                        $transaction->setAdditionalInformation("status", "successful");
                        $transaction->setIsClosed(true);
                        $transaction->save();
                    }

                    $payment->addTransactionCommentsToOrder($transaction, "Transaction is completed successfully With GetePay");
                    $payment->setParentTransactionId(null);

					# send new email
					$order->setCanSendNewEmailFlag(true);
					$objectManager = \Magento\Framework\App\ObjectManager::getInstance();
					$objectManager->create('Magento\Sales\Model\OrderNotifier')->notify($order);
					
                    $payment->save();
                    $order->save();
                    $this->logger->info("Payment for $getepayTxnId was credited.");       

                    // Check if the order exists and the customer ID is set
                    if ($order->getId() && $order->getCustomerId()) {
                        $customerId = $order->getCustomerId();
                        // Now, $customerId contains the user (customer) ID.
                        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
                        $customerSession = $objectManager->get('Magento\Customer\Model\Session');
                        $customerModel = $objectManager->create('Magento\Customer\Model\Customer')->load($customerId);

                        if ($customerModel->getId()) {
                            $customerSession->setCustomerAsLoggedIn($customerModel);
                            $customerSession->regenerateId();
                            //echo 'Login';
                        }
                    } 
                    
                    $this->_checkoutSession->setLastQuoteId($order->getQuoteId());
                    $this->_checkoutSession->setLastSuccessQuoteId($order->getQuoteId());
                    $this->_checkoutSession->setLastOrderId($order->getId());
                    $this->_checkoutSession->setLastRealOrderId($order->getIncrementId());
                    $this->_checkoutSession->setLastOrderStatus($order->getStatus());

                    return $this->resultRedirectFactory->create()->setPath('checkout/onepage/success', ['_secure' => true]);
                } 
                elseif ($json["txnStatus"] == "FAILED") {
                    $transaction = $this->transactionRepository->getByTransactionId("-1", $payment->getId(), $order->getId());
                    $transaction->setTxnId($getepayTxnId);
                    $transaction->setAdditionalInformation("Getepay Transaction Id", $getepayTxnId);
                    $transaction->setAdditionalInformation("status", "successful");
                    $transaction->setIsClosed(1);
                    $transaction->save();
                    $payment->addTransactionCommentsToOrder($transaction, "The transaction is failed");

                    try {
                        $items = $order->getItemsCollection();
                        foreach ($items as $item) {
                            $this->cart->addOrderItem($item);
                        }
                        $this->cart->save();
                    } catch (\Exception $e) {
                        $message = $e->getMessage();
                        $this->logger->info("Not able to add Items to cart Exception Message" . $message);
                    }
                    //$order->cancel();
                    $order->setState("canceled")->setStatus("canceled");
                    $payment->setParentTransactionId(null);
                    $payment->save();
                    $order->save();
                    $this->logger->info("Payment for $getepayTxnId failed.");

                    // Check if the order exists and the customer ID is set
                    if ($order->getId() && $order->getCustomerId()) {
                        $customerId = $order->getCustomerId();
                        // Now, $customerId contains the user (customer) ID.
                        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
                        $customerSession = $objectManager->get('Magento\Customer\Model\Session');
                        $customerModel = $objectManager->create('Magento\Customer\Model\Customer')->load($customerId);                       

                        if ($customerModel->getId()) {
                            $customerSession->setCustomerAsLoggedIn($customerModel);
                            $customerSession->regenerateId();
                            //echo 'Login';
                        }
                    }                    
                    $this->_checkoutSession->setLastQuoteId($order->getQuoteId());
                    $this->_checkoutSession->setLastSuccessQuoteId($order->getQuoteId());
                    $this->_checkoutSession->setLastOrderId($order->getId());
                    $this->_checkoutSession->setLastRealOrderId($order->getIncrementId());
                    $this->_checkoutSession->setLastOrderStatus($order->getStatus());

                   // return $this->resultRedirectFactory->create()->setPath('checkout/cart', ['_secure' => true]);
                    $this->messageManager->addErrorMessage(__('Payment failed. Please try again.'));
                    return $this->resultRedirectFactory->create()->setPath('checkout/onepage/failure', ['_secure' => true]);
					
                } 
                else {
                    // Check if the order exists and the customer ID is set
                    if ($order->getId() && $order->getCustomerId()) {
                        $customerId = $order->getCustomerId();
                        // Now, $customerId contains the user (customer) ID.
                        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
                        $customerSession = $objectManager->get('Magento\Customer\Model\Session');
                        $customerModel = $objectManager->create('Magento\Customer\Model\Customer')->load($customerId);                 
                        
                        if ($customerModel->getId()) {
                            $customerSession->setCustomerAsLoggedIn($customerModel);
                            $customerSession->regenerateId();
                            //echo 'Login';
                        }
                    } 

                    try {
                        $items = $order->getItemsCollection();
                        foreach ($items as $item) {
                            $this->cart->addOrderItem($item);
                        }
                        $this->cart->save();
                    } catch (\Exception $e) {
                        $message = $e->getMessage();
                        $this->logger->info("Not able to add Items to cart Exception Message" . $message);
                    }

                    $this->_checkoutSession->setLastQuoteId($order->getQuoteId());
                    $this->_checkoutSession->setLastSuccessQuoteId($order->getQuoteId());
                    $this->_checkoutSession->setLastOrderId($order->getId());
                    $this->_checkoutSession->setLastRealOrderId($order->getIncrementId());
                    $this->_checkoutSession->setLastOrderStatus($order->getStatus());

                    $this->messageManager->addErrorMessage(__('Payment failed. Please try again.'));
                    return $this->resultRedirectFactory->create()->setPath('checkout/onepage/failure', ['_secure' => true]);
                }
			
			}
		}
		return $this->_redirect($this->urlBuilder->getBaseUrl());
    }

    /**
     * @param RequestInterface $request
     *
     * @return bool|null
     */
    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
    }

    /**
     * @param RequestInterface $request
     *
     * @return InvalidRequestException|null
     */
    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return null;
    }
}
