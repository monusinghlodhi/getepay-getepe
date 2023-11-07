<?php

namespace Getepay\Getepe\Model;

use \Magento\Framework\App\Config\ScopeConfigInterface;
use \Magento\Framework\App\Config\Storage\WriterInterface;

class Config
{
    const KEY_ALLOW_SPECIFIC = 'allowspecific';
    const KEY_SPECIFIC_COUNTRY = 'specificcountry';
    const KEY_ACTIVE = 'active';

    const KEY_GETEPAY_MID = 'getepay_mid';
    const KEY_GETEPAY_TERMINAL_ID = 'terminalId';
    const KEY_GETEPAY_KEY = 'getepay_key';
    const KEY_GETEPAY_IV = 'getepay_iv';
    const KEY_PMT_CHK_URL = 'pmt_chk_url';
    

    const KEY_MERCHANT_NAME_OVERRIDE = 'merchant_name_override';
    const KEY_PAYMENT_ACTION = 'rzp_payment_action';
    const KEY_AUTO_INVOICE = 'auto_invoice';
    const KEY_NEW_ORDER_STATUS = 'order_status';
    const ENABLE_WEBHOOK = 'enable_webhook';
    const WEBHOOK_SECRET = 'webhook_secret';
    const ENABLE_PENDING_ORDERS_CRON = 'enable_pending_orders_cron';
    const PENDING_ORDER_TIMEOUT = 'pending_orders_timeout';
    const ENABLE_RESET_CART_CRON = 'enable_reset_cart_cron';
    const RESET_CART_ORDERS_TIMEOUT = 'reset_cart_orders_timeout';
    const DISABLE_UPGRADE_NOTICE = 'disable_upgrade_notice';
    const ENABLE_CUSTOM_PAID_ORDER_STATUS = 'enable_custom_paid_order_status';
    const CUSTOM_PAID_ORDER_STATUS = 'custom_paid_order_status';
    const ENABLE_UPDATE_ORDER_CRON_V1 = 'enable_update_order_cron_v1';
    /**
     * @var string
     */
    protected $methodCode = 'getepay';

    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;

    protected $configWriter;

    /**
     * @var int
     */
    protected $storeId = null;

    /**
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        WriterInterface $configWriter
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->configWriter = $configWriter;
    }

    /**
     * @return string
     */
    public function getMerchantNameOverride()
    {
        return $this->getConfigData(self::KEY_MERCHANT_NAME_OVERRIDE);
    }

    public function getGetepayKeyId()
    {
        return $this->getConfigData(self::KEY_GETEPAY_KEY);
    }

    public function getGetepayMid()
    {
        return $this->getConfigData(self::KEY_GETEPAY_MID);
    }

    public function getGetepayTerminalId()
    {
        return $this->getConfigData(self::KEY_GETEPAY_TERMINAL_ID);
    }

    public function getGetepayIv()
    {
        return $this->getConfigData(self::KEY_GETEPAY_IV);
    }

    public function getGetepayPmtChkUrl()
    {
        return $this->getConfigData(self::KEY_PMT_CHK_URL);
    }
    
    public function isCancelPendingOrderCronEnabled()
    {
        return (bool) (int) $this->getConfigData(self::ENABLE_PENDING_ORDERS_CRON, $this->storeId);
    }

    public function getPendingOrderTimeout()
    {
        return (int) $this->getConfigData(self::PENDING_ORDER_TIMEOUT);
    }

    public function isCancelResetCartOrderCronEnabled()
    {
        return (bool) (int) $this->getConfigData(self::ENABLE_RESET_CART_CRON, $this->storeId);
    }

    public function getResetCartOrderTimeout()
    {
        return (int) $this->getConfigData(self::RESET_CART_ORDERS_TIMEOUT);
    }

    public function isUpdateOrderCronV1Enabled()
    {
        return (bool) (int) $this->getConfigData(self::ENABLE_UPDATE_ORDER_CRON_V1, $this->storeId);
    }
    
    public function isCustomPaidOrderStatusEnabled()
    {
        return (bool) (int) $this->getConfigData(self::ENABLE_CUSTOM_PAID_ORDER_STATUS, $this->storeId);
    }

    public function getCustomPaidOrderStatus()
    {
        return $this->getConfigData(self::CUSTOM_PAID_ORDER_STATUS);
    }

    public function getPaymentAction()
    {
        return $this->getConfigData(self::KEY_PAYMENT_ACTION);
    }

    public function getNewOrderStatus()
    {
        return $this->getConfigData(self::KEY_NEW_ORDER_STATUS);
    }

    /**
     * @param int $storeId
     * @return $this
     */
    public function setStoreId($storeId)
    {
        $this->storeId = $storeId;
        return $this;
    }

    /**
     * Retrieve information from payment configuration
     *
     * @param string $field
     * @param null|string $storeId
     *
     * @return mixed
     */
    public function getConfigData($field, $storeId = null)
    {
        if ($storeId == null) {
            $storeId = $this->storeId;
        }

        $code = $this->methodCode;

        $path = 'payment/' . $code . '/' . $field;
        return $this->scopeConfig->getValue($path, \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $storeId);
    }

    /**
     * Set information from payment configuration
     *
     * @param string $field
     * @param string $value
     * @param null|string $storeId
     *
     * @return mixed
     */
    public function setConfigData($field, $value)
    {
        $code = $this->methodCode;

        $path = 'payment/' . $code . '/' . $field;

        return $this->configWriter->save($path, $value);
    }

    /**
     * @return bool
     */
    public function isActive()
    {
        return (bool) (int) $this->getConfigData(self::KEY_ACTIVE, $this->storeId);
    }

    /**
     * @return bool
     */
    public function canAutoGenerateInvoice()
    {
        return (bool) (int) $this->getConfigData(self::KEY_AUTO_INVOICE, $this->storeId);
    }

    /**
     * To check billing country is allowed for the payment method
     *
     * @param string $country
     * @return bool
     */
    public function canUseForCountry($country)
    {
        /*
        for specific country, the flag will set up as 1
        */
        if ($this->getConfigData(self::KEY_ALLOW_SPECIFIC) == 1) {
            $availableCountries = explode(',', $this->getConfigData(self::KEY_SPECIFIC_COUNTRY));
            if (!in_array($country, $availableCountries)) {
                return false;
            }
        }

        return true;
    }
}
