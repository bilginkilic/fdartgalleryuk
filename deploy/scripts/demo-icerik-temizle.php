<?php
/**
 * chestnyznak: Qwery tema DEMO icerigini yayindan kaldirir ve aramaya kapatir.
 *
 * SORUN (Ali, 02.09.2026): `site:chestnyznak.com.tr` aramasi tema demosunu
 * gosteriyor. Olculdu — yayindaki 191 sayfanin yalnizca **27'si gercek**;
 * ayrica 98 cpt_portfolio, 50 cpt_services, 33 cpt_testimonials, 11 cpt_team,
 * 15 tribe_events ve 83 cpt_layouts adresi site haritasinda.
 *
 * KOK SEBEP: Yoast'ta bu tiplerin HICBIRINDE `noindex` yok
 * (`noindex-cpt_portfolio` vb. hepsi `false`). Sayfalari kapatmak tek basina
 * yetmez; tip bazinda noindex sart, yoksa yeni demo icerik ayni yerden geri
 * doner.
 *
 * NE YAPAR
 *   1. GERCEK icerik kumesini **cozer** (asagi bkz.) ve disinda kalan
 *      yayindaki `page` kayitlarini **taslaga** ceker.
 *   2. Demo icerik tiplerini (portfolio/services/team/testimonials/events)
 *      taslaga ceker.
 *   3. Yoast'ta demo tiplerini ve yazar arsivlerini `noindex` yapar.
 *   4. Degistirdigi her kimligi `demo-temizlik-geri-alma.json` dosyasina yazar.
 *
 * NE YAPMAZ — ve NEDEN
 *   - **`cpt_layouts` TASLAGA CEKILMEZ.** Sitenin KULLANILAN baslik ve
 *     altbilgisi o tipte duruyor (`Header Main`, `Footer Default`); taslaga
 *     cekmek siteyi kirar. Onlara yalnizca `noindex` uygulanir.
 *   - **`cerez-politikasi` DOKUNULMAZ** (kullanici karari 02.09.2026): yasal
 *     sayfa, cerez bildiriminden baglanti aliyor.
 *   - `product`, `post`, `category` DOKUNULMAZ — gercek icerik.
 *
 * SCRIPTE POST ID YAZILMAZ (CLAUDE.md 6k). Dev ve canlida ayni degiller.
 * Gercek kume su kaynaklardan cozulur:
 *   - `page_on_front`, `page_for_posts`
 *   - WooCommerce sayfalari (`wc_get_page_id`)
 *   - **YALNIZCA bir tema konumuna ATANMIS** menulerdeki hedefler
 *     (temanin atanmamis demo menuleri — Main Menu, Demo Menu, Developer`s
 *      menu, Footer Menu 2/3, Simple Menu — 179 demo sayfaya link veriyor;
 *      onlar olcut olarak KULLANILAMAZ)
 *   - slug ile: acilis ve iframe sayfalari + korunacaklar
 *   - yukaridakilerin butun Polylang cevirileri
 *
 * Calistirma (DAIMA once dry, DAIMA once dev):
 *   wp --user=<admin> --path=<kok> eval-file demo-icerik-temizle.php dry
 *   wp --user=<admin> --path=<kok> eval-file demo-icerik-temizle.php
 *
 * Geri alma:
 *   wp --user=<admin> --path=<kok> eval-file demo-icerik-temizle.php geri-al
 */

$mod = (string) ( $args[0] ?? '' );
$dry = ( 'dry' === $mod );
$geri = ( 'geri-al' === $mod );

$kayit_dosyasi = WP_CONTENT_DIR . '/demo-temizlik-geri-alma.json';

/* Bu tipler taslaga cekilir. cpt_layouts BILEREK YOK — bkz. basliktaki not. */
$DEMO_TIPLER = [ 'cpt_portfolio', 'cpt_services', 'cpt_team', 'cpt_testimonials', 'tribe_events' ];

/* Yalnizca aramaya kapatilacak tipler (yayinda kalirlar). */
$NOINDEX_TIPLER = [ 'cpt_layouts', 'cpt_portfolio', 'cpt_services', 'cpt_team',
	'cpt_testimonials', 'tribe_events', 'elementor_library' ];
$NOINDEX_TAX = [ 'cpt_layouts_group', 'cpt_portfolio_group', 'cpt_services_group',
	'cpt_team_group', 'cpt_testimonials_group', 'tribe_events_cat' ];

