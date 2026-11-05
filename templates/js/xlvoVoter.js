/**
 * Class xlvoVoter
 * @type {{}}
 */
var xlvoVoter = {
	notificationCounter: 0,
	notified_voting_id: null,
	init: function (json) {
		var config = json;
		var replacer = new RegExp('amp;', 'g');
		config.base_url = config.base_url.replace(replacer, '');
		this.config = config;
		this.ready = true;
		if (xlvoVoter.config.use_mathjax && typeof MathJax !== 'undefined' && MathJax.version && (MathJax.version.charAt(0) !== '3')) {
			MathJax.Hub.Config({
				"HTML-CSS": {scale: 80}
			});
		}
	},
	config: {
		base_url: '', // Base-URL for API-Calls
		cmd_voting_data: '', // loadVotingData
		lng: {
			player_seconds: 's',
			new_voting: 'New Voting',
			new_voting_message: 'A new voting has started.',
			seconds_left: 'seconds left',
			qtype_1_unvote: 'Unvote',
			qtype_1_vote: 'Vote',
		},
		debug: false,
		delay: 1000
	},
	player: {
		frozen: true,
		active_voting_id: 0,
		status: -1,
		countdown: 0,
		has_countdown: false,
		countdown_classname: ''
	},
	delay: 1000,
	counter: 0,
	forced_update: 300,
	timeout: null,
	data: null,
	run: function () {
		this.loadVotingData();
		this.initElements();
		this.initDynamicForms();
	},
	initElements: function () {
		this.countdown_element = $('#xlvo_countdown');
		this.player_element = $('#xlvo_voter_player');

		$('.xlvo-nickname').attr('href', xlvoVoter.config.base_url + '&cmd=requestNickname');
	},
	/**
	 * Choice forms need at least one selected option. The message stays visible until the voter picks one.
	 */
	validateNewUIForm: function (form) {
		var feedback = form.querySelector('.xlvo-answer-feedback');
		var has_selection = true;
		if (form.getAttribute('data-xlvo-require-selection') === '1') {
			has_selection = form.querySelector('input[name="option_ids[]"]:checked') !== null;
		} else if (form.getAttribute('data-xlvo-require-text') === '1') {
			has_selection = $('.xlvo-free-text-field', form).filter(function () {
				return this.value.trim() !== '';
			}).length > 0;
		} else {
			return true;
		}
		if (feedback) {
			feedback.hidden = has_selection;
		}
		return has_selection;
	},

	/**
	 * Short horizontal shake of the answer area when the voter submits nothing, as in the mockups.
	 */
	shakeNewUIForm: function (form) {
		var target = form.querySelector('.xlvo-free-text-inputs, .xlvo-option-cards') || form;
		target.classList.remove('xlvo-shake');
		void target.offsetWidth;
		target.classList.add('xlvo-shake');
	},

	/**
	 * "Change Answer" only reopens the form in the browser; the stored answer is replaced on the next submit.
	 */
	unlockNewUIForm: function (form) {
		form.classList.remove('is-locked');
		$('input, textarea, select, button', form).not('[type="hidden"]').prop('disabled', false);
		$('.is-submitted', form).removeClass('is-submitted');
		var submit_button = form.querySelector('.xlvo-submit-btn');
		if (submit_button && form.getAttribute('data-xlvo-submit-text')) {
			submit_button.textContent = form.getAttribute('data-xlvo-submit-text');
		}
		var change_button = form.querySelector('.xlvo-change-answer-btn');
		if (change_button) {
			change_button.disabled = true;
		}
		$(form).trigger('xlvo:unlocked');
		var first_input = form.querySelector('input:not([type="hidden"]), textarea');
		if (first_input) {
			first_input.focus();
		}
	},

	/**
	 * Free text (multi input): adds an empty answer field after the given row, or at the end.
	 */
	addFreeTextRow: function (form, after_row) {
		var rows = $('.xlvo-free-text-row', form);
		var row = rows.first().clone().removeClass('has-value');
		var index = parseInt(form.getAttribute('data-xlvo-next-index'), 10) || rows.length;
		form.setAttribute('data-xlvo-next-index', String(index + 1));
		row.find('.xlvo-free-text-field').val('').prop('disabled', false)
			.attr('id', 'xlvo_free_text_' + index)
			.attr('name', 'vote_multi_line_input[' + index + '][free_input]');
		row.find('label').attr('for', 'xlvo_free_text_' + index);
		row.find('input[type="hidden"]').val('').attr('name', 'vote_multi_line_input[' + index + '][vote_id]');
		if (after_row && after_row.length) {
			row.insertAfter(after_row);
		} else {
			row.appendTo($('.xlvo-free-text-inputs', form));
		}
		row.find('.xlvo-free-text-field').trigger('focus');
	},

	/**
	 * Free text areas grow with their content, so a long answer is never cut behind a scrollbar.
	 */
	fitTextareas: function (scope) {
		$('.xlvo-new-ui textarea.xlvo-free-text-field', scope || document).each(function () {
			this.style.height = 'auto';
			this.style.height = (this.scrollHeight + 2) + 'px';
		});
	},

	initDynamicForms: function () {
		var free_text = '#xlvo_voter_player .xlvo-new-ui .xlvo-free-text-form';
		$(document).off('keydown.xlvoNewUI', free_text + ' input.xlvo-free-text-field')
			.on('keydown.xlvoNewUI', free_text + ' input.xlvo-free-text-field', function (event) {
				var form = $(this).closest('form')[0];
				if (event.key !== 'Enter' || form.getAttribute('data-xlvo-multi') !== '1') {
					return;
				}
				event.preventDefault();
				if (!form.classList.contains('is-locked')) {
					xlvoVoter.addFreeTextRow(form, $(this).closest('.xlvo-free-text-row'));
				}
			});
		$(document).off('click.xlvoNewUI', free_text + ' .xlvo-free-text-add')
			.on('click.xlvoNewUI', free_text + ' .xlvo-free-text-add', function () {
				var form = $(this).closest('form')[0];
				if (!form.classList.contains('is-locked')) {
					xlvoVoter.addFreeTextRow(form, null);
				}
			});
		$(document).off('click.xlvoNewUI', free_text + ' .xlvo-free-text-remove')
			.on('click.xlvoNewUI', free_text + ' .xlvo-free-text-remove', function () {
				var form = $(this).closest('form')[0];
				if (form.classList.contains('is-locked')) {
					return;
				}
				var row = $(this).closest('.xlvo-free-text-row');
				if ($('.xlvo-free-text-row', form).length > 1) {
					row.remove();
				} else {
					row.find('.xlvo-free-text-field').val('').trigger('focus');
				}
			});
		$(document).off('input.xlvoNewUI', free_text + ' .xlvo-free-text-field')
			.on('input.xlvoNewUI', free_text + ' .xlvo-free-text-field', function () {
				if (this.tagName === 'TEXTAREA') {
					xlvoVoter.fitTextareas($(this).parent());
				}
				$(this).closest('.xlvo-free-text-row').toggleClass('has-value', this.value.trim() !== '');
				var form = $(this).closest('form')[0];
				var feedback = form.querySelector('.xlvo-answer-feedback');
				if (feedback && !feedback.hidden) {
					xlvoVoter.validateNewUIForm(form);
				}
			});
		$(document).off('click.xlvoNewUI', '#xlvo_voter_player .xlvo-new-ui .xlvo-change-answer-btn')
			.on('click.xlvoNewUI', '#xlvo_voter_player .xlvo-new-ui .xlvo-change-answer-btn', function () {
				xlvoVoter.unlockNewUIForm($(this).closest('form')[0]);
			});
		$(document).off('change.xlvoNewUI', '#xlvo_voter_player .xlvo-new-ui .xlvo-answer-form input[name="option_ids[]"]')
			.on('change.xlvoNewUI', '#xlvo_voter_player .xlvo-new-ui .xlvo-answer-form input[name="option_ids[]"]', function () {
				var form = $(this).closest('form')[0];
				var feedback = form.querySelector('.xlvo-answer-feedback');
				if (feedback && !feedback.hidden) {
					xlvoVoter.validateNewUIForm(form);
				}
			});

		var selector = '#xlvo_voter_player .xlvo-new-ui .xlvo-free-input form, '
			+ '#xlvo_voter_player .xlvo-new-ui .xlvo-answer-form, '
			+ '#xlvo_voter_player .xlvo-new-ui .xlvo-number-range-form, '
			+ '#xlvo_voter_player .xlvo-new-ui .xlvo-priorities form, '
			+ '#xlvo_voter_player .xlvo-new-ui .xlvo-correct-order form';

		$(document).off('submit.xlvoNewUI', selector).on('submit.xlvoNewUI', selector, function (event) {
			event.preventDefault();

			var form = this;
			if (form.classList.contains('is-locked')) {
				return;
			}
			if (!xlvoVoter.validateNewUIForm(form)) {
				xlvoVoter.shakeNewUIForm(form);
				return;
			}
			var submitter = event.originalEvent && event.originalEvent.submitter;
			var formData = new FormData(form);
			var buttons = $('input[type="submit"], button[type="submit"]', form);

			if (submitter && submitter.name) {
				formData.append(submitter.name, submitter.value);
			} else {
				formData.append('cmd[submit]', 'submit');
			}
			formData.append('xlvo_new_ui_async', '1');

			buttons.prop('disabled', true);
			form.setAttribute('aria-busy', 'true');
			var submit_button = form.querySelector('.xlvo-submit-btn');
			var submit_label = submit_button ? submit_button.textContent : '';
			if (submit_button && xlvoVoter.config.lng && xlvoVoter.config.lng.voter_submitting) {
				submit_button.textContent = xlvoVoter.config.lng.voter_submitting;
			}
			var restore = function () {
				buttons.prop('disabled', false);
				form.removeAttribute('aria-busy');
				if (submit_button) {
					submit_button.textContent = submit_label;
				}
			};

			$.ajax({
				url: form.action,
				method: 'POST',
				data: formData,
				contentType: false,
				processData: false
			}).done(function () {
				xlvoVoter.replaceHTML().fail(restore);
			}).fail(restore);
		});
	},
	loadVotingData: function () {
		$.get(xlvoVoter.config.base_url, {cmd: 'getVotingData'})
			.done(function (data) {
				xlvoVoter.log(data);

				if (data.redirect) {
					window.location.href = data.redirect;
					return;
				}

				//kill timer if running
				if (xlvoVoter.interval) {
					clearInterval(xlvoVoter.interval);
					xlvoVoter.interval = null;
				}

				if (data.nickname) {
					$('.xlvo-nickname').text(data.nickname);
				}

				if (data.online_voters) {
					$('#xlvo-attendees').html(data.online_voters);
				}

				var voting_has_changed = (xlvoVoter.player.active_voting_id !== data.active_voting_id), // Voting has changed

					status_has_changed = (xlvoVoter.player.status !== data.status), // Status of player has changed
					forced_update = (xlvoVoter.counter > xlvoVoter.forced_update), // forced update
					frozen_changed = (xlvoVoter.player.frozen !== data.frozen), // frozen status has changed
					show_results_changed = (xlvoVoter.player.show_results !== data.show_results), // Show Results has changed
					show_correct_order_changed = (xlvoVoter.player.show_correct_order !== data.show_correct_order); // Show Correct Order has changed


				// Only announce a voting once it is actually open: while it is frozen the voter
				// is shown the "not yet active" screen, and both messages together contradict each other.
				if (!data.frozen && xlvoVoter.notified_voting_id !== data.active_voting_id) {
					if (xlvoVoter.notified_voting_id !== null) {
						xlvoVoter.showNotification(xlvoVoter.config.lng.new_voting, xlvoVoter.config.lng.new_voting_message);
					}
					xlvoVoter.notified_voting_id = data.active_voting_id;
				}

				if (status_has_changed) {
					if (data.status === 2 && data.is_challenge) {
						$('.xlvo-nickname').removeClass('disabled');
					} else {
						$('.xlvo-nickname').addClass('disabled');
					}
				}

				xlvoVoter.player = data;
				if (status_has_changed || voting_has_changed || forced_update || frozen_changed || show_correct_order_changed) {
					xlvoVoter.log("Replace HTML & Handle Countdown");

					xlvoVoter.log("status_has_changed:" + status_has_changed);
					xlvoVoter.log("voting_has_changed:" + voting_has_changed);
					xlvoVoter.log("forced_update:" + forced_update);
					xlvoVoter.log("frozen_changed:" + frozen_changed);
					xlvoVoter.log("show_results_changed:" + show_results_changed);
					xlvoVoter.log("show_correct_order_changed:" + show_correct_order_changed);

					xlvoVoter.replaceHTML(xlvoVoter.handleCountdown);
				} else {
					xlvoVoter.log("handleCountdown");
					xlvoVoter.handleCountdown();
				}
				xlvoVoter.log("Set TimeOut");
				xlvoVoter.timeout = setTimeout(xlvoVoter.loadVotingData, xlvoVoter.config.delay);
				xlvoVoter.counter++;
			}).fail(function () {
			xlvoVoter.timeout = setTimeout(xlvoVoter.loadVotingData, xlvoVoter.config.delay);

		});
	},
	replaceHTML: function (success) {
		xlvoVoter.log('replace');
		success = success ? success : function () {
		};
		return $.get(xlvoVoter.config.base_url, {cmd: 'getHTML'}).done(function (data) {
			if (xlvoVoter.data !== data) { // Only change html if changed (Try prevent blinking images) (Not work because countdown text and/or token links)

				xlvoVoter.log(data);

				xlvoVoter.player_element.replaceWith('<div id="xlvo_voter_player">' + data + '</div>');
				if (xlvoVoter.config.use_mathjax) {
					il.Util.renderMathJax([document.getElementById('xlvo_voter_player')]);
				}
				xlvoVoter.counter = 0;
				xlvoVoter.player_element = $('#xlvo_voter_player');
				xlvoVoter.fitTextareas(xlvoVoter.player_element);
				xlvoVoter.countdown_element = $('#xlvo_countdown');
			}
			success();
		});
	},
	handleCountdown: function () {
		if (xlvoVoter.player.has_countdown) {
			xlvoVoter.log('has countdown: ' + (xlvoVoter.player.has_countdown ? 'yes, ' + xlvoVoter.player.countdown : 'no'));
			xlvoVoter.countdown_element.removeClass();
			xlvoVoter.countdown_element.text(xlvoVoter.player.countdown.toString() + ' ' + xlvoVoter.config.lng.player_seconds);
			xlvoVoter.countdown_element.show();
			xlvoVoter.countdown_element.addClass('label label-cd-' + xlvoVoter.player.countdown_classname);
			xlvoVoter.interval = setInterval(xlvoVoter.countDown, 1000);

		} else {
			xlvoVoter.countdown_element.removeClass();
			xlvoVoter.countdown_element.hide();
		}
	},
	countDown: function () {
		if (xlvoVoter.player.has_countdown) {
			xlvoVoter.player.countdown--;
			if (xlvoVoter.player.countdown > 0) {
				xlvoVoter.countdown_element.text((xlvoVoter.player.countdown).toString() + ' ' + xlvoVoter.config.lng.player_seconds);

				if (xlvoVoter.player.countdown === 10 || xlvoVoter.player.countdown === 5) {
					$('[aria-live="assertive"]').text(xlvoVoter.player.countdown.toString() + ' ' + xlvoVoter.config.lng.seconds_left);
				}
			}
		}
	},

	/**
	 * @param data
	 */
	log: function (data) {
		if (xlvoVoter.config.debug) {
			console.log(data);
		}
	},
	debug: function () {
		this.config.debug = true;
	},

	stop: function () {
		this.config.debug = false;
	},

	playNotificationSound: function() {
		const audio = new Audio("Customizing/global/plugins/Services/Repository/RepositoryObject/LiveVoting/templates/media/notification.ogg");
		audio.volume = 0.3;
		audio.play();
	},

	showNotification: function(title, message) {
		xlvoVoter.playNotificationSound();

		const container = document.getElementById('notificationContainer');
		const notificationId = `notification-${++this.notificationCounter}`;

		const notification = document.createElement('div');
		notification.className = `notification`;
		notification.id = notificationId;

		notification.innerHTML = `
					<div class="notification-content">
						<div class="notification-title">${title}</div>
						<div class="notification-message">${message}</div>
					</div>
					<button class="notification-close" onclick="xlvoVoter.closeNotification('${notificationId}')">×</button>
				`;

		container.appendChild(notification);

		setTimeout(() => {
			if (document.getElementById(notificationId)) {
				xlvoVoter.closeNotification(notificationId);
			}
		}, 4500);
	},

	closeNotification: function(notificationId) {
		const notification = document.getElementById(notificationId);
		if (notification) {
			notification.classList.add('fade-out');
			setTimeout(() => {
				if (notification.parentNode) {
					notification.parentNode.removeChild(notification);
				}
			}, 400);
		}
	}
};
