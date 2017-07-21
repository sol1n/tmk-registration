(function ( $ ) {
    $.fn.actionConfirm = function( options ) {
        this.each(function() {
            var message = $(this).data('action-confirm');

            $(this).click(function (e) {
                if (!confirm(message)) {
                    return false;
                }
            });
        });
    };
}( jQuery ));