const xlvoBase = {
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
};

console.log("xlvoBase initialized:", xlvoBase);