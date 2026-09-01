<?php
/**
 * chestnyznak: giden e-postanin TUM alici ve gonderen adreslerini tek politikada toplar.
 *
 * KULLANICI KARARI (01.09.2026, WhatsApp):
 *   - Ekip bildirimleri  -> To: info@chestnyznak.com.tr, Cc: oguzk@chestnyznak.com.tr
 *   - WordPress yonetici e-postasi (`admin_email`) DEGISMEZ: swordbros@gmail.com
 *   - "Butun o admin disindaki mailleri belirttigim adreslere ayarla"
 *
 * POLITIKA
 *   ALICI  : admin_email disindaki her alici -> To info@, Cc oguzk@
 *   GONDEREN: alan adi chestnyznak.com.tr DISINDA olan her gonderen -> info@
 *             (`wordpress@chestnyznak.com.tr` dogru alan adinda oldugu icin
 *              DOKUNULMAZ; SPF/DKIM zaten bu alan adina kurulu)
 *   BCC     : chestnyznak.com.tr disindaki bcc adresleri kaldirilir
 *             (belirtilen ikili zaten cc'de)
 *
 * TEK ISTISNA — OTOMATIK YANITLAR
 *   FluentForm 3, 4 ve 8'in bildiriminde `sendTo.type = field`; alici
 *   BASVURANIN kendisidir. Oraya ekip adresi yazmak musteriye giden yaniti
 *   kirar. Bu yuzden `sendTo` DOKUNULMAZ, ekip kopyasi yalnizca `cc`'ye eklenir.
 *
 * Calistirma (once dry):
 *   wp --path=<kok> eval-file eposta-alicilari.php dry
 *   wp --path=<kok> eval-file eposta-alicilari.php
 *
 * Yeniden calistirilabilir: zaten dogru olan ayar "guncel" diye atlanir.
 */

$dry = ( ( $args[0] ?? '' ) === 'dry' );

const FD_EKIP_TO   = 'info@chestnyznak.com.tr';
const FD_EKIP_CC   = 'oguzk@chestnyznak.com.tr';
const FD_ALAN      = 'chestnyznak.com.tr';

global $wpdb;
$yonetici = get_option( 'admin_email' );
$sayac    = [ 'degisen' => 0, 'guncel' => 0 ];

/** Bir adres bizim alan adimizda mi? */
function fd_bizim_alan( $adres ) {
	return (bool) preg_match( '/@' . preg_quote( FD_ALAN, '/' ) . '$/i', trim( (string) $adres ) );
}

/** Virgullu listeden bizim alan adimiz disindakileri atar. */
function fd_yabanci_ayikla( $liste ) {
	$kalan = [];
	foreach ( array_map( 'trim', explode( ',', (string) $liste ) ) as $a ) {
		if ( '' !== $a && fd_bizim_alan( $a ) ) {
			$kalan[] = $a;
		}
	}
	return implode( ', ', array_unique( $kalan ) );
}

/** "Ad <adres>" bicimindeki gondereni, alan adi yabanciysa duzeltir. */
function fd_gonderen_duzelt( $gonderen, $site_adi ) {
	$gonderen = (string) $gonderen;
	if ( ! preg_match( '/<([^>]+)>/', $gonderen, $m ) ) {
		return fd_bizim_alan( $gonderen ) ? $gonderen : FD_EKIP_TO;
	}
	$adres = trim( $m[1] );
	$ad    = trim( str_replace( '<' . $m[1] . '>', '', $gonderen ) );
	if ( '' === $ad || 'Qwery' === $ad ) {
		$ad = $site_adi;      // tema adi degil, site adi
	}
	if ( ! fd_bizim_alan( $adres ) ) {
		$adres = FD_EKIP_TO;
	}
	return $ad . ' <' . $adres . '>';
}

$site_adi = (string) get_option( 'blogname' );

