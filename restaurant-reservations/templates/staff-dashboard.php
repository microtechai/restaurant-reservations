<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

// Use pre-prepared data from RRStaffDashboard when available
$today_reservations = get_query_var( 'rr_today_reservations' ) ?: null;
$today_date         = get_query_var( 'rr_today_date' ) ?: current_time( 'Y-m-d' );
$calendar_year      = get_query_var( 'rr_calendar_year' ) ?: (int) current_time( 'Y' );
$calendar_month     = get_query_var( 'rr_calendar_month' ) ?: (int) current_time( 'n' );
$calendar_counts    = get_query_var( 'rr_calendar_counts' ) ?: array();

// Fallback: fetch directly if class did not prepare data
if ( ! $today_reservations ) {
	$today_reservations = get_posts(
		array(
			'post_type'      => 'rr_reservation',
			'post_status'    => array( 'pending', 'confirmed', 'cancelled', 'completed' ),
			'posts_per_page' => -1,
			'meta_key'       => '_rr_time',
			'orderby'        => 'meta_value',
			'order'          => 'ASC',
			'meta_query'     => array(
				array(
					'key'   => '_rr_date',
					'value' => $today_date,
				),
			),
		)
	);
}
$month_start    = sprintf( '%04d-%02d-01', $calendar_year, $calendar_month );
$days_in_month  = (int) wp_date( 't', strtotime( $month_start ) );
$calendar_first_dow = (int) wp_date( 'w', strtotime( $month_start ) );
$prev_month = strtotime( '-1 month', strtotime( $month_start ) );
$next_month = strtotime( '+1 month', strtotime( $month_start ) );
if ( empty( $calendar_counts ) ) {
	$calendar_posts = get_posts(
		array(
			'post_type'      => 'rr_reservation',
			'post_status'    => array( 'pending', 'confirmed', 'cancelled', 'completed' ),
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'meta_query'     => array(
				array(
					'key'     => '_rr_date',
					'value'   => array( $month_start, sprintf( '%04d-%02d-%02d', $calendar_year, $calendar_month, $days_in_month ) ),
					'compare' => 'BETWEEN',
					'type'    => 'DATE',
				),
			),
		)
	);
	foreach ( $calendar_posts as $calendar_post_id ) {
		$reservation_date = get_post_meta( $calendar_post_id, '_rr_date', true );
		$calendar_counts[ $reservation_date ] = isset( $calendar_counts[ $reservation_date ] ) ? $calendar_counts[ $reservation_date ] + 1 : 1;
	}
}

$current_user = wp_get_current_user();
$status_labels = array(
	'pending'   => __( 'Pendiente', 'restaurant-reservations' ),
	'confirmed' => __( 'Confirmada', 'restaurant-reservations' ),
	'completed' => __( 'Completada', 'restaurant-reservations' ),
	'cancelled' => __( 'Cancelada', 'restaurant-reservations' ),
);
$month_label = wp_date( 'F Y', mktime( 0, 0, 0, (int) $calendar_month, 1, (int) $calendar_year ) );
$today_label = wp_date( 'l, j \d\e F \d\e Y', strtotime( $today_date ) );

