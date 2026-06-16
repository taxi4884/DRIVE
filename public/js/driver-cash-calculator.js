(function (window, document) {
    'use strict';

    function normalizeId(field) {
        return field.charAt(0) === '#' ? field.substring(1) : field;
    }

    function getElement(id) {
        return document.getElementById(id);
    }

    window.DriverCashCalculator = {
        init(options) {
            const config = Object.assign({
                incomeFields: [],
                expenseFields: [],
                outputField: null,
                onUpdate: null,
            }, options || {});

            const incomeIds = config.incomeFields.map(normalizeId);
            const expenseIds = config.expenseFields.map(normalizeId);
            const outputSelector = config.outputField;
            const listeners = {};
            let lastState = {
                total: 0,
                income: 0,
                expenses: 0,
                values: {},
            };

            function update() {
                const values = {};
                let incomeSum = 0;
                let expenseSum = 0;

                incomeIds.forEach((id) => {
                    const element = getElement(id);
                    if (!element) {
                        return;
                    }

                    const value = parseFloat(element.value) || 0;
                    values[id] = value;
                    incomeSum += value;
                });

                expenseIds.forEach((id) => {
                    const element = getElement(id);
                    if (!element) {
                        return;
                    }

                    const value = parseFloat(element.value) || 0;
                    values[id] = value;
                    expenseSum += value;
                });

                const total = incomeSum - expenseSum;

                if (outputSelector) {
                    const outputElement = typeof outputSelector === 'string'
                        ? document.querySelector(outputSelector)
                        : outputSelector;

                    if (outputElement) {
                        outputElement.value = total.toFixed(2);
                    }
                }

                lastState = {
                    total,
                    income: incomeSum,
                    expenses: expenseSum,
                    values,
                    elements: listeners,
                };

                if (typeof config.onUpdate === 'function') {
                    config.onUpdate(lastState);
                }
            }

            const attachFields = (ids) => {
                ids.forEach((id) => {
                    const element = getElement(id);
                    if (!element) {
                        return;
                    }

                    listeners[id] = element;
                    element.addEventListener('input', update);
                });
            };

            attachFields(incomeIds);
            attachFields(expenseIds);
            update();

            return {
                recalc: update,
                getTotals() {
                    return Object.assign({}, lastState);
                },
            };
        },
    };
}(window, document));
