<?php
/**
 * Upgrade tasks class
 *
 * @package         Atum
 * @subpackage      Inc
 * @author          Be Rebel - https://berebel.io
 * @copyright       ©2021 Stock Management Labs™
 *
 * @since           1.2.4
 */

namespace Atum\Inc;

defined( 'ABSPATH' ) || die;

use Atum\Components\AtumCache;
use Atum\Components\AtumQueues;
use Atum\InventoryLogs\Models\Log;
use Atum\InventoryLogs\InventoryLogs;
use Atum\PurchaseOrders\PurchaseOrders;
use Atum\StockCentral\StockCentral;
use Atum\StockCentral\Lists\ListTable;
use Atum\Suppliers\Suppliers;


class Upgrade {

	/**
	 * The current ATUM version
	 *
	 * @var string
	 */
	private $current_atum_version = '';

	/**
	 * Whether ATUM is being installed for the first time
	 *
	 * @var bool
	 */
	private $is_fresh_install = FALSE;

	/**
	 * Upgrade constructor
	 *
	 * @since 1.2.4
	 *
	 * @param string $db_version  The ATUM version saved in db as an option.
	 */
	public function __construct( $db_version ) {

		$this->current_atum_version = $db_version;

		if ( ! $db_version || version_compare( $db_version, '0.0.1', '<=' ) ) {
			$this->is_fresh_install = TRUE;
		}

		// Update the db version to the current ATUM version before upgrade to prevent various executions.
		update_option( 'atum_version', ATUM_VERSION );

		// Delete transients if there after every version change.
		AtumCache::delete_transients();

		/************************
		 * UPGRADE ACTIONS START
		 **********************!*/

		// ** version 1.2.4 ** The Inventory Logs was introduced.
		if ( version_compare( $db_version, '1.2.4', '<' ) ) {
			$this->create_atum_order_items_tables();
			add_action( 'admin_init', array( $this, 'create_inventory_log_types' ) );
		}

		// ** version 1.4.6 ** New hidden column: weight.
		if ( version_compare( $db_version, '1.4.6', '<' ) && ! $this->is_fresh_install ) {
			$this->add_default_hidden_columns();
		}

		// ** version 1.5.0 ** New tables to store ATUM data for products.
		if ( version_compare( $db_version, '1.5.0', '<' ) ) {
			$this->create_product_data_table();

			if ( ! $this->is_fresh_install ) {
				$this->update_po_status();
			}
		}

		// ** version 1.5.8 ** New tables to store ATUM data for products.
		if ( version_compare( $db_version, '1.5.8', '<' ) ) {
			$this->create_list_table_columns();
		}

		// ** version 1.6.1.1 ** Change field types in ATUM data for products.
		if ( version_compare( $db_version, '1.6.1.1', '<' ) ) {
			$this->alter_list_table_columns();
		}

		// ** version 1.6.3.2 ** Set the default for atum_controlled and disallow NULL.
		if ( version_compare( $db_version, '1.6.3.2', '<' ) && ! $this->is_fresh_install ) {
			$this->alter_atum_controlled_column();
		}

		// ** version 1.6.6 ** Add stock status and low stock calculated fields to ATUM data.
		if ( version_compare( $db_version, '1.6.6', '<' ) ) {
			$this->add_atum_stock_fields();
			add_action( 'atum/after_init', array( $this, 'fill_new_fields_values' ) );
		}

		// ** version 1.6.8 ** Change the supplier's meta key names.
		if ( version_compare( $db_version, '1.6.8', '<' ) && ! $this->is_fresh_install ) {
			$this->change_supplier_meta_key_names();
		}

		// ** version 1.7.1 ** Change the POs date_expected's meta key names.
		if ( version_compare( $db_version, '1.7.1', '<' ) && ! $this->is_fresh_install ) {
			$this->change_date_expected_meta_key_names();
		}

		// ** version 1.7.2 ** Update the calculated props for variable products.
		if ( version_compare( $db_version, '1.7.2', '<' ) && ! $this->is_fresh_install ) {
			// Run the method asynchronously.
			AtumQueues::add_async_action( 'update_atum_product_calc_props', array( $this, 'update_variable_calc_props' ) );
		}

		// ** version 1.7.3 ** Delete the comments count transient, so the unapproved and spam comments are counted.
		if ( version_compare( $db_version, '1.7.3', '<' ) ) {
			delete_transient( ATUM_PREFIX . 'count_comments' );
		}

		// ** version 1.7.8 ** Add the is_bom column
		if ( version_compare( $db_version, '1.7.8', '<' ) ) {
			$this->create_is_bom_column();
		}

		/**********************
		 * UPGRADE ACTIONS END
		 ********************!*/

		do_action( 'atum/after_upgrade', $db_version );

	}

