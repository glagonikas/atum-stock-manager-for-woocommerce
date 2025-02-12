/* =======================================
   SETTINGS PAGE
   ======================================= */

import EnhancedSelect from '../_enhanced-select';
import ButtonGroup from '../_button-group';
import ColorPicker from '../_color-picker';
import DateTimePicker from '../_date-time-picker';
import FileUploader, { WPMediaModalOptions } from '../_file-uploader';
import Settings from '../../config/_settings';
import SmartForm from '../_smart-form';
import Swal, { SweetAlertResult } from 'sweetalert2';
import TabLoader from '../_tab-loader';
import Tooltip from '../_tooltip';

export default class SettingsPage {
	
	$settingsWrapper: JQuery;
	$nav: JQuery;
	$form: JQuery;
	navigationReady: boolean = false;
	numHashParameters: number = 0;
	tabLoader: TabLoader;

	constructor(
		private settings: Settings,
		private enhancedSelect: EnhancedSelect,
		private tooltip: Tooltip,
		private dateTimePicker: DateTimePicker
	) {
		
		// Initialize selectors.
		this.$settingsWrapper = $( '.atum-settings-wrapper' );
		this.$nav = this.$settingsWrapper.find( '.atum-nav' );
		this.$form = this.$settingsWrapper.find( '#atum-settings' );
		
		// URL hash navigation.
		this.setupNavigation();

		// Enable DateTimePickers
		this.initDateTimePicker();

		// Enable Tooltips.
		this.tooltip.addTooltips( this.$form );

		// Enable ColoPickers.
		ColorPicker.doColorPickers( this.settings.get( 'selectColor' ) );

		// Enable Select2.
		this.enhancedSelect.doSelect2( this.$settingsWrapper.find( 'select' ), {}, true );

		// Enable button groups.
		ButtonGroup.doButtonGroups( this.$form );

		// Enable image uploader with the default options.
		const uploaderOptions: WPMediaModalOptions = {
			library: {
				type: 'image',
			},
		};
		new FileUploader( uploaderOptions, true );

		// Enable theme selector
        // this.doThemeSelector();

		// Toggle Menu.
		this.toggleMenu();

		this.$form

			// Out of stock threshold option updates.
			.on( 'change', '#atum_out_stock_threshold', ( evt: JQueryEventObject ) => this.maybeClearOutStockThreshold( $( evt.currentTarget ) ) )

			// Script Runner fields.
			.on( 'click', '.script-runner .tool-runner', ( evt: JQueryEventObject ) => this.runScript( $( evt.currentTarget ) ) )

			// Theme selector fields.
			.on( 'click', '.selector-box', ( evt: JQueryEventObject ) => this.doThemeSelector( $( evt.currentTarget ) ) )

			// Toggle checkboxes.
			.on( 'click', '.atum-settings-input[type=checkbox]', ( evt: JQueryEventObject ) => this.clickCheckbox( $( evt.currentTarget ) ) )

			// Default color fields.
			.on( 'click', '.reset-default-colors', ( evt: JQueryEventObject ) => this.doResetDefault( $( evt.currentTarget ) ) )

			// Switcher multicheckbox.
			.on( 'change', '.atum-multi-checkbox-main', ( evt: JQueryEventObject ) => this.toggleMultiCheckboxPanel( $( evt.currentTarget ) ) )

			.on( 'change', '.remove-datepicker-range', ( evt: JQueryEventObject ) => this.toggleRangeRemove( $( evt.currentTarget ) ) )

			.on( 'change update blur', '.range-datepicker.range-from, .range-datepicker.range-to, .remove-datepicker-range', () => this.setDateTimeInputs() );

		new SmartForm( this.$form, this.settings.get( 'atumPrefix' ) );


		// Footer positioning.
		$( window ).on( 'load', () => {

			if ( $( '.footer-box' ).hasClass( 'no-style' ) ) {
				$( '#wpfooter' ).css( 'position', 'relative' ).show();
				$( '#wpcontent' ).css( 'min-height', '95vh' );
			}

		} );

		// Adjust the nav height.
		this.$nav.css( 'min-height', `${ this.$nav.find( '.atum-nav-list' ).outerHeight() + 200 }px` );
	
	}

