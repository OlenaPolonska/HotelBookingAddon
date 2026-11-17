<?php

namespace HotelBookingAddon;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( !is_admin() ) return;

add_filter( 'register_post_type_args', __NAMESPACE__ . '\regina_enable_booking_creation', 999, 2 );
function regina_enable_booking_creation( $args, $post_type ) {
    if ( 'mphb_booking' === $post_type ) {
        $args['capabilities'] = [
            'create_posts' => 'manage_options', 
        ];
        $args['map_meta_cap'] = true;
    }
    return $args;
}

add_filter( 'mphb_edit_page_field_groups', __NAMESPACE__ . '\regina_make_booking_dates_editable', 20, 2 );
function regina_make_booking_dates_editable( $fieldGroups, $postType ) {
    if ( 'mphb_booking' === $postType ) {
        foreach ( $fieldGroups as $group ) {
            if ( $group->getName() === 'mphb_main' ) {
                $checkIn = $group->getFieldByName( 'mphb_check_in_date' );
                if ( $checkIn ) {
                    $checkIn->setReadonly( false );
                }
                $checkOut = $group->getFieldByName( 'mphb_check_out_date' );
                if ( $checkOut ) {
                    $checkOut->setReadonly( false );
                }
            }
        }
    }
    return $fieldGroups;
}

add_action( 'add_meta_boxes_mphb_booking', __NAMESPACE__ . '\regina_register_booking_meta_boxes', 20 );
function regina_register_booking_meta_boxes() {
    remove_meta_box( 'mphb_rooms', 'mphb_booking', 'advanced' );
    add_meta_box( 
        'mphb_rooms', 
        __( 'Reserved Accommodations', 'motopress-hotel-booking' ), 
        __NAMESPACE__ . '\regina_render_rooms_details_meta_box', 
        'mphb_booking', 
        'advanced' 
    );
}

function regina_render_rooms_details_meta_box( $post ) {
    $reservedRooms = MPHB()->getReservedRoomRepository()->findAllByBooking( $post->ID );
    $roomTypes = MPHB()->getRoomTypeRepository()->getIdTitleList();

    include plugin_dir_path( __FILE__ ) . 'templates/booking-page.php';
}

add_action( 'wp_ajax_regina_get_room_data', __NAMESPACE__ . '\regina_ajax_get_room_data' );
function regina_ajax_get_room_data() {
    check_ajax_referer( 'regina_room_data' );

    $roomTypeId = intval( $_GET['room_type_id'] );
    $checkIn = sanitize_text_field( $_GET['check_in'] );
    $checkOut = sanitize_text_field( $_GET['check_out'] );
    $bookingId = isset($_GET['booking_id']) ? intval($_GET['booking_id']) : 0;

    $dateFormat = MPHB()->settings()->dateTime()->getDateFormat();
    $checkInDate = \DateTime::createFromFormat( $dateFormat, $checkIn );
    $checkOutDate = \DateTime::createFromFormat( $dateFormat, $checkOut );

    if ( ! $checkInDate || ! $checkOutDate ) {
        wp_send_json_error(array('message' => 'Invalid dates.'));
    }

    $availableRooms = MPHB()->getRoomRepository()->getAvailableRooms( $checkInDate, $checkOutDate, $roomTypeId );
    
    if ($bookingId > 0) {
        $currentReserved = MPHB()->getReservedRoomRepository()->findAllByBooking($bookingId);
        foreach ($currentReserved as $res) {
            $currentRoom = MPHB()->getRoomRepository()->findById($res->getRoomId());
            if ($currentRoom && $currentRoom->getRoomTypeId() == $roomTypeId) {
                if (!isset($availableRooms[$roomTypeId]) || !in_array($res->getRoomId(), $availableRooms[$roomTypeId])) {
                    $availableRooms[$roomTypeId][] = $res->getRoomId();
                }
            }
        }
    }

    $rooms = array();
    if ( ! empty( $availableRooms[ $roomTypeId ] ) ) {
        foreach ( $availableRooms[ $roomTypeId ] as $roomId ) {
            $rooms[ $roomId ] = get_the_title( $roomId );
        }
    }

    // Use a safer way to get rates to avoid "wp_mphb_custom_prices" table error in Lite version
    $allRates = MPHB()->getRateRepository()->findAllByRoomType( $roomTypeId );
    $rates = array();
    foreach ( $allRates as $rate ) {
        if ( $rate->isAvailableForDates( $checkInDate, $checkOutDate ) ) {
            $rates[ $rate->getId() ] = $rate->getTitle();
        }
    }

    if (empty($rooms)) {
        wp_send_json_error(array('message' => 'No available rooms for these dates.'));
    }

    wp_send_json_success( array(
        'rooms' => $rooms,
        'rates' => $rates
    ) );
}

