<?php
/**
 * Plugin Name: FD Site Haritasi Durum Kodu (fdartgallery)
 * Description: `/wp-sitemap*.xml` isteklerinin 404 yerine 200 donmesini saglar.
 * Version:     1.0
 *
 * SORUN (olculdu 02.09.2026, CANLI): `wp-sitemap.xml` govdesinde GECERLI XML
 * donuyordu ama HTTP durumu **404** idi. `robots.txt` bu adresi ilan ettigi
 * icin arama motorlari site haritasini reddeder.
 *
 * KOK SEBEP — cekirdek akisi:
 *   1. `/wp-sitemap.xml` rewrite'i `index.php?sitemap=index` uretir.
 *   2. `WP::query_posts()` bunu SIRADAN bir gonderi sorgusu gibi calistirir.
 *      Cogu sitede en son yazilar doner ve is burada biter.
 *   3. Bu sitede **yayinlanmis `post` sayisi SIFIR**. Sorgu bos donunce
 *      `WP::handle_404()` `$set_404 = true` yapar ve `status_header(404)` basar.
 *   4. `template_redirect` sirasinda `WP_Sitemaps::render_sitemaps()` calisir,
 *      indeksi basar ve `exit` eder — durum kodunu 200'e GERI CEKMEZ.
 *   => Govde dogru, durum kodu yanlis.
 *
 * Yani hata "site haritasi uretilmiyor" degil, "bos blog yuzunden istek 404
 * damgasi yiyor". Blogda tek bir yayinlanmis yazi olsa kendiliginden duzelirdi.
 *
 * COZUM: `pre_handle_404` — cekirdegin tam bu is icin sundugu kanca
 * ("Filters whether to short-circuit default header status handling").
 * `true` donunce `handle_404()` hicbir sey yapmadan cikar, durum 200 kalir.
 *
 * NEYI BOZMAZ:
 *   - Gercek 404'ler etkilenmez: kanca YALNIZCA `sitemap` / `sitemap-stylesheet`
 *     sorgu degiskeni dolu oldugunda devreye girer.
 *   - Bos bir alt harita (orn. hic yazi yokken `wp-sitemap-posts-post-1.xml`)
 *     yine 404 doner: cekirdek `render_sitemaps()` icinde `empty($url_list)`
 *     dalinda `set_404()` + `status_header(404)` calistirir ve o BIZDEN SONRA
 *     olur. Dogru davranis korunur.
 *   - Deneme ortami korunur: `sitemaps_enabled()` `blog_public` secenegine
 *     bakar; `fd-staging-guard.php` dev'de onu 0'a zorladigi icin cekirdek
 *     yine `set_404()` + 404 basar. Bu dosya o karari EZMEZ.
 *
 * Hem canli hem dev fdartgallery'ye kurulur.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_filter(
	'pre_handle_404',
	function ( $bypass, $query ) {
		// Baska bir eklenti zaten devraldiysa karisma.
		if ( $bypass ) {
			return $bypass;
		}

		if ( ! $query instanceof WP_Query ) {
			return $bypass;
		}

		$harita = $query->get( 'sitemap' );
		$stil   = $query->get( 'sitemap-stylesheet' );

		if ( '' === $harita && '' === $stil ) {
			return $bypass;
		}

		// handle_404() hicbir sey yapmadan cikar; durum 200 kalir.
		return true;
	},
	10,
	2
);
