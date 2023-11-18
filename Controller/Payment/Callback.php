<?php
namespace Getepay\Getepe\Controller\Payment;
use Getepay\Getepe\Model\Config;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order\Payment\State\CaptureCommand;
use Magento\Sales\Model\Order\Payment\State\AuthorizeCommand;
use Magento\Framework\Controller\ResultFactory;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\OrderFactory;
use Magento\Sales\Model\Order\Invoice;
use Magento\Framework\DB\Transaction;
//use Getepay\Getepe\Model\PaymentMethod;
use Getepay\Getepe\Constants\OrderCronStatus;
use Magento\Framework\App\ResourceConnection;

/**
 * CancelPendingOrders controller to cancel Magento order
 * Used for off site redirect payment
 * ...
 */
class Callback extends \Getepay\Getepe\Controller\BaseController
{
    protected $_orderFactory;
    protected $resourceConnection;
    protected $transactionRepository;
    protected $isUpdateOrderCronV1Enabled;

    /**
     * @var \Magento\Framework\DB\Transaction
     */
    private Transaction $transactionModel;
    
    /**
     * @var OrderSender
     */
    protected $OrderSender;

    /**
     * @var InvoiceSender
     */
    protected $invoiceSender;

    /**
     * @var InvoiceService
     */
    protected $invoiceService;

    const STATUS_APPROVED = 'APPROVED';
    const STATUS_PROCESSING = 'processing';

    /**
     * @var \Magento\Checkout\Model\Session
     */
    protected $checkoutSession;

    /**
     * @var \Magento\Customer\Model\Session
     */
    protected $customerSession;

    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $logger;

    /**
     * @var \Magento\Sales\Api\OrderRepositoryInterface
     */
    protected $orderRepository;

    protected $objectManagement;

    /**
     * @var \Magento\Catalog\Model\Session
     */
    protected $catalogSession;

    /**
     * @var \Magento\Sales\Api\Data\OrderInterface
     */
    protected $order;

    /**
     * @var \Magento\Sales\Model\Order\Payment\State\CaptureCommand
     */
    protected $captureCommand;

    /**
     * @var \Magento\Sales\Model\Order\Payment\State\AuthorizeCommand
     */
    protected $authorizeCommand;