/* Menude olmasa da KORUNACAK sayfalar (slug ile). */
$KORUNACAK_SLUG = [
	'cerez-politikasi',        // yasal — kullanici karari
	'site-iletisim-formu',     // sag alt kosedeki sohbet iframe'i
	'contact-form',            // ^ EN kopyasi
	'forma-obratnoy-svyazi',   // ^ RU kopyasi
	'urun-talep-formu',        // reklam acilis sayfasi
	'lp-iletisim-formu',       // reklam acilis sayfasi
	'datamatrix-kod-etiket',   // reklam acilis sayfasi
];

/* ---------------------------------------------------------------- GERI AL */
if ( $geri ) {
	if ( ! is_readable( $kayit_dosyasi ) ) {
		echo "geri alma kaydi yok: $kayit_dosyasi\n";
		return;
	}
	$k = json_decode( (string) file_get_contents( $kayit_dosyasi ), true );
	$n = 0;
	foreach ( (array) ( $k['taslaga_cekilen'] ?? [] ) as $id ) {
		wp_update_post( [ 'ID' => (int) $id, 'post_status' => 'publish' ] );
		$n++;
	}
	if ( ! empty( $k['yoast_onceki'] ) ) {
		$y = (array) get_option( 'wpseo_titles' );
		foreach ( $k['yoast_onceki'] as $anahtar => $deger ) { $y[ $anahtar ] = $deger; }
		update_option( 'wpseo_titles', $y );
	}
	printf( "geri alindi: %d kayit yayina dondu, Yoast ayarlari eski haline getirildi\n", $n );
	return;
}

/* ------------------------------------------------- 1) GERCEK KUMEYI COZ */
$gercek = [];
$ekle = function ( $id, $neden ) use ( &$gercek ) {
	$id = (int) $id;
	if ( $id > 0 ) { $gercek[ $id ] = $neden; }
};

$ekle( get_option( 'page_on_front' ), 'on sayfa' );
$ekle( get_option( 'page_for_posts' ), 'blog arsivi' );
foreach ( [ 'shop', 'cart', 'checkout', 'myaccount' ] as $k ) {
	if ( function_exists( 'wc_get_page_id' ) ) { $ekle( wc_get_page_id( $k ), "woo:$k" ); }
}

/* ATANMIS menu konumlari — atanmamis demo menuler olcut DEGILDIR. */
$atanmis = array_unique( array_map( 'intval', array_values( array_filter( (array) get_nav_menu_locations() ) ) ) );
foreach ( $atanmis as $mid ) {
	foreach ( (array) wp_get_nav_menu_items( $mid ) as $oge ) {
		if ( 'post_type' === $oge->type ) {
			$ekle( $oge->object_id, 'menu' );
		} elseif ( 'custom' === $oge->type ) {
			$yol = trim( (string) wp_make_link_relative( $oge->url ), '/' );
			$yol = preg_replace( '#^(en|ru)/#', '', $yol );
			if ( '' !== $yol ) {
				$p = get_page_by_path( $yol );
				if ( $p ) { $ekle( $p->ID, 'menu' ); }
			}
		}
	}
}
foreach ( $KORUNACAK_SLUG as $slug ) {
	$p = get_page_by_path( $slug );
	if ( $p ) { $ekle( $p->ID, 'korunacak:' . $slug ); }
}
/* Polylang cevirileri */
if ( function_exists( 'pll_get_post' ) && function_exists( 'pll_languages_list' ) ) {
	foreach ( array_keys( $gercek ) as $id ) {
		foreach ( (array) pll_languages_list() as $d ) {
			$c = pll_get_post( $id, $d );
			if ( $c ) { $ekle( $c, 'ceviri' ); }
		}
	}
}

/* --- GUVENLIK FRENI: gercek kume mantikli mi? --- */
$on = (int) get_option( 'page_on_front' );
if ( ! $on || ! isset( $gercek[ $on ] ) ) {
	echo "DURDURULDU: on sayfa cozulemedi. Hicbir sey degistirilmedi.\n";
	return;
}
if ( count( $gercek ) < 15 ) {
	printf( "DURDURULDU: gercek kume yalnizca %d kayit — cok az, bir sey ters gitmis. Degisiklik yok.\n", count( $gercek ) );
	return;
}
printf( "gercek icerik kumesi: %d kayit (on sayfa #%d dogrulandi)\n", count( $gercek ), $on );

