=== Restaurant Reservations ===
Contributors: microtechai
Tags: restaurant, reservations, booking, table, calendar
Requires at least: 6.0
Tested up to: 7.1
Stable tag: 1.0.0
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Complete restaurant table reservation system with calendar, statistics, and optional email notifications.

== Description ==

Complete restaurant table reservation system:
- Multi-step reservation form with date picker and time slots
- Real-time availability checking via AJAX
- Admin dashboard with calendar, statistics, and CSV export
- Staff dashboard at /mesas/ for restaurant staff
- Table management with capacity, location, and availability
- Email notifications (optional)
- Translation-ready (Spanish included)

== Installation ==

1. Upload the `restaurant-reservations` folder to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to Reservations > Settings to configure business hours and capacity
4. Use the shortcode [rr_reservation_form] on any page

== Frequently Asked Questions ==

= Where is the staff dashboard? =
Visit /mesas/ on your site after logging in with a staff account.

== Screenshots ==

1. Reservation form
2. Admin dashboard
3. Staff dashboard
4. Calendar view

== Changelog ==

= 1.0.0 =
* Initial release
* Reservation form with date picker and time slots
* Admin dashboard with calendar and statistics
* Staff dashboard at /mesas/
* Table management
* Email notifications
* Spanish translations