import template from './square-api-test.html.twig';

const { Component, Mixin } = Shopware;

Component.register('square-api-test', {
    template,
    inject: ['squareApiTestService'],

    props: {
        label: String,
        environment: {
            type: String,
            required: true
        }
    },

    mixins: [Mixin.getByName('notification')],

    data() {
        return {
            isLoading: false,
            isSaveSuccessful: false,
        };
    },

    computed: {
        pluginConfig() {
            let $parent = this.$parent;
            while ($parent.actualConfigData === undefined) {
                $parent = $parent.$parent;
            }
            return $parent.actualConfigData.null;
        },
    },

    methods: {
        saveFinish() {
            this.isSaveSuccessful = false;
        },
        getCurrentSalesChannelId() {
            let $parent = this.$parent;
            while ($parent.currentSalesChannelId === undefined) {
                $parent = $parent.$parent;
            }
            return $parent.currentSalesChannelId;
        },
        async check() {
            if (!this.environment) {
                this.createNotificationError({
                    title: 'Square API Test',
                    message: 'Environment is missing. Please check the plugin config.xml component configuration.',
                });
                return;
            }

            this.isLoading = true;
            const payload = {
                ...this.pluginConfig,
                salesChannelId: this.getCurrentSalesChannelId(),
                environment: this.environment
            };
            try {
                const result = await this.squareApiTestService.check(payload);
                if (result.success) {
                    this.isSaveSuccessful = true;
                    this.createNotificationSuccess({
                        title: this.$tc('Square.apiTest.success.title'),
                        message: this.$tc('Square.apiTest.success.message'),
                    });
                } else {
                    this.createNotificationError({
                        title: this.$tc('Square.apiTest.error.title'),
                        message: result.message || this.$tc('Square.apiTest.error.message'),
                    });
                }
            } catch (error) {
                this.createNotificationError({
                    title: 'Square API Test',
                    message: error.message || 'Connection failed!',
                });
            } finally {
                this.isLoading = false;
            }
        },
    },
});
