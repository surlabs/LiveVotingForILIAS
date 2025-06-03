const xlvoForms = {
    inputs: [],
    inputSelector: null,
    hiddenId: "",
    parent: null,

    _parseInitialData: function () {
        this.inputs = [];
        let raw = $(this.inputSelector).val();
        if (raw && raw.length > 0) {
            try {
                const fixed = raw.replace(/\\'/g, '"');
                this.inputs = JSON.parse(fixed);
            } catch (e) {
                console.warn("Error parsing input JSON:", e, "Raw data:", raw);
                this.inputs = [];
            }
        }
        if (!Array.isArray(this.inputs)) {
            console.warn("Parsed data is not an array. Resetting. Data:", this.inputs);
            this.inputs = [];
        }
    },

    _updateHiddenInput: function () {
        let jsonString = "[]";
        if (Array.isArray(this.inputs) && this.inputs.length > 0) {
            jsonString = JSON.stringify(this.inputs);
        }
        $(this.hiddenId).val(jsonString);
    },

    initMultipleInputs: function (id) {
        this.inputSelector = "#" + id;
        this.hiddenId = this.inputSelector;
        this.parent = $(this.inputSelector).parent();

        $(this.inputSelector).addClass('xlvo-template');
        this._parseInitialData();

        this.parent.find(".multiple-input").remove();

        if (this.inputs.length > 0) {
            this.inputs.forEach((data, i) => {
                const optionId = (typeof data === 'object' && data !== null && data.hasOwnProperty('id')) ? parseInt(data.id) : 0;
                const textValue = (typeof data === 'object' && data !== null && data.hasOwnProperty('text')) ? data.text : (typeof data === 'string' ? data : '');

                const newInput = this.addMultipleInput(i + 1, optionId);
                this.parent.append(newInput);
                this.parent.find(".multiple-input").last().find(".option-input").val(textValue);
            });
        } else {
            this.parent.append(this.addMultipleInput(1, 0));
        }

        this.parent.off('keyup.multipleInputs', ".multiple-input .option-input");
        this.parent.on('keyup.multipleInputs', ".multiple-input .option-input", () => this.updateMultipleInputs());
    },

    addMultipleInput: function (index, option_id) {
        const originalInput = this.parent.find('.xlvo-template').first();
        const baseId = originalInput.attr('id') || 'xlvo_mi';
        const newId = baseId + '_' + index;

        const newInput = originalInput.clone();
        newInput.attr('id', newId);
        newInput.attr('name', (originalInput.attr('name') || baseId) + '_' + index);
        newInput.val('');
        newInput.addClass("option-input form-control");
        newInput.removeAttr('required');
        newInput.css('display', '');

        if (option_id && option_id !== 0) {
            newInput.attr('data-option-id', option_id);
        }

        const wrapper = $('<div class="multiple-input d-flex gap-1 mb-2 align-items-center"></div>');
        wrapper.append($('<div class="flex-col w-full"></div>').append(newInput));
        const actions = $(`
        <div class="action-buttons shrink-0 d-flex mt-2">
            <button type="button" class="btn btn-link p-1" onclick="xlvoForms.manageMultipleInputs('add', $(this).closest('.multiple-input'))" title="Add"><span class="sr-only">Add</span><span class="glyphicon glyphicon-plus"></span></button>
            <button type="button" class="btn btn-link p-1" onclick="xlvoForms.manageMultipleInputs('remove', $(this).closest('.multiple-input'))" title="Remove"><span class="sr-only">Remove</span><span class="glyphicon glyphicon-minus"></span></button>
            <button type="button" class="btn btn-link p-1" onclick="xlvoForms.manageMultipleInputs('down', $(this).closest('.multiple-input'))" title="Down"><span class="sr-only">Down</span><span class="glyphicon glyphicon-chevron-down"></span></button>
            <button type="button" class="btn btn-link p-1" onclick="xlvoForms.manageMultipleInputs('up', $(this).closest('.multiple-input'))" title="Up"><span class="sr-only">Up</span><span class="glyphicon glyphicon-chevron-up"></span></button>
        </div>`);

        wrapper.append(actions);

        return wrapper;
    },


    manageMultipleInputs: function (action, currentElement) {
        const parentContainer = currentElement.parent();
        switch (action) {
            case 'add':
                const newIndexAdd = parentContainer.find(".multiple-input").length + 1;
                currentElement.after(this.addMultipleInput(newIndexAdd, 0));
                parentContainer.find(".multiple-input").last().find(".option-input").focus();
                break;
            case 'remove':
                if (parentContainer.find(".multiple-input").length > 1) {
                    currentElement.remove();
                } else {
                    currentElement.find(".option-input").val('');
                    currentElement.find(".option-input").attr('data-option-id', 0);
                }
                break;
            case 'up':
                const prev = currentElement.prev(".multiple-input");
                if (prev.length) {
                    prev.before(currentElement);
                }
                break;
            case 'down':
                const next = currentElement.next(".multiple-input");
                if (next.length) {
                    next.after(currentElement);
                }
                break;
        }
        this.updateMultipleInputs();
    },

    updateMultipleInputs: function () {
        this.inputs = [];
        $(this.parent).find(".multiple-input .option-input").each((i, element) => {
            const id = $(element).attr("data-option-id") ?? 0;
            const text = $(element).val().trim();
            if (text !== "") {
                this.inputs.push({
                    'id': parseInt(id),
                    'text': text,
                });
            }
        });
        this._updateHiddenInput();
        return this.inputs;
    },

    initCorrectOrder: function (id, number_input_label = "Order", text_input_label = "Text") {
        this.inputSelector = "#" + id;
        this.hiddenId = this.inputSelector;
        this.parent = $(this.inputSelector).parent();

        $(this.inputSelector).addClass('xlvo-template');

        this._parseInitialData();
        this.parent.find(".order-input-container").remove();

        if (this.inputs.length > 0) {
            this.inputs.forEach((data, i) => {
                const optionId = parseInt(data.id) || 0;
                const order = parseInt(data.order) || (i + 1);
                const text = data.text || '';
                const newInput = this.addCorrectOrderInput(i + 1, optionId, order, number_input_label, text_input_label);
                this.parent.append(newInput);
                const lastContainer = this.parent.find(".order-input-container").last();
                lastContainer.find(".option-input").val(text);
                lastContainer.find(".order-input").val(order);

            });
        } else {
            this.parent.append(this.addCorrectOrderInput(1, 0, 1, number_input_label, text_input_label));
        }

        this.parent.off('keyup.correctOrder', ".order-input-container .option-input, .order-input-container .order-input");
        this.parent.on('keyup.correctOrder change.correctOrder', ".order-input-container .option-input, .order-input-container .order-input", () => this.updateOrderInputs());
    },

    addCorrectOrderInput: function (index, option_id, current_order_value, number_input_label, text_input_label) {
        const originalInput = $(this.inputSelector);
        const baseId = originalInput.attr('id') || 'xlvo_co';
        const newTextId = baseId + '_text_' + index;
        const newOrderId = baseId + '_order_' + index;

        const newInputHtml = $(originalInput.prop("outerHTML"));
        newInputHtml.attr('id', newTextId);
        newInputHtml.attr('name', (originalInput.attr('name') || baseId) + '_text_' + index);
        newInputHtml.val('');
        newInputHtml.removeAttr('value');
        newInputHtml.addClass("option-input form-control");
        newInputHtml.removeAttr('required');
        newInputHtml.css('display', '');

        if (option_id && option_id !== 0) {
            newInputHtml.attr('data-option-id', option_id);
        }

        return `
            <div class="order-input-container gap-1"> 
                <div class="inputs">
                    <div class="d-flex gap-1">
                        <div class="flex-col shrink-0"> 
                            ${number_input_label} 
                            <input type="number" class="form-control form-control-sm order-input" 
                                   id="${newOrderId}" name="${baseId}_order_val_${index}" 
                                   size="2" min="1" max="999" value="${current_order_value}"> 
                        </div>
                        <div class="flex-col term-input"> 
                            ${text_input_label} 
                            ${newInputHtml.prop("outerHTML")} 
                        </div>
                    </div>
                </div>
                <div class="action-buttons shrink-0">
                    <button type="button" name="Add" class="btn btn-link" 
                            onclick="xlvoForms.manageCorrectOrder('add', $(this).closest('.order-input-container'), '${number_input_label}', '${text_input_label}')" 
                            title="Add"> 
                        <span class="sr-only">Add</span><span class="glyphicon glyphicon-plus"></span>
                    </button>
                    <button type="button" name="Remove" class="btn btn-link" 
                            onclick="xlvoForms.manageCorrectOrder('remove', $(this).closest('.order-input-container') ${(typeof number_input_label !== 'undefined' && typeof text_input_label !== 'undefined') ? `, '${number_input_label}', '${text_input_label}'` : ''})" 
                            title="Remove"> 
                        <span class="sr-only">Remove</span><span class="glyphicon glyphicon-minus"></span>
                    </button>
                </div>
            </div>
        `;
    },

    manageCorrectOrder: function (action, currentElement, number_input_label, text_input_label) {
        const parentContainer = currentElement.parent();
        switch (action) {
            case 'add':
                const newIndexAdd = parentContainer.find(".order-input-container").length + 1;
                const defaultOrder = newIndexAdd;
                currentElement.after(this.addCorrectOrderInput(newIndexAdd, 0, defaultOrder, number_input_label, text_input_label));
                parentContainer.find(".order-input-container").last().find(".option-input").focus();
                break;
            case 'remove':
                if (parentContainer.find(".order-input-container").length > 1) {
                    currentElement.remove();
                } else {
                    currentElement.find(".option-input").val('');
                    currentElement.find(".order-input").val('1');
                    currentElement.find(".option-input").attr('data-option-id', 0);
                }
                break;
        }
        this.updateOrderInputs();
    },

    updateOrderInputs: function () {
        this.inputs = [];
        $(this.parent).find(".order-input-container").each((i, element) => {
            const textInput = $(element).find(".option-input");
            const orderInput = $(element).find(".order-input");

            const id = textInput.attr("data-option-id") ?? 0;
            const text = textInput.val().trim();
            const order = parseInt(orderInput.val()) || 0;

            if (text !== "" || orderInput.val() !== "") {
                this.inputs.push({
                    'id': parseInt(id),
                    'order': order,
                    'text': text,
                });
            }
        });
        this._updateHiddenInput();
        return this.inputs;
    },

    initMultipleInputsCM: function (id, correct_label = "Correct") {
        this.inputSelector = "#" + id;
        this.hiddenId = this.inputSelector;
        this.parent = $(this.inputSelector).parent();

        this._parseInitialData();
        this.parent.find(".multiple-input-cm").remove();

        if (this.inputs.length > 0) {
            this.inputs.forEach((data, i) => {
                const optionId = parseInt(data.id) || 0;
                const text = data.text || '';
                const isCorrect = data.isCorrect || false;
                const newInput = this.addMultipleInputCM(i + 1, optionId, isCorrect, correct_label);
                this.parent.append(newInput);
                const lastContainer = this.parent.find(".multiple-input-cm").last();
                lastContainer.find(".option-input").val(text);
                if (isCorrect) {
                    lastContainer.find(".correct-checkbox").prop('checked', true);
                }
            });
        } else {
            this.parent.append(this.addMultipleInputCM(1, 0, false, correct_label));
        }
        this.parent.off('keyup.multipleInputsCM', ".multiple-input-cm .option-input");
        this.parent.off('change.multipleInputsCM', ".multiple-input-cm .correct-checkbox");

        this.parent.on('keyup.multipleInputsCM', ".multiple-input-cm .option-input", () => this.updateMultipleInputsCM());
        this.parent.on('change.multipleInputsCM', ".multiple-input-cm .correct-checkbox", () => this.updateMultipleInputsCM());
    },

    addMultipleInputCM: function (index, option_id, isCorrect, correct_label) {
        const originalInput = $(this.inputSelector);
        const baseId = originalInput.attr('id') || 'xlvo_cm';
        const newTextId = baseId + '_text_' + index;
        const newCheckboxId = baseId + '_cb_' + index;

        const newInputHtml = $(originalInput.prop("outerHTML"));
        newInputHtml.attr('id', newTextId);
        newInputHtml.attr('name', (originalInput.attr('name') || baseId) + '_text_' + index);
        newInputHtml.val('');
        newInputHtml.addClass("option-input");
        newInputHtml.removeAttr('required');
        newInputHtml.css('display', '');

        if (option_id && option_id !== 0) {
            newInputHtml.attr('data-option-id', option_id);
        }

        return `
        <div class="multiple-input-cm d-flex gap-1 mb-2 align-items-center" >
            <div class="form-check shrink-0 me-2 align-self-center">
                <input class="form-check-input correct-checkbox" 
                       type="checkbox" 
                       id="${newCheckboxId}" 
                       name="${baseId}_correct_${index}"
                       ${isCorrect ? 'checked' : ''}>
                <label class="form-check-label" for="${newCheckboxId}">
                    ${correct_label}
                </label>
            </div>
            <div class="flex-col w-full">
                ${newInputHtml.prop("outerHTML")}
            </div>
            <div class="action-buttons shrink-0 d-flex mt-2">
                <button type="button" class="btn btn-link p-1" onclick="xlvoForms.manageMultipleInputsCM('add', $(this).closest('.multiple-input-cm'), '${correct_label}')" title="Add"><span class="sr-only">Add</span><span class="glyphicon glyphicon-plus"></span></button>
                <button type="button" class="btn btn-link p-1" onclick="xlvoForms.manageMultipleInputsCM('remove', $(this).closest('.multiple-input-cm'))" title="Remove"><span class="sr-only">Remove</span><span class="glyphicon glyphicon-minus"></span></button>
                <button type="button" class="btn btn-link p-1" onclick="xlvoForms.manageMultipleInputsCM('down', $(this).closest('.multiple-input-cm'))" title="Down"><span class="sr-only">Down</span><span class="glyphicon glyphicon-chevron-down"></span></button>
                <button type="button" class="btn btn-link p-1" onclick="xlvoForms.manageMultipleInputsCM('up', $(this).closest('.multiple-input-cm'))" title="Up"><span class="sr-only">Up</span><span class="glyphicon glyphicon-chevron-up"></span></button>
            </div>
        </div>`;
    },

    manageMultipleInputsCM: function (action, currentElement, correct_label) {
        const parentContainer = currentElement.parent();
        switch (action) {
            case 'add':
                const newIndexAdd = parentContainer.find(".multiple-input-cm").length + 1;
                currentElement.after(this.addMultipleInputCM(newIndexAdd, 0, false, correct_label));
                parentContainer.find(".multiple-input-cm").last().find(".option-input").focus();
                break;
            case 'remove':
                if (parentContainer.find(".multiple-input-cm").length > 1) {
                    currentElement.remove();
                } else {
                    currentElement.find(".option-input").val('');
                    currentElement.find(".correct-checkbox").prop('checked', false);
                    currentElement.find(".option-input").attr('data-option-id', 0);
                }
                break;
            case 'up':
                const prev = currentElement.prev(".multiple-input-cm");
                if (prev.length) {
                    prev.before(currentElement);
                }
                break;
            case 'down':
                const next = currentElement.next(".multiple-input-cm");
                if (next.length) {
                    next.after(currentElement);
                }
                break;
        }
        this.updateMultipleInputsCM();
    },

    updateMultipleInputsCM: function () {
        this.inputs = [];
        $(this.parent).find(".multiple-input-cm").each((i, element) => {
            const optionInput = $(element).find(".option-input");
            const checkboxInput = $(element).find(".correct-checkbox");

            const id = optionInput.attr("data-option-id") ?? 0;
            const text = optionInput.val().trim();
            const isCorrect = checkboxInput.prop('checked');

            if (text !== "") {
                this.inputs.push({
                    'id': parseInt(id),
                    'text': text,
                    'isCorrect': isCorrect
                });
            }
        });
        this._updateHiddenInput();
        return this.inputs;
    }
};