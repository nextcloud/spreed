$(document).ready(function(){

	$('#spreed_settings_form').change(function(){
		OC.msg.startSaving('#spreed_settings_msg');
		var post = $( "#spreed_settings_form" ).serialize();
		$.post(OC.generateUrl('/apps/spreed/settings/admin'), post, function(data){
			OC.msg.finishedSaving('#spreed_settings_msg', data);
		}).fail(function(){
			OC.msg.finishedError('#spreed_settings_msg', t('spreed', 'Saving failed'));
		});
	});

});
