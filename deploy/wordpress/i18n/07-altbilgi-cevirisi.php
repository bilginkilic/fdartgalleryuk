<?php
$MAP = json_decode( file_get_contents( '/tmp/altbilgi-cevirileri.json' ), true );
$dry = ( ( $args[0] ?? '' ) === 'dry' );
kses_remove_filters();
$SRC = 4105;

$raw  = get_post_meta( $SRC, '_elementor_data', true );
$data = json_decode( $raw, true );
if ( ! is_array( $data ) ) { echo "HATA: altbilgi verisi cozulemedi\n"; return; }
if ( ! pll_get_post_language( $SRC ) ) { pll_set_post_language( $SRC, 'tr' ); echo "  kaynak altbilgi 'tr' isaretlendi\n"; }

$uid  = function () { return substr( md5( uniqid( '', true ) ), 0, 7 ); };
$norm = function ( $s ) { return trim( preg_replace( '/\s+/u', ' ', $s ) ); };
$ceviri = [ 'tr' => $SRC ];

foreach ( [ 'en', 'ru' ] as $lang ) {
    $sozluk = [];
    foreach ( $MAP[ $lang ] as $k => $v ) { $sozluk[ $norm( $k ) ] = [ 'v' => $v, 'k' => $k ]; }
    $kopya = json_decode( wp_json_encode( $data ), true );
    $n = 0; $bulunan = [];
    $walk = function ( &$els ) use ( &$walk, &$n, &$bulunan, $sozluk, $norm, $uid ) {
        foreach ( $els as &$e ) {
            $e['id'] = $uid();
            if ( ! empty( $e['settings'] ) && is_array( $e['settings'] ) ) {
                array_walk_recursive( $e['settings'], function ( &$v ) use ( &$n, &$bulunan, $sozluk, $norm ) {
                    if ( ! is_string( $v ) || trim( $v ) === '' ) { return; }
                    $t = $norm( $v );
                    if ( isset( $sozluk[ $t ] ) ) { $v = $sozluk[ $t ]['v']; $n++; $bulunan[ $sozluk[ $t ]['k'] ] = true; }
                } );
            }
            if ( ! empty( $e['elements'] ) ) { $walk( $e['elements'] ); }
        }
    };
    $walk( $kopya );
    $eksik = array_diff( array_keys( $MAP[ $lang ] ), array_keys( $bulunan ) );
    printf( "  %s: %d alan cevrildi, eslesmeyen %d\n", $lang, $n, count( $eksik ) );
    foreach ( $eksik as $e2 ) { echo "     eslesmedi: " . mb_substr( $e2, 0, 55 ) . "\n"; }
    if ( $dry ) { continue; }

    $mevcut = pll_get_post( $SRC, $lang );
    $id = $mevcut ?: wp_insert_post( [
        'post_type' => 'cpt_layouts', 'post_status' => 'publish', 'post_author' => 3,
        'post_title' => 'Footer Default (' . $lang . ')',
    ], true );
    if ( is_wp_error( $id ) ) { echo "  HATA ($lang): " . $id->get_error_message() . "\n"; return; }
    foreach ( [ '_elementor_edit_mode', '_elementor_template_type', '_elementor_version', 'trx_addons_options' ] as $mk ) {
        $v = get_post_meta( $SRC, $mk, true );
        if ( $v !== '' && $v !== false ) { update_post_meta( $id, $mk, $v ); }
    }
    update_post_meta( $id, '_elementor_data', wp_slash( wp_json_encode( $kopya ) ) );
    pll_set_post_language( $id, $lang );
    $ceviri[ $lang ] = $id;
    echo "  $lang altbilgi: ID $id\n";
}
if ( $dry ) { echo "DRY-RUN\n"; return; }
pll_save_post_translations( $ceviri );
if ( class_exists( '\Elementor\Plugin' ) ) { \Elementor\Plugin::$instance->files_manager->clear_cache(); }
echo "  baglanti: " . wp_json_encode( $ceviri ) . "\nTAMAM\n";
