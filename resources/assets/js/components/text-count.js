(function ( $ ) {
    $.fn.textCounter = function( options ) {
        this.each(function(i, el) {
            var textField = $(el),
                maxLength = textField.attr('maxlength'),
                counterBlock = $(textField.data('text-count-block'));

            function update() {
                var textLength = textField.val().length,
                    textRemaining = maxLength - textLength;
                counterBlock.html(textRemaining);
            }

            textField.keyup(function() {
                update();
            });

            update();
        });
    };
}( jQuery ));