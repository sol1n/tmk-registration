(function( $ ) {
    $.fn.fileInput = function() {
        this.each(function() {
            var $fileInputBlock = $(this),
                $fileInputField = $fileInputBlock.find('.js-file-input-field'),
                $fileInputLink = $fileInputBlock.find('.js-file-input-link'),
                $fileInputPhoto = $fileInputBlock.find('.js-file-input-photo'),
                $fileInputName = $fileInputBlock.find('.js-file-input-name'),
                $fileInputDel = $fileInputBlock.find('.js-file-input-del'),
                fileInputBlockClasses = '';

            fileInputBlockClasses = $fileInputField.val() ? 'is-filled' : 'is-empty';

            $fileInputBlock.addClass(fileInputBlockClasses);

            $fileInputField.hover(function () {
                $fileInputBlock.addClass('is-hover');
            }, function () {
                $fileInputBlock.removeClass('is-hover');
            });

            $fileInputField.on('change', function () {
                var value = $(this).val();
                var imageType = /image.*/;
                var file = $(this)[0].files[0];

                if (value) {
                    $fileInputName.html(value);
                    $fileInputBlock.addClass('is-filled').removeClass('is-empty');
                } else {
                    $fileInputName.html('');
                    $fileInputBlock.removeClass('is-filled').addClass('is-empty');
                }

                if (file && file.type.match(imageType)) {
                    $fileInputBlock.addClass('is-image');
                    var reader = new FileReader();

                    reader.onload = function(e) {
                        $fileInputPhoto.html('');

                        var img = new Image();
                        img.src = reader.result;

                        $fileInputPhoto[0].appendChild(img);
                    };

                    reader.readAsDataURL(file);
                } else {
                    $fileInputBlock.removeClass('is-image');
                }
            });

            $fileInputDel.on('click', function () {
                $fileInputField.val('');
                $fileInputField.trigger('change');

                return false;
            });

            $fileInputField.trigger('change');
        });

        return this;
    };
}( jQuery ));