<?php
/**
 * fdartgallery.com — haftalik seckiyi yazi olarak olusturur (varsayilan TASLAK).
 *
 * Yuk: /tmp/secki-yuk.json  (ornek: 2026-10-sonbahar-tablolari.json)
 * {
 *   "slug","baslik","ozet","meta_aciklama","icerik_dosya",
 *   "kapak_urun": 3633,          // kapak = bu urunun one cikan gorseli
 *   "durum": "draft"|"publish",  // varsayilan draft
 *   "yazar": 1
 * }
 *
 * Calistirma (DAIMA once dry):
 *   wp --user=fdsanat --path=<kok> eval-file seckiler-yayinla.php dry
 *   wp --user=fdsanat --path=<kok> eval-file seckiler-yayinla.php
 *
 * `--user` SART (kses). Yeniden calistirilabilir: ayni slug varsa dokunmaz.
 * Kategori ve kisa kodlar `fd-blog-seckiler.php` mu-plugin'inden gelir —
 * o dosya kurulu degilse script durur (kisa kodlar ham metin basilirdi).
 */

$dry = ( ( $args[0] ?? '' ) === 'dry' );
$m   = json_decode( (string) file_get_contents( '/tmp/secki-yuk.json' ), true );
foreach ( [ 'slug', 'baslik', 'icerik_dosya' ] as $z ) {
	if ( empty( $m[ $z ] ) ) {
		printf( "HATA: yukte '%s' yok\n", $z );
		return;
	}
}
if ( ! shortcode_exists( 'fd_eser' ) || ! defined( 'FD_SECKI_KAT' ) ) {
	echo "HATA: fd-blog-seckiler.php mu-plugin'i yuklu degil.\n";
	return;
}
$kat = get_term_by( 'slug', FD_SECKI_KAT, 'category' );
if ( ! $kat ) {
	echo "HATA: '" . FD_SECKI_KAT . "' kategorisi yok (mu-plugin init'i bir kez calismali).\n";
	return;
}
if ( $var = get_page_by_path( $m['slug'], OBJECT, 'post' ) ) {
	printf( "zaten var: #%d /%s/ (%s)\n", $var->ID, $var->post_name, $var->post_status );
	return;
}

$icerik = (string) file_get_contents( $m['icerik_dosya'] );
if ( strlen( $icerik ) < 2000 ) {
	printf( "HATA: icerik cok kisa (%d bayt)\n", strlen( $icerik ) );
	return;
}

// Yazida gecen her eser yayinda mi? Satilmis olabilir (kart bunu kendisi
// gosterir) ama SILINMIS/taslak eser kartsiz kalir — once burada gorulsun.
preg_match_all( '/\[fd_eser\s+[^\]]*id="?(\d+)/', $icerik, $e );
$sorun = 0;
foreach ( $e[1] as $id ) {
	$u = function_exists( 'wc_get_product' ) ? wc_get_product( (int) $id ) : null;
	$d = $u ? $u->get_status() . ( $u->is_in_stock() ? ', stokta' : ', SATILMIS' ) : 'YOK';
	printf( "  eser #%-5d %-28s %s\n", $id, $u ? mb_substr( wp_strip_all_tags( $u->get_name() ), 0, 28 ) : '-', $d );
	if ( ! $u || 'publish' !== $u->get_status() ) {
		$sorun++;
	}
}
if ( $sorun ) {
	printf( "HATA: %d eser yayinda degil — yaziyi duzeltin.\n", $sorun );
	return;
}

$kapak = 0;
if ( ! empty( $m['kapak_urun'] ) ) {
	$kapak = (int) get_post_thumbnail_id( (int) $m['kapak_urun'] );
}
$durum = ( ( $m['durum'] ?? 'draft' ) === 'publish' ) ? 'publish' : 'draft';

printf( "### %s\n  /%s/ — %d bayt, %d h2, %d eser, kapak ek #%d, durum %s\n",
	$m['baslik'], $m['slug'], strlen( $icerik ), substr_count( $icerik, '<h2' ),
	count( $e[1] ), $kapak, $durum );
if ( mb_strlen( $m['meta_aciklama'] ?? '' ) > 155 ) {
	printf( "  UYARI: meta aciklama %d karakter (>155)\n", mb_strlen( $m['meta_aciklama'] ) );
}
if ( $dry ) {
	echo "DRY-RUN — yazilmadi.\n";
	return;
}

kses_remove_filters();
$id = wp_insert_post( [
	'post_type'     => 'post',
	'post_status'   => $durum,
	'post_name'     => $m['slug'],
	'post_title'    => $m['baslik'],
	'post_excerpt'  => $m['ozet'] ?? '',
	'post_content'  => $icerik,
	'post_author'   => (int) ( $m['yazar'] ?? 1 ),
	'post_category' => [ (int) $kat->term_id ],
	'comment_status'=> 'closed',
], true );
if ( is_wp_error( $id ) ) {
	echo 'HATA: ' . $id->get_error_message() . "\n";
	return;
}
if ( $kapak ) {
	set_post_thumbnail( $id, $kapak );
}
if ( ! empty( $m['meta_aciklama'] ) ) {
	update_post_meta( $id, '_fd_meta_aciklama', $m['meta_aciklama'] );
	// XStore SEO acik: <meta name="description"> bu alandan okunur; bos kalirsa
	// sitenin genel sloganini basar (butun yazilarda ayni aciklama).
	update_post_meta( $id, '_et_meta_description', $m['meta_aciklama'] );
}

// Dogrulama: geri oku.
$p = get_post( $id );
printf( "yazildi: #%d %s — icerik %d bayt (kayipsiz: %s), kapak %s, kategori %s\n",
	$id, get_permalink( $id ), strlen( $p->post_content ),
	strlen( $p->post_content ) === strlen( $icerik ) ? 'evet' : 'HAYIR',
	get_post_thumbnail_id( $id ) ?: 'YOK',
	has_term( FD_SECKI_KAT, 'category', $id ) ? 'tamam' : 'YOK' );
