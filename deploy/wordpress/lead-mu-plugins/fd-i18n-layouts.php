<?php
/**
 * Plugin Name: FD i18n Layouts (chestnyznak)
 * Description: Tema altbilgi duzenini gecerli dile gore secer.
 * Version:     2.0
 *
 * SORUN:
 * Qwery temasi altbilgiyi `footer_style` ayarindan secer; deger
 * `footer-custom-4105` gibi, icinde `cpt_layouts` post ID'si gomulu bir dizedir.
 * Ayar TEK bir degerdir — dil bilmez. Polylang duzenin cevirisini olustursa
 * bile tema hep Turkce olani basar.
 *
 * DENENIP OLMAYAN IKI YOL (tekrar denenmesin):
 *  1. `theme_mod_footer_style` filtresi — tema `get_theme_mod()` KULLANMIYOR,
 *     kendi deposundan okuyor. Filtre hic calismadi.
 *  2. `get_footer` kancasinda depoyu degistirmek — depo dize degil, ayarin TUM
 *     TANIM DIZISINI tutuyor (gercek deger `['val']` icinde) ve deger zaten
 *     daha once okunup `static` degiskende onbelleklenmis oluyor.
 *
 * CALISAN YOL:
 * Temanin `qwery_get_custom_footer_id()` fonksiyonu `function_exists()` ile
 * korunuyor. mu-plugin'ler temadan ONCE yuklendigi icin ayni adla burada
 * tanimlayinca tema kendi surumunu tanimlamiyor ve bizimki calisiyor.
 *
 * Ceviri yoksa temanin dondurecegi ID aynen doner — yani her zaman calisan
 * bir altbilgi kalir.
 *
 * YALNIZCA chestnyznak sitelerine kurulur.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Bir duzen (cpt_layouts) ID'sini gecerli dildeki cevirisiyle degistirir.
 * Ceviri yoksa girdi aynen doner.
 */
function fd_i18n_duzen_id( $id ) {
	$id = (int) $id;
	if ( $id <= 0 || ! function_exists( 'pll_current_language' ) || ! function_exists( 'pll_get_post' ) ) {
		return $id;
	}
	$dil = pll_current_language();
	if ( ! $dil ) {
		return $id;
	}
	$ceviri = pll_get_post( $id, $dil );
	if ( ! $ceviri || (int) $ceviri === $id || get_post_status( $ceviri ) !== 'publish' ) {
		return $id;
	}
	return (int) $ceviri;
}

/**
 * Temanin ayni adli fonksiyonunun yerine gecer (mu-plugin once yuklenir).
 * Temadaki mantik birebir korunur, yalnizca sonuna dil cevirisi eklenir.
 */
if ( ! function_exists( 'qwery_get_custom_footer_id' ) ) {
	function qwery_get_custom_footer_id() {
		static $layout_id = -1;
		if ( -1 !== $layout_id ) {
			return $layout_id;
		}
		if ( ! function_exists( 'qwery_get_theme_option' ) || ! function_exists( 'qwery_get_custom_layout_id' ) ) {
			return $layout_id = 0;
		}
		if ( qwery_get_theme_option( 'footer_type' ) !== 'custom' ) {
			return $layout_id = 0;
		}
		$layout_id = fd_i18n_duzen_id( qwery_get_custom_layout_id( 'footer' ) );
		return $layout_id;
	}
}

/* -------------------------------------------------------------------------
 * Tema widget alanindaki "extra_item" baglantilari (mobil menu paneli).
 * Bunlar tek bir Custom HTML widget'inin icinde ve o widget TUM dillerde
 * basiliyor (icindeki cz-* scriptleri her dilde calismali). Widget'i dile
 * bolmek scriptleri kirardi; bu yuzden yalnizca GORUNEN METINLERI
 * SUNUCU TARAFINDA cevriyoruz. JS enjeksiyonu kullanilmaz (CLAUDE.md 6f).
 * ---------------------------------------------------------------------- */
add_filter(
	'widget_custom_html_content',
	function ( $icerik ) {
		if ( ! function_exists( 'pll_current_language' ) ) {
			return $icerik;
		}
		$dil = pll_current_language();
		if ( ! $dil || 'tr' === $dil ) {
			return $icerik;
		}
		$sozluk = [
			'en' => [
				'Projeniz mi var?'                 => 'Have a project?',
				'Bizimle çalışmak ister misiniz?'  => 'Want to work with us?',
				'Bize Ulaşın'                      => 'Contact Us',
				'Çözümlerimizi inceleyin'          => 'Explore our solutions',
				'Mağazaya Git'                     => 'Go to Shop',
			],
			'ru' => [
				'Projeniz mi var?'                 => 'Есть проект?',
				'Bizimle çalışmak ister misiniz?'  => 'Хотите работать с нами?',
				'Bize Ulaşın'                      => 'Связаться с нами',
				'Çözümlerimizi inceleyin'          => 'Наши решения',
				'Mağazaya Git'                     => 'Перейти в магазин',
			],
		];
		if ( empty( $sozluk[ $dil ] ) ) {
			return $icerik;
		}
		return strtr( $icerik, $sozluk[ $dil ] );
	},
	20
);
