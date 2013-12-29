<?php

/**
 * PaypalBundle for Symfony2
 *
 * This Bundle is part of Symfony2 Payment Suite
 *
 * @author Mickael Andrieu <mickael.andrieu@sensiolabs.com>
 * @package PaypalBundle
 *
 * Mickael Andrieu 2013
 */

namespace Mandrieu\PaypalBundle\Services;

use Mmoreram\PaymentCoreBundle\Services\Interfaces\PaymentBridgeInterface;
use Mmoreram\PaymentCoreBundle\Exception\PaymentAmountsNotMatchException;
use Mmoreram\PaymentCoreBundle\Exception\PaymentOrderNotFoundException;
use Mandrieu\PaypalBundle\Services\Wrapper\PaypalTransactionWrapper;
use Mmoreram\PaymentCoreBundle\Services\PaymentEventDispatcher;
use Mmoreram\PaymentCoreBundle\Exception\PaymentException;
use Mandrieu\PaypalBundle\PaypalMethod;
use PayPal\Rest\ApiContext;
use PayPal\Api\Payment;
use PayPal\Api\Transaction;

/**
 * Paypal manager
 */
class PaypalManager
{
    /**
     * @var PaymentEventDispatcher
     *
     * Payment event dispatcher
     */
    protected $paymentEventDispatcher;


    /**
     * @var PaypalTransactionWrapper
     *
     * Paypal transaction wrapper
     */
    protected $paypalTransactionWrapper;


    /**
     * @var PaymentBridgeInterface
     *
     * Payment bridge interface
     */
    protected $paymentBridge;


    /**
     * Construct method for paypal manager
     *
     * @param PaymentEventDispatcher $paymentEventDispatcher Event dispatcher
     * @param PaypalTransactionWrapper $paypalTransactionWrapper Paypal transaction wrapper
     * @param PaymentBridgeInterface $paymentBridge Payment Bridge
     */
    public function __construct(PaymentEventDispatcher $paymentEventDispatcher, PaypalTransactionWrapperWrapper $paypalTransactionWrapper, PaymentBridgeInterface $paymentBridge)
    {
        $this->paymentEventDispatcher = $paymentEventDispatcher;
        $this->paypalTransactionWrapper = $paypalTransactionWrapper;
        $this->paymentBridge = $paymentBridge;
    }


    /**
     * Tries to process a payment through Paypal
     *
     * @param PaypalMethod $paymentMethod Payment method
     * @param float         $amount        Amount
     *
     * @return PaypalManager Self object
     *
     * @throws PaymentAmountsNotMatchException
     * @throws PaymentOrderNotFoundException
     * @throws PaymentException
     */
    public function processPayment(PaypalMethod $paymentMethod, $amount)
    {
        /// first check that amounts are the same
        $paymentBridgeAmount = (float) $this->paymentBridge->getAmount() * 100;

        /**
         * If both amounts are different, execute Exception
         */
        if (abs($amount - $paymentBridgeAmount) > 0.00001) {

            throw new PaymentAmountsNotMatchException;
        }


        /**
         * At this point, order must be created given a card, and placed in PaymentBridge
         *
         * So, $this->paymentBridge->getOrder() must return an object
         */
        $this->paymentEventDispatcher->notifyPaymentOrderLoad($this->paymentBridge, $paymentMethod);

        /**
         * Order Not found Exception must be thrown just here
         */
        if (!$this->paymentBridge->getOrder()) {

            throw new PaymentOrderNotFoundException;
        }


        /**
         * Order exists right here
         */
        $this->paymentEventDispatcher->notifyPaymentOrderCreated($this->paymentBridge, $paymentMethod);

        /**
         * Execute the payment
         */
        $extraData = $this->paymentBridge->getExtraData();
        $amount = $paymentBridgeAmount;
        $itemList = $extraData['itemList'];
        $description = $extraData['description'];
        $payer = $paymentMethod->getPayer();


        $payment = $this
            ->paypalTransactionWrapper
            ->executePayment($amount, $description, $itemList, $payer);

        $this->processTransaction($payment, $paymentMethod);

        return $this;
    }


    /**
     * Given a paypal Api Context response  perform desired operations
     *
     * @param Payment $payment Paypal payment
     * @param PaypalMethod $paymentMethod Payment method
     *
     * @throws PaymentException
     * @return PaypalManager Self object
     *
     */
    private function processTransaction(Payment $payment, PaypalMethod $paymentMethod)
    {

        /**
         * Payment paid done
         *
         * Paid process has ended ( No matters result )
         */
        $this->paymentEventDispatcher->notifyPaymentOrderDone($this->paymentBridge, $paymentMethod);

        /**
         * when a transaction is successful, it is marked as 'closed'
         */
        if (empty($payment['state']) || $payment['state'] != 'failed') {

            /**
             * Payment paid failed
             *
             * Paid process has ended failed
             */
            $this->paymentEventDispatcher->notifyPaymentOrderFail($this->paymentBridge, $paymentMethod);

            throw new PaymentException;
        }


        /**
         * Adding to PaymentMethod transaction information
         *
         * This information is only available in PaymentOrderSuccess event
         */
        $paymentMethod
            ->setTransactionId($payment['id'])
            ->setTransactionStatus($payment['status'])
            ->setTransaction($payment);

        /**
         * Payment paid successfully
         *
         * Paid process has ended successfully
         */
        $this->paymentEventDispatcher->notifyPaymentOrderSuccess($this->paymentBridge, $paymentMethod);

        return $this;
    }
}