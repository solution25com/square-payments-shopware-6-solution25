import template from './sw-order-detail-general.html.twig';

const { Component } = Shopware;
const { Criteria } = Shopware.Data;

Component.override('sw-order-detail-general', {
    template,

    inject: ['repositoryFactory'],

    data() {
        return {
            squareLogTransactions: null,
            squarePaymentsColumns: [
                { property: 'paymentId', label: 'Payment ID' },
                { property: 'type', label: 'Type' },
                { property: 'cardCategory', label: 'Card Category' },
                { property: 'paymentMethodType', label: 'Payment Method Type' },
                { property: 'amount', label: 'Amount' },
                { property: 'currency', label: 'Currency' },
                { property: 'expiryMonth', label: 'Expiry Month' },
                { property: 'expiryYear', label: 'Expiry Year' },
                { property: 'cardLast4', label: 'Card Last 4' },
                { property: 'statusCode', label: 'Status' },
                { property: 'lastUpdate', label: 'Last Update' }
            ],
            isSquarePayment: false
        };
    },

    computed: {
        showSquareLogTable() {
            return this.isSquarePayment;
        },
        squarePaymentsTransactions() {
            if (!this.squareLogTransactions) {
                return [];
            }

            return this.squareLogTransactions.map(transaction => {
                const details = transaction.customFields || {};
                return {
                    paymentId: transaction.paymentId || details.payment_id || '-',
                    type: details.type || '-',
                    cardCategory: details.card_category || '-',
                    paymentMethodType: details.payment_method_type || '-',
                    amount: details.amount ? Number(details.amount).toFixed(2) : '-',
                    currency: details.currency ? `${details.currency}` : '-',
                    expiryMonth: details.expiry_month ? String(parseInt(details.expiry_month, 10)) : '-',
                    expiryYear: details.expiry_year || '-',
                    cardLast4: details.card_last_4 || '-',
                    statusCode: details.status_code || '-',
                    lastUpdate: this.formatDate(details.last_update)
                };
            });
        }
    },

    async created() {
        await this.fetchSquareTransactions();
        await this.checkIsSquarePaymentMethod();
    },

    methods: {
        async fetchSquareTransactions() {
            if (!this.order || !this.order.id) {
                console.warn('Order not loaded yet.');
                return;
            }

            const criteria = new Criteria();
            criteria.addFilter(Criteria.equals('orderId', this.order.id));
            criteria.addSorting(Criteria.sort('createdAt', 'DESC'));

            const squareTransactionRepository = this.repositoryFactory.create('squarepayments_transaction');

            try {
                this.squareLogTransactions = await squareTransactionRepository.search(criteria, Shopware.Context.api);
            } catch (error) {
                console.error('Error fetching order transactions:', error);
            }
        },
        async checkIsSquarePaymentMethod() {
            // Use order's transactions to check payment method
            if (!this.order || !this.order.transactions || this.order.transactions.length === 0) {
                this.isSquarePayment = false;
                return;
            }
            let isSquare = false;
            for (const transaction of this.order.transactions) {
                if (!transaction.paymentMethodId) continue;
                const paymentMethod = await this.getPaymentMethod(transaction.paymentMethodId);
                if (paymentMethod && paymentMethod.handlerIdentifier === 'SquarePayments\\Gateways\\CreditCard') {
                    isSquare = true;
                    break;
                }
            }
            this.isSquarePayment = isSquare;
        },
        formatDate(dateString) {
            if (!dateString) return '-';

            const date = new Date(dateString);

            return new Intl.DateTimeFormat('en-GB', {
                year: 'numeric',
                month: '2-digit',
                day: '2-digit',
                hour: '2-digit',
                minute: '2-digit',
                hour12: false,
            }).format(date);
        },
        async getPaymentMethod(paymentMethodId) {
            const repository = this.repositoryFactory.create('payment_method');
            const criteria = new Criteria();
            criteria.addFilter(Criteria.equals('id', paymentMethodId));
            const result = await repository.search(criteria, Shopware.Context.api);
            return result.first();
        },
    }
});