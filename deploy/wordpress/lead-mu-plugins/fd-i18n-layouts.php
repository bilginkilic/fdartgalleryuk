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
 * bolmek scriptleri kirardi; bu yuzden yalnizca GORUNEN METINLERI ve
 * IC BAGLANTILARI SUNUCU TARAFINDA cevriyoruz. JS enjeksiyonu kullanilmaz
 * (CLAUDE.md 6f).
 *
 * BAGLANTILAR NEDEN ONEMLI: Polylang dili URL onekinden okur. `/en/` icindeki
 * bir baglanti `/iletisim/` derse ziyaretci Turkceye duser — kullanicinin
 * "dil degistirince gezerken Turkceye donuyor" sikayetinin kaynaklarindan
 * biri buydu. Widget hem duz `<a href>` hem de icindeki cz-* scriptlerinde
 * adres tasidigi icin ikisi de ayni strtr ile cevrilir.
 * ---------------------------------------------------------------------- */

/** Dile gore Turkce yol -> cevrilmis yol eslemesi. */
function fd_i18n_yol_esleme( $dil ) {
	$harita = [
		'en' => [
			'/hakkimizda/'    => '/en/about-us/',
			'/iletisim/'      => '/en/contact/',
			'/kod-sorgulama/' => '/en/code-lookup/',
			'/blog-standard/' => '/en/news/',
			'/shop/'          => '/en/solutions/',
			// Sag alt kosedeki sohbet dugmesinin IFRAME kaynagi.
			'/site-iletisim-formu/' => '/en/contact-form/',
		],
		'ru' => [
			'/hakkimizda/'    => '/ru/o-nas/',
			'/iletisim/'      => '/ru/kontakty/',
			'/kod-sorgulama/' => '/ru/proverka-koda/',
			'/blog-standard/' => '/ru/novosti/',
			'/shop/'          => '/ru/uslugi/',
			'/site-iletisim-formu/' => '/ru/forma-obratnoy-svyazi/',
		],
	];
	return $harita[ $dil ] ?? [];
}

/**
 * Widget metin sozlugu. Ayni dizindeki JSON dosyasindan okunur; boylece yeni
 * bir metin eklemek icin kod degistirmek gerekmez.
 * Kaynak: deploy/wordpress/lead-mu-plugins/fd-i18n-widget-sozluk.json
 */
function fd_i18n_widget_sozluk( $dil ) {
	static $tumu = null;
	if ( null === $tumu ) {
		$yol  = __DIR__ . '/fd-i18n-widget-sozluk.json';
		$ham  = is_readable( $yol ) ? file_get_contents( $yol ) : '';
		$tumu = $ham ? (array) json_decode( $ham, true ) : [];
	}
	return isset( $tumu[ $dil ] ) ? (array) $tumu[ $dil ] : [];
}

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
		$sozluk = [ $dil => fd_i18n_widget_sozluk( $dil ) ];
		if ( empty( $sozluk[ $dil ] ) ) {
			return $icerik;
		}

		/* Ic baglantilar: hem mutlak (https://host/yol/) hem koke gore (/yol/). */
		$kok = untrailingslashit( home_url() );
		foreach ( fd_i18n_yol_esleme( $dil ) as $eski => $yeni ) {
			$sozluk[ $dil ][ $kok . $eski ] = $kok . $yeni;
			$sozluk[ $dil ][ "'" . $eski . "'" ] = "'" . $yeni . "'";
		}

		return strtr( $icerik, $sozluk[ $dil ] );
	},
	20
);

/* -------------------------------------------------------------------------
 * Kenar cubugu widget BASLIKLARI.
 *
 * Polylang'de her widget'a dil atanabilir, ama o zaman ayni widget'i uc kez
 * kurmak ve uc yerde bakim yapmak gerekir. Basliklar tek satirlik metinler
 * oldugu icin ayni sozlukten sunucu tarafinda ceviriyoruz.
 * ---------------------------------------------------------------------- */
add_filter(
	'widget_title',
	function ( $baslik ) {
		if ( ! is_string( $baslik ) || '' === $baslik || ! function_exists( 'pll_current_language' ) ) {
			return $baslik;
		}
		$dil = pll_current_language();
		if ( ! $dil || 'tr' === $dil ) {
			return $baslik;
		}
		$sozluk = fd_i18n_widget_sozluk( $dil );
		return isset( $sozluk[ $baslik ] ) ? $sozluk[ $baslik ] : $baslik;
	},
	20
);
