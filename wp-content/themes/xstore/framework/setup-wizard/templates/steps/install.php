<?php if ( ! defined('ETHEME_FW')) exit('No direct script access allowed');
/**
 * Template "install" - step for ET_Setup_Wizard.
 * @package ET_Setup_Wizard
 * @since 9.5.0
 * @version 1.0.0
 */
if(isset($_GET['version'])){
	$versions  = etheme_get_demo_versions();
	$version   = $versions[ $_GET['version'] ];
	$to_import = $version['to_import'];
} else {
	$version = array('title' => 'Can not get version');
	$to_import = array();
}

$is_elementor_container = ET_Setup_Wizard::elementor_experiments_container();

?>

<div class="wizard-step wizard-install">
    <script type="text/javascript">
        var XStorePanelPatcherConfig = {
            ajaxurl: '<?php echo esc_js( admin_url( 'admin-ajax.php' ) ); ?>',
            test_mode: <?php echo isset($_GET['xstore-patches-test-mode']) ? 'true' : 'false'; ?>,
            nonce: '<?php echo esc_js( wp_create_nonce( 'xstore_patches_apply_nonce' ) ); ?>',
            theme_version: '<?php echo esc_js( ETHEME_THEME_VERSION ); ?>',
            refresh_patches_nonce: '<?php echo esc_js( wp_create_nonce('refresh-patches') ); ?>'
        };
    </script>
    <div class="wizard-step-content">
        <div class="et_install-demo-form-wrapper">
            <div class="wizard-step-heading text-center">
                <h2><?php echo esc_html__('Content configuration', 'xstore'); ?></h2>
                <?php if(!$is_elementor_container && $is_elementor_container !='no_elenemtor'): ?>
                    <div class="et-message et-warning" style="max-width: 600px; margin: 0 auto;">
                        <p><?php echo esc_html__('Your Elementor settings currently have Flexbox Container disabled, which may cause the demo to import incorrectly (header/footer might not appear).', 'xstore'); ?></p>
                        <p><?php echo esc_html__('To fix it, please enable it:', 'xstore'); ?></p>
                        <p><?php echo esc_html__('Elementor → Settings → Features → Flexbox Container → Active → Save', 'xstore'); ?></p>
                        <p>
                            <?php echo esc_html__('Then return and import the demo again', 'xstore'); ?> <a target="_blank" href="https://www.8theme.com/documentation/xstore/troubleshooting/what-to-do-if-the-header-content-is-not-visible-after-import/">[<?php echo esc_html__('More details', 'xstore'); ?>]</a>.
                        </p>
                    </div>
                    <br>
                    <br>
                <?php endif;?>
                <p><?php echo esc_html__('Pick the content elements you want: full demo or specific sections', 'xstore'); ?></p>
            </div>
            <div class="container-mini">
                <form class="et_install-demo-form et_setup-wizard-form with-scroll" action="">
                <div class="et_recomended-setup">
                    <input type="checkbox" id="et_all" name="et_all" value="et_all" checked>
                    <label for="et_all"><?php echo esc_html__('Full demo-site content', 'xstore'); ?></label>
                </div>
                <?php if (etheme_is_activated()) : ?>
                    <div class="et_hidden-setup hidden">
                        <input type="checkbox" id="patches" name="patches" value="patches" checked>
                        <label for="patches"><?php echo esc_html__('Base data', 'xstore'); ?></label>
                    </div>
                <?php endif; ?>
                <div class="et_manual-setup">
                    <?php if ( isset( $to_import['pages'] ) && ! empty( $to_import['pages'] ) ): ?>
                    <div>
                        <input type="checkbox" id="pages" name="pages" value="pages" checked>
                        <label for="pages"><?php echo esc_html__('Pages', 'xstore'); ?></label>
                        <div class="et_manual-setup-page">
                            <?php if ( isset( $to_import['widgets'] ) && ! empty( $to_import['widgets'] ) ): ?>
                                <input type="checkbox" id="widgets" name="widgets" value="widgets" checked>
                                <label for="widgets"><?php echo esc_html__('Widgets', 'xstore'); ?></label>
                                <br/>
                            <?php endif; ?>
                            <?php if ( isset( $to_import['home_page'] ) && ! empty( $to_import['home_page'] ) ): ?>
                                <input type="checkbox" id="home_page" name="home_page" value="home_page" checked>
                                <label for="home_page"><?php echo esc_html__('Home Page', 'xstore'); ?></label>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    <?php if ( isset( $to_import['posts'] ) && ! empty( $to_import['posts'] ) ): ?>
                    <div>
                        <input type="checkbox" id="posts" name="posts" value="posts" checked>
                        <label for="posts"><?php echo esc_html__('Posts', 'xstore'); ?></label>
                    </div>
                    <?php endif; ?>
                    <?php if ( isset( $to_import['products'] ) && ! empty( $to_import['products'] ) ): ?>
                    <div>
                        <input type="checkbox" id="products" name="products" value="products" checked>
                        <label for="products"><?php echo esc_html__('Products', 'xstore'); ?></label>
                    </div>
                    <?php endif; ?>

                    <?php if ( isset( $to_import['static-blocks'] ) && ! empty( $to_import['static-blocks'] ) ): ?>
                    <div>
                        <input type="checkbox" id="static-blocks" name="static-blocks" value="static-blocks" checked>
                        <label for="static-blocks"><?php echo esc_html__('Static Blocks', 'xstore'); ?></label>
                    </div>
                    <?php endif; ?>

                    <?php if ( isset( $to_import['projects'] ) && ! empty( $to_import['projects'] ) ): ?>
                    <div>
                        <input type="checkbox" id="projects" name="projects" value="projects" checked>
                        <label for="projects"><?php echo esc_html__('Projects', 'xstore'); ?></label>
                    </div>
                    <?php endif; ?>
                    <?php if ( isset( $to_import['testimonials'] ) && ! empty( $to_import['testimonials'] ) ): ?>
                    <div>
                        <input type="checkbox" id="testimonials" name="testimonials" value="testimonials" checked>
                        <label for="testimonials"><?php echo esc_html__('Testimonials', 'xstore'); ?></label>
                    </div>
                    <?php endif; ?>
                    <?php if ( isset( $to_import['contact-forms'] ) && ! empty( $to_import['contact-forms'] ) ): ?>
                    <div>
                        <input type="checkbox" id="contact-forms" name="contact-forms" value="contact-forms" checked>
                        <label for="contact-forms"><?php echo esc_html__('Contact Forms', 'xstore'); ?></label>
                    </div>
                    <?php endif; ?>
                    <?php if ( isset( $to_import['mailchimp'] ) && ! empty( $to_import['mailchimp'] ) ): ?>
                    <div>
                        <input type="checkbox" id="mailchimp" name="mailchimp" value="mailchimp" checked>
                        <label for="mailchimp"><?php echo esc_html__('Mailchimp Sign-up Forms', 'xstore'); ?></label>
                    </div>
                    <?php endif; ?>

                    <?php if ( isset( $to_import['mega-menus'] ) && ! empty( $to_import['mega-menus'] ) ): ?>
                    <div>
                        <input type="checkbox" id="mega-menus" name="elementor_headers" value="mega-menus" checked>
                        <label for="mega-menus"><?php echo esc_html__('Elementor Mega Menus', 'xstore'); ?></label>
                    </div>
                    <?php endif; ?>

                    <?php if ( isset( $to_import['media'] ) && ! empty( $to_import['media'] ) ): ?>
                    <div>
                        <input type="checkbox" id="media" name="media" value="media" checked>
                        <label for="media"><?php echo esc_html__('Media', 'xstore'); ?></label>
                    </div>
                    <?php endif; ?>
                    <?php if ( isset( $to_import['grid-builder'] ) && ! empty( $to_import['grid-builder'] ) ): ?>
                    <div>
                        <input type="checkbox" id="grid-builder" name="grid-builder" value="grid-builder" checked>
                        <label for="grid-builder"><?php echo esc_html__('Grid builder', 'xstore'); ?></label>
    <!--                    <br class="grid-builder-br">-->
                    </div>
                    <?php endif; ?>
                    <?php if ( isset( $to_import['fonts'] ) && ! empty( $to_import['fonts'] ) ): ?>
                    <div>
                        <input type="checkbox" id="fonts" name="fonts" value="fonts" checked>
                        <label for="fonts"><?php echo esc_html__('Custom fonts', 'xstore'); ?></label>
                    </div>
                    <?php endif; ?>

                    <?php if ( isset( $to_import['options'] ) && ! empty( $to_import['options'] ) ): ?>
                    <div>
                        <input type="checkbox" id="options" name="options" value="options" checked>
                        <label for="options"><?php echo esc_html__('Theme Options', 'xstore'); ?></label>
                    </div>
                    <?php endif; ?>
                    <?php if ( isset( $to_import['products'] ) && ! empty( $to_import['products'] ) && isset( $to_import['variations'] ) && $to_import['variations']  ): ?>
                        <input style="display: none;" type="checkbox" id="variation_taxonomy" name="variation_taxonomy" value="variation_taxonomy" checked>
                        <label style="display: none;" for="variation_taxonomy"><?php echo esc_html__('Variations taxonomy', 'xstore'); ?></label>
                        <input style="display: none;" type="checkbox" id="variations_trems" name="variations_trems" value="variations_trems" checked>
                        <label style="display: none;" for="variations_trems"><?php echo esc_html__('Variations terms', 'xstore'); ?></label>
                        <input style="display: none;" type="checkbox" id="variation_products" name="variation_products" value="variation_products" checked>
                        <label style="display: none;" for="variation_products"><?php echo esc_html__('Products variations', 'xstore'); ?></label>
                    <?php endif; ?>
                    <?php if ( isset( $to_import['menu'] ) && ! empty( $to_import['menu'] ) ): ?>
                    <div>
                        <input type="checkbox" id="menu" name="menu" value="menu" checked>
                        <label for="menu"><?php echo esc_html__('Menu', 'xstore'); ?></label>
                    </div>
                    <?php endif; ?>

                    <?php if ( isset( $to_import['etheme_slides'] ) && ! empty( $to_import['etheme_slides'] ) ): ?>
                    <div>
                        <input type="checkbox" id="etheme_slides" name="etheme_slides" value="etheme_slides" checked>
                        <label for="etheme_slides"><?php echo esc_html__('Etheme Slides', 'xstore'); ?></label>
                    </div>
                    <?php endif; ?>

                    <?php if ( isset( $to_import['elementor_sections'] ) && ! empty( $to_import['elementor_sections'] ) ): ?>
                    <div>
                        <input type="checkbox" id="elementor_sections" name="elementor_sections" value="elementor_sections" checked>
                        <label for="elementor_sections"><?php echo esc_html__('Elementor sections', 'xstore'); ?></label>
                    </div>
                    <?php endif; ?>
                    <?php if ( isset( $to_import['elementor_footers'] ) && ! empty( $to_import['elementor_footers'] ) ): ?>
                    <div>
                        <input type="checkbox" id="elementor_footers" name="elementor_footers" value="elementor_footers" checked>
                        <label for="elementor_footers"><?php echo esc_html__('Elementor footers', 'xstore'); ?></label>
                    </div>
                    <?php endif; ?>
                    <?php if ( isset( $to_import['elementor_headers'] ) && ! empty( $to_import['elementor_headers'] ) ): ?>
                    <div>
                        <input type="checkbox" id="elementor_headers" name="elementor_headers" value="elementor_headers" checked>
                        <label for="elementor_headers"><?php echo esc_html__('Elementor headers', 'xstore'); ?></label>
                    </div>
                    <?php endif; ?>
                    <?php if ( isset( $to_import['elementor_archives'] ) && ! empty( $to_import['elementor_archives'] ) ): ?>
                    <div>
                        <input type="checkbox" id="elementor_archives" name="elementor_archives" value="elementor_archives" checked>
                        <label for="elementor_archives"><?php echo esc_html__('Elementor product archives', 'xstore'); ?></label>
                    </div>
                    <?php endif; ?>
                    <?php if ( isset( $to_import['elementor_single_products'] ) && ! empty( $to_import['elementor_single_products'] ) ): ?>
                    <div>
                        <input type="checkbox" id="elementor_single_products" name="elementor_single_products" value="elementor_single_products" checked>
                        <label for="elementor_single_products"><?php echo esc_html__('Elementor single products', 'xstore'); ?></label>
                    </div>
                    <?php endif; ?>
                    <?php if ( isset( $to_import['elementor_post'] ) && ! empty( $to_import['elementor_post'] ) ): ?>
                    <div>
                        <input type="checkbox" id="elementor_post" name="elementor_post" value="elementor_post" checked>
                        <label for="elementor_post"><?php echo esc_html__('Elementor single post', 'xstore'); ?></label>
                    </div>
                    <?php endif; ?>
                    <?php if ( isset( $to_import['elementor_post_archive'] ) && ! empty( $to_import['elementor_post_archive'] ) ): ?>
                    <div>
                        <input type="checkbox" id="elementor_post_archive" name="elementor_post_archive" value="elementor_post_archive" checked>
                        <label for="elementor_post_archive"><?php echo esc_html__('Elementor post archive', 'xstore'); ?></label>
                    </div>
                    <?php endif; ?>

                    <?php if ( isset( $to_import['sales_boosters'] ) && ! empty( $to_import['sales_boosters'] ) ): ?>
                    <div>
                        <input type="checkbox" id="sales_boosters" name="sales_boosters" value="sales_boosters" checked>
                        <label for="sales_boosters"><?php echo esc_html__('Sales Boosters', 'xstore'); ?></label>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="et_hidden-setup hidden">
                    <?php if ( isset( $to_import['slider'] ) && ! empty( $to_import['slider'] ) ): ?>
                    <div>
                        <input type="checkbox" id="slider" name="slider" value="slider" checked>
                        <label for="slider"><?php echo esc_html__('Slider', 'xstore'); ?></label>
                    </div>
                    <?php endif; ?>
                    <?php if ( isset( $to_import['multiple_headers'] ) && ! empty( $to_import['multiple_headers'] ) ): ?>
                    <div>
                        <input type="checkbox" id="et_multiple_headers" name="et_multiple_headers" value="et_multiple_headers" checked>
                        <label for="et_multiple_headers"><?php echo esc_html__('Multiple headers', 'xstore'); ?></label>
                    </div>
                    <?php endif; ?>
                    <?php if ( isset( $to_import['multiple_conditions'] ) && ! empty( $to_import['multiple_conditions'] ) ): ?>
                    <div>
                        <input type="checkbox" id="et_multiple_conditions" name="et_multiple_conditions" value="et_multiple_conditions" checked>
                        <label for="et_multiple_conditions"><?php echo esc_html__('Headers conditions', 'xstore'); ?></label>
                    </div>
                    <?php endif; ?>
                    <?php if ( isset( $to_import['multiple_single_product'] ) && ! empty( $to_import['multiple_single_product'] ) ): ?>
                    <div>
                        <input type="checkbox" id="et_multiple_single_product" name="et_multiple_single_product" value="et_multiple_single_product" checked>
                        <label for="et_multiple_single_product"><?php echo esc_html__('Multiple single product', 'xstore'); ?></label>
                    </div>
                    <?php endif; ?>
                    <?php if ( isset( $to_import['multiple_single_product_conditions'] ) && ! empty( $to_import['multiple_single_product_conditions'] ) ): ?>
                    <div>
                        <input type="checkbox" id="et_multiple_single_product_conditions" name="et_multiple_single_product_conditions" value="et_multiple_single_product_conditions" checked>
                        <label for="et_multiple_single_product_conditions"><?php echo esc_html__('Single product conditions', 'xstore'); ?></label>
                    </div>
                    <?php endif; ?>
                    <div>
                        <input type="checkbox" id="default_woocommerce_pages" name="default_woocommerce_pages" value="default_woocommerce_pages" checked>
                        <label for="default_woocommerce_pages"><?php echo esc_html__('Default WooCommerce pages', 'xstore'); ?></label>
                    </div>
                    <div>
                        <input type="checkbox" id="version_info" name="version_info" value="version_info" checked>
                        <label for="version_info"><?php echo esc_html__('Version data', 'xstore'); ?></label>
                    </div>
                    <div>
                        <input type="checkbox" id="init_builders" name="init_builders" value="init_builders" checked>
                        <label for="init_builders"><?php echo esc_html__('Init builders', 'xstore'); ?></label>
                    </div>

                </div>
            </form>
                <?php // out of the form as it is not as separated step ?>
                <input type="hidden" name="nonce_etheme_import-demo" value="<?php echo wp_create_nonce( 'etheme_import-demo' ); ?>">
            </div>
        </div>
        <?php $current_version = $version ?? false; ?>
        <div class="et_step-processing hidden container-mini">
            <div class="text-center">
                <div class="wizard-step-heading">
                    <h2><?php
                    if ( $current_version )
                        echo sprintf(esc_html__('Importing %s', 'xstore'), $current_version['title']);
                    else
                        echo esc_html__('Importing: Please Wait', 'xstore');
                    ?></h2>
                    <p><?php esc_html_e('Please wait fot the whole demo data import before doing anything. It may take a while...', 'xstore');?></p>
                </div>
                <?php if ( $current_version ) : ?>
                    <div class="xstore-panel-grid-item version-preview" style="width: auto;max-width: 320px;margin: 0 auto 15px;">
                        <div class="xstore-panel-grid-item-content">
                            <div class="xstore-panel-grid-item-image version-screenshot">
                                <a href="<?php echo esc_url( $current_version['preview_url'] ); ?>" <?php echo (isset($current_version['preview_elementor_url'])) ? 'data-href="'.esc_url( $version['preview_elementor_url'] ).'"' : ''; ?> target="_blank">
                                    <img
                                        class="lazyload lazyload-simple et-lazyload-fadeIn"
                                        src="<?php echo esc_html( ETHEME_BASE_URI . ETHEME_CODE ); ?>assets/images/placeholder-350x268.png"
                                        data-src="<?php echo apply_filters('etheme_protocol_url', ETHEME_BASE_URL . 'import/xstore-demos/' . esc_attr( $_GET['version'] ) . '/screenshot.jpg'); ?>"
                                        data-old-src="<?php echo esc_html( ETHEME_BASE_URI . ETHEME_CODE ); ?>assets/images/placeholder-350x268.png"
                                        alt="<?php echo esc_attr( $current_version['title'] ); ?>">
                                </a>
                            </div>

                        </div>
                    </div>
                <?php endif; ?>
            </div>
            <span class="et_progress-container">
                <progress class="et_progress" max="100" value="0"></progress>
                 <span class="progress-label"></span>
            </span>
            <div class="et_progress-notice text-center">
                <span class="et_progress-notice-text"></span>
                <span class="dot-loader">
                  <span></span>
                  <span></span>
                  <span></span>
                </span>
            </div>
        </div>
	</div>
	<div class="wizard-step-controllers">
		<a href="" class="setup-button setup-button-arrow wizard-controllers-button install-demo-data"><?php esc_attr_e('Install demo data', 'xstore') ?> <svg class="arrow-icon" xmlns="http://www.w3.org/2000/svg" width="1.3em" height="1.3em" viewBox="0 0 32 32">
                <g fill="none" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round" stroke-miterlimit="10">
                    <circle class="arrow-icon--circle" cx="16" cy="16" r="15.12"></circle>
                    <path class="arrow-icon--arrow" d="M16.14 9.93L22.21 16l-6.07 6.07M8.23 16h13.98"></path>
                </g>
            </svg></a>
	</div>
</div>