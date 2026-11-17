<?php

namespace HotelBookingAddon;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Global flag to trigger WooCommerce redirection in REST API.
 */
class RedirectState {
    public static $should_redirect = false;
}

/**
 * Add Booking tab to WooCommerce settings.
 */
add_filter( 'woocommerce_settings_tabs_array', __NAMESPACE__ . '\add_booking_settings_tab', 50 );
function add_booking_settings_tab( $tabs ) {
    $tabs['booking'] = __( 'Booking', 'hba' );
    return $tabs;
}

/**
 * Add fields to the Booking tab.
 */
add_action( 'woocommerce_settings_tabs_booking', __NAMESPACE__ . '\add_booking_settings_fields' );
function add_booking_settings_fields() {
    woocommerce_admin_fields( [
        [
            'title' => __( 'Booking Settings', 'hba' ),
            'type'  => 'title',
            'id'    => 'hba_booking_settings_title'
        ],
        [
            'title'    => __( 'Description Template', 'hba' ),
            'desc'     => __( 'Template for the product description in the cart. Use %d for ID, %s for Rooms, %s for Check-in, %s for Check-out.', 'hba' ),
            'id'       => 'hba_booking_description_template',
            'type'     => 'textarea',
            'default'  => 'Reservation #%d: %s. Dates: %s to %s.',
            'css'      => 'min-width: 400px; height: 100px;',
        ],
        [
            'title'    => __( 'Discount Amount', 'hba' ),
            'desc'     => __( 'Fixed discount amount to subtract from the booking total.', 'hba' ),
            'id'       => 'hba_booking_discount_amount',
            'type'     => 'number',
            'default'  => '30',
            'custom_attributes' => [
                'step' => 'any',
                'min'  => '0',
            ],
        ],
        [
            'title'    => __( 'Prepayment, %', 'hba' ),
            'desc'     => __( 'Percentage of the total price to be paid.', 'hba' ),
            'id'       => 'hba_booking_prepayment_percentage',
            'type'     => 'number',
            'default'  => '25',
            'custom_attributes' => [
                'step' => 'any',
                'min'  => '0',
                'max'  => '100',
            ],
        ],
        [
            'type' => 'sectionend',
            'id'   => 'hba_booking_settings_sectionend'
        ],
    ] );
}

add_action( 'mphb_checkout_success_message', __NAMESPACE__ . '\dont_close_window_message', 999 );
function dont_close_window_message() {
	return __( 'Please do not close this page. In a short time you will be redirected to the payment page.', 'hba' );
}

/**
 * Save settings.
 */
add_action( 'woocommerce_update_options_booking', __NAMESPACE__ . '\save_booking_settings' );
function save_booking_settings() {
    woocommerce_update_options( [
        [ 'id' => 'hba_booking_description_template', 'type' => 'textarea' ],
        [ 'id' => 'hba_booking_discount_amount', 'type' => 'number' ],
        [ 'id' => 'hba_booking_prepayment_percentage', 'type' => 'number' ],
    ] );
}

/**
 * Intercept Hotel Booking Lite after a booking is created and prepare WooCommerce cart.
 */
