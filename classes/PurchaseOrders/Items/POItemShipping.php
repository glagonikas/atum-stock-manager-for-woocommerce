<?php
/**
 * @package         Atum\PurchaseOrders
 * @subpackage      Items
 * @author          Be Rebel - https://berebel.io
 * @copyright       ©2018 Stock Management Labs™
 *
 * @since           1.2.9
 *
 * The model class for the PO Item Shipping objects
 */

namespace Atum\PurchaseOrders\Items;

defined( 'ABSPATH' ) or die;

use Atum\Components\AtumOrders\Items\AtumOrderItemShipping;


class POItemShipping extends AtumOrderItemShipping {

	use POItemTrait;

}