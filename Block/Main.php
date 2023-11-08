<?php
namespace Getepay\Getepe\Block;

use Magento\Framework\View\Result\PageFactory;
use Magento\Framework\View\Element\Template\Context;
use Magento\Checkout\Model\Session;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\OrderFactory;
use Getepay\Getepe\Logger\Logger;
use Magento\Framework\App\Response\Http;
use Magento\Sales\Model\Order\Payment\Transaction\Builder as TransactionBuilder;
use Magento\Customer\Model\Session as CustomerSession;

 
class Main extends  \Magento\Framework\View\Element\Template
{
	 protected $_objectmanager;
	 protected $checkoutSession;
	 protected $orderFactory;
	 protected $urlBuilder;
	 private $logger;
	 protected $response;
	 protected $config;
	 protected $messageManager;
	 protected $transactionBuilder;
	 protected $inbox;
	 protected $customerSession;
	 public function __construct(Context $context,
			Session $checkoutSession,
			OrderFactory $orderFactory,
			Logger $logger,
			Http $response,
			CustomerSession $customerSession,
			TransactionBuilder $tb,
			 \Magento\AdminNotification\Model\Inbox $inbox
		) {

      
        $this->checkoutSession = $checkoutSession;
        $this->orderFactory = $orderFactory;
        $this->response = $response;
        $this->config = $context->getScopeConfig();
        $this->transactionBuilder = $tb;
		$this->logger = $logger;					
		$this->inbox = $inbox;
		$this->customerSession = $customerSession;			
        
		$this->urlBuilder = \Magento\Framework\App\ObjectManager::getInstance()
							->get('Magento\Framework\UrlInterface');
		parent::__construct($context);
    }

	public function isCustomerLoggedIn()
    {
        return $this->customerSession->isLoggedIn();
    }

