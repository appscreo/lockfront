<?php
/**
 * Front-end password page template.
 *
 * @package Lockfront
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders the visitor-facing login page and handles the admin preview.
 */
class LKFR_Template {

	/** Render the page and exit. */
	public function render() {
		$allowed = array( 200, 401, 503 );
		$code    = (int) lkfr_get( 'http_status_code', '503' );
		if ( ! in_array( $code, $allowed, true ) ) {
			$code = 503;
		}

		status_header( $code );
		nocache_headers();

		// 503: tell crawlers to come back later — protects SEO during maintenance.
		if ( 503 === $code ) {
			header( 'Retry-After: 3600' );
		}

		$this->html( $this->data() );
	}

	// -----------------------------------------------------------------------
	// Data
	// -----------------------------------------------------------------------

	/**
	 * Collect all template settings with their defaults.
	 *
	 * @return array
	 */
	private function data() {

		// Build redirect URL correctly for sub-directory installs.
		// home_url() already contains the sub-dir path, so we must extract
		// only the REQUEST_URI *path* and pass it to home_url() — never pass
		// the full REQUEST_URI value to home_url() directly.
		$request_path = '/';
		if ( ! empty( $_SERVER['REQUEST_URI'] ) ) {
			$request_path = wp_parse_url(
				sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ),
				PHP_URL_PATH
			);
		}
		$home_path = wp_parse_url( home_url( '/' ), PHP_URL_PATH );
		$redirect  = ( $request_path && $request_path !== $home_path )
			? esc_url( home_url( $request_path ) )
			: esc_url( home_url( '/' ) );

