<?php
/**
 * Plugin Name: zz Profil (GECICI — yalnizca dev)
 * Description: ?fdprofil=fd2026 ile istegin nereye zaman harcadigini /tmp/fdprofil.txt'e yazar.
 */
if ( ! isset( $_GET['fdprofil'] ) || $_GET['fdprofil'] !== 'fd2026' ) { return; }
if ( ! defined( 'SAVEQUERIES' ) ) { define( 'SAVEQUERIES', true ); }

global $fd_prof;
$fd_prof = [ 'baslangic' => ( $_SERVER['REQUEST_TIME_FLOAT'] ?? microtime( true ) ), 'nokta' => [] ];
$fd_prof['nokta']['mu-plugin yuklendi'] = microtime( true );

foreach ( [ 'plugins_loaded', 'setup_theme', 'after_setup_theme', 'init', 'wp_loaded',
            'parse_request', 'wp', 'template_redirect', 'wp_head', 'wp_footer' ] as $k ) {
    add_action( $k, function () use ( $k ) {
        global $fd_prof;
        $fd_prof['nokta'][ $k ] = microtime( true );
    }, -PHP_INT_MAX );
}

add_action( 'shutdown', function () {
    global $fd_prof, $wpdb;
    $t0  = $fd_prof['baslangic'];
    $son = microtime( true );
    $s   = str_repeat( '=', 70 ) . "\n";
    $s  .= sprintf( "%s  %s\n", gmdate( 'H:i:s' ), $_SERVER['REQUEST_URI'] ?? '?' );
    $s  .= sprintf( "TOPLAM %.0f ms   bellek tepe %.1f MB\n\n", ( $son - $t0 ) * 1000, memory_get_peak_usage( true ) / 1048576 );

    $s .= "--- asamalar (istek basindan itibaren, parantezde bir onceki asamadan beri) ---\n";
    $onceki = $t0;
    foreach ( $fd_prof['nokta'] as $ad => $t ) {
        $s .= sprintf( "  %-22s %8.0f ms   (+%.0f ms)\n", $ad, ( $t - $t0 ) * 1000, ( $t - $onceki ) * 1000 );
        $onceki = $t;
    }
    $s .= sprintf( "  %-22s %8.0f ms   (+%.0f ms)\n\n", 'shutdown', ( $son - $t0 ) * 1000, ( $son - $onceki ) * 1000 );

    $s .= sprintf( "--- yuklenen dosya: %d   opcache: ", count( get_included_files() ) );
    if ( function_exists( 'opcache_get_status' ) ) {
        $o = @opcache_get_status( false );
        if ( $o && ! empty( $o['opcache_enabled'] ) ) {
            $s .= sprintf( "acik, %d script, bellek %.0f/%.0f MB, isabet %%%.1f, kacirma %d, restart %d\n",
                $o['opcache_statistics']['num_cached_scripts'],
                $o['memory_usage']['used_memory'] / 1048576,
                ( $o['memory_usage']['used_memory'] + $o['memory_usage']['free_memory'] ) / 1048576,
                $o['opcache_statistics']['opcache_hit_rate'],
                $o['opcache_statistics']['misses'],
                $o['opcache_statistics']['oom_restarts'] + $o['opcache_statistics']['hash_restarts'] + $o['opcache_statistics']['manual_restarts'] );
        } else { $s .= "KAPALI\n"; }
    } else { $s .= "fonksiyon yok\n"; }
    $s .= sprintf( "--- object cache: %s\n", ( wp_using_ext_object_cache() ? 'harici (Redis)' : 'YOK' ) );

    $sorgular = $wpdb->queries ?? [];
    $toplam   = 0;
    foreach ( $sorgular as $q ) { $toplam += $q[1]; }
    $s .= sprintf( "--- veritabani: %d sorgu, toplam %.0f ms (istegin %%%.0f'i) ---\n",
        count( $sorgular ), $toplam * 1000, ( $son - $t0 ) > 0 ? $toplam / ( $son - $t0 ) * 100 : 0 );

    usort( $sorgular, function ( $a, $b ) { return $b[1] <=> $a[1]; } );
    foreach ( array_slice( $sorgular, 0, 8 ) as $q ) {
        $s .= sprintf( "  %7.1f ms  %s\n", $q[1] * 1000, substr( preg_replace( '/\s+/', ' ', $q[0] ), 0, 130 ) );
        $s .= sprintf( "            cagiran: %s\n", substr( $q[2], -160 ) );
    }
    $s .= "\n";
    file_put_contents( '/tmp/fdprofil.txt', $s, FILE_APPEND );
} , PHP_INT_MAX );
