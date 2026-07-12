jQuery.fn.extend({
    createRepeater: function (options = {}) {
        var hasOption = function (optionKey) {
            return options.hasOwnProperty(optionKey);
        };

        var option = function (optionKey) {
            return options[optionKey];
        };

        var generateId = function (string) {
            return string
                .replace(/\[/g, '_')
                .replace(/\]/g, '')
                .toLowerCase();
        };

        var addItem = function (items, key, fresh = true) {
            var itemContent = items;
            var group = itemContent.data("group");
            var item = itemContent;
            var input = item.find('input,select,textarea,div');

            input.each(function (index, el) {
                var attrName = $(el).data('name');
                var skipName = $(el).data('skip-name');

                if (skipName != true) {
                    $(el).attr("name", group + "_" + (key + 1) + "_" + attrName);
                } else {
                    if (attrName != 'undefined') {
                        $(el).attr("name", attrName);
                    }
                }

                if (fresh == true) {
                    $(el).attr('value', '');
                }

                $(el).attr('id', generateId($(el).attr('name')));
                $(el).parent().find('label').attr('for', generateId($(el).attr('name')));

                var label = $(el).parent().find('label');
                if (label.data('ngay') === 'ngay') {
                    label.text(`Tiêu đề ngày ${key + 1}`);
                    $(el).attr('placeholder', `Tiêu đề ngày ${key + 1}`);
                }
            })

            var itemClone = items;

            /* Handling remove btn */
            var removeButton = itemClone.find('.remove-btn');

            if (key == 0) {
                removeButton.attr('disabled', true);
            } else {
                removeButton.attr('disabled', false);
            }

            removeButton.attr('onclick', '$(this).parents(\'.items\').remove()');
            key--;

            var newItem = $("<div class='items'>" + itemClone.html() + "<div/>");
            newItem.attr('data-index', key)

            newItem.appendTo(repeater);

            var descriptionDiv = itemClone.find('[data-name="description_add"]');
            var hiddenInput = itemClone.find('input[data-name="description_content_add"]');
            initializeQuill(`#` + descriptionDiv.attr('id'), `#` + hiddenInput.attr('id'));

            function initializeQuill(editorSelector, hiddenInputSelector) {
                var toolbarOptions = [
                    [{'font': []}],
                    [{'header': [1, 2, 3, 4, 5, 6, false]}],
                    ['bold', 'italic', 'underline', 'strike'],
                    ['link'],
                    [{'header': 2}],
                    [{'list': 'ordered'}, {'list': 'bullet'}],
                    [{'indent': '-1'}, {'indent': '+1'}],
                    [{'color': []}, {'background': []}],
                    [{'align': []}]
                ];

                var editorElement = document.querySelector(editorSelector);
                if (editorElement) {
                    var quill = new Quill(editorElement, {
                        modules: { toolbar: toolbarOptions },
                        theme: 'snow'
                    });

                    $('form').on('submit', function () {
                        var content = quill.root.innerHTML;
                        $(hiddenInputSelector).val(content);
                    });
                }
            }
        };

        /* find elements */
        var repeater = this;
        var items = repeater.find(".items");
        var key = 0;
        var addButton = repeater.find('.repeater-add-btn');

        items.each(function (index, item) {
            items.remove();
            if (hasOption('showFirstItemToDefault') && option('showFirstItemToDefault') == true) {
                addItem($(item), key);
                key++;
            } else {
                if (items.length > 1) {
                    addItem($(item), key);
                    key++;
                }
            }
        });

        /* handle click and add items */
        addButton.on("click", function () {
            addItem($(items[0]), key);
            key++;
        });
    }
});