wp_enqueue_style( 'rr-staff-fonts', 'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Noto+Serif:wght@600;700&display=swap', array(), null );
get_header();
?>
<main class="rr-staff-wrap">
	<header class="rr-staff-header">
		<a class="rr-staff-brand" href="<?php echo esc_url( site_url( '/mesas/' ) ); ?>">El Cielo</a>
		<div class="rr-staff-account">
			<span><?php echo esc_html( $current_user->display_name ); ?></span>
			<a href="<?php echo esc_url( wp_logout_url( site_url( '/mesas/' ) ) ); ?>"><?php echo esc_html__( 'Cerrar sesión', 'restaurant-reservations' ); ?></a>
		</div>
	</header>

	<section class="rr-staff-today" aria-labelledby="rr-today-heading">
		<h2 id="rr-today-heading"><?php echo esc_html( $today_label ); ?></h2>
		<?php if ( empty( $today_reservations ) ) : ?>
			<p class="rr-empty-state"><?php echo esc_html__( 'No hay reservas para hoy.', 'restaurant-reservations' ); ?></p>
		<?php else : ?>
			<div class="rr-table-scroll">
				<table class="rr-reservations-table">
					<thead><tr>
						<th scope="col"><?php echo esc_html__( 'Hora', 'restaurant-reservations' ); ?></th>
						<th scope="col"><?php echo esc_html__( 'Cliente', 'restaurant-reservations' ); ?></th>
						<th scope="col"><?php echo esc_html__( 'Comensales', 'restaurant-reservations' ); ?></th>
						<th scope="col"><?php echo esc_html__( 'Estado', 'restaurant-reservations' ); ?></th>
						<th scope="col"><?php echo esc_html__( 'Acciones', 'restaurant-reservations' ); ?></th>
					</tr></thead>
					<tbody>
					<?php foreach ( $today_reservations as $reservation ) :
						$status = sanitize_key( $reservation->post_status );
						$time = get_post_meta( $reservation->ID, '_rr_time', true );
						$guests = get_post_meta( $reservation->ID, '_rr_guests', true );
					?>
						<tr data-reservation-id="<?php echo esc_attr( $reservation->ID ); ?>">
							<td data-label="<?php echo esc_attr__( 'Hora', 'restaurant-reservations' ); ?>"><?php echo esc_html( $time ); ?></td>
							<td data-label="<?php echo esc_attr__( 'Cliente', 'restaurant-reservations' ); ?>"><?php echo esc_html( $reservation->post_title ); ?></td>
							<td data-label="<?php echo esc_attr__( 'Comensales', 'restaurant-reservations' ); ?>"><?php echo esc_html( $guests ); ?></td>
							<td data-label="<?php echo esc_attr__( 'Estado', 'restaurant-reservations' ); ?>"><span class="rr-status rr-status--<?php echo esc_attr( $status ); ?>"><?php echo esc_html( $status_labels[ $status ] ?? ucfirst( $status ) ); ?></span></td>
							<td class="rr-actions" data-label="<?php echo esc_attr__( 'Acciones', 'restaurant-reservations' ); ?>">
								<?php if ( in_array( $status, array( 'pending', 'confirmed' ), true ) ) : ?>
									<button type="button" class="rr-btn rr-btn--complete" data-id="<?php echo esc_attr( $reservation->ID ); ?>" data-status="completed"><?php echo esc_html__( 'Completar', 'restaurant-reservations' ); ?></button>
								<?php endif; ?>
								<button type="button" class="rr-btn rr-btn--cancel" data-id="<?php echo esc_attr( $reservation->ID ); ?>" data-status="cancelled"><?php echo esc_html__( 'Cancelar', 'restaurant-reservations' ); ?></button>
							</td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		<?php endif; ?>
	</section>

	<section class="rr-staff-calendar" aria-labelledby="rr-calendar-title">
		<h2 id="rr-calendar-title"><?php echo esc_html__( 'Calendario de reservas', 'restaurant-reservations' ); ?></h2>
		<nav class="rr-calendar-nav" aria-label="<?php echo esc_attr__( 'Navegación del calendario', 'restaurant-reservations' ); ?>">
			<button type="button" class="rr-calendar-prev" data-year="<?php echo esc_attr( wp_date( 'Y', $prev_month ) ); ?>" data-month="<?php echo esc_attr( wp_date( 'n', $prev_month ) ); ?>" aria-label="<?php echo esc_attr__( 'Mes anterior', 'restaurant-reservations' ); ?>">&lsaquo; <span><?php echo esc_html__( 'Anterior', 'restaurant-reservations' ); ?></span></button>
			<h3 class="rr-calendar-month" aria-live="polite"><?php echo esc_html( $month_label ); ?></h3>
			<button type="button" class="rr-calendar-next" data-year="<?php echo esc_attr( wp_date( 'Y', $next_month ) ); ?>" data-month="<?php echo esc_attr( wp_date( 'n', $next_month ) ); ?>" aria-label="<?php echo esc_attr__( 'Mes siguiente', 'restaurant-reservations' ); ?>"><span><?php echo esc_html__( 'Siguiente', 'restaurant-reservations' ); ?></span> &rsaquo;</button>
		</nav>
		<div class="rr-calendar-grid" data-year="<?php echo esc_attr( $calendar_year ); ?>" data-month="<?php echo esc_attr( $calendar_month ); ?>">
			<?php foreach ( array( 'Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb' ) as $weekday ) : ?>
				<div class="rr-calendar-weekday"><?php echo esc_html( $weekday ); ?></div>
			<?php endforeach; ?>
			<?php for ( $blank = 0; $blank < $calendar_first_dow; $blank++ ) : ?><span class="rr-calendar-blank" aria-hidden="true"></span><?php endfor; ?>
			<?php for ( $day = 1; $day <= $days_in_month; $day++ ) :
				$date = sprintf( '%04d-%02d-%02d', $calendar_year, $calendar_month, $day );
				$count = isset( $calendar_counts[ $date ] ) ? (int) $calendar_counts[ $date ] : 0;
			?>
				<a href="#rr-day-detail" class="rr-calendar-day<?php echo $date === $today_date ? ' is-today' : ''; ?>" data-date="<?php echo esc_attr( $date ); ?>" aria-label="<?php echo esc_attr( sprintf( _n( '%1$s, %2$d reserva', '%1$s, %2$d reservas', $count, 'restaurant-reservations' ), wp_date( 'j F Y', strtotime( $date ) ), $count ) ); ?>">
					<span class="rr-calendar-number"><?php echo esc_html( $day ); ?></span>
					<?php if ( $count ) : ?><span class="rr-calendar-count"><?php echo esc_html( $count ); ?></span><?php endif; ?>
				</a>
			<?php endfor; ?>
		</div>
	</section>

	<section id="rr-day-detail" class="rr-staff-day-detail" hidden aria-live="polite">
		<div class="rr-day-detail-header">
			<h2 class="rr-day-detail-title"></h2>
			<button type="button" class="rr-day-detail-close" aria-label="<?php echo esc_attr__( 'Cerrar detalle', 'restaurant-reservations' ); ?>">&times;</button>
		</div>
		<div class="rr-day-detail-content"></div>
	</section>
	<div class="rr-staff-flash" role="status" aria-live="polite" hidden></div>
</main>
<?php get_footer(); ?>
