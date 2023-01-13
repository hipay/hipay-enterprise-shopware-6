(window.webpackJsonp=window.webpackJsonp||[]).push([["hi-pay-payment-plugin"],{z0Y9:function(e,t,n){"use strict";function r(e){return(r="function"==typeof Symbol&&"symbol"==typeof Symbol.iterator?function(e){return typeof e}:function(e){return e&&"function"==typeof Symbol&&e.constructor===Symbol&&e!==Symbol.prototype?"symbol":typeof e})(e)}function o(e,t){var n=Object.keys(e);if(Object.getOwnPropertySymbols){var r=Object.getOwnPropertySymbols(e);t&&(r=r.filter((function(t){return Object.getOwnPropertyDescriptor(e,t).enumerable}))),n.push.apply(n,r)}return n}function i(e){for(var t=1;t<arguments.length;t++){var n=null!=arguments[t]?arguments[t]:{};t%2?o(Object(n),!0).forEach((function(t){f(e,t,n[t])})):Object.getOwnPropertyDescriptors?Object.defineProperties(e,Object.getOwnPropertyDescriptors(n)):o(Object(n)).forEach((function(t){Object.defineProperty(e,t,Object.getOwnPropertyDescriptor(n,t))}))}return e}function a(e,t){if(!(e instanceof t))throw new TypeError("Cannot call a class as a function")}function u(e,t){for(var n=0;n<t.length;n++){var r=t[n];r.enumerable=r.enumerable||!1,r.configurable=!0,"value"in r&&(r.writable=!0),Object.defineProperty(e,r.key,r)}}function s(e,t){return!t||"object"!==r(t)&&"function"!=typeof t?function(e){if(void 0===e)throw new ReferenceError("this hasn't been initialised - super() hasn't been called");return e}(e):t}function c(e){return(c=Object.setPrototypeOf?Object.getPrototypeOf:function(e){return e.__proto__||Object.getPrototypeOf(e)})(e)}function l(e,t){return(l=Object.setPrototypeOf||function(e,t){return e.__proto__=t,e})(e,t)}function f(e,t,n){return t in e?Object.defineProperty(e,t,{value:n,enumerable:!0,configurable:!0,writable:!0}):e[t]=n,e}n.r(t);var p=function(e){function t(){return a(this,t),s(this,c(t).apply(this,arguments))}var n,r,o;return function(e,t){if("function"!=typeof t&&null!==t)throw new TypeError("Super expression must either be null or a function");e.prototype=Object.create(t&&t.prototype,{constructor:{value:e,writable:!0,configurable:!0}}),t&&l(e,t)}(t,e),n=t,(r=[{key:"init",value:function(){if(this.constructor===t)throw new TypeError('Class "HipayHostedFieldsPlugin" cannot be instantiated directly');this.options=i({},this.getPaymentDefaultOption(),{},this.options),console.log(i({},this.options)),this._configHostedFields=this.getConfigHostedFields(),this._form=document.querySelector("#"+this.options.idResponse).form,this._cardInstance=HiPay(this.options).create(this.getPaymentName(),this._configHostedFields),this._registerEvents()}},{key:"_registerEvents",value:function(){var e=this;this._cardInstance.on("ready",(function(){e._cardInstance.on("inputChange",e._inputErrorHandler.bind(e)),e._cardInstance.on("blur",e._inputErrorHandler.bind(e));var t=document.querySelector("#"+e.options.idResponse),n=!1;e._cardInstance.on("change",(function(r){(n=r.valid)?e._cardInstance.getPaymentData().then((function(e){t.setAttribute("value",JSON.stringify(e))})):t.setAttribute("value","")})),t.addEventListener("invalid",(function(r){n||e._cardInstance.getPaymentData().then((function(){}),(function(n){t.setAttribute("value",""),n.forEach((function(t){return e._errorHandler(t.field,!0,t.error)}))}))}))}))}},{key:"_inputErrorHandler",value:function(e){this._errorHandler(e.element,!e.validity.valid,e.validity.error)}},{key:"_errorHandler",value:function(e){var t=arguments.length>1&&void 0!==arguments[1]&&arguments[1],n=arguments.length>2&&void 0!==arguments[2]?arguments[2]:"",r=this._configHostedFields.fields[e].selector,o=document.querySelector("#"+r);t?o.classList.add(this.options.errorClass):o.classList.remove(this.options.errorClass);var i=document.querySelector("#"+this.options.errorPrefix+"-"+r);i&&(i.innerHTML=n)}},{key:"getPaymentName",value:function(){throw new Error('Method "getPaymentName" must be implemented')}},{key:"getConfigHostedFields",value:function(){throw new Error('Method "getConfigHostedFields" must be implemented')}}])&&u(n.prototype,r),o&&u(n,o),t}(n("FGIj").a);function y(e){return(y="function"==typeof Symbol&&"symbol"==typeof Symbol.iterator?function(e){return typeof e}:function(e){return e&&"function"==typeof Symbol&&e.constructor===Symbol&&e!==Symbol.prototype?"symbol":typeof e})(e)}function d(e,t){if(!(e instanceof t))throw new TypeError("Cannot call a class as a function")}function b(e,t){for(var n=0;n<t.length;n++){var r=t[n];r.enumerable=r.enumerable||!1,r.configurable=!0,"value"in r&&(r.writable=!0),Object.defineProperty(e,r.key,r)}}function h(e,t){return!t||"object"!==y(t)&&"function"!=typeof t?function(e){if(void 0===e)throw new ReferenceError("this hasn't been initialised - super() hasn't been called");return e}(e):t}function m(e){return(m=Object.setPrototypeOf?Object.getPrototypeOf:function(e){return e.__proto__||Object.getPrototypeOf(e)})(e)}function g(e,t){return(g=Object.setPrototypeOf||function(e,t){return e.__proto__=t,e})(e,t)}f(p,"options",{username:null,password:null,environment:null,lang:null,idResponse:"hipay-response",cvcHelp:!1,errorClass:"is-invalid",errorPrefix:"error",styles:null});var v=function(e){function t(){return d(this,t),h(this,m(t).apply(this,arguments))}var n,r,o;return function(e,t){if("function"!=typeof t&&null!==t)throw new TypeError("Super expression must either be null or a function");e.prototype=Object.create(t&&t.prototype,{constructor:{value:e,writable:!0,configurable:!0}}),t&&g(e,t)}(t,e),n=t,(r=[{key:"getPaymentDefaultOption",value:function(){return{idCardHolder:"hipay-card-holder",idCardNumber:"hipay-card-number",idExpiryDate:"hipay-expiry-date",idCvc:"hipay-cvc",firstnameValue:"",lastnameValue:""}}},{key:"getPaymentName",value:function(){return"card"}},{key:"getConfigHostedFields",value:function(){var e={fields:{cardHolder:{selector:this.options.idCardHolder,defaultFirstname:this.options.firstnameValue,defaultLastname:this.options.lastnameValue},cardNumber:{selector:this.options.idCardNumber},expiryDate:{selector:this.options.idExpiryDate},cvc:{selector:this.options.idCvc,helpButton:this.options.cvcHelp}}};return this.options.styles&&(e.styles=this.options.styles),e}}])&&b(n.prototype,r),o&&b(n,o),t}(p);function O(e){return(O="function"==typeof Symbol&&"symbol"==typeof Symbol.iterator?function(e){return typeof e}:function(e){return e&&"function"==typeof Symbol&&e.constructor===Symbol&&e!==Symbol.prototype?"symbol":typeof e})(e)}function _(e,t){if(!(e instanceof t))throw new TypeError("Cannot call a class as a function")}function w(e,t){for(var n=0;n<t.length;n++){var r=t[n];r.enumerable=r.enumerable||!1,r.configurable=!0,"value"in r&&(r.writable=!0),Object.defineProperty(e,r.key,r)}}function P(e,t){return!t||"object"!==O(t)&&"function"!=typeof t?function(e){if(void 0===e)throw new ReferenceError("this hasn't been initialised - super() hasn't been called");return e}(e):t}function j(e){return(j=Object.setPrototypeOf?Object.getPrototypeOf:function(e){return e.__proto__||Object.getPrototypeOf(e)})(e)}function k(e,t){return(k=Object.setPrototypeOf||function(e,t){return e.__proto__=t,e})(e,t)}var S=function(e){function t(){return _(this,t),P(this,j(t).apply(this,arguments))}var n,r,o;return function(e,t){if("function"!=typeof t&&null!==t)throw new TypeError("Super expression must either be null or a function");e.prototype=Object.create(t&&t.prototype,{constructor:{value:e,writable:!0,configurable:!0}}),t&&k(e,t)}(t,e),n=t,(r=[{key:"getPaymentDefaultOption",value:function(){return{idIssuerBank:"hipay-giropay-issuer-bank"}}},{key:"getPaymentName",value:function(){return"giropay"}},{key:"getConfigHostedFields",value:function(){var e={fields:{issuer_bank_id:{selector:this.options.idIssuerBank}}};return this.options.styles&&(e.styles=this.options.styles),e}}])&&w(n.prototype,r),o&&w(n,o),t}(p);function H(e){return(H="function"==typeof Symbol&&"symbol"==typeof Symbol.iterator?function(e){return typeof e}:function(e){return e&&"function"==typeof Symbol&&e.constructor===Symbol&&e!==Symbol.prototype?"symbol":typeof e})(e)}function E(e,t){if(!(e instanceof t))throw new TypeError("Cannot call a class as a function")}function C(e,t){for(var n=0;n<t.length;n++){var r=t[n];r.enumerable=r.enumerable||!1,r.configurable=!0,"value"in r&&(r.writable=!0),Object.defineProperty(e,r.key,r)}}function D(e,t){return!t||"object"!==H(t)&&"function"!=typeof t?function(e){if(void 0===e)throw new ReferenceError("this hasn't been initialised - super() hasn't been called");return e}(e):t}function F(e){return(F=Object.setPrototypeOf?Object.getPrototypeOf:function(e){return e.__proto__||Object.getPrototypeOf(e)})(e)}function I(e,t){return(I=Object.setPrototypeOf||function(e,t){return e.__proto__=t,e})(e,t)}var V=function(e){function t(){return E(this,t),D(this,F(t).apply(this,arguments))}var n,r,o;return function(e,t){if("function"!=typeof t&&null!==t)throw new TypeError("Super expression must either be null or a function");e.prototype=Object.create(t&&t.prototype,{constructor:{value:e,writable:!0,configurable:!0}}),t&&I(e,t)}(t,e),n=t,(r=[{key:"getPaymentDefaultOption",value:function(){return{firstname:"hipay-sdd-firstname",lastname:"hipay-sdd-lastname",iban:"hipay-sdd-iban",gender:"hipay-sdd-gender",bank_name:"hipay-sdd-bank-name",genderValue:"U",firstnameValue:"",lastnameValue:""}}},{key:"getPaymentName",value:function(){return"sdd"}},{key:"getConfigHostedFields",value:function(){var e={fields:{firstname:{selector:this.options.firstname,defaultValue:this.options.firstnameValue},lastname:{selector:this.options.lastname,defaultValue:this.options.lastnameValue},iban:{selector:this.options.iban},gender:{selector:this.options.gender,defaultValue:this.options.genderValue},bank_name:{selector:this.options.bank_name}}};return this.options.styles&&(e.styles=this.options.styles),e}}])&&C(n.prototype,r),o&&C(n,o),t}(p);function T(e){return(T="function"==typeof Symbol&&"symbol"==typeof Symbol.iterator?function(e){return typeof e}:function(e){return e&&"function"==typeof Symbol&&e.constructor===Symbol&&e!==Symbol.prototype?"symbol":typeof e})(e)}function x(e,t){if(!(e instanceof t))throw new TypeError("Cannot call a class as a function")}function N(e,t){for(var n=0;n<t.length;n++){var r=t[n];r.enumerable=r.enumerable||!1,r.configurable=!0,"value"in r&&(r.writable=!0),Object.defineProperty(e,r.key,r)}}function R(e,t){return!t||"object"!==T(t)&&"function"!=typeof t?function(e){if(void 0===e)throw new ReferenceError("this hasn't been initialised - super() hasn't been called");return e}(e):t}function B(e){return(B=Object.setPrototypeOf?Object.getPrototypeOf:function(e){return e.__proto__||Object.getPrototypeOf(e)})(e)}function L(e,t){return(L=Object.setPrototypeOf||function(e,t){return e.__proto__=t,e})(e,t)}var q=function(e){function t(){return x(this,t),R(this,B(t).apply(this,arguments))}var n,r,o;return function(e,t){if("function"!=typeof t&&null!==t)throw new TypeError("Super expression must either be null or a function");e.prototype=Object.create(t&&t.prototype,{constructor:{value:e,writable:!0,configurable:!0}}),t&&L(e,t)}(t,e),n=t,(r=[{key:"getPaymentDefaultOption",value:function(){return{idIssuerBank:"hipay-ideal-issuer-bank"}}},{key:"getPaymentName",value:function(){return"ideal"}},{key:"getConfigHostedFields",value:function(){var e={fields:{issuer_bank_id:{selector:this.options.idIssuerBank}}};return this.options.styles&&(e.styles=this.options.styles),e}}])&&N(n.prototype,r),o&&N(n,o),t}(p),M=window.PluginManager;M.register("HandlerHipayCreditcardPlugin",v,"[handler-hipay-creditcard-plugin]"),M.register("HandlerHipayGiropayPlugin",S,"[handler-hipay-giropay-plugin]"),M.register("HandlerHipaySepadirectdebitPlugin",V,"[handler-hipay-sepadirectdebit-plugin]"),M.register("HandlerHipayIdealPlugin",q,"[handler-hipay-ideal-plugin]")}},[["z0Y9","runtime","vendor-node","vendor-shared"]]]);