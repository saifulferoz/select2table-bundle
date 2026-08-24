import { Controller } from '@hotwired/stimulus';
import $ from 'jquery';
import 'select2';

/**
 * Stimulus Controller for Symfony UX / AssetMapper / Webpack Encore
 * Usage: <select data-controller="select2table" ...>
 */
export default class extends Controller {
    static values = {
        options: Object
    }

    connect() {
        const $element = $(this.element);
        if (typeof $element.select2table === 'function') {
            $element.select2table(this.optionsValue || {});
        }
    }

    disconnect() {
        const $element = $(this.element);
        if ($element.hasClass('select2-hidden-accessible')) {
            $element.select2('destroy');
        }
    }
}