	setupNavigation() {

		// Instantiate the loader to register the jQuery.address and the events.
		this.tabLoader = new TabLoader( this.$settingsWrapper, this.$nav );
		
		this.$settingsWrapper

			// Show the form after the page is loaded.
			.on( 'atum-tab-loader-init', () => this.$form.show() )

			// Tab clicked.
			.on( 'atum-tab-loader-clicked-tab', ( evt: JQueryEventObject, $navLink: JQuery, tab: string ) => {

				if ( this.$form.find( '.dirty' ).length ) {

					// Warn the user about unsaved data.
					Swal.fire( {
						title            : this.settings.get( 'areYouSure' ),
						text             : this.settings.get( 'unsavedData' ),
						icon             : 'warning',
						showCancelButton : true,
						confirmButtonText: this.settings.get( 'continue' ),
						cancelButtonText : this.settings.get( 'cancel' ),
						reverseButtons   : true,
						allowOutsideClick: false,
					} )
					.then( ( result: SweetAlertResult ) => {

						if ( result.isConfirmed ) {
							this.moveToTab( $navLink );
						}
						else {
							$navLink.blur();
						}

					} );

				}
				else {
					this.moveToTab( $navLink );
				}

			} );
		
	}
	
	hideColors() {

		const $tableColorSettings: JQuery = $( '#atum_setting_color_scheme #atum-table-color-settings' );

		if ( $tableColorSettings.length > 0 ) {

			const mode = $tableColorSettings.data( 'display' );

			$tableColorSettings.find( '.atum-settings-input.atum-color' ).not( '[data-display=' + mode + ']' ).closest( 'tr' ).hide();

			$tableColorSettings.find( 'tr' ).each( ( index: number, elem: Element ) => {
				if ( $( elem ).css( 'display' ) === 'none' ) {
					$( elem ).prependTo( $tableColorSettings.find( 'tbody' ) );
				}
			} );

		}

	}

	/**
	 * Move to a new settings tab
	 *
	 * @param {JQuery} $navLink
	 */
	moveToTab( $navLink: JQuery ) {

		const $formSettingsWrapper: JQuery = this.$form.find( '.form-settings-wrapper' );

		this.$nav.find( '.atum-nav-link.active' ).not( $navLink ).removeClass( 'active' );
		$navLink.addClass( 'active' );

		$formSettingsWrapper.addClass( 'overlay' );

		this.$form.load( `${ $navLink.attr( 'href' ) } .form-settings-wrapper`, () => {

			ColorPicker.doColorPickers( this.settings.get( 'selectColor' ) );
			this.initDateTimePicker();
			this.enhancedSelect.maybeRestoreEnhancedSelect();
			this.enhancedSelect.doSelect2( this.$settingsWrapper.find( 'select' ), {}, true );
			this.$form.find( '[data-dependency]' ).change().removeClass( 'dirty' );
			this.$form.show();

			const $inputButton: JQuery = this.$form.find( 'input:submit' );

			if ( $navLink.parent().hasClass( 'no-submit' ) ) {
				$inputButton.hide();
			}
			else {
				$inputButton.show();
			}

			// Enable Tooltips.
			this.tooltip.addTooltips( this.$form );

			this.$settingsWrapper.trigger( 'atum-settings-page-loaded', [ $navLink.data( 'tab' ) ] );

			if ( 'visual_settings' === $navLink.data( 'tab' ) ) {
				this.hideColors();
			}

		} );
		
	}
	
