$( document ).ready(function(){
    $('#profile-form').submit(function(e){
        e.preventDefault();
        saveForm($(this).serialize());
    })
    $('.button-line__delete-account').click(function(){
        $('.wrp,.modal-delete').addClass('show');
    })
    $('.modal-delete .close-modal,.modal-delete .cancel').click(function(){
        $('.wrp,.modal-delete').removeClass('show');
    })

    $('.modal-delete .delete').click(function(){
        deleteUser();
    })
    $('#profile-form .input-edit').each(function(){
        var that = this;
        $('.clear',this).click(function(){
            $('input',that).val('');
        })
    })
})



function saveForm(dataForm) {

    BX.ajax.runComponentAction('ldo:profile.data',
        'sendForm', {
            mode: 'class',
            data: {post: dataForm},
        })
        .then(function(response) {
            if(response.data.length !== 0){


            }
        });
}

function deleteUser() {

    BX.ajax.runComponentAction('ldo:profile.data',
        'deleteUser', {
            mode: 'class',
            data: {delete: 'Y'},
        })
        .then(function(response) {
            if(response.data.length !== 0){


            }
        });
}


