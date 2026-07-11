document.addEventListener('DOMContentLoaded', function() {
    if (typeof tinymce !== 'undefined') {
        tinymce.PluginManager.add('custom_btn', function(editor, url) {
            editor.addButton('custom_btn', {
                text: 'Notice Box', // Text to display on button
                icon: false,
                onclick: function() {
                    editor.windowManager.open({
                        title: 'Insert Custom Shortcode',
                        body: [
                            {
                                type: 'textbox',
                                name: 'title',
                                label: 'Title',
                                size: 40
                            },
                            {
                                type: 'textbox',
                                name: 'desc',
                                label: 'Description',
                                size: 40,
                                multiline: true,
                                minHeight: 100
                                // Removed 'id' as it's unnecessary; we'll use 'name' to find the field
                            },
                            {
                                type: 'button',
                                name: 'addLink',
                                text: 'Add Link',
                                onclick: function(e) {
                                    var mainWindow = e.control._window; // Reference to the main dialog
                                    openLinkDialog(editor, mainWindow);
                                }
                            },
                            {
                                type: 'listbox',
                                name: 'color',
                                label: 'Color',
                                values: [
                                    {text: 'Green', value: 'success'},
                                    {text: 'Yellow', value: 'warning'},
                                    {text: 'Red', value: 'error'},
                                    {text: 'Gray', value: 'secondary'},
                                ]
                            }
                        ],
                        onsubmit: function(e) {
                            var data = e.data;

                            // Escape single quotes in the description to prevent breaking the shortcode
                            var escapedDesc = data.desc.replace(/'/g, "\\'");

                            var shortcode = '[post-notice title="' + data.title + '" content=\'' + escapedDesc + '\' color="' + data.color + '"]';
                            editor.insertContent(shortcode);
                        }
                    });
                }
            });

            /**
             * Function to open the Link Insertion Dialog
             * @param {Object} editor - TinyMCE editor instance
             * @param {Object} mainWindow - Reference to the main dialog window
             */
            function openLinkDialog(editor, mainWindow) {
                editor.windowManager.open({
                    title: 'Insert Link',
                    body: [
                        {
                            type: 'textbox',
                            name: 'url',
                            label: 'URL',
                            size: 40,
                            placeholder: 'https://example.com'
                        },
                        {
                            type: 'textbox',
                            name: 'text',
                            label: 'Link Text',
                            size: 40,
                            placeholder: 'Enter link text'
                        },
                        {
                            type: 'checkbox',
                            name: 'newTab',
                            label: 'Open in new tab'
                        }
                    ],
                    onsubmit: function(e) {
                        var data = e.data;
                        var url = data.url.trim();
                        var text = data.text.trim() || url;
                        var newTab = data.newTab ? ' target="_blank"' : '';

                        if (url === '') {
                            editor.windowManager.alert('URL cannot be empty.');
                            return;
                        }

                        var linkHtml = '<a href="' + url + '"' + newTab + '>' + text + '</a>';

                        // Get the current value of the Description field using the correct selector
                        var descField = mainWindow.find('name=desc')[0];
                        if (descField) {
                            var currentDesc = descField.value();
                            // Append the new link with a preceding space
                            var updatedDesc = currentDesc + ' ' + linkHtml;
                            // Update the Description field with the new content
                            descField.value(updatedDesc);
                        } else {
                            editor.windowManager.alert('Description field not found.');
                        }

                        // Close the link dialog
                        editor.windowManager.close();
                    }
                });
            }
        });
    } else {
        console.error("TinyMCE is not loaded");
    }
});
