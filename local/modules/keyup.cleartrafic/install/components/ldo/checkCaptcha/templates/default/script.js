$(document).ready(function(){
    $('.afterFormLink span').click(function(){
        $('.modalError').show();
    })
    $('#modalError').submit(function(e){
        var $that = $(this),
        formData = new FormData($that.get(0));
        $.ajax({
            url:'/personal_filter/ajax.php',
            processData: false,
            contentType: false,
            type: 'POST',
            data: formData,
            success: function(response) {
               var data = JSON.parse(response); 
               if(data == 'success'){
                    console.log(response);
                    $('.modalError').hide();
                    $('.modal-ip-captcha').removeClass('show');
                    $('.succes-modal').show();
                    setTimeout(function(){
                        location.reload(); 
                    }, 2000);
                }
                else if(data == 'time')
                {
                    $('.modalError').hide();
                    $('.modal-ip-captcha').removeClass('show');
                    $('.succes-modal').show();
                    $('.succes-modal .title').html('Отправка сообщения не чаще 1 раза в минуту');
                    $('.succes-modal .title + p').remove();
                    setTimeout(function(){
                        location.reload(); 
                    }, 2000);
                   
                }

            },
            error: function(jqXHR, textStatus, errorThrown){ // Ошибка
                console.log('Error: '+ errorThrown);
            }
        });
        e.preventDefault();
    })

})