/**
 * Class xlvoSingleVote
 * @type {{}}
 */
var xlvoSingleVote = {
	config: {},
	base_url: '',
	running: false,

	init: function (json) {
		var config = json;
		var replacer = new RegExp('amp;', 'g');
		config.base_url = config.base_url.replace(replacer, '');
		this.config = config;
		this.ready = true;
	},

	run: function () {
		if (this.running) {
			return;
		}

		this.running = true;

		let lastInteractionWasMouse = false;

		document.addEventListener('mousedown', () => {
			lastInteractionWasMouse = true;
		});

		document.addEventListener('keydown', () => {
			lastInteractionWasMouse = false;
		});

		document.querySelectorAll('.vote-checkbox').forEach((checkbox) => {
			checkbox.addEventListener('focus', () => {
				if (lastInteractionWasMouse) {
					checkbox.blur();
				}
			});
		});

		$(document).on('change', '.vote-checkbox', function (event) {
			xlvoSingleVote.handleCheckboxChange(event.currentTarget);
		});
	},

	handleCheckboxChange: function (thisElement) {
		const selector = $(thisElement);

		this.updateButtonState(selector.is(":checked"), selector.attr('id').replace('option-', ''));

		$.get(selector.attr("link"), {
			'isRequest': true
		});

		if (selector.attr("type") === 'radio') {
			$(`input[type="radio"][name="${selector.attr('name')}"]`).not(selector).each((index, element) => {
				this.updateButtonState(false, $(element).attr('id').replace('option-', ''));
			});
		}
	},

	updateButtonState: function (checked, letter) {
		const selector = $(`[for="option-${letter}"] .btn`);

		if (checked) {
			selector.removeClass('btn-default').addClass('btn-primary').find('span').text(xlvoVoter.config.lng.qtype_1_unvote);
		} else {
			selector.removeClass('btn-primary').addClass('btn-default').find('span').text(xlvoVoter.config.lng.qtype_1_vote);
		}
	}
};
