<?php

namespace Getepay\Getepe\Constants;

class OrderCronStatus
{
    const NEW_ORDER = 0;
    const PAYMENT_AUTHORIZED_COMPLETED = 1;
    const ORDER_PAID_AFTER_MANUAL_CAPTURE = 2;
    const INVOICE_GENERATED = 3;
    const INVOICE_GENERATION_NOT_POSSIBLE = 4;
    const PAYMENT_AUTHORIZED_CRON_REPEAT = 5;
}
