const {PluginBaseClass} = window;


export default class SquarePaymentsCreditCard extends PluginBaseClass {

    static options = {
        payments: null,
        threeDS: false,
        card: null,
        config: {},
        translations: {},
        appId: '',
        locationId: '',
        salesChannelAccessKey: '',
        amount: null,
        billing: null,
        currency: '',
        intent: 'CHARGE',
        containerId: 'paymentForm',
        newCardFormId: 'newCardForm',
        savedCardsId: 'savedCards',
        payButtonId: 'confirmFormSubmit',
        saveCardFormClass: '.save-card',
        saveCardButtonId: 'saveCardButton',
        addCardButtonId: 'addCardButton',
        saveCardCheckboxId: 'saveCard',
        apiEndpoints: {
            authorizePayment: '/squarepayments/authorize-payment',
            addCard: '/account/squarepayments/add-card',
            getSavedCards: '/squarepayments/get-saved-cards'
        },
    };

    _init() {
        try {
            const mode = this.options.mode || 'sandbox';
            this.options.appId = mode === 'production' ? this.options.applicationIdProduction || '' : this.options.applicationIdSandbox || '';
            this.options.locationId = mode === 'production' ? this.options.locationIdProduction || '' : this.options.locationIdSandbox || '';
            if (!this.options.appId || !this.options.locationId) {
                console.error('[SquarePayments] appId or locationId not provided in options.');
            }
        } catch (e) {
            console.error('[SquarePayments] Failed to parse options:', e);
        }
        this._setupEventListeners();
        if (this.options.isPaymentForm) {
            this._loadSavedCards();
            this._initializeSquareCard().then();
        }
    }

    // Setup event listeners
    _setupEventListeners() {
        const confirmOrderForm = document.getElementById('confirmOrderForm');
        if (confirmOrderForm) {
            const submitButton = confirmOrderForm.querySelector('button[type="submit"]');
            if (submitButton) {
                submitButton.id = 'confirmFormSubmit';
            }
            confirmOrderForm.onsubmit = function (e) {
                e.preventDefault();
                const squareTransactionId = document.getElementById('square_transaction_id');
                if (squareTransactionId && squareTransactionId.value === '') {
                    payButton.click();
                } else {
                    confirmOrderForm.submit();
                }
            }
        }

        const savedCardsSelect = document.getElementById(this.options.savedCardsId);
        if (savedCardsSelect) {
            savedCardsSelect.addEventListener('change', this._toggleCardForm.bind(this));
        }
        const payButton = document.getElementById(this.options.payButtonId);
        if (payButton) {
            payButton.addEventListener('click', (e) => {
                e.preventDefault();
                this._handlePayment().then();
            });
        }

    }


    _loadSavedCards() {
        fetch(this.options.apiEndpoints.getSavedCards)
            .then(res => res.json())
            .then(data => {
                const savedCards = data.cards || [];
                let savedCardsSection = document.getElementById('saved-cards-section');
                const savedCardsSelect = document.getElementById(this.options.savedCardsId);
                if (savedCards.length === 0) {
                    if (savedCardsSection) {
                        savedCardsSection.style.display = 'none';
                    }
                } else {
                    savedCards.forEach(card => {
                        const option = document.createElement('option');
                        option.value = card.id;
                        option.textContent = `${card.cardholder_name } | ${card.card_brand} | ****${card.last_4} | (${this.options.translations.expiryDate || 'Exp: '} ${card.exp_month}/${card.exp_year})`;
                        if (savedCardsSelect) {
                            savedCardsSelect.appendChild(option);
                        }
                    });
                    if (savedCardsSection) {
                        savedCardsSection.style.display = 'block';
                    }
                }
                this._toggleCardForm();
            })
            .catch(err => console.error('[SquarePayments] Failed to load saved cards:', err));
    }

