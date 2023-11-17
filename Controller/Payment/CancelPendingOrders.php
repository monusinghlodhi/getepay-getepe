<?php
namespace Getepay\Getepe\Controller\Payment;
use \Magento\Sales\Model\Order;
//callback url : https://monu.magento.com/getepay/payment/cancelpendingorders
class CancelPendingOrders extends \Getepay\Getepe\Controller\BaseController
{
    /**
     * @var \Magento\Sales\Api\OrderRepositoryInterface
     */
    protected $orderRepository;
    /**
     * @var \Magento\Framework\Api\SearchCriteriaBuilder
     */
    protected $searchCriteriaBuilder;

    /**
     * @var \Magento\Sales\Api\OrderManagementInterface
     */
    protected $orderManagement;

    /**
     * @var STATUS_PENDING
     */
    protected const STATUS_PENDING = 'pending';

    /**
     * @var STATUS_CANCELED
     */
    protected const STATUS_CANCELED = 'canceled';

    /**
     * @var STATE_NEW
     */
    protected const STATE_NEW = 'new';

    /**
     * @var \Magento\Framework\Api\SortOrderBuilder
     */
    protected $sortOrderBuilder;

    /**
     * @var \Getepay\Getepe\Model\Config
     */
    protected $config;

    /**
     * @var \Magento\Customer\Model\Session
     */
    protected $customerSession;

    /**
     * @var \Magento\Checkout\Model\Session
     */
    protected $checkoutSession;
    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $logger;

    protected $isCancelPendingOrderCronEnabled;

    protected $pendingOrderTimeout;

    protected $isCancelResetCartCronEnabled;

    protected $resetCartOrderTimeout;

