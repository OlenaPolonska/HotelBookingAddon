<?php

namespace HotelBookingAddon;

use MPHB\Shortcodes;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
class ReginaSearchShortcode extends \MPHB\Shortcodes\SearchShortcode {
    
	private $adults;
	
	public function __construct() {
		parent::__construct();
		add_action( 'wp', [$this, 'set_adults'], 5 );
		add_action( 'wp', [$this, 'restore_adults'], 10 );
	}
	
	function set_adults() {
		$this->adults = absint( $_GET['mphb_adults'] ?? 0 );
		
		if ( $this->adults && $this->adults > 2 ) {
			$_GET['mphb_adults'] = 1;
			$_REQUEST['mphb_adults'] = 1;
		}
	}
	
	function restore_adults() {
		if ( $this->adults ) {
			$_GET['mphb_adults'] = $this->adults;
			$_REQUEST['mphb_adults'] = $this->adults;
		}
	}
};