	/**
	 * Create the tables for the ATUM Order items
	 *
	 * @since 1.2.4
	 */
	private function create_atum_order_items_tables() {

		global $wpdb;

		// Create DB tables for the ATUM Order items.
		$items_table    = $wpdb->prefix . 'atum_order_items';
		$itemmeta_table = $wpdb->prefix . 'atum_order_itemmeta';

		if ( ! $wpdb->get_var( "SHOW TABLES LIKE '$items_table';" ) ) { // phpcs:ignore WordPress.DB.PreparedSQL

			$collate = '';

			if ( $wpdb->has_cap( 'collation' ) ) {
				$collate = $wpdb->get_charset_collate();
			}

			$sql = "
				CREATE TABLE $items_table (
				    `order_item_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
					`order_item_name` text NOT NULL,
					`order_item_type` varchar(200) NOT NULL DEFAULT '',
					`order_id` bigint(20) unsigned NOT NULL,
					PRIMARY KEY (`order_item_id`),
					KEY `order_id` (`order_id`)
				) $collate;
				CREATE TABLE $itemmeta_table (
					`meta_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
	                `order_item_id` bigint(20) unsigned NOT NULL,
					`meta_key` varchar(255) DEFAULT NULL,
					`meta_value` longtext,
					PRIMARY KEY (`meta_id`),
					KEY `meta_key` (`meta_key`(191)),
					KEY `order_item_id` (`order_item_id`)
				) $collate;
			";

			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
			dbDelta( $sql );

		}

	}

	/**
	 * Create the default types for the Inventory Logs
	 *
	 * @since 1.2.4
	 */
	public function create_inventory_log_types() {

		// Create terms for the log types.
		$log_type_taxonomy = InventoryLogs::get_type_taxonomy();
		foreach ( Log::get_log_types() as $log_type_slug => $log_type_name ) {

			if ( empty( term_exists( $log_type_slug, $log_type_taxonomy ) ) ) {
				wp_insert_term( $log_type_name, $log_type_taxonomy, array( 'slug' => $log_type_slug ) );
			}

		}

	}

	/**
	 * Add default_hidden_columns to hidden columns on SC (in all users with hidden columns set)
	 *
	 * @since 1.4.6
	 */
	private function add_default_hidden_columns() {

		$hidden_columns = ListTable::hidden_columns();

		if ( empty( $hidden_columns ) ) {
			return;
		}

		global $wpdb;

		$meta_key_sc = 'manage' . Globals::ATUM_UI_HOOK . '_page_' . StockCentral::UI_SLUG . 'columnshidden';

		foreach ( $hidden_columns as $hidden_column ) {

			$user_ids = $wpdb->get_col( "SELECT user_id FROM $wpdb->usermeta WHERE meta_key = '$meta_key_sc' AND meta_value NOT LIKE '%{$hidden_column}%' AND meta_value <> ''" ); // phpcs:ignore WordPress.DB.PreparedSQL

			foreach ( $user_ids as $user_id ) {

				$meta = get_user_meta( $user_id, $meta_key_sc, TRUE );

				if ( ! array( $meta ) ) {
					$meta = array();
				}

				$meta[] = $hidden_column;
				update_user_meta( $user_id, $meta_key_sc, $meta );

			}
		}

	}

