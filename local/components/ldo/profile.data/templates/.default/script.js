$(document).ready(function(){
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
    const saveButton = document.querySelector('.button-line__save');
    const originalText = saveButton.innerHTML;
    saveButton.disabled = true;
    saveButton.innerHTML = 'Сохранение...';

    BX.ajax.runComponentAction('ldo:profile.data', 'sendForm', {
        mode: 'class',
        data: {post: dataForm},
    })
        .then(function(response) {
            // Восстанавливаем кнопку
            saveButton.disabled = false;
            saveButton.innerHTML = originalText;

            if (response.data && response.data.success) {
                //location.reload();
            } else {
                // Ошибка
                var errorMsg = response.data?.error || 'Произошла ошибка при сохранении';
                showErrorModal(errorMsg);
            }
        })
        .catch(function(error) {
            // Восстанавливаем кнопку
            saveButton.disabled = false;
            saveButton.innerHTML = originalText;

            showErrorModal(error);
        });
}

function deleteUser() {
    BX.ajax.runComponentAction('ldo:profile.data', 'deleteUser', {
        mode: 'class',
        data: {delete: 'Y'},
    })
        .then(function(response) {
            if (response.data && response.data.success) {
                // Успешно удален - редирект на главную
                window.location.href = '/';
            } else {
                showErrorModal(response.data?.error || 'Ошибка при удалении аккаунта');
            }
        })
        .catch(function(error) {
            showErrorModal('Ошибка соединения с сервером');
        });
}
function showErrorModal(errorText) {
    $('.modal-delete .button-modal').remove();
    $('.modal-delete .top-title').html('Ошибка').css('color', '#ff0000');
    $('.modal-delete .text-modal').html(errorText);
    $('.wrp,.modal-delete').addClass('show');

}


