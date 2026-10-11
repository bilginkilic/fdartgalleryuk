<?php
/**
 * Plugin Name: FD Blog Seçkileri
 * Description: Ana sayfanın en altına (altbilginin hemen üstüne) blog bölümü,
 *              haftalık seçki yazıları için eser kartı, paylaşım satırı ve
 *              SEO işaretlemesi (Article + ItemList JSON-LD, açıklama, OG).
 *
 * NEDEN mu-plugin: ana sayfa (#60) ve altbilgi Elementor'da. Bölümü Elementor
 * verisine yazmak yerine altbilgi konumunun kancasına bağlanıyoruz; dosya
 * silinince her şey eski haline döner, `_elementor_data`'ya dokunulmaz.
 *
 * Kancalar:
 *   - `elementor/theme/before_do_footer` — altbilgi Elementor Pro şablonu
 *     (#1041, include/general). XStore'un `etheme_prefooter`'ı bu durumda
 *     HİÇ çalışmaz (footer.php:12), bu yüzden ikisine de bağlanıp bir kez
 *     basıyoruz.
 *
 * Kısa kodlar (yazı içinde):
 *   [fd_eser id="3650"]Kartın altındaki küratör notu[/fd_eser]
 *   [fd_link kat="naturmont-oil"]metin[/fd_link]   ürün kategorisine bağlantı
 *   [fd_link sayfa="ozel-siparis"]metin[/fd_link]  sayfaya bağlantı (slug)
 *   [fd_seckiler]                                  bölümü elle yerleştirmek için
 *
 * Fiyat ve stok yazıya YAZILMAZ: kart canlı WooCommerce verisini basar. Eser
 * satılınca kart kendiliğinden "yeni evine gitti" der ve kategorisine yönlendirir.
 */

defined( 'ABSPATH' ) || exit;

const FD_SECKI_KAT = 'haftanin-seckisi';
// Seçki dışındaki yazılar (tablo alma rehberi, ipuçları): aynı görünüm, paylaşım
// ve SEO; ana sayfada öne çıkan kart yine en yeni SEÇKİ olur, bunlar yanda durur.
const FD_REHBER_KAT = 'sanat-rehberi';

/* ------------------------------------------------------------------ */
/* 1) Kategori — bir kez oluşturulur                                    */
/* ------------------------------------------------------------------ */

add_action( 'init', function () {
	if ( get_option( 'fd_secki_kat_v' ) === '2' ) {
		return;
	}
	if ( ! term_exists( FD_SECKI_KAT, 'category' ) ) {
		wp_insert_term( 'Haftanın Seçkisi', 'category', [
			'slug'        => FD_SECKI_KAT,
			'description' => 'Her hafta galeriden bir avuç eser; arkalarındaki hikâye, ilham aldıkları ustalar ve size anlattıkları.',
		] );
	}
	if ( ! term_exists( FD_REHBER_KAT, 'category' ) ) {
		wp_insert_term( 'Sanat Rehberi', 'category', [
			'slug'        => FD_REHBER_KAT,
			'description' => 'Orijinal tablo seçmek, almak ve evinizde yaşatmak üzerine yazılar.',
		] );
	}
	update_option( 'fd_secki_kat_v', '2', true );
}, 20 );

function fd_secki_mi( $post = null ) {
	$post = get_post( $post );
	return $post && 'post' === $post->post_type && has_term( [ FD_SECKI_KAT, FD_REHBER_KAT ], 'category', $post );
}

// Yalnızca haftalık seçki mi (rehber yazısı değil)?
function fd_secki_haftalik_mi( $post = null ) {
	$post = get_post( $post );
	return $post && has_term( FD_SECKI_KAT, 'category', $post );
}

/* ------------------------------------------------------------------ */
/* 2) Ortak stil — sayfada bir kez basılır                              */
/* ------------------------------------------------------------------ */

