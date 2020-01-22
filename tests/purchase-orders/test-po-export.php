<?php
/**
 * Class POExportTest
 *
 * @package Atum_Stock_Manager_For_Woocommerce
 */

use Atum\PurchaseOrders\Exports\POExport;
use TestHelpers\TestHelpers;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Sample test case.
 */
class POExportTest extends WP_UnitTestCase { //PHPUnit_Framework_TestCase {

	private $po;

	public function setUp() {
		parent::setUp();
		$this->po = TestHelpers::create_atum_purchase_order();
	}

	public function test_methods() {
		$data = TestHelpers::count_public_methods( POExport::class );

		foreach( $data['methods'] as $method) {
			$this->assertTrue( method_exists( $this, 'test_'.$method ), "Method `test_$method` doesn't exist in class ".self::class );
		}
	}

	public function test_instance() {
		$obj = new POExport( $this->po->get_id() );
		$this->assertInstanceOf( POExport::class, $obj );
	}

	public function test_get_content() {
		$obj = new POExport( $this->po->get_id() );

		try {
			$data = $obj->get_content();
		} catch( Exception $e ) {
			var_dump($e->getMessage());
			unset( $e );
		}

		$html = new Crawler( $data );
		$this->assertEquals( 1, $html->filter('div.po-wrapper.content-header')->count() );
		$this->assertEquals( 1, $html->filter('div.po-wrapper.content-address')->count() );
		$this->assertEquals( 1, $html->filter('div.po-wrapper.content-lines')->count() );
		$this->assertEquals( 1, $html->filter('div.po-wrapper.content-description')->count() );
		$this->assertContains( '<h3 class="po-title">Purchase Order</h3>', $data );
	}

	public function test_get_company_address() {
		global $atum_global_options;
		$atum_global_options = [ 'company_name' => 'Foo company', 'address_1' => '13th Foo Street' ];
		$obj  = new POExport( $this->po->get_id() );
		$data = $obj->get_company_address();
		$this->assertIsString( $data );
		$this->assertContains( 'Foo company', $data );
	}

	public function test_get_supplier_address() {
		$supplier         = $this->factory()->post->create_and_get( [
			'post_title'  => 'Foo supplier',
			'post_type'   => 'atum_supplier',
			'post_status' => 'published',
			'log_type'    => 'other',
		] );
		update_post_meta( $this->po->get_id(), '_supplier', $supplier->ID );
		$obj  = new POExport( $this->po->get_id() );
		$data = $obj->get_supplier_address();

		$this->assertIsString( $data );
		$this->assertContains( 'Foo supplier', $data );
	}

	public function test_get_shipping_address() {
		global $atum_global_options;
		$atum_global_options = [ 'ship_to' => 'Foo company', 'ship_address_1' => '13th Foo Street' ];
		$obj  = new POExport( $this->po->get_id() );
		$data = $obj->get_shipping_address();
		$this->assertIsString( $data );
		$this->assertContains( 'Foo company', $data );
	}

	public function test_get_stylesheets() {
		$obj  = new POExport( $this->po->get_id() );
		$data = $obj->get_stylesheets();
		$this->assertIsArray( $data );
		$this->assertContains( 'atum-po-export.css', $data[0] );
	}

}