printf( "### admin_email = %s (DEGISMEYECEK)\n", $yonetici );

/* -------------------------------------------------------------------------
 * 1) Bekleyen yonetici e-postasi degisikligini iptal et.
 * ---------------------------------------------------------------------- */
$bekleyen = get_option( 'new_admin_email' );
if ( $bekleyen ) {
	printf( "  bekleyen degisiklik: %s -> IPTAL\n", $bekleyen );
	if ( ! $dry ) {
		delete_option( 'new_admin_email' );
		delete_option( 'adminhash' );
	}
	$sayac['degisen']++;
} else {
	echo "  bekleyen degisiklik yok\n";
	$sayac['guncel']++;
}

/* -------------------------------------------------------------------------
 * 2) Contact Form 7 — TUM formlar (demo formlar dahil: `test@fwe.com`).
 * ---------------------------------------------------------------------- */
echo "\n### Contact Form 7\n";
foreach ( get_posts( [ 'post_type' => 'wpcf7_contact_form', 'numberposts' => -1, 'post_status' => 'any' ] ) as $f ) {
	foreach ( [ '_mail', '_mail_2' ] as $anahtar ) {
		$posta = get_post_meta( $f->ID, $anahtar, true );
		if ( ! is_array( $posta ) || empty( $posta ) ) {
			continue;
		}
		if ( '_mail_2' === $anahtar && empty( $posta['active'] ) ) {
			continue;   // ikinci posta kapali
		}

		$eski = $posta;

		/* `_mail_2` musteriye giden otomatik yanittir: alicisi form alanidir. */
		$otomatik_yanit = ( '_mail_2' === $anahtar );

		if ( ! $otomatik_yanit ) {
			$posta['recipient'] = FD_EKIP_TO;
		}

		$basliklar = (string) ( $posta['additional_headers'] ?? '' );
		$basliklar = preg_replace( '/^\s*Cc:.*$/mi', '', $basliklar );
		$basliklar = trim( trim( $basliklar ) . "\nCc: " . FD_EKIP_CC );
		$posta['additional_headers'] = $basliklar;

		$posta['sender'] = fd_gonderen_duzelt( $posta['sender'] ?? '', $site_adi );

		if ( $posta === $eski ) {
			$sayac['guncel']++;
			continue;
		}
		printf(
			"  #%-6d %-30s %-8s to: %s -> %s\n",
			$f->ID, mb_substr( $f->post_title, 0, 30 ), $anahtar,
			$eski['recipient'] ?? '-',
			$otomatik_yanit ? '(form alani, degismedi)' : FD_EKIP_TO
		);
		printf( "         cc: %s   gonderen: %s\n", FD_EKIP_CC, $posta['sender'] );
		$sayac['degisen']++;
		if ( $dry ) {
			continue;
		}
		update_post_meta( $f->ID, $anahtar, $posta );
		if ( get_post_meta( $f->ID, $anahtar, true ) != $posta ) {
			echo "         KAYIT DOGRULAMASI BASARISIZ\n";
			return;
		}
	}
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
	$tur = $v['sendTo']['type'] ?? '';

	if ( 'field' === $tur ) {
		/* Otomatik yanit: alici basvuran. sendTo'ya DOKUNMA. */
		$cc = array_unique( array_filter( array_merge(
			array_map( 'trim', explode( ',', (string) ( $v['cc'] ?? '' ) ) ),
			[ FD_EKIP_TO, FD_EKIP_CC ]
		) ) );
		$v['cc'] = implode( ', ', $cc );
		$aciklama = 'otomatik yanit';
	} else {
		$v['sendTo']['email'] = FD_EKIP_TO;
		$v['cc']              = FD_EKIP_CC;
		$aciklama = 'ekip bildirimi';
	}

	/* Yabanci alan adindaki bcc / gonderen / yanit adresi. */
	$v['bcc'] = fd_yabanci_ayikla( $v['bcc'] ?? '' );
	foreach ( [ 'fromEmail', 'replyTo' ] as $k ) {
		$mevcut = trim( (string) ( $v[ $k ] ?? '' ) );
		if ( '' !== $mevcut && ! fd_bizim_alan( $mevcut ) ) {
			$v[ $k ] = FD_EKIP_TO;
		}
	}

	$yeni = wp_json_encode( $v );
	if ( $yeni === $r->value ) {
		$sayac['guncel']++;
		continue;
	}
	$oncesi = json_decode( $r->value, true );
	printf(
		"  form #%-3d %-30s %s%s\n         to : %s -> %s\n         cc : %s\n         bcc: %s -> %s\n         from/replyTo: %s / %s\n",
		$r->form_id, mb_substr( $r->title, 0, 30 ), $aciklama,
		empty( $v['enabled'] ) ? ' [KAPALI]' : '',
		$oncesi['sendTo']['email'] ?? '(alan)',
		'field' === $tur ? '(alan, degismedi)' : FD_EKIP_TO,
		$v['cc'],
		( $oncesi['bcc'] ?? '' ) ?: '-',
		$v['bcc'] ?: '(bos)',
		$v['fromEmail'] ?: '(varsayilan)',
		$v['replyTo'] ?: '(varsayilan)'
	);
	$sayac['degisen']++;
	if ( $dry ) {
		continue;
	}
	$wpdb->update( $wpdb->prefix . 'fluentform_form_meta', [ 'value' => $yeni ], [ 'id' => $r->id ] );
	if ( $wpdb->get_var( $wpdb->prepare( "SELECT value FROM {$wpdb->prefix}fluentform_form_meta WHERE id=%d", $r->id ) ) !== $yeni ) {
		echo "         KAYIT DOGRULAMASI BASARISIZ\n";
		return;
	}
}

