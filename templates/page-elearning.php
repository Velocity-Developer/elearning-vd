<?php

/**
 * Template Name: Elearning VD
 * Template Post Type: page
 */

defined('ABSPATH') || exit;

get_header();
?>

<?php
while (have_posts()) {
    the_post();
?>
    <div id="post-<?php the_ID(); ?>" <?php post_class('elvd-page-template'); ?>>

        <?php
        echo do_shortcode('[elvd_app]');
        ?>

    </div>
<?php
}
?>

<?php
get_footer();
