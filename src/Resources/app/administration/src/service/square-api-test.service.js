const ApiService = Shopware.Classes.ApiService;

class SquareApiTestService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = '_action/squarepayments/api-test') {
        super(httpClient, loginService, apiEndpoint);
        this.name = 'squareApiTestService';
    }

    check(payload) {
        return this.httpClient
            .post(`${this.getApiBasePath()}/check`, payload, {
                headers: this.getBasicHeaders(),
            })
            .then((response) => response.data);
    }
}

export default SquareApiTestService;