function fd_secki_stil() {
	static $basildi = false;
	if ( $basildi ) {
		return '';
	}
	$basildi = true;
	return '<style id="fd-seckiler-css">
.fdsk{--fdsk-zemin:#f6f1ea;--fdsk-yazi:#211d1a;--fdsk-soluk:#6b625a;--fdsk-vurgu:#9a4a24;--fdsk-cizgi:#e4dbcf}
.fdsk-bolum{background:var(--fdsk-zemin);color:var(--fdsk-yazi);padding:72px 16px 80px}
.fdsk-ic{max-width:1200px;margin:0 auto}
.fdsk-bas{display:flex;flex-wrap:wrap;align-items:flex-end;justify-content:space-between;gap:12px 32px;margin-bottom:36px}
.fdsk .fdsk-ust{font-size:12px!important;letter-spacing:.18em;text-transform:uppercase;color:var(--fdsk-vurgu);margin:0 0 10px!important;font-weight:600}
.fdsk .fdsk-baslik{font-family:Georgia,"Times New Roman",serif;font-weight:400;font-size:clamp(28px,3.6vw,42px)!important;line-height:1.15!important;margin:0!important;color:var(--fdsk-yazi)}
.fdsk .fdsk-alt{max-width:560px;margin:12px 0 0!important;color:var(--fdsk-soluk);font-size:16px!important;line-height:1.6!important}
.fdsk-tumu{color:var(--fdsk-yazi);text-decoration:none;border-bottom:1px solid currentColor;padding-bottom:2px;font-weight:600;white-space:nowrap}
.fdsk-tumu:hover{color:var(--fdsk-vurgu)}
.fdsk-izgara{display:grid;grid-template-columns:minmax(0,1.35fr) minmax(0,1fr);gap:28px}
.fdsk-izgara.fdsk-tek{grid-template-columns:minmax(0,1fr)}
.fdsk-tek .fdsk-kart{display:grid;grid-template-columns:minmax(0,1.25fr) minmax(0,1fr);align-items:center}
.fdsk-tek .fdsk-metin{padding:32px 40px}
.fdsk-yan{display:grid;gap:28px;align-content:start}
.fdsk-kart{display:block;background:#fff;border:1px solid var(--fdsk-cizgi);text-decoration:none;color:inherit;transition:transform .25s ease,box-shadow .25s ease}
.fdsk-kart:hover{transform:translateY(-3px);box-shadow:0 14px 30px rgba(33,29,26,.10)}
.fdsk-gorsel{display:block;overflow:hidden;background:#e9e1d6;aspect-ratio:4/3}
.fdsk-yan .fdsk-gorsel{aspect-ratio:16/9}
.fdsk-gorsel img{width:100%;height:100%;object-fit:cover;display:block;transition:transform .6s ease}
.fdsk-kart:hover .fdsk-gorsel img{transform:scale(1.03)}
.fdsk-metin{display:block;padding:22px 24px 26px}
.fdsk-kunye{display:block;font-size:12px;letter-spacing:.12em;text-transform:uppercase;color:var(--fdsk-vurgu);margin-bottom:10px}
.fdsk-kart-baslik{display:block;font-family:Georgia,"Times New Roman",serif;font-size:22px;line-height:1.3;margin:0 0 10px;color:var(--fdsk-yazi)}
.fdsk-buyuk .fdsk-kart-baslik{font-size:clamp(24px,2.4vw,30px)}
.fdsk-ozet{display:block;color:var(--fdsk-soluk);font-size:15px;line-height:1.65;margin:0 0 14px}
.fdsk-oku{font-weight:600;font-size:14px;color:var(--fdsk-yazi)}
.fdsk-kart:hover .fdsk-oku{color:var(--fdsk-vurgu)}
@media (max-width:860px){.fdsk-izgara,.fdsk-tek .fdsk-kart{grid-template-columns:minmax(0,1fr)}.fdsk-tek .fdsk-metin{padding:22px 24px 26px}.fdsk-bolum{padding:56px 16px 60px}}
/* yazı içi eser kartı */
.fdsk-eser{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1fr);gap:0;margin:32px 0 40px;background:var(--fdsk-zemin);border:1px solid var(--fdsk-cizgi)}
.fdsk-eser-gorsel{display:block;background:#e9e1d6}
.fdsk-eser-gorsel img{width:100%;height:100%;max-height:520px;object-fit:contain;display:block}
.fdsk-eser-metin{padding:26px 28px;display:flex;flex-direction:column;justify-content:center}
.fdsk .fdsk-eser-teknik{font-size:12px!important;letter-spacing:.12em;text-transform:uppercase;color:var(--fdsk-vurgu);margin:0 0 8px!important}
.fdsk .fdsk-eser-ad{font-family:Georgia,"Times New Roman",serif;font-size:26px!important;line-height:1.25!important;margin:0 0 10px!important;color:var(--fdsk-yazi)}
.fdsk .fdsk-eser-not{color:var(--fdsk-soluk);font-style:italic;line-height:1.6!important;margin:0 0 16px!important}
.fdsk .fdsk-eser-fiyat{font-size:18px!important;margin:0 0 18px!important;color:var(--fdsk-yazi)}
.fdsk-eser-dugme{align-self:flex-start;display:inline-block;background:var(--fdsk-yazi);color:#fff!important;text-decoration:none!important;padding:12px 22px;font-weight:600;font-size:14px;letter-spacing:.04em}
.fdsk-eser-dugme:hover{background:var(--fdsk-vurgu)}
.fdsk .fdsk-eser-gitti{color:var(--fdsk-vurgu);font-weight:600;margin:0 0 14px!important}
@media (max-width:700px){.fdsk-eser{grid-template-columns:minmax(0,1fr)}.fdsk-eser-metin{padding:20px}}
/* seçki yazısı: tek yazı şablonu (#1053) tam genişlik; metin satırı ~200 karaktere
   uzuyordu. Yalnızca seçki yazılarında içerik sütunu daraltılır. */
body.fdsk-yazi .elementor-widget-theme-post-etheme_content{max-width:860px!important;width:100%!important;margin-left:auto!important;margin-right:auto!important}
body.fdsk-yazi .elementor-widget-theme-post-etheme_content p{font-size:18px;line-height:1.8}
body.fdsk-yazi .elementor-widget-theme-post-etheme_content h2{font-family:Georgia,"Times New Roman",serif;font-weight:400;text-transform:none;font-size:28px;line-height:1.3;margin:48px 0 16px}
/* paylaşım + önceki seçkiler */
.fdsk-paylas{margin:48px 0 0;padding:28px;background:var(--fdsk-zemin);border:1px solid var(--fdsk-cizgi);text-align:center}
.fdsk .fdsk-paylas-baslik{font-family:Georgia,"Times New Roman",serif;font-size:22px!important;margin:0 0 6px!important;color:var(--fdsk-yazi)}
.fdsk.fdsk-paylas p{color:var(--fdsk-soluk);margin:0 0 16px!important}
.fdsk-paylas-dugmeler{display:flex;flex-wrap:wrap;justify-content:center;gap:10px}
.fdsk-paylas-dugmeler a,.fdsk-paylas-dugmeler button{display:inline-block;border:1px solid var(--fdsk-yazi);background:transparent;color:var(--fdsk-yazi);padding:9px 16px;font-size:14px;font-weight:600;text-decoration:none;cursor:pointer;font-family:inherit;line-height:1.2}
.fdsk-paylas-dugmeler a:hover,.fdsk-paylas-dugmeler button:hover{background:var(--fdsk-yazi);color:#fff}
.fdsk-onceki{margin:40px 0 0}
.fdsk.fdsk-onceki h2{font-family:Georgia,"Times New Roman",serif;font-weight:400;font-size:26px!important;margin:0 0 16px!important}
.fdsk-onceki ul{list-style:none;margin:0;padding:0;border-top:1px solid var(--fdsk-cizgi)}
.fdsk-onceki li{border-bottom:1px solid var(--fdsk-cizgi);padding:14px 0;margin:0}
.fdsk-onceki a{text-decoration:none;color:var(--fdsk-yazi);font-weight:600}
.fdsk-onceki a:hover{color:var(--fdsk-vurgu)}
.fdsk-onceki time{display:block;font-size:13px;color:var(--fdsk-soluk);font-weight:400;margin-top:2px}
</style>';
}

/* ------------------------------------------------------------------ */
/* 3) Ana sayfa bölümü                                                  */
/* ------------------------------------------------------------------ */

function fd_secki_okuma_suresi( $post ) {
	$metin  = wp_strip_all_tags( strip_shortcodes( $post->post_content ) );
	$kelime = count( preg_split( '/\s+/u', trim( $metin ), -1, PREG_SPLIT_NO_EMPTY ) );
	return max( 1, (int) round( $kelime / 200 ) );
}

function fd_secki_kunye( $post ) {
	$parca = [];
	if ( has_term( FD_SECKI_KAT, 'category', $post ) ) {
		$parca[] = 'Haftanın Seçkisi';
	} else {
		$kat = get_the_category( $post->ID );
		if ( $kat && 'uncategorized' !== $kat[0]->slug ) {
			$parca[] = $kat[0]->name;
		}
	}
	$parca[] = date_i18n( 'j F Y', get_post_time( 'U', false, $post ) );
	$parca[] = fd_secki_okuma_suresi( $post ) . ' dk okuma';
	return implode( ' · ', $parca );
}

function fd_secki_ozet( $post, $kelime ) {
	$ham = has_excerpt( $post ) ? $post->post_excerpt : strip_shortcodes( $post->post_content );
	return wp_trim_words( wp_strip_all_tags( $ham ), $kelime, '…' );
}

function fd_secki_kart( $post, $buyuk ) {
	$gorsel = '';
	if ( has_post_thumbnail( $post ) ) {
		$gorsel = get_the_post_thumbnail( $post, 'medium_large', [
			'loading'  => 'lazy',
			'decoding' => 'async',
			'sizes'    => $buyuk ? '(max-width: 860px) 100vw, 680px' : '(max-width: 860px) 100vw, 500px',
			'alt'      => esc_attr( get_the_title( $post ) ),
		] );
	}
	return sprintf(
		'<a class="fdsk-kart%s" href="%s">%s<span class="fdsk-metin"><span class="fdsk-kunye">%s</span><span class="fdsk-kart-baslik">%s</span><span class="fdsk-ozet">%s</span><span class="fdsk-oku">%s →</span></span></a>',
		$buyuk ? ' fdsk-buyuk' : '',
		esc_url( get_permalink( $post ) ),
		$gorsel ? '<span class="fdsk-gorsel">' . $gorsel . '</span>' : '',
		esc_html( fd_secki_kunye( $post ) ),
		esc_html( get_the_title( $post ) ),
		esc_html( fd_secki_ozet( $post, $buyuk ? 38 : 18 ) ),
		$buyuk ? 'Hikâyeyi oku' : 'Oku'
	);
}

function fd_seckiler_html() {
	$onbellek = get_transient( 'fd_seckiler_html' );
	if ( false !== $onbellek ) {
		return $onbellek;
	}

	$taban = [
		'post_type'           => 'post',
		'post_status'         => 'publish',
		'ignore_sticky_posts' => true,
		'no_found_rows'       => true,
	];
	// Öne çıkan: en yeni seçki; yoksa en yeni yazı.
	$one = get_posts( $taban + [ 'numberposts' => 1, 'category_name' => FD_SECKI_KAT ] );
	if ( ! $one ) {
		$one = get_posts( $taban + [ 'numberposts' => 1 ] );
	}
	if ( ! $one ) {
		set_transient( 'fd_seckiler_html', '', DAY_IN_SECONDS );
		return '';
	}
	$yan = get_posts( $taban + [ 'numberposts' => 2, 'exclude' => [ $one[0]->ID ] ] );

	$blog = (int) get_option( 'page_for_posts' );
	$tumu = $blog ? get_permalink( $blog ) : get_term_link( FD_SECKI_KAT, 'category' );

	$html  = '<section class="fdsk fdsk-bolum" aria-labelledby="fdsk-baslik"><div class="fdsk-ic">';
	$html .= '<div class="fdsk-bas"><div>';
	$html .= '<p class="fdsk-ust">Blog · Haftanın Seçkisi</p>';
	$html .= '<h2 class="fdsk-baslik" id="fdsk-baslik">Tabloların anlattığı hikâyeler</h2>';
	$html .= '<p class="fdsk-alt">Her hafta galeriden birkaç eser seçiyor, arkalarındaki hikâyeyi ve ilham aldıkları ustaları anlatıyoruz. Okuyun, belki biri size kendi hikâyenizi hatırlatır.</p>';
	$html .= '</div>';
	if ( $tumu && ! is_wp_error( $tumu ) ) {
		$html .= '<a class="fdsk-tumu" href="' . esc_url( $tumu ) . '">Tüm yazılar →</a>';
	}
	$html .= '</div>';
	$html .= '<div class="fdsk-izgara' . ( $yan ? '' : ' fdsk-tek' ) . '">';
	$html .= fd_secki_kart( $one[0], true );
	if ( $yan ) {
		$html .= '<div class="fdsk-yan">';
		foreach ( $yan as $p ) {
			$html .= fd_secki_kart( $p, false );
		}
		$html .= '</div>';
	}
	$html .= '</div></div></section>';

	set_transient( 'fd_seckiler_html', $html, DAY_IN_SECONDS );
	return $html;
}

// Yazı yayınlanınca/güncellenince/silinince bölüm yeniden üretilsin.
add_action( 'transition_post_status', function ( $yeni, $eski, $post ) {
	if ( 'post' === $post->post_type && ( 'publish' === $yeni || 'publish' === $eski ) ) {
		delete_transient( 'fd_seckiler_html' );
	}
}, 10, 3 );
add_action( 'save_post_post', function () {
	delete_transient( 'fd_seckiler_html' );
} );

function fd_seckiler_ana_sayfa() {
	static $basildi = false;
	if ( $basildi || ! is_front_page() || is_admin() ) {
		return;
	}
	$basildi = true;
	$html    = fd_seckiler_html();
	if ( $html ) {
		echo fd_secki_stil() . $html; // phpcs:ignore WordPress.Security.EscapeOutput -- yukarıda kaçırıldı
	}
}
add_action( 'elementor/theme/before_do_footer', 'fd_seckiler_ana_sayfa' );
add_action( 'etheme_prefooter', 'fd_seckiler_ana_sayfa', 5 );

add_shortcode( 'fd_seckiler', function () {
	$html = fd_seckiler_html();
	return $html ? fd_secki_stil() . $html : '';
} );

/* ------------------------------------------------------------------ */
/* 4) Yazı içi kısa kodlar                                              */
/* ------------------------------------------------------------------ */

function fd_secki_teknik( $urun_id ) {
	// Kategori adları "(4)-Akrilik Teknik", "(c) Peyzaj Konulu" biçiminde;
	// sıra önekini atıp yalnızca tekniği gösteriyoruz.
	$teknik = [
		'acrylic' => 'Akrilik', 'oil' => 'Yağlı boya', 'water-color' => 'Sulu boya',
		'pastel' => 'Soft pastel', 'sketching' => 'Karakalem', 'virtualart' => 'Soyut',
	];
	$terimler = get_the_terms( $urun_id, 'product_cat' );
	if ( ! $terimler || is_wp_error( $terimler ) ) {
		return [ '', null ];
	}
	$bulunan = '';
	$yaprak  = null;
	foreach ( $terimler as $t ) {
		$kok = $t;
		while ( $kok->parent ) {
			$ust = get_term( $kok->parent, 'product_cat' );
			if ( ! $ust || is_wp_error( $ust ) ) {
				break;
			}
			$kok = $ust;
		}
		if ( ! $bulunan && isset( $teknik[ $kok->slug ] ) ) {
			$bulunan = $teknik[ $kok->slug ];
		}
		if ( $t->parent && ! $yaprak ) {
			$yaprak = $t;
		}
	}
	return [ $bulunan, $yaprak ?: $terimler[0] ];
}

$GLOBALS['fd_secki_eserler'] = [];

add_shortcode( 'fd_eser', function ( $atts, $not = '' ) {
	$a = shortcode_atts( [ 'id' => 0 ], $atts );
	if ( ! function_exists( 'wc_get_product' ) ) {
		return '';
	}
	$urun = wc_get_product( (int) $a['id'] );
	if ( ! $urun || 'publish' !== $urun->get_status() ) {
		return '';
	}
	$GLOBALS['fd_secki_eserler'][ $urun->get_id() ] = true;

	list( $teknik, $kat ) = fd_secki_teknik( $urun->get_id() );
	$ad     = trim( wp_strip_all_tags( $urun->get_name() ), " '\"" );
	$adres  = get_permalink( $urun->get_id() );
	$gorsel = $urun->get_image( 'large', [
		'loading'  => 'lazy',
		'decoding' => 'async',
		'sizes'    => '(max-width: 700px) 100vw, 480px',
		'alt'      => esc_attr( $ad . ( $teknik ? ' — ' . $teknik . ' tablo' : '' ) ),
	] );

	$h  = '<figure class="fdsk fdsk-eser">';
	$h .= '<a class="fdsk-eser-gorsel" href="' . esc_url( $adres ) . '">' . $gorsel . '</a>';
	$h .= '<figcaption class="fdsk-eser-metin">';
	if ( $teknik ) {
		$h .= '<p class="fdsk-eser-teknik">' . esc_html( $teknik ) . '</p>';
	}
	$h .= '<p class="fdsk-eser-ad">' . esc_html( $ad ) . '</p>';
	$not = trim( wp_strip_all_tags( (string) $not ) );
	if ( $not ) {
		$h .= '<p class="fdsk-eser-not">' . esc_html( $not ) . '</p>';
	}
	if ( $urun->is_in_stock() ) {
		$h .= '<p class="fdsk-eser-fiyat">' . wp_kses_post( $urun->get_price_html() ) . '</p>';
		$h .= '<a class="fdsk-eser-dugme" href="' . esc_url( $adres ) . '">Eseri yakından görün</a>';
	} else {
		$h .= '<p class="fdsk-eser-gitti">Bu eser yeni evine gitti.</p>';
		$kat_adres = $kat ? get_term_link( $kat ) : '';
		if ( $kat_adres && ! is_wp_error( $kat_adres ) ) {
			$h .= '<a class="fdsk-eser-dugme" href="' . esc_url( $kat_adres ) . '">Benzer eserlere bakın</a>';
		}
	}
	$h .= '</figcaption></figure>';
	return fd_secki_stil() . $h;
} );

add_shortcode( 'fd_link', function ( $atts, $metin = '' ) {
	$a     = shortcode_atts( [ 'kat' => '', 'sayfa' => '', 'yazi' => '' ], $atts );
	$adres = '';
	if ( $a['kat'] ) {
		$t = get_term_by( 'slug', $a['kat'], 'product_cat' );
		$adres = $t ? get_term_link( $t ) : '';
	} elseif ( $a['sayfa'] ) {
		$p = get_page_by_path( $a['sayfa'] );
		$adres = ( $p && 'publish' === $p->post_status ) ? get_permalink( $p ) : '';
	} elseif ( $a['yazi'] ) {
		$p = get_page_by_path( $a['yazi'], OBJECT, 'post' );
		$adres = ( $p && 'publish' === $p->post_status ) ? get_permalink( $p ) : '';
	}
	$metin = do_shortcode( $metin );
	// Hedef yoksa (sayfa silinmiş, slug değişmiş) metin bağlantısız kalır; ölü bağlantı basılmaz.
	if ( ! $adres || is_wp_error( $adres ) ) {
		return $metin;
	}
	return '<a href="' . esc_url( $adres ) . '">' . $metin . '</a>';
} );

/* ------------------------------------------------------------------ */
/* 5) Seçki yazısının sonu: paylaşım + önceki seçkiler                  */
/* ------------------------------------------------------------------ */

add_filter( 'the_content', function ( $icerik ) {
	if ( ! is_singular( 'post' ) || ! in_the_loop() || ! is_main_query() || ! fd_secki_mi() ) {
		return $icerik;
	}
	$post   = get_post();
	$adres  = get_permalink( $post );
	$baslik = get_the_title( $post );
	$gorsel = get_the_post_thumbnail_url( $post, 'large' );
	$u      = rawurlencode( $adres );
	$b      = rawurlencode( html_entity_decode( $baslik, ENT_QUOTES, 'UTF-8' ) );

	$haftalik = fd_secki_haftalik_mi( $post );
	if ( $haftalik ) {
		$p  = '<aside class="fdsk fdsk-paylas"><p class="fdsk-paylas-baslik">Bu seçki birine iyi gelir mi?</p>';
		$p .= '<p>Aklınıza gelen biri varsa gönderin; bazen bir tablo, söylenemeyeni söyler.</p>';
	} else {
		$p  = '<aside class="fdsk fdsk-paylas"><p class="fdsk-paylas-baslik">Bu yazı birinin işine yarar mı?</p>';
		$p .= '<p>Duvarına bir tablo arayan biri varsa gönderin.</p>';
	}
	$p .= '<div class="fdsk-paylas-dugmeler">';
	$p .= '<a href="https://wa.me/?text=' . $b . '%20' . $u . '" target="_blank" rel="noopener">WhatsApp</a>';
	if ( $gorsel ) {
		$p .= '<a href="https://pinterest.com/pin/create/button/?url=' . $u . '&media=' . rawurlencode( $gorsel ) . '&description=' . $b . '" target="_blank" rel="noopener">Pinterest</a>';
	}
	$p .= '<a href="https://www.facebook.com/sharer/sharer.php?u=' . $u . '" target="_blank" rel="noopener">Facebook</a>';
	$p .= '<a href="https://twitter.com/intent/tweet?url=' . $u . '&text=' . $b . '" target="_blank" rel="noopener">X</a>';
	$p .= '<button type="button" data-fdsk-kopya="' . esc_attr( $adres ) . '">Bağlantıyı kopyala</button>';
	$p .= '</div></aside>';
	$p .= '<script>document.addEventListener("click",function(e){var b=e.target.closest("[data-fdsk-kopya]");if(!b)return;var t=b.textContent;(navigator.clipboard?navigator.clipboard.writeText(b.dataset.fdskKopya):Promise.reject()).then(function(){b.textContent="Kopyalandı ✓";setTimeout(function(){b.textContent=t},2000)},function(){window.prompt("Bağlantı:",b.dataset.fdskKopya)})});</script>';

	$onceki = get_posts( [
		'post_type'     => 'post',
		'post_status'   => 'publish',
		'numberposts'   => 4,
		'category_name' => FD_SECKI_KAT,
		'exclude'       => [ $post->ID ],
		'no_found_rows' => true,
	] );
	if ( $onceki ) {
		$liste_adi = $haftalik ? 'Önceki seçkiler' : 'Haftanın seçkileri';
		$p .= '<nav class="fdsk fdsk-onceki" aria-label="' . esc_attr( $liste_adi ) . '"><h2>' . esc_html( $liste_adi ) . '</h2><ul>';
		foreach ( $onceki as $o ) {
			$p .= '<li><a href="' . esc_url( get_permalink( $o ) ) . '">' . esc_html( get_the_title( $o ) ) . '</a><time datetime="' . esc_attr( get_the_date( 'c', $o ) ) . '">' . esc_html( get_the_date( 'j F Y', $o ) ) . '</time></li>';
		}
		$p .= '</ul></nav>';
	}
	return $icerik . fd_secki_stil() . $p;
}, 20 );

add_filter( 'body_class', function ( $c ) {
	if ( is_singular( 'post' ) && fd_secki_mi( get_queried_object_id() ) ) {
		$c[] = 'fdsk-yazi';
	}
	return $c;
} );

/* ------------------------------------------------------------------ */
/* 6) SEO: açıklama, Open Graph, JSON-LD (yalnızca seçki yazılarında)   */
/* ------------------------------------------------------------------ */

// Açıklama/OG etiketlerini başkası basıyorsa biz basmayız (iki takım etiket olursa
// Google ve WhatsApp hangisini alacağını tahmin eder). Bu sitede XStore'un kendi
// SEO özelliği AÇIK (`et_seo_switcher`): tekil yazıda og:title, og:description
// (özet), og:image (öne çıkan, `full`) ve `description` basıyor. `description`'ı
// yazı başına `_et_meta_description` alanından okur — yayın betiği onu yazar.
function fd_secki_seo_eklentisi_var() {
	return defined( 'WPSEO_VERSION' ) || defined( 'RANK_MATH_VERSION' ) || defined( 'AIOSEO_VERSION' ) || defined( 'SEOPRESS_VERSION' )
		|| ( class_exists( 'XStore_SEO' ) && XStore_SEO::$enabled );
}

// Etiketleri biz basıyorsak Jetpack'inkiler susar.
add_filter( 'jetpack_enable_open_graph', function ( $acik ) {
	return ( is_singular( 'post' ) && fd_secki_mi() && ! fd_secki_seo_eklentisi_var() ) ? false : $acik;
}, 99 );

add_action( 'wp_head', function () {
	if ( ! is_singular( 'post' ) || ! fd_secki_mi() ) {
		return;
	}
	$post    = get_queried_object();
	$adres   = get_permalink( $post );
	$baslik  = wp_strip_all_tags( get_the_title( $post ) );
	$aciklama = get_post_meta( $post->ID, '_fd_meta_aciklama', true );
	if ( ! $aciklama ) {
		$aciklama = fd_secki_ozet( $post, 28 );
	}
	$gorsel  = get_the_post_thumbnail_url( $post, 'large' );

	$xstore_seo = class_exists( 'XStore_SEO' ) && XStore_SEO::$enabled;
	$seo_eklenti = defined( 'WPSEO_VERSION' ) || defined( 'RANK_MATH_VERSION' ) || defined( 'AIOSEO_VERSION' ) || defined( 'SEOPRESS_VERSION' );
	if ( ! $seo_eklenti ) {
		if ( ! $xstore_seo ) {
			printf( "<meta name=\"description\" content=\"%s\">\n<meta property=\"og:type\" content=\"article\">\n<meta property=\"og:url\" content=\"%s\">\n", esc_attr( $aciklama ), esc_url( $adres ) );
			if ( $gorsel ) {
				printf( "<meta property=\"og:image\" content=\"%s\">\n", esc_url( $gorsel ) );
			}
		}
		// XStore og:title ve og:description'i HIC basamiyor: seo.php:67'deki
		// `setup_postdata( $GLOBALS['post'] =& $post_object )` global $post'u
		// null'a cekiyor, get_the_title()/get_the_excerpt() bos donuyor ve bos
		// etiket atlaniyor (og_tag(), seo.php:225). Bu ikisini her durumda biz basariz.
		printf( "<meta property=\"og:title\" content=\"%s\">\n<meta property=\"og:description\" content=\"%s\">\n<meta property=\"og:locale\" content=\"tr_TR\">\n<meta property=\"article:published_time\" content=\"%s\">\n<meta name=\"twitter:card\" content=\"summary_large_image\">\n",
			esc_attr( $baslik ), esc_attr( $aciklama ), esc_attr( get_the_date( 'c', $post ) ) );
	}

	// Yazıda geçen eserler — kısa kodlardan okunur (render'dan önce, wp_head'deyiz).
	preg_match_all( '/\[fd_eser\s+[^\]]*id="?(\d+)/', $post->post_content, $m );
	$liste = [];
	$sira  = 1;
	foreach ( array_unique( array_map( 'intval', $m[1] ) ) as $id ) {
		if ( 'publish' !== get_post_status( $id ) ) {
			continue;
		}
		$liste[] = [
			'@type'    => 'ListItem',
			'position' => $sira++,
			'url'      => get_permalink( $id ),
			'name'     => trim( wp_strip_all_tags( get_the_title( $id ) ), " '\"" ),
		];
	}

	$kurum = [
		'@type' => 'Organization',
		'name'  => 'FD Art Gallery',
		'url'   => home_url( '/' ),
	];
	$makale = [
		'@type'            => 'BlogPosting',
		'headline'         => $baslik,
		'description'      => $aciklama,
		'mainEntityOfPage' => $adres,
		'datePublished'    => get_the_date( 'c', $post ),
		'dateModified'     => get_the_modified_date( 'c', $post ),
		'inLanguage'       => 'tr-TR',
		'author'           => $kurum,
		'publisher'        => $kurum,
		'articleSection'   => fd_secki_haftalik_mi( $post ) ? 'Haftanın Seçkisi' : 'Sanat Rehberi',
	];
	if ( $gorsel ) {
		$makale['image'] = $gorsel;
	}
	$grafik = [ $makale ];
	if ( $liste ) {
		$grafik[] = [
			'@type'           => 'ItemList',
			'name'            => $baslik . ( fd_secki_haftalik_mi( $post ) ? ' — seçkideki eserler' : ' — yazıdaki eserler' ),
			'itemListElement' => $liste,
		];
	}
	echo '<script type="application/ld+json">' . wp_json_encode( [ '@context' => 'https://schema.org', '@graph' => $grafik ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . "</script>\n";
}, 5 );
