<?php
/**
 * Plugin Name: FD Urun Gorseli srcset Temizligi
 * Description: Urun kucuk resimlerinin srcset'inden eski temadan kalan
 *              `woocommerce_thumbnail_preview` (1000x1000) adayini cikarir.
 * Version:     1.0
 *
 * SORUN: `woocommerce_thumbnail` 1000 -> 600 kuculdukten sonra bile mobil
 * tarayici 1000x1000'i indirmeye devam ediyordu. Sebep srcset'te duran
 * **artik** bir aday: `woocommerce_thumbnail_preview` (1000x1000). Bu boyut
 * artik ne temada ne eklentide kayitli — yalnizca ek verisinde (postmeta)
 * duruyor ve `wp_calculate_image_srcset()` ayni en-boy oraninda oldugu icin
 * onu da aday yapiyor. Uzerine `.webp` kopyasi da yok, yani en agir dosya
 * (153 KB) seciliyordu; 600'luk webp 45 KB.
 *
 * `sizes` duzeltmesi TEK BASINA yetmez: mobilde slot ~299 CSS px, DPR 2 ile
 * ~600 px gerekiyor ve tarayici gerekenden KUCUK olani secmez. 618 px gerekse
 * 600w'yi atlayip 1000w'ye ciker. Guvenli olan, adayi listeden cikarmak.
 *
 * Dosyalar diskte DURUYOR, silinmiyor; yalnizca srcset'te onerilmiyorlar.
 * Kaldirmak icin: bu dosyayi silin.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_filter(
	'wp_calculate_image_srcset',
	function ( $sources, $size_array, $image_src, $image_meta ) {
		if ( ! is_array( $sources ) || empty( $image_meta['sizes']['woocommerce_thumbnail_preview']['file'] ) ) {
			return $sources;
		}

		$artik = $image_meta['sizes']['woocommerce_thumbnail_preview']['file'];

		foreach ( $sources as $en => $kaynak ) {
			$dosya = basename( parse_url( $kaynak['url'], PHP_URL_PATH ) );
			// fd-webp-rewrite adresi `.webp` yapmis olabilir.
			if ( $dosya === $artik || $dosya === $artik . '.webp' ) {
				unset( $sources[ $en ] );
			}
		}

		return $sources;
	},
	20,
	4
);