		return array(
			// Text
			'headline'    => lkfr_get( 'tpl_headline',    __( 'This site is under development', 'lockfront' ) ),
			'subheadline' => lkfr_get( 'tpl_subheadline', __( 'Please enter the password to continue.', 'lockfront' ) ),
			'placeholder' => lkfr_get( 'tpl_placeholder', __( 'Enter password…', 'lockfront' ) ),
			'btn_text'    => lkfr_get( 'tpl_btn_text',    __( 'Unlock', 'lockfront' ) ),
			'footer_text' => lkfr_get( 'tpl_footer_text', '' ),
			'error_msg'   => lkfr_get( 'error_message',   __( 'Incorrect password. Please try again.', 'lockfront' ) ),

			// Layout
			'form_placement' => lkfr_get( 'tpl_form_placement', 'center' ),

			// Background: color | gradient | image
			'bg_type'         => lkfr_get( 'tpl_bg_type',         'color' ),
			'bg_color'        => lkfr_get( 'tpl_bg_color',        '#1a1a2e' ),
			'bg_grad_start'   => lkfr_get( 'tpl_bg_grad_start',   '#1a1a2e' ),
			'bg_grad_end'     => lkfr_get( 'tpl_bg_grad_end',     '#16213e' ),
			'bg_grad_angle'   => lkfr_get( 'tpl_bg_grad_angle',   '135' ),
			'bg_image'        => lkfr_get( 'tpl_bg_image',        '' ),
			'overlay_color'   => lkfr_get( 'tpl_overlay_color',   '#000000' ),
			'overlay_opacity' => lkfr_get( 'tpl_overlay_opacity', '0.5' ),

			// Card
			'card_bg'     => lkfr_get( 'tpl_card_bg',     '#ffffff' ),
			'card_radius' => lkfr_get( 'tpl_card_radius', '16' ),
			'card_shadow' => lkfr_get( 'tpl_card_shadow', '1' ),
			'card_width'  => lkfr_get( 'tpl_card_width',  '440' ),

			// Typography
			'font_family'   => lkfr_get( 'tpl_font_family',   "'Inter','Helvetica Neue',Arial,sans-serif" ),
			'google_font'   => lkfr_get( 'tpl_google_font',   '' ),
			'heading_color' => lkfr_get( 'tpl_heading_color', '#1a1a2e' ),
			'subtext_color' => lkfr_get( 'tpl_subtext_color', '#666666' ),

			// Logo
			'logo_url'   => lkfr_get( 'tpl_logo_url',   '' ),
			'logo_width' => lkfr_get( 'tpl_logo_width',  '120' ),

			// Input
			'input_bg'     => lkfr_get( 'tpl_input_bg',     '#f5f5f5' ),
			'input_border' => lkfr_get( 'tpl_input_border', '#dddddd' ),
			'input_color'  => lkfr_get( 'tpl_input_color',  '#333333' ),
			'input_radius' => lkfr_get( 'tpl_input_radius', '8' ),
			'input_focus'  => lkfr_get( 'tpl_input_focus',  '#6366f1' ),

			// Button
			'btn_bg'     => lkfr_get( 'tpl_btn_bg',    '#6366f1' ),
			'btn_hover'  => lkfr_get( 'tpl_btn_hover', '#4f46e5' ),
			'btn_color'  => lkfr_get( 'tpl_btn_color', '#ffffff' ),
			'btn_radius' => lkfr_get( 'tpl_btn_radius', '8' ),

			// Error
			'error_color' => lkfr_get( 'tpl_error_color', '#ef4444' ),

			// Runtime
			'nonce'    => wp_create_nonce( 'lkfr_login' ),
			'ajax_url' => admin_url( 'admin-ajax.php' ),
			'redirect' => $redirect,
			'site'     => get_bloginfo( 'name' ),
			'favicon'  => get_site_icon_url( 32 ),
		);
	}

	// -----------------------------------------------------------------------
	// Helpers
	// -----------------------------------------------------------------------

	/**
	 * Convert a hex colour and opacity to an rgba() CSS value.
	 *
	 * @param string    $hex     Hex colour (with or without #).
	 * @param float|int $opacity 0–1.
	 * @return string
	 */
	private function rgba( $hex, $opacity ) {
		$hex = ltrim( $hex, '#' );
		if ( 3 === strlen( $hex ) ) {
			$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
		}
		return sprintf(
			'rgba(%d,%d,%d,%s)',
			hexdec( substr( $hex, 0, 2 ) ),
			hexdec( substr( $hex, 2, 2 ) ),
			hexdec( substr( $hex, 4, 2 ) ),
			(float) $opacity
		);
	}

	// -----------------------------------------------------------------------
	// HTML
	// -----------------------------------------------------------------------

	/**
	 * Output the full HTML page.
	 *
	 * @param array $d Template data.
	 */
	private function html( $d ) {

		// Background CSS
		$bg_type = $d['bg_type'];
		if ( 'image' === $bg_type && $d['bg_image'] ) {
			$bg_css = 'background-image:url(' . esc_url( $d['bg_image'] ) . ');background-size:cover;background-position:center;';
		} elseif ( 'gradient' === $bg_type ) {
			$angle = absint( $d['bg_grad_angle'] );
			$bg_css = 'background:linear-gradient(' . $angle . 'deg,' . esc_attr( $d['bg_grad_start'] ) . ',' . esc_attr( $d['bg_grad_end'] ) . ');';
		} else {
			$bg_css = 'background-color:' . esc_attr( $d['bg_color'] ) . ';';
		}

		$overlay_show = ( 'image' === $bg_type && $d['bg_image'] && (float) $d['overlay_opacity'] > 0 );
		$overlay_rgba = $overlay_show ? $this->rgba( $d['overlay_color'], $d['overlay_opacity'] ) : '';

		$card_shadow_css = '1' === (string) $d['card_shadow']
			? 'box-shadow:0 20px 60px rgba(0,0,0,.15);'
			: '';

		$focus_ring = $this->rgba( $d['input_focus'], 0.18 );
		$error_bg   = $this->rgba( $d['error_color'], 0.08 );
		$error_bdr  = $this->rgba( $d['error_color'], 0.22 );

		// Form placement
		$placement = in_array( $d['form_placement'], array( 'left', 'right', 'center' ), true )
			? $d['form_placement'] : 'center';

		$justify  = array( 'left' => 'flex-start', 'right' => 'flex-end', 'center' => 'center' );
		$side_pad = 'center' === $placement ? '20px' : '60px';

		// Google Font
		$gfont_import = '';
		$active_font  = esc_attr( $d['font_family'] );
		if ( ! empty( $d['google_font'] ) ) {
			$slug         = rawurlencode( $d['google_font'] );
			$gfont_import = "@import url('https://fonts.googleapis.com/css2?family={$slug}:wght@300;400;500;600;700&display=swap');";
			$active_font  = "'" . esc_js( $d['google_font'] ) . "',sans-serif";
		}

		?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title><?php echo esc_html( $d['headline'] ); ?> &mdash; <?php echo esc_html( $d['site'] ); ?></title>
<?php if ( $d['favicon'] ) : ?>
<link rel="icon" href="<?php echo esc_url( $d['favicon'] ); ?>">
<?php endif; ?>
<style>
<?php if ( $gfont_import ) echo $gfont_import . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput ?>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{
	--lkfr-font:<?php echo $active_font; // phpcs:ignore WordPress.Security.EscapeOutput ?>;
	--lkfr-card-bg:<?php echo esc_attr( $d['card_bg'] ); ?>;
	--lkfr-card-r:<?php echo absint( $d['card_radius'] ); ?>px;
	--lkfr-card-w:<?php echo absint( $d['card_width'] ); ?>px;
	--lkfr-h-col:<?php echo esc_attr( $d['heading_color'] ); ?>;
	--lkfr-s-col:<?php echo esc_attr( $d['subtext_color'] ); ?>;
	--lkfr-in-bg:<?php echo esc_attr( $d['input_bg'] ); ?>;
	--lkfr-in-bd:<?php echo esc_attr( $d['input_border'] ); ?>;
	--lkfr-in-col:<?php echo esc_attr( $d['input_color'] ); ?>;
	--lkfr-in-r:<?php echo absint( $d['input_radius'] ); ?>px;
	--lkfr-in-foc:<?php echo esc_attr( $d['input_focus'] ); ?>;
	--lkfr-btn-bg:<?php echo esc_attr( $d['btn_bg'] ); ?>;
	--lkfr-btn-hov:<?php echo esc_attr( $d['btn_hover'] ); ?>;
	--lkfr-btn-col:<?php echo esc_attr( $d['btn_color'] ); ?>;
	--lkfr-btn-r:<?php echo absint( $d['btn_radius'] ); ?>px;
	--lkfr-err-col:<?php echo esc_attr( $d['error_color'] ); ?>;
	--lkfr-focus-ring:<?php echo esc_attr( $focus_ring ); ?>;
	--lkfr-err-bg:<?php echo esc_attr( $error_bg ); ?>;
	--lkfr-err-bdr:<?php echo esc_attr( $error_bdr ); ?>;
}
html,body{height:100%;font-family:var(--lkfr-font);-webkit-font-smoothing:antialiased}
body{
	<?php echo $bg_css; // phpcs:ignore WordPress.Security.EscapeOutput ?>
	min-height:100vh;
	display:flex;
	align-items:center;
	justify-content:<?php echo esc_attr( $justify[ $placement ] ); ?>;
	padding:<?php echo esc_attr( $side_pad ); ?>;
	position:relative;
}
<?php if ( $overlay_show ) : ?>
body::before{content:'';position:fixed;inset:0;background:<?php echo esc_attr( $overlay_rgba ); ?>;z-index:0;pointer-events:none}
<?php endif; ?>
.lkfr-wrap{position:relative;z-index:1;width:100%;max-width:var(--lkfr-card-w)}
.lkfr-card{background:var(--lkfr-card-bg);border-radius:var(--lkfr-card-r);<?php echo $card_shadow_css; // phpcs:ignore WordPress.Security.EscapeOutput ?>padding:48px 40px;width:100%}
.lkfr-logo{display:flex;justify-content:center;margin-bottom:28px}
.lkfr-logo img{max-width:<?php echo absint( $d['logo_width'] ); ?>px;height:auto;display:block}
.lkfr-icon{display:flex;justify-content:center;margin-bottom:20px}
.lkfr-icon svg{width:48px;height:48px;color:var(--lkfr-btn-bg)}
.lkfr-h1{color:var(--lkfr-h-col);font-size:1.5rem;font-weight:600;text-align:center;margin-bottom:8px;line-height:1.3}
.lkfr-sub{color:var(--lkfr-s-col);font-size:.9375rem;text-align:center;margin-bottom:32px;line-height:1.6}
.lkfr-field{position:relative;margin-bottom:14px}
.lkfr-input{width:100%;background:var(--lkfr-in-bg);border:1.5px solid var(--lkfr-in-bd);border-radius:var(--lkfr-in-r);color:var(--lkfr-in-col);font-family:var(--lkfr-font);font-size:1rem;padding:14px 48px 14px 16px;outline:none;transition:border-color .2s,box-shadow .2s;-webkit-appearance:none}
.lkfr-input:focus{border-color:var(--lkfr-in-foc);box-shadow:0 0 0 3px var(--lkfr-focus-ring)}
.lkfr-input::placeholder{color:var(--lkfr-s-col);opacity:.7}
.lkfr-eye{position:absolute;right:14px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;padding:4px;color:var(--lkfr-s-col);line-height:0;transition:color .2s}
.lkfr-eye:hover{color:var(--lkfr-h-col)}
.lkfr-eye svg{width:18px;height:18px}
.lkfr-btn{width:100%;background:var(--lkfr-btn-bg);color:var(--lkfr-btn-col);border:none;border-radius:var(--lkfr-btn-r);font-family:var(--lkfr-font);font-size:1rem;font-weight:600;padding:15px 24px;cursor:pointer;transition:background .2s,transform .1s,opacity .2s;margin-top:6px;position:relative;overflow:hidden;letter-spacing:.01em}
.lkfr-btn:hover:not(:disabled){background:var(--lkfr-btn-hov)}
.lkfr-btn:active:not(:disabled){transform:scale(.98)}
.lkfr-btn:disabled{opacity:.7;cursor:wait}
.lkfr-btn-txt{display:block}
.lkfr-btn-spin{display:none;position:absolute;inset:0;align-items:center;justify-content:center;background:inherit}
.lkfr-btn.loading .lkfr-btn-txt{opacity:0}
.lkfr-btn.loading .lkfr-btn-spin{display:flex}
.lkfr-spinner{width:20px;height:20px;border:2px solid rgba(255,255,255,.3);border-top-color:#fff;border-radius:50%;animation:lkfr-spin .7s linear infinite}
@keyframes lkfr-spin{to{transform:rotate(360deg)}}
.lkfr-msg{display:none;padding:12px 16px;border-radius:var(--lkfr-in-r);font-size:.875rem;margin-top:14px;text-align:center;line-height:1.4}
.lkfr-msg.error{display:block;background:var(--lkfr-err-bg);color:var(--lkfr-err-col);border:1px solid var(--lkfr-err-bdr)}
.lkfr-footer{color:var(--lkfr-s-col);font-size:.8125rem;text-align:center;margin-top:28px;line-height:1.5;opacity:.8}
@keyframes lkfr-shake{0%,100%{transform:translateX(0)}20%,60%{transform:translateX(-6px)}40%,80%{transform:translateX(6px)}}
.lkfr-shake{animation:lkfr-shake .4s ease-in-out}
@media(max-width:600px){body{padding:20px!important;justify-content:center!important}.lkfr-card{padding:32px 20px}.lkfr-h1{font-size:1.25rem}}
</style>
</head>
<body>
<div class="lkfr-wrap">
<div class="lkfr-card">

<?php if ( $d['logo_url'] ) : ?>
<div class="lkfr-logo">
	<img src="<?php echo esc_url( $d['logo_url'] ); ?>" alt="<?php echo esc_attr( $d['site'] ); ?>">
</div>
<?php else : ?>
<div class="lkfr-icon" aria-hidden="true">
	<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
		<rect x="3" y="11" width="18" height="11" rx="2"/>
		<path d="M7 11V7a5 5 0 0 1 10 0v4"/>
	</svg>
</div>
<?php endif; ?>

<h1 class="lkfr-h1"><?php echo esc_html( $d['headline'] ); ?></h1>
<p class="lkfr-sub"><?php echo esc_html( $d['subheadline'] ); ?></p>

<div class="lkfr-field">
	<input type="password" id="lkfr-pw" class="lkfr-input"
		placeholder="<?php echo esc_attr( $d['placeholder'] ); ?>"
		autocomplete="current-password" autofocus>
	<button type="button" class="lkfr-eye" id="lkfr-eye"
		aria-label="<?php esc_attr_e( 'Show or hide password', 'lockfront' ); ?>">
		<svg id="lkfr-eye-on" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
		<svg id="lkfr-eye-off" style="display:none" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
	</button>
</div>

<button type="button" class="lkfr-btn" id="lkfr-submit">
	<span class="lkfr-btn-txt"><?php echo esc_html( $d['btn_text'] ); ?></span>
	<span class="lkfr-btn-spin" aria-hidden="true"><span class="lkfr-spinner"></span></span>
</button>

<div class="lkfr-msg" id="lkfr-msg" role="alert" aria-live="polite"></div>

<?php if ( $d['footer_text'] ) : ?>
<div class="lkfr-footer"><?php echo wp_kses_post( $d['footer_text'] ); ?></div>
<?php endif; ?>

</div><!-- .lkfr-card -->
</div><!-- .lkfr-wrap -->

<script>
(function(){
	var pw      = document.getElementById('lkfr-pw');
	var btn     = document.getElementById('lkfr-submit');
	var msg     = document.getElementById('lkfr-msg');
	var eye     = document.getElementById('lkfr-eye');
	var eyeOn   = document.getElementById('lkfr-eye-on');
	var eyeOff  = document.getElementById('lkfr-eye-off');
	var ajaxUrl = <?php echo wp_json_encode( $d['ajax_url'] ); ?>;
	var nonce   = <?php echo wp_json_encode( $d['nonce'] ); ?>;
	var errMsg  = <?php echo wp_json_encode( esc_html( $d['error_msg'] ) ); ?>;
	var redirect= <?php echo wp_json_encode( $d['redirect'] ); ?>;
	var netErr  = <?php echo wp_json_encode( esc_html__( 'Network error. Please try again.', 'lockfront' ) ); ?>;

	eye.addEventListener('click', function(){
		var show = pw.type === 'password';
		pw.type = show ? 'text' : 'password';
		eyeOn.style.display  = show ? 'none' : '';
		eyeOff.style.display = show ? '' : 'none';
		pw.focus();
	});

	pw.addEventListener('keydown', function(e){ if(e.key==='Enter') submit(); });
	btn.addEventListener('click', submit);

	function submit(){
		if(!pw.value){ shake(); return; }
		setLoading(true);
		clearMsg();
		var fd = new FormData();
		fd.append('action',       'lkfr_login');
		fd.append('lkfr_nonce',   nonce);
		fd.append('lkfr_password',pw.value);
		fd.append('lkfr_redirect',redirect);
		fetch(ajaxUrl,{method:'POST',body:fd})
			.then(function(r){return r.json();})
			.then(function(r){
				if(r.success){ window.location.href=r.data.redirect; }
				else{
					setLoading(false);
					showErr(r.data&&r.data.message?r.data.message:errMsg);
					pw.value=''; pw.focus(); shake();
				}
			})
			.catch(function(){ setLoading(false); showErr(netErr); });
	}
	function setLoading(on){ btn.disabled=on; btn.classList.toggle('loading',on); }
	function showErr(t){ msg.textContent=t; msg.className='lkfr-msg error'; }
	function clearMsg(){ msg.textContent=''; msg.className='lkfr-msg'; }
	function shake(){ pw.classList.remove('lkfr-shake'); void pw.offsetWidth; pw.classList.add('lkfr-shake'); }
})();
</script>
</body>
</html>
		<?php
	}

	// -----------------------------------------------------------------------
	// Admin preview
	// -----------------------------------------------------------------------

	/** AJAX handler — renders the template for admin preview. */
	public static function ajax_preview() {
		$nonce = isset( $_GET['nonce'] ) ? sanitize_text_field( wp_unslash( $_GET['nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'lkfr_preview' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'lockfront' ), 403 );
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized.', 'lockfront' ), 403 );
		}
		( new self() )->render();
		wp_die();
	}
}
