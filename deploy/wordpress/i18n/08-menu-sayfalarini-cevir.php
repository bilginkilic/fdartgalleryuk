<?php
/**
 * Menudeki sayfalarin EN/RU cevirilerini uretir ve menuleri onlara baglar.
 *
 * NEDEN: dil degistirip gezinince site Turkceye donuyordu. Sebep basit —
 * `/en/` ve `/ru/` menuleri CEVIRISI OLMAYAN Turkce sayfalara isaret ediyordu.
 * Polylang dili URL onekinden okur; onek dusunce dil de duser. Cozum menuyu
 * kandirmak degil, sayfalari gercekten cevirmektir (CLAUDE.md 7, UC DIL KURALI).
 *
 * KAPSAM: Hakkimizda (37375), Iletisim (37379), Kod Sorgulama (37607).
 * Haberler (blog arsivi) ve Cozumlerimiz (WooCommerce magaza sayfasi) DISARIDA —
 * ikisinin de icerigi 470 Turkce yazi ve WooCommerce urunleri; onlar icin
 * Polylang'in UCRETLI WooCommerce eklentisi ve yazi cevirisi gerekir.
 *
 * Calistirma (DAIMA once dry):
 *   wp --user=<admin> --path=<kok> eval-file 08-menu-sayfalarini-cevir.php dry
 *   wp --user=<admin> --path=<kok> eval-file 08-menu-sayfalarini-cevir.php
 *
 * `--user` SART: kses aksi halde <style>/<script> etiketlerini SESSIZCE siler
 * (CLAUDE.md 6f). Script yazdiktan sonra kendini dogrular, tutmazsa geri alir.
 */

$dry = ( ( $args[0] ?? '' ) === 'dry' );
$P   = json_decode( file_get_contents( '/tmp/menu-sayfalari.json' ), true );
if ( ! $P || empty( $P['sayfalar'] ) || empty( $P['metin'] ) ) {
	echo "HATA: yuk bozuk\n";
	return;
}
if ( ! function_exists( 'pll_set_post_language' ) ) {
	echo "HATA: Polylang yok\n";
	return;
}
kses_remove_filters();

/** Ceviri sonrasi mutlaka ayakta kalmasi gereken isaretler. */
$KORUNACAK = [
	37379 => [ '[contact-form-7', '<iframe', 'tel:+74957970042', 'info@chestnyznak.com.tr' ],
	37607 => [ '<style>', '<script>', 'data-cz="btn"', 'cz-wrap' ],
	37375 => [ 'swordbros.com', 'rtib.org' ],
];

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
$sonuc = [];

