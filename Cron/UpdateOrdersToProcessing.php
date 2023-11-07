<?php
namespace Getepay\Getepe\Cron;
//callback url : https://monu.magento.com/getepay/payment/callback
use Getepay\Getepe\Model\Config;
use Magento\Sales\Model\Order\Payment\State\CaptureCommand;
use Magento\Sales\Model\Order\Payment\State\AuthorizeCommand;
use \Magento\Sales\Model\Order;
//use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Sales\Model\OrderFactory;
//use Getepay\Getepe\Model\PaymentMethod;
use Getepay\Getepe\Constants\OrderCronStatus;
use Magento\Framework\App\ResourceConnection;

class UpdateOrdersToProcessing {
    
    protected $_orderFactory;
    protected $resourceConnection;
    protected $transactionRepository;
    /**
     * @var \Magento\Framework\DB\Transaction
     */
    protected $transaction;

    /**
     * @var \Magento\Checkout\Model\Session
     */
    protected $checkoutSession;

     /**
     * @var \Magento\Customer\Model\Session
     */
    protected $customerSession;
    
    /**
     * @var \Magento\Catalog\Model\Session
     */
    protected $catalogSession;

    /**
     * @var \Magento\Sales\Model\Service\InvoiceService
     */
    protected $invoiceService;

    /**
     * @var \Magento\Sales\Model\Order\Email\Sender\InvoiceSender
     */
    protected $invoiceSender;

    /**
     * @var \Magento\Sales\Model\Order\Email\Sender\OrderSender
     */
    protected $orderSender;

    /**
     * @var \Magento\Sales\Api\OrderRepositoryInterface
     */
    protected $orderRepository;

    /**
     * @var \Magento\Sales\Api\Data\OrderInterface
     */
    protected $order;

    /**
     * @var \Magento\Framework\Api\SearchCriteriaBuilder
     */
    protected $searchCriteriaBuilder;

    /**
     * @var \Magento\Sales\Api\OrderManagementInterface
     */
    protected $orderManagement;

    protected $enableCustomPaidOrderStatus;

    protected $orderStatus;

    /**
     * @var STATUS_PROCESSING
     */
    protected const STATUS_PROCESSING   = 'processing';
    protected const STATUS_PENDING      = 'pending';
    protected const STATUS_CANCELED     = 'canceled';
    protected const STATE_NEW           = 'new';
    protected const PAYMENT_AUTHORIZED  = 'payment.authorized';
    protected const ORDER_PAID          = 'order.paid';

    protected const PROCESS_ORDER_WAIT_TIME = 5 * 60;

    /**
     * @var \Magento\Framework\Api\SortOrderBuilder
     */
    protected $sortOrderBuilder;

    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $logger;

    /**
     * @var \Magento\Sales\Model\Order\Payment\State\AuthorizeCommand
     */
    protected $authorizeCommand;

    /**
     * @var \Magento\Sales\Model\Order\Payment\State\CaptureCommand
     */
    protected $captureCommand;

    protected $isUpdateOrderCronV1Enabled;

