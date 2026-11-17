<?php
/**
 * Template for the Booking meta box (Accommodations section)
 *
 * @var WP_Post $post
 * @var \MPHB\Entities\ReservedRoom[] $reservedRooms
 * @var array $roomTypes
 */
?>
<div id="regina-reserved-rooms-wrapper">
    <?php if ( ! empty( $reservedRooms ) ) : ?>
        <h3><?php esc_html_e( 'Current Accommodations', 'motopress-hotel-booking' ); ?></h3>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php esc_html_e( 'Accommodation', 'motopress-hotel-booking' ); ?></th>
                    <th><?php esc_html_e( 'Rate', 'motopress-hotel-booking' ); ?></th>
                    <th><?php esc_html_e( 'Guests', 'motopress-hotel-booking' ); ?></th>
                    <th><?php esc_html_e( 'Actions', 'motopress-hotel-booking' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $reservedRooms as $reservedRoom ) : 
                    $room = MPHB()->getRoomRepository()->findById( $reservedRoom->getRoomId() );
                    $rate = MPHB()->getRateRepository()->findById( $reservedRoom->getRateId() );
                ?>
                    <tr>
                        <td><?php echo ( $room ? esc_html( $room->getTitle() ) : '—' ); ?></td>
                        <td><?php echo ( $rate ? esc_html( $rate->getTitle() ) : '—' ); ?></td>
                        <td><?php printf( __( 'Adults: %d, Children: %d', 'motopress-hotel-booking' ), $reservedRoom->getAdults(), $reservedRoom->getChildren() ); ?></td>
                        <td>
                            <button type="button" class="button regina-remove-room" data-id="<?php echo esc_attr( $reservedRoom->getId() ); ?>">
                                <?php esc_html_e( 'Remove', 'motopress-hotel-booking' ); ?>
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else : ?>
        <p><?php esc_html_e( 'No accommodations reserved yet.', 'motopress-hotel-booking' ); ?></p>
    <?php endif; ?>
</div>

<hr />
<h3><?php esc_html_e( 'Add Accommodation', 'motopress-hotel-booking' ); ?></h3>

<div id="regina-add-room-form">
    <p>
        <label><?php esc_html_e( 'Accommodation Type:', 'motopress-hotel-booking' ); ?></label><br />
        <select id="regina-room-type" name="regina_new_room[type_id]">
            <option value=""><?php esc_html_e( '— Select —', 'motopress-hotel-booking' ); ?></option>
            <?php foreach ( $roomTypes as $id => $title ) : ?>
                <option value="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $title ); ?></option>
            <?php endforeach; ?>
        </select>
    </p>
    <p>
        <label><?php esc_html_e( 'Accommodation:', 'motopress-hotel-booking' ); ?></label><br />
        <select id="regina-room" name="regina_new_room[room_id]" >
            <option value=""><?php esc_html_e( '— Select Type First —', 'motopress-hotel-booking' ); ?></option>
        </select>
    </p>
    <p>
        <label><?php esc_html_e( 'Rate:', 'motopress-hotel-booking' ); ?></label><br />
        <select id="regina-rate" name="regina_new_room[rate_id]" >
            <option value=""><?php esc_html_e( '— Select Type First —', 'motopress-hotel-booking' ); ?></option>
        </select>
    </p>
    <p>
        <label><?php esc_html_e( 'Adults:', 'motopress-hotel-booking' ); ?></label>
        <input type="number" name="regina_new_room[adults]" value="1" min="1" />
        <label><?php esc_html_e( 'Children:', 'motopress-hotel-booking' ); ?></label>
        <input type="number" name="regina_new_room[children]" value="0" min="0" />
    </p>
    <p>
        <button type="button" id="regina-add-room-btn" class="button button-secondary"><?php esc_html_e( 'Add to Booking', 'motopress-hotel-booking' ); ?></button>
    </p>
</div>
<input type="hidden" name="regina_rooms_to_remove" id="regina-rooms-to-remove" value="" />
<div id="regina-pending-rooms"></div>
