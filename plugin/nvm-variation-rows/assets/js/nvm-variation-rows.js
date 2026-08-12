/**
 * NovaMira Variation Rows.
 *
 * The rows never compute anything: they set the value of the native WooCommerce <select>
 * and let the core variation form recalculate price, stock and availability. Everything the
 * rows display afterwards comes from the variation object core hands back on show_variation.
 */
(function ($) {
	'use strict';

	var SELECTED = 'is-selected';

	function VariationRows($form) {
		this.$form = $form;
		this.$rows = $form.find('[data-nvm-rows]');
		this.$total = $form.find('[data-nvm-total]');

		if (!this.$rows.length) {
			return;
		}

		this.attribute = this.$rows.attr('data-attribute');
		this.$select = $form.find('select[name="' + this.attribute + '"]');

		if (!this.$select.length) {
			return;
		}

		this.$form.addClass('nvm-vr-active');
		this.bind();
		this.markFromSelect();
	}

	VariationRows.prototype.bind = function () {
		var self = this;

		this.$rows.on('click', '.nvm-vr__row', function (event) {
			// Let the info icon show its tooltip without changing the selection.
			if ($(event.target).is('.nvm-vr__info')) {
				return;
			}

			self.select($(this));
		});

		this.$rows.on('keydown', '.nvm-vr__row', function (event) {
			var $all = self.$rows.find('.nvm-vr__row');
			var index = $all.index($(this));

			switch (event.key) {
				case 'ArrowDown':
				case 'ArrowRight':
					event.preventDefault();
					self.select($all.eq((index + 1) % $all.length));
					break;
				case 'ArrowUp':
				case 'ArrowLeft':
					event.preventDefault();
					self.select($all.eq((index - 1 + $all.length) % $all.length));
					break;
				case ' ':
				case 'Enter':
					event.preventDefault();
					self.select($(this));
					break;
				default:
					break;
			}
		});

		this.$form.on('show_variation', function (event, variation) {
			self.markFromSelect();
			self.updateTotal(variation);
		});

		this.$form.on('hide_variation reset_data', function () {
			self.markFromSelect();
			self.hideTotal();
		});
	};

	/**
	 * Hand the choice over to the native select.
	 */
	VariationRows.prototype.select = function ($row) {
		var value = $row.attr('data-value');

		if (typeof value === 'undefined') {
			return;
		}

		this.$select.val(value).trigger('change');
		this.markFromSelect();
		$row.trigger('focus');
	};

	/**
	 * The select is the single source of truth for which row is active.
	 */
	VariationRows.prototype.markFromSelect = function () {
		var current = String(this.$select.val() || '');
		var $all = this.$rows.find('.nvm-vr__row');
		var matched = false;

		$all.each(function () {
			var isCurrent = current !== '' && $(this).attr('data-value') === current;

			matched = matched || isCurrent;

			$(this)
				.toggleClass(SELECTED, isCurrent)
				.attr('aria-checked', isCurrent ? 'true' : 'false')
				.attr('tabindex', isCurrent ? '0' : '-1');
		});

		// With nothing selected the group still needs one entry point for the keyboard.
		if (!matched && $all.length) {
			$all.eq(0).attr('tabindex', '0');
		}
	};

	VariationRows.prototype.updateTotal = function (variation) {
		if (!this.$total.length || !variation || !variation.nvm) {
			return;
		}

		var data = variation.nvm;

		this.$total.find('[data-nvm-total-value]').html(data.total_html);
		this.$total
			.find('[data-nvm-total-note]')
			.text(data.has_extra ? nvmVrStrings.extraNote : '');
		this.$total.prop('hidden', false);
	};

	VariationRows.prototype.hideTotal = function () {
		if (this.$total.length) {
			this.$total.prop('hidden', true);
		}
	};

	/**
	 * Quantity stepper. The input, its bounds and the form submission stay WooCommerce's;
	 * these buttons only write a value and fire the change the rest of the page listens for.
	 */
	function stepQuantity($input, direction) {
		var step = parseFloat($input.attr('step')) || 1;
		var min = parseFloat($input.attr('min'));
		var max = parseFloat($input.attr('max'));
		var current = parseFloat($input.val());

		if (isNaN(current)) {
			current = isNaN(min) ? 0 : min;
		}

		var next = current + direction * step;

		if (!isNaN(min) && next < min) {
			next = min;
		}
		if (!isNaN(max) && next > max) {
			next = max;
		}

		if (next === current) {
			return;
		}

		$input.val(next).trigger('change');
	}

	$(document).on('click', '.nvm-qty__btn', function (event) {
		event.preventDefault();

		var $input = $(this).closest('.nvm-qty').find('input.qty');

		if ($input.length) {
			stepQuantity($input, parseInt($(this).attr('data-nvm-step'), 10) || 0);
		}
	});

	$(function () {
		$('.variations_form').each(function () {
			new VariationRows($(this));
		});
	});
})(jQuery);
