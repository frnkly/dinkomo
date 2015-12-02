'use strict';

var App = {

    _kbFocus: null,

    init: function init() {
        //
        this.root = $('base').attr('href');
    },

    /**
     *
     */
    setKeyboardFocus: function setKeyboardFocus(input) {
        this._kbFocus = input;
    },

    /**
     *
     */
    keyboardInput: function keyboardInput(letter) {
        if (this._kbFocus) {
            this._kbFocus.value = this._kbFocus.value + letter;
            this._kbFocus.focus();
        }

        return false;
    },

    openDialog: function openDialog(id) {
        console.log('App.openDialog deprecated');

        return Dialogs.open(id);
    },

    closeDialogs: function closeDialogs() {
        console.log('App.closeDialogs deprecated');

        return Dialogs.close();
    },

    redirect: function redirect(path) {
        window.location = path.length > 1 ? this.root + path : this.root;
    },

    urlencode: function urlencode(str) {
        return encodeURIComponent((str + '').toString()).replace(/!/g, '%21').replace(/'/g, '%27').replace(/\(/g, '%28').replace(/\)/g, '%29').replace(/\*/g, '%2A').replace(/%20/g, '+');
    },

    log: function log(msg) {
        if (console) console.log('App.js - ' + msg);
    }
};

// Initiate
$(document).ready(function (event) {
    // Initialize app.
    App.init();

    // Attach event listeners.
    $('.close').click(App.closeDialogs.bind(App));
    $('.has-tooltip').popup({ on: 'hover' });
    $('.has-inline-tooltip').popup({ inline: true, on: 'hover' });
    $('.has-dropdown-menu').dropdown();

    // Attach helper keyboard to text inputs.
    $('.text-input').focus(function () {
        App.setKeyboardFocus(this);
        $('#keyboard').fadeIn(300);
    });

    // Remove helper keyboard when focus is lost.
    $('.en-text-input').focus(function () {
        App.setKeyboardFocus(null);
        $('#keyboard').fadeOut(300);
    });

    // Make keyboard draggable.
    $('#keyboard').draggable();

    // Setup AJAX headers
    $.ajaxSetup({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        }
    });
});

/*
 *
 */
var Dialogs = {
    open: function open(dialog) {
        $('.dialog').hide();
        return this.toggle(dialog);
    },

    toggle: function toggle(dialog) {
        box = '.dialog.' + dialog;
        if ($(box).is(':hidden')) $(box).fadeIn(240);else $(box).fadeOut(240);
        return false;
    },

    close: function close() {
        $('.dialog').fadeOut(240);
        return false;
    },

    //
    // "Add resource" dialog.
    //

    setupAddResourceForm: function setupAddResourceForm(input) {
        // Semantic UI search input.
        $(input).search({
            apiSettings: {
                method: 'POST',
                url: 'language/search/{query}?semantic=1'
            },
            searchFields: ['name', 'alt_names'],
            searchDelay: 500,
            searchFullText: false,
            onSelect: function onSelect(result, response) {
                document.addResourceDialogForm.lang.value = result.code;
            }
        });

        // Clear input.
        $(input).find('.prompt').val('');
    },

    addResource: function addResource() {
        // Get language code.
        var code = document.addResourceDialogForm.lang.value.trim();
        if (code.length < 3) {
            return false;
        }

        App.redirect(code + '/+' + $(document.addResourceDialogForm.type).val());
        return false;
    },

    //
    // "Find a language" dialog.
    //

    setupFindLanguageForm: function setupFindLanguageForm(input) {
        // Semantic UI search input.
        $(input).search({
            apiSettings: {
                method: 'POST',
                url: 'language/search/{query}?semantic=1'
            },
            searchFields: ['name', 'alt_names'],
            searchDelay: 500,
            searchFullText: false,
            onSelect: function onSelect(result, response) {
                $(document.findLanguageDialogForm).fadeOut(500);
                App.redirect(result.code);
            }
        });
    }
};

