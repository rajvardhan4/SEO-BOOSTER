<?php
/**
 * Template for Areas We Serve pages. Uses the theme's header and footer;
 * the body is drawn from the layout set in SEO Boster > Area Pages > Layout.
 *
 * @package SEO_Boster
 */

defined( 'ABSPATH' ) || exit;

$seob_block_theme = function_exists( 'wp_is_block_theme' ) && wp_is_block_theme();

if ( $seob_block_theme ) {
	?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
	<?php
	wp_body_open();
	echo '<div class="wp-site-blocks">';
	block_template_part( 'header' );
} else {
	get_header();
}

while ( have_posts() ) :
	the_post();
	SEOB_AP_Layout::render( get_the_ID(), SEOB_AP_Design::preview_layout() );
endwhile;

if ( $seob_block_theme ) {
	block_template_part( 'footer' );
	echo '</div>';
	wp_footer();
	echo '</body></html>';
} else {
	get_footer();
}
