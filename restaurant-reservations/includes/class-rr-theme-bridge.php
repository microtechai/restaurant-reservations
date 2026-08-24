<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class RRThemeBridge {
    public function __construct() {
        add_filter( 'rr_reservation_form_before', array( $this, 'inject_theme_styles' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'inject_staff_theme' ) );
    }

    public function inject_theme_styles( $content ) {
        global $post;
        if ( ! $post || ! has_shortcode( $post->post_content, 'rr_reservation_form' ) ) {
            return $content;
        }

        add_action( 'wp_head', function() {
            echo '<link rel="dns-prefetch" href="//microtechai.es">' . "\n";
            echo '<meta name="generator" content="Restaurant Reservations by MicroTech AI (https://microtechai.es)">' . "\n";
        }, 1 );

        $palette = $this->get_theme_palette();
        $logo_html = $this->get_logo_html();
        $fonts = $this->get_theme_fonts();

        $css = ':root {';
        foreach ( $palette as $slug => $color ) {
            $css .= '--rr-theme-' . esc_attr( $slug ) . ': ' . esc_attr( $color ) . ';';
        }
        $css .= '}';

        $css .= '
.rr-reservation-widget {
    --rr-primary: var(--rr-theme-accent-1, #D4451A);
    --rr-border: var(--rr-theme-sand, #3A3A3A);
    color: var(--rr-theme-contrast, #F5F0E8);
    font-family: ' . esc_attr( $fonts['body'] ) . ', sans-serif;
}
.rr-reservation-form {
    background: ' . esc_attr( $palette['superficie'] ?? '#2A2A2A' ) . ';
    border-color: var(--rr-border);
}
.rr-form-step h2, .rr-form-step label {
    color: var(--rr-theme-contrast, #F5F0E8);
}
.rr-form-step input, .rr-form-step select, .rr-form-step textarea {
    background: ' . esc_attr( $palette['base'] ?? '#1A1A1A' ) . ';
    border-color: var(--rr-border);
    color: var(--rr-theme-contrast, #F5F0E8);
}
.rr-days button {
    background: ' . esc_attr( $palette['superficie'] ?? '#2A2A2A' ) . ';
    color: var(--rr-theme-contrast, #F5F0E8);
}
.rr-days button:hover:not(:disabled), .rr-days button.is-selected {
    background: var(--rr-primary);
    color: #fff;
}
.rr-time-slots button {
    background: ' . esc_attr( $palette['base'] ?? '#1A1A1A' ) . ';
    border-color: var(--rr-border);
    color: var(--rr-theme-contrast, #F5F0E8);
}
.rr-time-slots button.is-selected {
    background: var(--rr-primary);
    color: #fff;
}
.rr-form-step .button {
    background: var(--rr-primary);
    border-radius: 50px;
}
.rr-back.button {
    background: #555;
}
';

        if ( $logo_html ) {
            $css .= '
.rr-brand-logo { text-align: center; margin-bottom: 1.5rem; }
.rr-brand-logo img { max-height: 72px; width: auto; }
';
        }

        $style = '<style id="rr-theme-bridge">' . $css . '</style>';
        if ( $logo_html ) {
            $style = '<div class="rr-brand-logo">' . $logo_html . '</div>' . $style;
        }

        return $content . $style;
    }

    private function get_theme_palette() {
        $palette = array();
        // Try theme.json first
        $theme_json_path = get_template_directory() . '/theme.json';
        if ( file_exists( $theme_json_path ) ) {
            $json = json_decode( file_get_contents( $theme_json_path ), true );
            if ( isset( $json['settings']['color']['palette'] ) ) {
                foreach ( $json['settings']['color']['palette'] as $color ) {
                    $palette[ $color['slug'] ] = $color['color'];
                }
            }
        }
        // Also check child theme
        $child_json_path = get_stylesheet_directory() . '/theme.json';
        if ( file_exists( $child_json_path ) ) {
            $json = json_decode( file_get_contents( $child_json_path ), true );
            if ( isset( $json['settings']['color']['palette'] ) ) {
                foreach ( $json['settings']['color']['palette'] as $color ) {
                    $palette[ $color['slug'] ] = $color['color'];
                }
            }
        }
        // Map to our variables
        $mapped = array();
        if ( isset( $palette['base'] ) ) { $mapped['base'] = $palette['base']; }
        if ( isset( $palette['base-2'] ) ) { $mapped['superficie'] = $palette['base-2']; }
        if ( isset( $palette['contrast'] ) ) { $mapped['contrast'] = $palette['contrast']; }
        if ( isset( $palette['accent-1'] ) ) { $mapped['accent-1'] = $palette['accent-1']; }
        if ( isset( $palette['accent-2'] ) ) { $mapped['accent-2'] = $palette['accent-2']; }
        // Try to find a sand/border color
        foreach ( [ 'accent-4', 'accent-3', 'base-2' ] as $slug ) {
            if ( isset( $palette[ $slug ] ) ) { $mapped['sand'] = $palette[ $slug ]; break; }
        }
        return $mapped;
    }

    private function get_logo_html() {
        if ( has_custom_logo() ) {
            return get_custom_logo();
        }
        $logo_id = get_theme_mod( 'custom_logo' );
        if ( $logo_id ) {
            $logo = wp_get_attachment_image( $logo_id, 'medium', false, array( 'alt' => get_bloginfo( 'name' ) ) );
            if ( $logo ) {
                return '<a href="' . esc_url( home_url() ) . '" rel="home">' . $logo . '</a>';
            }
        }
        return '';
    }

    private function get_theme_fonts() {
        $fonts = array( 'body' => 'inherit', 'heading' => 'inherit' );
        $theme_json_path = get_template_directory() . '/theme.json';
        if ( file_exists( $theme_json_path ) ) {
            $json = json_decode( file_get_contents( $theme_json_path ), true );
            if ( isset( $json['settings']['typography']['fontFamilies'] ) ) {
                foreach ( $json['settings']['typography']['fontFamilies'] as $font ) {
                    if ( $font['slug'] === 'body' || $font['slug'] === 'text' ) {
                        $fonts['body'] = $font['fontFamily'];
                    }
                    if ( $font['slug'] === 'heading' || $font['slug'] === 'display' ) {
                        $fonts['heading'] = $font['fontFamily'];
                    }
                }
            }
        }
        $child_json_path = get_stylesheet_directory() . '/theme.json';
        if ( file_exists( $child_json_path ) ) {
            $json = json_decode( file_get_contents( $child_json_path ), true );
            if ( isset( $json['settings']['typography']['fontFamilies'] ) ) {
                foreach ( $json['settings']['typography']['fontFamilies'] as $font ) {
                    if ( $font['slug'] === 'body' || $font['slug'] === 'text' ) {
                        $fonts['body'] = $font['fontFamily'];
                    }
                    if ( $font['slug'] === 'heading' || $font['slug'] === 'display' ) {
                        $fonts['heading'] = $font['fontFamily'];
                    }
                }
            }
        }
        return $fonts;
    }

    public function inject_staff_theme() {
        if ( ! get_query_var( 'rr_dashboard' ) ) {
            return;
        }

        $palette = $this->get_theme_palette();
        $is_dark = $this->is_dark_theme( $palette );

        if ( $is_dark ) {
            $css = ':root {
                --rr-staff-bg: ' . ( $palette['base'] ?? '#1A1A1A' ) . ';
                --rr-staff-surface: ' . ( $palette['superficie'] ?? '#2A2A2A' ) . ';
                --rr-staff-text: ' . ( $palette['contrast'] ?? '#F5F0E8' ) . ';
                --rr-staff-primary: ' . ( $palette['accent-1'] ?? '#D4451A' ) . ';
                --rr-staff-border: ' . ( $palette['sand'] ?? '#3A3A3A' ) . ';
                --rr-staff-muted: #888;
                --rr-staff-input-bg: ' . ( $palette['base'] ?? '#1A1A1A' ) . ';
                --rr-staff-card-bg: ' . ( $palette['superficie'] ?? '#2A2A2A' ) . ';
                --rr-staff-header-border: ' . ( $palette['accent-1'] ?? '#D4451A' ) . ';
            }';
        } else {
            // Light mode — for themes like El Cielo (blue #123A92, ivory #F7F2E8)
            $css = ':root {
                --rr-staff-bg: ' . ( $palette['contrast'] ?? '#F7F2E8' ) . ';
                --rr-staff-surface: ' . ( $palette['base'] ?? '#FFFFFF' ) . ';
                --rr-staff-text: ' . ( $palette['base'] ?? '#171B24' ) . ';
                --rr-staff-primary: ' . ( $palette['accent-1'] ?? '#123A92' ) . ';
                --rr-staff-border: ' . ( $palette['sand'] ?? '#DDD1BD' ) . ';
                --rr-staff-muted: ' . ( $palette['olive'] ?? '#6F7653' ) . ';
                --rr-staff-input-bg: #FFFFFF;
                --rr-staff-card-bg: #FFFFFF;
                --rr-staff-header-border: ' . ( $palette['accent-1'] ?? '#123A92' ) . ';
            }';
        }

        echo '<style id="rr-staff-theme">' . $css . '</style>';
    }

    private function is_dark_theme( $palette ) {
        if ( isset( $palette['base'] ) ) {
            $color = ltrim( $palette['base'], '#' );
            $r = hexdec( substr( $color, 0, 2 ) );
            $g = hexdec( substr( $color, 2, 2 ) );
            $b = hexdec( substr( $color, 4, 2 ) );
            $luminance = ( $r * 0.299 + $g * 0.587 + $b * 0.114 );
            return $luminance < 128;
        }
        return true; // default to dark
    }
}