var Forms = {
    /**
     * Definition lookup forms.
     */
    _def: {},

    getDefinitionForm: function getDefinitionForm(name) {
        return this._def[name];
    },

    /**
     * Prepares a language search input for AJAX calls.
     *
     * @param mixed input
     * @param object items
     * @param int max
     * @param array plugins
     */
    setupLangSearch: function setupLangSearch(input, items, max, plugins) {
        // Performance check.
        if (!$(input)) return;

        // Initialize selectize input.
        var $select = $(input).selectize({
            valueField: 'code',
            labelField: 'name',
            searchField: ['code', 'name', 'alt_names'],
            options: items,
            plugins: plugins || null,
            create: false,
            maxItems: max || 1,
            render: {
                item: function item(_item, escape) {
                    return '<div>' + '<span class="name">' + escape(_item.name) + '</span>' + '</div>';
                },
                score: function score(search) {
                    return this.getScoreFunction(search);
                },
                option: function option(item, escape) {
                    // Language title
                    var title = item.name;
                    if (item.parentName && item.parentName.length) title += ' (a sub-language of ' + item.parentName + ')';

                    // Add a short desciption
                    var hint = '';
                    if (item.alt_names && item.alt_names.length) hint = '<span class="hint"> &mdash; Also known as ' + item.alt_names + '</span>';

                    // Return formatted HTML
                    return '<div>' + '<span class="label">' + escape(title) + '</span>' + hint + '</div>';
                }
            },
            load: function load(query, callback) {
                if (!query.trim().length) return callback();
                $.ajax({
                    url: App.root + 'language/search/' + App.urlencode(query.trim()),
                    type: 'POST',
                    error: function error() {
                        callback();
                    },
                    success: function success(obj) {
                        callback(obj.results);
                    }
                });
            }
        });
    },

    /**
     *
     * @param name
     * @param options
     * @param lang
     */
    setupDefinitionLookup: function setupDefinitionLookup(name, options) {
        // Retrieve form elements.
        name = name || 'search';
        options = options || {};

        this._def[name] = {
            form: $(document[name]),
            results: options.results || $('#results'),
            query: options.query || $(document[name].q),
            clear: options.clear || $('input[name=clear]'),
            language: {
                code: options.langCode || false,
                name: options.langName || 'another language'
            }
        };

        // Form submit function.
        $(document[name]).submit([this._def[name]], function (event) {
            event.preventDefault();
            var form = event.data[0];

            // Performance check
            var query = form.query.val().trim();
            if (query.length < 2) {
                form.query.focus();
                return false;
            }

            // Display loading message
            form.results.html('<div class="center">looking up ' + query + '...</div>');

            // Build endpoint.
            var endpoint = App.root + '/definition/search/' + App.urlencode(query) + (options.langCode ? '?lang=' + options.langCode : '');

            // Start ajax request
            $.ajax({
                url: endpoint,
                type: 'POST',
                error: function error(xhr, status, _error) {
                    App.log('XHR error on search form: ' + xhr.status + ' (' + _error + ')');
                    form.results.html('<div class="center">Seems like we ran into a snag <span class="fa fa-frown-o"></span> try again?</div>');
                },
                success: function success(obj) {
                    if (obj.results.definitions.length > 0) {
                        var html = '<div class="center">' + 'we found <em>' + obj.results.definitions.length + '</em> definitions' + ' for <i>' + obj.results.query + '</i>.' + '</div><ol>';

                        $.each(obj.results.definitions, function (i, def) {
                            html += '<li>' + '<a href="' + def.uri + '">' + def.title + '</a>' + ' <small>(' + def.sub_type + ')</small>' + ' is a ' + def.type + ' that means <i>' + def.translation.practical.en + '</i> in ' + ' <a href="' + def.main_language.uri + '">' + def.main_language.name + '</a>' + '</li>';
                        });

                        form.results.html(html + '</ol>');
                    } else {
                        form.results.html('<div class="center">we couldn\'t find anything matching that query <span class="fa fa-frown-o"></span></div>');
                    }
                }
            });

            //return false;
        });

        // Form clearing.
        this._def[name].clear.click((function () {
            this.query.val('');
            this.results.html('<div class="center">Use this <em>&#10548;</em> to lookup words<br />in ' + this.language.name + '.</div>');
            this.query.focus();
        }).bind(this._def[name]));
    },

    /**
     *
     * @param name
     */
    resetDefinition: function resetDefinition(name) {
        // Performance check.
        if (!this._def[name]) return;

        // Clear form.
        this._def[name].query.value = '';
        this._def[name].results.html('<div class="center">' + 'Use this <em>&#10548;</em> to lookup words' + '<br />in ' + this._def[name].langName + '.' + '</div>');

        this._def[name].query.focus();
    },

    lookupDefinition: function lookupDefinition(name) {
        // Performance check
        if (!this._def[name]) return;

        var query = this._def[name].query.value.trim();
        if (query.length < 2) {
            this._def[name].query.focus();
            return false;
        }

        // Display loading message
        this._def[name].results.html('<div class="center">looking up ' + query + '...</div>');

        // Start ajax request
        $.ajax({
            url: App.root + '/definition/search/' + App.urlencode(query),
            type: 'POST',

            // onError
            error: function error(xhr, status, _error2) {
                Forms.log('XHR error on search form: ' + xhr.status + ' (' + _error2 + ')');
                Forms.setDefinitionResult(name, '<div class="center">' + 'Seems like we ran into a snag <span class="fa fa-frown-o"></span> try again?' + '</div>');
            },

            // onSuccess
            success: function success(obj) {
                if (obj.results.definitions.length > 0) {
                    var html = '<div class="center">' + 'we found <em>' + obj.results.definitions.length + '</em> definitions' + ' for <i>' + obj.results.query + '</i>.' + '</div><ol>';

                    $.each(obj.results.definitions, function (i, def) {
                        html += '<li>' + '<a href="' + def.uri + '">' + def.data + '</a>' + ' <small>(' + def.type + ')</small>' + ' is a word that means <i>' + def.translation.en + '</i> in ' + ' <a href="' + def.language.uri + '">' + def.language.name + '</a>' + '</li>';
                    });

                    Forms.setDefinitionResult(name, html + '</ol>');
                } else {
                    Forms.setDefinitionResult(name, '<div class="center">' + 'we couldn\'t find anything matching that query <span class="fa fa-frown-o"></span>' + '</div>');
                }
            }
        });

        return false;
    },

    setDefinitionResult: function setDefinitionResult(name, html) {
        // Performance check
        if (!this._def[name]) return;

        this._def[name].results.html(html);
    },

    log: function log(msg) {
        if (console) console.log('Forms.js - ' + msg);
    }
};

var Resources = {
    /**
     * Shortcut to verfify a definition title form input.
     */
    checkDefinitionTitle: function checkDefinitionTitle(input) {
        // Performance check.
        if (!input || !input.trim().length) return;

        // Add loading class.
        input.addClass('loading');

        var options = {
            langCode: false,
            onError: (function (xhr, status, error) {
                Resources.log('XHR error: ' + error);
            }).bind(input),
            onSuccess: function onSuccess(obj) {}
        };

        // Lookup definition title.
        this.findDefinitionByTitle(input.value, function () {});
    },

    /**
     * Looks up a definition by title.
     */
    findDefinitionByTitle: function findDefinitionByTitle(title, options) {
        // Performance check.
        if (!title.trim().length) return;
        options = options || {};

        // Build endpoint.
        var endpoint = App.root + '/definition/exists/' + App.urlencode(title) + (options.langCode ? '?lang=' + options.langCode : '');

        // Lookup definitions by title.
        $.ajax({
            url: endpoint,
            type: 'POST',
            error: options.onError,
            success: options.onSuccess
        });
    },

    log: function log(msg) {
        if (console) console.log('Resources.js - ' + msg);
    }
};
//# sourceMappingURL=compiled.js.map