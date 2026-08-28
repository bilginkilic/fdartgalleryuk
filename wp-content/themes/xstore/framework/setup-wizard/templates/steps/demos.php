<?php if ( ! defined('ETHEME_FW')) exit('No direct script access allowed');
/**
 * Template "Demos" - step for ET_Setup_Wizard.
 * @package ET_Setup_Wizard
 * @since 9.5.0
 * @version 1.0.0
 */


?>
<div class="wizard-step wizard-demos">
	<div class="wizard-step-content">
        <?php
            $class = '';

            $versions = etheme_get_demo_versions();

            if ( ! $versions ){
            echo '<p class="et-message et-error" style="width: 100%;">' .
                esc_html__('We are unable to connect to the 8Theme API to retrieve the list of versions.', 'xstore') .
                '</p>';
            return;
            }

            $pages = array_filter($versions, function( $el ) {
            if (isset($el['type'])){
            return $el['type'] == 'page';
            }
            });

            $demos = array_filter($versions, function( $el ) {
            if (isset($el['type'])){
            return $el['type'] == 'demo';
            }
            });

            $installed_versions = array();
            $installed_version = get_option('etheme_current_version');

            $core_active = class_exists('ETC\App\Controllers\Admin\Import');

            if ( $installed_version ) {
            $installed_versions[] = json_decode($installed_version)->name;
            }
            $is_remove = false;
            $et_imported_data = get_option('et_imported_data', array());

            if (count($et_imported_data)){
            foreach ($et_imported_data as $type){
            if (count($type)){
            $is_remove = true;
            break; // limit to stop if fount
            }
            }
            if (!$is_remove){
            delete_option('etheme_current_version');
            $installed_versions = array();
            $installed_version = false;
            }
            }

            $global_admin_class = EthemeAdmin::get_instance();
        ?>
        <div class="etheme-import-section <?php echo esc_attr( $class ); ?>">
            <div class="wizard-step-heading text-center container-mini">
                <h2><?php echo sprintf(esc_html__('Choose from %s prebuilt websites', 'xstore'), '<span class="et-counter" data-postfix="+">140+</span>'); ?></h2>
                <p>
                    <?php esc_html_e('Whether you\'re launching an online store, corporate site, blog, portfolio, or any other type of website, our prebuilt websites provide a solid foundation.', 'xstore'); ?>
                </p>
                <div class="xstore-panel-grid-header">
                    <?php
                    $global_admin_class->get_search_form('versions', esc_html__( 'Search for versions', 'xstore' ));
                    ?>
                </div>
            </div>

            <div class="xstore-panel-grid-wrapper import-demos">
				<?php
				foreach ( $demos as $key => $version ) : ?>
					<?php
					if ( ! isset( $version['filter'] ) ) {
						$version['filter'] = 'all';
					}

					if ( isset( $version['engine'] ) ) {
						$version['filter'] = $version['filter'] . ' ' . implode( " ", $version['engine'] );
					} else {
						$version['filter'] = $version['filter'] . ' wpb';
					}
					$engine = (isset( $version['engine'] )) ? $version['engine'] : array();

					$required = false;

					if ( isset($version['required']) ){
						if ( isset($version['required']['theme']) && version_compare( ETHEME_THEME_VERSION, $version['required']['theme'], '<' )){
							$required['theme'] = $version['required']['theme'];
						}

						if (isset($version['required']['plugin']) && defined('ET_CORE_VERSION') && version_compare( ET_CORE_VERSION, $version['required']['plugin'], '<' )){
							$required['plugin'] = $version['required']['plugin'];
						}

						if ($required){
							$required = json_encode($required);
						}
					}

					if (count( $engine ) > 1){
						$engine = count( $engine );
					}elseif (count( $engine ) == 1 && isset($engine[0])){
						$engine = $engine[0];
					} else {
						$engine = 0;
					}


					$item_classes = array(
						'xstore-panel-grid-item',
						'version-preview',
						'version-preview-'.$key,
					);


                    $item_classes[] = 'not-imported';


					if ( strpos($version['filter'], 'elementor' ) > 0 ) {

					}
					else {
						$item_classes[] = 'et-hide';
					}
					?>
                    <div
                            class="<?php echo implode(' ', $item_classes); ?>"
                            data-filter="<?php echo esc_js($version['filter']); ?>"
                            data-active-filter="all"
                    >
                        <div class="xstore-panel-grid-item-content">
                            <a href="<?php echo esc_url( $version['preview_url'] ); ?>" target="_blank" class="xstore-panel-grid-item-name version-title" style="text-decoration: none">
                                 <?php echo esc_html( $version['title'] ); ?>
                            </a>
                            <div class="xstore-panel-grid-item-image version-screenshot">
                                <a href="<?php echo esc_url( $version['preview_url'] ); ?>" <?php echo (isset($version['preview_elementor_url'])) ? 'data-href="'.esc_url( $version['preview_elementor_url'] ).'"' : ''; ?> target="_blank">
                                    <img
                                            class="lazyload lazyload-simple et-lazyload-fadeIn"
                                            src="<?php echo esc_html( ETHEME_BASE_URI . ETHEME_CODE ); ?>assets/images/placeholder-350x268.png"
                                            data-src="<?php echo apply_filters('etheme_protocol_url', ETHEME_BASE_URL . 'import/xstore-demos/' . esc_attr( $key ) . '/screenshot.jpg'); ?>"
                                            data-old-src="<?php echo esc_html( ETHEME_BASE_URI . ETHEME_CODE ); ?>assets/images/placeholder-350x268.png"
                                            alt="<?php echo esc_attr( $key ); ?>">
                                </a>
                                <div class="xstore-panel-grid-item-labels">
									<?php if ( isset($version['badge']) ) { ?>
                                        <span class="xstore-panel-grid-item-label active-label"><?php echo esc_html($version['badge']); ?></span>
									<?php } ?>
                                </div>
                            </div>
                            <div class="xstore-panel-grid-item-info">
                                <div class="xstore-panel-grid-item-control-wrapper">

                                    <a href="<?php echo esc_url( $version['preview_url'] ); ?>" <?php echo (isset($version['preview_elementor_url'])) ? 'data-href="'.esc_url( $version['preview_elementor_url'] ).'"' : ''; ?> target="_blank" class="xstore-panel-grid-item-control et-button button-preview setup-button setup-button-outline no-loader">
                                        <span class="dashicons dashicons-visibility"></span>
                                        <span><?php esc_html_e('View demo', 'xstore'); ?></span>
                                    </a>
                                        <?php if($engine !='wpb' && $engine !='elementor'):
                                            $import_url = ET_Setup_Wizard::get_controls_url('engine&version=' . esc_attr( $key ));
	                                    elseif($engine =='wpb' && $engine !='elementor'):
                                            $import_url = ET_Setup_Wizard::get_controls_url('plugins&engine=wpb&version=' . esc_attr( $key ));
	                                    else:
                                            $import_url = ET_Setup_Wizard::get_controls_url('plugins&engine=elementor&version=' . esc_attr( $key ));
	                                    endif; ?>
                                        <a class="xstore-panel-grid-item-control button-import-version setup-button et-button import-demo-btn" href="<?php echo esc_url($import_url); ?>"
                                           data-version="<?php echo esc_attr( $key ); ?>"
                                           data-engine="<?php echo esc_attr($engine); ?>"
                                           data-required="<?php echo esc_attr($required); ?>">
                                            <?php $global_admin_class->get_loader(); ?>
                                            <span class="dashicons dashicons-upload"></span>
                                            <span><?php esc_html_e('Import', 'xstore'); ?></span>
                                        </a>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
				<?php endforeach; ?>
				<?php
				$global_admin_class->get_search_no_found();
				?>
            </div>
        </div>
	</div>
	<div class="wizard-step-controllers">
<!--		<a href="--><?php //echo ET_Setup_Wizard::get_controls_url('register'); ?><!--" class="wizard-controllers-button">next</a>-->
	</div>
</div>