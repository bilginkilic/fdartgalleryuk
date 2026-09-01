<?php
if ( ! function_exists( 'pll_set_post_language' ) ) { echo "HATA: Polylang API yok\n"; return; }
global $wpdb;

$tipler = array_keys( PLL()->model->get_translated_post_types() );
$in_types = "'" . implode( "','", array_map( 'esc_sql', $tipler ) ) . "'";
$lang_tt = $wpdb->get_col( "SELECT term_taxonomy_id FROM {$wpdb->term_taxonomy} WHERE taxonomy='language'" );
$in_tt   = implode( ',', array_map( 'intval', $lang_tt ) );

$ids = $wpdb->get_col( "
  SELECT ID FROM {$wpdb->posts}
  WHERE post_type IN ($in_types)
    AND post_status IN ('publish','draft','private','pending','future')
    AND ID NOT IN (SELECT object_id FROM {$wpdb->term_relationships} WHERE term_taxonomy_id IN ($in_tt))
" );
echo "  dilsiz icerik: " . count( $ids ) . "\n";
$n = 0;
foreach ( $ids as $id ) { pll_set_post_language( (int) $id, 'tr' ); $n++; }
echo "  Turkce isaretlendi: $n\n";

/* --- terimler --- */
$tax = array_keys( PLL()->model->get_translated_taxonomies() );
echo "  ceviri edilebilir taksonomiler: " . implode( ', ', $tax ) . "\n";
$in_tax = "'" . implode( "','", array_map( 'esc_sql', $tax ) ) . "'";
$tlang_tt = $wpdb->get_col( "SELECT term_taxonomy_id FROM {$wpdb->term_taxonomy} WHERE taxonomy='term_language'" );
$in_ttt   = implode( ',', array_map( 'intval', $tlang_tt ) ) ?: '0';
$tids = $wpdb->get_col( "
  SELECT t.term_id FROM {$wpdb->terms} t
  JOIN {$wpdb->term_taxonomy} tt ON tt.term_id=t.term_id
  WHERE tt.taxonomy IN ($in_tax)
    AND t.term_id NOT IN (SELECT object_id FROM {$wpdb->term_relationships} WHERE term_taxonomy_id IN ($in_ttt))
" );
echo "  dilsiz terim: " . count( $tids ) . "\n";
$tn = 0;
foreach ( $tids as $tid ) { pll_set_term_language( (int) $tid, 'tr' ); $tn++; }
echo "  Turkce isaretlendi (terim): $tn\n";

/* --- kalan dilsiz var mi --- */
$kalan = $wpdb->get_var( "
  SELECT COUNT(*) FROM {$wpdb->posts}
  WHERE post_type IN ($in_types) AND post_status IN ('publish','draft','private','pending','future')
    AND ID NOT IN (SELECT object_id FROM {$wpdb->term_relationships} WHERE term_taxonomy_id IN ($in_tt))" );
echo "  kalan dilsiz icerik: $kalan\n";