    /**
     * @param \Magento\Framework\App\Action\Context $context
     * @param \Magento\Customer\Model\Session $customerSession
     * @param \Magento\Checkout\Model\Session $checkoutSession
     * @param \Getepay\Getepe\Model\Config $config
     * @param \Magento\Sales\Model\OrderFactory $orderFactory
     * @param \Psr\Log\LoggerInterface $logger
     * @param OrderRepositoryInterface $orderRepository
     * @param \Magento\Sales\Model\Service\InvoiceService $invoiceService
     * @param \Magento\Sales\Model\Order\Email\Sender\OrderSender $OrderSender
     * @param \Magento\Sales\Model\Order\Email\Sender\InvoiceSender $invoiceSender
     * @param \Magento\Catalog\Model\Session $catalogSession
     * @param \Magento\Sales\Api\Data\OrderInterface $order
     * @param \Magento\Sales\Api\TransactionRepositoryInterface $transactionRepository
     * @param Transaction $transactionModel
     * @param ResourceConnection $resourceConnection
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Customer\Model\Session $customerSession,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Getepay\Getepe\Model\Config $config,
        \Magento\Sales\Model\OrderFactory $orderFactory,
        \Psr\Log\LoggerInterface $logger,
        OrderRepositoryInterface $orderRepository,
        \Magento\Sales\Model\Service\InvoiceService $invoiceService,
        \Magento\Sales\Model\Order\Email\Sender\OrderSender $OrderSender,
        \Magento\Sales\Model\Order\Email\Sender\InvoiceSender $invoiceSender,
        \Magento\Catalog\Model\Session $catalogSession,
        \Magento\Sales\Api\Data\OrderInterface $order,
        \Magento\Sales\Api\TransactionRepositoryInterface $transactionRepository,
        Transaction $transactionModel,
        ResourceConnection $resourceConnection
)
    {
        parent::__construct($context, $customerSession, $checkoutSession, $config);

        $this->config           = $config;
        $getepay_mid            = $this->config->getConfigData(Config::KEY_GETEPAY_MID);
        $terminalId             = $this->config->getConfigData(Config::KEY_GETEPAY_TERMINAL_ID);
        $getepay_key            = $this->config->getConfigData(Config::KEY_GETEPAY_KEY);
        $getepay_iv             = $this->config->getConfigData(Config::KEY_GETEPAY_IV);
        $pmt_chk_url            = $this->config->getConfigData(Config::KEY_PMT_CHK_URL);

        $this->checkoutSession  = $checkoutSession;
        $this->customerSession  = $customerSession;
        $this->logger           = $logger;
        $this->orderRepository  = $orderRepository;
        $this->objectManagement = \Magento\Framework\App\ObjectManager::getInstance();
        $this->OrderSender      = $OrderSender;
        $this->_orderFactory    = $orderFactory;
        $this->invoiceService   = $invoiceService;
        $this->invoiceSender    = $invoiceSender;
        $this->catalogSession   = $catalogSession;
        $this->order            = $order;
        $this->transactionModel = $transactionModel;
        $this->resourceConnection = $resourceConnection;
        $this->transactionRepository = $transactionRepository;
        $this->isUpdateOrderCronV1Enabled = $this->config->isUpdateOrderCronV1Enabled();
        $this->authorizeCommand = new AuthorizeCommand();
        $this->captureCommand = new CaptureCommand();

    }
    //callback url : https://monu.magento.com/getepay/payment/callback
    public function execute()
    {

        // if($this->isUpdateOrderCronV1Enabled === true)
        // {
        //     echo 'Enable';
        // }else{
        //     echo 'Desable';
        // }
        // exit;
        // Fetch all pending orders' increment_id from the sales_order table.
        $connection = $this->resourceConnection->getConnection();
        $tableName = $connection->getTableName('sales_order');
        $select = $connection->select()->from(
            $tableName,
            ['increment_id', 'getepay_payment_id', 'getepay_payment_status']
        )->where(
            'status = ?',
            'pending' // Change 'pending' to the actual status you want to filter by
        )->where(
            'getepay_payment_id IS NOT NULL'
        );
        $result = $connection->fetchAll($select);

        // foreach ($result as $row) {
        //     $incrementId = $row['increment_id'];
        //     $getepayPaymentId = $row['getepay_payment_id'];
        //     $getepay_payment_status = $row['getepay_payment_status'];
        //     // Do something with the $incrementId and $getepayPaymentId, such as printing or processing them.
        //     echo "<b>Increment ID:</b> $incrementId, <b>Getepay Payment ID:</b> $getepayPaymentId, <b>Getepay Payment Status:</b> $getepay_payment_status<br>";
        // }
        // exit;

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
            $getepay_payment_status = $row['getepay_payment_status'];

            // Do something with the $incrementId and $getepayPaymentId, such as printing or processing them.
            echo "Increment ID: $incrementId, Getepay Payment ID: $getepayPaymentId, Getepay Payment Status: $getepay_payment_status<br>";

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

            // if ($order->getPayment()->getMethod() === 'getepay') 
            // {
            //     echo 'Getepay Payment';
            // }else{
            //     echo 'Other Payment';
            // }
            // exit;

            // echo '<pre>';
            // print_r($json);
            // echo 'Testt';
            // exit;

            $order->setGetepayPaymentStatus($json->txnStatus)->save();
        
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

                    // $payment->addTransactionCommentsToOrder($transaction, "Transaction is completed successfully With GetePay");
                    // $payment->setParentTransactionId(null);

                    $payment->setLastTransId($getepayTxnId)
                            ->setTransactionId($getepayTxnId)
                            ->setIsTransactionClosed(true)
                            ->setShouldCloseParentTransaction(true);

                    $payment->setParentTransactionId($payment->getTransactionId());

                        $payment->addTransactionCommentsToOrder(
                            "$getepayTxnId",
                            $this->authorizeCommand->execute(
                                $payment,
                                $order->getGrandTotal(),
                                $order
                            ),
                            ""
                        );
                    # get Client credentials from configurations.
                    $order_successful_email = $this->config->getConfigData(Config::KEY_GETEPAY_ORDER_EMAIL);

                    if ($order_successful_email != '0') {
                        $this->OrderSender->send($order);
                        $order->addStatusHistoryComment(
                            __('Notified customer about order #%1.', $orderId)
                        )->setIsCustomerNotified(true)->save();
                    }

					// # send new email
					// $order->setCanSendNewEmailFlag(true);
					// $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
					// $objectManager->create('Magento\Sales\Model\OrderNotifier')->notify($order);

                    // $create_invoice_after_order =  $this->config->getConfigData(Config::KEY_GETEPAY_INVOICE_AFTER_ORDER);
                    //     if ($create_invoice_after_order != '0') {
                    //     // Capture invoice when payment is successful
                    //     $invoice = $this->invoiceService->prepareInvoice($order);
                    //     $invoice->setRequestedCaptureCase(Invoice::CAPTURE_ONLINE);
                    //     $invoice->register();

                    //     // Save the invoice to the order
                    //     $transactionn = $this->transactionModel
                    //         ->addObject($invoice)
                    //         ->addObject($invoice->getOrder());

                    //     $transactionn->save();

                    //     // Magento\Sales\Model\Order\Email\Sender\InvoiceSender
                    //     $send_invoice_email =   $this->config->getConfigData(Config::KEY_GETEPAY_INVOICE_EMAIL);

                    //     if ($send_invoice_email != '0') {
                    //         $this->invoiceSender->send($invoice);
                    //         $order->addStatusHistoryComment(
                    //             __('Notified customer about invoice #%1.', $invoice->getId())
                    //         )->setIsCustomerNotified(true)->save();
                    //     }
                    // }
                    $order->setTotalPaid($order->getGrandTotal());
                    $payment->save();
                    $order->save();
                    $this->logger->info("Payment for $getepayTxnId was credited.");

        
            } 
            elseif( $json->txnStatus == "FAILED" ) {
                    
                    $transaction = $this->transactionRepository->getByTransactionId("-1", $payment->getId(), $order->getId());
                    $transaction->setTxnId($getepayTxnId);
                    $transaction->setAdditionalInformation("Getepay Transaction Id", $getepayTxnId);
                    $transaction->setAdditionalInformation("status", "successful");
                    $transaction->setIsClosed(true);
                    $transaction->save();
                    $payment->addTransactionCommentsToOrder($transaction, "The transaction is failed");
                    // try {
                    //     $items = $order->getItemsCollection();
                    //     foreach ($items as $item) {
                    //         $this->cart->addOrderItem($item);
                    //     }
                    //     $this->cart->save();
                    // } catch (\Exception $e) {
                    //     $message = $e->getMessage();
                    //     $this->logger->info("Not able to add Items to cart Exception Message" . $message);
                    // }
                    //$order->cancel();
                    $order->setState("canceled")->setStatus("canceled");
                    $payment->setParentTransactionId(null);
                    $payment->save();
                    $order->save();
            }
        }

    }

}

