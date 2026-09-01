<?php
$pll = PLL();
$langs = $pll->model->languages;

$tanim = [
    [ 'name' => 'Türkçe',  'slug' => 'tr', 'locale' => 'tr_TR', 'flag' => 'tr', 'rtl' => 0, 'term_group' => 0 ],
    [ 'name' => 'English', 'slug' => 'en', 'locale' => 'en_US', 'flag' => 'us', 'rtl' => 0, 'term_group' => 1 ],
    [ 'name' => 'Русский', 'slug' => 'ru', 'locale' => 'ru_RU', 'flag' => 'ru', 'rtl' => 0, 'term_group' => 2 ],
];
foreach ( $tanim as $d ) {
    if ( $langs->get( $d['slug'] ) ) { echo "  zaten var : {$d['slug']}\n"; continue; }
    $r = $langs->add( $d );
    echo is_wp_error( $r )
        ? "  HATA {$d['slug']}: " . $r->get_error_message() . "\n"
        : "  eklendi   : {$d['slug']} ({$d['locale']})\n";
}

/* --- URL yapisi --- */
$o = $pll->options;
$ayar = [
    'force_lang'    => 1, // /en/ /ru/ dizin oneki
    'hide_default'  => 1, // Turkce koke kalir
    'rewrite'       => 1, // permalink'ten /language/ kalksin
    'browser'       => 0, // tarayici diline gore otomatik yonlendirme KAPALI
    'redirect_lang' => 0,
    'default_lang'  => 'tr',
];
foreach ( $ayar as $k => $v ) {
    try { $o[ $k ] = $v; } catch ( \Throwable $e ) { echo "  ayar HATA $k: " . $e->getMessage() . "\n"; }
}
if ( method_exists( $o, 'save' ) ) { $o->save(); }

$langs->clean_cache();
flush_rewrite_rules();

$db = get_option( 'polylang' );
foreach ( array_keys( $ayar ) as $k ) {
    printf( "  %-14s = %s\n", $k, var_export( $db[ $k ] ?? '(yok)', true ) );
}
foreach ( $langs->get_list() as $l ) {
    printf( "  DIL %-4s %-8s %-10s anasayfa=%s\n", $l->slug, $l->locale, $l->name, $l->home_url );
}
