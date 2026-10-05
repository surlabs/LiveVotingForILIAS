/**
 * TinyMCE plugin "latex": adds a toolbar button that wraps a LaTeX formula in [tex] ... [/tex].
 * It is registered before the editor is initialised, so TinyMCE does not try to load it from node_modules.
 */
(function () {
    'use strict';

    if (typeof tinymce === 'undefined' || tinymce.PluginManager.get('latex')) {
        return;
    }

    tinymce.PluginManager.add('latex', function (editor) {
        var openDialog = function () {
            editor.windowManager.open({
                title: 'LaTeX',
                body: {
                    type: 'panel',
                    items: [{
                        type: 'textarea',
                        name: 'latex_code',
                        label: 'LaTeX code'
                    }]
                },
                initialData: {
                    latex_code: editor.selection.getContent({format: 'text'})
                },
                buttons: [{
                    type: 'cancel',
                    text: 'Cancel'
                }, {
                    type: 'submit',
                    text: 'Insert',
                    primary: true
                }],
                onSubmit: function (api) {
                    var latex_code = api.getData().latex_code.trim();

                    if (latex_code.length > 0) {
                        editor.insertContent(editor.dom.encode('[tex]' + latex_code + '[/tex]'));
                    }

                    api.close();
                }
            });
        };

        editor.ui.registry.addButton('latex', {
            text: 'LaTeX',
            tooltip: 'Insert LaTeX formula',
            onAction: openDialog
        });

        return {
            getMetadata: function () {
                return {name: 'LaTeX'};
            }
        };
    });
})();
