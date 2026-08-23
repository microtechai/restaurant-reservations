# Restaurant Reservations

A complete WordPress table-reservation plugin with a theme-independent shortcode, availability calendar, administration screens, statistics, CSV export, and optional email notifications.

## Installation

1. Copy `restaurant-reservations` into `wp-content/plugins/`.
2. Activate **Restaurant Reservations** in WordPress.
3. Configure hours and capacity under **Reservations → Settings**.
4. Add `[rr_reservation_form]` to any page or post.

## Requirements

- WordPress 6.0 or later
- PHP 7.4 or later

Reservation data is retained when the plugin is deactivated. The statistics table can be removed programmatically with `RRDatabase::drop_table()` if a site owner intentionally chooses to delete it.

## License

GPL-2.0-or-later.

