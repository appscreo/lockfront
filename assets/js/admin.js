/* global LKFR, wp, jQuery */
/**
 * Lockfront admin JavaScript.
 * Handles: colour pickers, image uploader, range sliders,
 * Google font preview, gradient live preview, bypass token AJAX,
 * log clearing, password toggle.
 */
(function ($) {
	'use strict';

	// ----------------------------------------------------------------
	// Colour pickers
	// ----------------------------------------------------------------
	$('.lkfr-color').wpColorPicker({
		change: function () {
			// Trigger gradient preview update when a gradient colour changes
			setTimeout( updateGradientPreview, 50 );
		},
	});

	// ----------------------------------------------------------------
	// Range sliders – update adjacent value display
	// ----------------------------------------------------------------
	$(document).on('input change', '.lkfr-range', function () {
		$(this).next('.lkfr-range-val').text(this.value);
		// If the gradient angle slider changed, update the preview
		if (this.id === 'lkfr-grad-angle') {
			updateGradientPreview();
		}
	});

	// ----------------------------------------------------------------
	// Background type radio – show/hide relevant rows
	// ----------------------------------------------------------------
	function applyBgType(type) {
		$('.lkfr-bg-rows').hide();
		$('.lkfr-bg-' + type).show();
		if (type === 'gradient') updateGradientPreview();
	}

	$(document).on('change', '.lkfr-bg-type-radio', function () {
		applyBgType($(this).val());
	});

	// Run on page load to show the correct rows
	var $checkedBg = $('.lkfr-bg-type-radio:checked');
	if ($checkedBg.length) applyBgType($checkedBg.val());

	// ----------------------------------------------------------------
	// Gradient live preview swatch
	// ----------------------------------------------------------------
	function updateGradientPreview() {
		var start = $('#lkfr-tpl_bg_grad_start').val() || '#1a1a2e';
		var end   = $('#lkfr-tpl_bg_grad_end').val()   || '#16213e';
		var angle = $('#lkfr-grad-angle').val()         || '135';
		$('#lkfr-grad-preview').css(
			'background',
			'linear-gradient(' + angle + 'deg,' + start + ',' + end + ')'
		);
	}

	// ----------------------------------------------------------------
	// Password show/hide
	// ----------------------------------------------------------------
	$(document).on('click', '.lkfr-pw-toggle', function () {
		var $btn = $(this);
		var $inp = $('#' + $btn.data('target'));
		var show = $inp.attr('type') === 'password';
		$inp.attr('type', show ? 'text' : 'password');
		$btn.text(show ? LKFR.i18n.hide : LKFR.i18n.show);
	});

	// ----------------------------------------------------------------
	// WP Media Library image uploader
	// wp_enqueue_media() is called server-side so wp.media is available
	// ----------------------------------------------------------------
	$(document).on('click', '.lkfr-img-btn', function (e) {
		e.preventDefault();
		var targetId  = $(this).data('target');
		var previewId = $(this).data('preview');
		var $rmBtn    = $(this).siblings('.lkfr-img-rm');

		var frame = wp.media({
			title:   'Select Image',
			button:  { text: 'Use this image' },
			multiple: false,
			library:  { type: 'image' },
		});

		frame.on('select', function () {
			var att = frame.state().get('selection').first().toJSON();
			$('#' + targetId).val(att.url);
			$('#' + previewId).attr('src', att.url).show();
			$rmBtn.show();
			// If we just set a background image and the type radio is "image", refresh
			updateGradientPreview();
		});

		frame.open();
	});

	$(document).on('click', '.lkfr-img-rm', function (e) {
		e.preventDefault();
		var targetId  = $(this).data('target');
		var previewId = $(this).data('preview');
		$('#' + targetId).val('');
		$('#' + previewId).attr('src', '').hide();
		$(this).hide();
	});

	// ----------------------------------------------------------------
	// Google Font live preview
	// ----------------------------------------------------------------
	$('#lkfr-gfont-preview').on('click', function () {
		var font = $('#lkfr-gfont').val().trim();
		if (!font) return;

		var slug = encodeURIComponent(font).replace(/%20/g, '+');
		var href = 'https://fonts.googleapis.com/css2?family=' + slug + ':wght@400;600&display=swap';

		if (!$('link[href="' + href + '"]').length) {
			$('<link rel="stylesheet">').attr('href', href).appendTo('head');
		}

		$('#lkfr-gfont-sample')
			.text('Aa Bb Cc — ' + font + '  /  1 2 3 4 5 6 7 8 9 0')
			.css('font-family', '"' + font + '", sans-serif')
			.show();
	});

	// ----------------------------------------------------------------
	// Clear logs (AJAX)
	// ----------------------------------------------------------------
	$(document).on('click', '#lkfr-clear-logs', function () {
		if (!confirm(LKFR.i18n.confirm_clr)) return;
		var $btn = $(this).prop('disabled', true);
		$.post(LKFR.ajax, {
			action: 'lkfr_clear_logs',
			nonce:  LKFR.nonce_logs,
		}, function (res) {
			$btn.prop('disabled', false);
			if (res.success) {
				$('#lkfr-logs-tbody').html(
					'<tr><td colspan="5" style="text-align:center;padding:24px;color:#777">' +
					'No log entries yet.</td></tr>'
				);
			}
		});
	});

	// ----------------------------------------------------------------
	// Bypass token – create
	// ----------------------------------------------------------------
	$(document).on('click', '#lkfr-create-token', function () {
		var label   = $('#lkfr-tok-lbl').val().trim();
		var expires = $('#lkfr-tok-exp').val();
		var uses    = parseInt($('#lkfr-tok-uses').val(), 10) || 0;

		if (!expires) {
			alert(LKFR.i18n.no_expiry);
			return;
		}

		var $btn = $(this).prop('disabled', true).text(LKFR.i18n.creating);

		$.post(LKFR.ajax, {
			action:     'lkfr_create_token',
			nonce:      LKFR.nonce_token,
			label:      label,
			expires_at: expires,
			max_uses:   uses,
		}, function (res) {
			$btn.prop('disabled', false).text(LKFR.i18n.generate);
			if (res.success) {
				$('#lkfr-new-url').val(res.data.url);
				$('#lkfr-new-result').show();
				$('#lkfr-no-tokens').remove();
				$('#lkfr-tokens-tbody').prepend(res.data.row);
				// Reset form
				$('#lkfr-tok-lbl').val('');
				$('#lkfr-tok-exp').val('');
				$('#lkfr-tok-uses').val(0);
			} else {
				alert((res.data && res.data.message) || LKFR.i18n.err_token);
			}
		}).fail(function () {
			$btn.prop('disabled', false).text(LKFR.i18n.generate);
			alert(LKFR.i18n.err_token);
		});
	});

	// Copy newly-created token link
	$(document).on('click', '#lkfr-copy-new', function () {
		copyText($('#lkfr-new-url').val(), this, LKFR.i18n.copy);
	});

	// Copy existing token URL
	$(document).on('click', '.lkfr-copy-tok', function () {
		copyText($(this).data('url'), this, LKFR.i18n.copy_url);
	});

	// Delete token
	$(document).on('click', '.lkfr-del-tok', function () {
		if (!confirm(LKFR.i18n.confirm_del)) return;
		var id   = $(this).data('id');
		var $row = $('#lkfr-tok-' + id);
		$.post(LKFR.ajax, {
			action:   'lkfr_delete_token',
			nonce:    LKFR.nonce_token,
			token_id: id,
		}, function (res) {
			if (res.success) {
				$row.fadeOut(200, function () { $(this).remove(); });
			}
		});
	});

	// ----------------------------------------------------------------
	// Clipboard helper
	// ----------------------------------------------------------------
	function copyText(text, btn, origLabel) {
		if (navigator.clipboard && window.isSecureContext) {
			navigator.clipboard.writeText(text).catch(fallback);
		} else {
			fallback();
		}
		$(btn).text(LKFR.i18n.copied);
		setTimeout(function () { $(btn).text(origLabel); }, 2200);

		function fallback() {
			var $el = $('<textarea>')
				.val(text)
				.css({ position: 'fixed', top: 0, left: 0, opacity: 0 })
				.appendTo('body');
			$el[0].select();
			try { document.execCommand('copy'); } catch (err) { /* silent */ }
			$el.remove();
		}
	}

}(jQuery));
