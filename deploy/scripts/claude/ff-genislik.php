<?php
/**
 * FluentForm Elementor widget'larinin SABIT px genisligi mobilde yatay tasma
 * yaratiyor. Mobil + tablet icin %100 verir; masaustu degismez.
 *
 * TUZAK 1: kontrolun adi `form_container_max_width` ama Elementor bunu
 * `max-width` DEGIL `width` olarak basiyor:
 *   .elementor-1041 .elementor-element-<id> .fluentform-widget-wrapper{width:480px}
 * 390px ekranda 330px'lik kabin icine 480px konuyor -> belge 510px -> butun
 * sayfa saga kayiyor ve HERO kaymis gibi gorunuyor. Suclu hero degil.
 *
 * TUZAK 2: widget kimlikleri dev ve canlida FARKLI (dev 5ee039e, canli 21d63db).
 * Bu yuzden kimlik gomulmez; widgetType'a gore taranir.
 *
 * Kullanim: wp eval-file ff-genislik.php [uygula] --user=fdsanat
 */
$uygula = in_array( 'uygula', (array) ( $args ?? [] ), true );
global $wpdb;

$kayit = $wpdb->get_col( "SELECT DISTINCT p.ID FROM {$wpdb->posts} p
  JOIN {$wpdb->postmeta} m ON m.post_id = p.ID
  WHERE m.meta_key = '_elementor_data' AND m.meta_value LIKE '%fluent-form-widget%'
    AND p.post_type <> 'revision'" );      // revizyonlara yazmak gereksiz

$yuzde = [ 'unit' => '%', 'size' => 100, 'sizes' => [] ];
$toplam = 0;

foreach ( $kayit as $id ) {
	$ham = get_post_meta( $id, '_elementor_data', true );
	$j   = json_decode( $ham, true );
	if ( ! is_array( $j ) ) { printf( "#%-5d json cozulemedi, atlandi\n", $id ); continue; }

	$sayac = 0;
	$gez = function ( &$l ) use ( &$gez, &$sayac, $yuzde, $id ) {
		foreach ( $l as &$e ) {
			if ( ( $e['widgetType'] ?? '' ) === 'fluent-form-widget' ) {
				$s = &$e['settings'];
				$mas = $s['form_container_max_width'] ?? null;
				// Yalnizca SABIT px genisligi olanlar sorun cikarir.
				if ( is_array( $mas ) && ( $mas['unit'] ?? '' ) === 'px' && ! empty( $mas['size'] ) ) {
					$eskiT = $s['form_container_max_width_tablet'] ?? null;
					$eskiM = $s['form_container_max_width_mobile'] ?? null;
					if ( $eskiT !== $yuzde || $eskiM !== $yuzde ) {
						$s['form_container_max_width_tablet'] = $yuzde;
						$s['form_container_max_width_mobile'] = $yuzde;
						$sayac++;
						printf( "  #%-5d %-10s masaustu %dpx korundu; tablet/mobil -> %%100\n",
							$id, $e['id'] ?? '?', $mas['size'] );
					}
				}
				unset( $s );
			}
			if ( ! empty( $e['elements'] ) ) { $gez( $e['elements'] ); }
		}
	};
	$gez( $j );
	if ( ! $sayac ) { continue; }
	$toplam += $sayac;

	if ( ! $uygula ) { continue; }
	$json = wp_json_encode( $j, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
	if ( ! $json || ! is_array( json_decode( $json, true ) ) ) {
		printf( "  #%-5d HATA: JSON gecersiz, YAZILMADI\n", $id ); continue;
	}
	update_post_meta( $id, '_elementor_data', wp_slash( $json ) );
	$geri = get_post_meta( $id, '_elementor_data', true );
	printf( "  #%-5d yazildi, geri okuma: %s\n", $id,
		is_array( json_decode( $geri, true ) ) && strpos( $geri, 'fluent-form-widget' ) !== false
			? 'JSON gecerli + widget duruyor' : 'BOZUK!' );
}

printf( "\n%s: %d widget\n", $uygula ? 'UYGULANDI' : '[KURU CALISMA] degisecek', $toplam );
if ( $uygula && $toplam && class_exists( '\Elementor\Plugin' ) ) {
	\Elementor\Plugin::$instance->files_manager->clear_cache();
	echo "elementor onbellegi temizlendi\n";
}
