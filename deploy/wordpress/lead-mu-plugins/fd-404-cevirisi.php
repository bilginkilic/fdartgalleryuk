<?php
/**
 * Plugin Name: FD 404 Cevirisi (chestnyznak)
 * Description: 404 sayfasinin metinlerini gecerli dile cevirir ve ise yarar
 *              baglantilar ekler.
 * Version:     1.0
 *
 * OLCULEN DURUM (02.09.2026, canli):
 * "404 sayfasi aktif edilmeli" denmisti; olculdu ve **zaten calisiyordu** —
 * `HTTP 404`, `<meta robots content="noindex, follow">`, tema sablonu
 * (`qwery/skins/default/templates/content-404.php`) menu + altbilgi + arama
 * kutusuyla geliyordu. Eksik olan METINDI:
 *
 *   baslik  : "Page not found - Chestny Znak"   (Yoast)
 *   h2      : "Oops..."                          (tema, `qwery` metin alani)
 *   metin   : "We're sorry, but something went wrong."
 *   dugme   : "Homepage"
 *
 * Ucu de Ingilizce ve dile gore degismiyordu. Polylang 404'te dili DOGRU
 * algiliyor (`lang=tr-TR/en-US/ru-RU` olculdu) ve `home_url()` zaten dile gore
 * `/en/` ve `/ru/` veriyor — yani cevrilmesi gereken yalnizca metinlerdi.
 *
 * NEDEN TEMA DOSYASI DEGISTIRILMEDI: `content-404.php` ana temada; degistirmek
 * tema guncellemesinde kaybolur. Cocuk temaya kopyalamak da olurdu ama o zaman
 * sablonun tamami catallanir ve ileride tema tarafindaki duzeltmeler gelmez.
 * Bunun yerine metinler `gettext` filtresiyle cevriliyor — sablon dokunulmadan
 * kaliyor.
 *
 * BAGLANTILAR: aciklama metni `wp_kses( ..., 'qwery_kses_content' )` icinden
 * geciyor ve o kural kumesi `<a href class style target>`, `<span>`, `<br>`
 * etiketlerine IZIN VERIYOR (temada `qwery_kses_allowed_html`). Bu yuzden
 * faydali baglantilar sablona dokunmadan aciklamanin icine konabiliyor.
 *
 * ADRESLER ELLE YAZILMAZ: `fd-i18n-layouts.php` icindeki `fd_i18n_yol_esleme()`
 * haritasindan cozulur. Elle yazilan bir adres dil dusurur (CLAUDE.md 6k).
 *
 * Metinler: `fd-404-metinleri.json` — kopya degisikligi kod degisikligi
 * gerektirmesin diye ayri dosyada.
 *
 * YALNIZCA chestnyznak sitelerine kurulur.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** JSON'daki metinleri okur (istek boyunca onbelleklenir). */
function fd_404_metin( $dil ) {
	static $tumu = null;
	if ( null === $tumu ) {
		$yol  = __DIR__ . '/fd-404-metinleri.json';
		$ham  = is_readable( $yol ) ? file_get_contents( $yol ) : '';
		$tumu = $ham ? (array) json_decode( $ham, true ) : [];
	}
	return isset( $tumu[ $dil ] ) ? (array) $tumu[ $dil ] : [];
}

/** Gecerli dil; Polylang yoksa 'tr'. */
function fd_404_dil() {
	if ( function_exists( 'pll_current_language' ) ) {
		$d = pll_current_language();
		if ( $d ) {
			return $d;
		}
	}
	return 'tr';
}

/**
 * Aciklamadaki yer tutuculari o dilin GERCEK adresiyle degistirir.
 * Harita `fd-i18n-layouts.php` icinde; orada olmayan bir dil icin Turkce
 * yollar aynen kullanilir (varsayilan dil koke oturuyor).
 */
function fd_404_baglantilari_coz( $metin, $dil ) {
	$tr = [
		'%cozumler%' => '/shop/',
		'%kod%'      => '/kod-sorgulama/',
		'%iletisim%' => '/iletisim/',
	];
	$harita = function_exists( 'fd_i18n_yol_esleme' ) ? fd_i18n_yol_esleme( $dil ) : [];
	$kok    = untrailingslashit( home_url( '/', 'https' ) );
	// home_url() Polylang ile dil onekini de tasir; kok olarak site kokunu isteriz.
	$kok = preg_replace( '#/(en|ru)$#', '', $kok );

	$degis = [];
	foreach ( $tr as $yer_tutucu => $tr_yol ) {
		$yol = $harita[ $tr_yol ] ?? $tr_yol;
		$degis[ $yer_tutucu ] = $kok . $yol;
	}
	return strtr( $metin, $degis );
}

/* -------------------------------------------------------------------------
 * 1) Tema metinleri. `gettext` filtresi 404 DISINDA da calisir, bu yuzden
 *    `is_404()` sarti ZORUNLU — yoksa "Homepage" gecen her yer degisir.
 * ---------------------------------------------------------------------- */
add_filter(
	'gettext',
	function ( $cevrilmis, $orijinal, $alan ) {
		if ( 'qwery' !== $alan || ! is_404() || is_admin() ) {
			return $cevrilmis;
		}
		$m = fd_404_metin( fd_404_dil() );
		if ( ! $m ) {
			return $cevrilmis;
		}
		switch ( $orijinal ) {
			case 'Oops...':
				return $m['altbaslik'] ?? $cevrilmis;
			case "We're sorry, but <br>something went wrong.":
				return fd_404_baglantilari_coz( (string) ( $m['aciklama'] ?? $cevrilmis ), fd_404_dil() );
			case 'Homepage':
				return $m['dugme'] ?? $cevrilmis;
		}
		return $cevrilmis;
	},
	10,
	3
);

/* -------------------------------------------------------------------------
 * 2) Sayfa basligi (Yoast). Varsayilani "Page not found %%sep%% %%sitename%%".
 * ---------------------------------------------------------------------- */
add_filter(
	'wpseo_title',
	function ( $baslik ) {
		if ( ! is_404() ) {
			return $baslik;
		}
		$m = fd_404_metin( fd_404_dil() );
		if ( empty( $m['baslik'] ) ) {
			return $baslik;
		}
		return $m['baslik'] . ' - ' . get_bloginfo( 'name' );
	},
	20
);

/* Yoast yoksa da baslik dogru olsun. */
add_filter(
	'document_title_parts',
	function ( $parcalar ) {
		if ( ! is_404() ) {
			return $parcalar;
		}
		$m = fd_404_metin( fd_404_dil() );
		if ( ! empty( $m['baslik'] ) ) {
			$parcalar['title'] = $m['baslik'];
		}
		return $parcalar;
	},
	20
);

/* -------------------------------------------------------------------------
 * 3) Eklenen baglantilarin gorunumu. Yalnizca 404'te basilir.
 * ---------------------------------------------------------------------- */
add_action(
	'wp_head',
	function () {
		if ( ! is_404() ) {
			return;
		}
		echo "<style>\n"
			. ".post_item_404 .fd-404-links{display:inline-flex;flex-wrap:wrap;gap:.5em 1em;margin-top:1em;justify-content:center}\n"
			. ".post_item_404 .fd-404-links a{text-decoration:underline;text-underline-offset:.25em}\n"
			. "</style>\n";
	},
	20
);