	toggleMenu() {

		const $navList: JQuery = this.$nav.find( '.atum-nav-list' );

		$( '.toogle-menu, .atum-nav-link' ).click( () => $navList.toggleClass( 'expand-menu' ) );

		$( window ).resize( () => $navList.removeClass( 'expand-menu' ) );
		
	}
	
	maybeClearOutStockThreshold( $checkbox: JQuery ) {

		if ( $checkbox.is( ':checked' ) && this.settings.get( 'isAnyOostSet' ) ) {

			Swal.fire( {
				title              : '',
				text               : this.settings.get( 'oostSetClearText' ),
				icon               : 'question',
				showCancelButton   : true,
				confirmButtonText  : this.settings.get( 'startFresh' ),
				cancelButtonText   : this.settings.get( 'useSavedValues' ),
				reverseButtons     : true,
				allowOutsideClick  : false,
				showLoaderOnConfirm: true,
				preConfirm         : (): Promise<any> => {

					return new Promise( ( resolve: Function, reject: Function ) => {

						$.ajax( {
							url     : window[ 'ajaxurl' ],
							method  : 'POST',
							dataType: 'json',
							data    : {
								action: this.settings.get( 'oostSetClearScript' ),
								token : this.settings.get( 'runnerNonce' ),
							},
							success : ( response: any ) => {

								if ( response.success !== true ) {
									Swal.showValidationMessage( response.data );
								}

								resolve( response.data );

							},
						} );

					} );

				},
			} )
			.then( ( result: SweetAlertResult ) => {

				if ( result.isConfirmed ) {
					Swal.fire( {
						title            : this.settings.get( 'done' ),
						icon             : 'success',
						text             : result.value,
						confirmButtonText: this.settings.get( 'ok' ),
					} );
				}

			} );
			
		}
		else if ( ! $checkbox.is( ':checked' ) ) {

			Swal.fire( {
				title            : '',
				text             : this.settings.get( 'oostDisableText' ),
				icon             : 'info',
				confirmButtonText: this.settings.get( 'ok' ),
			} );

		}
		
	}

	/**
	 * Run a tool script
	 *
	 * @param {JQuery} $button
	 */
	runScript( $button: JQuery ) {

		const $scriptRunner = $button.closest( '.script-runner' );

		if ( $scriptRunner.is( '.recurrent' ) ) {
			this.runRecurrentScript( $button, $scriptRunner );
		}
		else {
			this.runSingleScript( $button, $scriptRunner );
		}
	}

	runSingleScript( $button: JQuery, $scriptRunner ) {

		Swal.fire( {
			title              : this.settings.get( 'areYouSure' ),
			text               : $scriptRunner.data( 'confirm' ),
			icon               : 'warning',
			showCancelButton   : true,
			confirmButtonText  : this.settings.get( 'run' ),
			cancelButtonText   : this.settings.get( 'cancel' ),
			reverseButtons     : true,
			allowOutsideClick  : false,
			showLoaderOnConfirm: true,
			preConfirm         : (): Promise<any> => {

				return new Promise( ( resolve: Function, reject: Function ) => {

					let $input: JQuery = $scriptRunner.find( '#' + $scriptRunner.data( 'input' ) ),
					    data: any      = {
						    action: $scriptRunner.data( 'action' ),
						    token : this.settings.get( 'runnerNonce' ),
					    };

					if ( $input.length ) {
						data.option = $input.val();
					}

					$.ajax( {
						url       : window[ 'ajaxurl' ],
						method    : 'POST',
						dataType  : 'json',
						data      : data,
						beforeSend: () => $button.prop( 'disabled', true ),
						success   : ( response: any ) => {

							$button.prop( 'disabled', false );

							if ( response.success !== true ) {
								Swal.showValidationMessage( response.data );
							}

							resolve( response.data );

						},
					} );

				} );

			},

		} )
		.then( ( result: SweetAlertResult ) => {

			if ( result.isConfirmed ) {
				Swal.fire( {
						title            : this.settings.get( 'done' ),
						icon             : 'success',
						text             : result.value,
						confirmButtonText: this.settings.get( 'ok' ),
					} )
					.then( () => {
						this.$settingsWrapper.trigger( 'atum-settings-script-runner-done', [ $scriptRunner ] );
					} );
			}

		} );
		
	}

