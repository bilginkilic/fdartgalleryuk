<?php defined('ABSPATH') or die; ?>

<?php if(count($downloadableFiles)): ?>
<div  class="font_downloader_wrapper text-center">
    <img class="mb-3" src="<?php echo esc_url(FLUENTFORM_PDF_URL . 'assets/images/pdf-img.png'); ?>" alt="">
    <h3 class="mb-2"><?php echo esc_html__('Fonts are required for PDF Generation', 'fluentforms-pdf') ?></h3>
    <p class="mb-4"><?php echo esc_html__('This module requires to download fonts for PDF generation. Please click on the below button and it will download the required font files. This is one time job', 'fluentforms-pdf') ?></p>
    <button id="ff_download_fonts" class="el-button el-button--primary">
        <span class="ff_download_fonts_bar"></span>
        <span class="ff_download_fonts_text"><?php echo esc_html__('Install Fonts', 'fluentforms-pdf') ?></span>
    </button>
    <div class="ff_download_loading mt-3"></div>
    <div class="ff_download_logs mt-3 hidden"></div>
</div>
<?php else: ?>

<div class="ff_pdf_system_status">
    <h3 class="mb-3"><?php echo esc_html__('Fluent Forms PDF Module is now active', 'fluentforms-pdf') ?> <?php if(!$statuses['status']): ?><span style="color: red;"><?php echo esc_html__('But Few Server Extensions are missing', 'fluentforms-pdf') ?></span><?php endif; ?></h3>
    <ul>
        <?php foreach ($statuses['extensions'] as $status): ?>
        <li>
            <?php if($status['status']): ?><span class="dashicons dashicons-yes"></span>
            <?php else: ?><span class="dashicons dashicons-no-alt"></span><?php endif; ?>
            <?php echo esc_html($status['label']); ?>
        </li>
        <?php endforeach; ?>
    </ul>

    <?php if($statuses['status']): ?>
    <p><?php echo esc_html__('All Looks good! You can now use Fluent Forms PDF Addon.', 'fluentforms-pdf') ?> <a href="<?php echo esc_url($globalSettingsUrl); ?>"><?php echo esc_html__('Click Here', 'fluentforms-pdf') ?></a> <?php echo esc_html__(' to check your global PDF feed settings', 'fluentforms-pdf') ?></p>
    <?php endif; ?>
</div>
<?php endif; ?>