# Hotel Booking Addon

An addon for the [MotoPress Hotel Booking Lite](https://wordpress.org/plugins/motopress-hotel-booking-lite/) WordPress plugin. It adds features that the Lite version lacks: creating and editing bookings manually in the admin, and taking prepayments through **WooCommerce**.

- **Version:** 1.0
- **Author:** Olena Polonska
- **License:** GPL2
- **Text Domain:** `hba`

## Features

### 1. Manual booking creation in the admin (`add-booking.php`)
- Lets administrators create new bookings (`mphb_booking`). By default, Lite doesn't allow this.
- Makes the **Check-in / Check-out** dates editable on the booking edit screen.
- Replaces the **Reserved Accommodations** meta box with a custom one (`templates/booking-page.php`), where you can:
  - choose an accommodation type and see which rooms are free for the selected dates (via AJAX);
  - choose a rate and set the number of adults and children;
  - add rooms to a booking and remove them.
- Saves customer details (name, email, phone, address, note) and creates or links a customer record.
- Recalculates the booking total when you save.
- Won't save a booking that has no accommodations.
- Adds a **"+ Add New Booking"** button to the MotoPress calendar page.

### 2. Prepayment through WooCommerce (`wp-payment.php`)
- After a customer books on the frontend, they're redirected to the WooCommerce cart. This works for both regular and REST checkout.
- The cart price is calculated like this:
  ```
  (booking total − discount) × prepayment %
  ```
- Adds a **WooCommerce → Settings → Booking** tab with these settings:
  | Option | Description | Default |
  |---|---|---|
  | Description Template | Item description in the cart (`%d` – ID, `%s` – rooms, check-in, check-out) | `Reservation #%d: %s. Dates: %s to %s.` |
  | Discount Amount | Fixed discount | `30` |
  | Prepayment, % | Prepayment percentage | `25` |
- Cleans up the cart display: no product image or link, and the "Product" column header is renamed to "Booking".
- Saves the booking ID in the order item meta.
- **Automatically confirms the booking** when the order status changes to `processing` or `completed`.
- Adds the full booking cost to the admin "New Order" email.

### 3. Single room page (`single-room-page.php`)
- Shows the featured image on the accommodation type page (hook `mphb_render_single_room_type_before_content`).

### 4. Search form (`class-regina-search-shortcode.php`)
- Extends `\MPHB\Shortcodes\SearchShortcode`. If a search asks for more than 2 adults, the search runs as if for 1 adult. The original value is then put back so the form still shows what the customer entered. This means larger groups can still find several rooms.

## Requirements
- WordPress
- [MotoPress Hotel Booking Lite](https://wordpress.org/plugins/motopress-hotel-booking-lite/). The addon only turns on when this plugin is active.
- [WooCommerce](https://wordpress.org/plugins/woocommerce/). Only needed for prepayments.

## Installation
1. Copy the `hotel-booking-addon` folder to `wp-content/plugins/`.
2. Activate **Hotel Booking Addon** under **Plugins**.
3. For payments:
   - create a WooCommerce product with the slug or title `booking` (any price, since the addon sets it);
   - fill in the options under **WooCommerce → Settings → Booking**.

## Structure
```
hotel-booking-addon/
├── hotel-booking-addon.php          # Main file: init and asset loading
├── add-booking.php                  # Creating/editing bookings in the admin
├── wp-payment.php                   # WooCommerce integration
├── single-room-page.php             # Featured image on the room page
├── class-regina-search-shortcode.php# Extended search shortcode
├── regina-search-addon.php          # Alternative search interceptor (not loaded)
├── templates/
│   └── booking-page.php             # "Reserved Accommodations" meta box template
├── css/
│   └── hotel-booking-addon.css      # Admin styles
└── js/
    └── hotel-booking-addon.js       # Admin booking page logic (AJAX)
```

## License
GPL v2 or later.
