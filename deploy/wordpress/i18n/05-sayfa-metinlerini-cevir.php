<?php
$MAP  = json_decode( file_get_contents( '/tmp/sayfa-cevirileri.json' ), true );
$dry  = ( ( $args[0] ?? '' ) === 'dry' );
kses_remove_filters();
$HEDEF = [ 'en' => 37686, 'ru' => 37687 ];

foreach ( $HEDEF as $lang => $pid ) {
    $sozluk = $MAP[ $lang ];
    $raw = get_post_meta( $pid, '_elementor_data', true );
    $data = json_decode( $raw, true );
    if ( ! is_array( $data ) ) { echo "  $lang: veri cozulemedi\n"; continue; }

    /* Bosluga duyarsiz eslestirme: kaynak metinlerde cift bosluk ve satir
       sonlari var; birebir karsilastirma bu yuzden kaciriyordu. */
    $norm = function ( $s ) { return trim( preg_replace( '/\s+/u', ' ', $s ) ); };
    $nsozluk = [];
    foreach ( $sozluk as $k => $v ) { $nsozluk[ $norm( $k ) ] = [ 'v' => $v, 'k' => $k ]; }

    $n = 0; $bulunan = [];
    $walk = function ( &$els ) use ( &$walk, &$n, &$bulunan, $nsozluk, $norm ) {
        foreach ( $els as &$e ) {
            if ( ! empty( $e['settings'] ) && is_array( $e['settings'] ) ) {
                array_walk_recursive( $e['settings'], function ( &$v ) use ( &$n, &$bulunan, $nsozluk, $norm ) {
                    if ( ! is_string( $v ) || trim( $v ) === '' ) { return; }
                    $t = $norm( $v );
                    if ( isset( $nsozluk[ $t ] ) ) { $v = $nsozluk[ $t ]['v']; $n++; $bulunan[ $nsozluk[ $t ]['k'] ] = true; }
                } );
            }
            if ( ! empty( $e['elements'] ) ) { $walk( $e['elements'] ); }
        }
    };
    $walk( $data );

    $eksik = array_diff( array_keys( $sozluk ), array_keys( $bulunan ) );
    printf( "  %s: %d alan cevrildi, sozlukte kullanilmayan %d\n", $lang, $n, count( $eksik ) );
    foreach ( array_slice( $eksik, 0, 4 ) as $e2 ) { echo "     kullanilmadi: " . mb_substr( $e2, 0, 60 ) . "\n"; }

    if ( $dry ) { continue; }
    update_post_meta( $pid, '_elementor_data', wp_slash( wp_json_encode( $data ) ) );
    $b = get_post_meta( $pid, '_elementor_data', true );
    if ( substr_count( $b, '<style>' ) < 1 || strpos( $b, 'bindForm' ) === false ) {
        update_post_meta( $pid, '_elementor_data', wp_slash( $raw ) );
        echo "  $lang: DOGRULAMA BASARISIZ -> geri alindi\n";
    }
}
if ( ! $dry && class_exists( '\Elementor\Plugin' ) ) { \Elementor\Plugin::$instance->files_manager->clear_cache(); }
echo $dry ? "DRY-RUN\n" : "TAMAM\n";
