# Restaurant Reservations - WordPress Plugin Specification

## Overview
WordPress plugin for restaurant table reservations with calendar, real-time availability, and optional email notifications. Reusable across multiple WordPress sites.

## Architecture

### Plugin Structure
```
restaurant-reservations/
├── restaurant-reservations.php          # Main plugin file (header + bootstrap)
├── README.md                            # Documentation
├── includes/
│   ├── class-rr-activator.php           # Activation: DB tables, options, capabilities
│   ├── class-rr-deactivator.php         # Cleanup on deactivation
│   ├── class-rr-post-types.php          # CPT "rr_reservation" registration
│   ├── class-rr-database.php            # Custom tables manager (stats, logs)
│   ├── class-rr-admin.php               # Admin menu pages + columns
│   ├── class-rr-frontend.php            # Shortcode [rr_reservation_form]
│   ├── class-rr-ajax.php                # AJAX handlers (check availability, submit)
│   ├── class-rr-email.php               # Email notifications (optional)
│   ├── class-rr-calendar.php            # Calendar availability logic
│   ├── class-rr-stats.php               # Daily/weekly/monthly stats
│   └── class-rr-settings.php            # Settings page (WP options)
├── assets/
│   ├── css/
│   │   ├── admin.css                    # Admin styling
│   │   └── frontend.css                 # Frontend form styling
│   └── js/
│       ├── admin.js                     # Admin scripts
│       └── frontend.js                  # Frontend calendar + form
├── templates/
│   ├── reservation-form.php             # Frontend form template
│   ├── admin-reservations-list.php      # Admin reservations table
│   ├── admin-stats.php                  # Stats dashboard
│   └── admin-settings.php               # Settings page template
└── languages/
    └── restaurant-reservations.pot      # Translation template
```

### Data Model

#### CPT: `rr_reservation`
- title → customer name
- post_status → pending | confirmed | cancelled | completed
- post_date → reservation creation timestamp

#### Meta Fields (postmeta)
| Key | Description |
|-----|-------------|
| `_rr_date` | Reservation date (Y-m-d) |
| `_rr_time` | Reservation time (H:i) |
| `_rr_guests` | Number of guests (integer) |
| `_rr_email` | Customer email |
| `_rr_phone` | Customer phone |
| `_rr_notes` | Special requests |
| `_rr_table` | Table number/name (optional) |

#### Custom Tables
`wp_rr_stats_daily` — aggregated daily stats
| Column | Type | Description |
|--------|------|-------------|
| id | bigint(20) | Auto-increment |
| date | date | Date |
| total_reservations | int(11) | Total reservations |
| confirmed_reservations | int(11) | Confirmed count |
| cancelled_reservations | int(11) | Cancelled count |
| total_guests | int(11) | Total diners |
| max_guests | int(11) | Max guests for the day |
| created_at | datetime | When stat was recorded |

### Features

1. **Reservation Form [rr_reservation_form]**
   - Date picker (calendar view, min = today, max = +90 days)
   - Time slot selector (configurable intervals: 30min/60min)
   - Number of guests dropdown (1-20)
   - Customer: name, email, phone, notes
   - Real-time availability check via AJAX
   - CAPTCHA via honeypot field + time-based nonce

2. **Calendar Availability**
   - Configurable max guests per day/time slot
   - Configurable time slots (opening hours)
   - Block dates (holidays, private events)
   - Automatically close fully booked slots

3. **Email Notifications (Optional)**
   - Admin notification on new reservation
   - Customer confirmation email
   - Admin can toggle emails on/off in settings
   - Configurable email templates

4. **Admin Dashboard**
   - Reservations list with status management (pending→confirmed→cancelled→completed)
   - Calendar view of reservations
   - Filter: date range, status, time slot
   - Bulk actions: confirm, cancel, complete

5. **Statistics**
   - Daily: total reservations, total guests for any date
   - Weekly: aggregated totals for current/previous week
   - Monthly: aggregated totals for current/previous month
   - Export to CSV
   - Stats auto-updated when reservation status changes

## Acceptance Criteria

1. Shortcode `[rr_reservation_form]` renders a working form
2. Form submits reservation as CPT `rr_reservation` with proper meta
3. AJAX checks availability before submitting
4. Admin can view, filter, and manage reservations
5. Stats show daily/weekly/monthly totals of reservations and guests
6. Email notifications can be toggled on/off via settings
7. Settings page for: email admin address, time slots, max guests, business hours
8. All text is translatable via __() / _e()
9. Plugin works with any WordPress theme

## Implementation Order

1. Main plugin file, activation/deactivation hooks
2. CPT registration + meta fields
3. Admin reservations list page
4. Settings page
5. Frontend shortcode + form
6. AJAX availability + submission
7. Calendar logic
8. Stats engine
9. Email notifications
10. CSS/JS assets
11. Templates