    // Toggle new card form visibility
    _toggleCardForm() {
        let savedCardsSelect = document.getElementById(this.options.savedCardsId);
        let newCardForm = document.getElementById(this.options.newCardFormId);
        let billingSection = document.getElementById('billingSection');
        if (savedCardsSelect && newCardForm) {
            newCardForm.style.display = savedCardsSelect.value === 'new' ? 'block' : 'none';
            if (billingSection) {
                billingSection.style.display = savedCardsSelect.value === 'new' ? 'block' : 'none';
            }
        }
    }

    // Initialize Square Card
    async _initializeSquareCard() {
        if (!this.options.appId || !this.options.locationId) {
            console.error('[SquarePayments] appId or locationId not provided.');
            return;
        }
        this.options.payments = await Square.payments(this.options.appId, this.options.locationId);
        this.options.card = await this.options.payments.card();
        await this.options.card.attach('#card-container');
        const saveCardCheckbox = document.querySelector(this.options.saveCardFormClass);
        if(saveCardCheckbox) {
            saveCardCheckbox.style.display = 'block'
        }
    }

    _validateFormInputs() {
        const errors = [];
        const requiredFields = document.querySelectorAll('#confirmOrderForm input[required], #confirmOrderForm select[required], input[form="confirmOrderForm"][required], select[form="confirmOrderForm"][required]');
        requiredFields.forEach(field => {
            if (field.type === 'checkbox' && !field.checked) {
                errors.push({
                    field: field.id,
                    message: (this.options.translations.requiredCheckbox || 'Please check {fieldName}').replace('{fieldName}', field.name)
                });
            } else if (field.value.trim() === '') {
                errors.push({
                    field: field.id,
                    message: (this.options.translations.requiredField || 'Please fill out {fieldName}').replace('{fieldName}', field.name)
                });
            }
        });


        return {valid: errors.length === 0, errors};
    }

    _showError(errors) {
        ['billingFirstName', 'billingLastName', 'billingEmail', 'billingStreet', 'billingCity', 'billingZip', 'billingCountry', 'billingState'].forEach(field => {
            const input = document.getElementById(field);
            let errorDiv = document.getElementById(`${field}-error`);
            if (input) input.classList.remove('error');
            if (errorDiv) errorDiv.style.display = 'none';
        });

        errors.forEach(error => {
            const input = document.getElementById(error.field);
            const errorMessage = document.getElementById(`${error.field}-error`);
            if (input) input.classList.add('error');
            if (errorMessage) {
                errorMessage.textContent = error.message;
                errorMessage.style.display = 'block';
            }
            if (input) input.focus();
        });
    }

    _togglePageOverlay(show) {
        let overlay = document.getElementById('square-page-overlay');

        if (show) {
            if (!overlay) {
                overlay = document.createElement('div');
                overlay.id = 'square-page-overlay';
                overlay.style.position = 'fixed';
                overlay.style.top = '0';
                overlay.style.left = '0';
                overlay.style.width = '100%';
                overlay.style.height = '100%';
                overlay.style.backgroundColor = 'rgba(255, 255, 255, 0.7)';
                overlay.style.zIndex = '9999';
                overlay.style.display = 'flex';
                overlay.style.alignItems = 'center';
                overlay.style.justifyContent = 'center';

                const spinner = document.createElement('div');
                spinner.id = 'square-processing-loader';
                spinner.style.width = '48px';
                spinner.style.height = '48px';
                spinner.style.border = '5px solid #ccc';
                spinner.style.borderTop = '5px solid #333';
                spinner.style.borderRadius = '50%';
                spinner.style.animation = 'square-spin 1s linear infinite';
                overlay.appendChild(spinner);

                if (!document.getElementById('square-spinner-style')) {
                    const style = document.createElement('style');
                    style.id = 'square-spinner-style';
                    style.innerHTML = `
                    @keyframes square-spin {
                        0% { transform: rotate(0deg); }
                        100% { transform: rotate(360deg); }
                    }
                `;
                    document.head.appendChild(style);
                }

                document.body.appendChild(overlay);
            } else {
                overlay.style.display = 'flex';
            }
        } else {
            if (overlay) {
                overlay.style.display = 'none';
            }
        }
    }

