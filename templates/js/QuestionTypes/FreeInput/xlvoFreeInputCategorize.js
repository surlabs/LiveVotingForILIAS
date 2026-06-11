/**
 *
 * @type {{addCategory: xlvoFreeInputCategorize.addCategory}}
 */
var xlvoFreeInputCategorize = {

	/**
	 * dragula object
	 */
	drake: null,

	/**
	 *
	 */
	base_url: '',

	/**
	 * avoids simultaneous requests
	 */
	request_pending: false,

	auto_scroll_interval: null,
	auto_scroll_mouse_y: null,
	auto_scroll_threshold: 80,
	auto_scroll_speed: 25,

	/**
	 * activate to see log entries
	 */
	debug: false,

	/**
	 *  init dragula and event listeners
	 */
	init: function(base_url) {
		this.base_url = base_url;
		this._scroll_parent = this.findScrollParent(document.getElementById('xlvo-display-player'));
		this.initDragula();
		this.initButtons();
		this.initialized = true;
		$('div.ilTabsContentOuter').append($('#srag_waiter'));  // necessary for fullscreen mode
		this.log('xlvoFreeInputCategorize initialized');
	},

	/**
	 *
	 */
	initDragula: function () {
		// the new HTML is added first, then the old one is removed - therefore there are two 'div.bars' atm
		this.drake = dragula($.find('div.bars'), {
			moves: function (el) {
				return $(el).is("div");
			},
			accepts: function (el, target, source) {
				return target !== source;
			},
			mirrorContainer: $('div.ilTabsContentOuter')[0]     // necessary for fullscreen mode
		})
			.on('drag', function (el) {
				xlvoFreeInputCategorize.startAutoScroll();
				xlvoFreeInputCategorize.recalculatePlayerHeight();
			}).on('drop', function (el, target, source) {
				xlvoFreeInputCategorize.stopAutoScroll();
				xlvoFreeInputCategorize.changeCategory($(el).find('div.xlvo-vote-free-input')[0].getAttribute('data-vote-id'), $(target).parent().attr('data-category-id'));
				xlvoFreeInputCategorize.recalculatePlayerHeight();
			}).on('dragend', function () {
				xlvoFreeInputCategorize.stopAutoScroll();
			});

	},

	_autoScrollMouseHandler: null,
	_scroll_parent: null,

	findScrollParent: function (el) {
		var p = el ? el.parentElement : null;
		while (p && p !== document.documentElement) {
			var style = window.getComputedStyle(p);
			var oy = style.overflowY;
			if ((oy === 'scroll' || oy === 'auto') && p.scrollHeight > p.clientHeight) {
				return p;
			}
			p = p.parentElement;
		}
		return null;
	},

	startAutoScroll: function () {
		this.stopAutoScroll();
		this._autoScrollMouseHandler = function (event) {
			xlvoFreeInputCategorize.auto_scroll_mouse_y = event.clientY;
		};
		document.addEventListener('mousemove', this._autoScrollMouseHandler, true);
		this.auto_scroll_interval = window.setInterval(function () {
			xlvoFreeInputCategorize.autoScroll();
		}, 16);
	},

	stopAutoScroll: function () {
		if (this._autoScrollMouseHandler) {
			document.removeEventListener('mousemove', this._autoScrollMouseHandler, true);
			this._autoScrollMouseHandler = null;
		}
		if (this.auto_scroll_interval !== null) {
			window.clearInterval(this.auto_scroll_interval);
			this.auto_scroll_interval = null;
		}
	},

	autoScroll: function () {
		if (this.auto_scroll_mouse_y === null) {
			return;
		}

		var scroll_container = $('.xlvo-fullscreen:visible')[0];
		var top = 0;
		var bottom = window.innerHeight;

		if (scroll_container) {
			var rect = scroll_container.getBoundingClientRect();
			top = rect.top;
			bottom = rect.bottom;
		}

		var delta = 0;
		if (this.auto_scroll_mouse_y < top + this.auto_scroll_threshold) {
			delta = -this.auto_scroll_speed;
		} else if (this.auto_scroll_mouse_y > bottom - this.auto_scroll_threshold) {
			delta = this.auto_scroll_speed;
		}

		if (delta !== 0) {
			if (scroll_container) {
				scroll_container.scrollTop += delta;
			} else if (this._scroll_parent) {
				this._scroll_parent.scrollTop += delta;
			} else {
				window.scrollBy(0, delta);
			}
		}
	},

	/**
	 *
	 */
	initButtons: function() {
		$('input.category_input:last').on("keypress", function(e) {
			if (e.which == 13) {	// enter
				xlvoFreeInputCategorize.addCategory();
			}
		});

		$('button.category_button:last').on("click", xlvoFreeInputCategorize.addCategory);

		$('input.answer_input:last').on("keypress", function(e) {
			if (e.which == 13) {	// enter
				xlvoFreeInputCategorize.addAnswer();
			}
		});

		$('button.answer_button:last').on("click", xlvoFreeInputCategorize.addAnswer);
	},

	/**
	 *
	 */
	addCategory: function() {
		if (xlvoFreeInputCategorize.isRequestPending()) {
			this.log('Request Pending');
			return;
		}

		xlvoFreeInputCategorize.startRequest();

		$.post(xlvoPlayer.config.base_url + '&cmd=apiCall', {
			call: 'add_category',
			title: $('#category_input').val()
		}).done(function (data) {
			// append category
			$('div#categories').append(
				'<div id="category_id_' + data.category_id + '" class="col-md-4" data-category-id="' + data.category_id + '">' +
					'<button type="button" class="close" aria-label="Close" onClick="xlvoFreeInputCategorize.removeCategory($(this).parent());">' +
						'<span aria-hidden="true">&times;</span>' +
					'</button>' +
					'<fieldset class="well xlvo-category category_dropzone" data-category-id="' + data.category_id + '">' +
						'<legend>' + $('#category_input').val() + '</legend>' +
						'<br>' +
						'<div id="bars_' + data.category_id + '" class="bars">' +
							'<div class="col-md-4"></div>' +
						'</div>' +
					'</fieldset>' +
				'</div>'
			);

			// add new container to dragula
			xlvoFreeInputCategorize.drake.containers.push($('#bars_' + data.category_id)[0]);

			// flush input
			$('#category_input').val('');

			// recalculate height of player
			xlvoFreeInputCategorize.recalculatePlayerHeight();
		}).always(function(){
			xlvoFreeInputCategorize.endRequest();
		});

	},

	/**
	 *
	 * @param category
	 */
	removeCategory: function(category) {
		if (xlvoFreeInputCategorize.isRequestPending()) {
			this.log('Request Pending');
			return;
		}

		xlvoFreeInputCategorize.startRequest();

		category_id = $(category).attr('data-category-id');
		$.post(xlvoPlayer.config.base_url + '&cmd=apiCall', {
			call: 'remove_category',
			category_id: category_id
		}).done(function (data) {
			// $(category).find('div.col-md-4').each(function(key, element) {
			// 	console.log(element);
			// 	$('div.bars:last')[0].append(element);
			// });
			category.remove();
			// recalculate height of player
			xlvoFreeInputCategorize.recalculatePlayerHeight();
		}).always(function(){
			xlvoFreeInputCategorize.endRequest();
		});

	},

	/**
	 *
	 */
	addAnswer: function() {
		if (xlvoFreeInputCategorize.isRequestPending()) {
			this.log('Request Pending');
			return;
		}

		xlvoFreeInputCategorize.startRequest();
		$.post(xlvoPlayer.config.base_url + '&cmd=apiCall', {
			call: 'add_vote',
			input: $('#answer_input').val()
		}).done(function (data) {
			// append answer
			$('div#bars').append(
				'<div class="col-md-4">' +
					'<div id="vote_id_' + data.vote_id + '" class="xlvo-vote-free-input" data-vote-id="' + data.vote_id + '">' +
						'<button type="button" class="close" aria-label="Close" onClick="xlvoFreeInputCategorize.removeAnswer($(this).parent().parent())">' +
							'<span aria-hidden="true">&times;</span>' +
						'</button>' +
						'<div class="well well-sm">' +
							'<span>' + $('#answer_input').val() + '</span>' +
						'</div>' +
					'</div>' +
				'</div>'
			);

			// flush input
			$('#answer_input').val('');

			// recalculate height of player
			xlvoFreeInputCategorize.recalculatePlayerHeight();
		}).always(function(){
			xlvoFreeInputCategorize.endRequest();
		});

	},

	/**
	 * @param answer
	 */
	removeAnswer: function(answer) {
		if (xlvoFreeInputCategorize.isRequestPending()) {
			this.log('Request Pending');
			return;
		}

		xlvoFreeInputCategorize.startRequest();

		vote_id = $(answer).find('div.xlvo-vote-free-input')[0].getAttribute('data-vote-id');
		$.post(xlvoPlayer.config.base_url + '&cmd=apiCall', {
			call: 'remove_vote',
			vote_id: vote_id
		}).done(function (data) {
			$(answer).remove();

			// recalculate height of player
			xlvoFreeInputCategorize.recalculatePlayerHeight();
		}).always(function(){
			xlvoFreeInputCategorize.endRequest();
		});
	},

	/**
	 * @param vote_id int
	 * @param category_id int
 	 */
	changeCategory: function(vote_id, category_id) {
		if (xlvoFreeInputCategorize.isRequestPending()) {
			this.log('Request Pending');
			return;
		}

		xlvoFreeInputCategorize.startRequest();

		$.post(xlvoPlayer.config.base_url + '&cmd=apiCall', {
			call: 'change_category',
			vote_id: vote_id,
			category_id: (category_id === 'undefined') ? 0 : category_id
		}).done(function (data) {

		}).always(function(){
			xlvoFreeInputCategorize.endRequest();
		});
	},

	/**
	 * called in addCategory and addAnswer
	 */
	recalculatePlayerHeight: function () {
		var player_children = $('#xlvo-display-player').children();
		$('#xlvo-display-player').css('height', player_children.css('height'));
	},

	startRequest: function () {
		il.waiter.show();
		xlvoFreeInputCategorize.request_pending = true;
	},
	endRequest: function () {
		il.waiter.hide();
		xlvoFreeInputCategorize.request_pending = false;
	},
	isRequestPending: function () {
		return xlvoFreeInputCategorize.request_pending;
	},

	/**
	 * @param data
	 */
	log: function (data) {
		if (this.debug) {
			console.log(data);
		}
	},
	debug: function () {
		this.debug = true;
	},
	stop: function () {
		this.debug = false;
	}
};
