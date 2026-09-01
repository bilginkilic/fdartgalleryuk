<?php
/**
 * Blog yazilarinin EN/RU cevirilerini olusturur ve Polylang'de baglar.
 *
 * NEDEN: dil kalicigi ancak her adresin kendi dilinde bir karsiligi varsa
 * saglanir (bkz. CLAUDE.md 6k). Blog arsivi cevrilse bile icinde o dilde
 * yazi yoksa arsiv BOS gorunur; ayrica ana sayfadaki "Gelismeler & Haberler"
 * bolumu EN/RU'da 1744 px yerine 76 px kaliyordu.
 *
 * Yuk bicimi (/tmp/yazi-cevirileri.json):
 *   { "<tr_post_id>": { "en": {baslik,slug,ozet,icerik}, "ru": {...} }, ... }
 *
 * Calistirma (once dry):
 *   wp --user=<admin> --path=<kok> eval-file 13-yazilari-cevir.php dry
 *
 * Yeniden calistirilabilir: cevirisi zaten olan yazi ATLANIR.
 */

$dry = ( ( $args[0] ?? '' ) === 'dry' );
$P   = json_decode( file_get_contents( '/tmp/yazi-cevirileri.json' ), true );
if ( ! is_array( $P ) || ! $P ) {
	echo "HATA: yuk bozuk\n";
	return;
}
if ( ! function_exists( 'pll_set_post_language' ) ) {
	echo "HATA: Polylang yok\n";
	return;
}
kses_remove_filters();

/**
 * Turkce adreslerin dile gore karsiligi — yazi govdesindeki ic baglantilar.
 * Bazi yazilarda adres MUTLAK yazilmis (https://host/iletisim/), bu yuzden
 * asagida her anahtarin mutlak bicimi de sozluge eklenir.
 */
$YOL = [
	'en' => [
		'/iletisim/'      => '/en/contact/',
		'/hakkimizda/'    => '/en/about-us/',
		'/kod-sorgulama/' => '/en/code-lookup/',
		'/blog-standard/' => '/en/news/',
		'/blog/'          => '/en/news/',
	],
	'ru' => [
		'/iletisim/'      => '/ru/kontakty/',
		'/hakkimizda/'    => '/ru/o-nas/',
		'/kod-sorgulama/' => '/ru/proverka-koda/',
		'/blog-standard/' => '/ru/novosti/',
		'/blog/'          => '/ru/novosti/',
	],
];

/**
 * Yazilar birbirine baglanti veriyor (ornegin "GTIP kodlari rehberimiz").
 * O baglantilar da dile gore cevrilmeli, yoksa okuyucu Turkce yaziya duser.
 * Esleme ELLE YAZILMAZ: zaten var olan cevirilerden ve bu calistirmadaki
 * yuktan uretilir.
 */
foreach ( $P as $tr_id => $diller ) {
	$kaynak = get_post( (int) $tr_id );
	if ( ! $kaynak ) {
		continue;
	}
	foreach ( [ 'en', 'ru' ] as $dil ) {
		$slug = $diller[ $dil ]['slug'] ?? '';
		if ( $slug ) {
			$YOL[ $dil ][ '/' . $kaynak->post_name . '/' ] = '/' . $dil . '/' . $slug . '/';
		}
	}
}
$tum = get_posts( [ 'post_type' => 'post', 'post_status' => 'publish', 'numberposts' => -1, 'lang' => 'tr' ] );
foreach ( $tum as $t ) {
	foreach ( [ 'en', 'ru' ] as $dil ) {
		if ( isset( $YOL[ $dil ][ '/' . $t->post_name . '/' ] ) ) {
			continue;
		}
		$c = pll_get_post( $t->ID, $dil );
		if ( $c && 'publish' === get_post_status( $c ) ) {
			$YOL[ $dil ][ '/' . $t->post_name . '/' ] = '/' . $dil . '/' . get_post( $c )->post_name . '/';
		}
	}
}

/* Mutlak bicimleri de ekle — bazi yazilarda adres tam URL olarak yazilmis. */
$kok = untrailingslashit( home_url() );
foreach ( $YOL as $dil => $harita ) {
	foreach ( $harita as $eski => $yeni ) {
		$YOL[ $dil ][ $kok . $eski ] = $kok . $yeni;
	}
}

$olusan = 0;
$atlanan = 0;