    // Manage loading button state
    _showLoadingButton(show, buttonId) {
        let button = document.getElementById(buttonId);
        if (button == null) {
            button = document.getElementById(this.options.payButtonId);
        }
        const confirmOrderForm = document.getElementById('confirmOrderForm');
        if (confirmOrderForm) {
            button = confirmOrderForm.querySelector('button[type="submit"]');
        }
        if (button == null) {
            button = document.getElementById(this.options.saveCardButtonId);
        }

        this._togglePageOverlay(show);

        if (show) {
            button.disabled = true;
            window.originalBtnText = button.textContent;
            button.textContent = this.options.translations.processing || 'Processing...';
        } else {
            button.disabled = false;
            if (window.originalBtnText) {
                button.textContent = window.originalBtnText;
            }
        }
    }

    // Handle payment in checkout/confirm or save card in /saved-cards
    async _handlePayment() {
        const buttonId = this.options.payButtonId;
        this._showLoadingButton(true, buttonId);

        if (this.options.isSubscription) {
            // eslint-disable-next-line no-alert
            const userConfirmed = confirm(this.options.translations.subscriptionConfirmation || 'This product is part of a recurring payment plan. Your card details will be saved and used for future payments. Do you want to proceed?');
            if (!userConfirmed) {
                this._showLoadingButton(false, buttonId);
                return;
            }
        }

        const saveCardCheckbox = document.getElementById(this.options.saveCardCheckboxId);

        const savedCardsSelect = document.getElementById(this.options.savedCardsId);
        if (savedCardsSelect) {
            const cardId = savedCardsSelect.value !== 'new' ? savedCardsSelect.value : null;
            if (cardId) {
                await this._authorizePayment(null, cardId);
                return;
            }
        }

        // Validate form inputs
        const validation = this._validateFormInputs();
        if (!validation.valid) {
            this._showError(validation.errors);
            this._showLoadingButton(false, buttonId);
            return;
        }
        if (!this.options.card) {
            // eslint-disable-next-line no-alert
            alert(this.options.translations.cardFieldsNotLoaded || 'Card fields are not loaded yet. Please wait a few seconds.');
            this._showLoadingButton(false, buttonId);
            return;
        }
        let saveThisCard = saveCardCheckbox && saveCardCheckbox.checked;
        const verificationResult = await this._verifyBuyerAndGeneratePaymentToken(saveThisCard);


        await this._authorizePayment(verificationResult, null, saveThisCard);

    }


    async _verifyBuyerAndGeneratePaymentToken(saveCard) {
        const statusContainer = document.getElementById('payment-status-container');
        let state = this.options.billing.state;
        if (state.includes('-')) {
            state = state.split('-')[1];
        }
        this.options.intent = saveCard ? 'CHARGE_AND_STORE' : 'CHARGE';
        const verificationDetails = {
            amount: String(this.options.amount),
            billingContact: {
                givenName: this.options.billing.givenName ?? '',
                familyName: this.options.billing.familyName ?? '',
                email: this.options.billing.email ?? '',
                phone: this.options.billing.phone ?? '',
                addressLines: [this.options.billing.street, this.options.billing.addressLine1, this.options.billing.addressLine2],
                countryCode: this.options.billing.country ?? '',
                city: this.options.billing.city ?? ''
            },
            currencyCode: this.options.currency,
            intent: this.options.intent,
            customerInitiated: true,
            sellerKeyedIn: false,
        };
        if (state !== '') {
            verificationDetails.billingContact.state = state;
        }
        try{
            let result = null;
            if(this.options.threeDS){
                result = await this.options.card.tokenize(verificationDetails);
            }else{
                result = await this.options.card.tokenize();
            }
            let paymentToken = null;
            if (result.status === 'OK') {
                paymentToken = result.token;
                statusContainer.innerHTML = "Tokenization Successful";
                return [paymentToken,verificationDetails];
            } else {
                let errorMessage = `Tokenization failed with status: ${result.status}`;
                if (result.errors) {
                    errorMessage += ` and errors: ${JSON.stringify(
                        result.errors
                    )}`;
                }
                console.error('[SquarePayments] ', errorMessage);

                return null;
            }
        } catch (err) {
            console.error('[SquarePayments] Tokenization error', err);

        }
    }