add_action( 'mphb_create_booking_by_user', __NAMESPACE__ . '\create_booking_by_user', 10, 1 );
function create_booking_by_user( $booking ) {
    if ( ! class_exists( 'WooCommerce' ) ) {
        return;
    }

    $product_obj = get_page_by_path( 'booking', OBJECT, 'product' );
    if ( ! $product_obj ) {
        $product_obj = get_page_by_title( 'booking', OBJECT, 'product' );
    }
    if ( ! $product_obj ) {
        $product_obj = get_page_by_title( 'Booking', OBJECT, 'product' );
    }
    
    if ( ! $product_obj ) {
        return;
    }
    $product_id = $product_obj->ID;

    $discount   = (float) get_option( 'hba_booking_discount_amount', 0 );
    $percentage = (float) get_option( 'hba_booking_prepayment_percentage', 25 );
    
    $fullPrice  = max( 0, $booking->getTotalPrice() - $discount );
    $totalPrice = ( $fullPrice * $percentage ) / 100;
    
    $template   = get_option( 'hba_booking_description_template', 'Reservation #%d: %s. Dates: %s to %s.' );

    $checkIn    = $booking->getCheckInDate()->format( get_option( 'date_format' ) );
    $checkOut   = $booking->getCheckOutDate()->format( get_option( 'date_format' ) );
    
    $roomNames = [];
    foreach ( $booking->getReservedRooms() as $reservedRoom ) {
        $roomType = mphb_get_room_type( $reservedRoom->getRoomTypeId() );
        if ( $roomType ) {
            $roomNames[] = $roomType->getTitle();
        }
    }

    $description = sprintf(
        $template,
        $booking->getId(),
        implode( ', ', $roomNames ),
        $checkIn,
        $checkOut
    );
    
    $description .= sprintf( ' <span style="color: red; font-weight: bold;">(%s%% prepaid)</span>', $percentage );
	
    if ( function_exists( 'WC' ) ) {
        if ( is_null( WC()->cart ) && function_exists( 'wc_load_cart' ) ) {
            wc_load_cart();
        }

        if ( ! is_null( WC()->cart ) ) {
            WC()->cart->empty_cart();

            $cart_item_data = array(
                'mphb_booking_id'  => $booking->getId(),
                'mphb_total_price' => $totalPrice,
                'mphb_description' => $description
            );

            WC()->cart->add_to_cart( $product_id, 1, 0, array(), $cart_item_data );

            RedirectState::$should_redirect = true;
            
            if ( ! defined( 'REST_REQUEST' ) || ! REST_REQUEST ) {
                $cart_url = wc_get_cart_url();
                if ( $cart_url ) {
                    wp_redirect( $cart_url );
                    exit;
                }
            }
        }
    }
}

/**
 * Intercept the REST API response to override the redirect URL.
 */
add_filter( 'rest_post_dispatch', __NAMESPACE__ . '\override_rest_checkout_redirect', 10, 3 );
function override_rest_checkout_redirect( $response, $server, $request ) {
    if ( RedirectState::$should_redirect && strpos( $request->get_route(), 'checkout' ) !== false ) {
        $data = $response->get_data();
        if ( is_array( $data ) && isset( $data['redirect_url'] ) ) {
            $data['redirect_url'] = wc_get_cart_url();
            $response->set_data( $data );
        }
    }
    return $response;
}

/**
 * Update the price in the WooCommerce cart dynamically.
 */
add_action( 'woocommerce_before_calculate_totals', __NAMESPACE__ . '\before_calculate_totals', 10, 1 );
function before_calculate_totals( $cart ) {
    if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
        return;
    }

    foreach ( $cart->get_cart() as $cart_item ) {
        if ( isset( $cart_item['mphb_total_price'] ) ) {
            $cart_item['data']->set_price( $cart_item['mphb_total_price'] );
        }
    }
}

/**
 * Remove the redundant link and the word 'Booking' from the cell, showing only description.
 */
add_filter( 'woocommerce_cart_item_name', __NAMESPACE__ . '\filter_booking_product_name', 10, 3 );
function filter_booking_product_name( $product_name, $cart_item, $cart_item_key ) {
    if ( isset( $cart_item['mphb_description'] ) ) {
        return '<div class="mphb-cart-item-description">' . $cart_item['mphb_description'] . '</div>';
    }
    return $product_name;
}

/**
 * Change 'Product' header to 'Booking' in the cart and checkout tables.
 */
add_filter( 'gettext', __NAMESPACE__ . '\change_cart_product_header', 20, 3 );
function change_cart_product_header( $translated_text, $text, $domain ) {
    if ( ( is_cart() || is_checkout() ) && $text === 'Product' && $domain === 'woocommerce' ) {
        return __( 'Booking', 'hba' );
    }
    return $translated_text;
}

