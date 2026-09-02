<?php
/**
 * Bir blog yazisinin EN/RU cevirilerini yayinlar ve Polylang ile baglar.
 *
 * NEDEN VAR: UC DIL KURALI (CLAUDE.md 7) — siteye eklenen her yazi Turkce +
 * English + Русский olarak eklenir. Bu adimlar elle yapildiginda kolayca
 * atlaniyor (kategori, one cikan gorsel, Yoast alanlari, dil isareti ve
 * ceviri baglantisi), bu yuzden tek arada toplandi.
 *
 * SCRIPTE POST ID YAZILMAZ (CLAUDE.md 6k): Turkce yazi SLUG ile bulunur, dev
 * ve canlida kimlikler farklidir.
 *
 * WP-CLI'nin `--porcelain` ciktisi bu kurulumda GUVENILIR DEGIL: Elementor'un
 * shutdown kaydedicisi STDOUT'a bir deprecation uyarisi basiyor ve kimlik
 * yakalayan `$(...)` bunu da aliyor. Bu yuzden olusturma islemi kabuktan
 * degil, buradan yapilir.
 *
 * Yuk: /tmp/ceviri-yuk.json
 * {
 *   "tr_slug": "...",
 *   "diller": {
 *     "en": { "slug","baslik","ozet","yoast_baslik","yoast_desc",
 *             "icerik_dosya","kapak","kapak_baslik","kapak_alt" },
 *     "ru": { ... }
 *   }
 * }
 *
 * Calistirma (DAIMA once dry):
 *   wp --user=<admin> --path=<kok> eval-file ceviri-yayinla.php dry
 *   wp --user=<admin> --path=<kok> eval-file ceviri-yayinla.php
 *
 * `--user` SART: kses aksi halde icerikteki bazi etiketleri sessizce siler.
 * Yeniden calistirilabilir: mevcut ceviri atlanir.
 */

$dry = ( ( $args[0] ?? '' ) === 'dry' );
$P   = json_decode( (string) file_get_contents( '/tmp/ceviri-yuk.json' ), true );
if ( ! $P || empty( $P['tr_slug'] ) || empty( $P['diller'] ) ) {
	echo "HATA: yuk bozuk\n";
	return;
}
if ( ! function_exists( 'pll_set_post_language' ) ) {
	echo "HATA: Polylang yok\n";
	return;
}
kses_remove_filters();

$tr = get_page_by_path( $P['tr_slug'], OBJECT, 'post' );
if ( ! $tr ) {
	printf( "HATA: Turkce yazi bulunamadi: %s\n", $P['tr_slug'] );
	return;
}
printf( "### Turkce kaynak #%d — %s\n", $tr->ID, $tr->post_title );

$ceviri = [ 'tr' => (int) $tr->ID ];

foreach ( $P['diller'] as $dil => $m ) {
	$mevcut = pll_get_post( $tr->ID, $dil );
	if ( $mevcut && 'publish' === get_post_status( $mevcut ) ) {
		printf( "  %s zaten var: #%d\n", $dil, $mevcut );
		$ceviri[ $dil ] = (int) $mevcut;
		continue;
	}

	$icerik = (string) file_get_contents( $m['icerik_dosya'] );
	if ( strlen( $icerik ) < 2000 ) {
		printf( "  HATA: %s icerigi cok kisa (%d bayt)\n", $dil, strlen( $icerik ) );
		return;
	}
	printf( "  %s: %s (/%s/%s/) — %d bayt icerik\n", $dil, $m['baslik'], $dil, $m['slug'], strlen( $icerik ) );
	if ( $dry ) {
		continue;
	}

	/* --- kapak gorseli --- */
	$kapak_id = 0;
	if ( ! empty( $m['kapak'] ) && file_exists( $m['kapak'] ) ) {
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		$gecici = wp_tempnam( basename( $m['kapak'] ) );
		copy( $m['kapak'], $gecici );
		$dosya = [ 'name' => $m['slug'] . '.png', 'tmp_name' => $gecici ];
		$kapak_id = media_handle_sideload( $dosya, 0, $m['kapak_baslik'] ?? $m['baslik'] );
		if ( is_wp_error( $kapak_id ) ) {
			printf( "  UYARI: kapak yuklenemedi: %s\n", $kapak_id->get_error_message() );
			$kapak_id = 0;
		} else {
			update_post_meta( $kapak_id, '_wp_attachment_image_alt', $m['kapak_alt'] ?? $m['baslik'] );
			printf( "    kapak: #%d\n", $kapak_id );
		}
	}

	/* --- yazi --- */
	$id = wp_insert_post(
		[
			'post_type'    => 'post',
			'post_status'  => 'publish',
			'post_author'  => (int) $tr->post_author,
			'post_title'   => $m['baslik'],
			'post_name'    => $m['slug'],
			'post_content' => $icerik,
			'post_excerpt' => (string) ( $m['ozet'] ?? '' ),
		],
		true
	);
	if ( is_wp_error( $id ) ) {
		printf( "  HATA: %s\n", $id->get_error_message() );
		return;
	}

	/* --- yazildigi gibi mi durdu? kses icerigi kirpabilir --- */
	$yazilan = get_post( $id )->post_content;
	if ( strlen( $yazilan ) < strlen( $icerik ) * 0.9 ) {
		wp_delete_post( $id, true );
		printf( "  KAYIT DOGRULAMASI BASARISIZ: %d -> %d bayt, silindi\n", strlen( $icerik ), strlen( $yazilan ) );
		return;
	}

	/* --- dil ONCE isaretlenir: Polylang, dil atandiktan sonra atanan bir
	       kategoriyi kendiliginden O DILDEKI karsiligina esler. Sira ters
	       olursa terim Turkce kalir. --- */
	pll_set_post_language( $id, $dil );

	/* --- kategori --- */
	$terimler = [];
	foreach ( get_the_category( $tr->ID ) as $kat ) {
		$kat_ceviri = function_exists( 'pll_get_term' ) ? pll_get_term( $kat->term_id, $dil ) : 0;
		$terimler[] = (int) ( $kat_ceviri ?: $kat->term_id );
	}
	if ( $terimler ) {
		/* `append` MUTLAKA false: true birakilirsa WordPress'in ekleme aninda
		   atadigi varsayilan kategori ("uncategorized") uzerinde kalir.
		   02.09.2026'da dort yazi bu yuzden `modern,uncategorized` oldu. */
		wp_set_post_terms( $id, $terimler, 'category', false );
	}

	if ( $kapak_id ) {
		set_post_thumbnail( $id, $kapak_id );
	}
	if ( ! empty( $m['yoast_baslik'] ) ) {
		update_post_meta( $id, '_yoast_wpseo_title', $m['yoast_baslik'] );
	}
	if ( ! empty( $m['yoast_desc'] ) ) {
		update_post_meta( $id, '_yoast_wpseo_metadesc', $m['yoast_desc'] );
	}

	$ceviri[ $dil ] = (int) $id;
	printf( "    olusturuldu: #%d\n", $id );
}

if ( $dry ) {
	echo "DRY-RUN — yazilmadi.\n";
	return;
}

if ( count( $ceviri ) >= 2 ) {
	pll_save_post_translations( $ceviri );
	printf( "ceviri baglantisi: %s\n", wp_json_encode( $ceviri ) );
}
wp_cache_flush();
echo "TAMAM\n";
