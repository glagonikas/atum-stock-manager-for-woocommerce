<?php
/**
 * WC Product data store: using legacy tables
 *
 * @package         Atum\Models
 * @subpackage      DataStores
 * @author          Be Rebel - https://berebel.io
 * @copyright       ©2018 Stock Management Labs™
 *
 * @since           1.5.0
 */

namespace Atum\Models\DataStores;

defined( 'ABSPATH' ) || die;

/**
 * WC Product Data Store: Stored in CPT.
 *
 * @version  1.5.0
 */
class WCProductDataStoreCPT extends \WC_Product_Data_Store_CPT {
	
	use AtumDataStoreLegacyCustomTableTrait, AtumDataStoreCommonCustomTableTrait;
	
}
