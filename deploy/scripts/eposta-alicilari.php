<?php
/**
 * chestnyznak: form bildirimlerinin alicilarini tek yerde toplar.
 *
 * KULLANICI KARARI (01.09.2026, WhatsApp):
 *   - Ekip bildirimleri  -> To: info@chestnyznak.com.tr, Cc: oguzk@chestnyznak.com.tr
 *   - WordPress yonetici e-postasi (`admin_email`) DEGISMEZ: swordbros@gmail.com
 *   - Panelde bekleyen "yonetici e-postasini info@ yap" degisikligi IPTAL edilir
 *
 * NE DEGISIR
 *   1. `new_admin_email` secenegi silinir (bekleyen degisiklik iptal).
 *   2. Contact Form 7 "main" formlari (TR/EN/RU): alici = info@, baslikta Cc = oguzk@.
 *      Kisisel gmail adresleri (swordali@, blgnklc@) alicidan CIKARILIR.
 *   3. FluentForm bildirimleri:
 *      - EKIP bildirimi (`sendTo.type = email`) -> To: info@, Cc: oguzk@
 *      - MUSTERIYE OTOMATIK YANIT (`sendTo.type = field`) -> `sendTo` DOKUNULMAZ,
 *        yalnizca `cc` alanina ekip cifti eklenir. Otomatik yanitin alicisi
 *        basvuranin kendisidir; oraya ekip adresi yazmak yaniti kirar.
 *      - `bcc` alanlarina DOKUNULMAZ (mevcut ic dagitim korunur).
 *
 * NE DEGISMEZ
 *   - `admin_email`
 *   - WooCommerce siparis e-postalari (magazada fiyat/siparis akisi yok;
 *     ayri bir karar)
 *   - Temanin demo CF7 formlari (`test@fwe.com`) — kullanilmiyor
 *
 * Calistirma (once dry):
 *   wp --path=<kok> eval-file 16-eposta-alicilari.php dry
 */

$dry = ( ( $args[0] ?? '' ) === 'dry' );

const FD_EKIP_TO = 'info@chestnyznak.com.tr';
const FD_EKIP_CC = 'oguzk@chestnyznak.com.tr';

/** Alicidan cikarilacak kisisel adresler (kullanici bunlari ikili ile degistirdi). */
function fd_eposta_cikar() {
	return [ 'swordali@gmail.com', 'blgnklc@gmail.com' ];
}

global $wpdb;

/* -------------------------------------------------------------------------
 * 1) Bekleyen yonetici e-postasi degisikligini iptal et.
 * ---------------------------------------------------------------------- */
$bekleyen = get_option( 'new_admin_email' );
$yonetici = get_option( 'admin_email' );
printf( "### admin_email = %s (degismeyecek)\n", $yonetici );
if ( $bekleyen ) {
	printf( "  bekleyen degisiklik: %s -> IPTAL\n", $bekleyen );
	if ( ! $dry ) {
		delete_option( 'new_admin_email' );
		delete_option( 'adminhash' );   // onay bagini da gecersiz kil
	}
} else {
	echo "  bekleyen degisiklik yok\n";
}

/* -------------------------------------------------------------------------
 * 2) Contact Form 7
 * ---------------------------------------------------------------------- */
