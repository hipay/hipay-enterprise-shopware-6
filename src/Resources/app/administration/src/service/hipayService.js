const { Application, Classes } = Shopware;
const ApiService = Classes.ApiService;

/**
 * Service with HiPay endpoint calls
 */
class ApiHiPay extends ApiService {
  constructor(httpClient, loginService, apiEndpoint = 'hipay') {
    super(httpClient, loginService, apiEndpoint);
    this.headers = this.getBasicHeaders({});
  }

  getCurrencyFormater(currency) {
    return new Intl.NumberFormat(
        localStorage.getItem('sw-admin-locale'),
        { style: 'currency', currency }
    );
  }

  validateConfig(values) {
    const headers = this.getBasicHeaders({});

    return this.httpClient
      .post(`/_action/${this.getApiBasePath()}/checkAccess`, values, {
        headers
      })
      .then((response) => {
        return ApiService.handleResponse(response);
      });
  }

  cancelTransaction(hipayOrder) {
    const headers = this.getBasicHeaders({});

    return this.httpClient
      .post(
        `/_action/${this.getApiBasePath()}/cancel`,
        { hipayOrder: JSON.stringify(hipayOrder) },
        { headers }
      )
      .then((response) => {
        return ApiService.handleResponse(response);
      });
  }

  captureTransaction(hipayOrder, amount) {
    const headers = this.getBasicHeaders({});

    return this.httpClient
      .post(
        `/_action/${this.getApiBasePath()}/capture`,
        {
          hipayOrder: JSON.stringify(hipayOrder),
          amount
        },
        { headers }
      )
      .then((response) => {
        return ApiService.handleResponse(response);
      });
  }

  refundTransaction(hipayOrder, amount) {
    const headers = this.getBasicHeaders({});

    return this.httpClient
      .post(
        `/_action/${this.getApiBasePath()}/refund`,
        {
          hipayOrder: JSON.stringify(hipayOrder),
          amount
        },
        { headers }
      )
      .then((response) => {
        return ApiService.handleResponse(response);
      });
  }


}

Application.addServiceProvider('hipayService', (container) => {
  const initContainer = Application.getContainer('init');
  return new ApiHiPay(initContainer.httpClient, container.loginService);
});
