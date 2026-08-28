<?php 
/**
 * Blog PJAX Template
 *
 * This template is used to render portfolio content
 * specifically for PJAX requests.
 *
 * @package    PJAX
 * @since      9.5.5
 * @version    1.0.0
 */
    global $et_loop;
    $content_layout = etheme_get_option('blog_layout', 'default');
    $navigation_type = etheme_get_option( 'blog_navigation_type', 'pagination' );
    $full_width = false;
    $class = ' hfeed et_blog-ajax';
    $banner_pos = etheme_get_option( 'blog_page_banner_pos', 1 );

    if ( in_array($content_layout, array('grid', 'grid2'))  ) {
        $full_width = etheme_get_option('blog_full_width', 0);
        $content_layout = str_replace('grid2', 'grid-2', $content_layout);
        $class .= ' row';
        if ( etheme_get_option( 'blog_masonry', 1 ) ) {
            wp_enqueue_script( 'et_isotope');
            $class .= ' blog-masonry';
            $class .= ' et-isotope';
            $et_loop['isotope'] = true;
        }
    }

?>

<div class="content <?php echo esc_attr( get_query_var('et_content-class', 'col-md-9') ); ?>">
    <?php 
    if( $banner_pos == 1 ) {
        if ( is_category() && $cat_desc = category_description() ) : ?>
            <div class="blog-category-description"><?php echo do_shortcode( $cat_desc ); ?></div>
        <?php else:
            etheme_blog_header();
        endif;
    } ?>
    <div class="<?php echo esc_attr($class); ?>">
        <?php if(have_posts()):
            while(have_posts()) : the_post(); ?>

                <?php get_template_part('content', $content_layout); ?>

            <?php endwhile; ?>
        <?php else: ?>

            <div class="col-md-12">

                <h2><?php esc_html_e('No posts were found!', 'xstore') ?></h2>

                <p><?php esc_html_e('Sorry, but nothing matched your search terms. Please try again with some different keywords', 'xstore') ?></p>

                <?php get_search_form(); ?>

            </div>

        <?php endif; ?>
    </div>

    <?php
        global $wp_query;
        $cat = $wp_query->get_queried_object();

        if ( ! is_null($cat) && property_exists( $cat, 'term_id' ) && ! is_search() ) {
            $desc = get_term_meta( $cat->term_id, '_et_second_description', true );

            if ( ! empty( $desc ) ) {
                echo '<div class="term-description et_second-description">' . do_shortcode( $desc ) . '</div>';
            }
        }
    ?>

    <?php
        global $wp_query;
        $pag_align = etheme_get_option( 'blog_pagination_align', 'right' );

        $paginate_args = array(
            'pages'  => $wp_query->max_num_pages,
            'paged'  => ( get_query_var( 'paged' ) ) ? get_query_var( 'paged' ) : 1,
            'class'  => 'articles-pagination align-' . esc_attr( $pag_align ),
            'before' => etheme_count_posts( array( 'echo' => false ) ),
            'prev_text' => esc_html__( 'Prev page', 'xstore' ),
            'next_text' => esc_html__( 'Next page', 'xstore' ),
            'prev_next' => true
        );
        etheme_pagination( $paginate_args );

    ?>
</div>

<?php 
