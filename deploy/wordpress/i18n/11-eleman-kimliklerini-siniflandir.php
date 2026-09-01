<?php
/**
 * Ana sayfa scriptlerini dilden bagimsiz hale getirir.
 *
 * SORUN (kullanici: "en ve ru ana sayfa tr'den farkli, birebir ayni olmali"):
 * Olculdu — TR govde 8386 px, EN/RU 6877 px. Elementor YAPISI ucunde de
 * birebir ayni (70 dugum, diff bos). Fark CALISMA ANINDA olusuyor, cunku
 * ana sayfaya gomulu scriptler iki sekilde sayfaya CIVILENMIS durumda:
 *
 *   1. YOL KILAVUZU
 *        var p=location.pathname;
 *        if(p!=='/' && !/\/home\/?$/.test(p)) return;
 *      `/en/` ve `/ru/` bu testi gecemez -> `cz-hero-block` ve `cz-fix2`
 *      HIC CALISMAZ. Gizlenmesi gereken bolumler EN/RU'da acik kalir.
 *
 *   2. ELEMAN KIMLIGI
 *        document.querySelector('.elementor-element-54fa5520')
 *        document.querySelector('[data-id="567ae5d"]')
 *      Dil kopyalari uretilirken her elemanin `id` alani YENIDEN URETILIR
 *      (03-cevrilmis-anasayfalar.php), bu yuzden bu seciciler EN/RU'da hicbir
 *      seyi bulmaz.
 *
 * Ayni hata daha once `.cz-dedupe` icin de yasanmisti (CLAUDE.md 6i).
 * KURAL: sayfaya veya eleman kimligine bagli kilavuz YAZILMAZ.
 *
 * NEREDE: bu scriptler SAYFADA DEGIL, tema widget alanindaki tek bir
 * Custom HTML widget'indadir (`option widget_custom_html[2]`, ~36 KB,
 * 23 cz-* blok) — CLAUDE.md 6f'deki "kalan 14 JS enjeksiyonu" bunlar.
 * Widget uc dilde de ayni basildigi icin duzeltme oraya yapilir; sayfalara
 * yalnizca kalici SINIF yazilir.
 *
 * COZUM:
 *   - Yol kilavuzu -> `document.body.classList.contains('frontpage')`
 *     (tema on sayfaya `frontpage` sinifi basiyor; uc dilde de var — olculdu).
 *   - Eleman kimligi -> KALICI SINIF `cz-el-<kimlik>`. Sinif, TR sayfasindaki
 *     kimlige gore adlandirilir ama uc sayfada da YAPISAL OLARAK AYNI
 *     elemana yazilir (dugum sirasi birebir ayni oldugu icin indeks eslemesi
 *     guvenli; script bunu ONCE dogrular).
 *
 * Calistirma (once dry):
 *   wp --user=<admin> --path=<kok> eval-file 11-eleman-kimliklerini-siniflandir.php dry
 *
 * `--user` SART (kses; CLAUDE.md 6f). Yazimdan sonra kendini dogrular
 * (JSON gecerli + style/script sayilari sabit), tutmazsa GERI ALIR.
 */

$dry     = ( ( $args[0] ?? '' ) === 'dry' );
/* ID'ler ELLE YAZILMAZ — dev ve canlida farkli (bkz. 09 numarali script). */
$KAYNAK   = (int) get_option( 'page_on_front' );
$SAYFALAR = [ 'tr' => $KAYNAK ];
foreach ( [ 'en', 'ru' ] as $dil ) {
	$c = $KAYNAK && function_exists( 'pll_get_post' ) ? (int) pll_get_post( $KAYNAK, $dil ) : 0;
	if ( $c && $c !== $KAYNAK ) {
		$SAYFALAR[ $dil ] = $c;
	}
}
if ( count( $SAYFALAR ) < 3 ) {
	echo "HATA: uc dilin ana sayfasi bulunamadi: " . wp_json_encode( $SAYFALAR ) . "\n";
	return;
}
printf( "cozulen ana sayfalar: %s\n", wp_json_encode( $SAYFALAR ) );

kses_remove_filters();

/** Bir sayfanin dugumlerini derinlik-once sirayla duz listeye acar. */
function fd_dugumler( array $els, array &$duz ) {
	foreach ( $els as $i => $e ) {
		$duz[] = [ 'id' => $e['id'] ?? '', 'tur' => $e['widgetType'] ?? ( $e['elType'] ?? '?' ) ];
		if ( ! empty( $e['elements'] ) ) {
			fd_dugumler( $e['elements'], $duz );
		}
	}
}

