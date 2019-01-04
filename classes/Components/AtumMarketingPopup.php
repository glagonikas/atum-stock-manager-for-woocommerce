<?php
/**
 * Add Marketing Popup
 *
 * @package        Atum
 * @subpackage     Components
 * @author         Be Rebel - https://berebel.io
 * @copyright      ©2018 Stock Management Labs™
 *
 * @since          1.5.2
 */

namespace Atum\Components;

use Atum\Inc\Helpers;

defined( 'ABSPATH' ) || die;


class AtumMarketingPopup {

	/**
	 * The marketing popup title
	 *
	 * @var string
	 */
	protected $text = '';

	/**
	 * The marketing popup title
	 *
	 * @var string
	 */
	protected $image = '';

	/**
	 * The marketing popup url
	 *
	 * @var string
	 */
	protected $url = '';

	/**
	 * The marketing popup background
	 *
	 * @var string
	 */
	protected $background = '';

	/**
	 * The hide popup transient key
	 *
	 * @var string
	 */
	protected $transient_key = '';

	/**
	 * The ATUM's addons store URL
	 */
	const MARKETING_POPUP_STORE_URL = 'http://stockmanagementlabs.loc/';

	/**
	 * The ATUM's addons API endpoint
	 */
	const MARKETING_POPUP_API_ENDPOINT = 'marketing-popup-api';

	/**
	 * The singleton instance holder
	 *
	 * @var AtumMarketingPopup
	 */
	private static $instance;

	/**
	 * Singleton constructor
	 *
	 * @since 1.5.2
	 */
	public function __construct() {

		$request_params = array(
			'method'      => 'POST',
			'timeout'     => 15,
			'redirection' => 1,
			'httpversion' => '1.0',
			'user-agent'  => 'ATUM/' . ATUM_VERSION . ';' . home_url(),
			'blocking'    => TRUE,
			'headers'     => array(),
			'body'        => array(),
			'cookies'     => array(),
		);

		// Call marketing popup info.
		$marketing_popup = wp_remote_post( self::MARKETING_POPUP_STORE_URL . self::MARKETING_POPUP_API_ENDPOINT, $request_params );

		if ( ! is_wp_error( $marketing_popup ) ) {
			$marketing_popup = json_decode( wp_remote_retrieve_body( $marketing_popup ) );

			if ( $marketing_popup ) {
				$this->background    = $marketing_popup->background_color . ' ' . $marketing_popup->background_image . ' ' . $marketing_popup->background_position . '/100% 100% ' . $marketing_popup->background_repeat;
				$this->image         = $marketing_popup->image;
				$this->text          = $marketing_popup->text;
				$this->url           = $marketing_popup->url;
				$this->transient_key = $marketing_popup->transient_key;
			}
		}

	}

	/**
	 * Getter for the text
	 *
	 * @since 1.5.2
	 *
	 * @return string
	 */
	public function get_text() {

		return $this->text;
	}

	/**
	 * Getter for the image
	 *
	 * @since 1.5.2
	 *
	 * @return string
	 */
	public function get_image() {

		return $this->image;
	}

	/**
	 * Getter for the url
	 *
	 * @since 1.5.2
	 *
	 * @return string
	 */
	public function get_url() {

		return $this->url;
	}

	/**
	 * Getter for the background
	 *
	 * @since 1.5.2
	 *
	 * @return string
	 */
	public function get_background() {

		return $this->background;

	}

	/**
	 * Getter for the transient key
	 *
	 * @since 1.5.2
	 *
	 * @return string
	 */
	public function get_transient_key() {

		return $this->transient_key;

	}

	/*******************
	 * Instance methods
	 *******************/

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
	 * @return AtumMarketingPopup instance
	 */
	public static function get_instance() {

		if ( ! ( self::$instance && is_a( self::$instance, __CLASS__ ) ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

}
