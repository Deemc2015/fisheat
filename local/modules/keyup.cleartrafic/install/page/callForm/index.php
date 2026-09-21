<?php
define("NO_KEEP_STATISTIC", true);
require_once($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_before.php");
use Bitrix\Main\Loader;
if(!Loader::IncludeModule('keyup.cleartrafic')){
    echo "Не установлен модуль фильтрации трафика";
}
?>
<script src="https://code.jquery.com/jquery-3.7.1.min.js" integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>
<style>
.page{
    width: 320px;
    margin: 0 auto;
    padding-top: 40px;
}
.title{
    font-size: 18px;
    text-align: center;
}
textarea{
    width: 100%;
    margin-top: 26px;
    margin-bottom: 26px;

}
button{
    width: 250px;
    text-align: center;
    padding: 10px;
    margin: 0 auto;
    display: block;
}

</style>
<div class="page">
    <div class="modal-black">
        <div class="title">Для связи с администрацией, напишите в форму ниже.</div>
        <form action="#" method="post">
            <textarea name="" id="message" cols="30" rows="10"></textarea>
            <button type="submit">Отправить</button>
        </form>
    </div>
</div>

<script>
    $('.modal-black form').submit(function(e){
        e.preventDefault();
        var message = $('#message',this).val();

        if(message)
        {
            $.ajax({
                url:'/callForm/form.php',
                type: "get",
                data: {
                    data : message
                },
                success: function(response) {
                    if(response){
                        location.reload();
                    }
                },
                error: function(jqXHR, textStatus, errorThrown){ // Ошибка
                    console.log('Error: '+ errorThrown);
                }
            });
        }
    })
</script>

