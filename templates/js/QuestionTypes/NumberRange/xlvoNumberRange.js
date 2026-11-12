/**
 * Class xlvoNumberRange
 * @type {{}}
 */
var xlvoNumberRange = {
    init: function (json) {
        var config = json;
        var replacer = new RegExp("amp;", "g");
        config.base_url = config.base_url.replace(replacer, "");
        this.config = config;
        this.ready = true;
        this.percentageSign = "";
    },
    config: {},
    base_url: "",
    run: function () {
        var slider = $("#slider").bootstrapSlider();
        var step = parseInt(slider.attr("data-slider-step"), 10);
        var buttonMoveSliderLeft = document.querySelector("#btn-slider-left");
        var buttonMoveSliderRight = document.querySelector("#btn-slider-right");

        this.percentageSign = $("#percentage")[0].value === "1" ? " %" : "";

        var numberDisplay = $("#number-display");
        var oldText = numberDisplay.text();

        numberDisplay.text(oldText.concat(this.percentageSign));
        slider = slider.bootstrapSlider();

        slider.change(
            function (changedValues) {
                $("#number-display").text(String(changedValues.value.newValue).concat(this.percentageSign));
                buttonMoveSliderLeft.disabled = changedValues.value.newValue <= slider.bootstrapSlider("getAttribute", "min");
                buttonMoveSliderRight.disabled = changedValues.value.newValue >= slider.bootstrapSlider("getAttribute", "max");
            }.bind(this)
        );

        var moveLeft = function () {
            var sliderValue = slider.bootstrapSlider("getValue");
            if (slider.bootstrapSlider("getAttribute", "min") < sliderValue) {
                slider.bootstrapSlider("setValue", sliderValue - step, false, true);
            }
        }.bind(slider);

        var moveRight = function () {
            var sliderValue = slider.bootstrapSlider("getValue");
            if (slider.bootstrapSlider("getAttribute", "max") > sliderValue) {
                slider.bootstrapSlider("setValue", sliderValue + step, false, true);
            }
        }.bind(slider);

        buttonMoveSliderLeft.onclick = moveLeft;
        buttonMoveSliderRight.onclick = moveRight;

        this.addHoldAction(buttonMoveSliderLeft, moveLeft);
        this.addHoldAction(buttonMoveSliderRight, moveRight);

        // After submitting, the value stays visible but cannot be changed until "Change Answer".
        var form = slider.closest("form");
        var setLocked = function (locked) {
            slider.bootstrapSlider(locked ? "disable" : "enable");
            var value = slider.bootstrapSlider("getValue");
            buttonMoveSliderLeft.disabled = locked || value <= slider.bootstrapSlider("getAttribute", "min");
            buttonMoveSliderRight.disabled = locked || value >= slider.bootstrapSlider("getAttribute", "max");
        };
        setLocked(form.hasClass("is-locked"));
        form.off("xlvo:unlocked.numberRange").on("xlvo:unlocked.numberRange", function () {
            setLocked(false);
        });
    },
    addHoldAction: function (button, action) {
        var holdTimeout = null;
        var holdInterval = null;

        button.addEventListener("pointerdown", function () {
            holdTimeout = window.setTimeout(function () {
                holdInterval = window.setInterval(action, 90);
            }, 350);
        });
        ["pointerup", "pointercancel", "pointerleave"].forEach(function (eventName) {
            button.addEventListener(eventName, function () {
                window.clearTimeout(holdTimeout);
                window.clearInterval(holdInterval);
            });
        });
    },
    /**
     * @param button_id
     * @param button_data
     */
    handleButtonPress: function (button_id, button_data) {
    }
};