/* ------------------------------------------- 2) TASLAGA CEKILECEKLERI BUL */
$taslaga = [];
foreach ( (array) get_posts( [ 'post_type' => 'page', 'post_status' => 'publish', 'numberposts' => -1 ] ) as $p ) {
	if ( ! isset( $gercek[ $p->ID ] ) ) { $taslaga[] = $p; }
}
$sayfa_adedi = count( $taslaga );
foreach ( $DEMO_TIPLER as $t ) {
	foreach ( (array) get_posts( [ 'post_type' => $t, 'post_status' => 'publish', 'numberposts' => -1 ] ) as $p ) {
		$taslaga[] = $p;
	}
}

printf( "taslaga cekilecek: %d sayfa + %d demo icerik = %d kayit\n",
	$sayfa_adedi, count( $taslaga ) - $sayfa_adedi, count( $taslaga ) );

if ( $dry ) {
	echo "\n--- ilk 15 sayfa ---\n";
	$i = 0;
	foreach ( $taslaga as $p ) {
		if ( 'page' !== $p->post_type || $i++ >= 15 ) { continue; }
		printf( "  #%-6d %-44s %s\n", $p->ID, $p->post_name, mb_substr( $p->post_title, 0, 34 ) );
	}
	echo "\n--- tip dagilimi ---\n";
	$d = [];
	foreach ( $taslaga as $p ) { $d[ $p->post_type ] = ( $d[ $p->post_type ] ?? 0 ) + 1; }
	foreach ( $d as $t => $n ) { printf( "  %-20s %3d\n", $t, $n ); }
	echo "\n--- YAYINDA KALACAK sayfalar ---\n";
	foreach ( $gercek as $id => $neden ) {
		$p = get_post( $id );
		if ( ! $p || 'publish' !== $p->post_status ) { continue; }
		printf( "  #%-6d %-38s [%s]\n", $id, $p->post_name, $neden );
	}
	echo "\nDRY-RUN — hicbir sey degistirilmedi.\n";
	return;
}

/* ------------------------------------------------------------ 3) UYGULA */
$kayit = [ 'tarih' => current_time( 'mysql' ), 'taslaga_cekilen' => [], 'yoast_onceki' => [] ];
foreach ( $taslaga as $p ) {
	$r = wp_update_post( [ 'ID' => $p->ID, 'post_status' => 'draft' ], true );
	if ( is_wp_error( $r ) ) {
		printf( "  HATA #%d: %s\n", $p->ID, $r->get_error_message() );
		continue;
	}
	$kayit['taslaga_cekilen'][] = (int) $p->ID;
}
printf( "taslaga cekildi: %d kayit\n", count( $kayit['taslaga_cekilen'] ) );

/* ------------------------------------------------- 4) Yoast tip noindex */
$y = (array) get_option( 'wpseo_titles' );
$degisen = 0;
$uygula = function ( $anahtar ) use ( &$y, &$kayit, &$degisen ) {
	if ( ! array_key_exists( $anahtar, $y ) || true !== $y[ $anahtar ] ) {
		$kayit['yoast_onceki'][ $anahtar ] = $y[ $anahtar ] ?? false;
		$y[ $anahtar ] = true;
		$degisen++;
	}
};
foreach ( $NOINDEX_TIPLER as $t ) {
	$uygula( 'noindex-' . $t );
	$uygula( 'noindex-ptarchive-' . $t );
}
foreach ( $NOINDEX_TAX as $t ) { $uygula( 'noindex-tax-' . $t ); }
/* Yazar arsivleri: tek yazarli kurumsal sitede kopya icerik uretir. */
$uygula( 'noindex-author-wpseo' );
update_option( 'wpseo_titles', $y );
printf( "Yoast: %d noindex ayari acildi\n", $degisen );

file_put_contents( $kayit_dosyasi, wp_json_encode( $kayit, JSON_PRETTY_PRINT ) );
printf( "geri alma kaydi: %s\n", $kayit_dosyasi );

/* Site haritasi ve onbellek yenilensin. */
if ( class_exists( 'WPSEO_Sitemaps_Cache' ) ) { WPSEO_Sitemaps_Cache::clear(); }
flush_rewrite_rules();
wp_cache_flush();
echo "TAMAM\n";
