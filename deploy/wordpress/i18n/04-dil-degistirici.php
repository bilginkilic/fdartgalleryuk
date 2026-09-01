<?php
$MENU  = 171;                       // demo-menu — menu_main + menu_mobile
$theme = get_option( 'stylesheet' );

/* --- 1) Menuyu her dile ata --------------------------------------------
   Yalnizca ana sayfa cevrildigi icin EN/RU'ya ayri menu ACMIYORUZ; ayni
   menu her dilde gosteriliyor, boylece gezinme calismaya devam ediyor.
   Sayfalar cevrildikce dile ozel menu acilmali.                         */
$o = get_option( 'polylang' );
$nav = $o['nav_menus'] ?? [];
foreach ( [ 'menu_main', 'menu_mobile' ] as $loc ) {
    foreach ( [ 'tr', 'en', 'ru' ] as $l ) { $nav[ $theme ][ $loc ][ $l ] = $MENU; }
}
$o['nav_menus'] = $nav;
update_option( 'polylang', $o );
echo "  menu atamasi: " . wp_json_encode( $nav ) . "\n";

/* --- 2) Dil degistiriciyi menuye ekle --- */
$mevcut = 0;
foreach ( wp_get_nav_menu_items( $MENU ) ?: [] as $it ) {
    if ( get_post_meta( $it->ID, '_pll_menu_item', true ) ) { $mevcut = $it->ID; break; }
}
if ( $mevcut ) {
    echo "  dil degistirici zaten var (ID $mevcut)\n";
} else {
    $id = wp_update_nav_menu_item( $MENU, 0, [
        'menu-item-title'     => 'Dil',
        'menu-item-url'       => '#pll_switcher',
        'menu-item-type'      => 'custom',
        'menu-item-status'    => 'publish',
        'menu-item-position'  => 99,
    ] );
    if ( is_wp_error( $id ) ) { echo "  HATA: " . $id->get_error_message() . "\n"; return; }
    update_post_meta( $id, '_pll_menu_item', [
        'hide_if_no_translation' => 0,   // cevirisi olmayan dil de gorunsun
        'hide_current'           => 0,
        'force_home'             => 0,
        'show_flags'             => 1,
        'show_names'             => 1,
        'dropdown'               => 0,   // yan yana; tema acilir menuyu bogabiliyor
    ] );
    echo "  dil degistirici eklendi (ID $id)\n";
}
PLL()->model->clean_languages_cache();
wp_cache_flush();
echo "TAMAM\n";
