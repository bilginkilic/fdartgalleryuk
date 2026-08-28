<?php if ( ! defined('ETHEME_FW')) exit('No direct script access allowed');

class ETheme_Version_Check {

    private $current_version = '';
    private $new_version = '';
    private $theme_name = '';
    private $api_url = '';
    private $ignore_key = 'etheme_notice';
    public $information;
    public $api_key;
    public $url = '';
    public $notices;
    private $theme_id = 15780546;
    public $is_subscription = false;
    public $activated_data = array();
    public $is_license = true;
    public $is_new_deactivate = true;


    function __construct($update_transient = true) {
        $theme_data = wp_get_theme('xstore');
        $this->activated_data = $activated_data = get_option( 'etheme_activated_data' );
        $this->current_version = $theme_data->get('Version');
        $this->theme_name = strtolower($theme_data->get('Name'));
        $this->api_url = apply_filters('etheme_protocol_url', ETHEME_API);
        $this->url = apply_filters('etheme_protocol_url', ETHEME_BASE_URL . 'import/demo/xstore/change-log.php');
        $this->api_key = ( ! empty( $activated_data['api_key'] ) ) ? $activated_data['api_key'] : false;
        $this->is_subscription = ( isset($activated_data['item']) && isset($activated_data['item']['license']) && $activated_data['item']['license'] == '8theme-subscription' );

        $this->is_new_deactivate = false;

        add_action('admin_init', array($this, 'dismiss_notices'));
        add_action('admin_notices', array($this, 'show_notices'), 50 );

        add_action( 'wp_ajax_et_support_refresh', array($this, 'et_support_refresh') );
        add_action('wp_ajax_etheme_deactivate_theme', array($this, 'deactivate'));

        add_action('wp_ajax_etheme_activate_theme', array($this, 'ajax_etheme_activate_theme'));
        add_action('wp_ajax_etheme_check_activation_data', array($this, 'check_activation_data'));

        add_action('admin_init', function (){
            if (isset($_GET['disable_limit_notice'])){
                update_option('et_disable_licence_limit_notice', true);
            }

            if (get_option('et_documentation_beacon', false) !== 'off'){
                update_option( 'et_documentation_beacon', 'off', 'no');
            }
        });

//        if( ! etheme_is_activated() ) {
//            #$this->activation_notice();
//            return;
//        }

        if( $this->is_update_available() ) {
            if ( $this->major_update( 'both' ) ) add_action( 'admin_head', array( $this, 'major_update_holder' ) );
            //$this->update_notice();
        }

        add_action( 'switch_theme', array( $this, 'update_dismiss' ) );

        add_action( 'current_screen', array( $this, 'api_results_init' ) );

        if ($update_transient) {
            add_filter( 'site_transient_update_themes', array( $this, 'update_transient' ), 20, 2 );
            add_filter( 'pre_set_site_transient_update_themes', array( $this, 'set_update_transient' ) );
            //add_filter( 'themes_api', array(&$this, 'api_results'), 10, 3);
        }

    }

    public function api_results_init( $current_screen ) {
        if ( $current_screen->base !== 'woocommerce_page_wc-status' ) {
            add_filter( 'themes_api', array(&$this, 'api_results'), 10, 3);
        }

    }