    /**
     * CancelOrder constructor.
     * @param \Magento\Framework\App\Action\Context $context
     * @param \Magento\Customer\Model\Session $customerSession
     * @param \Magento\Sales\Api\OrderRepositoryInterface $orderRepository
     * @param \Magento\Framework\Api\SearchCriteriaBuilder $searchCriteriaBuilder
     * @param \Magento\Framework\Api\SortOrderBuilder $sortOrderBuilder
     * @param \Magento\Checkout\Model\Session $checkoutSession
     * @param \Magento\Sales\Api\OrderManagementInterface $orderManagement
     * @param \Magento\Sales\Model\Service\InvoiceService $invoiceService
     * @param \Magento\Framework\DB\Transaction $transaction
     * @param \Magento\Sales\Model\Order\Email\Sender\InvoiceSender $invoiceSender
     * @param \Magento\Sales\Model\Order\Email\Sender\OrderSender $orderSender
     * @param \Magento\Catalog\Model\Session $catalogSession
     * @param \Magento\Sales\Api\Data\OrderInterface $order
     * @param \Getepay\Getepe\Model\Config $config
     * @param \Magento\Sales\Api\TransactionRepositoryInterface $transactionRepository
     * @param \Psr\Log\LoggerInterface $logger
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Customer\Model\Session $customerSession,
        \Magento\Sales\Api\OrderRepositoryInterface $orderRepository,
        \Magento\Framework\Api\SearchCriteriaBuilder $searchCriteriaBuilder,
        \Magento\Framework\Api\SortOrderBuilder $sortOrderBuilder,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Sales\Api\OrderManagementInterface $orderManagement,
        \Magento\Sales\Model\Service\InvoiceService $invoiceService,
        \Magento\Framework\DB\Transaction $transaction,
        \Magento\Sales\Model\Order\Email\Sender\InvoiceSender $invoiceSender,
        \Magento\Sales\Model\Order\Email\Sender\OrderSender $orderSender,
        \Magento\Catalog\Model\Session $catalogSession,
        \Magento\Sales\Api\Data\OrderInterface $order,
        \Getepay\Getepe\Model\Config $config,
        \Magento\Sales\Api\TransactionRepositoryInterface $transactionRepository,
        \Psr\Log\LoggerInterface $logger,
        ResourceConnection $resourceConnection
    )
    {
        $this->config                   = $config;
        $getepay_mid                    = $this->config->getConfigData(Config::KEY_GETEPAY_MID);
        $terminalId                     = $this->config->getConfigData(Config::KEY_GETEPAY_TERMINAL_ID);
        $getepay_key                    = $this->config->getConfigData(Config::KEY_GETEPAY_KEY);
        $getepay_iv                     = $this->config->getConfigData(Config::KEY_GETEPAY_IV);
        $pmt_chk_url                    = $this->config->getConfigData(Config::KEY_PMT_CHK_URL);
        //$this->api                      = new Api($keyId, $keySecret);
        $this->orderRepository          = $orderRepository;
        $this->searchCriteriaBuilder    = $searchCriteriaBuilder;
        $this->sortOrderBuilder         = $sortOrderBuilder;
        $this->transaction              = $transaction;
        $this->checkoutSession          = $checkoutSession;
        $this->customerSession          = $customerSession;

        $this->invoiceService           = $invoiceService;
        $this->invoiceSender            = $invoiceSender;
        $this->orderSender              = $orderSender;
        $this->catalogSession           = $catalogSession;
        $this->order                    = $order;
        $this->transactionRepository    = $transactionRepository;
        $this->resourceConnection       = $resourceConnection;
        $this->logger                   = $logger;
        $this->orderStatus              = static::STATUS_PROCESSING;

        // $this->enableCustomPaidOrderStatus = $this->config->isCustomPaidOrderStatusEnabled();

        // if ($this->enableCustomPaidOrderStatus === true
        //     && empty($this->config->getCustomPaidOrderStatus()) === false)
        // {
        //     $this->orderStatus = $this->config->getCustomPaidOrderStatus();
        // }

        // $this->authorizeCommand = new AuthorizeCommand();
        // $this->captureCommand = new CaptureCommand();
        $this->isUpdateOrderCronV1Enabled = $this->config->isUpdateOrderCronV1Enabled();
    }

    public function execute()
    {
        $this->logger->info("Cronjob: Update Orders To Processing Cron value = " . $this->isUpdateOrderCronV1Enabled);
        if($this->isUpdateOrderCronV1Enabled === true)
        {
            // Fetch all pending orders' increment_id from the sales_order table.
            $connection = $this->resourceConnection->getConnection();
            $tableName = $connection->getTableName('sales_order');
            $select = $connection->select()->from(
                $tableName,
                ['increment_id', 'getepay_payment_id']
            )->where(
                'status = ?',
                'pending' // Change 'pending' to the actual status you want to filter by
            )->where(
                'getepay_payment_id IS NOT NULL'
            );
            $result = $connection->fetchAll($select);

            $mid            = $this->config->getConfigData(Config::KEY_GETEPAY_MID);
            $terminalId     = $this->config->getConfigData(Config::KEY_GETEPAY_TERMINAL_ID);
            $keyy           = $this->config->getConfigData(Config::KEY_GETEPAY_KEY);
            $ivv            = $this->config->getConfigData(Config::KEY_GETEPAY_IV);
            $url            = $this->config->getConfigData(Config::KEY_PMT_CHK_URL);
            $key            = base64_decode($keyy);
            $iv             = base64_decode($ivv);
            // Loop through each order Ids

            foreach ($result as $row) {
                $incrementId = $row['increment_id'];
                $getepayPaymentId = $row['getepay_payment_id'];
                // Do something with the $incrementId and $getepayPaymentId, such as printing or processing them.
                //echo "Increment ID: $incrementId, Getepay Payment ID: $getepayPaymentId<br>";

                //GetePay Callback
                $requestt = array(
                    "mid" => $mid ,
                    "paymentId" => $getepayPaymentId,
                    "referenceNo" => "",
                    "status" => "",
                    "terminalId" => $terminalId,
                );
                $json_requset = json_encode($requestt);	
                $ciphertext_raw = openssl_encrypt($json_requset, "AES-256-CBC", $key, $options = OPENSSL_RAW_DATA, $iv);	
                $ciphertext = bin2hex($ciphertext_raw);	
                $newCipher = strtoupper($ciphertext);	
                $request = array(
                    "mid" => $mid,
                    "terminalId" => $terminalId,
                    "req" => $newCipher
                );
                $curl = curl_init();	
                curl_setopt($curl, CURLOPT_URL, $url);
                curl_setopt($curl, CURLINFO_HEADER_OUT, true);
                curl_setopt(
                    $curl,
                    CURLOPT_HTTPHEADER,
                    array(
                        'Content-Type:application/json',
                    )
                );
                curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($curl, CURLOPT_POST, true);
                curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($request));
                $result = curl_exec($curl);
                curl_close($curl);	
                $jsonDecode = json_decode($result);
                $jsonResult = $jsonDecode->response;	
                $ciphertext_raw = hex2bin($jsonResult);
                $original_plaintext = openssl_decrypt($ciphertext_raw, "AES-256-CBC", $key, $options = OPENSSL_RAW_DATA, $iv);
                $json = json_decode($original_plaintext);
                
                $orderId = $json->merchantOrderNo;
                $getepayTxnId = $json->getepayTxnId;
                # get order and payment objects
                $order = $this->_orderFactory->create()->load($orderId);
                $payment = $order->getPayment();
            
                // Update order status
                if($json->txnStatus == "SUCCESS"){
                    
                    $order->setState(Order::STATE_PROCESSING)->setStatus($order->getConfig()->getStateDefaultStatus(Order::STATE_PROCESSING));

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
            
                } 
                elseif( $json->txnStatus == "FAILED" ) {
                        
                        $transaction = $this->transactionRepository->getByTransactionId("-1", $payment->getId(), $order->getId());
                        $transaction->setTxnId($getepayTxnId);
                        $transaction->setAdditionalInformation("Getepay Transaction Id", $getepayTxnId);
                        $transaction->setAdditionalInformation("status", "successful");
                        $transaction->setIsClosed(true);
                        $transaction->save();
                        $payment->addTransactionCommentsToOrder($transaction, "The transaction is failed");
                        $order->setState("canceled")->setStatus("canceled");
                        $payment->setParentTransactionId(null);
                        $payment->save();
                        $order->save();
                }
            }
        }
    }

    // @codeCoverageIgnoreStart
    // function getObjectManager()
    // {
    //     return \Magento\Framework\App\ObjectManager::getInstance();
    // }
    // @codeCoverageIgnoreEnd
}
