/* =======================================
   ADD ITEMS POPUP FOR ATUM ORDERS
   ======================================= */

import AtumOrders from './_atum-orders';
import {Blocker} from '../_blocker';
import Settings from '../../config/_settings';
import Tooltip from '../_tooltip';

export default class AddItemsPopup {
	
	constructor(
		private settings: Settings,
		private $container: JQuery,
		private atumOrders: AtumOrders,
		private tooltip: Tooltip
	) {
		
		$('body')
			.on( 'wc_backbone_modal_loaded', this.init )
			.on( 'wc_backbone_modal_response', this.response );
		
	}
	
	init( evt: Event, target: string ) {
		
		if ( 'atum-modal-add-products' === target ) {
			$('body').trigger( 'wc-enhanced-select-init' );
		}
		
	}
	
	response( evt: Event, target: string, data: any ) {
		
		if ( 'atum-modal-add-tax' === target ) {
			
			let rateId        = data.add_atum_order_tax,
			    manualRateId = '';
			
			if ( data.manual_tax_rate_id ) {
				manualRateId = data.manual_tax_rate_id;
			}
			
			this.addTax( rateId, manualRateId );
			
		}
		
		if ( 'atum-modal-add-products' === target ) {
			this.addItem( data.add_atum_order_items );
		}
		
	}
	
	addItem( itemIds: string|string[] ) {
		
		if ( itemIds ) {
			
			Blocker.block(this.$container);
			
			const data: any = {
				action       : 'atum_order_add_item',
				item_to_add  : itemIds,
				atum_order_id: this.settings.get('post_id'),
				security     : this.settings.get('atum_order_item_nonce'),
			}
			
			$.post( window['ajaxurl'], data, (response: any) => {
				
				if ( response.success ) {
					$('#atum_order_line_items').append( response.data.html );
				}
				else {
					this.atumOrders.showAlert('error', this.settings.get('error'), response.data.error);
				}
				
				this.tooltip.addTooltips();
				Blocker.unblock(this.$container);
				
			}, 'json');
		}
		
	}
	
	addTax( rateId: string, manualRateId: string ) {
		
		if ( manualRateId ) {
			rateId = manualRateId;
		}
		
		if ( ! rateId ) {
			return false;
		}
		
		const rates: any[] = $('.atum-order-tax-id').map( (index: number, elem: Element) => {
			return $(elem).val();
		}).get();
		
		// Test if already exists
		if ( -1 === $.inArray( rateId, rates ) ) {
			
			this.atumOrders.loadItemsTable({
				action       : 'atum_order_add_tax',
				rate_id      : rateId,
				atum_order_id: this.settings.get('post_id'),
				security     : this.settings.get('atum_order_item_nonce'),
			}, 'json');
			
		}
		else {
			this.atumOrders.showAlert('error', this.settings.get('error'), this.settings.get('tax_rate_already_exists'));
		}
		
	}
	
}