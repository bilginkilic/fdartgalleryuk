<?php
/**
 * Elementor sablonlarini tarar: kosullari, widget agaci, aranan isaret.
 * Bir sablonu duzenlemeden ONCE yapisini gormek icin.
 *
 * TUZAK: yapi sablondan sablona degisir. Ayni is icin bir sablonda urun
 * widget'i kok[1]'de, digerinde kok[2]'de olabilir. Sabit indise GUVENMEYIN.
 *
 * Kullanim:
 *   ./gonder.sh elementor-tara.php --wp <site>                      # tum tipler
 *   ./gonder.sh elementor-tara.php --wp <site> -- product-archive
 *   ./gonder.sh elementor-tara.php --wp <site> -- product-archive etheme_sidebar_horizontal
 */
$tip    = $args[0] ?? '';
$isaret = $args[1] ?? '';

$meta = $tip ? [ [ 'key' => '_elementor_template_type', 'value' => $tip ] ] : [];
$q = get_posts( [ 'post_type' => 'elementor_library', 'post_status' => 'any',
	'numberposts' => -1, 'meta_query' => $meta ] );
echo "sablon: " . count( $q ) . ( $tip ? " (tip: $tip)" : '' ) . "\n\n";

$gez = function ( $liste, $yol, &$cikti ) use ( &$gez ) {
	foreach ( (array) $liste as $i => $e ) {
		$y = $yol . "[$i]";
		if ( ( $e['elType'] ?? '' ) === 'widget' ) {
			$cikti[] = sprintf( '%-42s %s', $y, $e['widgetType'] ?? '?' );
		} else {
			$s = $e['settings'] ?? [];
			$ek = [];
			foreach ( [ 'flex_direction', 'boxed_width', 'content_width' ] as $k ) {
				if ( isset( $s[ $k ] ) ) {
					$ek[] = $k . '=' . ( is_array( $s[ $k ] )
						? ( ( $s[ $k ]['size'] ?? '' ) . ( $s[ $k ]['unit'] ?? '' ) ) : $s[ $k ] );
				}
			}
			$cikti[] = sprintf( '%-42s %s %s', $y, $e['elType'] ?? '?', implode( ' ', $ek ) );
		}
		if ( ! empty( $e['elements'] ) ) { $gez( $e['elements'], $y . "['elements']", $cikti ); }
	}
};

foreach ( $q as $p ) {
	$kosul = get_post_meta( $p->ID, '_elementor_conditions', true );
	$ham   = get_post_meta( $p->ID, '_elementor_data', true );
	printf( "== #%d  %s  [%s]  tip=%s\n", $p->ID, $p->post_title, $p->post_status,
		get_post_meta( $p->ID, '_elementor_template_type', true ) ?: '?' );
	echo "   kosul: " . ( is_array( $kosul ) && $kosul ? implode( ' | ', $kosul ) : '(yok)' ) . "\n";
	if ( $isaret ) {
		echo "   '$isaret': " . ( strpos( (string) $ham, $isaret ) !== false ? 'VAR' : 'yok' ) . "\n";
	}
	$j = json_decode( $ham, true );
	if ( ! is_array( $j ) ) { echo "   (json cozulemedi — muhtemelen bos taslak)\n\n"; continue; }
	$c = [];
	$gez( $j, '', $c );
	foreach ( $c as $satir ) { echo "     $satir\n"; }
	echo "\n";
}
