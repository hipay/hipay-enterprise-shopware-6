import template from './sw-order-detail.html.twig';
import './sw-order-detail.scss';

/**
 * Order detail component
 */
Shopware.Component.override('sw-order-detail', {
  template,
  inject: ['hipayService'],
  data() {
    return {
      hipayOrderData: null,
      showOrderCapture: false,
      showOrderRefund: false,
      lastTransaction: null,
      lineItems: null,
      basketColumns: [
        {
          property: 'label',
          label: this.$tc('hipay.basket.column.product'),
          multiLine: true
        },
        {
          property: 'quantity',
          label: this.$tc('hipay.basket.column.quantity')
        },
        { property: 'totalPrice', label: this.$tc('hipay.basket.column.price') }
      ],
      currency: null,
      captureAmount: null,
      refundAmount: null,
      manualCaptureAmount: null,
      manualRefundAmount: null,
      fullCapture: true,
      fullRefund: true,
      showOrderStateForCapture: false,
      showOrderStateForRefund: false,
      showOrderStateForCancel: false,
      isLoadingRequest: false
    };
  },
  watch: {
    order() {
      this.currency = this.order.currency.isoCode;
      this.lastTransaction = this.order.transactions.last();
      this.hipayOrderData = this.order.extensions?.hipayOrder;

      // Set lineItems from order & add currentQuantity field to lineItems
      const lineItems = JSON.parse(JSON.stringify(this.order.lineItems));

      if (this.order.shippingCosts.totalPrice > 0) {
        // Add a shipping item to line items
        lineItems.push({
          label: this.$tc('hipay.basket.column.shipping'),
          quantity: 1,
          totalPrice: this.order.shippingCosts.totalPrice,
          unitPrice: this.order.shippingCosts.totalPrice,
          shipping: true
        });
      }

      for (const index in lineItems) {
        lineItems[index].currentQuantity = lineItems[index].quantity;
      }
      this.lineItems = lineItems;
    }
  },
  computed: {
    orderCriteria() {
      const criteria = this.$super('orderCriteria');

      criteria.addAssociation('hipayOrder');
      criteria.addAssociation('hipayOrder.captures');
      criteria.addAssociation('hipayOrder.refunds');
      criteria.addAssociation('hipayOrder.statusFlows');

      return criteria;
    },
    showOnHipayMethod() {
      return /hipay/.test(
        this.lastTransaction?.paymentMethod?.formattedHandlerIdentifier
      );
    },
    canCancel() {
      return ['authorized'].includes(
        this.lastTransaction?.stateMachineState?.technicalName
      );
    },
    canCapture() {
      return (
        ['refunded_partially', 'paid_partially', 'authorized'].includes(
          this.lastTransaction?.stateMachineState?.technicalName
        ) && this.orderAmount > this.capturedAmountInProgress
      );
    },
    canPartialCapture() {
      return (
        this.canCapture &&
        this.lastTransaction?.paymentMethod?.extensions?.hipayConfig
          ?.allowPartialCapture !== false
      );
    },
    canRefund() {
      return (
        ['paid_partially', 'paid', 'refunded_partially'].includes(
          this.lastTransaction?.stateMachineState?.technicalName
        ) && this.capturedAmount > this.refundedAmountInProgress
      );
    },
    canPartialRefund() {
      return (
        this.canRefund &&
        this.lastTransaction?.paymentMethod?.extensions?.hipayConfig
          ?.allowPartialRefund !== false
      );
    },
    orderBasket() {
      // Returns lineItems as source to data grid & show currency next to totalPrice
      for (const lineItem of this.lineItems) {
        lineItem.totalPrice = this.formatCurrency(
          lineItem.unitPrice * lineItem.currentQuantity
        );
        lineItem.editable = this.canPartialCapture;
      }

      return this.lineItems;
    },
    orderAmount() {
      return this.order.amountTotal;
    },
    capturedAmount() {
      return this.hipayOrderData.capturedAmount;
    },
    capturedAmountInProgress() {
      return this.hipayOrderData.capturedAmountInProgress;
    },
    remainingCaptureAmount() {
      return Number(
        (this.orderAmount - this.capturedAmountInProgress).toFixed(2)
      );
    },
    refundedAmountInProgress() {
      return this.hipayOrderData.refundedAmountInProgress;
    },
    remainingRefundAmount() {
      return Number(
        (this.capturedAmount - this.refundedAmountInProgress).toFixed(2)
      );
    },
    getCaptureAmount() {
      return this.captureAmount ?? this.manualCaptureAmount;
    },
    getRefundAmount() {
      return this.refundAmount ?? this.manualRefundAmount;
    },
    isInvalidFullCaptureAmount() {
      return this.remainingCaptureAmount <= 0;
    },
    isInvalidCaptureAmount() {
      return !this.getCaptureAmount || this.isInvalidFullCaptureAmount;
    },
    isInvalidFullRefundAmount() {
      return this.remainingRefundAmount <= 0;
    },
    isInvalidRefundAmount() {
      return !this.getRefundAmount || this.isInvalidFullRefundAmount;
    },
    captureAmountPlaceholder() {
      return this.captureAmount > this.remainingCaptureAmount
        ? this.remainingCaptureAmount
        : this.captureAmount;
    },
    refundAmountPlaceholder() {
      return this.refundAmount > this.remainingRefundAmount
        ? this.remainingRefundAmount
        : this.refundAmount;
    }
  },
  methods: {
    formatCurrency(number) {
      return this.hipayService
        .getCurrencyFormater(this.currency)
        .format(number);
    },
    openCapture() {
      this.showOrderCapture = true;
    },
    openRefund() {
      this.showOrderRefund = true;
    },
    openCancel() {
      this.showOrderStateForCancel = true;
    },
    closeOrderCaptureModal() {
      this.showOrderCapture = false;
      this.captureAmount = null;
      this.manualCaptureAmount = null;

      // Reset currentQuantity to lineItems
      for (const index in this.lineItems) {
        this.lineItems[index].currentQuantity = this.lineItems[index].quantity;
      }
    },
    closeOrderRefundModal() {
      this.showOrderRefund = false;
      this.refundAmount = null;
      this.manualRefundAmount = null;

      // Reset currentQuantity to lineItems
      for (const index in this.lineItems) {
        this.lineItems[index].currentQuantity = this.lineItems[index].quantity;
      }
    },
    closeCancelModal() {
      this.showOrderStateForCancel = false;
    },
    onSelectProductForCapture(selections) {
      // Calcul capture amount according to selected products + current quantity
      let captureAmount = 0;
      for (const key in selections) {
        if (this.$refs.basket.isSelected(selections[key].id)) {
          captureAmount +=
            selections[key].unitPrice * selections[key].currentQuantity;
        }
      }
      this.captureAmount = captureAmount || null;
    },
    onSelectProductForRefund(selections) {
      // Calcul refund amount according to selected products + current quantity
      let refundAmount = 0;
      for (const key in selections) {
        if (this.$refs.basket.isSelected(selections[key].id)) {
          refundAmount +=
            selections[key].unitPrice * selections[key].currentQuantity;
        }
      }
      this.refundAmount = refundAmount || null;
    },
    selectAllProducts() {
      this.$refs.basket.selectAll(true);
    },
    isProductSelectable(item) {
      return item.good || item.shipping;
    },
    onManualCaptureAmount(amount) {
      this.manualCaptureAmount = amount;
    },
    onManualRefundAmount(amount) {
      this.manualRefundAmount = amount;
    },
    onQuantityChangeForCapture(val, product) {
      // Change current quantity of a lineItem & trigger selectProduct event
      const itemIndex = this.lineItems.findIndex(
        lineItem => lineItem.id === product.id
      );
      if (itemIndex >= 0) {
        this.lineItems[itemIndex].currentQuantity = val;
      }
      this.onSelectProductForCapture(this.lineItems);
    },
    onQuantityChangeForRefund(val, product) {
      // Change current quantity of a lineItem & trigger selectProduct event
      const itemIndex = this.lineItems.findIndex(
        lineItem => lineItem.id === product.id
      );
      if (itemIndex >= 0) {
        this.lineItems[itemIndex].currentQuantity = val;
      }
      this.onSelectProductForRefund(this.lineItems);
    },
    captureOrder() {
      // Partial capture if capture amount is different to remaining amount
      if (
        this.$refs.captureAmount.currentValue !== this.remainingCaptureAmount
      ) {
        this.fullCapture = false;
      }
      this.showOrderStateForCapture = true;
    },
    fullCaptureOrder() {
      this.showOrderStateForCapture = true;
    },
    refundOrder() {
      // Partial refund if refund amount is different to remaining amount
      if (this.$refs.refundAmount.currentValue !== this.remainingRefundAmount) {
        this.fullRefund = false;
      }
      this.showOrderStateForRefund = true;
    },
    fullRefundOrder() {
      this.showOrderStateForRefund = true;
    },
    closeOrderStateModal() {
      this.showOrderStateForCapture = false;
      this.showOrderStateForRefund = false;
    },
    makeCancel() {
      this.isLoadingRequest = true;

      // Call HiPay API endpoint
      this.hipayService
        .cancelTransaction(this.hipayOrderData)
        .then(response => {
          if (!response.success) {
            throw new Error(response.message);
          }

          this.createNotificationSuccess({
            title: this.$tc('hipay.notification.cancel.title'),
            message: this.$tc('hipay.notification.cancel.success')
          });

          // Close modals
          this.showOrderStateForCancel = false;
        })
        .catch(() => {
          this.createNotificationError({
            title: this.$tc('hipay.notification.capture.title'),
            message: this.$tc('hipay.notification.capture.failure')
          });
        })
        .finally(() => (this.isLoadingRequest = false));
    },
    makeCapture() {
      this.isLoadingRequest = true;

      // Call HiPay API endpoint
      this.hipayService
        .captureTransaction(
          this.hipayOrderData,
          this.fullCapture
            ? this.remainingCaptureAmount
            : this.$refs.captureAmount.currentValue ??
                this.captureAmountPlaceholder
        )
        .then(response => {
          if (!response.success) {
            throw new Error(response.message);
          }

          this.onSaveEdits();

          this.createNotificationSuccess({
            title: this.$tc('hipay.notification.capture.title'),
            message: this.$tc('hipay.notification.capture.success')
          });

          // Close modals
          this.showOrderStateForCapture = false;
          this.$nextTick(() => {
            // Wait until previous modal finish rendering
            this.showOrderCapture = false;
          });
        })
        .catch(() => {
          this.createNotificationError({
            title: this.$tc('hipay.notification.capture.title'),
            message: this.$tc('hipay.notification.capture.failure')
          });
        })
        .finally(() => (this.isLoadingRequest = false));
    },
    makeRefund() {
      this.isLoadingRequest = true;

      // Call HiPay API endpoint
      this.hipayService
        .refundTransaction(
          this.hipayOrderData,
          this.fullRefund
            ? this.remainingRefundAmount
            : this.$refs.refundAmount.currentValue ??
                this.refundAmountPlaceholder
        )
        .then(response => {
          if (!response.success) {
            throw new Error(response.message);
          }

          this.onSaveEdits();

          this.createNotificationSuccess({
            title: this.$tc('hipay.notification.refund.title'),
            message: this.$tc('hipay.notification.refund.success')
          });

          // Close modals
          this.showOrderStateForRefund = false;
          this.$nextTick(() => {
            // Wait until previous modal finish rendering
            this.showOrderRefund = false;
          });
        })
        .catch(() => {
          this.createNotificationError({
            title: this.$tc('hipay.notification.refund.title'),
            message: this.$tc('hipay.notification.refund.failure')
          });
        })
        .finally(() => (this.isLoadingRequest = false));
    }
  }
});
