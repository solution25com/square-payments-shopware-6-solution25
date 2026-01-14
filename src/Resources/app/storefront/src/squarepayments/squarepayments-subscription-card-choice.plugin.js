const {PluginBaseClass} = window;
export default class SquarePaymentsSubscriptionCardChoicePlugin extends PluginBaseClass {
    get defaultOptions() {
        return {
            saveUrl: '/account/squarepayments/subscription-card-choice',
        };
    }

    init() {
        this.options = Object.assign({}, this.defaultOptions, this.options || {});

        this.bindOpenModalButtons();
        this.bindModal();
    }

    bindOpenModalButtons() {
        this.el.querySelectorAll('[data-squarepayments-subscription-card-choice-open]').forEach((btn) => {
            btn.addEventListener('click', (e) => this.openModal(e));
        });
    }

    bindModal() {
        this.modal = document.getElementById('squarepayments-subscription-card-choice-modal');
        this.backdrop = document.getElementById('squarepayments-subscription-card-choice-backdrop');
        this.select = document.getElementById('squarepayments-subscription-card-choice-select');
        this.saveButton = document.getElementById('squarepayments-subscription-card-choice-save');
        this.cancelButtons = this.modal ? this.modal.querySelectorAll('[data-dismiss-modal]') : [];

        if (!this.modal || !this.backdrop || !this.select || !this.saveButton) {
            return;
        }

        this.cancelButtons.forEach((btn) => btn.addEventListener('click', () => this.hideModal()));
        this.backdrop.addEventListener('click', () => this.hideModal());

        this.saveButton.addEventListener('click', () => this.saveChoice());
    }

    openModal(event) {
        if (!this.modal) {
            return;
        }

        const btn = event.currentTarget;
        const subscriptionId = btn.getAttribute('data-subscription-id');
        const currentChoice = btn.getAttribute('data-current-choice');

        this.modal.setAttribute('data-subscription-id', subscriptionId);

        if (currentChoice) {
            this.select.value = currentChoice;
        } else {
            this.select.selectedIndex = 0;
        }

        this.modal.style.display = 'block';
        this.backdrop.style.display = 'block';
        document.body.style.overflow = 'hidden';

        this.modal.offsetHeight;
        this.backdrop.offsetHeight;

        requestAnimationFrame(() => {
            this.modal.classList.add('show');
            this.backdrop.classList.add('show');
        });
    }

    hideModal() {
        if (!this.modal) {
            return;
        }

        this.modal.classList.add('fade-out');
        this.backdrop.classList.add('fade-out');
        this.modal.classList.remove('show');
        this.backdrop.classList.remove('show');

        setTimeout(() => {
            this.modal.style.display = 'none';
            this.backdrop.style.display = 'none';
            this.modal.classList.remove('fade-out');
            this.backdrop.classList.remove('fade-out');
            document.body.style.overflow = '';
        }, 300);
    }

    async saveChoice() {
        const subscriptionId = this.modal.getAttribute('data-subscription-id');
        const cardId = this.select.value;

        if (!subscriptionId || !cardId) {
            return;
        }

        this.saveButton.disabled = true;

        try {
            const formData = new FormData();
            formData.append('subscriptionId', subscriptionId);
            formData.append('cardId', cardId);

            const res = await fetch(this.options.saveUrl, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });

            const data = await res.json();
            if (!res.ok || !data.success) {
                throw new Error(data.message || 'Failed to save');
            }

            window.location.reload();
        } catch (e) {
            const errorElement = document.getElementById('squarepayments-card-errors');
            if (errorElement) {
                errorElement.textContent = e.message;
                errorElement.style.display = 'block';
            }
            this.saveButton.disabled = false;
        }
    }
}

