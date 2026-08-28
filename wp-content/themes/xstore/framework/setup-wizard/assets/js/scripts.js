/**
 * Wizard scripts
 *
 * @version 1.0.0
 * @since 9.5.0
 */

!function($) {
    // global
    if( typeof lazyload === "function" ){
        $(document).find('.lazyload:not(.zoomImg, .et-lazy-loaded, .rs-lazyload)').lazyload();
        $(".xstore-panel-grid-item-image img").on('error', function() {
            $(this).attr("src", $(this).attr("data-old-src"))
        });
    } else {
        $.each($(document).find('.lazyload:not(.zoomImg, .et-lazy-loaded, .rs-lazyload)'), function (e,t) {
            $(t).attr('src',$(t).attr('data-src'))
        });
    }

    // Basic step

    $('.update-site-basic').on('click', function(e){
        e.preventDefault();
        $('#et_setup-basis').trigger('submit');
    });

    $('.update-site-languages').on('click', function(e){
        e.preventDefault();
        $('#et_setup-language').trigger('submit');
    });

    // Register step
    $('#is_confirmed').on('change', function(){
        if (!$(this).prop('checked')){
            $('[for="is_confirmed"]').addClass('is_alert');
        } else {
            $('[for="is_confirmed"]').removeClass('is_alert');
        }
    });
    $('.activate-license-btn').on('click', function(_this,e) {
        _this.preventDefault();

       var code = $('#purchase-code').val(),
           is_dev = $('#is_dev').prop('checked'),
           is_confirmed = $('#is_confirmed').prop('checked'),
           security = $(document).find('[name="nonce_etheme-theme-actions"]').val();

       if (!is_confirmed){
            $('[for="is_confirmed"]').addClass('is_alert');
            return;
       }

       $('.et-message.et-error').remove();

        jQuery.ajax({
            type: 'POST',
            dataType: 'JSON',
            url: ajaxurl,
            data: {
                'action': 'etheme_check_activation_data',
                'purchase_code': code,
                'domain_type' : (is_dev) ? 'staging' : 'live',
                'is_confirmed': is_confirmed,
                'security':  security,
                'is_check': 1,

            },
            success: function (_data) {
                if (_data.status != 'error') {

                    jQuery.ajax({
                        type: 'POST',
                        dataType: 'JSON',
                        url: ajaxurl,
                        data: {
                            'action': 'etheme_activate_theme',
                            'purchase_code' : code,
                            'security':  security,
                            'domain_type' : (is_dev) ? 'staging' : 'live'
                        },
                        success: function (data) {
                        },
                        error: function (data) {
                            alert('Error while activation');
                        },
                        complete: function (){
                            location.href = adminurl + 'admin.php?page=xstore-setup&step=registered';
                        },
                    });
                } else {
                    $('#licence-form .xstore-form').after(_data.msg);
                    $('html, body').animate({
                        scrollTop: $('.et-message.et-error').offset().top
                    }, 900);
                }
            },
            error: function (data) {
            },
            complete: function (){
            },
        });

    });

    // Child theme step
    $('.create-child-theme').on('click', function (e) {
        e.preventDefault();
        var data = {
            action: 'et_create_child_theme',
            helper: 'child-theme',
            theme_name: $(document).find('[name="theme_name"]').val(),
            theme_template:  $(document).find('[name="theme_template"]').val(),
            security:   $(document).find('[name="nonce_etheme-create_child_theme"]').val(),
        };

        $.ajax({
            method: "POST",
            url: ajaxurl,
            data: data,
            dataType: 'json',
             success: function (response) {
                if (response.type == 'success') {
                    location.href = adminurl + 'admin.php?page=xstore-setup&step=child-theme-created';
                } else {

                }
            },
            error: function (response) {
            },
            complete: function (response) {
            }
        });
    });

    // Demos page
    $(document).find(".version-screenshot img").on('error', function() {
        $(this).attr("src", $(this).attr("data-old-src"))
    });
    $('.etheme-versions-search').on('keyup', function(e){
         var  _this = $(this);
         wizard_demos_search(
             _this,
             '.etheme-search',
             '.xstore-panel-grid-item',
             '.xstore-panel-grid-item-name',
             function () {
                 $('.xstore-panel-grid-item').removeClass('et-hide').removeClass('et-show');
             }
         );
    });


    $('.et-counter').each(function () {
        let postfix = $(this).data('postfix');
        $(this).prop('Counter', 0).animate({
            Counter: parseInt($(this).text())
        }, {
            duration: 1500,
            easing: 'swing',
            step: function (now) {
                $(this).text(Math.ceil(now) + (!!postfix ? postfix : ''));}
        });
    });

    $('.installation-progress-value').each(function () {
       $(this).css({'width': $(this).attr('data-active-percent') + '%'});
    });

    $('.et_panel-dark-light-switcher .switcher').on('click', function (e) {
        e.preventDefault();
        let is_light_active = $('body').attr('data-mode') == 'light';
        if ( is_light_active ) {
            $(this).addClass('dark-mode').removeClass('light-mode');
            $('body').addClass('et-dark-mode').removeClass('et-light-mode')
        }
        else {
            $(this).addClass('light-mode').removeClass('dark-mode');
            $('body').addClass('et-light-mode').removeClass('et-dark-mode')
        }
        var data = {
            action: 'et_panel_ajax',
            action_type: 'et_panel_dark_light_switch_default',
            value: (is_light_active ? 'dark' : 'light'),
            nonce: $('#nonce_etheme_panel_actions').val()
        };
        $.ajax({
            type: 'POST',
            url: ajaxurl,
            data: data,
            success: function (response) {
                // location.reload();
            },
            error: function () {
                alert('Error while switching');
            },
            complete: function () {
            }
        });
        $('body').attr('data-mode', (!is_light_active ? 'light' : 'dark'));
    });

    function wizard_demos_search(input, form, selector, find_in, on_clear){
        var value = input.val(),
            no_results = $('.et-not-found');
        $(form).find('.spinner').addClass('is-active');
        $(form).find('.etheme-search-icon').addClass('et-invisible');
        no_results.removeClass('et-show').addClass('et-hide');
        if (value.length >= 2) {
            $(selector).removeClass('et-show').addClass('et-hide');
            var is_find = false;
            $.each($(selector), function () {
                var text = $(this).find(find_in).text();
                if (text.toLowerCase().includes(value.toLowerCase())) {
                    $(this).removeClass('et-hide').addClass('et-show');
                    is_find = true;
                }
            });
            if (!is_find){
                no_results.removeClass('et-hide').addClass('et-show');
                no_results.find('.et-search-request').text(value);
            }
        } else {
            on_clear();
        }
        setTimeout(function () {
            $(form).find('.spinner').removeClass('is-active');
            $(form).find('.etheme-search-icon').removeClass('et-invisible');
        }, 500);
    }

    // engine step
    $(document).on('change', 'input[name="engine"]', function (e) {
        var engine = $('input[name="engine"]:checked').val();
        $('.engine-selector').removeClass('active');
        $('.engine-selector[for="' + engine + '"]').addClass('active');

        const $link = $('.select-engine-btn');
        const href = new URL($link.attr('href'));
        href.searchParams.set('engine', engine);
        $link.attr('href', href.toString());
    });

    // plugins step
    $('.all-plugins').on('change', function (e) {
        // if ( !$(this).prop('checked') ) {
        //     $('.plugins-install-btn').removeClass('install-with-all');
        //     $('.plugins-install-btn').removeClass('selected-to-install');
        //     $('input.plugin-setup').prop('checked', false);
        // } else {
        //     $('.plugins-install-btn').addClass('install-with-all');
        //     $('.plugins-install-btn').addClass('selected-to-install');
        //     $('input.plugin-setup').prop('checked', true);
        // }

        if ( !$(this).prop('checked') ) {
            // $('.install-with-all').removeClass('install-with-all');
            $('.selected-to-install').removeClass('selected-to-install');
            $('input.plugin-setup').prop('checked', false);
        } else {
            // $('.et_popup-import-plugin').addClass('install-with-all');
            $('.et_popup-import-plugin').addClass('selected-to-install');
            $('input.plugin-setup').prop('checked', true);
        }
    });

    $('input.plugin-setup').on('change', function (e) {
        if (!$(this).prop('checked')) {
            $(this).parents('.et_popup-import-plugin').removeClass('selected-to-install');
        } else {
            $(this).parents('.et_popup-import-plugin').addClass('selected-to-install');
        }

        // Check if all individual checkboxes are now checked
        const allChecked = $('input.plugin-setup').length === $('input.plugin-setup:checked').length;

        // Set master checkbox accordingly
        $('#all-plugins').prop('checked', allChecked);
    });

     $(document).on('click', '.plugins-install-btn-all', function (e) {
        e.preventDefault();
        var _this = $(this);

        var plugins = [];

        $.each($('.et_popup-import-plugin:not(.et_plugin-installed)'), function(e, t){
            plugins.push($(t).find('.selected-to-install').attr('data-slug'));
        });
        
        if(plugins){
            if(!_this.hasClass('in-progress')){
            _this.addClass('in-progress');
                recalc_percent_loader($('.loader-percent'), $('.et_popup-import-plugin:not(.et_plugin-installed)').length);
            }
            install_all_plugins(plugins);
        }

     });

    function install_all_plugins(plugins){
        install_plugin($('[data-slug="'+plugins[0]+'"]'), plugins[0], plugins, 'all');
    }

    function install_plugin(_this, _plugin, plugins, type){
        var $el = _this,
            li = $el.parents('li'),
            data = {
                action: 'envato_setup_plugins',
                helper:'plugins',
                slug: _plugin,
                wpnonce: $(document).find('.et_plugin-nonce').attr('data-plugin-nonce'),
            },
            current_item_hash = '';

        $(document).addClass('ajax-processing');
        $el.addClass('et_plugin-installing');
        li.addClass('processing');
        $el.find('.setup-button-link').text($el.data('process-text'));

        $.ajax({
            method: "POST",
            url: ajaxurl,
            data: data,
            success: function (response) {
                if (response.hash != current_item_hash) {
                    $.ajax({
                        method: "POST",
                        url: response.url,
                        data: response,
                        success: function (response) {
                            if ($el.hasClass('et_core-plugin')) {
                                $('.etheme-page-nav .mtips').removeClass('inactive mtips');
                                window.location = $('.etheme-page-nav .et-nav-portfolio').attr('href');
                                $el.css('pointer-events', 'none');
                                $('.mt-mes').remove();
                            }
                        },
                        error: function () {

                        },
                        complete: function () {
                            // second chance for plugins
                            if (!$el.hasClass('et_second-try')) {
                                li.removeClass('processing');
                                $el.removeClass('loading');
                                $(document).removeClass('ajax-processing');
                                $el.removeClass('et_plugin-installed').addClass('et_second-try');
                                install_plugin($('[data-slug="'+_plugin+'"]'), _plugin, plugins, 'all');
                            } else {
                                li.addClass('activated');
                                $el.removeClass('et_plugin-installing').addClass('et_plugin-installed green-color').attr('style', null).find('.setup-button-link').text($el.data('success-text')).prepend(`<svg width="1em" height="1em" viewBox="0 0 9 9" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M4.5 0C2.01911 0 0 2.01911 0 4.5C0 6.98089 2.01911 9 4.5 9C6.98089 9 9 6.98089 9 4.5C9 2.01911 6.98089 0 4.5 0ZM4.5 8.2666C2.41751 8.2666 0.7334 6.5825 0.7334 4.5C0.7334 2.41751 2.41751 0.7334 4.5 0.7334C6.5825 0.7334 8.2666 2.41751 8.2666 4.5C8.2666 6.5825 6.5825 8.2666 4.5 8.2666ZM6.80885 2.85211C6.70926 2.84306 6.6006 2.87928 6.52817 2.95171L3.85714 5.54125L2.47183 4.11972C2.3994 4.04728 2.2998 4.01107 2.19115 4.01107C2.0825 4.01107 1.9829 4.05634 1.92857 4.14688C1.86519 4.22837 1.82897 4.33702 1.83803 4.43662C1.84708 4.51811 1.8833 4.5996 1.94668 4.64487L3.58551 6.33803C3.65795 6.41046 3.74849 6.44668 3.84809 6.44668C3.93863 6.44668 4.02918 6.41046 4.10161 6.33803L7.02616 3.48592C7.09859 3.41348 7.13481 3.31388 7.13481 3.20523C7.13481 3.11469 7.09859 3.02414 7.03521 2.96982C6.98089 2.89738 6.8994 2.86117 6.80885 2.85211Z" fill="currentColor"/></svg>`).removeClass('setup-button-link');
                                recalc_percent_loader($('.loader-percent'), $('.et_popup-import-plugin:not(.et_plugin-installed, .activated)').length);
                                
                                if($('.et_popup-import-plugin:not(.et_plugin-installed, .activated)').length <1){
                                    $('.plugins-next-btn').removeClass('hidden');
                                }
                                if(type == 'all'){
                                    plugins.shift();

                                    if(plugins.length){
                                        install_plugin($('[data-slug="'+plugins[0]+'"]'), plugins[0], plugins, 'all');
                                    }
                                }
                            }
                        }
                    });
                } else {
                    $el.removeClass('et_plugin-installing').addClass('et_plugin-installed installing-error red-color').attr('style', null).find('.setup-button-link').text('Failed');
                }
            },
            error: function (response) {
                $el.removeClass('et_plugin-installing').addClass('et_plugin-installed installing-error red-color').attr('style', null).find('.setup-button-link').text('Failed');
                li.removeClass('processing');
                $el.removeClass('loading');
               $(document).removeClass('ajax-processing');
            },
            complete: function (response) {
            }
        });
    }

     function recalc_percent_loader(loader, percent){
        if(percent == 0){
            percent = 100;
        }
        $(loader).text( parseInt((100/percent)-1) + '%' ).attr('data-percent',parseInt((100/percent)-1));

        if(percent == 100 || percent == 0 ){
           $('.plugins-install-btn-all').addClass('hidden');
        //    $('#all-plugins').parents('li').remove();
            // $('.plugins-install-btn-all + .setup-button').removeClass('hidden');
        }
     }

    $(document).on('click', '.plugins-install-btn:not(.et_plugin-installing, .et_plugin-installed)', function (e) {
        e.preventDefault();
        var $el = $(this);
        install_plugin($el, $el.attr('data-slug'), [], 'single');
    
    });

    // import page
    $('#et_all').on('change', function (e) {
        if (!$(this).prop('checked')) {
            $('.et_manual-setup').addClass('active');
            $('.et_manual-setup input').prop('checked', false);
        } else {
            $('.et_manual-setup').removeClass('active');
            $('.et_manual-setup input').prop('checked', true);
        }
        // $('#pages').trigger('change');
    });

    $('.et_manual-setup input').on('change', function (e) {
        if (!$(this).prop('checked')) {
            $('#et_all').prop('checked', false);
            
        } else {
            $('.et_hidden-setup input').prop('checked', true);
            if($('.et_manual-setup input:checked').length === $('.et_manual-setup input').length){
                $('#et_all').prop('checked', true);
            }
        }
        // $('#pages').trigger('change');
    });

    $('#pages').on('change', function (e) {
        if (!$(this).prop('checked')) {
            $('.et_manual-setup-page').addClass('hidden');
            $('#widgets').prop('checked', false);
            $('#home_page').prop('checked', false);
        } else {
            $('.et_manual-setup-page').removeClass('hidden');
            $('#widgets').prop('checked', true);
            $('#home_page').prop('checked', true);
        }
    });

    var to_install = [];
    var errors = [];

    $('.install-demo-data').on('click', function(e){
        $('body').addClass('process-import');
        e.preventDefault();
        to_install = $('.et_install-demo-form').serializeArray();

        $('.et_progress').attr('data-step', parseInt(100 / to_install.length));
        var urlParams = new URLSearchParams(window.location.search),
            engine = urlParams.get('engine'),
            version = urlParams.get('version'),
            step = $('.wizard-step.wizard-install'),
            data = {
                type: 'xml',
                action: 'etheme_import_ajax',
                version: version,
                engine: engine,
                security:  $(document).find('[name="nonce_etheme_import-demo"]').val(),
            };

        $('.et_install-demo-form-wrapper, .wizard-step-controllers').addClass('hidden');
        $('.et_step-processing').removeClass('hidden');
        demos_import_install_part(step, data, 0, false, '');
    });

    function demos_import_install_part(step, data, iteration, error) {

        var importSection = $('.etheme-import-section');

        if (iteration == 0) {
            data.install = to_install.shift();
        } else {
            iteration = iteration - 1;
        }

        // if(to_install.length < 1){
        //     location.href = adminurl + 'admin.php?page=xstore-setup&step=final';
        //     return;
        // }

        data.type = get_part_type(data.install.value);
        data.errors = errors;

        // Install patches
        if ( data.type == 'patches'){
            demos_import_progress_setup(step, data.install.name);

            step.find('.et_progress-notice-text').html('Installing patches');
            step.find('.et_navigate-install').addClass('hidden');

            $.ajax({
                url: XStorePanelPatcherConfig.ajaxurl,
                method: 'POST',
                data: {
                    'action': 'xstore_refresh_patches',
                    'security': XStorePanelPatcherConfig.refresh_patches_nonce
                },
                dataType: 'json',
                beforeSend: function () {
                },
                complete: function () {
                },
                success: function (response) {
                    $.ajax({
                        url: XStorePanelPatcherConfig.ajaxurl,
                        method: 'POST',
                        data: {
                            'action': 'xstore_apply_patch_all',
                            'theme_version': XStorePanelPatcherConfig.theme_version,
                            'test_mode': XStorePanelPatcherConfig.test_mode,
                            '_nonce': XStorePanelPatcherConfig.nonce,
                        },
                        dataType: 'json',
                        beforeSend: function () {
                        },
                        complete: function (response) {
                            demos_import_install_part(step, data, 0, false);
                        },
                        success: function (response) {
                        },
                        error: function () {
                        }
                    });
                },
                error: function () {
                }
            });
            return;
        }

        var installPartName = $('[for="' + data.install.value + '"]').html();

        if( typeof installPartName != "undefined" && installPartName){

            if(data.install.value != "init_builders"){
                installPartName = 'Installing ' + installPartName;
            }
            step.find('.et_progress-notice-text').html(installPartName);
        }

        step.find('.et_navigate-install').addClass('hidden');

        $.ajax({
            method: "POST",
            url: ajaxurl,
            data: data,
            success: function (response) {
                if (
                    response
                    && response.status != 'installed'
                    && data.install.name != 'nonce_etheme_import-demo'
                    && data.install.name != 'elementor_headers'
                    && data.install.name != 'elementor_archives'
                    && data.install.name != 'elementor_single_products'
                    && data.install.name != 'elementor_footers'
                    && data.install.name != 'elementor_post_archive'
                    && data.install.name != 'elementor_post'
                ) {
                    errors.push(data.install.name);
                }
                demos_import_progress_setup(step, data.install.name);
                if (to_install.length) {
                    demos_import_install_part(step, data, 0, false);
                } else {
                   location.href = adminurl + 'admin.php?page=xstore-setup&step=final';
                }
                importSection.removeClass('import-process');
            },
            error: function () {
                if (to_install.length) {
                    // quick fix to prevent variations errors
                    if (data.type != 'variation_taxonomy' && data.type != 'variations_trems' && data.type != 'variation_products') {
                        if (data.type == 'xml' && data.install.name != 'menu') {
                            if (iteration == 0 && data.install.name != error) {
                                if (data.install.name == 'media'){
                                    demos_import_install_part(step, data, 5, false);
                                } else {
                                    demos_import_install_part(step, data, 2, false);
                                }
                            } else {
                                demos_import_install_part(step, data, iteration, data.install.name);

                                if (iteration == 0 && error && data.install.name != error) {
                                    errors.push(error);

                                    demos_import_progress_setup(step, error);
                                }
                            }
                        } else {
                            errors.push(data.install.name);
                            demos_import_progress_setup(step, data.install.name);
                            demos_import_install_part(step, data, 0, data.install.name);
                        }
                    } else {
                        demos_import_install_part(step, data, 0, data.install.name);
                    }
                } else {
                    $('body').removeClass('process-import');
                    location.href = adminurl + 'admin.php?page=xstore-setup&step=final';
                }

            },
            complete: function (response) {
            }
        });
    }

    function get_part_type (part) {
        switch (part) {
            case 'patches':
                return 'patches';
            case 'options':
                return 'options';
            case 'menu':
                return 'menu';
            case 'home_page':
                return 'home_page';
            case 'slider':
                return 'slider';
            case 'widgets':
                return 'widgets';
            case 'fonts':
                return 'fonts';
            case 'variation_taxonomy':
                return 'variation_taxonomy';
            case 'variations_trems':
                return 'variations_trems';
            case 'variation_products':
                return 'variation_products';
            case 'et_multiple_headers':
                return 'et_multiple_headers';
            case 'et_multiple_conditions':
                return 'et_multiple_conditions';
            case 'et_multiple_single_product':
                return 'et_multiple_single_product';
            case 'et_multiple_single_product_conditions':
                return 'et_multiple_single_product_conditions';
            case 'elementor_globals':
                return 'elementor_globals';
            case 'elementor_sections':
                return 'elementor_sections';
            case 'elementor_footers':
                return 'elementor_footers';
            case 'elementor_headers':
                return 'elementor_headers';
            case 'elementor_archives':
                return 'elementor_archives';
            case 'elementor_single_products':
                return 'elementor_single_products';
            case 'elementor_post_archive':
                return 'elementor_post_archive';
            case 'elementor_post':
                return 'elementor_post';
            case 'version_info':
                return 'version_info';
            case 'init_builders':
                return 'init_builders';
            case 'default_woocommerce_pages':
                return 'default_woocommerce_pages';
            case 'sales_boosters':
                return 'sales_boosters';
            default:
                return 'xml';
        }
    }

    function demos_import_progress_setup(step, progress) {
        var part = parseInt(step.find('.et_progress').val()) + parseInt(step.find('.et_progress').attr('data-step'));
        step.find('.et_progress').attr('value', part);
        // step.find('.et_progress-notice-text').html('Installed ' + $('[for="' + part + '"]').html());
        if (parseInt(part)>10){
            step.find('.progress-label').html(part + '%');
            if ( parseInt(part)>=50 )
                step.find('.progress-label').css({'right': 'calc('+(100 - part)+'% + 3px)', 'left': 'auto'});
            else
                step.find('.progress-label').css('left', 'calc('+part+'% - 30px)');
        } else {
            step.find('.progress-label').html(part + '%');
        }
    }
    
    // window.addEventListener("beforeunload", function (e) {
    //     if($('body').hasClass('process-import')){
    //         e.preventDefault();
    //         e.returnValue = "The changes you made will be lost if you navigate away from this page.";
    //     }
    // });
}(jQuery);
