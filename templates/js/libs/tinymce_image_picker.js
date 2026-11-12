/**
 * Select local images for the question editor using its existing inline image support.
 */
(function () {
    'use strict';

    if (typeof tinymce === 'undefined' || tinymce.PluginManager.get('xlvo_image_picker')) {
        return;
    }

    tinymce.PluginManager.add('xlvo_image_picker', function (editor) {
        var max_size = 2 * 1024 * 1024;
        var allowed_types = ['image/png', 'image/jpeg', 'image/gif'];

        function showError(key) {
            if (!editor.removed) {
                editor.windowManager.alert(window.xlvoImagePickerMessages[key]);
            }
        }

        editor.options.set('file_picker_types', 'image');
        editor.options.set('file_picker_callback', function (callback, value, meta) {
            if (meta.filetype !== 'image') {
                return;
            }

            var input = document.createElement('input');
            input.type = 'file';
            input.accept = allowed_types.join(',');
            input.hidden = true;
            document.body.appendChild(input);

            input.addEventListener('cancel', function () {
                input.remove();
            }, {once: true});

            input.addEventListener('change', function () {
                var file = input.files[0];
                input.remove();

                if (!file) {
                    return;
                }
                if (allowed_types.indexOf(file.type) === -1) {
                    showError('invalid_type');
                    return;
                }
                if (file.size > max_size) {
                    showError('too_large');
                    return;
                }

                var reader = new FileReader();
                reader.addEventListener('error', function () {
                    showError('read_error');
                }, {once: true});
                reader.addEventListener('load', function () {
                    var image = new Image();
                    image.addEventListener('error', function () {
                        showError('read_error');
                    }, {once: true});
                    image.addEventListener('load', function () {
                        if (!editor.removed) {
                            // Persist the data URI, not a temporary blob URL.
                            callback(reader.result, {alt: file.name});
                        }
                    }, {once: true});
                    image.src = reader.result;
                }, {once: true});
                reader.readAsDataURL(file);
            }, {once: true});

            input.click();
        });
    });
})();
