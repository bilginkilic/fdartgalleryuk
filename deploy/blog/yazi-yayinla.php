<?php
/**
 * Turkce blog yazisini yayinlar: kapak gorseli, kategori, Yoast alanlari ve
 * Polylang dil isareti tek adimda.
 *
 * NEDEN KABUKTAN DEGIL: wp-cli'nin `--porcelain` ciktisi bu kurulumda
 * GUVENILIR DEGIL. Elementor'un shutdown kaydedicisi STDOUT'a bir deprecation
 * uyarisi basiyor; `AID=$(wp media import ... --porcelain)` bu uyariyi da
 * yakaliyor ve degisken "PHP: 2026-... )]" gibi bir sey oluyor. 02.09.2026'da
 * bu yuzden bir yayin yarim kaldi (yazi ve gorsel olustu ama meta atanamadi).
 *
 * Yuk: /tmp/yazi-yuk.json
 * {
 *   "slug","baslik","ozet","yoast_baslik","yoast_desc",
 *   "icerik_dosya","kapak","kapak_baslik","kapak_alt",
 *   "kategori":"modern", "dil":"tr", "yazar":3
 * }
 *
 * Calistirma (DAIMA once dry):
 *   wp --user=<admin> --path=<kok> eval-file yazi-yayinla.php dry
 *   wp --user=<admin> --path=<kok> eval-file yazi-yayinla.php
 *
 * `--user` SART: kses aksi halde bazi etiketleri sessizce siler.
 * Yeniden calistirilabilir: ayni slug varsa dokunmaz.
 *
 * Ceviriler AYRI adimda: `ceviri-yayinla.php` (UC DIL KURALI, CLAUDE.md 7).
 */

$dry = ( ( $args[0] ?? '' ) === 'dry' );
$m   = json_decode( (string) file_get_contents( '/tmp/yazi-yuk.json' ), true );
foreach ( [ 'slug', 'baslik', 'icerik_dosya' ] as $z ) {
	if ( empty( $m[ $z ] ) ) {
		printf( "HATA: yukte '%s' yok\n", $z );
		return;
	}
}
kses_remove_filters();

$var = get_page_by_path( $m['slug'], OBJECT, 'post' );
if ( $var ) {
	printf( "zaten var: #%d /%s/\n", $var->ID, $var->post_name );
	return;
}

$icerik = (string) file_get_contents( $m['icerik_dosya'] );
if ( strlen( $icerik ) < 2000 ) {
	printf( "HATA: icerik cok kisa (%d bayt)\n", strlen( $icerik ) );
	return;
}
printf( "### %s\n  /%s/  — %d bayt, %d h2\n", $m['baslik'], $m['slug'],
	strlen( $icerik ), substr_count( $icerik, '<h2' ) );

if ( $dry ) {
	echo "DRY-RUN — yazilmadi.\n";
	return;
}

/* --- kapak --- */
$kapak_id = 0;
if ( ! empty( $m['kapak'] ) && file_exists( $m['kapak'] ) ) {
	require_once ABSPATH . 'wp-admin/includes/media.php';
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';
	$gecici = wp_tempnam( basename( $m['kapak'] ) );
	copy( $m['kapak'], $gecici );
	$kapak_id = media_handle_sideload(
		[ 'name' => $m['slug'] . '.png', 'tmp_name' => $gecici ],
		0,
		$m['kapak_baslik'] ?? $m['baslik']
	);
	if ( is_wp_error( $kapak_id ) ) {
		printf( "  UYARI: kapak yuklenemedi: %s\n", $kapak_id->get_error_message() );
		$kapak_id = 0;
	} else {
		update_post_meta( $kapak_id, '_wp_attachment_image_alt', $m['kapak_alt'] ?? $m['baslik'] );
		printf( "  kapak: #%d\n", $kapak_id );
	}
}

/* --- yazi --- */
$id = wp_insert_post(
	[
		'post_type'    => 'post',
		'post_status'  => 'publish',
		'post_author'  => (int) ( $m['yazar'] ?? 3 ),
		'post_title'   => $m['baslik'],
		'post_name'    => $m['slug'],
		'post_content' => $icerik,
		'post_excerpt' => (string) ( $m['ozet'] ?? '' ),
	],
	true
);
if ( is_wp_error( $id ) ) {
	printf( "HATA: %s\n", $id->get_error_message() );
	return;
}

/* --- kses kirpti mi? --- */
$yazilan = get_post( $id )->post_content;
if ( strlen( $yazilan ) < strlen( $icerik ) * 0.9 ) {
	wp_delete_post( $id, true );
	printf( "KAYIT DOGRULAMASI BASARISIZ: %d -> %d bayt, silindi\n", strlen( $icerik ), strlen( $yazilan ) );
	return;
}

/* --- kategori: yalnizca istenen, varsayilan EKLENMEZ --- */
$kat = get_term_by( 'slug', $m['kategori'] ?? 'modern', 'category' );
if ( $kat ) {
	wp_set_post_terms( $id, [ (int) $kat->term_id ], 'category', false );
}
if ( $kapak_id ) {
	set_post_thumbnail( $id, $kapak_id );
}
foreach ( [ 'yoast_baslik' => '_yoast_wpseo_title', 'yoast_desc' => '_yoast_wpseo_metadesc' ] as $k => $meta ) {
	if ( ! empty( $m[ $k ] ) ) {
		update_post_meta( $id, $meta, $m[ $k ] );
	}
}
if ( function_exists( 'pll_set_post_language' ) ) {
	pll_set_post_language( $id, $m['dil'] ?? 'tr' );
}

printf( "olusturuldu: #%d  kategori=%s  gorsel=%s  dil=%s\n", $id,
	implode( ',', wp_list_pluck( get_the_category( $id ), 'slug' ) ) ?: '(YOK)',
	get_post_thumbnail_id( $id ) ?: '(YOK)',
	function_exists( 'pll_get_post_language' ) ? pll_get_post_language( $id ) : '?' );
wp_cache_flush();
echo "TAMAM\n";
