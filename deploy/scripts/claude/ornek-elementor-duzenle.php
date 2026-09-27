<?php
/**
 * ORNEK — Elementor _elementor_data GUVENLI DUZENLEME KALIBI
 * ===========================================================
 * Bu dosya 25.09.2026'da 9 kategori sablonuna gercekten uygulanan script'tir,
 * kalip olarak saklaniyor. Yeni bir Elementor duzenlemesi yazarken bunu
 * kopyalayip icini degistirin; asagidaki BES adimi atlamayin.
 *
 *  1. KURU CALISMA varsayilan olsun. Yazma yalnizca 'uygula' argumaniyla.
 *  2. YAPIYI ARAYARAK BUL, sabit indis kullanma. Ayni is icin urun widget'i
 *     bir sablonda kok[1]'de, digerinde kok[2]'deydi.
 *  3. YAZMADAN ONCE JSON'u dogrula (wp_json_encode + json_decode geri testi).
 *  4. YAZDIKTAN SONRA GERI OKU ve hem yeni seyin hem eski seyin durdugunu gor.
 *  5. Yedegi PHP ile /var/backups'a YAZMAYA CALISMA — open_basedir engeller ve
 *     file_put_contents sessizce basarisiz olur. Yedegi kabuktan alin:
 *       wp post meta get <id> _elementor_data > /var/backups/<...>.json
 *
 * TUZAK (bu script'in varlik sebebi): urun izgarasini tutan kok konteyner
 * ROW yonundeydi. Widget'i ICINE koymak onu izgaranin YANINA sikistiriyordu.
 * Dogrusu: hemen ONUNE, ayni genislikte ayri bir COLUMN konteyner.
 * Bunu ancak tarayicida olcunce gorursunuz — sayfa-olc.py.
 *
 * Kullanim:
 *   ./gonder.sh ornek-elementor-duzenle.php --wp dev.fdartgallery.com
 *   ./gonder.sh ornek-elementor-duzenle.php --wp fdartgallery.com -- uygula
 */
$uygula = in_array( 'uygula', (array) ( $args ?? [] ), true );
$urun_w = 'woocommerce-etheme_archive_products';

$sb = wp_get_sidebars_widgets();
if ( empty( $sb['shop-sidebar'] ) ) { echo "HATA: shop-sidebar bos. Iptal.\n"; return; }
echo "shop-sidebar: " . count( $sb['shop-sidebar'] ) . " widget\n\n";

$uret = function () { return substr( str_replace( [ '.', ' ' ], '', uniqid( '', true ) ), -7 ); };

$blok_yap = function ( $genislik ) use ( $uret ) {
	$ayar = [ 'content_width' => 'boxed', 'flex_direction' => 'column' ];
	if ( $genislik ) { $ayar['boxed_width'] = $genislik; }
	return [
		'id'       => $uret(),
		'elType'   => 'container',
		'settings' => $ayar,
		'elements' => [
			[
				'id'         => $uret(),
				'elType'     => 'widget',
				'widgetType' => 'etheme_sidebar_horizontal',
				'settings'   => [
					'sidebar'                => 'shop-sidebar',
					'cols'                   => '3',
					'cols_tablet'            => '2',
					'cols_mobile'            => '1',
					'widgets_toggle'         => 'yes',
					'enabled_default'        => 'yes',
					'sidebar_off_canvas_on'  => [ 'mobile', 'tablet' ],
					'button_text'            => 'Filtreler',
					'widgets_title_type'     => 'underline',
					'widgets_count_brackets' => 'brackets',
				],
				'elements'   => [],
			],
		],
		'isInner'  => false,
	];
};

$q = get_posts( [
	'post_type'   => 'elementor_library',
	'post_status' => 'publish',
	'numberposts' => -1,
	'meta_query'  => [ [ 'key' => '_elementor_template_type', 'value' => 'product-archive' ] ],
] );

$sayac = [ 'eklendi' => 0, 'zaten' => 0, 'atlandi' => 0 ];
foreach ( $q as $p ) {
	$ham = get_post_meta( $p->ID, '_elementor_data', true );
	printf( "== #%d  %s\n", $p->ID, $p->post_title );

	if ( strpos( (string) $ham, 'etheme_sidebar_horizontal' ) !== false ) {
		echo "   filtre ZATEN VAR, atlandi\n\n"; $sayac['zaten']++; continue;
	}
	$j = json_decode( $ham, true );
	if ( ! is_array( $j ) ) { echo "   HATA: json cozulemedi, ATLANDI\n\n"; $sayac['atlandi']++; continue; }

	// urun widget'ini iceren KOK elemani bul
	$hedef = null;
	foreach ( $j as $i => $e ) {
		if ( strpos( wp_json_encode( $e ), $urun_w ) !== false ) { $hedef = $i; break; }
	}
	if ( $hedef === null ) { echo "   HATA: $urun_w bulunamadi, ATLANDI\n\n"; $sayac['atlandi']++; continue; }

	$yon  = $j[ $hedef ]['settings']['flex_direction'] ?? '(bos)';
	$gen  = $j[ $hedef ]['settings']['boxed_width'] ?? null;
	printf( "   urun konteyneri: kok[%d] yon=%s genislik=%s\n",
		$hedef, $yon, $gen ? ( $gen['size'] . $gen['unit'] ) : '(varsayilan)' );

	array_splice( $j, $hedef, 0, [ $blok_yap( $gen ) ] );
	echo "   yeni kok sirasi: ";
	foreach ( $j as $i => $e ) {
		$ne = strpos( wp_json_encode( $e ), 'etheme_sidebar_horizontal' ) !== false ? 'FILTRE'
			: ( strpos( wp_json_encode( $e ), $urun_w ) !== false ? 'URUNLER' : ( $e['elType'] ?? '?' ) );
		printf( '[%d]%s ', $i, $ne );
	}
	echo "\n";

	if ( ! $uygula ) { echo "   [KURU CALISMA] yazilmadi\n\n"; $sayac['eklendi']++; continue; }

	$json = wp_json_encode( $j, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
	if ( ! $json || ! is_array( json_decode( $json, true ) ) ) {
		echo "   HATA: uretilen JSON gecersiz, YAZILMADI\n\n"; $sayac['atlandi']++; continue;
	}
	update_post_meta( $p->ID, '_elementor_data', wp_slash( $json ) );
	$geri = get_post_meta( $p->ID, '_elementor_data', true );
	$g2   = json_decode( $geri, true );
	$ok   = is_array( $g2 ) && strpos( $geri, 'etheme_sidebar_horizontal' ) !== false
		&& strpos( $geri, $urun_w ) !== false;
	echo "   yazildi, geri okuma: " . ( $ok ? 'JSON gecerli + filtre + urunler duruyor' : 'BOZUK!' ) . "\n\n";
	$sayac['eklendi']++;
}

if ( $uygula && class_exists( '\Elementor\Plugin' ) ) {
	\Elementor\Plugin::$instance->files_manager->clear_cache();
	echo "elementor onbellegi temizlendi\n";
}
echo "\nOZET: eklendi=" . $sayac['eklendi'] . " zaten_vardi=" . $sayac['zaten'] . " atlandi=" . $sayac['atlandi'] . "\n";