/* -------------------------------------------------------------------------
 * 4) WooCommerce — siparis/stok bildirimleri ve gonderen kimligi.
 *    Siparis e-postalarinin alicisi BOS birakilirsa WooCommerce admin_email'e
 *    duser; kullanici bunlarin da ekip kutusuna gitmesini istedi, bu yuzden
 *    acikca yazilir.
 * ---------------------------------------------------------------------- */
echo "\n### WooCommerce\n";
$woo_alici = FD_EKIP_TO . ', ' . FD_EKIP_CC;   // Woo'da ayri Cc alani yok
$woo = [
	'woocommerce_email_from_address'   => FD_EKIP_TO,
	'woocommerce_email_from_name'      => $site_adi,
	'woocommerce_stock_email_recipient' => $woo_alici,
];
foreach ( $woo as $ad => $deger ) {
	$mevcut = get_option( $ad );
	if ( (string) $mevcut === (string) $deger ) {
		$sayac['guncel']++;
		continue;
	}
	printf( "  %-36s %s -> %s\n", $ad, $mevcut ?: '(bos)', $deger );
	$sayac['degisen']++;
	if ( ! $dry ) {
		update_option( $ad, $deger );
	}
}
foreach ( [ 'new_order', 'cancelled_order', 'failed_order' ] as $k ) {
	$ad  = 'woocommerce_' . $k . '_settings';
	$o   = get_option( $ad );
	$o   = is_array( $o ) ? $o : [];
	if ( ( $o['recipient'] ?? '' ) === $woo_alici ) {
		$sayac['guncel']++;
		continue;
	}
	printf( "  %-36s %s -> %s\n", $ad . '[recipient]', ( $o['recipient'] ?? '' ) ?: '(bos -> admin_email)', $woo_alici );
	$o['recipient'] = $woo_alici;
	$sayac['degisen']++;
	if ( ! $dry ) {
		update_option( $ad, $o );
	}
}