foreach ( $P as $tr_id => $diller ) {
	$tr_id = (int) $tr_id;
	$kaynak = get_post( $tr_id );
	if ( ! $kaynak || 'post' !== $kaynak->post_type ) {
		echo "ATLANDI: $tr_id yazi degil\n";
		continue;
	}
	echo "### $tr_id — " . mb_substr( $kaynak->post_title, 0, 60 ) . "\n";

	$ceviri = [ 'tr' => $tr_id ];

	foreach ( [ 'en', 'ru' ] as $dil ) {
		$mevcut = pll_get_post( $tr_id, $dil );
		if ( $mevcut && get_post_status( $mevcut ) ) {
			echo "  $dil zaten var: $mevcut\n";
			$ceviri[ $dil ] = (int) $mevcut;
			continue;
		}
		if ( empty( $diller[ $dil ]['icerik'] ) ) {
			echo "  $dil ceviri yok, atlandi\n";
			continue;
		}
		$m = $diller[ $dil ];

		/* Ic baglantilari o dilin adreslerine cevir. */
		$icerik = strtr( $m['icerik'], $YOL[ $dil ] );

		/* Denetim: cevrilmemis Turkce adres kaldi mi?
		   Yalnizca href olarak gecenler sayilir; duz metinde gecen bir yol
		   (ornegin bir aciklama icinde) yanlis alarm uretmesin. */
		$kalan_yol = 0;
		$kalan_ornek = [];
		foreach ( array_keys( $YOL[ $dil ] ) as $y ) {
			$n = substr_count( $icerik, 'href="' . $y . '"' );
			if ( $n ) {
				$kalan_yol += $n;
				$kalan_ornek[] = $y;
			}
		}

		/* Denetim: Turkce'ye ozgu harf (govdede olmamali). */
		$duz = wp_strip_all_tags( $icerik );
		$turkce = preg_match_all( '/[çğışÇĞİŞ]/u', $duz );

		printf(
			"  %s  %-42s  %5d bayt  kalan_tr_adres=%d  tr_harf=%d\n",
			$dil,
			mb_substr( $m['baslik'], 0, 42 ),
			strlen( $icerik ),
			$kalan_yol,
			$turkce
		);
		if ( $kalan_yol > 0 ) {
			echo '  DURDURULDU: cevrilmemis ic adres -> ' . implode( ', ', $kalan_ornek ) . "\n";
			return;
		}

		if ( $dry ) {
			continue;
		}

		$id = wp_insert_post(
			[
				'post_type'     => 'post',
				'post_status'   => 'publish',
				'post_author'   => (int) $kaynak->post_author,
				'post_title'    => $m['baslik'],
				'post_name'     => $m['slug'],
				'post_excerpt'  => (string) ( $m['ozet'] ?? '' ),
				'post_content'  => $icerik,
				'post_date'     => $kaynak->post_date,
				'post_date_gmt' => $kaynak->post_date_gmt,
				'comment_status' => $kaynak->comment_status,
			],
			true
		);
		if ( is_wp_error( $id ) ) {
			echo '  HATA: ' . $id->get_error_message() . "\n";
			return;
		}

		/* One cikan gorsel — media_support kapali, ayni ek dosya kullanilir. */
		$gorsel = get_post_thumbnail_id( $tr_id );
		if ( $gorsel ) {
			set_post_thumbnail( $id, $gorsel );
		}
		foreach ( [ 'trx_addons_options', 'qwery_options', '_wp_page_template' ] as $mk ) {
			$v = get_post_meta( $tr_id, $mk, true );
			if ( '' !== $v && false !== $v ) {
				update_post_meta( $id, $mk, $v );
			}
		}

		pll_set_post_language( $id, $dil );

		/* Kategori: Turkce kategorinin ayni dildeki cevirisi. */
		$kat = [];
		foreach ( wp_get_post_terms( $tr_id, 'category', [ 'fields' => 'ids' ] ) as $t ) {
			$c = pll_get_term( $t, $dil );
			if ( $c ) {
				$kat[] = (int) $c;
			}
		}
		if ( $kat ) {
			wp_set_post_terms( $id, $kat, 'category' );
		}

		$ceviri[ $dil ] = $id;
		$olusan++;
		echo "  olusturuldu $dil: $id (/$dil/{$m['slug']}/)\n";
	}

	if ( ! $dry && count( $ceviri ) === 3 ) {
		pll_save_post_translations( $ceviri );
	} elseif ( ! $dry ) {
		$atlanan++;
	}
}

echo "olusan: $olusan, eksik kalan yazi: $atlanan\n";
if ( $dry ) {
	echo "DRY-RUN — yazilmadi.\n";
	return;
}
wp_cache_flush();
echo "TAMAM\n";
