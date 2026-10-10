<?php
/**
 * Varsayilan siralamayi "yeniden eskiye" yapar; site hep guncel gorunsun.
 *
 * Iki ayri yerden geliyor, ikisi de gerekli:
 *  1. ARSIVLER (/shop/ + 9 kategori sablonu): hicbirinde kendi siralamasi yok,
 *     hepsi `woocommerce_default_catalog_orderby` seceneğine bakiyor. Deger
 *     `menu_order` idi; menu_order her urunde 0 oldugu icin WooCommerce basliga
 *     dusuyor ve liste fiilen ALFABETIK cikiyordu (Acorn, Agil, Alplerde...).
 *  2. ANA SAYFA izgarasi (etheme_product_grid): Elementor widget'i, WC
 *     secenegini DINLEMEZ. Kendi `orderby`/`order` ayari gerekir.
 *
 * TUZAK: widget'in `orderby` varsayilani zaten 'date' ama `order` varsayilani
 * 'ASC' — yani ayar vermezseniz ESKIDEN YENIYE olur. Ikisi birden yazilmali.
 *
 * `/indirimdekiler/` kisa koduna dokunulmaz (o bilerek fiyata gore siralı).
 *
 * Kullanim: wp eval-file siralama-yeni.php [uygula] --user=fdsanat
 */
$uygula = in_array( 'uygula', (array) ( $args ?? [] ), true );

// 1) WooCommerce arsiv varsayilani
$simdi = get_option( 'woocommerce_default_catalog_orderby' );
printf( "woocommerce_default_catalog_orderby : %s -> date%s\n",
	$simdi ?: '(ayarsiz)', $uygula ? '' : '  [kuru]' );
if ( $uygula && 'date' !== $simdi ) { update_option( 'woocommerce_default_catalog_orderby', 'date' ); }

// 2) Elementor urun izgaralari
global $wpdb;
$ids = $wpdb->get_col( "SELECT DISTINCT p.ID FROM {$wpdb->posts} p
  JOIN {$wpdb->postmeta} m ON m.post_id = p.ID
  WHERE m.meta_key = '_elementor_data' AND p.post_type <> 'revision'
    AND m.meta_value LIKE '%etheme_product_grid%'" );

$toplam = 0;
foreach ( $ids as $id ) {
	$j = json_decode( get_post_meta( $id, '_elementor_data', true ), true );
	if ( ! is_array( $j ) ) { printf( "  #%-5d json cozulemedi, atlandi\n", $id ); continue; }
	$sayac = 0;
	$gez = function ( &$l ) use ( &$gez, &$sayac, $id ) {
		foreach ( $l as &$e ) {
			if ( ( $e['widgetType'] ?? '' ) === 'etheme_product_grid' ) {
				$s = &$e['settings'];
				$eski = ( $s['orderby'] ?? '(yok)' ) . '/' . ( $s['order'] ?? '(yok)' );
				if ( ( $s['orderby'] ?? '' ) !== 'date' || ( $s['order'] ?? '' ) !== 'DESC' ) {
					$s['orderby'] = 'date';
					$s['order']   = 'DESC';
					$sayac++;
					printf( "  #%-5d %-10s %s -> date/DESC\n", $id, $e['id'] ?? '?', $eski );
				}
				unset( $s );
			}
			if ( ! empty( $e['elements'] ) ) { $gez( $e['elements'] ); }
		}
	};
	$gez( $j );
	if ( ! $sayac || ! $uygula ) { $toplam += $sayac; continue; }
	$json = wp_json_encode( $j, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
	if ( ! $json || ! is_array( json_decode( $json, true ) ) ) {
		printf( "  #%-5d HATA: JSON gecersiz, YAZILMADI\n", $id ); continue;
	}
	update_post_meta( $id, '_elementor_data', wp_slash( $json ) );
	$geri = get_post_meta( $id, '_elementor_data', true );
	printf( "  #%-5d yazildi: %s\n", $id,
		is_array( json_decode( $geri, true ) ) && strpos( $geri, 'etheme_product_grid' ) !== false
			? 'JSON gecerli + widget duruyor' : 'BOZUK!' );
	$toplam += $sayac;
}
printf( "\n%s: %d izgara\n", $uygula ? 'UYGULANDI' : '[KURU CALISMA] degisecek', $toplam );
if ( $uygula ) {
	if ( class_exists( '\Elementor\Plugin' ) ) { \Elementor\Plugin::$instance->files_manager->clear_cache(); }
	wp_cache_flush();   // WC secenegi nesne onbelleginde duruyor olabilir
	echo "elementor onbellegi + nesne onbellegi temizlendi\n";
}
