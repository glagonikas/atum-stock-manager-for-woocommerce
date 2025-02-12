<?php
/**
 * The ATUM's API class
 *
 * @since       1.6.2
 * @author      Be Rebel - https://berebel.io
 * @copyright   ©2021 Stock Management Labs™
 *
 * @package     Atum\Api
 */

namespace Atum\Api;

defined( 'ABSPATH' ) || die;

use Atum\Api\Extenders\AtumProductData;
use Atum\Api\Extenders\ProductAttributes;
use Atum\Api\Extenders\ProductCategories;
use Atum\Modules\ModuleManager;

class AtumApi {

	/**
	 * The singleton instance holder
	 *
	 * @var AtumApi
	 */
	private static $instance;

	/**
	 * The CORS origin coming from the ATUM App
	 * TODO: CHANGE IT AFTER DEPLOYING THE APP WITH THE NEW NAME.
	 */
	const ATUM_APP_ORIGIN = 'capacitor://io.berebel.atum';

	/**
	 * The ATUM API controllers
	 *
	 * @var array
	 */
	private $api_controllers = array(
		'atum-addons'                        => __NAMESPACE__ . '\Controllers\V3\AddonsController',
		'atum-dashboard'                     => __NAMESPACE__ . '\Controllers\V3\DashboardController',
		'atum-dashboard-current-stock-value' => __NAMESPACE__ . '\Controllers\V3\DashboardCurrentStockValueController',
		'atum-dashboard-lost-sales'          => __NAMESPACE__ . '\Controllers\V3\DashboardLostSalesController',
		'atum-dashboard-orders'              => __NAMESPACE__ . '\Controllers\V3\DashboardOrdersController',
		'atum-dashboard-promo-sales'         => __NAMESPACE__ . '\Controllers\V3\DashboardPromoSalesController',
		'atum-dashboard-sales'               => __NAMESPACE__ . '\Controllers\V3\DashboardSalesController',
		'atum-dashboard-statistics'          => __NAMESPACE__ . '\Controllers\V3\DashboardStatisticsController',
		'atum-dashboard-stock-control'       => __NAMESPACE__ . '\Controllers\V3\DashboardStockControlController',
		'atum-inbound-stock'                 => __NAMESPACE__ . '\Controllers\V3\InboundStockController',
		'atum-inventory-logs'                => __NAMESPACE__ . '\Controllers\V3\InventoryLogsController',
		'atum-inventory-log-notes'           => __NAMESPACE__ . '\Controllers\V3\InventoryLogNotesController',
		'atum-locations'                     => __NAMESPACE__ . '\Controllers\V3\ProductLocationsController',
		'atum-product-variations'            => __NAMESPACE__ . '\Controllers\V3\ProductVariationsController',
		'atum-purchase-orders'               => __NAMESPACE__ . '\Controllers\V3\PurchaseOrdersController',
		'atum-purchase-order-notes'          => __NAMESPACE__ . '\Controllers\V3\PurchaseOrderNotesController',
		'atum-settings'                      => __NAMESPACE__ . '\Controllers\V3\SettingsController',
		'atum-setting-options'               => __NAMESPACE__ . '\Controllers\V3\SettingOptionsController',
		'atum-suppliers'                     => __NAMESPACE__ . '\Controllers\V3\SuppliersController',
		'atum-tools'                         => __NAMESPACE__ . '\Controllers\V3\ToolsController',
	);

	/**
	 * AtumApi constructor
	 *
	 * @since 1.6.2
	 */
	private function __construct() {

		// Pre-filter the available endpoints according to currently-enabled modules.
		if ( ! ModuleManager::is_module_active( 'inventory_logs' ) ) {
			unset( $this->api_controllers['atum-inventory-logs'] );
		}

		if ( ! ModuleManager::is_module_active( 'purchase_orders' ) ) {
			unset(
				$this->api_controllers['atum-purchase-orders'],
				$this->api_controllers['atum-suppliers'],
				$this->api_controllers['atum-locations'],
				$this->api_controllers['atum-inbound-stock']
			);
		}

		// Add the ATUM controllers to the WooCommerce API (/wp-json/wc/v3).
		add_filter( 'woocommerce_rest_api_get_rest_namespaces', array( $this, 'register_api_controllers' ) );

		// Fix CORS issue when connecting through Ionic's Capacitor to our API.
		add_action( 'rest_api_init', array( $this, 'add_cors_hooks' ), 15 );

		$this->load_extenders();

	}

	/**
	 * Register the ATUM API controllers
	 *
	 * @since 1.6.2
	 *
	 * @param array $api_controllers
	 *
	 * @return array
	 */
	public function register_api_controllers( $api_controllers ) {

		if ( ! empty( $api_controllers['wc/v3'] ) ) {
			$api_controllers['wc/v3'] = array_merge( $api_controllers['wc/v3'], apply_filters( 'atum/api/registered_controllers', $this->api_controllers ) );
		}

		return $api_controllers;

	}

	/**
	 * Load the ATUM API extenders (all those that are extending an existing WC endpoint)
	 *
	 * @since 1.6.2
	 */
	public function load_extenders() {

		AtumProductData::get_instance();
		ProductAttributes::get_instance();
		ProductCategories::get_instance();

		// Allow ATUM plugins to load their extenders.
		do_action( 'atum/api/load_extenders' );

	}

	/**
	 * Add hooks for fixing CORS rules
	 *
	 * @since 1.8.8
	 */
	public function add_cors_hooks() {

		remove_filter( 'rest_pre_serve_request', 'rest_send_cors_headers' );
		add_filter( 'rest_pre_serve_request', array( $this, 'fix_cors' ), PHP_INT_MAX );

	}

	/**
	 * Fix CORS issue when connecting through Ionic's Capacitor to our API.
	 *
	 * @since 1.8.8
	 *
	 * @param mixed $value Response data.
	 *
	 * @return mixed
	 */
	public function fix_cors( $value ) {

		$origin = get_http_origin();

		if ( $origin ) {

			// Requests from file:// and data: URLs send "Origin: null".
			if ( self::ATUM_APP_ORIGIN !== $origin && 'null' !== $origin ) {
				$origin = esc_url_raw( $origin );
			}

			// Allow 3rd parties to add any extra allowed origins.
			$origin = apply_filters( 'atum/api/cors_origin', $origin );

			header( 'Access-Control-Allow-Origin: ' . $origin );
			header( 'Access-Control-Allow-Methods: OPTIONS, GET, POST, PUT, PATCH, DELETE' );
			header( 'Access-Control-Allow-Credentials: true' );
			header( 'Vary: Origin', false );

		}
		elseif ( ! headers_sent() && 'GET' === $_SERVER['REQUEST_METHOD'] && ! is_user_logged_in() ) {
			header( 'Vary: Origin', false );
		}

		return $value;

	}


	/****************************
	 * Instance methods
	 ****************************/

	/**
	 * Cannot be cloned
	 */
	public function __clone() {
		_doing_it_wrong( __FUNCTION__, esc_attr__( 'Cheatin&#8217; huh?', ATUM_TEXT_DOMAIN ), '1.0.0' );
	}

	/**
	 * Cannot be serialized
	 */
	public function __sleep() {
		_doing_it_wrong( __FUNCTION__, esc_attr__( 'Cheatin&#8217; huh?', ATUM_TEXT_DOMAIN ), '1.0.0' );
	}

	/**
	 * Get Singleton instance
	 *
	 * @return AtumApi instance
	 */
	public static function get_instance() {
		if ( ! ( self::$instance && is_a( self::$instance, __CLASS__ ) ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

}
