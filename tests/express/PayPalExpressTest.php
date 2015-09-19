<?php

use App;
use Log;
use Shop;
use Config;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class PayPalExpressTest extends TestCase
{

	/**
	 * User set for tests.
	 */
  	protected $user;

	/**
	 * Cart set for tests.
	 */
  	protected $cart;

	/**
	 * Product set for tests.
	 */
  	protected $product;

	/**
	 * Setups test data.
	 */
	public function setUp()
	{
		parent::setUp();

		$this->user = factory('App\User')->create(['password' => Hash::make('laravel-shop')]);

		Auth::attempt(['email' => $this->user->email, 'password' => 'laravel-shop']);

		$this->product = App\TestProduct::create([
			'price' 			=> 9.99,
			'sku'				=> str_random(15),
			'name'				=> str_random(64),
			'description'		=> str_random(500),
		]);

		$this->cart = App\Cart::current()->add($this->product);
	}

	/**
	 * Removes test data.
	 */
	public function tearDown() 
	{
		$this->user->delete();

		$this->product->delete();

		parent::tearDown();
	}

	/**
	 * Tests if gateway is integrated with shop.
	 */
	public function testGatewayIntegration()
	{
		Shop::setGateway('paypalExpress');

		$this->assertEquals(Shop::getGateway(), 'paypalExpress');

		$gateway = Shop::gateway();

		$this->assertNotNull($gateway);

		$this->assertNotEmpty($gateway->toJson());
	}
	
	/**
	 * Tests paypal approval url.
	 */
	public function testApprovalUrl()
	{
		Shop::setGateway('paypalExpress');

		$this->assertTrue(Shop::checkout());

		$order = Shop::placeOrder();

		$this->assertTrue($order->isPending);

		$approvalUrl = Shop::gateway()->getApprovalUrl();

		$this->assertNotEmpty($approvalUrl);
	}
	
	/**
	 * Tests completed orders based on total amount equals to 0.
	 */
	public function testOrderTotalZero()
	{
		Shop::setGateway('paypalExpress');

		$this->cart->clear();

		$this->cart->add([
			'sku'	=> 'ZERO0000',
			'price'	=> 0.0,
		]);

		$this->assertTrue(Shop::checkout());

		$order = Shop::placeOrder();

		$this->assertTrue($order->isCompleted);
	}
}