const ApiService = Shopware.Classes.ApiService;

class SquareApiTestService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = 'squarepayments/api-test') {
        super(httpClient, loginService, apiEndpoint);
        this.name = 'squareApiTestService';
    }

    check(payload) {
        return this.httpClient.post(
            `${this.getApiBasePath()}/check`,
            payload,
            {
                headers: this.getBasicHeaders(),
            }
        ).then(response => response.data);
    }

    getApiBasePath() {
        return this.apiEndpoint.replace(/\/$/, '');
    }
}

export default SquareApiTestService;

