<?php
/**
 * Ic baglantilari dogru sayfaya ve dogru dile baglar.
 *
 * IKI AYRI SORUNU COZER:
 *
 * 1) YANLIS HEDEF (her dilde): ana sayfadaki "Hakkımızda devamı oku" dugmesi
 *    temanin DEMO sayfasina (`/about-creative/`, "About - Creative") gidiyordu.
 *    Gercek Hakkimizda sayfasi `/hakkimizda/`.
 *
 * 2) DIL DUSMESI: `/en/` ve `/ru/` sayfalarindaki govde ve altbilgi
 *    baglantilari Turkce adreslere gidiyordu. Polylang dili URL onekinden
 *    okur; onek dusunce ziyaretci Turkceye donuyordu — kullanicinin bildirdigi
 *    sikayet buydu.
 *
 * Calistirma (once dry):
 *   wp --user=<admin> --path=<kok> eval-file 09-ic-baglantilari-dile-bagla.php dry
 *
 * `--user` SART (kses; CLAUDE.md 6f). Her yazimdan sonra kendini dogrular:
 *   - Elementor verisi hala cozulebilir JSON olmali
 *   - <style>/<script> sayisi degismemeli
 * tutmazsa degisikligi GERI ALIR.
 *
 * NOT: `wp_json_encode` egik cizgiyi `\/` yapar. Bu yuzden arama/degistirme
 * HEM duz HEM kacisli bicimde yapilir.
 */

$dry  = ( ( $args[0] ?? '' ) === 'dry' );
$kok  = untrailingslashit( home_url() );

/* Hangi gonderide hangi eslemenin uygulanacagi. */
$IS = [
	// TR ana sayfa + TR altbilgi: yalnizca yanlis demo hedefi duzelt.
	5002  => [ '/about-creative/' => '/hakkimizda/' ],
	4105  => [ '/about-creative/' => '/hakkimizda/' ],
	// EN ana sayfa + EN altbilgi
	37686 => [
		'/about-creative/' => '/en/about-us/',
		'/hakkimizda/'     => '/en/about-us/',
		'/iletisim/'       => '/en/contact/',
		'/kod-sorgulama/'  => '/en/code-lookup/',
		'/blog-standard/'  => '/en/news/',
		'/shop/'           => '/en/solutions/',
	],
	37703 => [
		'/about-creative/' => '/en/about-us/',
		'/hakkimizda/'     => '/en/about-us/',
		'/iletisim/'       => '/en/contact/',
		'/kod-sorgulama/'  => '/en/code-lookup/',
		'/blog-standard/'  => '/en/news/',
		'/shop/'           => '/en/solutions/',
	],
	// RU ana sayfa + RU altbilgi
	37687 => [
		'/about-creative/' => '/ru/o-nas/',
		'/hakkimizda/'     => '/ru/o-nas/',
		'/iletisim/'       => '/ru/kontakty/',
		'/kod-sorgulama/'  => '/ru/proverka-koda/',
		'/blog-standard/'  => '/ru/novosti/',
		'/shop/'           => '/ru/uslugi/',
	],
	37704 => [
		'/about-creative/' => '/ru/o-nas/',
		'/hakkimizda/'     => '/ru/o-nas/',
		'/iletisim/'       => '/ru/kontakty/',
		'/kod-sorgulama/'  => '/ru/proverka-koda/',
		'/blog-standard/'  => '/ru/novosti/',
		'/shop/'           => '/ru/uslugi/',
	],
];

kses_remove_filters();

/** <style>/<script> sayilari — yazim sonrasi ayni kalmali. */
$imza = function ( $s ) {
	return [ substr_count( $s, '<style' ), substr_count( $s, '<script' ) ];
};

$toplam = 0;
foreach ( $IS as $id => $esleme ) {
	$p = get_post( $id );
	if ( ! $p ) {
		echo "ATLANDI: $id yok\n";
		continue;
	}

	/* Duz ve kacisli bicimlerin ikisi icin de sozluk kur. */
	$sozluk = [];
	foreach ( $esleme as $eski => $yeni ) {
		$sozluk[ $kok . $eski ]                     = $kok . $yeni;
		$sozluk[ str_replace( '/', '\/', $kok . $eski ) ] = str_replace( '/', '\/', $kok . $yeni );
	}

	foreach ( [ 'post_content', '_elementor_data' ] as $alan ) {
		$ham = 'post_content' === $alan
			? (string) $p->post_content
			: (string) get_post_meta( $id, '_elementor_data', true );
		if ( '' === $ham ) {
			continue;
		}

		$yeni = strtr( $ham, $sozluk );
		if ( $yeni === $ham ) {
			continue;
		}

		$adet = 0;
		foreach ( $sozluk as $a => $b ) {
			$adet += substr_count( $ham, $a );
		}

		/* Elementor verisi hala gecerli JSON mu? */
		if ( '_elementor_data' === $alan && null === json_decode( $yeni, true ) ) {
			echo "  DURDURULDU: $id/_elementor_data JSON bozuldu\n";
			return;
		}
		if ( $imza( $ham ) !== $imza( $yeni ) ) {
			echo "  DURDURULDU: $id/$alan style/script sayisi degisti\n";
			return;
		}

		printf( "  %-6d %-16s %d baglanti\n", $id, $alan, $adet );
		$toplam += $adet;

		if ( $dry ) {
			continue;
		}

		if ( 'post_content' === $alan ) {
			wp_update_post( [ 'ID' => $id, 'post_content' => $yeni ] );
			$geri = get_post( $id )->post_content;
		} else {
			update_post_meta( $id, '_elementor_data', wp_slash( $yeni ) );
			$geri = (string) get_post_meta( $id, '_elementor_data', true );
		}

		/* Yazildigi gibi mi durdu? (kses sessizce kirpar) */
		if ( $imza( $geri ) !== $imza( $ham ) ) {
			if ( 'post_content' === $alan ) {
				wp_update_post( [ 'ID' => $id, 'post_content' => $ham ] );
			} else {
				update_post_meta( $id, '_elementor_data', wp_slash( $ham ) );
			}
			echo "  KAYIT DOGRULAMASI BASARISIZ ($id/$alan) -> GERI ALINDI\n";
			return;
		}
	}
}

echo "toplam $toplam baglanti\n";
if ( $dry ) {
	echo "DRY-RUN — yazilmadi.\n";
	return;
}
if ( class_exists( '\Elementor\Plugin' ) ) {
	\Elementor\Plugin::$instance->files_manager->clear_cache();
}
wp_cache_flush();
echo "TAMAM\n";