/**
 * Duz listedeki indeksleri kullanarak agaca sinif yazar.
 *
 * TUZAK: Elementor'da "CSS Classes" denetiminin adi eleman turune gore
 * DEGISIR — widget'ta `_css_classes`, section ve column'da `css_classes`
 * (elementor/includes/elements/section.php:1291). Ilk denemede hepsine
 * `_css_classes` yazildi ve 16 dugumden yalnizca 4'unun sinifi basildi.
 */
function fd_sinif_yaz( array &$els, array $indeks_sinif, &$sayac, &$n ) {
	foreach ( $els as &$e ) {
		$i = $n++;
		if ( isset( $indeks_sinif[ $i ] ) ) {
			$tur      = $e['elType'] ?? '';
			$anahtar  = in_array( $tur, [ 'section', 'column' ], true ) ? 'css_classes' : '_css_classes';
			$mevcut   = (string) ( $e['settings'][ $anahtar ] ?? '' );
			$sinif    = $indeks_sinif[ $i ];
			if ( false === strpos( ' ' . $mevcut . ' ', ' ' . $sinif . ' ' ) ) {
				$e['settings'][ $anahtar ] = trim( $mevcut . ' ' . $sinif );
				$sayac++;
			}
		}
		if ( ! empty( $e['elements'] ) ) {
			fd_sinif_yaz( $e['elements'], $indeks_sinif, $sayac, $n );
		}
	}
}

/* -------------------------------------------------------------------------
 * 1) Widget'taki scriptlerin ATIF YAPTIGI eleman kimliklerini bul.
 *    Listeyi elle yazmiyoruz — widget'tan cikariyoruz ki bir script
 *    degisirse burasi da kendiliginden dogru kalsin.
 * ---------------------------------------------------------------------- */
$WIDGET_INDEKS = 2;
$widgetlar = get_option( 'widget_custom_html' );
if ( empty( $widgetlar[ $WIDGET_INDEKS ]['content'] ) ) {
	echo "HATA: widget_custom_html[$WIDGET_INDEKS] bos\n";
	return;
}
$w_ham = (string) $widgetlar[ $WIDGET_INDEKS ]['content'];

$ham_tr = (string) get_post_meta( $KAYNAK, '_elementor_data', true );
$tr     = json_decode( $ham_tr, true );
if ( ! is_array( $tr ) ) {
	echo "HATA: kaynak elementor verisi cozulemedi\n";
	return;
}

$duz_tr = [];
fd_dugumler( $tr, $duz_tr );
$tr_kimlikler = array_column( $duz_tr, 'id' );

/* Atiflar IKI kaynaktan toplanir:
 *   a) tema widget'i (cz-hero-block, cz-fix2, cz-home ...)
 *   b) SAYFANIN KENDI Elementor verisi — `cz-lead-assets` widget'indaki CSS
 *      de eleman kimligine bagliydi:
 *        body.frontpage .elementor-element-85f2abf,
 *        body.frontpage .elementor-element-3c0b548 { display:none!important }
 *      Bu kural TR sayfasinin kimliklerini tasidigi icin EN/RU'da hicbir seyi
 *      gizlemiyordu ve iki `trx_sc_title` widget'i acik kaliyordu (+223 px).
 * Ikisi de ayni sekilde kalici sinifa cevrilir.
 */
$atif = [];
foreach ( [ $w_ham, $ham_tr ] as $kaynak_metin ) {
	// Hem donusturulmemis (`elementor-element-x`) hem donusturulmus (`cz-el-x`)
	// bicimi toplanir; boylece script YENIDEN calistirilabilir (idempotent).
	if ( preg_match_all( '/(?:elementor-element-|cz-el-)([0-9a-f]{6,8})/', $kaynak_metin, $m ) ) {
		$atif = array_merge( $atif, $m[1] );
	}
	if ( preg_match_all( '/data-id=\\?"([0-9a-f]{6,8})\\?"/', $kaynak_metin, $m ) ) {
		$atif = array_merge( $atif, $m[1] );
	}
	// `['7027c76','567ae5d',...].forEach(... '.elementor-element-'+id ...)` bicimi
	if ( preg_match_all( "/\\[((?:'[0-9a-f]{6,8}',?)+)\\]/", $kaynak_metin, $m ) ) {
		foreach ( $m[1] as $liste ) {
			preg_match_all( '/[0-9a-f]{6,8}/', $liste, $mm );
			$atif = array_merge( $atif, $mm[0] );
		}
	}
}
$atif = array_values( array_unique( $atif ) );

