<?php
/**
 * @package         Atum
 * @subpackage      Components
 * @author          Salva Machí and Jose Piera - https://sispixels.com
 * @copyright       ©2017 Stock Management Labs™
 *
 * @since           1.2.5
 *
 * The class resposible to export the ATUM data to downloadable files
 */

namespace Atum\Components;

use Atum\Inc\Globals;
use Atum\Inc\Helpers;


defined( 'ABSPATH' ) or die;


class DataExport {

	public function __construct() {

		add_action( 'admin_enqueue_scripts', array($this, 'enqueue_scripts') );

		// Ajax action to export the ATUM data to file
		add_action( 'wp_ajax_atum_export_data', array( $this, 'export_data' ) );

	}

	/**
	 * Enqueue the required scripts
	 *
	 * @since 1.2.5
	 *
	 * @param string $hook
	 */
	public function enqueue_scripts( $hook ) {

		// Load the script on the "Stock Central" page
		if ($hook == 'toplevel_page_' . Globals::ATUM_UI_SLUG ) {

			$min = (! ATUM_DEBUG) ? '.min' : '';
			wp_register_script( 'atum-data-export', ATUM_URL . "assets/js/atum.data.export$min.js", array('jquery'), ATUM_VERSION, TRUE );

			wp_localize_script( 'atum-data-export', 'atumExport', array(
				'tabTitle'          => __( 'Export Data', ATUM_TEXT_DOMAIN ),
				'submitTitle'       => __( 'Export', ATUM_TEXT_DOMAIN ),
				'outputFormatTitle' => __( 'Output Format', ATUM_TEXT_DOMAIN ),
				'outputFormats'     => array(
					'csv' => 'CSV',
					'pdf' => 'PDF',
					'xlsx' => 'XLSX'
				),
				'chooseFormat'      => __( 'Please choose the output format', ATUM_TEXT_DOMAIN ),
				'productTypesTitle' => __( 'Product Types', ATUM_TEXT_DOMAIN ),
				'productTypes'      => Helpers::product_types_dropdown(),
				'exportNonce'       => wp_create_nonce('atum-data-export-nonce')
			) );

			wp_enqueue_script( 'atum-data-export' );

		}

	}

	/**
	 * Set the reponse header to make the returning file downloadable
	 *
	 * @since 1.2.5
	 *
	 * @param string $filename  The output file name
	 * @param string $type       The file type
	 */
	private function set_file_headers($filename, $type){

		if ( strpos($filename, ".$type") === FALSE ){
			$filename .= ".$type";
		}

		$mime_type = '';
		switch ( $type ) {

			case 'pdf':
				$mime_type = 'application/pdf';
		        break;

			case 'xls':
				$mime_type = 'application/vnd.ms-excel';
				break;

			case 'xslx':
				$mime_type = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
				break;

			case 'csv':
				$mime_type = 'text/csv';
				break;

		}

		header( 'Content-Description: File Transfer' );
		header( "Content-type: $mime_type" );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Content-Transfer-Encoding: binary' );
		header( 'Expires: 0' );
		header( 'Cache-Control: must-revalidate' );
		header( 'Pragma: public' );

	}

	/**
	 * Export the ATUM data to file
	 *
	 * @since 1.2.5
	 */
	public function export_data() {

		check_ajax_referer( 'atum-data-export-nonce', 'token' );

		$mpdf = new \mPDF( 'utf-8', 'A4-L' );
		$common_stylesheet = file_get_contents( ABSPATH . 'wp-admin/css/common.css');
		$mpdf->WriteHTML($common_stylesheet, 1);
		$atum_stylesheet = file_get_contents( ATUM_PATH . 'assets/css/atum-list.css');
		$mpdf->WriteHTML($atum_stylesheet, 1);
		$mpdf->WriteHTML( Helpers::load_view_to_string('data-export/report-pdf') );
		echo $mpdf->Output();

	}

}