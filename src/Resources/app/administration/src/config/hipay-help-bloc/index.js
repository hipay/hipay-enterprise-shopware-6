import template from './hipay-help-bloc.html.twig';
import composerFile from './../../../../../../../composer.json';
import './hipay-help-bloc.scss'


/**
 * Component for the plugin configuration
 * Add stylized help link 
 * Version of the plugin extracted from composer.json
 */
Shopware.Component.register('hipay-help-bloc', {
    template,
    data() {
        return {
            version: composerFile.version
        }
    }
});