/* -------------------------------------------------------------------------
 * 5) Tema/eklenti demo kalintilari.
 *    `widget_trx_addons_widget_contacts` GORUNEN bir adres (mailto), alici
 *    ayari degil; widget su an `wp_inactive_widgets` icinde. Yine de demo
 *    adresi birakmiyoruz. DIKKAT: ayni widget'taki adres ve telefon HALA
 *    demo verisi (Berlin adresi, +1 telefon) — widget kullanilacaksa onlar
 *    da elle duzeltilmeli.
 * ---------------------------------------------------------------------- */
echo "\n### Demo kalintilari\n";
$booked = get_option( 'booked_email_force_sender_from' );
if ( false !== $booked && ! fd_bizim_alan( $booked ) ) {
	printf( "  %-36s %s -> %s\n", 'booked_email_force_sender_from', $booked, FD_EKIP_TO );
	$sayac['degisen']++;
	if ( ! $dry ) {
		update_option( 'booked_email_force_sender_from', FD_EKIP_TO );
	}
} else {
	$sayac['guncel']++;
}

$kisiler = get_option( 'widget_trx_addons_widget_contacts' );
if ( is_array( $kisiler ) ) {
	$degisti = false;
	foreach ( $kisiler as $i => $w ) {
		if ( is_array( $w ) && ! empty( $w['email'] ) && ! fd_bizim_alan( $w['email'] ) ) {
			printf( "  %-36s %s -> %s\n", "widget_contacts[$i][email]", $w['email'], FD_EKIP_TO );
			$kisiler[ $i ]['email'] = FD_EKIP_TO;
			$degisti = true;
			$sayac['degisen']++;
		}
	}
	if ( $degisti && ! $dry ) {
		update_option( 'widget_trx_addons_widget_contacts', $kisiler );
	}
	if ( ! $degisti ) {
		$sayac['guncel']++;
	}
}

/* -------------------------------------------------------------------------
 * 6) "E-postayla paylas" dugmesi — bu bir ALICI AYARI DEGILDIR.
 *
 * `trx_addons_options[share][*][url]` icinde `mailto:test@fwe.com?...` duruyordu.
 * Bu baglanti ZIYARETCININ posta istemcisini acar; oraya bir adres yazmak
 * "yaziyi paylas" dugmesini "bize e-posta gonder"e cevirir. Dogrusu alici
 * kismini BOS birakmaktir: `mailto:?subject=...` — ziyaretci kime
 * gonderecegini kendisi secer. Bu yuzden ekip adresi YAZILMAZ, adres silinir.
 * ---------------------------------------------------------------------- */
echo "\n### Paylas dugmesi (mailto)\n";
$tema = get_option( 'trx_addons_options' );
$mailto_degisti = false;
$mailto_temizle = function ( &$veri ) use ( &$mailto_temizle, &$mailto_degisti ) {
	foreach ( $veri as $k => &$v ) {
		if ( is_string( $v ) && 0 === strpos( $v, 'mailto:' ) ) {
			$yeni = preg_replace( '/^mailto:[^?]*/', 'mailto:', $v );
			if ( $yeni !== $v ) {
				printf( "  %-20s %s -> %s\n", $k, $v, $yeni );
				$v = $yeni;
				$mailto_degisti = true;
			}
		} elseif ( is_array( $v ) ) {
			$mailto_temizle( $v );
		}
	}
	unset( $v );
};
if ( is_array( $tema ) ) {
	$mailto_temizle( $tema );
	if ( $mailto_degisti ) {
		$sayac['degisen']++;
		if ( ! $dry ) {
			update_option( 'trx_addons_options', $tema );
		}
	} else {
		echo "  (guncel)\n";
		$sayac['guncel']++;
	}
}

printf( "\ndegisen: %d, zaten guncel: %d\n", $sayac['degisen'], $sayac['guncel'] );
if ( $dry ) {
	echo "DRY-RUN — yazilmadi.\n";
	return;
}
wp_cache_flush();
echo "TAMAM\n";
