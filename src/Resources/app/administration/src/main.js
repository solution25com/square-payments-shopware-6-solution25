import './component/square-api-test';
import './component/square-webhook-manager';
import './module/extension/sw-order/view/sw-order-detail-general';
import SquareApiTestService from './service/square-api-test.service';

Shopware.Service().register('squareApiTestService', () => {
    return new SquareApiTestService(
        Shopware.Application.getContainer('init').httpClient,
        Shopware.Service('loginService')
    );
});