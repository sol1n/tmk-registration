function toLatin(text){

    var ru = ['Я','я','Ю','ю','Ч','ч','Ш','ш','Щ','щ','Ж','ж','А','а','Б','б','В','в','Г','г','Д','д','Е','е','Ё','ё','З','з','И','и','Й','й','К','к','Л','л','М','м','Н','н', 'О','о','П','п','Р','р','С','с','Т','т','У','у','Ф','ф','Х','х','Ц','ц','Ы','ы','Ь','ь','Ъ','ъ','Э','э'];

    var en = ['Ya','ya','Yu','yu','Ch','ch','Sh','sh','Sh','sh','Zh','zh','A','a','B','b','V','v','G','g','D','d','E','e','E','e','Z','z','I','i','J','j','K','k','L','l','M','m','N','n', 'O','o','P','p','R','r','S','s','T','t','U','u','F','f','H','h','C','c','Y','y','`','`','\'','\'','E', 'e'];

    for(var i=0, len = ru.length; i < len; i++){
        var reg = new RegExp(ru[i], "g");
        text = text.replace(reg, en[i]);
    }
    return text;
}

$(function(){
	$(document).on('click', '.more-lecture', function(){
		var form = $(this).parents('.member-additional-form');
		var count = $(this).parents('.form-block').find('.lecture-form').length;

		$.ajax({
			url: '/lecture',
			method: 'GET',
			data: {
				index: count
			},
			success: function(response) {
				var newform = $(response);
				newform.find('.lecture-textarea').trumbowyg({
					btns: [['formatting'], ['bold', 'italic'], ['justifyLeft', 'justifyCenter', 'justifyRight', 'justifyFull']],
					lang: 'ru'
				});
				newform.find('.js-file-input').fileInput();
				form.after(newform);
			}
		});

		// var newform = form.clone().hide();
		// form.after(newform);
		// newform.fadeIn();
		// newform.find('textarea, select, input').each(function(){
		// 	$(this).val('');
		// 	var name = $(this).data('name');
		// 	$(this).attr('name', name);
		// });
		// //.trumbowyg('empty');
		// console.log(newform.find('.lecture-textarea'));
		// newform.find('.js-file-input').removeClass('is-filled').removeClass('is-image');
		// $(this).hide();
		// newform.find('input').first().focus();
		// newform.find('.uploaded-file-link').remove();
		// newform.find('.js-file-input').fileInput();

		
		// newform.removeClass('lecture-form-' + (count - 1));
		// newform.addClass('lecture-form-' + count);
		return false;
	});

	$(document).on('keyup', '[data-translate-for]', function(){
		var text = $(this).val();
		var target = $($(this).data('translate-for'));

		target.val(toLatin(text));
	});

	$.trumbowyg.svgPath = '/assets/images/icons.svg';

	$('.lecture-textarea').trumbowyg({
		btns: [['formatting'], ['bold', 'italic'], ['justifyLeft', 'justifyCenter', 'justifyRight', 'justifyFull']],
		lang: 'ru'
	});
})