(function (global) {
    'use strict';

    const FELD_IDS = [
        'taxameter',
        'ohne_taxameter',
        'kartenzahlung',
        'rechnungsfahrten',
        'krankenfahrten',
        'gutscheine',
        'alita',
        'tanken_waschen',
        'sonstige_ausgaben'
    ];

    function sanitizeNumeric(input) {
        if (!input) {
            return 0;
        }
        const rawValue = typeof input.value === 'string' ? input.value.trim() : '';
        if (rawValue === '') {
            return 0;
        }
        const normalized = rawValue.replace(',', '.');
        const numeric = parseFloat(normalized);
        return Number.isFinite(numeric) ? numeric : 0;
    }

    function getNumericValueById(id) {
        const element = document.getElementById(id);
        return sanitizeNumeric(element);
    }

    function calculateTotal(targetId = 'gesamtumsatz') {
        const total = FELD_IDS.reduce((sum, id) => {
            const value = getNumericValueById(id);
            switch (id) {
                case 'taxameter':
                case 'ohne_taxameter':
                    return sum + value;
                default:
                    return sum - value;
            }
        }, 0);

        const target = document.getElementById(targetId);
        if (target) {
            target.value = total.toFixed(2);
        }

        return total;
    }

    function validateInput(input) {
        if (!input) {
            return;
        }

        if (input.value === '') {
            input.style.border = '2px solid #ccc';
            return;
        }

        const value = sanitizeNumeric(input);
        input.style.border = value >= 0 ? '2px solid #4caf50' : '2px solid #ccc';
    }

    function bindNumericInputs() {
        const inputs = document.querySelectorAll('input[type="number"]');
        inputs.forEach((input) => {
            input.addEventListener('input', () => {
                calculateTotal();
                validateInput(input);
            });
            validateInput(input);
        });
    }

    function mindestensEinUmsatz() {
        const taxameter = getNumericValueById('taxameter');
        const ohneTaxameter = getNumericValueById('ohne_taxameter');

        if (taxameter === 0 && ohneTaxameter === 0) {
            alert('Bitte gib entweder einen Umsatz *mit* oder *ohne* Taxameter ein.');
            return false;
        }
        return true;
    }

    function initForm(options = {}) {
        const { formId, overlayId = 'overlay', submitDelay = 2000 } = options;
        if (!formId) {
            return;
        }

        const form = document.getElementById(formId);
        if (!form) {
            return;
        }

        const overlay = overlayId ? document.getElementById(overlayId) : null;

        form.addEventListener('submit', (event) => {
            event.preventDefault();
            if (!mindestensEinUmsatz()) {
                return;
            }

            if (overlay) {
                overlay.style.display = 'flex';
            }

            const submit = () => form.submit();
            if (submitDelay > 0) {
                setTimeout(submit, submitDelay);
            } else {
                submit();
            }
        });
    }

    global.UmsatzHelpers = {
        calculateTotal,
        validateInput,
        mindestensEinUmsatz,
        bindNumericInputs,
        initForm
    };
})(window);