    public function activation_page() {
        ?>

        <?php if ( etheme_is_activated() ): ?>
            <?php
            $activated_data = get_option( 'etheme_activated_data' );
            $purchase = ( isset( $activated_data['purchase'] ) && ! empty( $activated_data['purchase'] ) ) ? $activated_data['purchase'] : '';
            $supported_until = ( isset( $activated_data['item'] ) && isset( $activated_data['item']['supported_until'] ) && ! empty( $activated_data['item']['supported_until'] ) ) ? $activated_data['item']['supported_until'] : '';

            ?>

            <p>
                <?php
                echo sprintf(
                    esc_html__('Congratulations, and thank you for registering your website. You now have access to automatic updates via %s and exclusive features. To manage your licenses, simply visit %s.','xstore'),
                    '<a href="'.admin_url( 'update-core.php' ).'" target="_blank">'.esc_html__('Dashboard → Updates', 'xstore').'</a>',
                    '<a href="https://www.8theme.com/" target="_blank">'.esc_html__('8theme.com', 'xstore').'</a>'
                );
                ?>
            </p>
            <?php $this->process_form(); ?>
            <p class="etheme-purchase">
                <span class="etheme-purchase-inner">
                    <span><?php echo esc_html($this->maskHalfOfStringFromEnd($purchase)); ?></span>
                </span>
                <?php //if (!$this->is_subscription): ?>
                <?php $is_new_deactivate = ($this->is_new_deactivate) ? 'new-theme-deactivate': ''; ?>
                <span class="et-button et-button-active et_theme-deactivator <?php echo esc_attr($is_new_deactivate); ?>">
                            <?php esc_html_e( 'Deactivate theme', 'xstore' ); ?>
                            <span class="et-loader">
                                <svg class="loader-circular" viewBox="25 25 50 50">
                                    <circle class="loader-path" cx="50" cy="50" r="12" fill="none" stroke-width="2" stroke-miterlimit="10"></circle>
                                </svg>
                            </span>
                        </span>
                <?php //endif; ?>
            </p>

            <?php $this->support_status($supported_until); ?>
            <?php if (!$this->is_subscription): ?>
                <!--                <p class="et-message et-info">-->
                <!--					Due to the Envato's <a href="https://themeforest.net/licenses/terms/regular">license policy</a> one standard license is valid only for 1 project.-->
                <!--                    Running multiple projects on a single license is a copyright violation. If you want to view all your active domains, please visit our website and check the activation list in your <a href="https://www.8theme.com/account/">account</a>.-->
                <!--                </p>-->
            <?php endif; ?>
        <?php else: ?>

            <p class="">
                <?php //echo sprintf(esc_html__('Activate %s now to unlock auto updates, premium plugins, and world-class support!', 'xstore'), apply_filters('etheme_theme_label', 'XStore')); ?>
                <?php esc_html_e('Activate your license to unlock the full power of', 'xstore'); ?>
                <?php echo apply_filters('etheme_theme_label', 'XStore'); ?>
                <?php esc_html_e('and keep your store future-ready with premium features and automatic updates.', 'xstore'); ?>
            </p>

            <?php $this->process_form(); ?>

            <form class="xstore-form" method="post">
                <span class="etheme-purchase-inner">
                    <input type="text" name="purchase-code" placeholder="Example: f20b1cdd-ee2a-1c32-a146-66eafe" id="purchase-code" />
                </span>
                <span class="et-button et-button-green no-transform active activate-license-btn-holder popup-caller" onclick="">
                            <span class="et-loader">
                                <svg class="loader-circular" viewBox="25 25 50 50">
                                    <circle class="loader-path" cx="50" cy="50" r="12" fill="none" stroke-width="2" stroke-miterlimit="10"></circle>
                                </svg>
                            </span>
                            <?php esc_attr_e( 'Activate theme', 'xstore' ); ?>
                        </span>


                <!--                    <span class="et-button et-button-green no-transform active activate-license-btn-holder" onclick="jQuery('.xstore-form .activate-license-btn').trigger('click')">-->
                <!--                        <span class="et-loader">-->
                <!--                            <svg class="loader-circular" viewBox="25 25 50 50">-->
                <!--                                <circle class="loader-path" cx="50" cy="50" r="12" fill="none" stroke-width="2" stroke-miterlimit="10"></circle>-->
                <!--                            </svg>-->
                <!--                        </span>-->
                <!--                        --><?php //esc_attr_e( 'Register', 'xstore' ); ?>
                <!--                    </span>-->

                <input class="et-button et-button-green no-transform no-loader active hidden activate-license-btn" name="xstore-purchase-code" type="submit" value="<?php esc_attr_e( 'Activate theme', 'xstore' ); ?>" />

                <?php if(false) :?>
                    <p <?php if(!$this->is_license ) echo 'style="color: #ff0000;"'; ?> >
                        <input type="checkbox" id="form-license" name="form-license">
                        <label for="form-license">
                            <?php esc_attr_e( 'Please confirm that, according to Envato\'s license policy, a single standard license is only valid for one project. Using a single license for multiple projects is a violation of copyright. For more information, please refer to', 'xstore' ); ?>
                            

                        <a style="color: #2271b1;" href="https://themeforest.net/licenses/terms/regular" target="_blank"> <?php esc_attr_e( 'Envato\'s license policy.', 'xstore' ); ?>
                        </a>
                    </p>
                <?php endif; ?>
                <!--                    <p class="et-message et-info">-->
                <!--                        By clicking "Register," you agree to allow 8theme.com to store your purchase code and user data.-->
                <!--                    </p>-->

            </form>

        <?php endif; ?>
        <?php
    }

