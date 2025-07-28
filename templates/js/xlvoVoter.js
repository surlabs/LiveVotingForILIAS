/**
 * Class xlvoVoter
 * @type {{}}
 */
var xlvoVoter = {
	notificationCounter: 0,
	init: function (json) {
		var config = json;
		var replacer = new RegExp('amp;', 'g');
		config.base_url = config.base_url.replace(replacer, '');
		this.config = config;
		this.ready = true;
		if (xlvoVoter.config.use_mathjax && !!MathJax && MathJax.version && (MathJax.version.charAt(0) !== '3')) {
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
	},
	initElements: function () {
		this.countdown_element = $('#xlvo_countdown');
		this.player_element = $('#xlvo_voter_player');

		$('.xlvo-nickname').attr('href', xlvoVoter.config.base_url + '&cmd=requestNickname');
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
					$('.xlvo-nickname').html(data.nickname);
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


				if (xlvoVoter.player.active_voting_id !== data.active_voting_id && xlvoVoter.player.active_voting_id !== 0) {
					xlvoVoter.showNotification(xlvoVoter.config.lng.new_voting, xlvoVoter.config.lng.new_voting_message);
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
		$.get(xlvoVoter.config.base_url, {cmd: 'getHTML'}).done(function (data) {
			if (xlvoVoter.data !== data) { // Only change html if changed (Try prevent blinking images) (Not work because countdown text and/or token links)

				xlvoVoter.log(data);

				xlvoVoter.player_element.replaceWith('<div id="xlvo_voter_player">' + data + '</div>');
				if (xlvoVoter.config.use_mathjax && !!MathJax) {
					if ((MathJax.version.charAt(0) === '3')) {
						MathJax.typeset('xlvo_voter_player');
					} else {
						MathJax.Hub.Queue(
							["Typeset", MathJax.Hub, 'xlvo_voter_player']
						);
					}
				}
				xlvoVoter.counter = 0;
				xlvoVoter.player_element = $('#xlvo_voter_player');
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