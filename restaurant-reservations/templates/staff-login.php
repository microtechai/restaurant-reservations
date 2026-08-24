<?php if ( ! defined( "ABSPATH" ) ) { exit; } ?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
		<title><?php echo esc_html__( 'Acceso personal — Libro de Reservas', 'restaurant-reservations' ); ?></title>
	<link rel="preconnect" href="https://fonts.googleapis.com">
	<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
	<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&amp;family=Noto+Serif:wght@600;700&amp;display=swap" rel="stylesheet">
	<?php wp_head(); ?>
</head>
<body class="rr-login-page">
	<main class="rr-login-main">
		<section class="rr-login-card" aria-labelledby="rr-login-title">
						<div class="rr-login-brand" aria-hidden="true">Libro de Reservas</div>
			<h1 id="rr-login-title"><?php echo esc_html__( 'Acceso del personal', 'restaurant-reservations' ); ?></h1>
			<?php
			wp_login_form(
				array(
					'redirect' => site_url( '/mesas/' ),
					'remember' => true,
				)
			);
			?>
			<p class="rr-login-footer"><?php echo esc_html__( 'Acceso para personal del restaurante', 'restaurant-reservations' ); ?></p>
		</section>
	</main>
	<?php wp_footer(); ?>
</body>
</html>
