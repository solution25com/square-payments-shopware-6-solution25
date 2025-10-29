import template from './index.html.twig';

const { Component } = Shopware;

Component.register('square-webhook-manager', {
    template,
    props: {
        environment: {
            type: String,
            required: true
        }
    },
    data() {
        return {
            isActive: false,
            isLoading: false,
            webhookId: null,
            error: null,
        };
    },
    created() {
        this.fetchStatus();
    },
    methods: {
        async fetchStatus() {
            this.isLoading = true;
            try {
                const response = await fetch(`/squarepayments/webhook/status?environment=${this.environment}`);
                const data = await response.json();
                this.isActive = data.active;
                this.webhookId = data.webhookId;
                this.error = null;
            } catch {
                this.error = 'Failed to fetch webhook status.';
            } finally {
                this.isLoading = false;
            }
        },
        async activateWebhook() {
            this.isLoading = true;
            try {
                const response = await fetch(`/squarepayments/webhook/create?environment=${this.environment}`, { method: 'POST' });
                const data = await response.json();
                if (data.success) {
                    this.isActive = true;
                    this.webhookId = data.webhookId;
                    this.error = null;
                } else {
                    this.error = data.message || 'Failed to create webhook.';
                }
            } catch {
                this.error = 'Failed to create webhook.';
            } finally {
                this.isLoading = false;
            }
        },
        async deactivateWebhook() {
            this.isLoading = true;
            try {
                const response = await fetch(`/squarepayments/webhook/delete?environment=${this.environment}`, { method: 'POST' });
                const data = await response.json();
                if (data.success) {
                    this.isActive = false;
                    this.webhookId = null;
                    this.error = null;
                } else {
                    this.error = data.message || 'Failed to delete webhook.';
                }
            } catch {
                this.error = 'Failed to delete webhook.';
            } finally {
                this.isLoading = false;
            }
        }
    }
});
