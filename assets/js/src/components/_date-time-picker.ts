/* =======================================
   DATE TIME PICKER
   ======================================= */

import moment from 'moment/min/moment.min';
import Settings from '../config/_settings';

export default class DateTimePicker {
	
	defaults: any = {};
	
	constructor(
		private settings: Settings
	) {
		
		this.defaults = {
			format           : this.settings.get('dateFormat'),
			useCurrent       : false,
			showClose        : true,
			icons            : {
				time    : 'atum-icon atmi-clock',
				date    : 'atum-icon atmi-calendar-full',
				up      : 'atum-icon atmi-chevron-up',
				down    : 'atum-icon atmi-chevron-down',
				previous: 'atum-icon atmi-chevron-left',
				next    : 'atum-icon atmi-chevron-right',
				today   : 'atum-icon atmi-frame-expand',
				clear   : 'atum-icon atmi-trash',
				close   : 'atum-icon atmi-cross',
			},
			minDate          : moment(),
			showClear        : true,
			showTodayButton  : true,
			widgetPositioning: {
				horizontal: 'right',
				vertical:   'bottom',
			},
			tooltips         : {
				today          : this.settings.get('goToToday'),
				clear          : this.settings.get('clearSelection'),
				close          : this.settings.get('closePicker'),
				selectMonth    : this.settings.get('selectMonth'),
				prevMonth      : this.settings.get('prevMonth'),
				nextMonth      : this.settings.get('nextMonth'),
				selectYear     : this.settings.get('selectYear'),
				prevYear       : this.settings.get('prevYear'),
				nextYear       : this.settings.get('nextYear'),
				selectDecade   : this.settings.get('selectDecade'),
				prevDecade     : this.settings.get('prevDecade'),
				nextDecade     : this.settings.get('nextDecade'),
				prevCentury    : this.settings.get('prevCentury'),
				nextCentury    : this.settings.get('nextCentury'),
				incrementHour  : this.settings.get('incrementHour'),
				pickHour       : this.settings.get('pickHour'),
				decrementHour  : this.settings.get('decrementHour'),
				incrementMinute: this.settings.get('incrementMinute'),
				pickMinute     : this.settings.get('pickMinute'),
				decrementMinute: this.settings.get('decrementMinute'),
				incrementSecond: this.settings.get('incrementSecond'),
				pickSecond     : this.settings.get('pickSecond'),
				decrementSecond: this.settings.get('decrementSecond'),
			},
		}
		
	}
	
	/**
	 * Add the date time pickers
	 *
	 * @param jQuery $selector
	 * @param Object opts
	 */
	addDateTimePickers($selector: any, opts: any = {}) {
		
		$selector.each( (index: number, elem: Element) => {
			
			let $dateTimePicker: any = $(elem),
			    mergedOpts: any      = {};
			
			// Extend the date picker options with data options.
			Object.assign(mergedOpts, this.defaults, $dateTimePicker.data() || {}, opts);
			
			$dateTimePicker.datetimepicker(mergedOpts);
			
		})
		.on('dp.change', (evt: any) => {
			
			const label: string = typeof evt.date === 'object' ? evt.date.format(this.settings.get('dateFormat')) : this.settings.get('none');
			$(evt.currentTarget).siblings('.field-label').addClass('unsaved').text(label);
			
		})
		.on('dp.show', (evt: any) => {
			
			// Hide others opened.
			$selector.not($(evt.currentTarget)).filter( (index: number, elem: Element) => {
				
				if ($(elem).children('.bootstrap-datetimepicker-widget').length) {
					return true;
				}
				
				return false;
				
			}).each( (index: number, elem: Element) => {
				$(elem).data('DateTimePicker').hide();
			});
			
		});
		
	}
	
}