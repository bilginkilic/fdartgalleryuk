<?php
$P = json_decode( file_get_contents( '/tmp/i18n-payload.json' ), true );
if ( ! $P || empty( $P['assets'] ) ) { echo "HATA: yuk bozuk\n"; return; }
$dry = ( ( $args[0] ?? '' ) === 'dry' );
kses_remove_filters();

$SRC = 5002;
$raw  = get_post_meta( $SRC, '_elementor_data', true );
$data = json_decode( $raw, true );
if ( ! is_array( $data ) ) { echo "HATA: kaynak elementor verisi cozulemedi\n"; return; }

/* --- 1) Turkce sayfanin assets widget'ini dilden bagimsiz surumle degistir --- */
$upd = 0;
$fix = function ( &$els ) use ( &$fix, &$upd, $P ) {
    foreach ( $els as &$e ) {
        if ( ( $e['widgetType'] ?? '' ) === 'html' && strpos( $e['settings']['html'] ?? '', 'bindForm' ) !== false ) {
            $e['settings']['html'] = $P['assets']; $upd++;
        }
        if ( ! empty( $e['elements'] ) ) { $fix( $e['elements'] ); }
    }
};
$fix( $data );
echo "  TR assets widget guncellendi: $upd\n";
if ( $upd !== 1 ) { echo "DURDURULDU\n"; return; }

if ( ! $dry ) {
    update_post_meta( $SRC, '_elementor_data', wp_slash( wp_json_encode( $data ) ) );
    $b = get_post_meta( $SRC, '_elementor_data', true );
    if ( substr_count( $b, '<style>' ) < 1 || strpos( $b, 'bindForm' ) === false ) {
        update_post_meta( $SRC, '_elementor_data', wp_slash( $raw ) );
        echo "TR DOGRULAMA BASARISIZ -> geri alindi\n"; return;
    }
}

/* --- 2) dil kopyalarini uret --- */
$uid = function () { return substr( md5( uniqid( '', true ) ), 0, 7 ); };
$ceviri = [ 'tr' => $SRC ];

foreach ( [ 'en', 'ru' ] as $lang ) {
    $var = $P[ $lang ];
    $kopya = json_decode( wp_json_encode( $data ), true );   // derin kopya

    $sayac = [ 'assets' => 0, 'form' => 0, 'metin' => 0 ];
    $harita = [
        'cz-lead-eyebrow' => $var['eyebrow'], 'cz-lead-h"' => $var['h1'],
        'cz-lead-sub'     => $var['sub'],     'cz-lead-perks' => $var['perks'],
        'cz-lead-trust'   => $var['trust'],   'cz-lead-fn'  => $var['fn'],
    ];
    $walk = function ( &$els ) use ( &$walk, &$sayac, $var, $harita, $uid ) {
        foreach ( $els as &$e ) {
            $e['id'] = $uid();
            $t = $e['widgetType'] ?? '';
            if ( $t === 'html' ) {
                $h = $e['settings']['html'] ?? '';
                if ( strpos( $h, 'bindForm' ) !== false )            { $e['settings']['html'] = $var['assets'] ?? $h; $sayac['assets']++; }
                elseif ( strpos( $h, 'cz-lead-formwrap' ) !== false ) { $e['settings']['html'] = $var['form'];  $sayac['form']++; }
            } elseif ( $t === 'text-editor' ) {
                $ed = $e['settings']['editor'] ?? '';
                foreach ( $harita as $imza => $yeni ) {
                    if ( strpos( $ed, $imza ) !== false ) { $e['settings']['editor'] = $yeni; $sayac['metin']++; break; }
                }
            }
            if ( ! empty( $e['elements'] ) ) { $walk( $e['elements'] ); }
        }
    };
    $walk( $kopya );
    printf( "  %s: assets=%d form=%d metin=%d\n", $lang, $sayac['assets'], $sayac['form'], $sayac['metin'] );
    if ( $sayac['form'] !== 1 || $sayac['metin'] !== 6 ) { echo "  DURDURULDU ($lang): beklenen 1 form + 6 metin\n"; return; }

    if ( $dry ) { continue; }

    $id = wp_insert_post( [
        'post_type' => 'page', 'post_status' => 'publish', 'post_author' => 3,
        'post_title' => $var['title'], 'post_name' => ( $lang === 'en' ? 'home' : 'glavnaya' ),
    ], true );
    if ( is_wp_error( $id ) ) { echo "  HATA ($lang): " . $id->get_error_message() . "\n"; return; }

    foreach ( [ '_wp_page_template', '_elementor_edit_mode', '_elementor_template_type', '_elementor_version', 'trx_addons_options', 'qwery_options', '_elementor_page_settings' ] as $mk ) {
        $v = get_post_meta( $SRC, $mk, true );
        if ( $v !== '' && $v !== false ) { update_post_meta( $id, $mk, $v ); }
    }
    update_post_meta( $id, '_elementor_data', wp_slash( wp_json_encode( $kopya ) ) );
    pll_set_post_language( $id, $lang );
    $ceviri[ $lang ] = $id;
    echo "  olusturuldu $lang: ID $id\n";
}

if ( $dry ) { echo "DRY-RUN — yazilmadi.\n"; return; }
pll_save_post_translations( $ceviri );
echo "  ceviri baglantisi: " . wp_json_encode( $ceviri ) . "\n";
if ( class_exists( '\Elementor\Plugin' ) ) { \Elementor\Plugin::$instance->files_manager->clear_cache(); }
flush_rewrite_rules();
echo "TAMAM\n";
