<?php
$theme = get_option( 'stylesheet' );
$KAYNAK = 171;
$ETIKET = [
  'en' => [ 'Anasayfa'=>'Home', 'Hakkımızda'=>'About Us', 'Haberler'=>'News',
            'Çözümlerimiz'=>'Solutions', 'Kod Sorgulama'=>'Code Lookup', 'İletişim'=>'Contact' ],
  'ru' => [ 'Anasayfa'=>'Главная', 'Hakkımızda'=>'О нас', 'Haberler'=>'Новости',
            'Çözümlerimiz'=>'Услуги', 'Kod Sorgulama'=>'Проверка кода', 'İletişim'=>'Контакты' ],
];
$kaynak_ogeler = wp_get_nav_menu_items( $KAYNAK );
echo "  kaynak menu ogesi: " . count( $kaynak_ogeler ) . "\n";

$o = get_option( 'polylang' );
$nav = $o['nav_menus'] ?? [];

foreach ( [ 'en', 'ru' ] as $lang ) {
    $ad = "Main Menu ($lang)";
    $m = wp_get_nav_menu_object( $ad );
    $mid = $m ? (int) $m->term_id : (int) wp_create_nav_menu( $ad );
    if ( is_wp_error( $mid ) || ! $mid ) { echo "  $lang: menu olusturulamadi\n"; continue; }
    // var olan ogeleri temizle (yeniden calistirilabilir olsun)
    foreach ( (array) wp_get_nav_menu_items( $mid ) as $eski ) { wp_delete_post( $eski->ID, true ); }

    $n = 0;
    foreach ( $kaynak_ogeler as $it ) {
        if ( get_post_meta( $it->ID, '_pll_menu_item', true ) ) { continue; } // eski dil ogesi
        $baslik = $ETIKET[ $lang ][ $it->title ] ?? $it->title;
        $hedef  = $it->url;
        // Cevirisi olan sayfa varsa ona bagla (su an yalnizca ana sayfa)
        if ( $it->type === 'post_type' && $it->object_id ) {
            $cev = pll_get_post( (int) $it->object_id, $lang );
            if ( $cev ) { $hedef = get_permalink( $cev ); }
        }
        wp_update_nav_menu_item( $mid, 0, [
            'menu-item-title'  => $baslik,
            'menu-item-url'    => $hedef,
            'menu-item-type'   => 'custom',
            'menu-item-status' => 'publish',
        ] );
        $n++;
    }
    // dil degistirici
    $sw = wp_update_nav_menu_item( $mid, 0, [
        'menu-item-title' => 'Language', 'menu-item-url' => '#pll_switcher',
        'menu-item-type' => 'custom', 'menu-item-status' => 'publish',
    ] );
    update_post_meta( $sw, '_pll_menu_item', [
        'hide_if_no_translation'=>0,'hide_current'=>0,'force_home'=>0,
        'show_flags'=>1,'show_names'=>1,'dropdown'=>0,
    ] );
    foreach ( [ 'menu_main', 'menu_mobile' ] as $loc ) { $nav[ $theme ][ $loc ][ $lang ] = $mid; }
    echo "  $lang menusu: ID $mid, $n oge + dil degistirici\n";
}
$o['nav_menus'] = $nav;
update_option( 'polylang', $o );
PLL()->model->clean_languages_cache();
wp_cache_flush();
echo "  atama: " . wp_json_encode( $nav[ $theme ] ) . "\n";
echo "TAMAM\n";