    public function show_notices() {
        global $current_user;
        $user_id = $current_user->ID;
        if( ! empty( $this->notices ) ) {
            foreach ($this->notices as $key => $notice) {
                if ( ! get_user_meta($user_id, $this->ignore_key . $key) ) {
                    echo '<div class="et-message et-info">' . $notice['message'] . '</div>';
                }
            }
        }
    }

    public function dismiss_notices() {
        global $current_user;
        $user_id = $current_user->ID;
        if ( isset( $_GET['et-hide-notice'] ) && isset( $_GET['_et_notice_nonce'] ) ) {
            if ( ! wp_verify_nonce( $_GET['_et_notice_nonce'], 'etheme_hide_notices_nonce' ) ) {
                return;
            }

            add_user_meta($user_id, $this->ignore_key . '_' . $_GET['et-hide-notice'], 'true', true);
        }
    }

    public function setup_notice() {
        $this->notices['_setup'] = array(
            'message' => '
                <p><strong>Welcome to XStore</strong> – You‘re almost ready to start selling :)</p>
                <p class="submit"><a href="' . admin_url( 'themes.php?page=xstore-setup' ) . '" class="button-primary">Run the Setup Wizard</a> <a class="button-secondary skip" href="' . esc_url( wp_nonce_url( add_query_arg( 'et-hide-notice', 'setup' ), 'etheme_hide_notices_nonce', '_et_notice_nonce' ) ). '">Skip Setup</a></p>
            '
        );
    }

    public function activation_notice() {
        $this->notices['_activation'] = array(
            'message' => '
                <p><strong>You need to activate XStore</strong></p>
                <p class="submit"><a href="' . admin_url( 'themes.php?page=xstore-setup' ) . '" class="button-primary">Activate theme</a></p>
            '
        );
    }

    public function update_notice() {
        if( isset( $_GET['_wpnonce'] )) return;

        $this->notices['_update'] = array(
            'message' => '
                    <p>There is a new version of ' . ETHEME_THEME_NAME . ' Theme available.</p>' . $this->major_update( 'msg-b' ) . '
                    <p class="submit"><a href="' . admin_url( 'update-core.php?force-check=1&theme_force_check=1' ) . '" class="button-primary">Update now</a> <a class="button-secondary skip" href="' . esc_url( wp_nonce_url( add_query_arg( 'et-hide-notice', 'update' ), 'etheme_hide_notices_nonce', '_et_notice_nonce' ) ). '">Dismiss</a></p>
                ',
        );
    }

    private function api_get_version() {

        $raw_response = wp_remote_get($this->api_url . '?theme=' . ETHEME_THEME_SLUG);
        if (!is_wp_error($raw_response) && ($raw_response['response']['code'] == 200)) {
            $response = json_decode($raw_response['body'], true);
            if(!empty($response['version'])) $this->new_version = $response['version'];
        }
    }

    public function update_dismiss() {
        global $current_user;
        #$user_id = $current_user->ID;
        #delete_user_meta($user_id, $this->ignore_key);
    }


    public function update_transient($value, $transient) {
        // if(isset($_GET['theme_force_check']) && $_GET['theme_force_check'] == '1') return false;
        if(isset($_GET['force-check']) && $_GET['force-check'] == '1') return false;
        return $value;
    }


    public function set_update_transient($transient) {
        $xstore_branding_settings = get_option( 'xstore_white_label_branding_settings', array() );

        if (
            count($xstore_branding_settings)
            && isset($xstore_branding_settings['control_panel'])
            && isset($xstore_branding_settings['control_panel']['hide_updates'])
            && $xstore_branding_settings['control_panel']['hide_updates'] == 'on'
        ){
            return $transient;
        }

        $this->check_for_update();

        if (is_null($transient)) {
            $transient = new stdClass();
        }

        if( isset( $transient ) && ! isset( $transient->response ) ) {
            $transient->response = array();
        }

        if( ! empty( $this->information ) && is_object( $this->information ) ) {
            if( $this->is_update_available() ) {
                $transient->response[ $this->theme_name ] = json_decode( json_encode( $this->information ), true );
            }
        }

        remove_filter( 'site_transient_update_themes', array( $this, 'update_transient' ), 20, 2 );

        return $transient;
    }


    public function api_results($result, $action, $args) {

        $this->check_for_update();

        if( isset( $args->slug ) && $args->slug == $this->theme_name && $action == 'theme_information') {
            if( is_object( $this->information ) && ! empty( $this->information ) ) {
                $result = $this->information;
            }
        }

        return $result;
    }


    protected function check_for_update() {

        $force = false;

        // if( isset( $_GET['theme_force_check'] ) && $_GET['theme_force_check'] == '1') $force = true;

        if( isset( $_GET['force-check'] ) && $_GET['force-check'] == '1') $force = true;

        // Get data
        if( empty( $this->information ) ) {
            $version_information = get_option( 'xstore-update-info', false );
            $version_information = $version_information ? $version_information : new stdClass;

            $this->information = is_object( $version_information ) ? $version_information : maybe_unserialize( $version_information );

        }

        $last_check = get_option( 'xstore-update-time' );
        if( $last_check == false ){
            update_option( 'xstore-update-time', time(), 'no' );
        }

        if( time() - $last_check > 172800 || $force || $last_check == false ){

            $version_information = $this->api_info();

            if( $version_information ) {
                update_option( 'xstore-update-time', time(), 'no' );

                $this->information          = $version_information;
                $this->information->checked = time();
                $this->information->url     = $this->url;
                $this->information->package = $this->download_url();

            }

        }

        // Save results
        update_option( 'xstore-update-info', $this->information );
    }

    public function api_info() {
        $version_information = new stdClass;

        $response = wp_remote_get( $this->api_url . 'info/' . $this->theme_name . '?plugin=et-core' );
        $response_code = wp_remote_retrieve_response_code( $response );

        if( $response_code != '200' ) {
            return false;
        }

        $response = json_decode( wp_remote_retrieve_body( $response ) );
        if( ! isset( $response ) || ! isset( $response->new_version ) || empty( $response->new_version ) ) {
            return $version_information;
        }

        $version_information = $response;

        return $version_information;
    }

    public function is_update_available() {
        return version_compare( $this->current_version, $this->release_version(), '<' );
    }

    public function download_url() {
        $activated_data = get_option( 'etheme_activated_data' );
        $purchase = ( isset( $activated_data['purchase'] ) && ! empty( $activated_data['purchase'] ) ) ? $activated_data['purchase'] : '';
        $url = $this->api_url . 'files/get/' . $this->theme_name . '.zip?token=' . $this->api_key . '&code=' . $purchase;
        return apply_filters( 'etheme_theme_url', $url );
    }
    public function release_version() {
        $this->check_for_update();

        if ( isset( $this->information ) && isset( $this->information->new_version ) ) {
            return $this->information->new_version;
        }
    }


    public function activate( $purchase, $args ) {

        $data = array(
            'api_key' => (isset($args['token'])) ? $args['token'] : '',
            'theme' => ETHEME_PREFIX,
            'purchase' => $purchase,
            'domains_count' => $this->get_code_domains_count($purchase),
        );

        foreach ( $args as $key => $value ) {
            $data['item'][$key] = $value;
        }

        update_option( 'envato_purchase_code_15780546', $purchase );
        update_option( 'etheme_activated_data', maybe_unserialize( $data ) );
        update_option( 'etheme_is_activated', true );
    }

    public function process_form() {

        if (
            isset( $_POST['xstore-purchase-code'] )
            && ! empty( $_POST['xstore-purchase-code'] )
            && ! isset($_POST['form-license'])
            && empty( $_POST['form-license'] )
        ){
            $this->is_license = false;
        }

        if(
            isset( $_POST['xstore-purchase-code'] )
            && ! empty( $_POST['xstore-purchase-code'] )
            && isset($_POST['form-license'])
            && ! empty( $_POST['form-license'] )
        ) {
            $code = trim( $_POST['purchase-code'] );

            if( empty( $code ) ) {
                echo  '<p class="et-message et-error">The code is missing, <a href="https://help.market.envato.com/hc/en-us/articles/202822600-Where-Is-My-Purchase-Code-" target="_blank">here</a> you have more information where you can find it</p>';
                return;
            }

            $response = wp_remote_get( $this->api_url . 'activate/' . $code . '?envato_id='. $this->theme_id .'&domain=' .$this->domain() );
            if ( ! $response ) {
                echo  '<p class="et-message et-error">There was an error with the API request call; it was unable to connect to 8theme.com.</p>';
                return;
            }
            $response_code = wp_remote_retrieve_response_code( $response );

            if( $response_code != '200' ) {

                if( is_wp_error( $response ) ) {
                    echo '<p class="et-message et-error">' . $response->get_error_message() . '</p>';
                }

                if (!$response_code){
                    echo  '<p class="et-message et-error">There was an error with the API request call. This is a common problem caused by an SSL certificate. Please check it  <a href="https://www.sslshopper.com/ssl-checker.html" target="_blank" rel="nofollow">here</a>. If your certificate does not exist or has errors, please contact your server provider.</p>';
                    return;
                }
                echo  '<p class="et-message et-error">API request call error. Response code - <a href="https://en.wikipedia.org/wiki/List_of_HTTP_status_codes" target="_blank" rel="nofollow">' . $response_code . '</a></p>';
                return;
            }

            $data = json_decode( wp_remote_retrieve_body($response), true );

            if( isset( $data['error'] ) ) {
                echo  '<p class="et-message et-error">' . $data['error'] . '</p>';
                return;
            }

            if ( ! isset($data['verified']) ){
                echo  '<p class="et-message et-error">Sorry, I cannot get the API response..</p>';
                return;
            }

            if( ! $data['verified'] ) {
                echo  '<p class="et-message et-error">Sorry, the code is incorrect. Please try again.</p>';
                return;
            }

            $this->activate( $code, $data );

            wp_safe_redirect(admin_url( 'admin.php?page=et-panel-demos&after_activate=true&et_clear_plugins_transient' ));
        }
    }

    public function maskHalfOfStringFromEnd($str) {
        $halfLength = floor(strlen($str) / 2);
        $startUnmaskedIndex = strlen($str) - $halfLength;
        $maskedPart = str_repeat('*', $halfLength);
        $unmaskedPart = substr($str, 0, $startUnmaskedIndex);
        return $unmaskedPart . $maskedPart;
    }

    public function domain() {
        $domain = get_option('siteurl'); //or home
        $domain = str_replace('http://', '', $domain);
        $domain = str_replace('https://', '', $domain);
        $domain = str_replace('www', '', $domain); //add the . after the www if you don't want it
        return urlencode($domain);
    }

    public function domain_type(){
        if(isset($_POST['domain_type']) && in_array($_POST['domain_type'],  array('live', 'staging'))){
            return $_POST['domain_type'];
        }

        return 'undefined';
    }

    public function major_update( $type = 'msg' ) {

        // ! major update versions
        $versions = array( '4.0', '4.18', '5.0', '6.0', '7.0', '8.0', '9.0', '10.0', '11.0', '12.0', '13.0', '14.0', '15.0' );

        // ! current release version
        $release = $this->release_version();

        if ( ! in_array( $release , $versions ) ){
            return;
        }

        $message = esc_html__( 'This is major theme update! Please, do the backup of your files and database before proceed to update. If you use WooCommerce plugin make sure that its latest version.', 'xstore' );

        switch ( $type ) {
            case 'msg':
                $return = $message;
                break;
            case 'msg-b':
                $return = '<p class="et_major-update">' . $message . '</p>';
                break;
            case 'ver':
                $return = $release;
                break;
            case 'both':
                $return['msg'] = $message;
                $return['ver'] = $release;
                break;
            default:
                $return = $release;
                break;
        }
        return $return;
    }

    public function major_update_holder() {
        $major_update = $this->major_update( 'both' );
        echo '<span class="hidden et_major-version" data-version="' . $major_update['ver'] . '" data-message="' . $major_update['msg'] . '"></span>';
    }

    public function et_support_refresh(){
        $activated_data = get_option( 'etheme_activated_data' );
        $purchase = ( isset( $activated_data['purchase'] ) && ! empty( $activated_data['purchase'] ) ) ? $activated_data['purchase'] : '';

        if (!$purchase){
            wp_send_json( array( 'status' => 'error', 'msg' => __('Invalid purchase code', 'xstore') ) );
        }

        $remote_response = wp_remote_get( $this->api_url . 'support/' . $purchase . '?envato_id='. $this->theme_id );
        $response_code = wp_remote_retrieve_response_code( $remote_response );

        if( $response_code != '200' ) {
            wp_send_json( array( 'status' => 'error', 'msg' => __('API request call error. Can not connect to 8theme.com', 'xstore') ) );
        }

        $remote_response = json_decode( wp_remote_retrieve_body( $remote_response ) );

        if (isset($remote_response->error)){
            wp_send_json( array( 'status' => 'error', 'msg' => $remote_response->error ) );
        }

        $activated_data['item']['supported_until'] = $remote_response->supported_until;
        $activated_data['domains_count'] = $this->get_code_domains_count($purchase);
        update_option('etheme_activated_data', $activated_data);

        wp_send_json( array('status' => 'success', 'msg' => __('Successfully updated', 'xstore'), 'html' => $this->support_status($remote_response->supported_until, false) ) );
    }

    public function domains_count_limit() {
        $activated_data = get_option( 'etheme_activated_data' );
        if ( isset($activated_data['domains_count']) )
            return $activated_data['domains_count'];
        return 0;
    }

    public function get_support_day_left(){
        $activated_data = get_option( 'etheme_activated_data' );
        $supported_until = ( isset( $activated_data['item'] ) && isset( $activated_data['item']['supported_until'] ) && ! empty( $activated_data['item']['supported_until'] ) ) ? $activated_data['item']['supported_until'] : '';
        $daysleft = round(((( strtotime($supported_until) - time() )/24)/60)/60);
        return $daysleft;
    }

    public function get_support_status(){

        if (
            $this->is_subscription
            && isset($this->activated_data['item'])
            && isset($this->activated_data['item']['subscription_type'])
            && $this->activated_data['item']['subscription_type'] == 'lifetime'){
            return 'lifetime';
        }

        $daysleft = $this->get_support_day_left();

        if ($daysleft <= 30 && $daysleft > 0) {
            $status = 'expire-soon';
        }else if ($daysleft <= 0) {
            $status = 'expired';
        } else {
            $status = 'active';
        }
        return $status;
    }

    public function support_status($supported_until, $echo = true) {
        $support  = $this->get_support_status();
        $daysleft = $this->get_support_day_left();

        $left = $daysleft . ' ' . _nx( 'day remaining', 'days remaining', $daysleft, 'Support day/days remaining', 'xstore' );

        $icon = '<span style="cursor: pointer; font-size: 14px; width: 14px; height: 14px; padding-left: 5px; vertical-align: -1px;" class="et_support-refresh mtips mtips-top"><span class="dashicons dashicons-image-rotate"></span><span class="mt-mes">'.esc_html__('Refresh license status', 'xstore').'</span></span>';
        $renew = sprintf(__('To continue receiving premium support, %s.', 'xstore'), '<a href="https://1.envato.market/2rXmmA" target="_blank"><strong>' . __('renew now', 'xstore') . '</strong></a>');
        $renew .= '<br/>' . sprintf(__('Need help? %s.', 'xstore'), '<a href="https://help.market.envato.com/hc/en-us/articles/207886473-Extend-or-renew-Item-Support#:~:text=on%20that%20item%3A-,Log%20in%20to%20your%20account,on%20\'Renew%20support%20now" target="_blank"><strong>' . __('Learn more', 'xstore') . '</strong></a>');

        if ($support == 'expire-soon') {
            $status = 'et-notice';
            $left .= $icon . '</br>' . $renew;
        }else if ($support == 'expired') {
            $status = 'et-error';
            $left = __('Expired', 'xstore');
            $left .= $icon . '</br>' . $renew;
        } else if($support == 'lifetime'){
            $status = 'et-notice';
            $left = 'lifetime' . $icon;
        } else {
            $status = 'et-notice';
            $left .= $icon;
        }

        return '';

        if ($echo){
            printf(
                '<div class="et_support-block"><p class="temp-msg"></p><p class="et_support-status et-message %s">%s %s </p></div>',
                $status,
                __('Support status:', 'xstore'),
                $left
            );
        } else {
            return sprintf(
                '<div class="et_support-block"><p class="temp-msg"></p><p class="et_support-status et-message %s">%s %s </p></div>',
                $status,
                __('Support status:', 'xstore'),
                $left
            );
        }
    }

    public function deactivate_action(){
        if ( $this->api_url ) {

            if ($this->is_new_deactivate){

                $remote_response = wp_remote_get( $this->api_url . 'xstore/deactivate/' . $this->api_key . '?is_test=1/&domain=' . $this->domain() );

//	            write_log($this->api_url . 'deactivate/' . $this->api_key . 'is_test=1/?domain=' . $this->domain());

                $code = wp_remote_retrieve_response_code( $remote_response );

                $response = wp_remote_retrieve_body( $remote_response );

                if ($code == 200 && $response){
                    $response = json_decode($response, true);

                    if ($response['status'] == 'deleted' || $response['status'] == 'not_found'){
                        $this->update_options();
                        wp_send_json( array('status' => 'deleted') );
                    }

                    wp_send_json( array('status' => $response['status']) );
                }
                wp_send_json( array('status' => 'active') );
            }

            $remote_response = wp_remote_get( $this->api_url . 'xstore/deactivate/' . $this->api_key . '?domain=' . $this->domain() );

            // old deactivation type
//			$remote_response = wp_remote_get( $this->api_url . 'deactivate/' . $this->api_key . '?domain=' . $this->domain() );


            //wp_remote_retrieve_response_code( $remote_response );
            //wp_remote_retrieve_body( $remote_response );
        }
    }

    public function update_options(){
        update_option( 'etheme_activated_data',
            maybe_unserialize( array(
                'api_key' => 0,
                'theme' => 0,
                'purchase' => 0,
                'domains_count' => 0
            ) )
        );
        update_option( 'envato_purchase_code_15780546', '' );
    }

    public function deactivate() {
        check_ajax_referer('etheme_theme-actions', 'security');
        if (! current_user_can( 'manage_options' )){
            wp_send_json( array('status' => 'error') );
        }

        $this->deactivate_action();
        $this->update_options();
        delete_transient('etheme_plugins_info');
        wp_send_json( array('status' => 'deleted') );
    }

    public function ajax_etheme_activate_theme(){
        check_ajax_referer('etheme_theme-actions', 'security');
        if (! current_user_can( 'manage_options' )){
            wp_send_json( array('status' => 'error') );
        }

        $code = trim( $_POST['purchase_code'] );
        $this->activate_theme($code);
    }

    public function check_activation_data(){
        check_ajax_referer('etheme_theme-actions', 'security');
        if (! current_user_can( 'manage_options' )){
            wp_send_json( array('status' => 'error') );
        }

        $code = trim( $_POST['purchase_code'] );

        $this->activate_theme($code, 'check');
    }


    public function activate_theme($code, $type='activate'){
        $_response = array(
            'status' => 'error',
            'msg' => 'code 10'
        );

        if( empty( $code ) ) {
            $_response['msg'] = '<p class="et-message et-error">The code is missing, <a href="https://help.market.envato.com/hc/en-us/articles/202822600-Where-Is-My-Purchase-Code-" target="_blank">here</a> you have more information where you can find it</p>';
            wp_send_json( $_response );
        }

        $args = [
            'envato_id'   => $this->theme_id,
            'domain'      => $this->domain(),
        ];

        // domain type
        if($type='activate' && $this->domain_type() != 'undefined' && !  ( isset( $_POST['is_check']) && $_POST['is_check'] == 1)){
            $args['domain_type'] = $this->domain_type();
        }elseif ($type=='check' || ( isset( $_POST['is_check']) && $_POST['is_check'] == 1)){
            $args['is_check'] = 1;
            $args['check_limits'] = 1;
            if($this->domain_type() != 'undefined'){
                $args['domain_type'] = $this->domain_type();
            }
        }

        $url = $this->api_url . 'xstore/activate/' . $code . '?' . http_build_query($args);

        // write_log($url);

        add_filter( 'http_request_args', 'et_increase_http_request_timeout', 10, 2 );
        $response = wp_remote_get( $url );
        remove_filter( 'http_request_args', 'et_increase_http_request_timeout', 10, 2 );

        if ( ! $response ) {
            $_response['msg'] =  '<p class="et-message et-error">There was an error with the API request call; it was unable to connect to 8theme.com.</p>';
            wp_send_json( $_response );
        }
        $response_code = wp_remote_retrieve_response_code( $response );

        if( $response_code != '200' ) {

            if( is_wp_error( $response ) ) {
                $_response['msg'] = '<p class="et-message et-error">' . $response->get_error_message() . '</p>';
            }

            if (!$response_code){
                $_response['msg'] =  '<p class="et-message et-error">There was an error with the API request call. This is a common problem caused by an SSL certificate. Please check it  <a href="https://www.sslshopper.com/ssl-checker.html" target="_blank" rel="nofollow">here</a>. If your certificate does not exist or has errors, please contact your server provider.</p>';
                wp_send_json( $_response );
            }
            $_response['msg'] =  '<p class="et-message et-error">API request call error. Response code - <a href="https://en.wikipedia.org/wiki/List_of_HTTP_status_codes" target="_blank" rel="nofollow">' . $response_code . '</a></p>';
            wp_send_json( $_response );
        }

        $data = json_decode( wp_remote_retrieve_body($response), true );

        if( isset( $data['error'] ) ) {
            $style = '';
	        $_response['msg'] = '';
	        $_response['type'] =  (isset($data['type']) && !empty($data['type'])) ? $data['type'] : 'top';
	        $_response['location'] =  (isset($data['location']) && !empty($data['location'])) ? $data['location'] : 'top';

	        if ( $_response['location'] == 'after_form'){
		        $style = 'style="margin-top: 20px;"';
            }
            $_response['msg'] .= '<p class="et-message et-error"'.$style.'>' . $data['error'] . '</p>';

            wp_send_json( $_response );
        }

        if ( ! isset($data['verified']) ){
            $_response['msg'] =  '<p class="et-message et-error">Sorry, I cannot get the API response..</p>';
            wp_send_json( $_response );
        }

        if( ! $data['verified'] ) {
            $_response['msg'] =  '<p class="et-message et-error">Sorry, the code is incorrect. Please try again.</p>';
            wp_send_json( $_response );
        }

        if ($type=='activate'){
            $this->activate( $code, $data );
        }

        $_response = array(
            'status' => 'success',
            'msg' => 'code 11',
            'type' => (isset($data['type']))?$data['type']:''
        );

//		if (isset($data['type'])){
//			$_response['type'] =>
//		}

        if (isset($data['notice'])){
            $_response['notice'] = array(
                'notice' => $data['notice'],
                'type' => $data['type'],
                'max_domains' => $data['max_domains'],
                'actve_domains' => $data['actve_domains']
            );
        }

        wp_send_json( $_response );
    }

    public function get_code_domains_count($code){

        return 0;

        $url = 'https://www.8theme.com/themes/api/domainstokens/' . $code;
        $domains = array();
        $response = wp_remote_get( $url );
        $response_code = wp_remote_retrieve_response_code( $response );

        if( $response_code == '200' ) {
            $domains = json_decode( wp_remote_retrieve_body($response), true );
            if (
                ! is_array($domains)
                || ! count($domains)
                || ! isset($domains['domains'])
                || ! is_array($domains['domains'])
            ) {
                $domains['domains'] = array();
            }
        }
        return count($domains['domains']);
    }

}

if(!function_exists('etheme_check_theme_update')) {
    add_action('init', 'etheme_check_theme_update');
    function etheme_check_theme_update() {
        new ETheme_Version_Check();
    }
}