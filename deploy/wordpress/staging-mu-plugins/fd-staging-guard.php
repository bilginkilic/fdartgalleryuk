<?php
/**
 * Plugin Name: FD Staging Guard
 * Description: Bu kopyanin bir DENEME ortami oldugunu garanti eder: disari mail
 *              gonderimini keser, arama motorlarina kapatir, panelde uyari
 *              gosterir. YALNIZCA staging sitelerine kurulur.
 * Version:     1.0
 *
 * Neden gerekli: staging, canli veritabaninin birebir kopyasidir — icinde
 * gercek musteri adresleri, siparisler ve WooCommerce zamanlanmis gorevleri
 * vardir. Onlem alinmazsa deneme ortami gercek musterilere "siparisiniz
 * kargolandi" maili gonderebilir.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 1) Giden e-postayi kes.
 * wp_mail'i yeniden tanimlamak yerine PHPMailer'i devre disi birakiyoruz:
 * boylece eklentiler "mail gonderildi" sanip akislarini tamamlar, ama hicbir
 * mesaj disari cikmaz.
 */
add_action(
	'phpmailer_init',
	function ( $phpmailer ) {
		$phpmailer->ClearAllRecipients();
		$phpmailer->ClearAttachments();
		$phpmailer->ClearCustomHeaders();
		$phpmailer->ClearReplyTos();
		$phpmailer->Mailer = 'mail';
		$phpmailer->Host   = 'localhost';
		// Gonderimi kesin olarak engelle.
		$phpmailer->addCustomHeader( 'X-Staging-Blocked', '1' );
		$phpmailer->PreSend = function () {
			return false; };
	},
	PHP_INT_MAX
);

// Ek guvenlik: wp_mail cagrisini bastan bosa dusur.
add_filter(
	'pre_wp_mail',
	function () {
		return true; // "gonderildi" de, ama gonderme.
	},
	PHP_INT_MAX
);

/**
 * 2) Arama motorlarina kapali kalsin — veritabani canlidan geldigi icin
 *    blog_public ayari 1 olarak gelmis olabilir.
 */
add_filter( 'pre_option_blog_public', '__return_zero' );

/**
 * 2b) robots.txt'yi de kesin olarak kapat.
 *
 * `blog_public = 0` tek basina YETMIYOR: Yoast `robots_txt` filtresine baglanip
 * ciktinin tamamini kendi kurallariyla degistiriyor ve `Disallow: /` satirini
 * dusuruyor. Olculdu (01.09.2026): iki dev sitesinde de robots.txt WooCommerce
 * ve Yoast kurallarini gosteriyor, `Disallow: /` yok.
 *
 * PHP_INT_MAX onceligi ile en sonda calisip her seyi eziyoruz.
 */
add_filter(
	'robots_txt',
	function () {
		return "# DENEME ORTAMI — arama motorlarina tamamen kapali\n"
			. "User-agent: *\n"
			. "Disallow: /\n";
	},
	PHP_INT_MAX
);

/**
 * 2c) Her yanita `X-Robots-Tag` bas.
 *
 * Meta etiketi yalnizca HTML sayfalarda gorunur; PDF, resim ve dogrudan
 * servis edilen dosyalar kapsam disi kalir. Bu baslik onlari da kapatir.
 * nginx tarafinda da ayni baslik var (snippets/staging-noindex.conf) —
 * ikisi bilerek ust uste; mu-plugin silinse bile nginx tarafi ayakta kalir.
 */
add_filter(
	'wp_headers',
	function ( $headers ) {
		$headers['X-Robots-Tag'] = 'noindex, nofollow, noarchive, nosnippet';
		return $headers;
	},
	PHP_INT_MAX
);

/**
 * 3) Panelde ve on yuzde gorunur uyari — yanlislikla staging'de calismayi onler.
 */
add_action(
	'admin_notices',
	function () {
		echo '<div class="notice notice-warning" style="border-left-color:#d63638">'
			. '<p><strong>DENEME ORTAMI (staging).</strong> Bu site canli degildir. '
			. 'Giden e-postalar kapali, arama motorlarina kapali. '
			. 'Yaptiginiz degisiklikler canli siteye YANSIMAZ.</p></div>';
	}
);

add_action(
	'wp_body_open',
	function () {
		if ( ! is_admin() ) {
			echo '<div style="position:fixed;bottom:0;left:0;right:0;z-index:99999;'
				. 'background:#d63638;color:#fff;text-align:center;padding:6px;'
				. 'font:13px/1.4 sans-serif">DENEME ORTAMI — canli site degildir</div>';
		}
	}
);