add_action( 'save_post_mphb_booking', __NAMESPACE__ . '\regina_save_booking_data', 20 );
function regina_save_booking_data( $postId ) {
    static $is_saving = false;

    if ( $is_saving ) return;
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
    if ( ! isset( $_POST['post_type'] ) || $_POST['post_type'] !== 'mphb_booking' ) return;
    if ( ! current_user_can( 'edit_post', $postId ) ) return;

    $is_saving = true;

    $customer_fields = array(
        'mphb_first_name', 'mphb_last_name', 'mphb_email', 'mphb_phone', 
        'mphb_country', 'mphb_address1', 'mphb_city', 'mphb_state', 'mphb_zip', 'mphb_note'
    );
    foreach ( $customer_fields as $field ) {
        if ( isset( $_POST[$field] ) ) {
            if ($field === 'mphb_email') {
                update_post_meta( $postId, $field, sanitize_email( $_POST[$field] ) );
            } elseif ($field === 'mphb_note') {
                update_post_meta( $postId, $field, sanitize_textarea_field( $_POST[$field] ) );
            } else {
                update_post_meta( $postId, $field, sanitize_text_field( $_POST[$field] ) );
            }
        }
    }

    $booking = MPHB()->getBookingRepository()->findById( $postId, true );
    if ( $booking ) {
        $customerRecord = MPHB()->customers()->createCustomerOnBooking( $booking, false );
        if ( ! is_wp_error( $customerRecord ) ) {
            update_post_meta( $postId, 'mphb_customer_id', $customerRecord );
        }
    }

    if ( ! empty( $_POST['regina_rooms_to_remove'] ) ) {
        $idsToRemove = explode( ',', $_POST['regina_rooms_to_remove'] );
        foreach ( $idsToRemove as $id ) {
            $id = intval($id);
            $reservedRoom = MPHB()->getReservedRoomRepository()->findById( $id );
            if ( $reservedRoom && $reservedRoom->getBookingId() == $postId ) {
                wp_delete_post( $id, true );
            }
        }
    }

    if ( ! empty( $_POST['regina_pending_rooms'] ) && is_array( $_POST['regina_pending_rooms'] ) ) {
        foreach ( $_POST['regina_pending_rooms'] as $roomJson ) {
            $data = json_decode( stripslashes( $roomJson ), true );
            if ( $data ) {
                $reservedRoom = new \MPHB\Entities\ReservedRoom( array(
                    'booking_id' => $postId,
                    'room_id'    => intval( $data['room_id'] ),
                    'rate_id'    => intval( $data['rate_id'] ),
                    'adults'     => intval( $data['adults'] ),
                    'children'   => intval( $data['children'] ),
                ) );
                MPHB()->getReservedRoomRepository()->save( $reservedRoom );
            }
        }
    }

    $booking = MPHB()->getBookingRepository()->findById( $postId, true );
    if ( $booking ) {
        $checkIn  = get_post_meta( $postId, 'mphb_check_in_date', true );
        if ( empty($checkIn) && isset($_POST['mphb_check_in_date']) ) $checkIn = sanitize_text_field($_POST['mphb_check_in_date']);

        $checkOut = get_post_meta( $postId, 'mphb_check_out_date', true );
        if ( empty($checkOut) && isset($_POST['mphb_check_out_date']) ) $checkOut = sanitize_text_field($_POST['mphb_check_out_date']);

        if ( ! empty( $checkIn ) && ! empty( $checkOut ) ) {
            $checkInDate  = \DateTime::createFromFormat( 'Y-m-d', $checkIn );
            $checkOutDate = \DateTime::createFromFormat( 'Y-m-d', $checkOut );
            if ( $checkInDate && $checkOutDate ) $booking->setDates( $checkInDate, $checkOutDate );
        }

        $reservedRooms = MPHB()->getReservedRoomRepository()->findAllByBooking( $postId, true );
        if ( empty( $reservedRooms ) ) {
             wp_die( __( 'Please add at least one accommodation before saving the booking.', 'hba' ), __( 'Validation Error', 'hba' ), array( 'back_link' => true ) );
        }
        $booking->setRooms( $reservedRooms );

        if ( !is_null($booking->getCheckInDate()) && !is_null($booking->getCheckOutDate()) ) {
            $booking->updateTotal();
            MPHB()->getBookingRepository()->save( $booking );
        }
    }

    $is_saving = false;
}

add_action('admin_notices', __NAMESPACE__ . '\add_custom_button_to_mphb_calendar_php');
function add_custom_button_to_mphb_calendar_php() {
    // Проверяем, что находимся именно на странице Календаря MotoPress
    if ( isset($_GET['page']) && $_GET['page'] === 'mphb_calendar' ) {
        ?>
        <div style="margin-top: 15px; margin-bottom: 10px;">
            <a href="<?php echo admin_url( 'post-new.php?post_type=mphb_booking' ) ?>" class="button button-primary button-large">
                + Add New Booking
            </a>
        </div>
        <?php
    }
}
