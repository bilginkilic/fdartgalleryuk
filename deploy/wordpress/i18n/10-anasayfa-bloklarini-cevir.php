<?php
/**
 * Cevrilmis ana sayfalardaki (37686 en / 37687 ru) cz-* bloklarini cevirir.
 *
 * NEDEN: ana sayfanin gorunur bolumlerinin bir kismi tek bir HTML widget'inda
 * duran sablon + script ciftlerinden uretiliyor (cz-vals, cz-stats, cz-faq,
 * cz-sectors, cz-audit). Dil kopyalari bu widget'i OLDUGU GIBI aldigi icin
 * EN/RU sayfalarda o bloklar Turkce kaliyordu.
 *
 * Metinler HEM HTML sablonunda HEM script icindeki dizelerde ayni gectigi
 * icin tek bir strtr her ikisini birden cevirir.
 *
 * TUZAK: `_elementor_data` JSON'unda Turkce harfler `ı` bicimindedir.
 * Ham dize uzerinde strtr yapmak yalnizca ASCII anahtarlari yakalar (ilk
 * denemede 56 anahtardan 6'si esletti). Bu yuzden JSON ONCE COZULUR, dizeler
 * PHP tarafinda (gercek UTF-8) cevrilir, sonra yeniden kodlanir.
 *
 * Calistirma (once dry):
 *   wp --user=<admin> --path=<kok> eval-file 10-anasayfa-bloklarini-cevir.php dry
 *
 * `--user` SART (kses; CLAUDE.md 6f). Yazimdan sonra kendini dogrular:
 *   - JSON hala cozulebilir olmali
 *   - <style>/<script> sayilari degismemeli
 * tutmazsa GERI ALIR.
 */

$dry = ( ( $args[0] ?? '' ) === 'dry' );
$P   = json_decode( file_get_contents( '/tmp/anasayfa-bloklari.json' ), true );
if ( ! $P || empty( $P['en'] ) || empty( $P['ru'] ) ) {
	echo "HATA: yuk bozuk\n";
	return;
}
kses_remove_filters();

$SAYFA = [ 'en' => 37686, 'ru' => 37687 ];

$imza = function ( $s ) {
	return [ substr_count( $s, '<style' ), substr_count( $s, '<script' ) ];
};

foreach ( $SAYFA as $dil => $id ) {
	$ham = (string) get_post_meta( $id, '_elementor_data', true );
	if ( '' === $ham ) {
		echo "ATLANDI: $id elementor verisi yok\n";
		continue;
	}
	$sozluk = $P[ $dil ];

	$veri = json_decode( $ham, true );
	if ( ! is_array( $veri ) ) {
		echo "  DURDURULDU ($dil): kaynak JSON cozulemedi\n";
		return;
	}

	$adet = 0;
	$cevir = function ( &$dugum ) use ( &$cevir, $sozluk, &$adet ) {
		foreach ( $dugum as &$v ) {
			if ( is_string( $v ) ) {
				foreach ( $sozluk as $tr => $_ ) {
					$adet += substr_count( $v, $tr );
				}
				$v = strtr( $v, $sozluk );
			} elseif ( is_array( $v ) ) {
				$cevir( $v );
			}
		}
	};
	$cevir( $veri );

	$yeni = wp_json_encode( $veri );
	if ( ! is_string( $yeni ) ) {
		echo "  DURDURULDU ($dil): yeniden kodlanamadi\n";
		return;
	}

	$kalan = 0;
	$say = function ( $dugum ) use ( &$say, $sozluk ) {
		$n = 0;
		foreach ( $dugum as $v ) {
			if ( is_string( $v ) ) {
				foreach ( $sozluk as $tr => $_ ) {
					$n += substr_count( $v, $tr );
				}
			} elseif ( is_array( $v ) ) {
				$n += $say( $v );
			}
		}
		return $n;
	};
	$kalan = $say( json_decode( $yeni, true ) );
	if ( $imza( $ham ) !== $imza( $yeni ) ) {
		echo "  DURDURULDU ($dil): style/script sayisi degisti\n";
		return;
	}

	printf( "  %s (%d): %d degisim, kalan %d  (%d -> %d bayt)\n", $dil, $id, $adet, $kalan, strlen( $ham ), strlen( $yeni ) );

	if ( $dry ) {
		continue;
	}

	update_post_meta( $id, '_elementor_data', wp_slash( $yeni ) );
	$geri = (string) get_post_meta( $id, '_elementor_data', true );
	if ( $imza( $geri ) !== $imza( $ham ) || null === json_decode( $geri, true ) ) {
		update_post_meta( $id, '_elementor_data', wp_slash( $ham ) );
		echo "  KAYIT DOGRULAMASI BASARISIZ ($dil) -> GERI ALINDI\n";
		return;
	}
	delete_post_meta( $id, '_elementor_element_cache' );
	echo "  yazildi: $id\n";
}

if ( $dry ) {
	echo "DRY-RUN — yazilmadi.\n";
	return;
}
if ( class_exists( '\Elementor\Plugin' ) ) {
	\Elementor\Plugin::$instance->files_manager->clear_cache();
}
wp_cache_flush();
echo "TAMAM\n";