	runRecurrentScript( $button: JQuery, $scriptRunner ) {

		Swal.fire( {
			title              : this.settings.get( 'areYouSure' ),
			text               : $scriptRunner.data( 'confirm' ),
			icon               : 'warning',
			showCancelButton   : true,
			confirmButtonText  : this.settings.get( 'run' ),
			cancelButtonText   : this.settings.get( 'cancel' ),
			reverseButtons     : true,
			allowOutsideClick  : false,
			showLoaderOnConfirm: true,
			preConfirm         : (): Promise<any> => {

				return new Promise( ( resolve: Function, reject: Function ) => {

					let $input: JQuery = $scriptRunner.find( '#' + $scriptRunner.data( 'input' ) ),
					    option: string = '';

					const action: string = $scriptRunner.data( 'action' );

					if ( $input.length ) {
						option = $input.val();
					}

					const doRecurrentAjaxCall: any = ( offset: number = 0 ) => {

						let data: any = {
							action: action,
							token : this.settings.get( 'runnerNonce' ),
							option: option,
							offset: offset,
						};

						return $.ajax( {
							url     : window[ 'ajaxurl' ],
							method  : 'POST',
							dataType: 'json',
							data    : data,
						} );
					};

					$button.prop( 'disabled', true );

					const recurrentCall = ( offset: number = 0 ) =>

						doRecurrentAjaxCall( offset ).done( ( response: any ) => {
							if ( response.success === true ) {

								Swal.update( {
									text             : $scriptRunner.data( 'processing' ).replace( '{processed}', response.data.limit ).replace( '{total}', response.data.total ),
									showConfirmButton: false,
								} );

								if ( response.data.finished !== undefined && response.data.finished === true ) {
									$button.prop( 'disabled', false );
									resolve( response.data );
								}
								else {
									offset = response.data.limit !== undefined ? response.data.limit : 100;
									return recurrentCall( offset );
								}
							}
							else {
								$button.prop( 'disabled', false );
								Swal.showValidationMessage( response.data );
								resolve( response.data );
							}
						} );

					recurrentCall();

				} );

			},

		} )
		.then( ( result: SweetAlertResult ) => {

			if ( result.isConfirmed ) {
				Swal.fire( {
						title            : this.settings.get( 'done' ),
						icon             : 'success',
						text             : $scriptRunner.data( 'processed' ).replace( '{processed}', result.value.total ),
						confirmButtonText: this.settings.get( 'ok' ),
					} )
					.then( () => {
						this.$settingsWrapper.trigger( 'atum-settings-script-runner-done', [ $scriptRunner ] );
					} );
			}

		} );

	}

