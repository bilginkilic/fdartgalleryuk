<?php
/**
 * Sag alt kosedeki sohbet formunu ve WhatsApp dugmesini dile baglar.
 *
 * SORUN (kullanici, ekran goruntusu /ru/uslugi/): iki yuzen dugme her dilde
 * Turkce. Ikisi de tema widget'indaki `cz-wa` ve `cz-contact` bloklarindan
 * geliyor:
 *   - WhatsApp dugmesi, WhatsApp'i TURKCE hazir mesajla aciyordu.
 *   - Sari sohbet dugmesi bir IFRAME aciyor; iframe'in kaynagi
 *     `/site-iletisim-formu/` (ID 37546) — Turkce bir canvas sayfasi ve
 *     icinde Turkce FluentForm (#10).
 * Widget metinlerinin bir kismi zaten `fd-i18n-widget-sozluk.json` ile
 * cevriliyordu; eksik olan IFRAME SAYFASININ KENDISIYDI.
 *
 * BU SCRIPT NE YAPAR
 *   1. FluentForm #10'un EN/RU kopyalarini uretir (etiket, yer tutucu, buton,
 *      tesekkur mesaji cevrilir; ALICI/BILDIRIM ayarlari AYNEN kopyalanir —
 *      adres degistirmek gelen basvurulari kaybettirir, bkz. CLAUDE.md 6m).
 *   2. `/site-iletisim-formu/` sayfasinin EN/RU kopyalarini uretir; her biri
 *      kendi dilindeki formu gomer. Sayfa `elementor_canvas` sablonunda
 *      kalir — iframe icinde menu/altbilgi basilmamali (CLAUDE.md 6h).
 *   3. Polylang ceviri baglantilarini kurar.
 *
 * Iframe ADRESI burada degistirilmez: widget uc dilde de ayni basildigi icin
 * adres cevirisi `fd-i18n-layouts.php` icindeki `fd_i18n_yol_esleme()`
 * haritasinda yapilir (sunucu tarafinda).
 *
 * Calistirma (once dry):
 *   wp --user=<admin> --path=<kok> eval-file 16-sohbet-formu-cevirisi.php dry
 *
 * Yeniden calistirilabilir: mevcut ceviri atlanir.
 */

$dry = ( ( $args[0] ?? '' ) === 'dry' );
$P   = json_decode( file_get_contents( '/tmp/sohbet-formu.json' ), true );
if ( ! $P || empty( $P['diller'] ) ) {
	echo "HATA: yuk bozuk\n";
	return;
}
if ( ! function_exists( 'pll_set_post_language' ) ) {
	echo "HATA: Polylang yok\n";
	return;
}
kses_remove_filters();
global $wpdb;

/* ID'ler ELLE YAZILMAZ — slug ve kisa koddan cozulur. */
$sayfa = get_page_by_path( $P['kaynak_sayfa_slug'] );
if ( ! $sayfa ) {
	echo "HATA: /{$P['kaynak_sayfa_slug']}/ sayfasi yok\n";
	return;
}
if ( ! preg_match( '/\[fluentform\s+id=["\']?(\d+)/', $sayfa->post_content, $m ) ) {
	echo "HATA: sayfada [fluentform id=..] kisa kodu yok\n";
	return;
}
$form_id = (int) $m[1];
printf( "### kaynak sayfa #%d (/%s/), form #%d\n", $sayfa->ID, $sayfa->post_name, $form_id );

$kaynak_form = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}fluentform_forms WHERE id=%d", $form_id ) );
if ( ! $kaynak_form ) {
	echo "HATA: form #$form_id yok\n";
	return;
}

/** Bir FluentForm alan agacindaki gorunur metinleri cevirir. */
function fd_ff_cevir( array $alanlar, array $sozluk, &$sayac ) {
	foreach ( $alanlar as &$a ) {
		foreach ( [ [ 'settings', 'label' ], [ 'settings', 'placeholder' ], [ 'settings', 'help_message' ], [ 'attributes', 'placeholder' ] ] as $yol ) {
			list( $u, $v ) = $yol;
			if ( isset( $a[ $u ][ $v ] ) && is_string( $a[ $u ][ $v ] ) && isset( $sozluk[ $a[ $u ][ $v ] ] ) ) {
				$a[ $u ][ $v ] = $sozluk[ $a[ $u ][ $v ] ];
				$sayac++;
			}
		}
		foreach ( [ 'columns', 'fields' ] as $k ) {
			if ( 'columns' === $k && ! empty( $a['columns'] ) ) {
				foreach ( $a['columns'] as &$kol ) {
					if ( ! empty( $kol['fields'] ) ) {
						$kol['fields'] = fd_ff_cevir( $kol['fields'], $sozluk, $sayac );
					}
				}
				unset( $kol );
			} elseif ( 'fields' === $k && ! empty( $a['fields'] ) ) {
				$a['fields'] = fd_ff_cevir( $a['fields'], $sozluk, $sayac );
			}
		}
	}
	unset( $a );
	return $alanlar;
}

$ceviri_sayfa = [ 'tr' => (int) $sayfa->ID ];

