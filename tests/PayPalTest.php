<?php

use App;
use Log;
use Shop;
use Config;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class PayPalTest extends TestCase
{
	/**
	 * Tests if gateway is integrated with shop.
	 */
	public function testGatewayIntegration()
	{
		Shop::setGateway('paypal');

		$this->assertEquals(Shop::getGateway(), 'paypal');

		$gateway = Shop::gateway();

		$this->assertNotNull($gateway);

		$this->assertNotEmpty($gateway->toJson());
	}

	/**
	 * Tests error handling when card is not set.
	 */
	public function testUnsetCreditCard()
	{
		// Prepare

		$user = factory('App\User')->create(['password' => Hash::make('laravel-shop')]);

		$bool = Auth::attempt(['email' => $user->email, 'password' => 'laravel-shop']);

		Shop::setGateway('paypal');

		$this->assertFalse(Shop::checkout());

		$this->assertEquals(Shop::exception()->getMessage(), 'Credit Card is not set.');

		$user->delete();
	}
	
	/**
	 * Tests all credit card validations made by the gateway.
	 */
	public function testCreditCardValidations()
	{
		// Prepare

		$user = factory('App\User')->create(['password' => Hash::make('laravel-shop')]);

		$bool = Auth::attempt(['email' => $user->email, 'password' => 'laravel-shop']);

		Shop::setGateway('paypal');

		Shop::gateway()->setCreditCard(
			'jcb',
			544997944656222244,
			1,
			date('Y') + 1,
			123,
			str_random(64),
			str_random(64)
		);

		$this->assertFalse(Shop::checkout());

		$this->assertEquals(Shop::exception()->getMessage(), 'Credit Card is not supported.');

		Shop::gateway()->setCreditCard(
			'visa',
			445777777777777,
			1,
			date('Y') + 1,
			123,
			str_random(64),
			str_random(64)
		);

		$this->assertFalse(Shop::checkout());

		$this->assertEquals(Shop::exception()->getMessage(), 'Credit Card is invalid.');

		Shop::gateway()->setCreditCard(
			Config::get('testing.paypal.creditcard.type'),
			Config::get('testing.paypal.creditcard.number'),
			Config::get('testing.paypal.creditcard.month'),
			Config::get('testing.paypal.creditcard.year'),
			Config::get('testing.paypal.creditcard.cvv'),
			Config::get('testing.paypal.creditcard.firstname'),
			Config::get('testing.paypal.creditcard.lastname')
		);

		$this->assertTrue(Shop::checkout());

		$user->delete();
	}
	
	/**
	 * Tests a successful purchase.
	 */
	public function testSuccessfulPurchase()
	{
		// Prepare

		$user = factory('App\User')->create(['password' => Hash::make('laravel-shop')]);

		Auth::attempt(['email' => $user->email, 'password' => 'laravel-shop']);

	    $cart = App\Cart::current();

	    $products = [];

		for ($i = 0; $i < 3; ++$i) {
			$product = App\TestProduct::create([
				'price'			=> $i + 0.99,
				'sku'			=> str_random(15),
				'name'			=> str_random(64),
				'description'	=> str_random(500),
			]);

			$cart->add($product, $i + 1);

			$products[] = $product;
		}

		Shop::setGateway('paypal');

		Shop::gateway()->setCreditCard(
			Config::get('testing.paypal.creditcard.type'),
			Config::get('testing.paypal.creditcard.number'),
			Config::get('testing.paypal.creditcard.month'),
			Config::get('testing.paypal.creditcard.year'),
			Config::get('testing.paypal.creditcard.cvv'),
			Config::get('testing.paypal.creditcard.firstname'),
			Config::get('testing.paypal.creditcard.lastname')
		);

		$this->assertTrue(Shop::checkout());

		$order = Shop::placeOrder();

		$this->assertTrue($order->isCompleted);

		$user->delete();

		foreach ($products as $product) {

			$product->delete();

		}
	}
	
	/**
	 * Tests a fail purchase.
	 */
	public function testFailPurchase()
	{
		// Prepare

		$user = factory('App\User')->create(['password' => Hash::make('laravel-shop')]);

		Auth::attempt(['email' => $user->email, 'password' => 'laravel-shop']);

	    $cart = App\Cart::current();

	    $product = $product = App\TestProduct::create([
			'price'			=> 0.99,
			'sku'			=> str_random(15),
			'name'			=> str_random(64),
			'description'	=> str_random(500),
		]);

		$cart->add($product);

		Shop::setGateway('paypal');

		Shop::gateway()->setCreditCard(
			Config::get('testing.paypal.creditcard.type'),
			'4111111111111111',
			Config::get('testing.paypal.creditcard.month'),
			Config::get('testing.paypal.creditcard.year'),
			Config::get('testing.paypal.creditcard.cvv'),
			Config::get('testing.paypal.creditcard.firstname'),
			Config::get('testing.paypal.creditcard.lastname')
		);

		$this->assertTrue(Shop::checkout());

		$order = Shop::placeOrder();

		$this->assertTrue($order->hasFailed);

		$this->assertEquals(Shop::exception()->getMessage(), 'INTERNAL_SERVICE_ERROR: Paypal payment Failed.');

		$user->delete();

		$product->delete();
	}
}