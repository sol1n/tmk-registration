(function( $ ) {
    $.fn.maskedinput = function() {
        this.each(function() {
            var mask = $(this).data('masked-input'),
                placeholder = $(this).data('masked-input-placeholder'),
                clearIfNotMatch = $(this).is('[data-masked-input-clearifnotmatch]');

            $(this).mask(mask, {
                placeholder: placeholder,
                clearIfNotMatch: clearIfNotMatch
            });
        });
    };
}( jQuery ));