echo "\n### Contact Form 7\n";
foreach ( get_posts( [ 'post_type' => 'wpcf7_contact_form', 'numberposts' => -1, 'post_status' => 'any' ] ) as $f ) {
	$posta = (array) get_post_meta( $f->ID, '_mail', true );
	$alici = (string) ( $posta['recipient'] ?? '' );

	/* Yalnizca gercek ekip formlarina dokun; demo formlar test@fwe.com. */
	if ( false === stripos( $alici, 'chestnyznak.com.tr' ) ) {
		continue;
	}

	$basliklar = (string) ( $posta['additional_headers'] ?? '' );
	$yeni_baslik = preg_replace( '/^\s*Cc:.*$/mi', '', $basliklar );
	$yeni_baslik = trim( $yeni_baslik ) . "\nCc: " . FD_EKIP_CC;
	$yeni_baslik = trim( $yeni_baslik );

	$degisti = ( FD_EKIP_TO !== $alici ) || ( $yeni_baslik !== trim( $basliklar ) );
	printf( "  #%-6d %-32s\n", $f->ID, mb_substr( $f->post_title, 0, 32 ) );
	printf( "      to : %s\n      -> : %s\n", $alici, FD_EKIP_TO );
	printf( "      cc : %s\n", FD_EKIP_CC );
	if ( ! $degisti ) {
		echo "      (zaten guncel)\n";
		continue;
	}
	if ( $dry ) {
		continue;
	}
	$posta['recipient']          = FD_EKIP_TO;
	$posta['additional_headers'] = $yeni_baslik;
	update_post_meta( $f->ID, '_mail', $posta );

	$geri = (array) get_post_meta( $f->ID, '_mail', true );
	if ( FD_EKIP_TO !== ( $geri['recipient'] ?? '' ) ) {
		echo "      KAYIT DOGRULAMASI BASARISIZ\n";
		return;
	}
	echo "      yazildi\n";
}

/* -------------------------------------------------------------------------
 * 3) FluentForm bildirimleri
 * ---------------------------------------------------------------------- */
echo "\n### FluentForm bildirimleri\n";
$satirlar = $wpdb->get_results(
	"SELECT m.id, m.form_id, m.value, f.title
	   FROM {$wpdb->prefix}fluentform_form_meta m
	   JOIN {$wpdb->prefix}fluentform_forms f ON f.id = m.form_id
	  WHERE m.meta_key = 'notifications'"
);
foreach ( $satirlar as $r ) {
	$v = json_decode( $r->value, true );
	if ( ! is_array( $v ) ) {
		continue;
	}
	$tur    = $v['sendTo']['type'] ?? '';
	$eski_to = (string) ( $v['sendTo']['email'] ?? '' );
	$eski_cc = (string) ( $v['cc'] ?? '' );

	if ( 'field' === $tur ) {
		/* Musteriye otomatik yanit: alici basvuran, dokunma. Ekip kopyasi cc. */
		$cc = array_values( array_unique( array_filter( array_merge(
			array_map( 'trim', explode( ',', $eski_cc ) ),
			[ FD_EKIP_TO, FD_EKIP_CC ]
		) ) ) );
		$cc = array_values( array_diff( $cc, fd_eposta_cikar() ) );
		$v['cc'] = implode( ', ', $cc );
		$aciklama = 'otomatik yanit — sendTo korundu';
	} else {
		$v['sendTo']['email'] = FD_EKIP_TO;
		$v['cc']              = FD_EKIP_CC;
		$aciklama = 'ekip bildirimi';
	}

	$degisti = ( wp_json_encode( $v ) !== $r->value );
	printf(
		"  form #%-3d %-34s %s%s\n      to: %s -> %s\n      cc: %s -> %s\n",
		$r->form_id,
		mb_substr( $r->title, 0, 34 ),
		$aciklama,
		empty( $v['enabled'] ) ? ' [KAPALI]' : '',
		$eski_to ?: '(alan)',
		'field' === $tur ? '(alan, degismedi)' : FD_EKIP_TO,
		$eski_cc ?: '-',
		$v['cc']
	);
	if ( ! $degisti ) {
		echo "      (zaten guncel)\n";
		continue;
	}
	if ( $dry ) {
		continue;
	}
	$yeni = wp_json_encode( $v );
	$wpdb->update( $wpdb->prefix . 'fluentform_form_meta', [ 'value' => $yeni ], [ 'id' => $r->id ] );
	$geri = $wpdb->get_var( $wpdb->prepare( "SELECT value FROM {$wpdb->prefix}fluentform_form_meta WHERE id=%d", $r->id ) );
	if ( $geri !== $yeni ) {
		echo "      KAYIT DOGRULAMASI BASARISIZ\n";
		return;
	}
	echo "      yazildi\n";
}

if ( $dry ) {
	echo "\nDRY-RUN — yazilmadi.\n";
	return;
}
wp_cache_flush();
echo "\nTAMAM\n";
