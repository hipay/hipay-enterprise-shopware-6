!function(e){var t={};function n(r){if(t[r])return t[r].exports;var a=t[r]={i:r,l:!1,exports:{}};return e[r].call(a.exports,a,a.exports,n),a.l=!0,a.exports}n.m=e,n.c=t,n.d=function(e,t,r){n.o(e,t)||Object.defineProperty(e,t,{enumerable:!0,get:r})},n.r=function(e){"undefined"!=typeof Symbol&&Symbol.toStringTag&&Object.defineProperty(e,Symbol.toStringTag,{value:"Module"}),Object.defineProperty(e,"__esModule",{value:!0})},n.t=function(e,t){if(1&t&&(e=n(e)),8&t)return e;if(4&t&&"object"==typeof e&&e&&e.__esModule)return e;var r=Object.create(null);if(n.r(r),Object.defineProperty(r,"default",{enumerable:!0,value:e}),2&t&&"string"!=typeof e)for(var a in e)n.d(r,a,function(t){return e[t]}.bind(null,a));return r},n.n=function(e){var t=e&&e.__esModule?function(){return e.default}:function(){return e};return n.d(t,"a",t),t},n.o=function(e,t){return Object.prototype.hasOwnProperty.call(e,t)},n.p="/bundles/hipaypaymentplugin/",n(n.s="3YVu")}({"3YVu":function(e,t,n){"use strict";n.r(t);n("PfVt"),n("xlli");var r=n("yAkZ");n("IHpg");Shopware.Component.register("hipay-help-bloc",{template:'<div class="hipay-help">\n  <div class="hipay-help-bloc">\n    <div class="hipay-help-bloc-item">\n      <sw-icon name="default-documentation-file"></sw-icon>\n      <a href="https://support.hipay.com"\n        class="sw-button sw-button--primary"\n        target="_blank"\n        rel="noopener"\n        :title="$tc(\'hipay.config.help.manual\')">\n        {{ $tc(\'hipay.config.help.manual\') }}\n      </a>\n    </div>\n\n    <div class="hipay-help-bloc-item">\n      <sw-icon name="default-text-code"></sw-icon>\n      <a href="https://github.com/hipay/hipay-enterprise-shopware-6"\n        class="sw-button sw-button--primary"\n        rel="noopener"\n        target="_blank"\n        :title="$tc(\'hipay.config.help.manual\')">\n        {{ $tc(\'hipay.config.help.github\') }}\n      </a>\n    </div>\n\n    <div class="hipay-help-bloc-item">\n      <sw-icon name="default-action-cloud-upload"></sw-icon>\n      <p class="version-text">\n        {{ $tc(\'hipay.config.help.version\') }} : {{ version }}\n      </p>\n    </div>\n  </div>\n</div>\n',data:function(){return{version:r.version}}});Shopware.Component.register("hipay-help-info",{template:'<sw-alert>\n  <div v-html="$tc(text)"></div>\n</sw-alert>\n',data:function(){return{text:this.$parent.bind.value}}});var a=Shopware,i=a.Component,o=a.Mixin;i.register("hipay-check-server-access",{template:'<sw-button-process style="margin-top: 20px"\n  class="sw-button--primary"\n  :disabled="isLoading"\n  :isLoading="isLoading"\n  :processSuccess="success"\n  @process-finish="completeSucess"\n  @click="validateConfig">\n  {{ $tc(\'hipay.config.checkAccess.button\') }}\n</sw-button-process>\n',inject:["hipayService"],mixins:[o.getByName("notification")],props:{value:{required:!1}},data:function(){return{isLoading:!1,success:!1}},methods:{completeSucess:function(){this.sucess=!1},validateConfig:function(e){var t=this;this.isLoading=!0;var n=this.$tc("hipay.config.checkAccess.title");this.hipayService.validateConfig(this.getConfig()).then((function(e){if(!e.success)throw new Error(e.message);t.createNotificationSuccess({title:n,message:t.$tc("hipay.config.checkAccess.success")}),t.success=!0})).catch((function(e){t.createNotificationError({title:n,message:e.message||t.$tc("hipay.config.checkAccess.failure")})})).finally((function(){return t.isLoading=!1}))},getConfig:function(){for(var e=this.$parent;!e.hasOwnProperty("actualConfigData");)e=e.$parent;var t=e.currentSalesChannelId,n=e.actualConfigData;return Object.assign({},n.null,n[t],{environment:this.$parent.bind.value})}}});Shopware.Component.register("hipay-settings-cards-selector",{template:'<sw-card position-identifier="sw-settings-payment-detail-content"\n  title="Credit cards available"\n  :is-loading="isLoading">\n  <sw-multi-select :label="$tc(\'hipay.config.creditCards.choice\')"\n    :options="availableCards"\n    :value="paymentMethod.customFields?.cards || []"\n    @change="updateCardsValue"></sw-multi-select>\n</sw-card>\n',props:{isLoading:{type:Boolean,required:!0},paymentMethod:{type:Object,required:!0}},data:function(){return{availableCards:[{label:"Carte Bancaire",value:"cb"},{label:"VISA",value:"visa"},{label:"MasterCard",value:"mastercard"},{label:"Amercian Express",value:"american-express"},{label:"Bancontact / Mister Cash",value:"bcmc"},{label:"Maestro",value:"maestro"}]}},methods:{updateCardsValue:function(e){null===this.paymentMethod.customFields?this.paymentMethod.customFields={cards:e}:this.paymentMethod.customFields.cards=e}}});n("EJ7b");Shopware.Component.override("sw-data-grid",{template:'{% block sw_data_grid_columns_render_value %}\n  <template v-if="column.property === \'quantity\'">\n    <sw-number-field class="quantityInp"\n      numberType="int"\n      :min="1"\n      :max="item.quantity"\n      :value="item.quantity"\n      :disabled="!item.good || item.shipping"\n      @change="onValueChange(item, $event)"></sw-number-field>\n  </template>\n  <template v-else>\n    <span class="sw-data-grid__cell-value">\n      {{ renderColumn(item, column) }}\n    </span>\n  </template>\n{% endblock %}\n',methods:{onValueChange:function(e,t){this.$emit("quantity-change",t,e)}}});n("fk7Z");function s(e,t){var n=Object.keys(e);if(Object.getOwnPropertySymbols){var r=Object.getOwnPropertySymbols(e);t&&(r=r.filter((function(t){return Object.getOwnPropertyDescriptor(e,t).enumerable}))),n.push.apply(n,r)}return n}function u(e){for(var t=1;t<arguments.length;t++){var n=null!=arguments[t]?arguments[t]:{};t%2?s(Object(n),!0).forEach((function(t){c(e,t,n[t])})):Object.getOwnPropertyDescriptors?Object.defineProperties(e,Object.getOwnPropertyDescriptors(n)):s(Object(n)).forEach((function(t){Object.defineProperty(e,t,Object.getOwnPropertyDescriptor(n,t))}))}return e}function c(e,t,n){return t in e?Object.defineProperty(e,t,{value:n,enumerable:!0,configurable:!0,writable:!0}):e[t]=n,e}Shopware.Data.Criteria;Shopware.Component.override("sw-order-detail-base",{template:'{% block sw_order_detail_customer_comment_card %}\n  {% parent %}\n  <sw-card v-if="order.extensions?.hipayOrder?.statusFlows?.length"\n    class="card-no-padding"\n    position-identifier="sw-settings-payment-detail-content"\n    :title="getTitle"\n    :is-loading="isLoading">\n    <sw-data-grid :dataSource="datasource"\n      :columns="columns"\n      :showSelection="false"\n      :showActions="false"></sw-data-grid>\n  </sw-card>\n{% endblock %}\n',inject:["hipayService"],computed:{orderCriteria:function(){var e=this.$super("orderCriteria");return e.addAssociation("hipayOrder"),e.addAssociation("hipayOrder.captures"),e.addAssociation("hipayOrder.refunds"),e.addAssociation("hipayOrder.statusFlows"),e},datasource:function(){var e=this;return this.order.extensions.hipayOrder.statusFlows.sort((function(e,t){return new Date(t.createdAt).getTime()-new Date(e.createdAt).getTime()})).map((function(t){return u(u({},t),{},{description:t.name?"".concat(t.name," (").concat(t.code,")"):t.code,createdAt:new Date(t.createdAt).toLocaleString(),amount:e.hipayService.getCurrencyFormater(e.order.currency.isoCode).format(t.amount)})}))},columns:function(){return[{property:"description",label:"Status"},{property:"createdAt",label:"Date"},{property:"message",label:"Message"},{property:"amount",label:"Amount"}]},getTitle:function(){return this.$t("hipay.transaction.title")+" #"+this.order.extensions.hipayOrder.transactionReference}},watch:{order:function(){this.$root.$emit("order-loaded",this.order)}}});n("C0+k");function l(e,t){var n="undefined"!=typeof Symbol&&e[Symbol.iterator]||e["@@iterator"];if(!n){if(Array.isArray(e)||(n=function(e,t){if(!e)return;if("string"==typeof e)return d(e,t);var n=Object.prototype.toString.call(e).slice(8,-1);"Object"===n&&e.constructor&&(n=e.constructor.name);if("Map"===n||"Set"===n)return Array.from(e);if("Arguments"===n||/^(?:Ui|I)nt(?:8|16|32)(?:Clamped)?Array$/.test(n))return d(e,t)}(e))||t&&e&&"number"==typeof e.length){n&&(e=n);var r=0,a=function(){};return{s:a,n:function(){return r>=e.length?{done:!0}:{done:!1,value:e[r++]}},e:function(e){throw e},f:a}}throw new TypeError("Invalid attempt to iterate non-iterable instance.\nIn order to be iterable, non-array objects must have a [Symbol.iterator]() method.")}var i,o=!0,s=!1;return{s:function(){n=n.call(e)},n:function(){var e=n.next();return o=e.done,e},e:function(e){s=!0,i=e},f:function(){try{o||null==n.return||n.return()}finally{if(s)throw i}}}}function d(e,t){(null==t||t>e.length)&&(t=e.length);for(var n=0,r=new Array(t);n<t;n++)r[n]=e[n];return r}Shopware.Component.override("sw-order-detail",{template:'{% block sw_order_detail_actions_slot_smart_bar_actions %}\n  <template v-if="showOnHipayMethod">\n    <sw-button variant="contrast"\n      v-if="canCapture"\n      @click="openCapture"\n      :disabled="!acl.can(\'order.editor\')">\n      {{ $tc(\'hipay.action.capture\') }}\n    </sw-button>\n    <sw-button variant="contrast"\n      v-if="canRefund"\n      @click="openRefund"\n      :disabled="!acl.can(\'order.editor\')">\n      {{ $tc(\'hipay.action.refund\') }}\n    </sw-button>\n    {% parent %}\n    <sw-modal :title="$tc(\'hipay.action.capture\')"\n      v-if="showOrderCapture"\n      v-show="!showOrderStateForCapture"\n      @modal-close="closeOrderCaptureModal">\n      <sw-data-grid ref="basket"\n        :dataSource="orderBasket"\n        :columns="basketColumns"\n        :showActions="false"\n        :isRecordSelectable="isProductSelectable"\n        @selection-change="onSelectProductForCapture"\n        @quantity-change="onQuantityChangeForCapture"\n        @hook:mounted="selectAllProducts"></sw-data-grid>\n      <br />\n      <div class="captureBtnGrp">\n        <sw-text-field :label="$tc(\'hipay.field.order_amount\')"\n          :value="formatCurrency(orderAmount)"\n          :disabled="true"></sw-text-field>\n        <sw-text-field :label="$tc(\'hipay.field.captured_amount\')"\n          :value="formatCurrency(capturedAmountInProgress)"\n          :disabled="true"></sw-text-field>\n      </div>\n      <div class="captureBtnGrp">\n        <sw-text-field :label="$tc(\'hipay.field.remaining_amount\')"\n          :value="formatCurrency(remainingCaptureAmount)"\n          :disabled="true"></sw-text-field>\n        <sw-number-field ref="captureAmount"\n          :label="$tc(\'hipay.field.capture_amount\')"\n          :min="0.01"\n          :max="remainingCaptureAmount"\n          :value="getCaptureAmount"\n          :allowEmpty="true"\n          :required="true"\n          :isInvalid="isInvalidCaptureAmount"\n          @change="onManualCaptureAmount"\n          :placeholder="captureAmountPlaceholder ? formatCurrency(captureAmountPlaceholder) : null"></sw-number-field>\n      </div>\n      <template slot="modal-footer">\n        <sw-button @click="closeOrderCaptureModal">\n          {{ $tc(\'hipay.action.cancel\') }}\n        </sw-button>\n        <sw-button variant="primary"\n          @click="captureOrder"\n          :disabled="isInvalidCaptureAmount">\n          {{ $tc(\'hipay.action.capture\') }}\n        </sw-button>\n        <sw-button variant="primary"\n          @click="fullCaptureOrder"\n          :disabled="isInvalidFullCaptureAmount">\n          {{ $tc(\'hipay.action.full_capture\') }}\n        </sw-button>\n      </template>\n    </sw-modal>\n    <sw-order-state-change-modal :order="orderData"\n      :isLoading="isLoadingRequest"\n      technicalName=""\n      v-if="showOrderStateForCapture"\n      @page-leave="closeOrderStateModal"\n      @page-leave-confirm="makeCapture"></sw-order-state-change-modal>\n\n    <sw-modal :title="$tc(\'hipay.action.refund\')"\n      v-if="showOrderRefund"\n      v-show="!showOrderStateForRefund"\n      @modal-close="closeOrderRefundModal">\n      <sw-data-grid ref="basket"\n        :dataSource="orderBasket"\n        :columns="basketColumns"\n        :showActions="false"\n        :isRecordSelectable="isProductSelectable"\n        @selection-change="onSelectProductForRefund"\n        @quantity-change="onQuantityChangeForRefund"\n        @hook:mounted="selectAllProducts"></sw-data-grid>\n      <br />\n      <div class="refundBtnGrp">\n        <sw-text-field :label="$tc(\'hipay.field.captured_amount\')"\n          :value="formatCurrency(capturedAmount)"\n          :disabled="true"></sw-text-field>\n        <sw-text-field :label="$tc(\'hipay.field.refunded_amount\')"\n          :value="formatCurrency(refundedAmountInProgress)"\n          :disabled="true"></sw-text-field>\n      </div>\n      <div class="refundBtnGrp">\n        <sw-text-field :label="$tc(\'hipay.field.remaining_amount\')"\n          :value="formatCurrency(remainingRefundAmount)"\n          :disabled="true"></sw-text-field>\n        <sw-number-field ref="refundAmount"\n          :label="$tc(\'hipay.field.refund_amount\')"\n          :min="0.01"\n          :max="remainingRefundAmount"\n          :value="getRefundAmount"\n          :allowEmpty="true"\n          :required="true"\n          :isInvalid="isInvalidRefundAmount"\n          @change="onManualRefundAmount"\n          :placeholder="refundAmountPlaceholder ? formatCurrency(refundAmountPlaceholder) : null"></sw-number-field>\n      </div>\n      <template slot="modal-footer">\n        <sw-button @click="closeOrderRefundModal">\n          {{ $tc(\'hipay.action.cancel\') }}\n        </sw-button>\n        <sw-button variant="primary"\n          @click="refundOrder"\n          :disabled="isInvalidRefundAmount">\n          {{ $tc(\'hipay.action.refund\') }}\n        </sw-button>\n        <sw-button variant="primary"\n          @click="fullRefundOrder"\n          :disabled="isInvalidFullRefundAmount">\n          {{ $tc(\'hipay.action.full_refund\') }}\n        </sw-button>\n      </template>\n      <sw-order-state-change-modal :order="orderData"\n        :isLoading="isLoadingRequest"\n        technicalName=""\n        v-if="showOrderStateForRefund"\n        @page-leave="closeOrderStateModal"\n        @page-leave-confirm="makeRefund"></sw-order-state-change-modal>\n    </sw-modal>\n  </template>\n{% endblock %}\n',inject:["hipayService"],data:function(){return{orderData:null,hipayOrderData:null,showOrderCapture:!1,showOrderRefund:!1,lastTransaction:null,lineItems:null,basketColumns:[{property:"label",label:this.$tc("hipay.basket.column.product"),multiLine:!0},{property:"quantity",label:this.$tc("hipay.basket.column.quantity")},{property:"totalPrice",label:this.$tc("hipay.basket.column.price")}],currency:null,captureAmount:null,refundAmount:null,manualCaptureAmount:null,manualRefundAmount:null,fullCapture:!0,fullRefund:!0,showOrderStateForCapture:!1,showOrderStateForRefund:!1,isLoadingRequest:!1}},computed:{showOnHipayMethod:function(){var e,t;return/hipay/.test(null===(e=this.lastTransaction)||void 0===e||null===(t=e.paymentMethod)||void 0===t?void 0:t.formattedHandlerIdentifier)},canCapture:function(){var e,t;return["paid_partially","authorized"].includes(null===(e=this.lastTransaction)||void 0===e||null===(t=e.stateMachineState)||void 0===t?void 0:t.technicalName)},canRefund:function(){var e,t;return["paid_partially","paid","refunded_partially"].includes(null===(e=this.lastTransaction)||void 0===e||null===(t=e.stateMachineState)||void 0===t?void 0:t.technicalName)},orderBasket:function(){var e,t=l(this.lineItems);try{for(t.s();!(e=t.n()).done;){var n=e.value;n.totalPrice=this.formatCurrency(n.unitPrice*n.currentQuantity)}}catch(e){t.e(e)}finally{t.f()}return this.lineItems},orderAmount:function(){return this.orderData.amountTotal},capturedAmount:function(){return this.hipayOrderData.capturedAmount},capturedAmountInProgress:function(){return this.hipayOrderData.capturedAmountInProgress},remainingCaptureAmount:function(){return Number((this.orderAmount-this.capturedAmountInProgress).toFixed(2))},refundedAmountInProgress:function(){return this.hipayOrderData.refundedAmountInProgress},remainingRefundAmount:function(){return Number((this.capturedAmount-this.refundedAmountInProgress).toFixed(2))},getCaptureAmount:function(){var e;return null!==(e=this.captureAmount)&&void 0!==e?e:this.manualCaptureAmount},getRefundAmount:function(){var e;return null!==(e=this.refundAmount)&&void 0!==e?e:this.manualRefundAmount},isInvalidFullCaptureAmount:function(){return this.remainingCaptureAmount<=0},isInvalidCaptureAmount:function(){return!this.getCaptureAmount||this.isInvalidFullCaptureAmount},isInvalidFullRefundAmount:function(){return this.remainingRefundAmount<=0},isInvalidRefundAmount:function(){return!this.getRefundAmount||this.isInvalidFullRefundAmount},captureAmountPlaceholder:function(){return this.captureAmount>this.remainingCaptureAmount?this.remainingCaptureAmount:this.captureAmount},refundAmountPlaceholder:function(){return this.refundAmount>this.remainingRefundAmount?this.remainingRefundAmount:this.refundAmount}},methods:{formatCurrency:function(e){return this.hipayService.getCurrencyFormater(this.currency).format(e)},openCapture:function(){console.log(JSON.parse(JSON.stringify(this.hipayOrderData))),this.showOrderCapture=!0},openRefund:function(){console.log(JSON.parse(JSON.stringify(this.hipayOrderData))),this.showOrderRefund=!0},createdComponent:function(){this.$super("createdComponent"),this.$root.$on("order-loaded",this.orderLoaded)},destroyedComponent:function(){this.$root.$off("order-loaded",this.orderLoaded),this.$super("destroyedComponent")},orderLoaded:function(e){var t;this.orderData=e,this.currency=e.currency.isoCode,console.log(JSON.parse(JSON.stringify(this.orderData))),this.lastTransaction=this.orderData.transactions.last(),this.hipayOrderData=null===(t=this.orderData.extensions)||void 0===t?void 0:t.hipayOrder,this.hipayOrderData&&console.log(JSON.parse(JSON.stringify(this.hipayOrderData)));var n=JSON.parse(JSON.stringify(this.orderData.lineItems));for(var r in this.orderData.shippingCosts.totalPrice>0&&n.push({label:this.$tc("hipay.basket.column.shipping"),quantity:1,totalPrice:this.orderData.shippingCosts.totalPrice,unitPrice:this.orderData.shippingCosts.totalPrice,shipping:!0}),n)n[r].currentQuantity=n[r].quantity;this.lineItems=n},closeOrderCaptureModal:function(){for(var e in this.showOrderCapture=!1,this.captureAmount=null,this.manualCaptureAmount=null,this.lineItems)this.lineItems[e].currentQuantity=this.lineItems[e].quantity},closeOrderRefundModal:function(){for(var e in this.showOrderRefund=!1,this.refundAmount=null,this.manualRefundAmount=null,this.lineItems)this.lineItems[e].currentQuantity=this.lineItems[e].quantity},onSelectProductForCapture:function(e){var t=0;for(var n in e)this.$refs.basket.isSelected(e[n].id)&&(t+=e[n].unitPrice*e[n].currentQuantity);this.captureAmount=t||null},onSelectProductForRefund:function(e){var t=0;for(var n in e)this.$refs.basket.isSelected(e[n].id)&&(t+=e[n].unitPrice*e[n].currentQuantity);this.refundAmount=t||null},selectAllProducts:function(){this.$refs.basket.selectAll(!0)},isProductSelectable:function(e){return e.good||e.shipping},onManualCaptureAmount:function(e){this.manualCaptureAmount=e},onManualRefundAmount:function(e){this.manualRefundAmount=e},onQuantityChangeForCapture:function(e,t){var n=this.lineItems.findIndex((function(e){return e.id===t.id}));n>=0&&(this.lineItems[n].currentQuantity=e),this.onSelectProductForCapture(this.lineItems)},onQuantityChangeForRefund:function(e,t){var n=this.lineItems.findIndex((function(e){return e.id===t.id}));n>=0&&(this.lineItems[n].currentQuantity=e),this.onSelectProductForRefund(this.lineItems)},captureOrder:function(){this.$refs.captureAmount.currentValue!==this.remainingCaptureAmount&&(this.fullCapture=!1),this.showOrderStateForCapture=!0},fullCaptureOrder:function(){this.showOrderStateForCapture=!0},refundOrder:function(){this.$refs.refundAmount.currentValue!==this.remainingRefundAmount&&(this.fullRefund=!1),this.showOrderStateForRefund=!0},fullRefundOrder:function(){this.showOrderStateForRefund=!0},closeOrderStateModal:function(){this.showOrderStateForCapture=!1,this.showOrderStateForRefund=!1},makeCapture:function(){var e,t=this;this.isLoadingRequest=!0,this.hipayService.captureTransaction(this.hipayOrderData,this.fullCapture?this.remainingCaptureAmount:null!==(e=this.$refs.captureAmount.currentValue)&&void 0!==e?e:this.captureAmountPlaceholder).then((function(e){if(!e.success)throw new Error(e.message);t.createNotificationSuccess({title:t.$tc("hipay.notification.capture.title"),message:t.$tc("hipay.notification.capture.success")}),e.captures&&(t.hipayOrderData.captures=e.captures),e.captured_amount&&(t.hipayOrderData.capturedAmountInProgress=e.captured_amount),t.showOrderStateForCapture=!1,t.$nextTick((function(){t.showOrderCapture=!1}))})).catch((function(){t.createNotificationError({title:t.$tc("hipay.notification.capture.title"),message:t.$tc("hipay.notification.capture.failure")})})).finally((function(){return t.isLoadingRequest=!1}))},makeRefund:function(){var e,t=this;this.isLoadingRequest=!0,this.hipayService.refundTransaction(this.hipayOrderData,this.fullRefund?this.remainingRefundAmount:null!==(e=this.$refs.refundAmount.currentValue)&&void 0!==e?e:this.refundAmountPlaceholder).then((function(e){if(!e.success)throw new Error(e.message);t.createNotificationSuccess({title:t.$tc("hipay.notification.refund.title"),message:t.$tc("hipay.notification.refund.success")}),e.refunds&&(t.hipayOrderData.refunds=e.refunds),e.refunded_amount&&(t.hipayOrderData.refundedAmountInProgress=e.refunded_amount),t.showOrderStateForRefund=!1,t.$nextTick((function(){t.showOrderRefund=!1}))})).catch((function(){t.createNotificationError({title:t.$tc("hipay.notification.refund.title"),message:t.$tc("hipay.notification.refund.failure")})})).finally((function(){return t.isLoadingRequest=!1}))}}});Shopware.Component.override("sw-settings-payment-detail",{template:'{% block sw_settings_payment_detail_content_card %}\n  {% parent %}\n  <template v-if="paymentMethod.handlerIdentifier == \'HiPay\\\\Payment\\\\PaymentMethod\\\\CreditCard\'">\n    <hipay-settings-cards-selector :is-loading="isLoading"\n      :paymentMethod="paymentMethod"></hipay-settings-cards-selector>\n  </template>\n{% endblock %}\n'});var p=n("yLFk"),f=n("AK4m");Shopware.Locale.extend("en-GB",p),Shopware.Locale.extend("de-DE",f)},AK4m:function(e){e.exports=JSON.parse('{"hipay":{"config":{"help":{"manual":"Online-Handbuch","github":"Meldung eines Fehler auf GitHub","version":"Versionsnummer"},"checkAccess":{"title":"HiPay Configuration","success":"API-Anmeldeinformationen testen: Die API-Anmeldeinformationen von HiPay sind gültig.","failure":"Test API Credentials : Die API Credentials von HiPay sind nicht gültig.","button":"API-Anmeldedaten testen"},"capture-help":"<b>Automatich</b>: Alle Transaktionen werden automatisch erfasst.<br/><b>Manual</b>: Alle Transaktionen werden manuell in Ihrem HiPay- oder PrestaShop-Backoffice erfasst.","operation-help":"<b>Hostet Page</b>: Der Kunde wird zu einer sicheren, von HiPay gehosteten Zahlungsseite weitergeleitet. <b>Hosted Fields</b>: Der Kunde gibt seine Bankdaten direkt auf der Website des Händlers ein, die Felder des Formulars werden jedoch von HiPay gehostet. Dieser Modus ist nur für Kreditkarten gültig.","title":{"privateKey":"Private Anmeldedaten","publicKey":"Öffentliche Anmeldedaten","notification":"Einstellungen für Benachrichtigungen"},"info":"Um Sie über Ereignisse im Zusammenhang mit Ihrem Zahlungssystem zu informieren, z. B. über eine neue Transaktion oder eine 3-D Secure-Transaktion, kann die HiPay Plattform Ihrer Anwendung eine Server-to-Server-Benachrichtigung senden. Loggen Sie sich bei HiPay Console ein, wählen Sie einen Account und klicken Sie Integration > Sicherheitseinstellungen um die geheime Passphrase abzurufen.","authenticationIndicator":"<b>3-D Secure-Authentifizierung obligatorisch :</b><br/>Zum Fortfahren ist eine Authentifizierung erforderlich (diese Authentifizierung kann entweder ein 3DSv2-Challenge-Flow oder ein reibungsloser 3DSv2-Flow sein, ohne dass eine Benutzereingabe erforderlich ist). <b>Schlägt die Authentifizierung fehl, wird die Transaktion abgelehnt und der Kunde wird nicht belastet.</b><br/><br/><b>3-D Secure-Authentifizierung, falls verfügbar :</b><br/>Wenn die Zahlungsmethode dies zulässt, wird ein Authentifizierungsprozess angefordert. Wenn die Authentifizierung jedoch fehlschlägt, wird die Transaktion nicht abgelehnt (<b>nur für den Rest der Welt</b>, da Transaktionen innerhalb der EURO-Zone PSD2 unterliegen und authentifiziert werden müssen).e. Falls von der Bank verlangt, führt der Kunde einen zusätzlichen Authentifizierungsschritt durch, damit die Belastung erfolgreich ist.","creditCards":{"choice":"Kreditkarte auswählen"}},"hosted-page":{"redirect-message":"Sie werden auf eine externe Zahlungsseite weitergeleitet. Bitte aktualisieren Sie die Seite während des Vorgangs nicht"},"hosted-fields":{"fullname":"Vollständiger Name","card-number":"Kartennummer","expiry-date":"Verfallsdatum","cvc":"CVC"},"action":{"cancel":"Absagen","capture":"Erfassung","refund":"Erstattung","full_capture":"Vollständige Erfassung","full_refund":"Vollständige Erstattung"},"basket":{"column":{"product":"Produkt","quantity":"Menge","price":"Preis","shipping":"Versandkosten"}},"field":{"capture_amount":"Erfassungsbetrag","remaining_amount":"Restbetrag","order_amount":"Bestellbetrag","captured_amount":"Erfasster Betrag","refund_amount":"Rückerstattungsbetrag","refunded_amount":"Erstatteter Betrag"},"notification":{"capture":{"title":"Transaktionserfassung","success":"Transaktion erfolgreich erfasst","failure":"Fehler bei der Transaktionserfassung"},"refund":{"title":"Transaktionsrückerstattung","success":"Transaktion erfolgreich zurückerstattet","failure":"Fehler bei der Transaktionsrückerstattung"}},"transaction":{"title":"HiPay-Transaktionslebenszyklus"}}}')},"C0+k":function(e,t,n){var r=n("n6Dk");r.__esModule&&(r=r.default),"string"==typeof r&&(r=[[e.i,r,""]]),r.locals&&(e.exports=r.locals);(0,n("SZ7m").default)("639cac67",r,!0,{})},C2Iv:function(e,t,n){},EJ7b:function(e,t,n){var r=n("UgRQ");r.__esModule&&(r=r.default),"string"==typeof r&&(r=[[e.i,r,""]]),r.locals&&(e.exports=r.locals);(0,n("SZ7m").default)("73a153b8",r,!0,{})},IHpg:function(e,t,n){var r=n("RvIq");r.__esModule&&(r=r.default),"string"==typeof r&&(r=[[e.i,r,""]]),r.locals&&(e.exports=r.locals);(0,n("SZ7m").default)("d17d5a74",r,!0,{})},PfVt:function(e,t){function n(e){return(n="function"==typeof Symbol&&"symbol"==typeof Symbol.iterator?function(e){return typeof e}:function(e){return e&&"function"==typeof Symbol&&e.constructor===Symbol&&e!==Symbol.prototype?"symbol":typeof e})(e)}function r(e,t){if(!(e instanceof t))throw new TypeError("Cannot call a class as a function")}function a(e,t){for(var n=0;n<t.length;n++){var r=t[n];r.enumerable=r.enumerable||!1,r.configurable=!0,"value"in r&&(r.writable=!0),Object.defineProperty(e,r.key,r)}}function i(e,t){return(i=Object.setPrototypeOf||function(e,t){return e.__proto__=t,e})(e,t)}function o(e){var t=function(){if("undefined"==typeof Reflect||!Reflect.construct)return!1;if(Reflect.construct.sham)return!1;if("function"==typeof Proxy)return!0;try{return Boolean.prototype.valueOf.call(Reflect.construct(Boolean,[],(function(){}))),!0}catch(e){return!1}}();return function(){var n,r=u(e);if(t){var a=u(this).constructor;n=Reflect.construct(r,arguments,a)}else n=r.apply(this,arguments);return s(this,n)}}function s(e,t){if(t&&("object"===n(t)||"function"==typeof t))return t;if(void 0!==t)throw new TypeError("Derived constructors may only return object or undefined");return function(e){if(void 0===e)throw new ReferenceError("this hasn't been initialised - super() hasn't been called");return e}(e)}function u(e){return(u=Object.setPrototypeOf?Object.getPrototypeOf:function(e){return e.__proto__||Object.getPrototypeOf(e)})(e)}var c=Shopware,l=c.Application,d=c.Classes.ApiService,p=function(e){!function(e,t){if("function"!=typeof t&&null!==t)throw new TypeError("Super expression must either be null or a function");e.prototype=Object.create(t&&t.prototype,{constructor:{value:e,writable:!0,configurable:!0}}),Object.defineProperty(e,"prototype",{writable:!1}),t&&i(e,t)}(c,e);var t,n,s,u=o(c);function c(e,t){var n,a=arguments.length>2&&void 0!==arguments[2]?arguments[2]:"hipay";return r(this,c),(n=u.call(this,e,t,a)).headers=n.getBasicHeaders({}),n}return t=c,(n=[{key:"getCurrencyFormater",value:function(e){return new Intl.NumberFormat(localStorage.getItem("sw-admin-locale"),{style:"currency",currency:e})}},{key:"validateConfig",value:function(e){var t=this.getBasicHeaders({});return this.httpClient.post("/_action/".concat(this.getApiBasePath(),"/checkAccess"),e,{headers:t}).then((function(e){return d.handleResponse(e)}))}},{key:"captureTransaction",value:function(e,t){var n=this.getBasicHeaders({});return this.httpClient.post("/_action/".concat(this.getApiBasePath(),"/capture"),{hipayOrder:JSON.stringify(e),amount:t},{headers:n}).then((function(e){return d.handleResponse(e)}))}},{key:"refundTransaction",value:function(e,t){var n=this.getBasicHeaders({});return this.httpClient.post("/_action/".concat(this.getApiBasePath(),"/refund"),{hipayOrder:JSON.stringify(e),amount:t},{headers:n}).then((function(e){return d.handleResponse(e)}))}}])&&a(t.prototype,n),s&&a(t,s),Object.defineProperty(t,"prototype",{writable:!1}),c}(d);l.addServiceProvider("hipayService",(function(e){var t=l.getContainer("init");return new p(t.httpClient,e.loginService)}))},RvIq:function(e,t,n){},SZ7m:function(e,t,n){"use strict";function r(e,t){for(var n=[],r={},a=0;a<t.length;a++){var i=t[a],o=i[0],s={id:e+":"+a,css:i[1],media:i[2],sourceMap:i[3]};r[o]?r[o].parts.push(s):n.push(r[o]={id:o,parts:[s]})}return n}n.r(t),n.d(t,"default",(function(){return h}));var a="undefined"!=typeof document;if("undefined"!=typeof DEBUG&&DEBUG&&!a)throw new Error("vue-style-loader cannot be used in a non-browser environment. Use { target: 'node' } in your Webpack config to indicate a server-rendering environment.");var i={},o=a&&(document.head||document.getElementsByTagName("head")[0]),s=null,u=0,c=!1,l=function(){},d=null,p="data-vue-ssr-id",f="undefined"!=typeof navigator&&/msie [6-9]\b/.test(navigator.userAgent.toLowerCase());function h(e,t,n,a){c=n,d=a||{};var o=r(e,t);return m(o),function(t){for(var n=[],a=0;a<o.length;a++){var s=o[a];(u=i[s.id]).refs--,n.push(u)}t?m(o=r(e,t)):o=[];for(a=0;a<n.length;a++){var u;if(0===(u=n[a]).refs){for(var c=0;c<u.parts.length;c++)u.parts[c]();delete i[u.id]}}}}function m(e){for(var t=0;t<e.length;t++){var n=e[t],r=i[n.id];if(r){r.refs++;for(var a=0;a<r.parts.length;a++)r.parts[a](n.parts[a]);for(;a<n.parts.length;a++)r.parts.push(g(n.parts[a]));r.parts.length>n.parts.length&&(r.parts.length=n.parts.length)}else{var o=[];for(a=0;a<n.parts.length;a++)o.push(g(n.parts[a]));i[n.id]={id:n.id,refs:1,parts:o}}}}function y(){var e=document.createElement("style");return e.type="text/css",o.appendChild(e),e}function g(e){var t,n,r=document.querySelector("style["+p+'~="'+e.id+'"]');if(r){if(c)return l;r.parentNode.removeChild(r)}if(f){var a=u++;r=s||(s=y()),t=w.bind(null,r,a,!1),n=w.bind(null,r,a,!0)}else r=y(),t=A.bind(null,r),n=function(){r.parentNode.removeChild(r)};return t(e),function(r){if(r){if(r.css===e.css&&r.media===e.media&&r.sourceMap===e.sourceMap)return;t(e=r)}else n()}}var b,v=(b=[],function(e,t){return b[e]=t,b.filter(Boolean).join("\n")});function w(e,t,n,r){var a=n?"":r.css;if(e.styleSheet)e.styleSheet.cssText=v(t,a);else{var i=document.createTextNode(a),o=e.childNodes;o[t]&&e.removeChild(o[t]),o.length?e.insertBefore(i,o[t]):e.appendChild(i)}}function A(e,t){var n=t.css,r=t.media,a=t.sourceMap;if(r&&e.setAttribute("media",r),d.ssrId&&e.setAttribute(p,t.id),a&&(n+="\n/*# sourceURL="+a.sources[0]+" */",n+="\n/*# sourceMappingURL=data:application/json;base64,"+btoa(unescape(encodeURIComponent(JSON.stringify(a))))+" */"),e.styleSheet)e.styleSheet.cssText=n;else{for(;e.firstChild;)e.removeChild(e.firstChild);e.appendChild(document.createTextNode(n))}}},UgRQ:function(e,t,n){},fk7Z:function(e,t,n){var r=n("C2Iv");r.__esModule&&(r=r.default),"string"==typeof r&&(r=[[e.i,r,""]]),r.locals&&(e.exports=r.locals);(0,n("SZ7m").default)("ecfcf946",r,!0,{})},n6Dk:function(e,t,n){},xlli:function(e,t){Shopware.Component.register("hipay-html-bloc",{template:'<div v-html="html()"></div>',methods:{html:function(){var e=this.$attrs.name.slice(this.$attrs.name.lastIndexOf(".")+1);return"<"+e+">"+this.$tc(this.$parent.bind.value)+"</"+e+">"}}})},yAkZ:function(e){e.exports=JSON.parse('{"name":"hipay/hipay-enterprise-shopware-6","description":"HiPay enterprise module for Shopware","license":"Apache-2.0","version":"1.0.0","authors":[{"email":"support.tpp@hipay.com","homepage":"http://www.hipay.com","name":"HiPay"}],"keywords":["HiPay","payment","php","shopware"],"type":"shopware-platform-plugin","extra":{"shopware-plugin-class":"HiPay\\\\Payment\\\\HiPayPaymentPlugin","plugin-icon":"src/Resources/config/hipay.png","author":"HiPay","label":{"en-GB":"HiPay Payment","de-DE":"HiPay Payment"},"description":{"en-GB":"Hipay enterprise module for Shopware","de-DE":"Hipay Enterprise-Modul für Shopware"},"manufacturerLink":{"en-GB":"#","de-DE":"#"},"supportLink":{"en-GB":"#","de-DE":"#"}},"autoload":{"psr-4":{"HiPay\\\\Payment\\\\":"src/"}},"autoload-dev":{"psr-4":{"HiPay\\\\Payment\\\\Tests\\\\":"tests/"}},"require":{"shopware/core":"6.4.*","hipay/hipay-fullservice-sdk-php":"^2.11"},"require-dev":{"phpunit/php-code-coverage":"~9.2.14","phpunit/phpunit":"~9.5.17","symfony/phpunit-bridge":"~4.4 || ~5.2.3 || ~5.3.0 || ~5.4.0","infection/infection":"^0.26.6","phpstan/phpstan":"^1.8","friendsofphp/php-cs-fixer":"*"},"archive":{"exclude":["/bin","./\\\\.*","docker-compose.yaml","shopware.sh"]},"config":{"allow-plugins":{"infection/extension-installer":true}}}')},yLFk:function(e){e.exports=JSON.parse('{"hipay":{"config":{"help":{"manual":"Online manual","github":"Report Errors on github","version":"Version number"},"checkAccess":{"title":"HiPay Configuration","success":"Test API credentials: The HiPay API credentials are valid.","failure":"Test API credentials: The HiPay API credentials are not valid.","button":"Test API credentials"},"title":{"privateKey":"Private credentials","publicKey":"Public credentials","notification":"Notification settings"},"capture-help":"<b>Automatic</b>: All transactions will be automatically captured.<br/><b>Manual</b>: All transactions will be captured manually in your HiPay or Shopware back office.","operation-help":"<b>Hosted page</b>: The customer is redirected to a secure payment page hosted by HiPay.<br/><b>Hosted fields</b>: The customer will fill in their banking information directly on the merchant\'s website but the form fields will be hosted by HiPay. This mode is only valid for credit cards","info":"To inform you about events related to your payment system, e.g. about a new transaction or a 3-D Secure transaction, the HiPay Enterprise platform can send your application a server-to-server notification. Login to Hipay Back Office, go to Integration > Security Settings module and get your HiPay merchant account passphrase.","authenticationIndicator":"<b>3-D Secure authentication mandatory :</b><br/>An authentication is required to proceed (This authentication can either be a 3DSv2 Challenge flow or a 3DSv2 frictionless flow, without any user input required). <b>If the authentication fails, the transaction will be refused and the client will not be charged.</b><br/><br/><b>3-D Secure authentication if available :</b><br/>If the Payment Method allows it, an authentication process is requested. However, if the authentication fails, the transaction will not be refused (<b>only for rest of the world transactions</b>, as transactions made inside the EURO zone are subject to PSD2 and must be authenticated).e. If required by the bank, the customer completes an additional authentication step for the charge to succeed.","creditCards":{"choice":"Select credit card"}},"hosted-page":{"redirect-message":"You will be redirected to an external payment page. Please do not refresh the page during the process"},"hosted-fields":{"fullname":"fullname","card-number":"Card number","expiry-date":"Expiry date","cvc":"CVC"},"action":{"cancel":"Cancel","capture":"Capture","refund":"Refund","full_capture":"Full Capture","full_refund":"Full Refund"},"basket":{"column":{"product":"Product","quantity":"Quantity","price":"Price","shipping":"Shipping costs"}},"field":{"capture_amount":"Capture amount","remaining_amount":"Remaining amount","order_amount":"Order amount","captured_amount":"Captured amount","refund_amount":"Refund amount","refunded_amount":"Refunded amount"},"notification":{"capture":{"title":"Transaction capture","success":"Transaction successfully captured","failure":"Error during transaction capture"},"refund":{"title":"Transaction refund","success":"Transaction successfully refunded","failure":"Error during transaction refund"}},"transaction":{"title":"HiPay transaction lifecycle"}}}')}});