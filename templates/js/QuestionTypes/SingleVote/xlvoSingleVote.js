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

		$('.vote-checkbox').each(function() {
			$(this).data('previousState', this.checked);
		});

		$(document).on('click', '.vote-label', function (event) {
			event.preventDefault();
			const checkboxId = $(this).attr('for');
			const $checkbox = $(`#${checkboxId}`);

			if ($checkbox.attr('type') === 'radio') {
				xlvoSingleVote.handleRadioClick($checkbox[0]);
			} else if ($checkbox.attr('type') === 'checkbox') {
				xlvoSingleVote.handleCheckboxClick($checkbox[0]);
			}
		});

		$(document).on('click', '.vote-checkbox', function (event) {
			if ($(this).attr('type') === 'radio') {
				event.preventDefault();
				xlvoSingleVote.handleRadioClick(this);
			}
		});
	},

	handleRadioClick: function(radioElement) {
		const $radio = $(radioElement);
		const wasChecked = $radio.data('previousState');
		const radioName = $radio.attr('name');

		if (wasChecked) {
			radioElement.checked = false;
			$radio.data('previousState', false);
			this.updateAllRadioButtonsUI(radioName);
			this.sendVote($radio);
		} else {
			$(`input[type="radio"][name="${radioName}"]`).each((index, element) => {
				element.checked = false;
				$(element).data('previousState', false);
			});

			radioElement.checked = true;
			$radio.data('previousState', true);

			this.updateAllRadioButtonsUI(radioName);

			this.sendVote($radio);
		}
	},

	updateAllRadioButtonsUI: function(radioName) {
		$(`input[type="radio"][name="${radioName}"]`).each((index, element) => {
			const $element = $(element);
			const optionId = $element.attr('id').replace('option-', '');
			this.updateButtonState(element.checked, optionId);
		});
	},

	sendVote: function($checkbox) {
		$.get($checkbox.attr("link"), {
			'isRequest': true
		});
	},

	handleCheckboxChange: function (thisElement, isChecked) {
		const selector = $(thisElement);
		const optionId = selector.attr('id').replace('option-', '');

		this.updateButtonState(isChecked, optionId);

		$.get(selector.attr("link"), {
			'isRequest': true
		});
	},

	handleCheckboxClick: function(checkboxElement) {
		const $checkbox = $(checkboxElement);
		const currentState = checkboxElement.checked;

		// Toggle del checkbox
		checkboxElement.checked = !currentState;
		$checkbox.data('previousState', checkboxElement.checked);

		// Actualizar UI
		const optionId = $checkbox.attr('id').replace('option-', '');
		this.updateButtonState(checkboxElement.checked, optionId);

		// Enviar petición al servidor
		this.sendVote($checkbox);
	},

	updateButtonState: function (checked, letter) {
		const selector = $(`[for="option-${letter}"] .btn`);

		if (checked) {
			selector.removeClass('btn-default').addClass('btn-primary')
				.find('span').text(xlvoVoter.config.lng.qtype_1_unvote);
		} else {
			selector.removeClass('btn-primary').addClass('btn-default')
				.find('span').text(xlvoVoter.config.lng.qtype_1_vote);
		}
	}
};