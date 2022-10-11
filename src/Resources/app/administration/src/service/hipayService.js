
const ApiService = Shopware.Classes.ApiService;
const { Application } = Shopware;

/**
 * Service with HiPay endpoint calls
 */
class ApiHiPay extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = 'hipay') {
        super(httpClient, loginService, apiEndpoint);
        this.headers = this.getBasicHeaders({});
    }

    validateConfig(values) {
        const headers = this.getBasicHeaders({});

        return this.httpClient.post(`/_action/${this.getApiBasePath()}/checkAccess`, values, { headers })
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }
}

Application.addServiceProvider('hipayService', (container) => {
    const initContainer = Application.getContainer('init');
    return new ApiHiPay(initContainer.httpClient, container.loginService);
});