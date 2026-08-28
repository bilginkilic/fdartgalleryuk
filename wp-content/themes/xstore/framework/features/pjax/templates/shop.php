<?php
/**
 * Default shop PJAX Template
 *
 * This template is used to render default shop content
 * specifically for PJAX requests.
 *
 * @package    PJAX
 * @since      9.5.5
 * @version    1.0.0
 */

$product_bage_banner_pos = etheme_get_option( 'product_bage_banner_pos', 1 );
?>

<div class="content main-products-loop <?php echo esc_attr( get_query_var('et_content-class', 'col-md-9') ); ?>">
    
    <?php if ( woocommerce_product_loop() ) : ?>
        
        <?php wc_print_notices(); ?>
        
        <?php if ( $product_bage_banner_pos == 1 ) {
            do_action( 'woocommerce_shop_loop_header' );
        } ?>
        
        <?php if ( woocommerce_products_will_display() ): ?>
            <?php if ( etheme_get_option( 'top_toolbar', 1 ) ) {
                if ( ! wc_get_loop_prop( 'is_shortcode' ) ) {
                    if ( apply_filters('etheme_should_enqueue_style', true) )
                        etheme_enqueue_style('filter-area', true ); ?>
                    <div class="filter-wrap">
                    <div class="filter-content">
                <?php }
                /**
                 * woocommerce_before_shop_loop hook
                 *
                 * @hooked woocommerce_result_count - 20
                 * @hooked woocommerce_catalog_ordering - 30
                 * @hooked etheme_grid_list_switcher - 35
                 */
                do_action( 'woocommerce_before_shop_loop' );
                if ( ! wc_get_loop_prop( 'is_shortcode' ) ) { ?>
                    </div>
                    </div>
                <?php }
            }
                etheme_shop_filters_sidebar();
        endif;   ?>

        <?php do_action('etheme_before_product_loop_start', wc_get_loop_prop( 'total' )); ?>
        
        <?php 
            $search_content = etheme_get_option( 'search_results_content_et-desktop',
                array(
                    'products',
                    'posts',
                )
            ); 
        ?>
        
        <?php if ( is_array($search_content) && is_search() && ! in_array('products', $search_content ) ): ?>
        
        <?php else: ?>
            <?php woocommerce_product_loop_start(); ?>
            
            <?php if ( wc_get_loop_prop( 'total' ) ) { ?>
                
                <?php while ( have_posts() ) : the_post(); ?>
                    
                    <?php do_action( 'woocommerce_shop_loop' ); ?>
                    
                    <?php wc_get_template_part( 'content', 'product' ); ?>
                
                <?php endwhile; // end of the loop. ?>
            
            <?php } ?>
            
            <?php woocommerce_product_loop_end(); ?>
        <?php endif; ?>
        
        <?php if ( is_array($search_content) && is_search() && ! in_array('products', $search_content ) ): ?>
        
        <?php else: ?>
            <div class="after-shop-loop"><?php /*** woocommerce_after_shop_loop hook** @hooked woocommerce_pagination - 10*/ do_action( 'woocommerce_after_shop_loop' ); ?></div>
        <?php endif; ?>
        
        <?php do_action('etheme_after_product_loop_end'); ?>
        <?php etheme_second_cat_desc(); ?>
    
    <?php elseif ( ! woocommerce_product_subcategories( array( 'before' => woocommerce_product_loop_start( false ), 'after' => woocommerce_product_loop_end( false ) ) ) ) : ?>
        <?php do_action( 'etheme_before_product_loop_start' ); ?>
        <?php do_action( 'woocommerce_no_products_found' ); ?>
        <?php do_action( 'etheme_after_product_loop_start' ); ?>
    
    
    <?php endif; ?>
    
    <?php
    /**
     * woocommerce_after_main_content hook
     *
     * @hooked woocommerce_output_content_wrapper_end - 10 (outputs closing divs for the content)
     */
    etheme_after_products_widgets();
    if ( $product_bage_banner_pos == 2 ) {
        do_action( 'woocommerce_shop_loop_header' );
    }
    do_action( 'woocommerce_after_main_content' );
    ?>

</div>
