<?php
/*
Plugin Name: Hotel Booking Addon
Description: Addon for Hotel Booking Light plugin (https://motopress.com/products/hotel-booking)
Text Domain: hba
Version: 1.0
Author: Olena Polonska
License: GPL-2.0-or-later
*/

namespace HotelBookingAddon;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_action( 'plugins_loaded', __NAMESPACE__ . '\init_booking_addon' );
function init_booking_addon() {
    if ( ! class_exists( '\MPHB\Shortcodes\SearchShortcode' ) ) {
        return; 
    }

    require_once plugin_dir_path( __FILE__ ) . 'single-room-page.php';
    require_once plugin_dir_path( __FILE__ ) . 'add-booking.php';
    require_once plugin_dir_path( __FILE__ ) . 'wp-payment.php';
	
    require_once plugin_dir_path( __FILE__ ) . 'class-regina-search-shortcode.php';    
    $shortcode = new \HotelBookingAddon\ReginaSearchShortcode();
}

/**
 * Enqueue admin styles and scripts.
 */
add_action( 'admin_enqueue_scripts', __NAMESPACE__ . '\enqueue_admin_assets' );
function enqueue_admin_assets( $hook ) {
    wp_enqueue_style( 'hba-admin-css', plugin_dir_url( __FILE__ ) . 'css/hotel-booking-addon.css', array(), '1.0' );

    $screen = get_current_screen();
    if ( $screen && 'mphb_booking' === $screen->post_type ) {
        wp_enqueue_script( 'hba-admin-js', plugin_dir_url( __FILE__ ) . 'js/hotel-booking-addon.js', array( 'jquery' ), '1.0', true );

        wp_localize_script( 'hba-admin-js', 'regina_hba', array(
            'nonce' => wp_create_nonce( 'regina_room_data' )
        ) );
    }
}
