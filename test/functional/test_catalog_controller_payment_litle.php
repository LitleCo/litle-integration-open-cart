<?php
/*
 * Copyright (c) 2011 Litle & Co.
 * 
 * Permission is hereby granted, free of charge, to any person
 * obtaining a copy of this software and associated documentation
 * files (the "Software"), to deal in the Software without
 * restriction, including without limitation the rights to use,
 * copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the
 * Software is furnished to do so, subject to the following
 * conditions:
 * 
 * The above copyright notice and this permission notice shall be
 * included in all copies or substantial portions of the Software.
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND
 * EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES
 * OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
 * NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT
 * HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY,
 * WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING
 * FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR
 * OTHER DEALINGS IN THE SOFTWARE.
 */

if(!defined("UNIT_TESTING")) {
	define("UNIT_TESTING",true);
}

require_once realpath(dirname(__FILE__)) . "/OpenCartTest.php";

define ( 'DB_NAME', getenv('OPENCART_DB_NAME') );
define ( 'HOSTNAME', getenv('HOSTNAME'));
define ( 'CONTEXT', getenv('OPENCART_CONTEXT'));
define ( 'DB_USER', getenv('OPENCART_DB_USER'));

class MockOpenBay{
function orderNew($var){
 }
}

class LitlePaymentControllerTest extends OpenCartTest
{
	public function setUp() {
	   $this->execute_sql('cleanup.sql');
	}
	
	function test_successful_checkout()
	{
	   $this->execute_sql('loadOrderForCheckout.sql');
		
		$order_id = 1000;
				
		$controller = $this->loadControllerByRoute("payment/litle");
 		$this->session->data['order_id'] = $order_id;
 		
 		$this->request->post['cc_cvv2'] = '123';
 		$this->request->post['cc_expire_date_month'] = '02';
 		$this->request->post['cc_expire_date_year'] = '2015';
 		$this->request->post['cc_number'] = '4100000000000000';
 		$this->request->post['cc_type'] = 'VI';
		

        $this->load->model('checkout/order');
        $checkoutOrder = $this->model_checkout_order;
        $checkoutOrder->openbay=new MockOpenBay();

		$controller->send();
		
		$order = $this->model_checkout_order->getOrder($order_id);
		$latest_order_status_id = $order['order_status_id'];
		$this->assertEquals(1, $latest_order_status_id);

        $json = '{"success":"http://' . HOSTNAME . '/' . CONTEXT . '/index.php?route=checkout/success"}';
		$output = $this->getOutput();
		$this->assertEquals(str_replace('/', '\/', $json), $output);
		
		$this->load->model('account/order');
		$orderHistories = $this->model_account_order->getOrderHistories($order_id);
		$orderHistory = $orderHistories[0];
		
		$this->assertContains('Litle Response Code: 000', $orderHistory['comment']);
		$this->assertEquals("1", $orderHistory['notify']);
	}
	
	// https://github.com/LitleCo/litle-integration-open-cart/pull/3
	function test_expiration_date()
	{
 	    $this->execute_sql('loadOrderForCheckout.sql');
		
		$order_id = 1000;
				
		$controller = $this->loadControllerByRoute("payment/litle");
 		$this->session->data['order_id'] = $order_id;
 		
 		$this->request->post['cc_cvv2'] = '123';
 		$this->request->post['cc_expire_date_month'] = '02';
 		$this->request->post['cc_expire_date_year'] = '2015';
 		$this->request->post['cc_number'] = '4100000000000000';
 		$this->request->post['cc_type'] = 'VI';
                
        $requestArray = $controller->getCreditCardInfo();
		$this->assertEquals('0215',$requestArray['expDate']);
		
		$this->request->post['cc_expire_date_year'] = '2';
		$this->request->post['cc_expire_date_year'] = '15';
		
		$requestArray = $controller->getCreditCardInfo();
		$this->assertEquals('0215',$requestArray['expDate']);
	}
	
	// https://github.com/LitleCo/litle-integration-open-cart/pull/2
	function test_getAddressInfo_shouldReturnZoneForState()
	{
		$controller = $this->loadControllerByRoute("payment/litle");
		
		$orderInfo = array("billing_zone" => "MA", "billing_firstname" => "Greg");
		
		$whatToSendToLitle = $controller->getAddressInfo($orderInfo, "billing");
		$this->assertEquals("MA", $whatToSendToLitle['state']);
	}
	
}