    doThemeSelector( $element: JQuery ) {

	    const $formSettingsWrapper: JQuery  = this.$form.find( '.form-settings-wrapper' ),
	          $themeSelectorWrapper: JQuery = this.$form.find( '.theme-selector-wrapper' ),
	          $themeOptions: JQuery         = $themeSelectorWrapper.find( '.selector-container .selector-box img' ),
	          themeSelectedValue: string    = $element.data( 'value' ),
	          resetDefault: number          = $element.data( 'reset' ),
	          $radioInput: JQuery           = this.$form.find( `#${ themeSelectedValue }` ),
	          $resetDefaultColors           = this.$form.find( '.reset-default-colors' );

	    $radioInput.prop( 'checked', true );
	    $themeOptions.removeClass( 'active' );
	    $element.find( 'img' ).addClass( 'active' );
	    $resetDefaultColors.data( 'value', themeSelectedValue );

	    $.ajax( {
		    url       : window[ 'ajaxurl' ],
		    method    : 'POST',
		    data      : {
			    token : this.settings.get( 'colorSchemeNonce' ),
			    action: this.settings.get( 'getColorScheme' ),
			    theme : themeSelectedValue,
			    reset : resetDefault,
		    },
		    beforeSend: () => $formSettingsWrapper.addClass( 'overlay' ),
		    success   : ( response: any ) => {

			    if ( response.success === true ) {

				    for ( let dataKey in response.data ) {
					    ColorPicker.updateColorPicker( this.$form.find( `#atum_${ dataKey }` ), response.data[ dataKey ] );
				    }

				    let title: string = '';

				    if ( themeSelectedValue === 'dark_mode' ) {
					    title = this.settings.get( 'dark' );
				    }
				    else if ( themeSelectedValue === 'hc_mode' ) {
					    title = this.settings.get( 'highContrast' );
				    }
				    else {
					    title = this.settings.get( 'branded' );
				    }

				    this.$form.find( '.section-title h2 span' ).html( title );
				    $formSettingsWrapper.removeClass( 'overlay' );
				    this.$form.find( 'input:submit' ).click();

			    }
			    else {
				    //console.log('Error');
			    }

		    },
	    } );

    }
	
	doResetDefault( $element: JQuery ) {

		const themeSelectedValue: string    = $element.data( 'value' ),
		      $colorSettingsWrapper: JQuery = this.$settingsWrapper.find( '#atum_setting_color_scheme' ),
		      $colorInputs: JQuery          = $colorSettingsWrapper.find( `input.atum-settings-input[data-display='${ themeSelectedValue }']` );

		$colorInputs.each( ( index: number, elem: Element ) => {
			const $elem: JQuery = $( elem );

			$elem.val( $elem.data( 'default' ) ).change();

		} );
		
	}

	toggleMultiCheckboxPanel( $switcher: JQuery ) {
		const $panel: JQuery = $switcher.siblings( '.atum-settings-multi-checkbox' );

		$panel.css( 'display', $switcher.is( ':checked' ) ? 'block' : 'none' );
	}

	clickCheckbox( $checkbox: JQuery ) {

		const $wrapper: JQuery = $checkbox.parents( '.atum-multi-checkbox-option' );

		if ( $checkbox.is( ':checked' ) ) {
			$wrapper.addClass( 'setting-checked' );
		}
		else {
			$wrapper.removeClass( 'setting-checked' );
		}

	}

	initDateTimePicker() {

		const $dateFrom: JQuery = this.$form.find( '.range-datepicker.range-from' ),
		      $dateTo: JQuery   = this.$form.find( '.range-datepicker.range-to' );

		if ( $dateFrom.length && $dateTo.length ) {
			this.dateTimePicker.addDateTimePickers( $dateFrom, { minDate: false, maxDate: new Date() } );
			this.dateTimePicker.addDateTimePickers( $dateTo, { minDate: false } );
		}
	}

	setDateTimeInputs() {
		const $dateFrom: JQuery = this.$form.find( '.range-datepicker.range-from' ),
		      $dateTo: JQuery   = this.$form.find( '.range-datepicker.range-to' ),
		      $field: JQuery    = this.$form.find( '.range-value' ),
		      $checkbox: JQuery = this.$form.find( '.remove-datepicker-range' );

		$field.val( JSON.stringify( {
			checked : $checkbox.is( ':checked' ),
			dateFrom: $dateFrom.val(),
			dateTo  : $dateTo.val(),
		} ) );
	}

	toggleRangeRemove( $checkbox: JQuery ) {
		const $panel: JQuery  = $checkbox.parent().siblings( '.range-fields-block' ),
		      $button: JQuery = $checkbox.parent().siblings( '.tool-runner' );

		$panel.css( 'display', $checkbox.is( ':checked' ) ? 'block' : 'none' );
		$button.text( $checkbox.is( ':checked' ) ? this.settings.get( 'removeRange' ) : this.settings.get( 'removeAll' ) );
	}

}