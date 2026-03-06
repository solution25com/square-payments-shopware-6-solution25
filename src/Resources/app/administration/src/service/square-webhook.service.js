const ApiService = Shopware.Classes.ApiService;

class SquareWebhookService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = '_action/squarepayments/webhook') {
        super(httpClient, loginService, apiEndpoint);
        this.name = 'squareWebhookService';
    }

    status(environment) {
        return this.httpClient
            .get(`${this.getApiBasePath()}/status?environment=${encodeURIComponent(environment)}`, {
                headers: this.getBasicHeaders(),
            })
            .then((response) => response.data);
    }

    create(environment) {
        return this.httpClient
            .post(`${this.getApiBasePath()}/create`, { environment }, {
                headers: this.getBasicHeaders(),
            })
            .then((response) => response.data);
    }

    delete(environment) {
        return this.httpClient
            .post(`${this.getApiBasePath()}/delete`, { environment }, {
                headers: this.getBasicHeaders(),
            })
            .then((response) => response.data);
    }
}

export default SquareWebhookService;