	/**
	 * Create the table for the product data related to ATUM
	 *
	 * @since 1.5.0
	 */
	private function create_product_data_table() {

		global $wpdb;

		$product_meta_table = $wpdb->prefix . 'atum_product_data';

		if ( ! $wpdb->get_var( "SHOW TABLES LIKE '$product_meta_table';" ) ) { // phpcs:ignore WordPress.DB.PreparedSQL

			$collate = '';

			if ( $wpdb->has_cap( 'collation' ) ) {
				$collate = $wpdb->get_charset_collate();
			}

			$sql = "
				CREATE TABLE $product_meta_table (
				`product_id` BIGINT(20) NOT NULL,
		  		`purchase_price` DOUBLE NULL DEFAULT NULL,
			  	`supplier_id` BIGINT(20) NULL DEFAULT NULL,
			  	`supplier_sku` VARCHAR(100) NULL DEFAULT '',
			  	`atum_controlled` TINYINT(1) NULL DEFAULT 1,
			  	`out_stock_date` DATETIME NULL DEFAULT NULL,
			  	`out_stock_threshold` DOUBLE NULL DEFAULT NULL,
			  	`inheritable` TINYINT(1) NULL DEFAULT 0,	
			  	PRIMARY KEY  (`product_id`),
				KEY `supplier_id` (`supplier_id`),
				KEY `atum_controlled` (`atum_controlled`)
			) $collate;
			";

			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
			dbDelta( $sql );

			// We only need to migrate meta keys if is not a fresh install.
			if ( ! $this->is_fresh_install ) {
				$this->migrate_atum_products_data();
			}

		}

	}

	/**
	 * Migrate from the old metas to the new data tables
	 *
	 * @since 1.5.0
	 */
	private function migrate_atum_products_data() {

		global $wpdb;

		$meta_keys_to_migrate = array(
			'_purchase_price'      => 'purchase_price',
			'_supplier'            => 'supplier_id',
			'_supplier_sku'        => 'supplier_sku',
			'_atum_manage_stock'   => 'atum_controlled',
			'_out_of_stock_date'   => 'out_stock_date',
			'_out_stock_threshold' => 'out_stock_threshold',
			'_inheritable'         => 'inheritable',
		);

		$products = array();

		if ( Helpers::is_using_new_wc_tables() ) {
			$products = $wpdb->get_results( "SELECT `product_id` AS ID, `type` FROM {$wpdb->prefix}wc_products ORDER BY `product_id`" );
		}

		if ( empty( $products ) ) {
			$products = $wpdb->get_results(
				"SELECT `ID`, `post_type` AS type FROM {$wpdb->posts} WHERE `post_type` IN ('product', 'product_variation') ORDER BY `ID`"
			);
		}

		foreach ( $products as $product ) {

			$metas      = get_post_meta( $product->ID );
			$meta_value = NULL;

			$new_data = array(
				'product_id' => $product->ID,
			);

			foreach ( $meta_keys_to_migrate as $meta_key => $new_field_name ) {

				switch ( $meta_key ) {

					// Yes/No metas.
					case '_atum_manage_stock':
					case '_inheritable':
						if ( isset( $metas[ $meta_key ] ) ) {
							$meta_value = 'yes' === $metas[ $meta_key ][0] ? 1 : 0;
						}
						else {
							$meta_value = 0;
						}
						break;

					// Date metas.
					case '_out_of_stock_date':
						$meta_value = ( isset( $metas[ $meta_key ] ) && ! empty( $metas[ $meta_key ][0] ) ) ? $metas[ $meta_key ][0] : NULL;
						break;

					// Other metas.
					default:
						$meta_value = isset( $metas[ $meta_key ] ) ? $metas[ $meta_key ][0] : NULL;
						break;

				}

				$new_data[ $new_field_name ] = $meta_value;

			}

			// Insert a new row of data.
			$inserted_row = $wpdb->insert( $wpdb->prefix . 'atum_product_data', $new_data );

			// TODO: Move meta deletion to ATUM settings -> Tools
			// If the row was inserted, delete the old meta.
			// phpcs:ignore Squiz.Commenting.BlockComment.NoNewLine
			/*if ( $inserted_row ) {

				foreach ( array_keys( $meta_keys_to_migrate ) as $meta_key ) {
					delete_post_meta( $product->ID, $meta_key );
				}

			}*/

		}

	}

