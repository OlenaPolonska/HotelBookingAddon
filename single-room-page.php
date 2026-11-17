<?php

namespace HotelBookingAddon;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Add featured image to the single room page.
 * 
 * Uses the 'mphb_render_single_room_type_before_content' hook from MotoPress Hotel Booking Lite.
 */
add_action( 'mphb_render_single_room_type_before_content', __NAMESPACE__ . '\regina_add_featured_image_to_single_room' );

function regina_add_featured_image_to_single_room() {
    // Only display if the room type has a featured image set
    if ( has_post_thumbnail() ) {
        echo '<div class="regina-room-featured-image-wrapper">';
        the_post_thumbnail( 'large', array( 'class' => 'regina-room-featured-image' ) );
        echo '</div>';
    }
}
