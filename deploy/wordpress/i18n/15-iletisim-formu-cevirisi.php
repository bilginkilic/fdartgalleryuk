<?php
/**
 * Iletisim sayfasindaki Contact Form 7 formunun EN/RU kopyalarini uretir ve
 * cevrilmis iletisim sayfalarindaki kisa kodu o kopyayla degistirir.
 *
 * NEDEN: CF7 formu tek bir kayittir; Polylang onu cevirmez. Sonuc olarak
 * `/en/contact/` ve `/ru/kontakty/` sayfalarinda alan adlari, buton ve
 * onay metni Turkce kaliyordu (olculdu: /en/contact/ gorunur metninde
 * "Gonderdigim kisisel verilerin ... kabul ediyorum" gibi 10 Turkce kelime).
 *
 * NE KOPYALANIR: form alanlari, e-posta sablonu ve kullaniciya gosterilen
 * mesajlar. ALICI ADRESLER AYNEN KORUNUR — ceviri sirasinda adres degistirmek
 * gelen basvurulari kaybettirir.
 *
 * Calistirma (once dry):
 *   wp --user=<admin> --path=<kok> eval-file 15-iletisim-formu-cevirisi.php dry
 */

$dry = ( ( $args[0] ?? '' ) === 'dry' );
$P   = json_decode( file_get_contents( '/tmp/iletisim-formu.json' ), true );
if ( ! $P || empty( $P['diller'] ) ) {
	echo "HATA: yuk bozuk\n";
	return;
}
if ( ! class_exists( 'WPCF7_ContactForm' ) ) {
	echo "HATA: Contact Form 7 yok\n";
	return;
}
kses_remove_filters();

$KAYNAK  = (int) $P['kaynak_form'];
$SAYFALAR = $P['sayfalar'];   // dil => cevrilmis iletisim sayfasi ID

$kaynak = get_post( $KAYNAK );
if ( ! $kaynak || 'wpcf7_contact_form' !== $kaynak->post_type ) {
	echo "HATA: kaynak form $KAYNAK yok\n";
	return;
}
echo "### kaynak form #$KAYNAK — {$kaynak->post_title}\n";

$kaynak_hash = (string) get_post_meta( $KAYNAK, '_hash', true );

foreach ( $P['diller'] as $dil => $m ) {
	$sayfa_id = (int) ( $SAYFALAR[ $dil ] ?? 0 );
	if ( ! $sayfa_id || ! get_post( $sayfa_id ) ) {
		echo "  ATLANDI ($dil): sayfa yok\n";
		continue;
	}

	/* Zaten uretilmis mi? */
	$var = get_posts(
		[
			'post_type'   => 'wpcf7_contact_form',
			'post_status' => 'any',
			'numberposts' => 1,
			'title'       => $m['baslik'],
		]
	);
	if ( $var ) {
		$form_id = (int) $var[0]->ID;
		echo "  $dil form zaten var: $form_id\n";
	} else {
		printf( "  %s form olusturulacak: %s\n", $dil, $m['baslik'] );
		if ( $dry ) {
			continue;
		}
		$form_id = wp_insert_post(
			[
				'post_type'   => 'wpcf7_contact_form',
				'post_status' => 'publish',
				'post_title'  => $m['baslik'],
				'post_name'   => sanitize_title( $m['baslik'] ),
			],
			true
		);
		if ( is_wp_error( $form_id ) ) {
			echo '  HATA: ' . $form_id->get_error_message() . "\n";
			return;
		}
		/* Kaynagin tum ayarlarini kopyala, sonra cevrilenleri ez. */
		foreach ( get_post_meta( $KAYNAK ) as $anahtar => $degerler ) {
			if ( '_hash' === $anahtar ) {
				continue;
			}
			update_post_meta( $form_id, $anahtar, maybe_unserialize( $degerler[0] ) );
		}
		update_post_meta( $form_id, '_form', $m['form'] );

		$posta = (array) get_post_meta( $KAYNAK, '_mail', true );
		$posta['subject'] = $m['konu'];
		$posta['body']    = $m['govde'];
		update_post_meta( $form_id, '_mail', $posta );   // alici ADRESLER korunur

		$mesajlar = (array) get_post_meta( $KAYNAK, '_messages', true );
		foreach ( $m['mesajlar'] as $k => $v ) {
			$mesajlar[ $k ] = $v;
		}
		update_post_meta( $form_id, '_messages', $mesajlar );

		update_post_meta( $form_id, '_hash', wp_generate_password( 40, false, false ) );
		echo "  olusturuldu $dil: $form_id\n";
	}

	if ( $dry ) {
		continue;
	}

	/* Sayfadaki kisa kodu yeni formla degistir. */
	$hash    = (string) get_post_meta( $form_id, '_hash', true );
	$sayfa   = get_post( $sayfa_id );
	$icerik  = $sayfa->post_content;
	$yenisi  = preg_replace(
		'/\[contact-form-7[^\]]*\]/',
		'[contact-form-7 id="' . $hash . '" title="' . esc_attr( $m['baslik'] ) . '"]',
		$icerik,
		-1,
		$adet
	);
	if ( ! $adet ) {
		echo "  UYARI ($dil): sayfada kisa kod bulunamadi\n";
		continue;
	}
	wp_update_post( [ 'ID' => $sayfa_id, 'post_content' => $yenisi ] );
	if ( false === strpos( get_post( $sayfa_id )->post_content, $hash ) ) {
		wp_update_post( [ 'ID' => $sayfa_id, 'post_content' => $icerik ] );
		echo "  KAYIT DOGRULAMASI BASARISIZ ($dil) -> GERI ALINDI\n";
		return;
	}
	echo "  sayfa $sayfa_id kisa kodu guncellendi ($adet adet)\n";
}

if ( $dry ) {
	echo "DRY-RUN — yazilmadi.\n";
	return;
}
wp_cache_flush();
echo "TAMAM\n";
