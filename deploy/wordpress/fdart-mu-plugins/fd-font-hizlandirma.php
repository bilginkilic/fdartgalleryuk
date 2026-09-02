<?php
/**
 * Plugin Name: FD Font Hizlandirma (fdartgallery)
 * Description: Google Fonts isteklerini tek istekte birlestirir, kullanilmayan
 *              agirliklari atar ve font kaynaklarina preconnect ekler.
 * Version:     1.0
 *
 * OLCULEN DURUM (02.09.2026, dev ana sayfasi):
 *   - fonts.googleapis.com'a **5 AYRI** stylesheet istegi:
 *     Roboto, Roboto Slab, Outfit, Urbanist, Poppins — her biri
 *     `100,100italic,...,900,900italic` yani **18 agirlik**.
 *   - Sayfada `preconnect` / `dns-prefetch` **HIC YOK**.
 *
 * NEDEN ONEMLI: fontlar IKI ayri harici kaynaktan gelir —
 * CSS `fonts.googleapis.com`, dosyalar `fonts.gstatic.com`. Ikisi de yeni
 * DNS + TCP + TLS demek. Turkiye'den Londra RTT ~60 ms; onceden baglanti
 * kurulmadiginda ilk font metni ekrana gelene kadar birkac tur gidis-donus
 * bekleniyor (CLAUDE.md 6j'deki HTTP/2 olcumunun ayni mantigi).
 *
 * GERCEK KULLANIM (tum `_elementor_data` tarandi):
 *   aile   : Outfit 6704, Poppins 61, Source Sans Pro 31, Roboto 14
 *   agirlik: 500 -> 4856, 400 -> 1745, 300 -> 8, 600 -> 2
 *   italik : sitede uretilen CSS'te `font-style: italic` **0 kez** geciyor.
 *
 * NE YAPILIYOR
 *   1. `fonts.googleapis.com`+`fonts.gstatic.com` icin `preconnect`
 *      (gstatic `crossorigin` SART — font dosyalari CORS ile inar; bayrak
 *      olmazsa tarayici ikinci bir baglanti acar ve preconnect BOSA GIDER).
 *   2. Kuyruktaki tum googleapis stylesheet'leri TEK istege birlestirilir
 *      (v1 API `family=A:...|B:...` destekler).
 *   3. Agirlik listesi 18 -> 9'a inerken AILE HIC ELENMEZ. Elementor bir
 *      aileyi o sayfada gercekten kullandigi icin basar; aile atmak yazi
 *      tipini degistirir, agirlik atmak yalnizca indirilmeyen bir yuz birakir.
 *
 * NEDEN AILE ELENMIYOR: "Roboto ve Roboto Slab Elementor'un varsayilan kit
 * fontlari, atalim" denebilirdi. Ama kit'teki global tipografi sayfada
 * KULLANILIYOR (primary/secondary/text/accent); atilirsa basliklar sistem
 * fontuna duser. Aile sadelestirmesi bir TASARIM karari, kod karari degil.
 *
 * Hem canli hem dev fdartgallery'ye kurulur.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Istenecek agirliklar. Olculen kullanim 300/400/500/600; 700-900 tema ve
 * eklenti CSS'lerinde geciyor. Italikler DUSURULDU (sitede kullanilmiyor);
 * yalnizca govde metninde `<em>` cikarsa diye 400italic/700italic birakildi.
 *
 * `fd_font_agirliklar` filtresiyle degistirilebilir.
 */
function fd_font_agirliklar() {
	return (array) apply_filters(
		'fd_font_agirliklar',
		[ '300', '400', '400italic', '500', '600', '700', '700italic', '800', '900' ]
	);
}

/* 1) Font kaynaklarina onceden baglan. */
add_action(
	'wp_head',
	function () {
		if ( is_admin() ) {
			return;
		}
		echo "<link rel='preconnect' href='https://fonts.googleapis.com'>\n";
		echo "<link rel='preconnect' href='https://fonts.gstatic.com' crossorigin>\n";
		// Turnstile geciktirilmis yukleniyor; tam baglanti yerine yalnizca DNS.
		echo "<link rel='dns-prefetch' href='//challenges.cloudflare.com'>\n";
	},
	1
);

/**
 * 2-3) Kuyruktaki googleapis stylesheet'lerini tek istege indirger.
 *
 * `wp_print_styles` uzerinde calisir: bu noktada butun enqueue'lar bitmistir
 * ama hicbir sey basilmamistir.
 */
add_action(
	'wp_print_styles',
	function () {
		if ( is_admin() ) {
			return;
		}
		global $wp_styles;
		if ( empty( $wp_styles ) || empty( $wp_styles->queue ) ) {
			return;
		}

		$agirlik  = implode( ',', fd_font_agirliklar() );
		$aileler  = [];
		$ekler    = [];   // display, subset gibi ortak parametreler
		$dusenler = [];

		foreach ( $wp_styles->queue as $h ) {
			$kayit = $wp_styles->registered[ $h ] ?? null;
			$src   = $kayit && isset( $kayit->src ) ? (string) $kayit->src : '';
			if ( '' === $src || false === strpos( $src, 'fonts.googleapis.com' ) ) {
				continue;
			}
			$sorgu = wp_parse_url( html_entity_decode( $src ), PHP_URL_QUERY );
			if ( ! $sorgu ) {
				continue;
			}
			parse_str( $sorgu, $p );
			if ( empty( $p['family'] ) ) {
				continue;
			}
			// `family` v1'de `Aile:agirliklar` ve `|` ile coklu olabilir.
			foreach ( explode( '|', $p['family'] ) as $parca ) {
				$ad = explode( ':', $parca, 2 )[0];
				if ( '' !== trim( $ad ) ) {
					$aileler[ $ad ] = true;
				}
			}
			foreach ( [ 'display', 'subset' ] as $k ) {
				if ( ! empty( $p[ $k ] ) ) {
					$ekler[ $k ] = $p[ $k ];
				}
			}
			$dusenler[] = $h;
		}

		// Tek istek zaten tek istekse dokunma (yine de agirlik kirpmaya deger).
		if ( count( $dusenler ) < 1 || ! $aileler ) {
			return;
		}

		foreach ( $dusenler as $h ) {
			wp_dequeue_style( $h );
		}

		$family = [];
		foreach ( array_keys( $aileler ) as $ad ) {
			$family[] = str_replace( ' ', '+', $ad ) . ':' . $agirlik;
		}
		$url = 'https://fonts.googleapis.com/css?family=' . implode( '|', $family )
			. '&display=' . rawurlencode( $ekler['display'] ?? 'swap' );
		if ( ! empty( $ekler['subset'] ) ) {
			$url .= '&subset=' . rawurlencode( $ekler['subset'] );
		}

		wp_register_style( 'fd-google-fonts', $url, [], null );
		wp_enqueue_style( 'fd-google-fonts' );
		// Kuyruga sonradan eklendigi icin bu turda basilmasi elle tetiklenir.
		$wp_styles->do_items( [ 'fd-google-fonts' ] );
	},
	1
);
