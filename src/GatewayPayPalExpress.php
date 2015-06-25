<?php

namespace Amsgames\LaravelShopGatewayPaypal;

/**
 * Gateway that adds PayPal payments to Laravel Shop.
 *
 * @author Alejandro Mostajo
 * @copyright Amsgames, LLC
 * @license MIT
 * @package Amsgames\LaravelShop
 */

use PayPal\Rest\ApiContext;
use PayPal\Auth\OAuthTokenCredential;
use PayPal\Api\Item;
use PayPal\Api\ItemList;
use PayPal\Api\Details;
use PayPal\Api\Amount;
use PayPal\Api\Transaction;
use PayPal\Api\Payment;
use PayPal\Api\Payer;
use PayPal\Api\RedirectUrls;
use PayPal\Api\PaymentExecution;
use PayPal\Exception\PayPalConnectionException;
use Amsgames\LaravelShop\Exceptions\CheckoutException;
use Amsgames\LaravelShop\Exceptions\GatewayException;
use Amsgames\LaravelShop\Exceptions\ShopException;
use Amsgames\LaravelShop\Core\PaymentGateway;
use Illuminate\Support\Facades\Config;

class GatewayPayPalExpress extends PaymentGateway
{
    /**
     * PayPal's api context.
     * @var object
     */
    protected $apiContext;

    /**
     * Approval URL to redirect to.
     */
    protected $approvalUrl = '';

    /**
     * Returns paypal url for approval.
     *
     * @return string
     */
    public function getApprovalUrl()
    {
        return $this->approvalUrl;
    }

    /**
     * Called by shop to charge order's amount.
     *
     * @param Cart $cart Cart.
     *
     * @return bool
     */
    public function onCharge($order)
    {
        $this->statusCode = 'pending';

        // Begin paypal
        try {

            $this->setContext();

            $payer = new Payer();
        
            $payer->setPaymentMethod('paypal');

            $list = new ItemList();
            
            $list->setItems($this->toPayPalItems($order));
            
            $details = new Details();
            
            $details->setShipping($order->totalShipping)
                ->setTax($order->totalTax)
                ->setSubtotal($order->totalPrice);

            $amount = new Amount();

            $amount->setCurrency(Config::get('shop.currency'))
                ->setTotal($order->total)
                ->setDetails($details);

            $transaction = new Transaction();

            $transaction->setAmount($amount)
                ->setItemList($list)
                ->setDescription(sprintf(
                    '%s payment, Order #%d',
                    Config::get('shop.name'),
                    $order->id
                ))
                ->setInvoiceNumber($order->id);

            $redirectUrls = new RedirectUrls();
            
            $redirectUrls->setReturnUrl($this->callbackSuccess)
                ->setCancelUrl($this->callbackFail);

            $payment = new Payment();

            $payment->setIntent('sale')
                ->setPayer($payer)
                ->setRedirectUrls($redirectUrls)
                ->setTransactions([$transaction]);

            //$request = clone $payment;

            $payment->create($this->apiContext);

            $this->approvalUrl = $payment->getApprovalLink();

            $this->detail = sprintf('Pending approval: %s', $this->approvalUrl);

            return true;

        } catch (PayPalConnectionException $e) {

            $response = json_decode($e->getData());

            throw new GatewayException(
                sprintf(
                    '%s: %s',
                    $response->name,
                    isset($response->message) ? $response->message : 'Paypal payment Failed.'
                ),
                1001,
                $e
            );

        } catch (\Exception $e) {

            throw new GatewayException(
                $e->getMessage(),
                1000,
                $e
            );

        }

        return false;
    }

    /**
     * Called on callback.
     *
     * @param Order $order Order.
     * @param mixed $data  Request input from callback.
     *
     * @return bool
     */
    public function onCallbackSuccess($order, $data = null)
    {
        $paymentId  = is_array($data) ? $data['paymentId'] : $data->paymentId;

        $payerId    = is_array($data) ? $data['PayerID'] : $data->PayerID;

        $this->statusCode = 'failed';

        $this->detail = sprintf('Payment failed. Ref: %s', $paymentId);

        // Begin paypal
        try {

            $this->setContext();

            $payment = Payment::get($paymentId, $this->apiContext);
            
            $execution = new PaymentExecution();

            $execution->setPayerId($payerId);

            $payment->execute($execution, $this->apiContext);

            $payment = Payment::get($paymentId, $this->apiContext);

            $this->statusCode = 'completed';

            $this->transactionId = $payment->id;

            $this->detail = 'Success';

        } catch (PayPalConnectionException $e) {

            $response = json_decode($e->getData());

            throw new GatewayException(
                sprintf(
                    '%s: %s',
                    $response->name,
                    isset($response->message) ? $response->message : 'Paypal payment Failed.'
                ),
                1001,
                $e
            );

        } catch (\Exception $e) {

            throw new GatewayException(
                $e->getMessage(),
                1000,
                $e
            );

        }
    }

    /**
     * Setups contexts for api calls.
     */
    private function setContext()
    {
        $this->apiContext = new ApiContext(new OAuthTokenCredential(
            Config::get('services.paypal.client_id'),
            Config::get('services.paypal.secret')
        ));

        if (!Config::get('services.paypal.sandbox'))
            $this->apiContext->setConfig(['mode' => 'live']);
    }

    /**
     * Converts the items in the order into paypal items for purchase.
     *
     * @param object $order Order.
     *
     * @return array
     */
    private function toPayPalItems($order)
    {
        $items = [];

        foreach ($order->items as $shopItem) {

            $item = new Item();

            $item->setName(substr($shopItem->displayName, 0, 127))
                ->setDescription($shopItem->sku)
                ->setCurrency($shopItem->currency)
                ->setQuantity($shopItem->quantity)
                ->setTax($shopItem->tax)
                ->setPrice($shopItem->price);

            $items[] = $item;
        }

        return $items;
    }
}