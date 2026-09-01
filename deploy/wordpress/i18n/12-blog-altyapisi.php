<?php
/**
 * Blogun uc dilli altyapisi: arsiv sayfasi, kategori ve etiket cevirileri.
 *
 * NEDEN: yazilarin cevirisi tek basina yetmez. Polylang'de bir EN yazi ancak
 * EN bir kategoriye baglanabilir; `page_for_posts` de dile gore cozulur.
 * Bu script yazilardan ONCE calisir ve iskeleti kurar:
 *
 *   1. Blog arsiv sayfasi (`page_for_posts`) -> EN /en/news/, RU /ru/novosti/
 *   2. Kategoriler -> EN/RU karsiliklari (`ceviriler` sozlugunden)
 *   3. Etiketler   -> EN/RU karsiliklari
 *   4. EN/RU menulerindeki "News"/"Новости" ogesi yeni arsive baglanir
 *
 * Calistirma (once dry):
 *   wp --user=<admin> --path=<kok> eval-file 12-blog-altyapisi.php dry
 */

$dry = ( ( $args[0] ?? '' ) === 'dry' );
$P   = json_decode( file_get_contents( '/tmp/blog-altyapisi.json' ), true );
if ( ! $P ) {
	echo "HATA: yuk bozuk\n";
	return;
}
if ( ! function_exists( 'pll_set_post_language' ) ) {
	echo "HATA: Polylang yok\n";
	return;
}
kses_remove_filters();

/* Menu ID'leri de ELLE YAZILMAZ — Polylang dil basina ayri menu konumu acar
   (`menu_main___en`). Konumdan cozulur. */
$MENU = [];
$konumlar = get_nav_menu_locations();
foreach ( [ 'en', 'ru' ] as $dil ) {
	foreach ( [ 'menu_main___' . $dil, 'primary___' . $dil, 'menu_mobile___' . $dil ] as $konum ) {
		if ( ! empty( $konumlar[ $konum ] ) ) {
			$MENU[ $dil ] = (int) $konumlar[ $konum ];
			break;
		}
	}
}
printf( "cozulen menuler: %s\n", wp_json_encode( $MENU ) );

/* -------------------------------------------------------------------------
 * 1) Blog arsiv sayfasi
 * ---------------------------------------------------------------------- */
$arsiv_id = (int) get_option( 'page_for_posts' );
if ( ! $arsiv_id ) {
	echo "HATA: page_for_posts tanimsiz\n";
	return;
}
$arsiv = get_post( $arsiv_id );
echo "### arsiv sayfasi #$arsiv_id — {$arsiv->post_title} (/{$arsiv->post_name}/)\n";

$arsiv_ceviri = [ 'tr' => $arsiv_id ];
foreach ( [ 'en', 'ru' ] as $dil ) {
	$mevcut = pll_get_post( $arsiv_id, $dil );
	if ( $mevcut && get_post_status( $mevcut ) === 'publish' ) {
		echo "  $dil zaten var: $mevcut\n";
		$arsiv_ceviri[ $dil ] = (int) $mevcut;
		continue;
	}
	$m = $P['arsiv'][ $dil ];
	printf( "  %s olusturulacak: %s (/%s/%s/)\n", $dil, $m['baslik'], $dil, $m['slug'] );
	if ( $dry ) {
		continue;
	}
	$id = wp_insert_post(
		[
			'post_type'    => 'page',
			'post_status'  => 'publish',
			'post_author'  => (int) $arsiv->post_author,
			'post_title'   => $m['baslik'],
			'post_name'    => $m['slug'],
			'post_content' => (string) $arsiv->post_content,
		],
		true
	);
	if ( is_wp_error( $id ) ) {
		echo '  HATA: ' . $id->get_error_message() . "\n";
		return;
	}
	foreach ( [ '_wp_page_template', 'trx_addons_options', 'qwery_options', '_thumbnail_id' ] as $mk ) {
		$v = get_post_meta( $arsiv_id, $mk, true );
		if ( '' !== $v && false !== $v ) {
			update_post_meta( $id, $mk, $v );
		}
	}
	pll_set_post_language( $id, $dil );
	$arsiv_ceviri[ $dil ] = $id;
	echo "  olusturuldu $dil: $id\n";
}
if ( ! $dry && count( $arsiv_ceviri ) === 3 ) {
	pll_save_post_translations( $arsiv_ceviri );
}

/* -------------------------------------------------------------------------
 * 2-3) Kategori ve etiket cevirileri
 * ---------------------------------------------------------------------- */
foreach ( [ 'category', 'post_tag' ] as $tax ) {
	$sozluk = $P[ $tax ] ?? [];
	echo "### $tax (" . count( $sozluk ) . " terim)\n";
	foreach ( $sozluk as $tr_slug => $diller ) {
		$tr_term = get_term_by( 'slug', $tr_slug, $tax );
		if ( ! $tr_term ) {
			echo "  ATLANDI: '$tr_slug' bulunamadi\n";
			continue;
		}
		$ceviri = [ 'tr' => (int) $tr_term->term_id ];
		foreach ( [ 'en', 'ru' ] as $dil ) {
			$mevcut = pll_get_term( $tr_term->term_id, $dil );
			if ( $mevcut ) {
				$ceviri[ $dil ] = (int) $mevcut;
				continue;
			}
			$m = $diller[ $dil ];
			if ( $dry ) {
				printf( "  %-34s %s -> %s\n", $tr_term->name, $dil, $m['ad'] );
				continue;
			}
			$yeni = wp_insert_term( $m['ad'], $tax, [ 'slug' => $m['slug'] ] );
			if ( is_wp_error( $yeni ) ) {
				// Ayni slug varsa onu kullan.
				$v = get_term_by( 'slug', $m['slug'], $tax );
				if ( ! $v ) {
					echo '  HATA: ' . $yeni->get_error_message() . "\n";
					return;
				}
				$yeni = [ 'term_id' => $v->term_id ];
			}
			pll_set_term_language( (int) $yeni['term_id'], $dil );
			$ceviri[ $dil ] = (int) $yeni['term_id'];
			printf( "  %-34s %s -> %s (#%d)\n", $tr_term->name, $dil, $m['ad'], $yeni['term_id'] );
		}
		if ( ! $dry && count( $ceviri ) === 3 ) {
			pll_save_term_translations( $ceviri );
		}
	}
}

if ( $dry ) {
	echo "DRY-RUN — yazilmadi.\n";
	return;
}

/* -------------------------------------------------------------------------
 * 4) EN/RU menulerinde arsive giden ogeyi yeni adrese bagla.
 * ---------------------------------------------------------------------- */
$eski = untrailingslashit( wp_make_link_relative( get_permalink( $arsiv_id ) ) );
foreach ( $MENU as $dil => $mid ) {
	if ( empty( $arsiv_ceviri[ $dil ] ) ) {
		continue;
	}
	$yeni_url = get_permalink( $arsiv_ceviri[ $dil ] );
	$degisti  = 0;
	foreach ( (array) wp_get_nav_menu_items( $mid ) as $oge ) {
		if ( 'custom' !== $oge->type ) {
			continue;
		}
		if ( untrailingslashit( wp_make_link_relative( $oge->url ) ) === $eski ) {
			update_post_meta( $oge->ID, '_menu_item_url', $yeni_url );
			$degisti++;
		}
	}
	echo "menu $mid ($dil): $degisti baglanti -> $yeni_url\n";
}

flush_rewrite_rules();
wp_cache_flush();
echo "TAMAM\n";
