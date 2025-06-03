const xlvoMultipleInputs = {
    ...structuredClone(xlvoBase),

    inputs: [],
    inputSelector: null,
    hiddenId: "",
    parent: null,

    init: function (id) {
        this.inputSelector = "#" + id;
        this.hiddenId = this.inputSelector;
        this.parent = $(this.inputSelector).parent();
        this._parseInitialData();
        this.parent.find(".multiple-input").remove();

        if (this.inputs.length > 0) {
            this.inputs.forEach((data, i) => {
                const optionId = parseInt(data.id || 0);
                const textValue = data.text || "";
                const newInput = this.addInput(i + 1, optionId);
                this.parent.append(newInput);
                this.parent.find(".multiple-input").last().find(".option-input").val(textValue);
            });
        } else {
            this.parent.append(this.addInput(1, 0));
        }

        this.parent.off('keyup.multipleInputs', ".multiple-input .option-input");
        this.parent.on('keyup.multipleInputs', ".multiple-input .option-input", () => this.update());
    },

    addInput: function (index, option_id) {
        const originalInput = $(this.inputSelector);
        const baseId = originalInput.attr('id') || 'xlvo_mi';
        const newId = baseId + '_' + index;

        const newInput = originalInput.clone().attr({
            id: newId,
            name: baseId + '_' + index,
        }).val('').addClass("option-input form-control").removeAttr('required').css('display', '');

        if (option_id) newInput.attr('data-option-id', option_id);

        const wrapper = $('<div class="multiple-input d-flex gap-1 mb-2 align-items-center"></div>');
        wrapper.append($('<div class="flex-col w-full"></div>').append(newInput));
        const actions = $(`<div class="action-buttons shrink-0 d-flex mt-2">
            <button type="button" class="btn btn-link p-1" onclick="xlvoMultipleInputs.manage('add', $(this).closest('.multiple-input'))"><span class="glyphicon glyphicon-plus"></span></button>
            <button type="button" class="btn btn-link p-1" onclick="xlvoMultipleInputs.manage('remove', $(this).closest('.multiple-input'))"><span class="glyphicon glyphicon-minus"></span></button>
            <button type="button" class="btn btn-link p-1" onclick="xlvoMultipleInputs.manage('down', $(this).closest('.multiple-input'))"><span class="glyphicon glyphicon-chevron-down"></span></button>
            <button type="button" class="btn btn-link p-1" onclick="xlvoMultipleInputs.manage('up', $(this).closest('.multiple-input'))"><span class="glyphicon glyphicon-chevron-up"></span></button>
        </div>`);

        return wrapper.append(actions);
    },

    manage: function (action, currentElement) {
        const parentContainer = currentElement.parent();
        switch (action) {
            case 'add':
                const newIndexAdd = parentContainer.find(".multiple-input").length + 1;
                currentElement.after(this.addInput(newIndexAdd, 0));
                break;
            case 'remove':
                if (parentContainer.find(".multiple-input").length > 1) {
                    currentElement.remove();
                } else {
                    currentElement.find(".option-input").val('');
                }
                break;
            case 'up':
                const prev = currentElement.prev(".multiple-input");
                if (prev.length) prev.before(currentElement);
                break;
            case 'down':
                const next = currentElement.next(".multiple-input");
                if (next.length) next.after(currentElement);
                break;
        }
        this.update();
    },

    update: function () {
        this.inputs = [];
        this.parent.find(".multiple-input .option-input").each((_, element) => {
            const id = parseInt($(element).attr("data-option-id") || 0);
            const text = $(element).val().trim();
            if (text !== "") {
                this.inputs.push({ id, text });
            }
        });
        this._updateHiddenInput();
        return this.inputs;
    }
};
