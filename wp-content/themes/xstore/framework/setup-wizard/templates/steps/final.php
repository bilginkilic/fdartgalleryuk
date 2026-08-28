<?php if ( ! defined('ETHEME_FW')) exit('No direct script access allowed');
/**
 * Template "final" - step for ET_Setup_Wizard.
 * @package ET_Setup_Wizard
 * @since 9.5.0
 * @version 1.0.0
 */

?>

<div class="wizard-step wizard-final">
    <div class="wizard-step-content text-center">
        <span style="line-height: 1;padding: 10px;margin-bottom:15px;background: #C8E6C9;border-radius: 50%;display: inline-flex;align-items: center;">
            <svg width="2em" height="2em" viewBox="0 0 44 34" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M38.7608 0.260834L15.3525 23.6967L5.68909 14.0608L0.810791 18.9391L15.3594 33.4533L43.6408 5.13913L38.7608 0.260834Z" fill="#4CAF50"></path>
            </svg>
        </span>
        <h2><?php esc_html_e('Congratulations! Your new site is ready!', 'xstore')?></h2>
        <p><?php esc_html_e('Congratulations! You can now explore all the features, customize your site, and enjoy the beautiful design of XStore.', 'xstore')?></p>
    </div>
	<div class="wizard-step-controllers wizard-step-controllers-inline">
		<a href="<?php echo esc_url(admin_url('admin.php?page=et-panel-welcome')); ?>" target="_blank" class="setup-button setup-button-outline wizard-controllers-button"><svg width="1em" height="1em" viewBox="0 0 14 14" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M6.22222 14V9.33333H7.77778V10.8889H14V12.4444H7.77778V14H6.22222ZM0 12.4444V10.8889H4.66667V12.4444H0ZM3.11111 9.33333V7.77778H0V6.22222H3.11111V4.66667H4.66667V9.33333H3.11111ZM6.22222 7.77778V6.22222H14V7.77778H6.22222ZM9.33333 4.66667V0H10.8889V1.55556H14V3.11111H10.8889V4.66667H9.33333ZM0 3.11111V1.55556H7.77778V3.11111H0Z" fill="currentColor"/>
            </svg>
            <?php esc_html_e('Start customizing', 'xstore')?>
             </a>
        <a href="<?php echo get_home_url(); ?>" target="_blank" class="setup-button wizard-controllers-button"><svg width="1em" height="1em" viewBox="0 0 14 14" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M1.55556 14C1.12778 14 0.761574 13.8477 0.456944 13.5431C0.152315 13.2384 0 12.8722 0 12.4444V1.55556C0 1.12778 0.152315 0.761574 0.456944 0.456944C0.761574 0.152315 1.12778 0 1.55556 0H12.4444C12.8722 0 13.2384 0.152315 13.5431 0.456944C13.8477 0.761574 14 1.12778 14 1.55556V12.4444C14 12.8722 13.8477 13.2384 13.5431 13.5431C13.2384 13.8477 12.8722 14 12.4444 14H1.55556ZM1.55556 12.4444H12.4444V3.11111H1.55556V12.4444ZM7 10.8889C5.93704 10.8889 4.9875 10.6005 4.15139 10.0236C3.31528 9.44676 2.70926 8.69815 2.33333 7.77778C2.70926 6.85741 3.31528 6.1088 4.15139 5.53194C4.9875 4.95509 5.93704 4.66667 7 4.66667C8.06296 4.66667 9.0125 4.95509 9.84861 5.53194C10.6847 6.1088 11.2907 6.85741 11.6667 7.77778C11.2907 8.69815 10.6847 9.44676 9.84861 10.0236C9.0125 10.6005 8.06296 10.8889 7 10.8889ZM7 9.72222C7.72593 9.72222 8.38704 9.55046 8.98333 9.20694C9.57963 8.86343 10.0463 8.38704 10.3833 7.77778C10.0463 7.16852 9.57963 6.69213 8.98333 6.34861C8.38704 6.00509 7.72593 5.83333 7 5.83333C6.27407 5.83333 5.61296 6.00509 5.01667 6.34861C4.42037 6.69213 3.9537 7.16852 3.61667 7.77778C3.9537 8.38704 4.42037 8.86343 5.01667 9.20694C5.61296 9.55046 6.27407 9.72222 7 9.72222ZM7 8.94444C7.32407 8.94444 7.59954 8.83102 7.82639 8.60417C8.05324 8.37732 8.16667 8.10185 8.16667 7.77778C8.16667 7.4537 8.05324 7.17824 7.82639 6.95139C7.59954 6.72454 7.32407 6.61111 7 6.61111C6.67593 6.61111 6.40046 6.72454 6.17361 6.95139C5.94676 7.17824 5.83333 7.4537 5.83333 7.77778C5.83333 8.10185 5.94676 8.37732 6.17361 8.60417C6.40046 8.83102 6.67593 8.94444 7 8.94444Z" fill="currentColor"/>
            </svg>
            <?php esc_html_e('View your new site', 'xstore')?>
            </a>
	</div>
</div>