	protected function _prepareLayout()
	{
		//$method_data = array();
		$orderId = $this->checkoutSession->getLastOrderId();
		$this->logger->info('Creating Order for orderId $orderId');
		$order = $this->orderFactory->create()->load($orderId);
		if ($order)
		{
			$billing = $order->getBillingAddress();
			# check if mobile no to be updated.
			$updateTelephone = $this->getRequest()->getParam('telephone');
			if($updateTelephone)
			{
				$billing->setTelephone($updateTelephone)->save();
				
			}
 
			//var_dump($trn);exit;
			//try{
				$storeScope = \Magento\Store\Model\ScopeInterface::SCOPE_STORE;
				// $testmode = $this->config->getValue("payment/getepay/instamojo_testmode",$storeScope);
				$req_url = $this->config->getValue("payment/getepay/req_url",$storeScope);
				$getepay_mid = $this->config->getValue("payment/getepay/getepay_mid",$storeScope);
				$terminalId = $this->config->getValue("payment/getepay/terminalId",$storeScope);
				$getepay_key = $this->config->getValue("payment/getepay/getepay_key",$storeScope);
				$getepay_iv = $this->config->getValue("payment/getepay/getepay_iv",$storeScope);
				$this->logger->info("Request URL: $req_url | Getepay MID : $getepay_mid | Getepay Key: $getepay_key | Getepay IV: $getepay_iv | Order ID: $orderId");				
				
				$transaction_id = time() ."-". $order->getRealOrderId();
				$phone = $billing->getTelephone();
				$email = $billing->getEmail();
				$name = $billing->getFirstname() ." ". $billing->getLastname();
				// $amount = round((int)$order->getGrandTotal(),2);
				$amount = $order->getGrandTotal();
				$currency = "INR";
				$redirect_url = $this->urlBuilder->getUrl('getepay/response', ['_secure' => true]);
				//$this->logger->info("Date sent for creating order ".print_r($api_data,true));

				$getGetepayReqUrl = $req_url;
				//$getGetepayReqUrl = "https://pay1.getepay.in:8443/getepayPortal/pg/generateInvoice";
				$getGetepayMId = $getepay_mid;
				//$getGetepayMId = "108";
				$getGetepayTerminalId = $terminalId;
				//$getGetepayTerminalId = "Getepay.merchant61062@icici";
				$getGetepayKey = $getepay_key;
				//$getGetepayKey = "JoYPd+qso9s7T+Ebj8pi4Wl8i+AHLv+5UNJxA3JkDgY=";
				$getGetepayIv = $getepay_iv;
				//$getGetepayIv = "hlnuyA9b4YxDq6oJSZFl8g==";
				$getResponseUrl = $this->urlBuilder->getUrl('getepay/response', ['_secure' => true]);
				//$getResponseUrl = "https://getepay.in";
				$callBackUrl = $this->urlBuilder->getUrl('getepay/response', ['_secure' => true]);
				
				$url = trim($getGetepayReqUrl);
				$mid = trim($getGetepayMId);
				$terminalId = trim($getGetepayTerminalId);
				$keyy = trim($getGetepayKey);
				$ivv = trim($getGetepayIv);
				$ivv = trim($getGetepayIv);
				$ru =  trim($getResponseUrl);
				// $amt= trim($payment_total);
				$amt= $amount;
				$txnDateTime = date("Y-m-d H:m:s");              
				$udf1 = $name; 
				$udf2 = $phone;
				$udf3 = $email;
				//$udf4=""; 
				//$udf5 = "";
				$request=array(
					"mid"=>$mid,
					"amount"=>$amt,
					// "merchantTransactionId"=>$order_id,
					"merchantTransactionId"=>$orderId,
					"transactionDate"=>date("Y-m-d H:i:s"),
					"terminalId"=>$terminalId,
					"udf1"=>$udf1,
					"udf2"=>$udf2,
					"udf3"=>$udf3,
					"udf4"=>"",
					"udf5"=>"",
					"udf6"=>"",
					"udf7"=>"",
					"udf8"=>"",
					"udf9"=>"",
					"udf10"=>"",
					"ru"=>$ru,
					"callbackUrl"=>$callBackUrl,
					"currency"=>"INR",
					"paymentMode"=>"ALL",
					"bankId"=>"",
					"txnType"=>"single",
					"productType"=>"IPG",
					"txnNote"=>"Getepay transaction",
					"vpa"=>$terminalId,
				);
				$json_requset = json_encode($request);
				
				$key = base64_decode($keyy);
				$iv = base64_decode($ivv);

				// Encryption Code //
				$ciphertext_raw = openssl_encrypt($json_requset, "AES-256-CBC", $key, $options = OPENSSL_RAW_DATA, $iv);
				$ciphertext = bin2hex($ciphertext_raw);
				$newCipher = strtoupper($ciphertext);
				//print_r($newCipher);exit;
				$request=array(
					"mid"=>$mid,
					"terminalId"=>$terminalId,
					"req"=>$newCipher
				);
				$curl = curl_init();
				curl_setopt($curl, CURLOPT_URL, $url);
				curl_setopt($curl, CURLINFO_HEADER_OUT, true);
				curl_setopt($curl, CURLOPT_HTTPHEADER, array(
					'Content-Type:application/json',
				));
				curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
				curl_setopt($curl, CURLOPT_POST, true);
				curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($request));
				$result = curl_exec($curl);
				curl_close ($curl);
				
				$jsonDecode = json_decode($result);
				$jsonResult = $jsonDecode->response;
				$ciphertext_raw = hex2bin($jsonResult);
				$original_plaintext = openssl_decrypt($ciphertext_raw,  "AES-256-CBC", $key, $options=OPENSSL_RAW_DATA, $iv);
				$json = json_decode($original_plaintext);
				
				//echo "<pre/>"; print_r($json);
				$paymentId = $json->paymentId;

				$payment = $order->getPayment();
			
				$payment->setTransactionId("-1");
				$payment->setAdditionalInformation(  
					[\Magento\Sales\Model\Order\Payment\Transaction::RAW_DETAILS => array("Transaction is yet to complete")]
				);
				$trn = $payment->addTransaction(\Magento\Sales\Model\Order\Payment\Transaction::TYPE_CAPTURE,null,true);
				$trn->setIsClosed(0)->save();
				$payment->addTransactionCommentsToOrder(
					$trn,
				"The User was Redirected to Getepay for Payment."
				);

				$payment->setParentTransactionId($paymentId);

				// Save Getepay Payment ID to the order
				$order->setGetepayPaymentId($paymentId);
				$payment->save();
				$order->save();

				$pgUrl = $json->paymentUrl;

				if(isset($pgUrl))
				{
					// $this->setAction($pgUrl);
					$this->checkoutSession->setGetepayPaymentId($paymentId);
					// $this->checkoutSession->setLastSuccessQuoteId($order->getQouteId());
					$this->checkoutSession->setLastOrderId($orderId);
					$this->checkoutSession->setLastRealOrder($orderId);
					$this->checkoutSession->setLastSuccessQuoteId($orderId);
					// header("Location: $pgUrl");	
					// return;
					$this->setAction($pgUrl);
					$this->checkoutSession->setPaymentRequestId($orderId);	
					return;
				}
			//}			
		}
		else
		{
			$this->logger->info('Order with ID $orderId not found. Quitting :-(');
		}
		
		
		
			// $showPhoneBox = false;
			// if(isset($method_data['errors']) and is_array($method_data['errors']))
			// {
			// 	foreach($method_data['errors'] as $error)
			// 	{
			// 		if(stristr($error,"phone"))
			// 			$showPhoneBox = true;
			// 	}
				
			// $this->setMessages($method_data['errors']);
			// }
			// if($showPhoneBox)
			// 	$this->setTelephone($api_data['phone']);
			// $this->setShowPhoneBox($showPhoneBox);
	}
}
