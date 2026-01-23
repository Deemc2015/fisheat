$( document ).ready(function(){
    $('#notification-form input').change(function(){
        $('#notification-form .save').removeAttr('disabled').addClass('active');
    })

    $('#notification-form .mute').click(function(){
        $('#notification-form input').prop('checked', false).removeAttr('checked');
        $('#notification-form .save').removeAttr('disabled').addClass('active');
    })

    $('#notification-form').submit(function(e){
        e.preventDefault();
        var dataForm = {
            'email' : $('#email', this).prop('checked'),
            'sms' : $('#sms', this).prop('checked'),
            'push' : $('#push', this).prop('checked')
        };

        saveForm(dataForm);
    })
})




function saveForm(dataForm) {

    BX.ajax.runComponentAction('ldo:profile.notification',
        'sendForm', {
            mode: 'class',
            data: {dataForm},
        })
        .then(function(response) {

            if(response.status == 'success'){
                location.reload();
            }

        });
}