    // Authorize payment
    async _authorizePayment(verificationResult, cardId, saveCard = false) {
        const url = window.location.pathname;
        const pattern = /^\/account\/order\/edit\/(.+)$/;
        const match = url.match(pattern);

        let orderId = null;
        if (match) {
            orderId = match[1];
        }

        let bodyParams = {
            cardId,
            saveCard,
            orderId,
            currency: this.options.currency,
            amount: this.options.amount,
            intent: this.options.intent,
            isSubscription: this.options.isSubscription || false,
        };

        if(verificationResult){
            bodyParams.paymentToken=  verificationResult[0];
            bodyParams.billingAddress =  verificationResult[1].billingContact
        }

        fetch(this.options.apiEndpoints.authorizePayment, {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(bodyParams)
        })
            .then(res => res.json())
            .then(data => {
                if (!data.status || data.status !== 'success') {
                    // eslint-disable-next-line no-alert
                    alert(data.message || this.options.translations.paymentSetupFailed || 'Payment setup failed. Please try again.');
                    this._showLoadingButton(false, this.options.payButtonId);
                    return;
                }
                const statusContainer = document.getElementById('payment-status-container');
                statusContainer.innerHTML = "Payment Successful";

                this._updateHiddenFields(data);
                document.getElementById('confirmOrderForm').submit();
            })
            .catch(err => {
                console.error('Payment setup error:', err);
                // eslint-disable-next-line no-alert
                alert(this.options.translations.paymentSetupError || 'An error occurred during payment setup. Please try again.');
                this._showLoadingButton(false, this.options.payButtonId);
            });
    }

    _updateHiddenFields(data) {

        document.getElementById('squarepayments_transaction_id').value = data.payment.id;
        document.getElementById('squarepayments_payment_status').value = data.status;

        let paymentDataInput = document.getElementById('squarepayments_payment_data');
        if (!paymentDataInput) {
            paymentDataInput = document.createElement('input');
            paymentDataInput.type = 'hidden';
            paymentDataInput.id = 'square_payment_data';
            paymentDataInput.name = 'square_payment_data';
            document.getElementById('confirmOrderForm').appendChild(paymentDataInput);
        }
        paymentDataInput.value = data.payment ? JSON.stringify(data.payment) : '';
        let isSubscriptionInput = document.getElementById('squarepayments_is_subscription');
        if (!isSubscriptionInput) {
            isSubscriptionInput = document.createElement('input');
            isSubscriptionInput.type = 'hidden';
            isSubscriptionInput.id = 'squarepayments_is_subscription';
            isSubscriptionInput.name = 'squarepayments_is_subscription';
            document.getElementById('confirmOrderForm').appendChild(isSubscriptionInput);
        }
        isSubscriptionInput.value = this.options.isSubscription || false ? '1' : '0';
        let isPaymentCardInput = document.getElementById('squarepayments_subscription_card');
        if (!isPaymentCardInput) {
            isPaymentCardInput = document.createElement('input');
            isPaymentCardInput.type = 'hidden';
            isPaymentCardInput.id = 'squarepayments_subscription_card';
            isPaymentCardInput.name = 'squarepayments_subscription_card';
            document.getElementById('confirmOrderForm').appendChild(isPaymentCardInput);
        }
        isPaymentCardInput.value = this.options.isSubscription || false ?
            isPaymentCardInput.value = data.card ? JSON.stringify(data.card) : '' : '';
    }
}