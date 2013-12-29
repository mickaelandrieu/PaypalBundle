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

namespace Mandrieu\PaypalBundle\Services\Wrapper;
use PayPal\Rest\ApiContext;
use PayPal\Auth\OAuthTokenCredential;
use PayPal\Api\Payer;
use PayPal\Api\Payment;
use PayPal\Api\Transaction;


/**
 * Paypal transaction wrapper
 */
class PaypalTransactionWrapper
{

    /**
     * @var ApiContext $apiContext
     *
     * Paypal Transaction
     */
    private $apiContext;

    /**
     * Construct method for paypal apiContext wrapper
     *
     * @param string $client_id Client ID
     * @param string $secret Secret key
     * @param string $mode Mode
     * @param int $httpConnectionTimeout http connection timeout
     * @param boolean $logEnabled log enabled
     * @param string $logFilename path to the log file
     * @param string $logLevel log level
     */
    public function __construct($client_id, $secret, $mode, $httpConnectionTimeout, $logEnabled, $logFilename, $logLevel)
    {
        $this->apiContext = new ApiContext(new OAuthTokenCredential($client_id, $secret));
        $this->apiContext->setConfig(array(
            'mode' => $mode,
            'http.ConnectionTimeOut' => $httpConnectionTimeout,
            'log.LogEnabled' => $logEnabled,
            'log.FileName' => $logFilename,
            'log.LogLevel' => $logLevel
        ));
    }

    /**
     * Create new Transaction with a set of params
     *
     * @param int $amount amount of the transaction
     * @param string $description description of the transaction
     * @param ItemList $itemList list of items concerned by transaction
     * @param Payer $payer payer of the payment
     *
     * @return Transaction Paypal transaction object
     */
    public function executePayment($amount, $description, $itemList, $payer)
    {
        $transaction = new Transaction();
        $transaction->setAmount($amount)
                    ->setItemList($itemList)
                    ->setDescription($description);

        $payment = new Payment();
        $payment->setIntent("sale")
            ->setPayer($payer)
            ->setTransactions(array($transaction));

        try {
            $payment->create($this->apiContext);
        } catch (PayPal\Exception\PPConnectionException $ex) {

        }

    }

}