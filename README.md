PAYPAL GATEWAY (for Laravel Shop Package)
--------------------------------

[![Latest Stable Version](https://poser.pugx.org/amsgames/laravel-shop-gateway-paypal/v/stable)](https://packagist.org/packages/amsgames/laravel-shop-gateway-paypal)
[![Total Downloads](https://poser.pugx.org/amsgames/laravel-shop-gateway-paypal/downloads)](https://packagist.org/packages/amsgames/laravel-shop-gateway-paypal)
[![License](https://poser.pugx.org/amsgames/laravel-shop-gateway-paypal/license)](https://packagist.org/packages/amsgames/laravel-shop-gateway-paypal)

PayPal Gateway solution for [Laravel Shop](https://github.com/amsgames/laravel-shop).

## Gateways

This package comes with:

* Direct Credit Card payments

* PayPal Express payments

## Contents

- [Installation](#installation)
- [Configuration](#configuration)
    - [Authentication](#authentication)
- [Gateway Usage](#gateway-usage)
    - [Direct Credit Card](#direct-credit-card)
    - [PayPal Express](#paypal-express)
        - [Configuration](#configuration-1)
        - [Usage](#usage)
- [License](#license)
- [Additional Information](#aditional-information)

## Installation

In order to install Laravel Shop, you can run

```json
"amsgames/laravel-shop-gateway-paypal": "v1.0.0"
```

to your composer.json. Then run `composer install` or `composer update`.

Then in your `config/shop.php` add 

```php
'paypal'            =>  Amsgames\LaravelShopGatewayPaypal\GatewayPayPal::class,
'paypalExpress'     =>  Amsgames\LaravelShopGatewayPaypal\GatewayPayPalExpress::class,
```
    
in the `gateways` array.

## Configuration

### Authentication

Set your PayPal app authentication credentials in `config/services.php`, like:

```php
    'paypal' => [
        'client_id' => env('PAYPAL_CLIENT_ID', ''),
        'secret' => env('PAYPAL_SECRET', ''),
        'sandbox' => env('PAYPAL_SANDBOX', true),
    ],
```

**NOTE:** Change `sandbox` to false when going live.

## Gateway Usage

### Direct Credit Card

The only additional step needed for this to work is to add the credit card information before `checkout` and before `order placement`, like:

```php
// (1) - Set gateway
Shop::setGateway('paypal');

// (2) - Add credit card for validation
Shop::gateway()->setCreditCard(
  $cartType   = 'visa',
  $cardNumber = '4111111111111111',
  $month      = '1',
  $year       = '2019',
  $cvv        = '123',
  $firstname  = 'John',
  $lastname   = 'Doe'
);

// (3) - Call checkout
if (!Shop::checkout()) {
  echo Shop::exception()->getMessage(); // echos: card validation error.
}

// (4) - Create order
$order = Shop::placeOrder();

// (5) - Review payment
if ($order->hasFailed) {

  echo Shop::exception()->getMessage(); // echos: payment error.

}
```

**NOTE:** If you are calling `Shop::checkout()` in a different controller or view than `Shop::placeOrder`, be sure to recall `setCreditCard()` before calling `Shop::placeOrder()` in your second controller. This package does not stores credit card data.

**RECOMMENDATION:** Use SSL to secure you checkout flow when dealing with credit cards.

### PayPal Express

If you don't want to deal with credit card forms and SSL, you can us PayPal Express instead. PayPal Express handles the payment process outside your website and returns with the results.

Look at [PayPal's documentation](https://developer.paypal.com/docs/classic/express-checkout/integration-guide/ECGettingStarted/) for more information about this process.

#### Configuration

PayPal will callback Laravel Shop and this gateway with the results. Laravel Shop will then redirect the customer to the route name set in `config/shop.php` and will pass by as parameter the `Order Id`. Set up this route before using this gateway.

```php
    /*
    |--------------------------------------------------------------------------
    | Redirect route after callback
    |--------------------------------------------------------------------------
    |
    | Which route to call after the callback has been processed.
    |
    */
    'callback_redirect_route' => 'home',
```

#### Usage

```php
// (1) - Set gateway
Shop::setGateway('paypalExpress');

// (2) - Call checkout / OPTIONAL
// You can call this to keep a standard flow
// Although this step for this gateway is not needed.
Shop::checkout();

// (3) - Create order
$order = Shop::placeOrder();

// (4) - Review order and redirect to payment
if ($order->isPending) {

  // PayPal URL to redirect to proceed with payment
  $approvalUrl = Shop::gateway()->getApprovalUrl();

  // Redirect to url
  return redirect($approvalUrl);
}

// (5) - Callback
// You don't have to do anything.
// Laravel Shop will handle the callback and redirect the customer to the configured route.
```

## License

This package is free software distributed under the terms of the MIT license.

## Additional Information

This package uses the official [PayPal PHP SDK](https://github.com/paypal/PayPal-PHP-SDK).