// Yalnizca bu sayfada GERCEKTEN bir dugumun kimligi olanlar anlamli.
$hedef = array_values( array_intersect( $atif, $tr_kimlikler ) );
$oksuz = array_values( array_diff( $atif, $tr_kimlikler ) );

echo 'scriptlerde atif yapilan kimlik: ' . count( $atif )
	. ' | sayfada karsiligi olan: ' . count( $hedef )
	. ' | karsiligi olmayan: ' . count( $oksuz ) . "\n";
if ( $oksuz ) {
	echo '  karsiligi yok (dokunulmaz): ' . implode( ',', $oksuz ) . "\n";
}
if ( ! $hedef ) {
	echo "HATA: hedef yok\n";
	return;
}

/* indeks -> sinif adi */
$indeks_sinif = [];
foreach ( $duz_tr as $i => $d ) {
	if ( in_array( $d['id'], $hedef, true ) ) {
		$indeks_sinif[ $i ] = 'cz-el-' . $d['id'];
	}
}
echo 'sinif yazilacak dugum: ' . count( $indeks_sinif ) . " (TR indekslerine gore)\n";

/* -------------------------------------------------------------------------
 * 2) Uc sayfada da YAPI AYNI MI? Ayni degilse indeks eslemesi gecersizdir.
 * ---------------------------------------------------------------------- */
$veriler = [];
foreach ( $SAYFALAR as $dil => $id ) {
	$ham = (string) get_post_meta( $id, '_elementor_data', true );
	$v   = json_decode( $ham, true );
	if ( ! is_array( $v ) ) {
		echo "HATA: $dil ($id) cozulemedi\n";
		return;
	}
	$duz = [];
	fd_dugumler( $v, $duz );
	if ( count( $duz ) !== count( $duz_tr ) ) {
		echo sprintf( "DURDURULDU: %s dugum sayisi %d, TR %d — indeks eslemesi guvenli degil\n", $dil, count( $duz ), count( $duz_tr ) );
		return;
	}
	foreach ( $duz as $i => $d ) {
		if ( $d['tur'] !== $duz_tr[ $i ]['tur'] ) {
			echo sprintf( "DURDURULDU: %s indeks %d turu '%s', TR '%s'\n", $dil, $i, $d['tur'], $duz_tr[ $i ]['tur'] );
			return;
		}
	}
	$veriler[ $dil ] = [ 'id' => $id, 'ham' => $ham, 'veri' => $v ];
}
echo "yapi dogrulandi: uc sayfa da " . count( $duz_tr ) . " dugum, turler ayni\n";

/* -------------------------------------------------------------------------
 * 3) Sinif yaz + script metinlerini donustur.
 * ---------------------------------------------------------------------- */
$imza = function ( $s ) {
	return [ substr_count( $s, '<style' ), substr_count( $s, '<script' ) ];
};

foreach ( $veriler as $dil => $bilgi ) {
	$veri  = $bilgi['veri'];
	$sayac = 0;
	$n     = 0;
	fd_sinif_yaz( $veri, $indeks_sinif, $sayac, $n );

	/* Sayfanin kendi dize ayarlarindaki kimlik secicileri de kalici sinifa
	   cevrilir (cz-lead-assets icindeki dedupe CSS'i gibi). */
	$metin_sayaci = 0;
	$metin_donustur = function ( &$dugum ) use ( &$metin_donustur, $hedef, &$metin_sayaci ) {
		foreach ( $dugum as $anahtar => &$v ) {
			if ( is_string( $v ) && '_css_classes' !== $anahtar && 'css_classes' !== $anahtar ) {
				$eski_v = $v;
				$v = str_replace( "'.elementor-element-'+id", "'.cz-el-'+id+',.elementor-element-'+id", $v );
				foreach ( $hedef as $k ) {
					$v = str_replace( '[data-id="' . $k . '"]', '.cz-el-' . $k, $v );
					$v = str_replace( 'elementor-element-' . $k, 'cz-el-' . $k, $v );
				}
				if ( $v !== $eski_v ) {
					$metin_sayaci++;
				}
			} elseif ( is_array( $v ) ) {
				$metin_donustur( $v );
			}
		}
		unset( $v );
	};
	$metin_donustur( $veri );

	$yeni = wp_json_encode( $veri );
	if ( ! is_string( $yeni ) || null === json_decode( $yeni, true ) ) {
		echo "  DURDURULDU ($dil): yeniden kodlanamadi\n";
		return;
	}
	if ( $imza( $bilgi['ham'] ) !== $imza( $yeni ) ) {
		echo "  DURDURULDU ($dil): style/script sayisi degisti\n";
		return;
	}

	printf( "  %-3s sinif yazilan dugum=%d, metin donusumu=%d\n", $dil, $sayac, $metin_sayaci );

	if ( $dry ) {
		continue;
	}

	update_post_meta( $bilgi['id'], '_elementor_data', wp_slash( $yeni ) );
	$geri = (string) get_post_meta( $bilgi['id'], '_elementor_data', true );
	if ( $imza( $geri ) !== $imza( $bilgi['ham'] ) || null === json_decode( $geri, true ) ) {
		update_post_meta( $bilgi['id'], '_elementor_data', wp_slash( $bilgi['ham'] ) );
		echo "  KAYIT DOGRULAMASI BASARISIZ ($dil) -> GERI ALINDI\n";
		return;
	}
	delete_post_meta( $bilgi['id'], '_elementor_element_cache' );
	echo "  yazildi: {$bilgi['id']}\n";
}

