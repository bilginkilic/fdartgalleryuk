<?php
/**
 * "Cozumlerimiz" (WooCommerce magaza) sayfasinin EN/RU karsiliklarini kurar.
 *
 * SORUN: menudeki "Solutions" / "Услуги" ogesi `/shop/` adresine gidiyordu.
 * `/shop/` Turkce bir sayfa (magaza sayfasi) oldugu icin Polylang dili TR'ye
 * cekiyor ve ziyaretci dilinden dusuyordu (CLAUDE.md 6k).
 *
 * NEDEN KOPYA SAYFA: WooCommerce magaza sayfasini `woocommerce_shop_page_id`
 * ile TEK bir kayittan okur; dile gore magaza sayfasi ancak Polylang'in
 * UCRETLI WooCommerce eklentisiyle mumkundur. Ucretsiz surumde yapilabilecek
 * en iyi sey, ayni urun listesini `[products]` kisa koduyla basan cevrilmis
 * bir sayfa acmaktir.
 *
 * URUNLER NEDEN LISTELENIR: `product` icerik tipi Polylang ayarlarinda
 * CEVRILEN TIPLER ARASINDA DEGIL (`post_types` bos). Bu yuzden urunler dile
 * bakilmaksizin her dilde gorunur — olculdu: tr/en/ru icin de 5 urun.
 * Urun ADLARI Turkce kalir; bunu cevirmek ucretli eklenti ister.
 *
 * Calistirma (once dry):
 *   wp --user=<admin> --path=<kok> eval-file 14-magaza-sayfasi.php dry
 */

$dry = ( ( $args[0] ?? '' ) === 'dry' );
$P   = json_decode( file_get_contents( '/tmp/magaza-sayfasi.json' ), true );
if ( ! $P || empty( $P['en'] ) ) {
	echo "HATA: yuk bozuk\n";
	return;
}
if ( ! function_exists( 'pll_set_post_language' ) ) {
	echo "HATA: Polylang yok\n";
	return;
}
kses_remove_filters();

/* Menu ID'leri de ELLE YAZILMAZ — Polylang dil basina ayri menu konumu acar
   (`menu_main___en`). Konumdan cozulur. */
$MENU = [];
$konumlar = get_nav_menu_locations();
foreach ( [ 'en', 'ru' ] as $dil ) {
	foreach ( [ 'menu_main___' . $dil, 'primary___' . $dil, 'menu_mobile___' . $dil ] as $konum ) {
		if ( ! empty( $konumlar[ $konum ] ) ) {
			$MENU[ $dil ] = (int) $konumlar[ $konum ];
			break;
		}
	}
}
printf( "cozulen menuler: %s\n", wp_json_encode( $MENU ) );

$magaza_id = (int) get_option( 'woocommerce_shop_page_id' );
if ( ! $magaza_id ) {
	echo "HATA: woocommerce_shop_page_id tanimsiz\n";
	return;
}
$magaza = get_post( $magaza_id );
echo "### magaza sayfasi #$magaza_id — {$magaza->post_title} (/{$magaza->post_name}/)\n";

$ceviri = [ 'tr' => $magaza_id ];
foreach ( [ 'en', 'ru' ] as $dil ) {
	$m      = $P[ $dil ];
	$mevcut = pll_get_post( $magaza_id, $dil );
	if ( $mevcut && 'publish' === get_post_status( $mevcut ) ) {
		$ceviri[ $dil ] = (int) $mevcut;
		if ( get_post( $mevcut )->post_content === $m['icerik'] ) {
			echo "  $dil zaten guncel: $mevcut\n";
			continue;
		}
		printf( "  %s icerigi guncellenecek: %d (%d -> %d bayt)\n", $dil, $mevcut, strlen( get_post( $mevcut )->post_content ), strlen( $m['icerik'] ) );
		if ( ! $dry ) {
			wp_update_post( [ 'ID' => (int) $mevcut, 'post_content' => $m['icerik'], 'post_title' => $m['baslik'] ] );
			echo "  guncellendi: $mevcut\n";
		}
		continue;
	}
	printf( "  %s: %s (/%s/%s/)\n", $dil, $m['baslik'], $dil, $m['slug'] );
	if ( $dry ) {
		continue;
	}
	$id = wp_insert_post(
		[
			'post_type'    => 'page',
			'post_status'  => 'publish',
			'post_author'  => (int) $magaza->post_author,
			'post_title'   => $m['baslik'],
			'post_name'    => $m['slug'],
			'post_content' => $m['icerik'],
		],
		true
	);
	if ( is_wp_error( $id ) ) {
		echo '  HATA: ' . $id->get_error_message() . "\n";
		return;
	}
	foreach ( [ '_wp_page_template', 'trx_addons_options', 'qwery_options' ] as $mk ) {
		$v = get_post_meta( $magaza_id, $mk, true );
		if ( '' !== $v && false !== $v ) {
			update_post_meta( $id, $mk, $v );
		}
	}
	/* Yazildigi gibi mi durdu? Isaretler yukten gelir — icerik degistiginde
	   burayi da guncellemek gerekmesin diye kod icine gomulmez. */
	$yazilan = get_post( $id )->post_content;
	foreach ( (array) ( $P['dogrulama'] ?? [] ) as $isaret ) {
		if ( false !== strpos( $m['icerik'], $isaret ) && false === strpos( $yazilan, $isaret ) ) {
			wp_delete_post( $id, true );
			echo "  KAYIT DOGRULAMASI BASARISIZ ('$isaret' kayboldu) -> silindi\n";
			return;
		}
	}
	pll_set_post_language( $id, $dil );
	$ceviri[ $dil ] = $id;
	echo "  olusturuldu $dil: $id\n";
}

if ( $dry ) {
	echo "DRY-RUN — yazilmadi.\n";
	return;
}
if ( count( $ceviri ) === 3 ) {
	pll_save_post_translations( $ceviri );
}

/* Menuleri yeni adrese bagla. */
$eski = untrailingslashit( wp_make_link_relative( get_permalink( $magaza_id ) ) );
foreach ( $MENU as $dil => $mid ) {
	if ( empty( $ceviri[ $dil ] ) ) {
		continue;
	}
	$yeni_url = get_permalink( $ceviri[ $dil ] );
	$degisti  = 0;
	foreach ( (array) wp_get_nav_menu_items( $mid ) as $oge ) {
		if ( 'custom' !== $oge->type ) {
			continue;
		}
		if ( untrailingslashit( wp_make_link_relative( $oge->url ) ) === $eski ) {
			update_post_meta( $oge->ID, '_menu_item_url', $yeni_url );
			$degisti++;
		}
	}
	echo "menu $mid ($dil): $degisti baglanti -> $yeni_url\n";
}

flush_rewrite_rules();
wp_cache_flush();
echo "TAMAM\n";