/**
 * Remove product image from the cart.
 */
add_filter( 'woocommerce_cart_item_thumbnail', '__return_empty_string' );

/**
 * Hide the thumbnail column via CSS on the cart page.
 */
add_action( 'wp_head', __NAMESPACE__ . '\inject_cart_styles' );
function inject_cart_styles() {
    if ( is_cart() ) {
        echo '<style>.woocommerce-cart-form th.product-thumbnail, .woocommerce-cart-form td.product-thumbnail { display: none !important; }</style>';
    }
}

/**
 * Save the Booking ID to the WooCommerce Order for reference.
 */
add_action( 'woocommerce_checkout_create_order_line_item', __NAMESPACE__ . '\checkout_create_order_line_item', 10, 4 );
function checkout_create_order_line_item( $item, $cart_item_key, $values, $order ) {
    if ( isset( $values['mphb_booking_id'] ) ) {
        $item->add_meta_data( '_mphb_booking_id', $values['mphb_booking_id'] );
    }
    if ( isset( $values['mphb_description'] ) ) {
        $item->add_meta_data( '_mphb_details', $values['mphb_description'] );
    }
}

/**
 * Automatically confirm the MotoPress booking when the WooCommerce order is paid.
 */
add_action( 'woocommerce_order_status_changed', __NAMESPACE__ . '\auto_confirm_mphb_booking', 10, 4 );
function auto_confirm_mphb_booking( $order_id, $old_status, $new_status, $order ) {
    if ( in_array( $new_status, array( 'processing', 'completed' ) ) ) {
        foreach ( $order->get_items() as $item ) {
            $booking_id = $item->get_meta( '_mphb_booking_id' );
            if ( $booking_id ) {
                $booking = MPHB()->getBookingRepository()->findById( $booking_id, true );
                if ( $booking && $booking->getStatus() !== 'confirmed' ) {
                    $booking->setStatus( 'confirmed' );
                    MPHB()->getBookingRepository()->save( $booking );
                }
            }
        }
    }
}

add_action( 'woocommerce_email_order_meta', __NAMESPACE__ . '\inject_full_cost_into_wc_admin_email', 20, 4 );
function inject_full_cost_into_wc_admin_email( $order, $sent_to_admin, $plain_text, $email ) {
	if ( ! $sent_to_admin || ! $order ) {
		return;
	}

	$is_booking = false;
	foreach ( $order->get_items() as $item ) {
		if ( $item->get_meta( '_mphb_booking_id' ) ) {
			$is_booking = true;
			break;
		}
	}

	if ( ! $is_booking ) {
		return;
	}

	$order_total = (float) $order->get_total();
	$discount    = (float) get_option( 'hba_booking_prepayment_percentage', 0 );

	$price_without_discount = $order_total * 100 / $discount;

	$currency = function_exists( 'get_woocommerce_currency_symbol' ) ? get_woocommerce_currency_symbol() : '';
	$price_without_discount_fmt = $currency . number_format( $price_without_discount, 2, '.', ' ' );

	if ( $plain_text ) {
		echo "\n========================================\n";
		echo esc_html__( 'Full cost (without discount):', 'hba' ) . " " . $price_without_discount_fmt . "\n";
		echo "========================================\n";
	} else {
		echo sprintf(
			'<table cellpadding="0" cellspacing="0" style="width:100%%;border-top:2px solid #dddddd;margin-top:20px; margin-bottom:20px;">'
			. '<tr>'
			.   '<td style="padding:10px 0 4px;font-size:14px;color:#555555;">%s</td>'
			.   '<td style="padding:10px 0 4px;font-size:14px;color:#333333;text-align:right;font-weight:bold;">%s</td>'
			. '</tr>'
			. '</table>',
			esc_html__( 'Full cost (without discount):', 'hba' ),
			esc_html( $price_without_discount_fmt )
		);
	}
}
