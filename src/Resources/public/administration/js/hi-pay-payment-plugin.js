!function(e){var t={};function n(r){if(t[r])return t[r].exports;var i=t[r]={i:r,l:!1,exports:{}};return e[r].call(i.exports,i,i.exports,n),i.l=!0,i.exports}n.m=e,n.c=t,n.d=function(e,t,r){n.o(e,t)||Object.defineProperty(e,t,{enumerable:!0,get:r})},n.r=function(e){"undefined"!=typeof Symbol&&Symbol.toStringTag&&Object.defineProperty(e,Symbol.toStringTag,{value:"Module"}),Object.defineProperty(e,"__esModule",{value:!0})},n.t=function(e,t){if(1&t&&(e=n(e)),8&t)return e;if(4&t&&"object"==typeof e&&e&&e.__esModule)return e;var r=Object.create(null);if(n.r(r),Object.defineProperty(r,"default",{enumerable:!0,value:e}),2&t&&"string"!=typeof e)for(var i in e)n.d(r,i,function(t){return e[t]}.bind(null,i));return r},n.n=function(e){var t=e&&e.__esModule?function(){return e.default}:function(){return e};return n.d(t,"a",t),t},n.o=function(e,t){return Object.prototype.hasOwnProperty.call(e,t)},n.p="/bundles/hipaypaymentplugin/",n(n.s="3YVu")}({"1oaI":function(e,t){e.exports='\n<sw-button-process\n    style="margin-top: 20px"\n    class="sw-button--primary"\n    :disabled="isLoading"\n    :isLoading="isLoading"\n    :processSuccess="success"\n    @process-finish="completeSucess"\n    @click="validateConfig"\n>{{ $tc(\'hipay.config.checkAccess.button\') }}</sw-button-process>\n'},"3YVu":function(e,t,n){"use strict";n.r(t);n("PfVt"),n("xlli");var r=n("41++"),i=n.n(r),o=n("yAkZ");n("IHpg");Shopware.Component.register("hipay-help-bloc",{template:i.a,data:function(){return{version:o.version}},mounted:function(){console.log(o.version)}});var a=n("1oaI"),s=n.n(a),c=Shopware,u=c.Component,p=c.Mixin;u.register("hipay-check-server-access",{template:s.a,inject:["hipayService"],mixins:[p.getByName("notification")],props:{value:{required:!1}},data:function(){return{isLoading:!1,success:!1}},methods:{completeSucess:function(){this.sucess=!1},validateConfig:function(e){var t=this;this.isLoading=!0;var n=this.$tc("hipay.config.checkAccess.title");this.hipayService.validateConfig(this.getConfig()).then((function(e){if(!e.success)throw new Error(e.message);t.createNotificationSuccess({title:n,message:t.$tc("hipay.config.checkAccess.success")}),t.success=!0})).catch((function(e){t.createNotificationError({title:n,message:e.message||t.$tc("hipay.config.checkAccess.failure")})})).finally((function(){return t.isLoading=!1}))},getConfig:function(){for(var e=this.$parent;!e.hasOwnProperty("actualConfigData");)e=e.$parent;var t=e.currentSalesChannelId,n=e.actualConfigData;return Object.assign({},n.null,n[t],{environment:this.$parent.bind.value})}}});var l=n("ezwn"),f=n("Top6");Shopware.Locale.extend("en-GB",l),Shopware.Locale.extend("de_DE",f)},"41++":function(e,t){e.exports='<div class="hipay-help">\n    <div class="hipay-help-bloc">\n\n        <div class="hipay-help-bloc-item">\n            <sw-icon name="default-documentation-file"></sw-icon>\n            <a href="https://support.hipay.com" class="sw-button sw-button--primary" target="_blank" :title="$tc(\'hipay.config.help.manual\')">\n                {{ $tc(\'hipay.config.help.manual\') }} \n            </a>\n        </div>\n\n        <div class="hipay-help-bloc-item">\n            <sw-icon name="default-text-code"></sw-icon>\n            <a href="https://github.com/hipay/hipay-enterprise-shopware-6" class="sw-button sw-button--primary" target="_blank" :title="$tc(\'hipay.config.help.manual\')">\n               {{ $tc(\'hipay.config.help.github\') }} \n            </a>\n        </div>\n    </div>\n    <p> {{ $tc(\'hipay.config.help.version\') }} : {{ version }}</p>\n</div>'},IHpg:function(e,t,n){var r=n("RvIq");r.__esModule&&(r=r.default),"string"==typeof r&&(r=[[e.i,r,""]]),r.locals&&(e.exports=r.locals);(0,n("SZ7m").default)("d17d5a74",r,!0,{})},PfVt:function(e,t){function n(e){return(n="function"==typeof Symbol&&"symbol"==typeof Symbol.iterator?function(e){return typeof e}:function(e){return e&&"function"==typeof Symbol&&e.constructor===Symbol&&e!==Symbol.prototype?"symbol":typeof e})(e)}function r(e,t){if(!(e instanceof t))throw new TypeError("Cannot call a class as a function")}function i(e,t){for(var n=0;n<t.length;n++){var r=t[n];r.enumerable=r.enumerable||!1,r.configurable=!0,"value"in r&&(r.writable=!0),Object.defineProperty(e,r.key,r)}}function o(e,t){return(o=Object.setPrototypeOf||function(e,t){return e.__proto__=t,e})(e,t)}function a(e){var t=function(){if("undefined"==typeof Reflect||!Reflect.construct)return!1;if(Reflect.construct.sham)return!1;if("function"==typeof Proxy)return!0;try{return Boolean.prototype.valueOf.call(Reflect.construct(Boolean,[],(function(){}))),!0}catch(e){return!1}}();return function(){var n,r=c(e);if(t){var i=c(this).constructor;n=Reflect.construct(r,arguments,i)}else n=r.apply(this,arguments);return s(this,n)}}function s(e,t){if(t&&("object"===n(t)||"function"==typeof t))return t;if(void 0!==t)throw new TypeError("Derived constructors may only return object or undefined");return function(e){if(void 0===e)throw new ReferenceError("this hasn't been initialised - super() hasn't been called");return e}(e)}function c(e){return(c=Object.setPrototypeOf?Object.getPrototypeOf:function(e){return e.__proto__||Object.getPrototypeOf(e)})(e)}var u=Shopware.Classes.ApiService,p=Shopware.Application,l=function(e){!function(e,t){if("function"!=typeof t&&null!==t)throw new TypeError("Super expression must either be null or a function");e.prototype=Object.create(t&&t.prototype,{constructor:{value:e,writable:!0,configurable:!0}}),Object.defineProperty(e,"prototype",{writable:!1}),t&&o(e,t)}(p,e);var t,n,s,c=a(p);function p(e,t){var n,i=arguments.length>2&&void 0!==arguments[2]?arguments[2]:"hipay";return r(this,p),(n=c.call(this,e,t,i)).headers=n.getBasicHeaders({}),n}return t=p,(n=[{key:"validateConfig",value:function(e){var t=this.getBasicHeaders({});return this.httpClient.post("http://localhost:8076/api/_action/".concat(this.getApiBasePath(),"/checkAccess"),e,{headers:t}).then((function(e){return u.handleResponse(e)}))}}])&&i(t.prototype,n),s&&i(t,s),Object.defineProperty(t,"prototype",{writable:!1}),p}(u);p.addServiceProvider("hipayService",(function(e){var t=p.getContainer("init");return new l(t.httpClient,e.loginService)}))},RvIq:function(e,t,n){},SZ7m:function(e,t,n){"use strict";function r(e,t){for(var n=[],r={},i=0;i<t.length;i++){var o=t[i],a=o[0],s={id:e+":"+i,css:o[1],media:o[2],sourceMap:o[3]};r[a]?r[a].parts.push(s):n.push(r[a]={id:a,parts:[s]})}return n}n.r(t),n.d(t,"default",(function(){return d}));var i="undefined"!=typeof document;if("undefined"!=typeof DEBUG&&DEBUG&&!i)throw new Error("vue-style-loader cannot be used in a non-browser environment. Use { target: 'node' } in your Webpack config to indicate a server-rendering environment.");var o={},a=i&&(document.head||document.getElementsByTagName("head")[0]),s=null,c=0,u=!1,p=function(){},l=null,f="data-vue-ssr-id",h="undefined"!=typeof navigator&&/msie [6-9]\b/.test(navigator.userAgent.toLowerCase());function d(e,t,n,i){u=n,l=i||{};var a=r(e,t);return y(a),function(t){for(var n=[],i=0;i<a.length;i++){var s=a[i];(c=o[s.id]).refs--,n.push(c)}t?y(a=r(e,t)):a=[];for(i=0;i<n.length;i++){var c;if(0===(c=n[i]).refs){for(var u=0;u<c.parts.length;u++)c.parts[u]();delete o[c.id]}}}}function y(e){for(var t=0;t<e.length;t++){var n=e[t],r=o[n.id];if(r){r.refs++;for(var i=0;i<r.parts.length;i++)r.parts[i](n.parts[i]);for(;i<n.parts.length;i++)r.parts.push(m(n.parts[i]));r.parts.length>n.parts.length&&(r.parts.length=n.parts.length)}else{var a=[];for(i=0;i<n.parts.length;i++)a.push(m(n.parts[i]));o[n.id]={id:n.id,refs:1,parts:a}}}}function g(){var e=document.createElement("style");return e.type="text/css",a.appendChild(e),e}function m(e){var t,n,r=document.querySelector("style["+f+'~="'+e.id+'"]');if(r){if(u)return p;r.parentNode.removeChild(r)}if(h){var i=c++;r=s||(s=g()),t=w.bind(null,r,i,!1),n=w.bind(null,r,i,!0)}else r=g(),t=S.bind(null,r),n=function(){r.parentNode.removeChild(r)};return t(e),function(r){if(r){if(r.css===e.css&&r.media===e.media&&r.sourceMap===e.sourceMap)return;t(e=r)}else n()}}var v,b=(v=[],function(e,t){return v[e]=t,v.filter(Boolean).join("\n")});function w(e,t,n,r){var i=n?"":r.css;if(e.styleSheet)e.styleSheet.cssText=b(t,i);else{var o=document.createTextNode(i),a=e.childNodes;a[t]&&e.removeChild(a[t]),a.length?e.insertBefore(o,a[t]):e.appendChild(o)}}function S(e,t){var n=t.css,r=t.media,i=t.sourceMap;if(r&&e.setAttribute("media",r),l.ssrId&&e.setAttribute(f,t.id),i&&(n+="\n/*# sourceURL="+i.sources[0]+" */",n+="\n/*# sourceMappingURL=data:application/json;base64,"+btoa(unescape(encodeURIComponent(JSON.stringify(i))))+" */"),e.styleSheet)e.styleSheet.cssText=n;else{for(;e.firstChild;)e.removeChild(e.firstChild);e.appendChild(document.createTextNode(n))}}},Top6:function(e){e.exports=JSON.parse('{"hipay":{"config":{"help":{"manual":"Online manual","github":"Report Errors on github","version":"version"},"checkAccess":{"title":"Hipay Configuration","success":"Your configuration is valid.","failure":"There\'s an issue in your configuration.","button":"API-Anmeldedaten testen"},"title":{"privateKey":"Private key","publicKey":"Public key","notification":"Benachrichtigungseinstellungen"},"info":"Um Sie über Ereignisse im Zusammenhang mit Ihrem Zahlungssystem zu informieren, z. B. über eine neue Transaktion oder eine 3-D Secure-Transaktion, kann die HiPay Enterprise-Plattform Ihrer Anwendung eine Server-zu-Server-Benachrichtigung senden. Loggen Sie sich in das Hipay Back Office ein, gehen Sie zum Modul Integration > Sicherheitseinstellungen und rufen Sie die Passphrase Ihres HiPay-Händlerkontos ab"}}}')},ezwn:function(e){e.exports=JSON.parse('{"hipay":{"config":{"help":{"manual":"Online manual","github":"Report Errors on github","version":"version"},"checkAccess":{"title":"Hipay Configuration","success":"Your configuration is valid.","failure":"There\'s an issue in your configuration.","button":"Test API Credentials"},"title":{"privateKey":"Private key","publicKey":"Public key","notification":"Notification setting"},"info":"In order to inform you of events related to your payment system, such as a new transaction or a 3-D Secure transaction, the HiPay Enterprise platform can send your application a server-to-server notification. Log in to the Hipay Back Office, go to the Integration > Security Setting module to retrieve the passphrase of your HiPay merchant account"}}}')},xlli:function(e,t){Shopware.Component.register("hipay-html-bloc",{template:'<div v-html="html()"></div>',methods:{html:function(){var e=this.$attrs.name.slice(this.$attrs.name.lastIndexOf(".")+1);return"<"+e+">"+this.$tc(this.$parent.bind.value)+"</"+e+">"}}})},yAkZ:function(e){e.exports=JSON.parse('{"name":"hipay/hipay-enterprise-shopware-6","description":"HiPay enterprise module for Shopware","license":"Apache-2.0","version":"1.0.0","authors":[{"email":"support.tpp@hipay.com","homepage":"http://www.hipay.com","name":"HiPay"}],"keywords":["HiPay","payment","php","shopware"],"type":"shopware-platform-plugin","extra":{"shopware-plugin-class":"HiPay\\\\Payment\\\\HiPayPaymentPlugin","plugin-icon":"src/Resources/config/hipay.png","author":"HiPay","label":{"en-GB":"HiPay Payment","de-DE":"HiPay Payment"},"description":{"en-GB":"Hipay enterprise module for Shopware","de-DE":"Hipay Enterprise-Modul für Shopware"},"manufacturerLink":{"en-GB":"#","de-DE":"#"},"supportLink":{"en-GB":"#","de-DE":"#"}},"autoload":{"psr-4":{"HiPay\\\\Payment\\\\":"src/"}},"autoload-dev":{"psr-4":{"HiPay\\\\Payment\\\\Tests\\\\":"tests/"}},"require":{"shopware/core":"6.4.*","hipay/hipay-fullservice-sdk-php":"^2.10"},"require-dev":{"phpunit/php-code-coverage":"~9.2.14","phpunit/phpunit":"~9.5.17","symfony/phpunit-bridge":"~4.4 || ~5.2.3 || ~5.3.0 || ~5.4.0"},"archive":{"exclude":["/bin","./\\\\.*","docker-compose.yaml","shopeware.sh"]}}')}});