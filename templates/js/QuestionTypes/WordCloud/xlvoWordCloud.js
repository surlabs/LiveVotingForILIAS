/**
 * Word cloud results in the presenter view (new UI).
 *
 * The server sends the grouped words (most voted first) and this script places them: size by square root of the votes,
 * spiral placement without overlaps, and a notice for the words that still do not fit after shrinking them, instead of
 * dropping them silently. xlvoPlayer replaces the results HTML on every refresh and calls render() again; the words
 * already seen and the pinned tooltip survive the refresh.
 *
 * The moderator clicks a word to pin its tooltip, and the button there removes the word with all its repetitions.
 */
var xlvoWordCloud = {
	MIN_FONT: 14,
	MAX_FONT: 64,
	COLORS: ['#3b82f6', '#10b981', '#f59e0b', '#ef4444'],
	seen: {},
	pinned: null,

	render: function (scope) {
		$('.xlvo-word-cloud', scope || document).each(function () {
			xlvoWordCloud.renderCloud(this);
		});
	},

	renderCloud: function (cloud) {
		var words = [];
		try {
			words = JSON.parse(cloud.getAttribute('data-xlvo-words') || '[]');
		} catch (e) {
			words = [];
		}

		var canvas = cloud.querySelector('.xlvo-word-cloud-canvas');
		var notice = cloud.querySelector('.xlvo-word-cloud-overflow');
		$(canvas).find('.xlvo-word-cloud-word').remove();

		var keys = {};
		words.forEach(function (word) {
			keys[word.key] = true;
		});
		if (xlvoWordCloud.pinned !== null && !keys[xlvoWordCloud.pinned]) {
			xlvoWordCloud.pinned = null;
		}

		if (words.length === 0) {
			notice.classList.remove('is-visible');
			xlvoWordCloud.hideTooltip(cloud);
			return;
		}

		var max_count = words[0].count;
		var min_count = words[words.length - 1].count;
		var width = canvas.offsetWidth;
		var height = canvas.offsetHeight;
		var placed = [];
		var dropped = 0;

		words.forEach(function (word) {
			var element = document.createElement('button');
			element.type = 'button';
			element.className = 'xlvo-word-cloud-word';
			element.textContent = word.label;
			element.setAttribute('data-key', word.key);

			// Square root scaling: with a linear scale one popular word squeezes every other word to the minimum.
			var size = (xlvoWordCloud.MIN_FONT + xlvoWordCloud.MAX_FONT) / 2;
			if (max_count > min_count) {
				var t = (Math.sqrt(word.count) - Math.sqrt(min_count)) / (Math.sqrt(max_count) - Math.sqrt(min_count));
				size = xlvoWordCloud.MIN_FONT + t * (xlvoWordCloud.MAX_FONT - xlvoWordCloud.MIN_FONT);
			}
			element.style.fontSize = size + 'px';
			element.style.fontWeight = word.count > max_count * 0.5 ? '700' : '500';
			element.style.color = xlvoWordCloud.COLORS[Math.abs(xlvoWordCloud.hash(word.label)) % xlvoWordCloud.COLORS.length];
			element.style.visibility = 'hidden';
			canvas.appendChild(element);

			// A long German compound at full size can be wider than the canvas: shrink it to fit.
			var max_width = width * 0.92;
			if (element.offsetWidth > max_width) {
				element.style.fontSize = Math.max(10, size * (max_width / element.offsetWidth)) + 'px';
			}

			if (!xlvoWordCloud.place(element, placed, width, height)) {
				element.style.fontSize = xlvoWordCloud.MIN_FONT + 'px';
				if (!xlvoWordCloud.place(element, placed, width, height)) {
					element.remove();
					dropped++;
					return;
				}
			}

			element.style.visibility = '';
			if (!xlvoWordCloud.seen[word.key]) {
				element.classList.add('is-new');
				xlvoWordCloud.seen[word.key] = true;
			}
			if (word.key === xlvoWordCloud.pinned) {
				element.classList.add('is-pinned');
			}
			element.setAttribute('aria-label', word.label + ', ' + xlvoWordCloud.stats(cloud, word));
			$(element).data('word', word);
		});

		if (dropped > 0) {
			var template = cloud.getAttribute(dropped === 1 ? 'data-txt-overflow-one' : 'data-txt-overflow-many');
			notice.textContent = template.replace('%s', String(dropped));
			notice.classList.add('is-visible');
		} else {
			notice.classList.remove('is-visible');
		}

		var pinned_element = xlvoWordCloud.pinned !== null ? canvas.querySelector('.xlvo-word-cloud-word.is-pinned') : null;
		if (pinned_element) {
			xlvoWordCloud.showTooltip(cloud, pinned_element, true);
		} else {
			xlvoWordCloud.pinned = null;
			xlvoWordCloud.hideTooltip(cloud);
		}
	},

	/**
	 * Spiral from the centre, flattened because the canvas is wider than tall; first free spot wins.
	 */
	place: function (element, placed, width, height) {
		var w = element.offsetWidth;
		var h = element.offsetHeight;
		var angle = 0;
		var radius = 0;
		var gap = 4;

		for (var i = 0; i < 1500; i++) {
			var x = width / 2 + radius * Math.cos(angle) - w / 2;
			var y = height / 2 + radius * Math.sin(angle) * 0.6 - h / 2;

			if (x >= 0 && y >= 0 && x + w <= width && y + h <= height) {
				var overlapping = false;
				for (var j = 0; j < placed.length; j++) {
					var o = placed[j];
					if (x < o.right + gap && x + w + gap > o.left && y < o.bottom + gap && y + h + gap > o.top) {
						overlapping = true;
						break;
					}
				}
				if (!overlapping) {
					element.style.left = x + 'px';
					element.style.top = y + 'px';
					placed.push({left: x, right: x + w, top: y, bottom: y + h});
					return true;
				}
			}
			angle += 0.35;
			radius += 1.2;
		}

		return false;
	},

	hash: function (text) {
		var hash = 0;
		for (var i = 0; i < text.length; i++) {
			hash = text.charCodeAt(i) + ((hash << 5) - hash);
			hash |= 0;
		}
		return hash;
	},

	stats: function (cloud, word) {
		var total = parseInt(cloud.getAttribute('data-xlvo-total'), 10) || 0;
		var share = total > 0 ? (word.count / total * 100).toFixed(1) : '0.0';
		var votes = cloud.getAttribute(word.count === 1 ? 'data-txt-vote' : 'data-txt-votes').replace('%s', String(word.count));

		return votes + ' • ' + cloud.getAttribute('data-txt-share').replace('%s', share);
	},

	showTooltip: function (cloud, element, pinned) {
		var word = $(element).data('word');
		var canvas = cloud.querySelector('.xlvo-word-cloud-canvas');
		var tooltip = cloud.querySelector('.xlvo-word-cloud-tooltip');
		if (!word || !tooltip) {
			return;
		}

		tooltip.querySelector('.xlvo-word-cloud-tooltip-word').textContent = word.label;
		tooltip.querySelector('.xlvo-word-cloud-tooltip-stats').textContent = xlvoWordCloud.stats(cloud, word);
		tooltip.classList.toggle('is-pinned', pinned);
		tooltip.classList.add('is-visible');

		var left = element.offsetLeft + element.offsetWidth / 2 - tooltip.offsetWidth / 2;
		left = Math.max(4, Math.min(left, canvas.offsetWidth - tooltip.offsetWidth - 4));
		var top = element.offsetTop - tooltip.offsetHeight - 10;
		// Words on the top edge get the tooltip below them, so it is never cut off.
		tooltip.classList.toggle('is-below', top < 0);
		if (top < 0) {
			top = element.offsetTop + element.offsetHeight + 10;
		}
		tooltip.style.left = left + 'px';
		tooltip.style.top = top + 'px';
	},

	hideTooltip: function (cloud) {
		var tooltip = cloud.querySelector('.xlvo-word-cloud-tooltip');
		if (tooltip) {
			tooltip.classList.remove('is-visible', 'is-pinned');
		}
	},

	unpin: function (cloud) {
		xlvoWordCloud.pinned = null;
		$(cloud).find('.xlvo-word-cloud-word.is-pinned').removeClass('is-pinned');
		xlvoWordCloud.hideTooltip(cloud);
	},

	init: function () {
		var words = '.xlvo-word-cloud .xlvo-word-cloud-word';

		$(document).off('.xlvoWordCloud')
			.on('mouseenter.xlvoWordCloud focus.xlvoWordCloud', words, function () {
				if (xlvoWordCloud.pinned === null) {
					xlvoWordCloud.showTooltip($(this).closest('.xlvo-word-cloud')[0], this, false);
				}
			})
			.on('mouseleave.xlvoWordCloud blur.xlvoWordCloud', words, function () {
				if (xlvoWordCloud.pinned === null) {
					xlvoWordCloud.hideTooltip($(this).closest('.xlvo-word-cloud')[0]);
				}
			})
			.on('click.xlvoWordCloud', words, function (event) {
				event.stopPropagation();
				var cloud = $(this).closest('.xlvo-word-cloud')[0];
				var key = this.getAttribute('data-key');
				if (xlvoWordCloud.pinned === key) {
					xlvoWordCloud.unpin(cloud);
					return;
				}
				$(cloud).find('.xlvo-word-cloud-word.is-pinned').removeClass('is-pinned');
				xlvoWordCloud.pinned = key;
				this.classList.add('is-pinned');
				xlvoWordCloud.showTooltip(cloud, this, true);
			})
			.on('click.xlvoWordCloud', '.xlvo-word-cloud .xlvo-word-cloud-remove', function (event) {
				event.stopPropagation();
				var key = xlvoWordCloud.pinned;
				if (key === null) {
					return;
				}
				xlvoWordCloud.unpin($(this).closest('.xlvo-word-cloud')[0]);
				xlvoPlayer.callPlayer('remove_word', {word: key});
			})
			.on('click.xlvoWordCloud', function (event) {
				if (xlvoWordCloud.pinned !== null && !$(event.target).closest('.xlvo-word-cloud-tooltip').length) {
					$('.xlvo-word-cloud').each(function () {
						xlvoWordCloud.unpin(this);
					});
				}
			})
			.on('keydown.xlvoWordCloud', function (event) {
				if (event.key === 'Escape' && xlvoWordCloud.pinned !== null) {
					$('.xlvo-word-cloud').each(function () {
						xlvoWordCloud.unpin(this);
					});
				}
			});

		var resize_timer = null;
		$(window).off('resize.xlvoWordCloud').on('resize.xlvoWordCloud', function () {
			clearTimeout(resize_timer);
			resize_timer = setTimeout(function () {
				xlvoWordCloud.render();
			}, 300);
		});
	}
};

$(function () {
	xlvoWordCloud.init();
	xlvoWordCloud.render();
});
