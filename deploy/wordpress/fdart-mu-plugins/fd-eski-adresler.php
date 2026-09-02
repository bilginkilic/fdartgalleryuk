<?php
/**
 * Plugin Name: FD Eski Adresler (fdartgallery)
 * Description: Degistirilen slug'lar icin kalici (301) yonlendirme tablosu.
 * Version:     1.0
 *
 * NEDEN VAR: WordPress cekirdegi slug degisiminde eski adresi `_wp_old_slug`
 * meta'sina yazar ve `wp_old_slug_redirect()` ile 301 doner — AMA yalnizca
 * HIYERARSIK OLMAYAN tipler icin. `wp_check_for_changed_slugs()` icinde:
 *
 *     if ( ... || is_post_type_hierarchical( $post->post_type ) ) return;
 *
 * `page` hiyerarsiktir, dolayisiyla SAYFA slug'i degistirildiginde cekirdek
 * hicbir yonlendirme kurmaz — eski adres duz 404 olur. 02.09.2026'da sayfa #2
 * `sample-page` → `ozel-siparis` yapilirken olculdu: `_wp_old_slug` BOS kaldi.
 *
 * Bu dosya o bosluğu kapatir. Blogun Ingilizce demo slug'lari duzeltilirken
 * de ayni tabloya satir eklenir (bkz. deploy/blog/BACKLOG.md).
 *
 * TASARIM: yonlendirme YALNIZCA istek 404'e dustugunde calisir. Boylece tablo
 * hicbir zaman gercek bir sayfayi golgeleyemez; hedef adres bir gun gercek
 * icerige donerse kural kendiliginden devre disi kalir.
 *
 * Hem canli hem dev fdartgallery'ye kurulur.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Eski yol => yeni yol. Anahtar bas/son egik cizgisiz, kucuk harf.
 *
 * @return array<string,string>
 */
function fd_eski_adres_haritasi() {
	return (array) apply_filters(
		'fd_eski_adres_haritasi',
		[
			// 02.09.2026 — WordPress kurulumundan kalma "Sample Page" slug'i.
			'sample-page' => '/ozel-siparis/',
		]
	);
}

add_action(
	'template_redirect',
	function () {
		if ( ! is_404() || is_admin() ) {
			return;
		}

		$yol = wp_parse_url( $_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH );
		$yol = strtolower( trim( (string) $yol, '/' ) );
		if ( '' === $yol ) {
			return;
		}

		$harita = fd_eski_adres_haritasi();
		if ( ! isset( $harita[ $yol ] ) ) {
			return;
		}

		$hedef = home_url( $harita[ $yol ] );

		// Sorgu dizesi korunur: reklam/bulten baglantilari utm'ini kaybetmesin.
		$sorgu = wp_parse_url( $_SERVER['REQUEST_URI'] ?? '', PHP_URL_QUERY );
		if ( $sorgu ) {
			$hedef .= ( false === strpos( $hedef, '?' ) ? '?' : '&' ) . $sorgu;
		}

		wp_safe_redirect( $hedef, 301 );
		exit;
	},
	1
);