    /**
     * CancelOrder constructor.
     * @param \Magento\Framework\App\Action\Context $context
     * @param \Magento\Customer\Model\Session $customerSession
     * @param \Magento\Checkout\Model\Session $checkoutSession
     * @param \Magento\Sales\Api\OrderRepositoryInterface $orderRepository
     * @param \Magento\Framework\Api\SearchCriteriaBuilder $searchCriteriaBuilder
     * @param \Magento\Framework\Api\SortOrderBuilder $sortOrderBuilder
     * @param \Magento\Sales\Api\OrderManagementInterface $orderManagement
     * @param \Getepay\Getepe\Model\Config $config
     * @param \Psr\Log\LoggerInterface $logger
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Customer\Model\Session $customerSession,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Sales\Api\OrderRepositoryInterface $orderRepository,
        \Magento\Framework\Api\SearchCriteriaBuilder $searchCriteriaBuilder,
        \Magento\Framework\Api\SortOrderBuilder $sortOrderBuilder,
        \Magento\Sales\Api\OrderManagementInterface $orderManagement,
        \Getepay\Getepe\Model\Config $config,
        \Psr\Log\LoggerInterface $logger
    )
    {
        parent::__construct($context, $customerSession, $checkoutSession, $config);
        $this->checkoutSession                 = $checkoutSession;
        $this->customerSession                 = $customerSession;
        $this->orderRepository                 = $orderRepository;
        $this->searchCriteriaBuilder           = $searchCriteriaBuilder;
        $this->sortOrderBuilder                = $sortOrderBuilder;
        $this->orderManagement                 = $orderManagement;
        $this->config                          = $config;
        $this->logger                          = $logger;
        $this->isCancelPendingOrderCronEnabled = $this->config->isCancelPendingOrderCronEnabled();
        $this->pendingOrderTimeout             = ($this->config->getPendingOrderTimeout() > 0) ? $this->config->getPendingOrderTimeout() : 2880;
        $this->isCancelResetCartCronEnabled    = $this->config->isCancelResetCartOrderCronEnabled();
        $this->resetCartOrderTimeout           = ($this->config->getResetCartOrderTimeout() > 0) ? $this->config->getResetCartOrderTimeout() : 2880;
    }

    public function execute()
    {        
        // Execute only if Cancel Pending Order Cron is Enabled
        if ($this->isCancelPendingOrderCronEnabled === true
            && $this->pendingOrderTimeout > 0)
        {
            $this->logger->info("Cronjob: Cancel Pending Order Cron started.");
           echo 'Pending Orders Timeout: '. date('Y-m-d H:i:s', strtotime('-' . $this->pendingOrderTimeout . ' minutes'));
            $dateTimeCheck = date('Y-m-d H:i:s', strtotime('-' . $this->pendingOrderTimeout . ' minutes'));
            $sortOrder = $this->sortOrderBuilder->setField('entity_id')->setDirection('DESC')->create();
            $searchCriteria = $this->searchCriteriaBuilder
            ->addFilter(
                'updated_at',
                $dateTimeCheck,
                'lt'
            )->addFilter(
                'state',
                static::STATE_NEW,
                'eq'
            )->addFilter(
               'status',
               static::STATUS_PENDING,
               'eq'
            )->setSortOrders(
                [$sortOrder]
            )->create();            

            $orders = $this->orderRepository->getList($searchCriteria);
            foreach ($orders->getItems() as $order) {
                $updated_at = $order->getUpdatedAt();
                $increment_id = $order->getIncrementId();
                $payment_metghod = $order->getPayment()->getMethod();
                echo '<br>======================================================';
                echo '<br> Order Update At : ' . $updated_at .'<br>';
                echo '<br> Order Increment ID : ' . $increment_id .'<br>';
                echo '<br> Payment Method : ' . $payment_metghod .'<br>';
                echo '======================================================';

            }
            // echo '<pre>';
            // var_dump($orders);
            // exit;
            foreach ($orders->getItems() as $order)
            {
                if ($order->getPayment()->getMethod() === 'getepay') {
                    $this->cancelOrder($order);    
                }
            }
        } else
        {
            $this->logger->critical('Cronjob: isCancelPendingOrderCronEnabled:'
             . $this->isCancelPendingOrderCronEnabled . ', '
            . 'pendingOrderTimeout:' . $this->pendingOrderTimeout);
        }

        // Execute only if Reset Cart Cron is Enabled
        if ($this->isCancelResetCartCronEnabled === true
            && $this->resetCartOrderTimeout > 0)
        {
            $this->logger->info("Cronjob: Cancel Reset Cart Order Cron started.");
            $dateTimeCheck = date('Y-m-d H:i:s', strtotime('-' . $this->resetCartOrderTimeout . ' minutes'));
            $sortOrder = $this->sortOrderBuilder->setField('entity_id')->setDirection('DESC')->create();
            $searchCriteria = $this->searchCriteriaBuilder
            ->addFilter(
                'updated_at',
                $dateTimeCheck,
                'lt'
            )->addFilter(
               'state',
               static::STATE_NEW,
               'eq'
            )->addFilter(
               'status',
               static::STATUS_PENDING,
               'eq'
            )->setSortOrders(
                [$sortOrder]
            )->create();

            $orders = $this->orderRepository->getList($searchCriteria);

            foreach ($orders->getItems() as $order)
            {
                if ($order->getPayment()->getMethod() === 'getepay') {
                    $this->cancelOrder($order);
                }
            }
        } else
        {
            $this->logger->critical('Cronjob: isCancelResetCartCronEnabled:'
             . $this->isCancelResetCartCronEnabled . ', '
            . 'resetCartOrderTimeout:' . $this->resetCartOrderTimeout);
        }
    }

    private function cancelOrder($order)
    {
        if ($order)
        {
            if ($order->canCancel()) {
                $this->logger->info("Cronjob: Cancelling Order ID: " . $order->getEntityId());

                $order->cancel()
                ->setState(
                    Order::STATE_CANCELED,
                    Order::STATE_CANCELED,
                    'Payment Failed',
                    false
                )->save();
            }
        }
    }
}
