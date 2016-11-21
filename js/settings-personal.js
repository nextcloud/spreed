$(document).ready(function(){

    $('#spreed_settings_form').change(function(){
        OC.msg.startSaving('#spreed_settings_msg');
        var post = $("#spreed_settings_form").serialize();
        $.post(OC.generateUrl('/apps/spreed/settings/personal'), post, function(data){
            OC.msg.finishedSaving('#spreed_settings_msg', data);
        });
    });

});
