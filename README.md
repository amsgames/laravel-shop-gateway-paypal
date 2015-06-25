PAYPAL GATEWAY (for Laravel Shop Package)
--------------------------------

PayPal Gateway solution for [Laravel Shop](https://github.com/amsgames/laravel-shop).

## Gateways

This package comes with:

* PayPal Direct Credit Card payment gateway
* PayPal Express payment gateway

## Contents

- [Installation](#installation)
- [Configuration](#configuration)
    - [Authentication](#authentication)
- [Gateway Usage](#gateway-usage)
    - [Direct Credit Card](#direct-credit-card)
    - [PayPal Express](#paypal-express)
- [License](#license)

## Installation

In order to install Laravel Shop, you can run

```json
"composer require amsgames/laravel-shop-gateway-paypal": "dev-master"
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

Set you PayPal app authentication settings in `config/services.php`, like:

```php
    'paypal' => [
        'account' => env('PAYPAL_ACCOUNT', 'account@domain.com'),
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

**NOTE:** If you are calling `Shop::checkout()` in a different controller or view than `Shop::placeOrder`, be sure to recall `setCreditCard()` before calling `Shop::placeOrder()` in the second controller.

## License

This package is free software distributed under the terms of the MIT license.