foreach ( $P['diller'] as $dil => $m2 ) {
	/* ---------- 1) Form kopyasi ---------- */
	$var = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}fluentform_forms WHERE title=%s", $m2['form_basligi'] ) );
	if ( $var ) {
		$yeni_form_id = (int) $var->id;
		echo "  $dil form zaten var: #$yeni_form_id\n";
	} else {
		$alanlar = json_decode( $kaynak_form->form_fields, true );
		$sayac   = 0;
		if ( ! empty( $alanlar['fields'] ) ) {
			$alanlar['fields'] = fd_ff_cevir( $alanlar['fields'], $m2['alanlar'], $sayac );
		}
		$alanlar['submitButton']['settings']['button_ui']['text'] = $m2['buton'];

		printf( "  %s form: %s (%d alan metni cevrildi)\n", $dil, $m2['form_basligi'], $sayac );
		if ( $dry ) {
			$yeni_form_id = 0;
		} else {
			/* Sutunlar ELLE SAYILMAZ — FluentForm surumune gore degisir.
			   (Ilk denemede olmayan bir `form_meta` sutunu yazilmaya calisildi
			   ve insert sessizce basarisiz oldu.) Kaynak satirin sutunlari
			   okunur, yalnizca degisenler ezilir. */
			$satir = (array) $kaynak_form;
			unset( $satir['id'] );
			$satir['title']       = $m2['form_basligi'];
			$satir['form_fields'] = wp_json_encode( $alanlar );
			$satir['created_at']  = current_time( 'mysql' );
			$satir['updated_at']  = current_time( 'mysql' );

			$wpdb->insert( $wpdb->prefix . 'fluentform_forms', $satir );
			$yeni_form_id = (int) $wpdb->insert_id;
			if ( ! $yeni_form_id ) {
				echo '  HATA: form kopyalanamadi: ' . $wpdb->last_error . "\n";
				return;
			}

			/* Meta satirlari: bildirim ayarlari AYNEN kopyalanir (alicilar korunur),
			   yalnizca tesekkur mesaji ve bildirim konusu cevrilir. */
			$metalar = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}fluentform_form_meta WHERE form_id=%d", $form_id ) );
			foreach ( $metalar as $meta ) {
				$deger = $meta->value;
				if ( 'formSettings' === $meta->meta_key ) {
					$v = json_decode( $deger, true );
					if ( is_array( $v ) ) {
						$v['confirmation']['messageToShow'] = $m2['tesekkur'];
						$deger = wp_json_encode( $v );
					}
				} elseif ( 'notifications' === $meta->meta_key ) {
					$v = json_decode( $deger, true );
					if ( is_array( $v ) && ! empty( $m2['bildirim_konusu'] ) ) {
						$v['subject'] = $m2['bildirim_konusu'];
						$deger = wp_json_encode( $v );
					}
				}
				$wpdb->insert(
					$wpdb->prefix . 'fluentform_form_meta',
					[ 'form_id' => $yeni_form_id, 'meta_key' => $meta->meta_key, 'value' => $deger ]
				);
			}
			echo "  olusturuldu $dil form: #$yeni_form_id\n";
		}
	}

	/* ---------- 2) Sayfa kopyasi ---------- */
	$mevcut = pll_get_post( $sayfa->ID, $dil );
	if ( $mevcut && 'publish' === get_post_status( $mevcut ) ) {
		echo "  $dil sayfa zaten var: $mevcut\n";
		$ceviri_sayfa[ $dil ] = (int) $mevcut;
		continue;
	}
	printf( "  %s sayfa: %s (/%s/%s/)\n", $dil, $m2['sayfa_basligi'], $dil, $m2['sayfa_slug'] );
	if ( $dry ) {
		continue;
	}

	$icerik = preg_replace(
		'/\[fluentform\s+id=["\']?\d+["\']?\s*\]/',
		'[fluentform id="' . $yeni_form_id . '"]',
		$sayfa->post_content,
		-1,
		$adet
	);
	if ( ! $adet ) {
		echo "  HATA: kisa kod degistirilemedi\n";
		return;
	}

	$id = wp_insert_post(
		[
			'post_type'    => 'page',
			'post_status'  => 'publish',
			'post_author'  => (int) $sayfa->post_author,
			'post_title'   => $m2['sayfa_basligi'],
			'post_name'    => $m2['sayfa_slug'],
			'post_content' => $icerik,
		],
		true
	);
	if ( is_wp_error( $id ) ) {
		echo '  HATA: ' . $id->get_error_message() . "\n";
		return;
	}
	/* Sablon SART: canvas degilse iframe icinde menu ve altbilgi de basilir. */
	foreach ( [ '_wp_page_template', 'trx_addons_options', 'qwery_options' ] as $mk ) {
		$v = get_post_meta( $sayfa->ID, $mk, true );
		if ( '' !== $v && false !== $v ) {
			update_post_meta( $id, $mk, $v );
		}
	}

	$yazilan = get_post( $id )->post_content;
	if ( false === strpos( $yazilan, '[fluentform' ) || false === strpos( $yazilan, '<style>' ) ) {
		wp_delete_post( $id, true );
		echo "  KAYIT DOGRULAMASI BASARISIZ (kisa kod veya style kayboldu) -> silindi\n";
		return;
	}

	pll_set_post_language( $id, $dil );
	$ceviri_sayfa[ $dil ] = $id;
	echo "  olusturuldu $dil sayfa: $id\n";
}

if ( $dry ) {
	echo "DRY-RUN — yazilmadi.\n";
	return;
}
if ( count( $ceviri_sayfa ) === 3 ) {
	pll_save_post_translations( $ceviri_sayfa );
	echo 'ceviri baglantisi: ' . wp_json_encode( $ceviri_sayfa ) . "\n";
}
wp_cache_flush();
echo "TAMAM\n";
