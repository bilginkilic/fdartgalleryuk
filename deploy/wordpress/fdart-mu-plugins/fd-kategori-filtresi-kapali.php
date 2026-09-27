<?php
/**
 * Plugin Name: FD — Kategori filtresi varsayilan kapali
 * Description: Magaza/kategori sayfalarindaki yatay filtre cubugunda yalnizca
 *              "Kategori" panelini kapali baslatir. Durum ve Fiyat acik kalir.
 *
 * NEDEN BOYLE: XStore'da panel BAZINDA acik/kapali ayari YOK. Widget'in
 * `widgets_toggle_action_opened` ayari uc paneli birden kapatir, `enabled_default`
 * ise tum cubugu gizler — ve masaustunde acma dugmesi display:none oldugu icin
 * filtreler tamamen erisilemez hale gelir. Bu yuzden temanin KENDI toggle'i
 * (.widget-title tiklamasi) programatik olarak tetikleniyor; boylece acma/kapama
 * davranisi sonrasinda birebir normal calisir.
 *
 * Paneller AJAX ile geliyor, bu yuzden MutationObserver gerekiyor.
 *
 * GUVENLIK AGI: script 8 saniyede is goremezse icerik yine de gosterilir.
 * Bozuk bir script filtreyi kalici olarak gizlememeli.
 */

defined( 'ABSPATH' ) || exit;

add_action( 'wp_head', function () {
	if ( ! function_exists( 'is_shop' ) ) { return; }
	if ( ! is_shop() && ! is_product_category() && ! is_product_tag() ) { return; }
	?>
<style id="fd-kategori-kapali-stil">
/* Biz kapatana kadar liste gorunmesin — acilip hemen kapanma titremesi olmasin.
   .fd-kat-hazir her durumda eklenir (basarida da, zaman asiminda da). */
html:not(.fd-kat-hazir) .etheme_widget_cats_filter > *:not(.widget-title) { opacity: 0; }
</style>
<script id="fd-kategori-kapali-js">
(function () {
	var bitti = false, gozlemci = null;

	function hazir() {
		bitti = true;
		document.documentElement.classList.add('fd-kat-hazir');
		if (gozlemci) { gozlemci.disconnect(); gozlemci = null; }
	}

	function dene() {
		if (bitti) { return; }
		var w = document.querySelector('.etheme_widget_cats_filter.widget-has-toggle');
		if (!w) { return; }                       // AJAX daha gelmemis
		var baslik = w.querySelector('.widget-title');
		if (!baslik) { return; }
		if (!w.classList.contains('widget-toggled')) { baslik.click(); }
		hazir();
	}

	function basla() {
		dene();
		if (bitti) { return; }
		gozlemci = new MutationObserver(dene);
		gozlemci.observe(document.body, { childList: true, subtree: true });
		setTimeout(function () { if (!bitti) { hazir(); } }, 8000);   // guvenlik agi
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', basla);
	} else {
		basla();
	}
})();
</script>
	<?php
}, 1 );
