/**
 * Select2Table Bundle - jQuery plugin for Symfony Select2TableType
 * Compatible with Select2 v4+, Symfony 6.4 / 7.x / 8.x
 */
(function ($) {
    'use strict';

    if (typeof $ === 'undefined') {
        return;
    }

    $.fn.select2table = function (options) {
        return this.each(function () {
            const $s2 = $(this);

            // Avoid double-initialization
            if ($s2.hasClass('select2-hidden-accessible')) {
                return;
            }

            let request;
            const limit = $s2.data('page-limit') || 10;
            const scroll = Boolean($s2.data('scroll'));
            const prefix = Date.now();
            const queryParameters = $s2.data('query-parameters') || {};
            const renderHtml = Boolean($s2.data('render-html'));
            const cache = {};

            // Dependent parameters handling
            const reqParams = $s2.data('req_params');
            if (reqParams && typeof reqParams === 'object') {
                $.each(reqParams, function (paramKey, inputName) {
                    $(`[name="${inputName}"]`).on('change', function () {
                        $s2.val(null).trigger('change');
                    });
                });
            }

            const defaultOptions = {
                // Tags / Allow Add
                createTag: function (params) {
                    if ($s2.data('tags') && params.term && params.term.length > 0) {
                        const newTagText = $s2.data('tags-text') || ' (NEW)';
                        const newTagPrefix = $s2.data('new-tag-prefix') || '__';
                        return {
                            id: newTagPrefix + params.term,
                            text: params.term + newTagText
                        };
                    }
                    return null;
                },
                ajax: {
                    url: $s2.data('ajax--url') || '',
                    dataType: 'json',
                    delay: $s2.data('ajax--delay') || 250,
                    transport: function (params, success, failure) {
                        const cacheEnabled = Boolean($s2.data('ajax--cache'));
                        const cacheTimeout = parseInt($s2.data('ajax--cache-timeout'), 10) || 0;

                        if (cacheEnabled) {
                            const cacheKey = prefix + '_p:' + (params.data.page || 1) + '_q:' + (params.data.q || '');

                            if (cache[cacheKey] && (!cacheTimeout || Date.now() < cache[cacheKey].expiresAt)) {
                                success(cache[cacheKey].data);
                                return;
                            }

                            return $.ajax(params)
                                .done(function (data) {
                                    cache[cacheKey] = {
                                        data: data,
                                        expiresAt: cacheTimeout > 0 ? Date.now() + cacheTimeout : null
                                    };
                                    success(data);
                                })
                                .fail(failure);
                        }

                        if (request) {
                            request.abort();
                        }

                        request = $.ajax(params)
                            .done(success)
                            .fail(failure)
                            .always(function () {
                                request = null;
                            });

                        return request;
                    },
                    data: function (params) {
                        const payload = {
                            q: params.term || '',
                            field_name: $s2.data('name'),
                            class_type: $s2.data('classtype') || ''
                        };

                        if (scroll) {
                            payload.page = params.page || 1;
                        }

                        // Attach dependent field values
                        if (reqParams && typeof reqParams === 'object') {
                            $.each(reqParams, function (paramKey, inputName) {
                                payload[paramKey] = $(`[name="${inputName}"]`).val();
                            });
                        }

                        // Attach static/dynamic custom query parameters
                        if (typeof queryParameters === 'object') {
                            for (const key in queryParameters) {
                                if (Object.prototype.hasOwnProperty.call(queryParameters, key) && !(key in payload)) {
                                    payload[key] = queryParameters[key];
                                }
                            }
                        }

                        return payload;
                    },
                    processResults: function (data, params) {
                        let results = [];
                        let more = false;

                        if (Array.isArray(data)) {
                            results = data;
                        } else if (data && typeof data === 'object') {
                            results = data.results || [];
                            more = Boolean(data.more);
                        }

                        const response = { results: results };
                        if (scroll) {
                            response.pagination = { more: more };
                        }

                        return response;
                    }
                }
            };

            let mergedOptions = $.extend(true, {}, defaultOptions, options || {});

            if (renderHtml) {
                mergedOptions = $.extend({}, {
                    escapeMarkup: function (markup) {
                        return markup;
                    },
                    templateResult: function (item) {
                        if (!item.id) {
                            return item.text;
                        }
                        return item.html ? $('<span>' + item.html + '</span>') : item.text;
                    },
                    templateSelection: function (item) {
                        return item.text;
                    }
                }, mergedOptions);
            }

            $s2.select2(mergedOptions);
        });
    };

    // Auto-initialize on ready
    $(function () {
        $('.select2table[data-autostart="true"], .select2entity[data-autostart="true"]').select2table();

        // Support Symfony dynamic collection prototype addition
        $(document).on('click', '[data-prototype], [data-collection-add-new-widget]', function () {
            setTimeout(function () {
                $('.select2table[data-autostart="true"], .select2entity[data-autostart="true"]').select2table();
            }, 50);
        });

        // Support Turbo / Hotwire page loads
        document.addEventListener('turbo:load', function () {
            $('.select2table[data-autostart="true"], .select2entity[data-autostart="true"]').select2table();
        });
    });

})(typeof jQuery !== 'undefined' ? jQuery : null);
