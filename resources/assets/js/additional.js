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

	$('[data-settings]').each(function(){
		var countdown = $(this).data('settings');
		$(this).find('select').on('change', function(){
			if (window.settingsTimeout) {
				clearTimeout(window.settingsTimeout);
			}
			window.settingsTimeout = setTimeout(function() {
				var href = window.location.href.split('?')[0] + '?';
				var count = $('[data-settings] select').length;
				$('[data-settings] select').each(function(index) {
					href += $(this).attr('name') + '=' + $(this).val();
					if (index !== count - 1) {
						href += '&';
					}
				});
				document.location.href = href;
			}, countdown);
		})
	})
})