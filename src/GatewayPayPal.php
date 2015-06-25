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
use PayPal\Api\CreditCard;
use PayPal\Api\FundingInstrument;
use PayPal\Api\Item;
use PayPal\Api\ItemList;
use PayPal\Api\Details;
use PayPal\Api\Amount;
use PayPal\Api\Transaction;
use PayPal\Api\Payment;
use PayPal\Api\Payer;
use PayPal\Exception\PayPalConnectionException;
use Amsgames\LaravelShop\Exceptions\CheckoutException;
use Amsgames\LaravelShop\Exceptions\GatewayException;
use Amsgames\LaravelShop\Exceptions\ShopException;
use Amsgames\LaravelShop\Core\PaymentGateway;
use Illuminate\Support\Facades\Config;

class GatewayPayPal extends PaymentGateway
{
    /**
     * PayPal's api context.
     * @var object
     */
    protected $apiContext;

    /**
     * PayPal's credit card.
     * @var object
     */
    protected $creditCard = null;

    /**
     * PayPal's credit card.
     * @var object
     */
    protected $validTypes = ['visa', 'mastercard', 'amex', 'discover'];

    /**
     * Called on cart checkout.
     *
     * @param Cart $cart Cart.
     */
    public function onCheckout($cart)
    {
        if (!isset($this->creditCard))
            throw new CheckoutException('Credit Card is not set.', 0);

        if (!in_array($this->creditCard->getType(), $this->validTypes))
            throw new CheckoutException('Credit Card is not supported.', 1);

        if ($this->getPatternType($this->creditCard->getNumber()) != $this->creditCard->getType())
            throw new CheckoutException('Credit Card is invalid.', 2);
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
        if (!isset($this->creditCard))
            throw new GatewayException('Credit Card is not set.', 0);

        try {

            $this->setContext();

            $instrument = new FundingInstrument();

            $instrument->setCreditCard($this->creditCard);

            $payer = new Payer();
        
            $payer->setPaymentMethod('credit_card')
                ->setFundingInstruments([$instrument]);

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

            $payment = new Payment();

            $payment->setIntent('sale')
                ->setPayer($payer)
                ->setTransactions([$transaction]);

            //$request = clone $payment;

            $payment->create($this->apiContext);

            $this->transactionId = $payment->id;

            $this->detail = 'Success';

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

            throw new ShopException(
                $e->getMessage(),
                1000,
                $e
            );

        }

        return false;
    }

    /**
     * Sets credit card for usage.
     *
     * @param string $type        Card type. i.e. visa, mastercard
     * @param int    $number      Card number.
     * @param mixed  $expireMonth Month in which the card expires.
     * @param mixed  $expireYear  Year in which the card expires.
     * @param int    $cvv         CVV.
     * @param string $firstname   First name printed in card.
     * @param string $lastname    Last name printed in card.
     */
    public function setCreditCard(
        $type,
        $number,
        $expireMonth,
        $expireYear,
        $cvv,
        $firstname,
        $lastname
    ) {
        $this->creditCard = new CreditCard();

        $this->creditCard->setType($type)
            ->setNumber($number)
            ->setExpireMonth($expireMonth)
            ->setExpireYear($expireYear)
            ->setCvv2($cvv)
            ->setFirstName($firstname)
            ->setLastName($lastname);

        return $this;
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

    /**
     * Returns the credit card type based on a credit card number pattern.
     *
     * @param string $cardNumber Credit card number.
     *
     * @return string
     */
    private function getPatternType($cardNumber)
    {
        if (empty($cardNumber)) return;

        $types = [
            'visa'          => '(4\d{12}(?:\d{3})?)',
            'amex'          => '(3[47]\d{13})',
            'jcb'           => '(35[2-8][89]\d\d\d{10})',
            'maestro'       => '((?:5020|5038|6304|6579|6761)\d{12}(?:\d\d)?)',
            'solo'          => '((?:6334|6767)\d{12}(?:\d\d)?\d?)',
            'mastercard'    => '(5[1-5]\d{14})',
            'switch'        => '(?:(?:(?:4903|4905|4911|4936|6333|6759)\d{12})|(?:(?:564182|633110)\d{10})(\d\d)?\d?)',
        ];
        $names      = ['visa','amex', 'jcb', 'maestro', 'solo', 'mastercard', 'switch'];

        $matches    = [];

        $pattern    = "#^(?:" . implode('|', $types)  . ")$#";

        $result     = preg_match($pattern, str_replace(' ', '', $cardNumber), $matches);

        return $result > 0 ? $names[sizeof($matches)-2] : false;
    }
}