	/**
	 * Update PO status completed to received.
	 *
	 * @since 1.5.0
	 */
	private function update_po_status() {

		global $wpdb;

		$wpdb->update( $wpdb->posts, [ 'post_status' => 'atum_received' ], [
			'post_status' => 'atum_completed',
			'post_type'   => PurchaseOrders::get_post_type(),
		] );

		$sql = "
			UPDATE $wpdb->postmeta pm
			INNER JOIN $wpdb->posts p ON pm.post_id = p.ID
			SET pm.meta_value = 'received'
			WHERE p.post_type = 'atum_purchase_order' AND
			pm.meta_key = '_status' AND pm.meta_value = 'completed'
		";

		$wpdb->query( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

	}

	/**
	 * Create the new columns for the ATUM product data table
	 *
	 * @since 1.5.8
	 */
	private function create_list_table_columns() {

		global $wpdb;

		$db_name         = DB_NAME;
		$atum_data_table = $wpdb->prefix . Globals::ATUM_PRODUCT_DATA_TABLE;
		$columns         = array(
			'inbound_stock'    => 'DOUBLE',
			'stock_on_hold'    => 'DOUBLE',
			'sold_today'       => 'BIGINT(20)',
			'sales_last_days'  => 'BIGINT(20)',
			'reserved_stock'   => 'BIGINT(20)',
			'customer_returns' => 'BIGINT(20)',
			'warehouse_damage' => 'BIGINT(20)',
			'lost_in_post'     => 'BIGINT(20)',
			'other_logs'       => 'BIGINT(20)',
			'out_stock_days'   => 'INT(11)',
			'lost_sales'       => 'BIGINT(20)',
			'has_location'     => 'TINYINT(1)',
			'update_date'      => 'DATETIME',
		);

		foreach ( array_keys( $columns ) as $column_name ) {

			// Avoid adding the column if was already added.
			$column_exist = $wpdb->prepare( '
				SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
				WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND column_name = %s
			', $db_name, $atum_data_table, $column_name );

			// Add the new column to the table.
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			if ( ! $wpdb->get_var( $column_exist ) ) {
				$wpdb->query( "ALTER TABLE $atum_data_table ADD `$column_name` {$columns[ $column_name ]} DEFAULT NULL;" ); // phpcs:ignore WordPress.DB.PreparedSQL
			}

		}

		// Add extra key indexes to ATUM tables to improve performance.
		$indexes = array(
			'inheritable',
		);

		foreach ( $indexes as $index ) {

			// Avoid adding the index if was already added.
			$index_exist = $wpdb->prepare( '
				SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
				WHERE table_schema = %s AND TABLE_NAME = %s AND index_name = %s;
			', $db_name, $atum_data_table, $index );

			// Add the new index to the table.
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			if ( ! $wpdb->get_var( $index_exist ) ) {
				$wpdb->query( "ALTER TABLE $atum_data_table ADD INDEX `$index` (`$index`)" ); // phpcs:ignore WordPress.DB.PreparedSQL
			}

		}

	}

	/**
	 * Modify the stock count inventory fields from bigint to double
	 *
	 * @since 1.6.1.1
	 */
	private function alter_list_table_columns() {

		global $wpdb;

		$db_name         = DB_NAME;
		$atum_data_table = $wpdb->prefix . Globals::ATUM_PRODUCT_DATA_TABLE;
		$columns         = array(
			'sold_today'       => 'DOUBLE',
			'sales_last_days'  => 'DOUBLE',
			'reserved_stock'   => 'DOUBLE',
			'customer_returns' => 'DOUBLE',
			'warehouse_damage' => 'DOUBLE',
			'lost_in_post'     => 'DOUBLE',
			'other_logs'       => 'DOUBLE',
			'lost_sales'       => 'DOUBLE',
		);

		foreach ( array_keys( $columns ) as $column_name ) {

			// Avoid adding the column if was already added.
			$column_exist = $wpdb->prepare( '
				SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
				WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND column_name = %s
			', $db_name, $atum_data_table, $column_name );

			// Add the new column to the table.
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			if ( ! $wpdb->get_var( $column_exist ) ) {
				$wpdb->query( "ALTER TABLE $atum_data_table ADD `$column_name` {$columns[ $column_name ]} DEFAULT NULL;" ); // phpcs:ignore WordPress.DB.PreparedSQL
			}
			else {
				$wpdb->query( "ALTER TABLE $atum_data_table MODIFY `$column_name` {$columns[ $column_name ]} DEFAULT NULL;" ); // phpcs:ignore WordPress.DB.PreparedSQL
			}

		}

	}

	/**
	 * Set the default value for atum_controlled and disallow NULL
	 *
	 * @since 1.6.3.2
	 */
	private function alter_atum_controlled_column() {

		global $wpdb;

		$atum_data_table = $wpdb->prefix . Globals::ATUM_PRODUCT_DATA_TABLE;

		// Make sure that there are no NULL values currently.
		$wpdb->update(
			$atum_data_table,
			array(
				'atum_controlled' => 1, // All those that were incorrectly set to NULL should be enabled by default.
			),
			array(
				'atum_controlled' => NULL,
			),
			array(
				'%d',
			),
			array(
				NULL,
			)
		);

		$wpdb->query( "ALTER TABLE $atum_data_table MODIFY `atum_controlled` TINYINT(1) NOT NULL DEFAULT '1';" ); // phpcs:ignore WordPress.DB.PreparedSQL

	}

	/**
	 * Add atum_stock_status and lowstock columns to ATUM Product data
	 *
	 * @since 1.6.6
	 */
	private function add_atum_stock_fields() {

		global $wpdb;

		$db_name         = DB_NAME;
		$atum_data_table = $wpdb->prefix . Globals::ATUM_PRODUCT_DATA_TABLE;
		$columns         = array(
			'atum_stock_status' => array(
				'type'    => 'VARCHAR(15)',
				'default' => "'instock'",
			),
			'low_stock'         => array(
				'type'    => 'TINYINT(1)',
				'default' => '0',
			),
		);

		foreach ( $columns as $column_name => $props ) {

			// Avoid adding the column if was already added.
			$column_exist = $wpdb->prepare( '
				SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
				WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND column_name = %s
			', $db_name, $atum_data_table, $column_name );

			// Add the new column to the table.
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			if ( ! $wpdb->get_var( $column_exist ) ) {
				$wpdb->query( "ALTER TABLE $atum_data_table ADD `$column_name` {$props[ 'type' ]} NOT NULL DEFAULT {$props[ 'default' ]};" ); // phpcs:ignore WordPress.DB.PreparedSQL
			}
			else {
				$wpdb->query( "ALTER TABLE $atum_data_table MODIFY `$column_name` {$props[ 'type' ]} NOT NULL DEFAULT {$props[ 'default' ]};" ); // phpcs:ignore WordPress.DB.PreparedSQL
			}

		}

	}

	/**
	 * Update the ATUM Stock Status and Low Stock fields.
	 *
	 * @since 1.6.6
	 */
	public function fill_new_fields_values() {

		global $wpdb;

		$atum_product_data_table = $wpdb->prefix . Globals::ATUM_PRODUCT_DATA_TABLE;

		$product_statuses = array(
			'instock'     => [],
			'outofstock'  => [],
			'onbackorder' => [],

		);

		$ids = $wpdb->get_col( "SELECT product_id FROM $atum_product_data_table;" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		if ( $ids ) {
			foreach ( $ids as $id ) {

				$product = Helpers::get_atum_product( $id );

				if ( $product instanceof \WC_Product ) {
					$product_statuses[ $product->get_stock_status() ][] = $id;

					$low_stock = Helpers::is_product_low_stock( $product );

					// phpcs:disable WordPress.DB
					$wpdb->query( $wpdb->prepare(
						'UPDATE ' . $atum_product_data_table . ' SET low_stock = %1$d
						WHERE product_id = %2$d', $low_stock, $product->get_id()
					) );
					// phpcs:enable
				}
			}
		}

		foreach ( $product_statuses as $status => $products_ids ) {

			if ( $products_ids ) {

				// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$wpdb->query( $wpdb->prepare( "
					UPDATE $atum_product_data_table SET atum_stock_status = %s
					WHERE product_id IN(" . implode( ',', $products_ids ) . ')',
					$status
				) );
				// phpcs:enable
			}
		}

	}

	/**
	 * Change the supplier meta key names to be compatible with the new model
	 *
	 * @since 1.6.8
	 */
	private function change_supplier_meta_key_names() {

		$group_keys = array(
			'_supplier_details',
			'_default_settings',
			'_billing_information',
		);

		global $wpdb;

		foreach ( $group_keys as $group_key ) {

			$wpdb->query( $wpdb->prepare( "
				UPDATE $wpdb->postmeta SET meta_key = REPLACE(meta_key, %s, '')
				WHERE post_id IN (
					SELECT ID FROM $wpdb->posts WHERE post_type = %s
				)
			", $group_key, Suppliers::POST_TYPE ) );

		}

	}

	/**
	 * Change the POs date expected meta key names to be compatible with the new model
	 *
	 * @since 1.7.1
	 */
	private function change_date_expected_meta_key_names() {

		global $wpdb;

		$wpdb->query( "
			UPDATE $wpdb->postmeta SET meta_key = '_date_expected'
			WHERE meta_key = '_expected_at_location_date'
		" );

	}

	/**
	 * Update the calculated props for all the site variables
	 *
	 * @since 1.7.2
	 */
	public function update_variable_calc_props() {

		$variation_products = get_posts( array(
			'posts_per_page' => - 1,
			'post_type'      => 'product_variation',
		) );

		$processed_variables = [];

		if ( ! empty( $variation_products ) ) {

			foreach ( $variation_products as $variation_product ) {

				if ( in_array( $variation_product->post_parent, $processed_variables ) ) {
					continue;
				}

				$processed_variables[] = $variation_product->post_parent;
				$variation_product     = Helpers::get_atum_product( $variation_product->ID );

				foreach (
					[
						'inbound_stock',
						'stock_on_hold',
						'sold_today',
						'sales_last_days',
						'reserved_stock',
						'customer_returns',
						'warehouse_damage',
						'lost_in_post',
						'other_logs',
						'lost_sales',
					] as $prop
				) {

					Helpers::maybe_update_variable_calc_prop( $variation_product, $prop, call_user_func( array( $variation_product, "get_$prop" ) ) );

				}

			}

		}

	}

	/**
	 * Alter the the ATUM product data table to add the is_bom column.
	 *
	 * @since 1.7.8
	 */
	private function create_is_bom_column() {

		global $wpdb;

		// Avoid adding the column if was already added.
		$db_name         = DB_NAME;
		$atum_data_table = $wpdb->prefix . Globals::ATUM_PRODUCT_DATA_TABLE;

		$column_exist = $wpdb->prepare( "
			SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
			WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND column_name = 'is_bom'
		", $db_name, $atum_data_table );

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		if ( ! $wpdb->get_var( $column_exist ) ) {
			$wpdb->query( "ALTER TABLE $atum_data_table ADD `is_bom` TINYINT(1) NULL DEFAULT 0;" ); // phpcs:ignore WordPress.DB.PreparedSQL
		}

	}

}
