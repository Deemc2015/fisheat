$.fn.setCursorPosition = function(pos) {
    if ($(this).get(0).setSelectionRange) {
        $(this).get(0).setSelectionRange(pos, pos);
    } else if ($(this).get(0).createTextRange) {
        var range = $(this).get(0).createTextRange();
        range.collapse(true);
        range.moveEnd('character', pos);
        range.moveStart('character', pos);
        range.select();
    }
};


/*Сортировка товаров*/
$(document).on('click', '.sort-block-product div', function(event) {
    event.preventDefault();
    var sortValue = $(this).attr('name');
    var sortType =  $(this).attr('type');

    $.ajax({
        url: '/local/ajax/sort.php',
        type: 'POST',
        data: {sortten: sortValue, type:sortType},
        dataType: 'json', // Ожидаем JSON-ответ
        success: function(response) {
            console.log('Server response:', response);
            if (response.status === 'success') {
                location.reload();
            }
        },
        error: function(xhr, status, error) {
            console.log("Error:", status, error);
            console.log("Response:", xhr.responseText);
        }
    });
});


$(document).ready(function(){
    $(".mycustom-scroll").mCustomScrollbar();


    $('.filter-change').click(function(){
        $('.mobile_filter_panel').toggleClass('open');
    })
    $('.sort-block').click(function(){
        if($('.sort-block-product').hasClass('open')){
            $('.sort-block-product').removeClass('open');
        }
        else{
            $('.sort-block-product').addClass('open');
        }
    })
    /*$(document).mouseup(function (e) {
        var container = $('.sort-block-product');
        if (container.has(e.target).length === 0) {
            container.removeClass('open');
        }
    });*/

    $('.checked-button').click(function(){
        $(this).toggleClass('active');
    })

    $('.bread-link .bread-toggle').click(function(){
        $('.bread-link').toggleClass('view');
    })

    $('.bread-link .close').click(function(){
        $('.bread-link').removeClass('view');
    })


    $("#profile-form #phone").click(function(){
        $(this).setCursorPosition(3);
    }).mask("+7(999) 999-9999");

    $("#profile-form #dateB").click(function(){
        $(this).setCursorPosition(0);
    }).mask("99.99.9999");

    $('#slider-index').slick({
        slidesToShow: 3,
        slidesToScroll: 1,
        arrows:false,
        dots:true,
        responsive: [
            {
                breakpoint: 1024,
                settings: {
                    slidesToShow: 3,
                    slidesToScroll: 1,
                    infinite: true,
                    dots: true
                }
            },
            {
                breakpoint: 768,
                settings: {
                    slidesToShow: 2,
                    slidesToScroll: 1
                }
            },
            {
                breakpoint: 576,
                settings: {
                    slidesToShow: 1,
                    slidesToScroll: 1
                }
            }
        ],
    })
    $(document).on('click', '.wish-add', function(e) {
        e.preventDefault();
        var id = $(this).attr('data-id');

        $(this).toggleClass('active');
        // Отправляем AJAX-запрос
        $.ajax({
            url: '/local/ajax/wishlist.php', // Путь к обработчику
            type: 'GET',
            data: { id: id },
            success: function(response) {
                /*if(response == 0){
                    $('.header-right__wish span').hide();
                    $('.header-right__wish').removeClass('active');
                }
                else{
                    if(!$('.header-right__wish').hasClass('active')){
                        $('.header-right__wish').addClass('active')
                    }
                    $('.header-right__wish').html('<span>'+response+'</span>');
                }*/
                $('.count-wish').html(response);
                if(response == 0){
                    $('.count-wish').hide();
                }
                else{
                    $('.count-wish').show();
                }

            },
            error: function(xhr, status, error) {
                console.error("Ошибка:", error);

            }
        });




    })



    $('.slider-index-product').slick({
        infinite: true,
        slidesToShow: 5,
        slidesToScroll: 1,
        dots:false,
        arrows:false,
        responsive: [
            {
                breakpoint: 1024,
                settings: {
                    slidesToShow: 3,
                    slidesToScroll: 1,
                    infinite: true,
                    dots: true
                }
            },
            {
                breakpoint: 768,
                settings: {
                    slidesToShow: 3,
                    slidesToScroll: 1
                }
            },
            {
                breakpoint: 576,
                settings: {
                    slidesToShow: 2,
                    slidesToScroll: 1
                }
            }
        ],
    })

    window.LDOInitBasket = function(options) {
        BX.ready(function() {
            BX.LDO.CustomBasket.init(options);
        });
    };
})