foreach ( $P['sayfalar'] as $kaynak_id => $diller ) {
	$kaynak_id = (int) $kaynak_id;
	$kaynak    = get_post( $kaynak_id );
	if ( ! $kaynak ) {
		echo "HATA: kaynak $kaynak_id yok\n";
		return;
	}
	echo "### $kaynak_id — {$kaynak->post_title}\n";

	$ceviri = [ 'tr' => $kaynak_id ];

	foreach ( $diller as $dil => $meta ) {
		$sozluk = $P['metin'][ $dil ] ?? [];
		$icerik = strtr( $kaynak->post_content, $sozluk );

		/* --- 1) Ne kadari cevrildi? --- */
		$degisen = 0;
		foreach ( $sozluk as $tr => $_ ) {
			if ( strpos( $kaynak->post_content, $tr ) !== false ) {
				$degisen++;
			}
		}
		$kalan = [];
		foreach ( $sozluk as $tr => $_ ) {
			if ( strpos( $icerik, $tr ) !== false ) {
				$kalan[] = mb_substr( $tr, 0, 40 );
			}
		}

		/* --- 2) Kritik isaretler ayakta mi? --- */
		$eksik = [];
		foreach ( $KORUNACAK[ $kaynak_id ] ?? [] as $isaret ) {
			if ( strpos( $kaynak->post_content, $isaret ) !== false
				&& strpos( $icerik, $isaret ) === false ) {
				$eksik[] = $isaret;
			}
		}

		printf(
			"  %s: eslesen=%d kalan=%d kayip_isaret=%d  (%d -> %d bayt)\n",
			$dil,
			$degisen,
			count( $kalan ),
			count( $eksik ),
			strlen( $kaynak->post_content ),
			strlen( $icerik )
		);
		if ( $kalan ) {
			echo '     CEVRILMEYEN: ' . implode( ' | ', array_slice( $kalan, 0, 5 ) ) . "\n";
		}
		if ( $eksik ) {
			echo '     DURDURULDU — kaybolan isaret: ' . implode( ', ', $eksik ) . "\n";
			return;
		}

		if ( $dry ) {
			continue;
		}

		/* --- 3) Sayfayi olustur --- */
		$id = wp_insert_post(
			[
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_author'  => (int) $kaynak->post_author,
				'post_title'   => $meta['baslik'],
				'post_name'    => $meta['slug'],
				'post_content' => $icerik,
				'post_excerpt' => strtr( (string) $kaynak->post_excerpt, $sozluk ),
			],
			true
		);
		if ( is_wp_error( $id ) ) {
			echo '  HATA: ' . $id->get_error_message() . "\n";
			return;
		}

		foreach ( [ '_wp_page_template', '_elementor_edit_mode', '_elementor_template_type', '_elementor_version', '_elementor_page_settings', 'trx_addons_options', 'qwery_options', '_thumbnail_id' ] as $mk ) {
			$v = get_post_meta( $kaynak_id, $mk, true );
			if ( '' !== $v && false !== $v ) {
				update_post_meta( $id, $mk, $v );
			}
		}

		/* --- 4) Yazildigi gibi mi durdu? (kses sessizce kirpabilir) --- */
		$yazilan = get_post( $id )->post_content;
		foreach ( $KORUNACAK[ $kaynak_id ] ?? [] as $isaret ) {
			if ( strpos( $icerik, $isaret ) !== false && strpos( $yazilan, $isaret ) === false ) {
				wp_delete_post( $id, true );
				echo "  KAYIT DOGRULAMASI BASARISIZ ($isaret) -> sayfa silindi\n";
				return;
			}
		}

		pll_set_post_language( $id, $dil );
		$ceviri[ $dil ] = $id;
		echo "  olusturuldu $dil: ID $id (/$dil/{$meta['slug']}/)\n";
	}

	if ( $dry ) {
		continue;
	}
	pll_save_post_translations( $ceviri );
	$sonuc[ $kaynak_id ] = $ceviri;
}

if ( $dry ) {
	echo "DRY-RUN — yazilmadi.\n";
	return;
}

/* -------------------------------------------------------------------------
 * 5) EN/RU menulerindeki ozel baglantilari yeni sayfalara cevir.
 *    Oge tipi `custom` kalir; yalnizca URL degisir — boylece menu basliklari
 *    (elle cevrilmis) korunur.
 * ---------------------------------------------------------------------- */
$eski_yeni = [];
foreach ( $sonuc as $kaynak_id => $ceviri ) {
	foreach ( [ 'en', 'ru' ] as $dil ) {
		if ( empty( $ceviri[ $dil ] ) ) {
			continue;
		}
		$eski_yeni[ $dil ][ untrailingslashit( wp_make_link_relative( get_permalink( $kaynak_id ) ) ) ]
			= get_permalink( $ceviri[ $dil ] );
	}
}

foreach ( $MENU as $dil => $mid ) {
	$degisti = 0;
	foreach ( (array) wp_get_nav_menu_items( $mid ) as $oge ) {
		if ( 'custom' !== $oge->type ) {
			continue;
		}
		$rel = untrailingslashit( wp_make_link_relative( $oge->url ) );
		if ( isset( $eski_yeni[ $dil ][ $rel ] ) ) {
			update_post_meta( $oge->ID, '_menu_item_url', $eski_yeni[ $dil ][ $rel ] );
			$degisti++;
		}
	}
	echo "menu $mid ($dil): $degisti baglanti guncellendi\n";
}

if ( class_exists( '\Elementor\Plugin' ) ) {
	\Elementor\Plugin::$instance->files_manager->clear_cache();
}
flush_rewrite_rules();
echo "TAMAM\n";
