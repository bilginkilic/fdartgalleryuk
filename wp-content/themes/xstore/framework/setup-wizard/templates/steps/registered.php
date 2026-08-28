<?php if ( ! defined('ETHEME_FW')) exit('No direct script access allowed');
/**
 * Template "Registered" - step for ET_Setup_Wizard.
 * @package ET_Setup_Wizard
 * @since 9.5.0
 * @version 1.0.0
 */

?>
<div class="wizard-step container-mini wizard-registered">
	<div class="wizard-step-content">
        <div class="wizard-step-heading text-center">
            <span style="line-height: 1;padding: 10px;margin-bottom:15px;background: #C8E6C9;border-radius: 50%;display: inline-flex;align-items: center;">
                <svg width="2em" height="2em" viewBox="0 0 44 34" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M38.7608 0.260834L15.3525 23.6967L5.68909 14.0608L0.810791 18.9391L15.3594 33.4533L43.6408 5.13913L38.7608 0.260834Z" fill="#4CAF50"></path>
                </svg>
            </span>
            <h2>
                <?php esc_html_e('Theme successfully activated', 'xstore'); ?>
            </h2>
            <p>
                <?php esc_html_e('Your license has been successfully verified, and the theme is now activated. Auto updates are enabled. W\'re excited to support your web development journey and help you build amazing experiences.', 'xstore'); ?>
            </p>
        </div>
        <div class="text-center">
            <svg class="signature-svg" width="163" height="89" viewBox="0 0 468 258" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M107.5 140.5C102 133.833 100.2 114.5 137 90.5C148.5 83 171.1 79.4 147.5 103C140.667 110.5 127.229 127.204 125 133C122.5 139.5 122.5 150.5 131.5 150.5C138.7 150.5 142.5 137.833 143.5 131.5C144.5 125.167 143.3 107.3 140.5 84.5M161.5 82C161 80.5 163.9 75.1 179.5 65.5C195.1 55.9 233.333 33.5 250.5 23.5M197.5 33.5C196.667 28.6667 194.4 21.9 192 33.5C189 48 172.339 115.386 168 131.5C164.5 144.5 164.5 149 164.5 159.5M197.5 157C199.741 148.382 202.664 137.466 205.98 125.5M238.5 42C243.3 32.4 249.5 15.6667 252 8.50002C253.5 2.16669 253.5 -4.89998 241.5 17.5C232.282 34.7063 216.968 85.8591 205.98 125.5M205.98 125.5C209.32 117.667 217 101.5 221 99.5C225 97.5 222.667 106 221 110.5C217.667 121.667 215.1 141.1 231.5 129.5C252 115 262.5 107 267.5 94.5C272.5 82 259 86 252 96.5C245.712 105.932 237 145.5 279 122M289.5 92C290.833 94 292.7 102 289.5 118C289.3 119 303.775 95.0065 308.5 92C314 88.5 315.5 92.5 313.5 112C313 113 330.1 86.7 344.5 87.5C355 88.0833 349 106 346.5 112C344.333 117.833 345.4 125.3 367 108.5C383 97.3 397.667 78.8333 403 71C405.833 66.5 406.7 61.2 387.5 76C363.5 94.5 388 106 394 106.5C400 107 414.5 107.5 428 102M158.5 197C159 194.833 159 190.5 155 190.5C151 190.5 149.333 201.5 149 207C148.5 218.5 151.2 242.8 166 248C184.5 254.5 170.5 241 166 239.5C161.5 238 145.5 231 107.5 237.5C77.1 242.7 60.5 247.667 56 249.5C46.3333 251.833 24.3 256.5 13.5 256.5C-9.53674e-07 256.5 0.499999 252.5 3 248C5.5 243.5 12 228 68.5 208.5C125 189 181 170 238 162.5C295 155 428.5 150.5 457.5 153C480.7 155 449.5 157.5 431 158.5M172.5 234C178.667 230.667 190.6 222.1 189 214.5C187 205 178.5 218.5 177.5 222C176.5 225.5 168.5 257 199 239.5C201.167 238.167 205.9 234.9 207.5 232.5M207.5 232.5C204.3 220.1 220.5 215.5 229 215.5C234.5 215.5 241.731 220.5 234.135 229M207.5 232.5C209.5 237.459 216.9 244.26 230.5 232.5C231.953 231.244 233.163 230.088 234.135 229M207.5 232.5C209.09 233.667 216.643 234.6 234.135 229M234.135 229C241.923 226.167 256 222.6 250 231M255 217C256.167 223.167 260.7 233.4 269.5 225C278.3 216.6 282 211.5 281.5 212.5C281.667 220.167 284 233.4 292 225C302 214.5 321 206.5 321 222C321 237.5 320.5 234.5 323 236" stroke="var(--et_admin_dark2white-color, #1D1A34)" stroke-width="3" stroke-linecap="round"/>
            </svg>
        </div>
        <br/>
        <p class="text-center"><?php esc_html_e('Best wishes, Your 8theme Team', 'xstore'); ?></p>
	</div>
	<div class="wizard-step-controllers">
		<a href="<?php echo ET_Setup_Wizard::get_controls_url('child-theme'); ?>" class="setup-button setup-button-arrow wizard-controllers-button"><?php echo ET_Setup_Wizard::texts('next'); ?>
            <svg class="arrow-icon" xmlns="http://www.w3.org/2000/svg" width="1.3em" height="1.3em" viewBox="0 0 32 32">
                <g fill="none" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round" stroke-miterlimit="10">
                    <circle class="arrow-icon--circle" cx="16" cy="16" r="15.12"></circle>
                    <path class="arrow-icon--arrow" d="M16.14 9.93L22.21 16l-6.07 6.07M8.23 16h13.98"></path>
                </g>
            </svg></a>
	</div>
</div>