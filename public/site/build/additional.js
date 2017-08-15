$(function(){
	$(document).on('click', '.more-lecture', function(){
		var form = $(this).parents('.member-additional-form');
		var newform = form.clone().hide();
		form.after(newform);
		newform.fadeIn();
		newform.find('textarea, select, input').each(function(){
			$(this).val('');
		});
		newform.find('.js-file-input').removeClass('is-filled').removeClass('is-image');
		$(this).hide();
		newform.find('input').first().focus();
		newform.find('.uploaded-file-link').remove();
		newform.find('.js-file-input').fileInput();
		return false;
	})
})