/* -------------------------------------------------------------------------
 * 4) Widget: yol kilavuzu ve eleman kimligi seciciler.
 *    DIKKAT: yalnizca ON SAYFA kilavuzu degistirilir. `cz-shop` blogundaki
 *    `/shop` kilavuzu BILEREK dokunulmaz — o gercekten magazaya ozeldir.
 * ---------------------------------------------------------------------- */
$w_yeni = $w_ham;

// a) on sayfa yol kilavuzu -> `frontpage` govde sinifi
$w_yeni = preg_replace(
	'#var\s+p\s*=\s*location\.pathname;\s*\n?if\s*\(\s*p\s*!==\s*\'/\'\s*&&\s*![^\n]*?return;#',
	"if(!document.body.classList.contains('frontpage')) return;",
	$w_yeni,
	-1,
	$kilavuz_sayisi
);

// b) `'.elementor-element-'+id` bicimi.
//    Bu kaliptaki dizi bazen SAYFA disindaki elemanlara da atif yapiyor
//    (ornegin 3b86507 bir cpt_layouts elemani). Onlara sinif yazmadigimiz
//    icin secici IKI SECENEKLI yapilir: once kalici sinif, yoksa eski kimlik.
$w_yeni = str_replace(
	"'.elementor-element-'+id",
	"'.cz-el-'+id+',.elementor-element-'+id",
	$w_yeni
);

// c) dogrudan yazilmis kimlikler
$kimlik_sayisi = 0;
foreach ( $hedef as $k ) {
	$kimlik_sayisi += substr_count( $w_yeni, 'elementor-element-' . $k )
		+ substr_count( $w_yeni, '[data-id="' . $k . '"]' );
	$w_yeni = str_replace( '[data-id="' . $k . '"]', '.cz-el-' . $k, $w_yeni );
	$w_yeni = str_replace( 'elementor-element-' . $k, 'cz-el-' . $k, $w_yeni );
}

$kalan_kim = 0;
foreach ( $hedef as $k ) {
	$kalan_kim += substr_count( $w_yeni, 'elementor-element-' . $k );
}
printf(
	"widget: on-sayfa kilavuzu=%d  kimlik secicisi=%d  kalan=%d  (%d -> %d bayt)\n",
	$kilavuz_sayisi,
	$kimlik_sayisi,
	$kalan_kim,
	strlen( $w_ham ),
	strlen( $w_yeni )
);
if ( $kalan_kim > 0 ) {
	echo "  DURDURULDU: widget'ta kimlik secicisi kaldi\n";
	return;
}
if ( substr_count( $w_yeni, '<script' ) !== substr_count( $w_ham, '<script' )
	|| substr_count( $w_yeni, '<style' ) !== substr_count( $w_ham, '<style' ) ) {
	echo "  DURDURULDU: widget style/script sayisi degisti\n";
	return;
}

if ( $dry ) {
	echo "DRY-RUN — yazilmadi.\n";
	return;
}

$widgetlar[ $WIDGET_INDEKS ]['content'] = $w_yeni;
update_option( 'widget_custom_html', $widgetlar );
$w_geri = (string) ( get_option( 'widget_custom_html' )[ $WIDGET_INDEKS ]['content'] ?? '' );
if ( $w_geri !== $w_yeni ) {
	$widgetlar[ $WIDGET_INDEKS ]['content'] = $w_ham;
	update_option( 'widget_custom_html', $widgetlar );
	echo "  WIDGET DOGRULAMASI BASARISIZ -> GERI ALINDI\n";
	return;
}
echo "widget yazildi\n";
if ( class_exists( '\Elementor\Plugin' ) ) {
	\Elementor\Plugin::$instance->files_manager->clear_cache();
}
wp_cache_flush();
echo "TAMAM\n";
