const {PluginBaseClass} = window;


export default class SquarePaymentsSavedCards extends PluginBaseClass {

    static options = {
        card: null,
        config: {},
        threeDS: false,
        translations: {},
        appId: '',
        locationId: '',
        salesChannelAccessKey: '',
        amount: null,
        billing: null,
        currency: '',
        addCardFormId: 'addCardForm',
        newCardFormId: 'newCardForm',
        savedCardsId: 'savedCards',
        payButtonId: 'confirmFormSubmit',
        saveCardButtonId: 'saveCardButton',
        deleteCardButtonId: 'remove-card',
        addCardButtonId: 'addCardButton',
        saveCardCheckboxId: 'saveCard',
        apiEndpoints: {
            addCard: '/account/squarepayments/add-card',
            getSavedCards: '/squarepayments/get-saved-cards',
            deleteCard: '/account/squarepayments/delete-card/{cardId}',
        },
        isPaymentForm: false,
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
        this._initializeSquareCard().then();
    }

    async _initializeSquareCard() {
        if (!this.options.appId || !this.options.locationId) {
            console.error('[SquarePayments] appId or locationId not provided.');
            return;
        }
        this.options.payments = await Square.payments(this.options.appId, this.options.locationId);
        this.options.card = await this.options.payments.card();
        await this.options.card.attach('#card-container');
    }

    // Setup event listeners
    _setupEventListeners() {

        const addCardButton = document.getElementById(this.options.addCardButtonId);
        if (addCardButton) {
            addCardButton.addEventListener('click', () => {
                const addCardForm = document.getElementById(this.options.addCardFormId);
                addCardForm.style.display =   window.getComputedStyle(addCardForm).display === 'none' ? 'flex' : 'none';
                addCardButton.textContent = addCardForm.style.display === 'none' ? this.options.translations.addCard || '+ Add Card' : this.options.translations.cancelCard || '- Cancel';
            });
        }

        const saveCardButton = document.getElementById(this.options.saveCardButtonId);
        if (saveCardButton) {
            saveCardButton.addEventListener('click', (e) => {
                e.preventDefault();
                this._generateTokenAndCreateCard().then();
            });
        }
        const deleteCardButtons = document.getElementsByClassName(this.options.deleteCardButtonId);
        Array.from(deleteCardButtons).forEach((button) => {
            button.addEventListener('click', (e) => {
                e.preventDefault();
                //get data-card-id attribute
                const cardId = button.getAttribute('data-card-id');
                if (!cardId) {
                    console.error('[SquarePayments] Card ID not found for deletion.');
                    return;
                }
                // eslint-disable-next-line no-alert
                if (!confirm(this.options.translations.confirmCardDeletion || 'Are you sure you want to delete this card?')) {
                    return;
                }
                this._showLoadingButton(true, this.options.deleteCardButtonId);
                fetch(this.options.apiEndpoints.deleteCard.replace('{cardId}', cardId), {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                }).then(response => {
                    if (!response.ok) {
                        throw new Error(`Response status: ${response.status}`);
                    }
                    return response.json();
                }).then(data => {
                    if (!data.success) {
                        // eslint-disable-next-line no-alert
                        alert(data.message || this.options.translations.deleteCardFailed || 'Failed to delete card. Please try again.');
                        this._showLoadingButton(false, this.options.deleteCardButtonId);
                        return;
                    }
                    window.scrollTo({top: 0, behavior: 'smooth'});
                    setTimeout(() => window.location.reload(), 300);
                }).catch(error => {
                    console.error('[SquarePayments] Card deletion error:', error);
                });
            });
        });
    }

    // Toggle new card form visibility
    _toggleCardForm() {
        let savedCardsSelect = document.getElementById(this.options.savedCardsId);
        let addCardForm = document.getElementById(this.options.addCardFormId);
        if (savedCardsSelect && addCardForm) {
            addCardForm.style.display = savedCardsSelect.value === 'new' ? 'block' : 'none';
        }
    }

