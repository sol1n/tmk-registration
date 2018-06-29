$(document).ready(function () {
    $('[data-masked-input]').maskedinput();
    $('[data-text-count]').textCounter();
    $('[data-action-confirm]').actionConfirm();

    $('#form-company-name').on('change', function () {
        var url = $(this).val();
        if (url) {
            window.location = url;
        }
        return false;
    });

    $('.js-form-status').on('change', function () {
        var statuses = $(this).val();

        $('.js-member-status').removeClass('active');

        statuses.forEach(function (status) {
            if ($('.js-member-status-' + status).length) {
                $('.js-member-status-' + status).addClass('active');
            }
        });
    });

    $('[data-member-edit]').click(function () {
        var $memberRow = $( $(this).data('member-edit') ),
            $currentRow = $(this).closest('tr');

        if ( $currentRow.hasClass('active') ) {
            $currentRow.removeClass('active');
            $memberRow.removeClass('active');
        } else {
            $('.members-table-row').removeClass('active');
            $currentRow.addClass('active');

            $('.js-members-table-row-edit').removeClass('active');
            $memberRow.addClass('active');

            setTimeout(function () {
                $('html, body').animate({
                    scrollTop: $memberRow.offset().top - 90
                }, 500);
            }, 600);
        }

        return false;
    });

    $('.js-chosen-select').chosen({
        no_results_text: 'Ничего не найдено'
    });

    $('.js-file-input').fileInput();
});