<?php
/**
 * Plugin Name: FD Cerez Bildirimi Duzeltmesi
 * Description: XStore'un cerez bildirimini onbellekli sayfalarda da kapatir ve
 *              onayin omrunu 3 gunden 1 yila cikarir.
 * Version:     1.0
 *
 * SORUN: XStore bildirimi SUNUCUDA karar veriyor —
 * `xstore/framework/features/gdpr.php:39`
 *   if ( isset($_COOKIE['etheme_cookies']) && $_COOKIE['etheme_cookies'] == 'false' ) return;
 * Sayfa HTML'i hem nginx `fastcgi_cache` hem Cloudflare APO tarafindan
 * onbelleklendigi icin, onbellege giren kopya HER ZAMAN bildirimi iceriyor.
 * Ziyaretcinin cerezi olsa da olmasa da ayni HTML donuyor; temanin JS'i yalnizca
 * TIKLAMADA kaldiriyor, acilista cerezi KONTROL ETMIYOR. Sonuc: kullanici "Tamam"
 * dese de bildirim her sayfada geri geliyor.
 *
 * COZUM: karari tarayiciya tasi. Isaret <html>'e `wp_head`'in en basinda
 * konuyor, CSS de ayni yerde — yani bildirim hic boyanmiyor, goz kirpmasi yok.
 *
 * IKINCI SEBEP: onay cerezinin omru `et_cookies_notice_cache` = **3 GUN**
 * (`etTheme.setCookie` gun cinsinden). Onbellek olmasa bile ucuncu gunde geri
 * gelirdi. Filtre ile 365 gune cikariliyor — tema ayarina dokunulmuyor.
 *
 * Kaldirmak icin: bu dosyayi silin. Veritabaninda hicbir degisiklik yok.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// 1) Onay cerezinin omru: 3 gun -> 365 gun. Tema kendi filtresini 10'da
//    ekliyor, biz 20'de uzerine yaziyoruz.
add_filter(
	'etheme_et_js_config',
	function ( $config ) {
		if ( isset( $config['etCookies'] ) && is_array( $config['etCookies'] ) ) {
			$config['etCookies']['cache_time'] = 365;
		}
		return $config;
	},
	20
);

// 2) Cerezi olan ziyaretcide bildirimi hic gosterme (onbellekli HTML'de de).
add_action(
	'wp_head',
	function () {
		if ( is_admin() || is_customize_preview() ) {
			return;
		}
		?>
<style id="fd-cerez-stil">html.fd-cerez-kabul .et-cookies-popup-wrapper{display:none!important}</style>
<script id="fd-cerez-js">(function(){try{if(/(?:^|;\s*)etheme_cookies=false(?:;|$)/.test(document.cookie)){document.documentElement.className+=" fd-cerez-kabul";}}catch(e){}})();</script>
		<?php
	},
	1
);