    _validateFormInputs() {
        try {
            const errors = [];
            const firstName = document.getElementById('billingFirstName').value.trim();
            const lastName = document.getElementById('billingLastName').value.trim();
            const email = document.getElementById('billingEmail').value.trim();
            const street = document.getElementById('billingAddress').value.trim();
            const city = document.getElementById('billingCity').value.trim();
            const zip = document.getElementById('billingZip').value.trim();
            const country = document.getElementById('billingCountry').value.trim();

            if (!firstName) errors.push({
                field: 'billingFirstName',
                message: translations.pleaseEnterValidFirstName || 'Please enter a valid first name'
            });
            if (!lastName) errors.push({
                field: 'billingLastName',
                message: translations.pleaseEnterValidLastName || 'Please enter a valid last name'
            });
            if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) errors.push({
                field: 'billingEmail',
                message: translations.pleaseEnterValidEmail || 'Please enter a valid email address'
            });
            if (!street) errors.push({
                field: 'billingAddress',
                message: translations.pleaseEnterValidStreet || 'Please enter a valid street address'
            });
            if (!city) errors.push({
                field: 'billingCity',
                message: translations.pleaseEnterValidCity || 'Please enter a valid city'
            });
            if (!zip) errors.push({
                field: 'billingZip',
                message: translations.pleaseEnterValidZip || 'Please enter a valid zip code'
            });
            if (!country) errors.push({
                field: 'billingCountry',
                message: translations.pleaseEnterValidCountry || 'Please select a country'
            });


            return {valid: errors.length === 0, errors};
        }
        catch (error) {
            console.error('[SquarePayments] Form validation error:', error);
            return {valid: false, errors: [{field: null, message: this.options.translations.formValidationError || 'An error occurred during form validation. Please try again.'}]};
        }
    }

     _showError(errors) {
        ['billingFirstName', 'billingLastName', 'billingEmail', 'billingAddress', 'billingCity', 'billingZip', 'billingCountry'].forEach(field => {
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



    async _generateTokenAndCreateCard() {
        const buttonId = this.options.saveCardButtonId;
        this._showLoadingButton(true, buttonId);

        try {
            const validation = this._validateFormInputs();
            if (!validation.valid) {
                this._showError(validation.errors);
                this._showLoadingButton(false, buttonId);
                return;
            }


            let tokenResult = null;

            if(this.options.threeDS) {

                this.options.intent = 'STORE';
                const verificationDetails = {
                    billingContact: {
                        givenName: document.getElementById('billingFirstName').value,
                        familyName: document.getElementById('billingLastName').value,
                        email: document.getElementById('billingEmail').value,
                        addressLines: [ document.getElementById('billingAddress').value],
                        countryCode:document.getElementById('billingCountry').value,
                        city: document.getElementById('billingCity').value
                    },
                    intent: this.options.intent,
                    customerInitiated: true,
                    sellerKeyedIn: false,
                };

                tokenResult = await this.options.card.tokenize(verificationDetails);
            }else{
                tokenResult = await this.options.card.tokenize();

            }
            if (tokenResult.status === 'OK') {
                const token = tokenResult.token;

                await this._saveCard(token);

            } else {
                let errorMessage = `Tokenization failed with status: ${tokenResult.status}`;
                if (tokenResult.errors) {
                    errorMessage += ` and errors: ${JSON.stringify(
                        tokenResult.errors,
                    )}`;
                }

                throw new Error(errorMessage);
            }

        }  catch (error) {
            console.error('[SquarePayments] Tokenization error', error);
            // eslint-disable-next-line no-alert
            alert(this.options.translations.cardVerificationFailed || 'Card information could not be verified.');
            this._showLoadingButton(false, buttonId);
    }
    }
        async _saveCard(token)
        {
            let bodyParams = {cardToken:token};
            bodyParams.billingAddress = {
                firstName: document.getElementById('billingFirstName').value,
                lastName: document.getElementById('billingLastName').value,
                email: document.getElementById('billingEmail').value,
                addressLine1: document.getElementById('billingAddress').value,
                locality: document.getElementById('billingCity').value,
                postalCode: document.getElementById('billingZip').value,
                country: document.getElementById('billingCountry').value,
            };

            try {
                let response = await fetch(this.options.apiEndpoints.addCard, {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify(bodyParams)
                })
                if (!response.ok) {
                    throw new Error(`Response status: ${response.status}`);
                }
                let data = await response.json();
                if (!data.success) {
                    // eslint-disable-next-line no-alert
                    alert(data.message || this.options.translations.saveCardFailed || 'Failed to save card. Please try again.');
                    this._showLoadingButton(false, this.options.saveCardButtonId);
                    return;
                }
                window.scrollTo({top: 0, behavior: 'smooth'});
                setTimeout(() => window.location.reload(), 300);
            } catch (error) {
                console.error('[SquarePayments] Card save error:', error);
                // eslint-disable-next-line no-alert
                alert(this.options.translations.saveCardError || '[SquarePayments] An error occurred while saving the card. Please try again.');
                this._showLoadingButton(false, this.options.saveCardButtonId);
            }
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
}