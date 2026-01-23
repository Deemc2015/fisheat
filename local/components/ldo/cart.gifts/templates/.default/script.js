$( document ).ready(function(){
    BX.addCustomEvent('OnBasketChange', function () {
        getSum();
    });

})



function getSum() {
    BX.ajax.runComponentAction('ldo:cart.gifts',
        'getSum', {
            mode: 'class',
            data: {post: '111'},
        })
        .then(function(response) {

            var progress =  100 - response.data;

            console.log(response.data);

            $('.gifts-block .progress-fill').css('height',progress+'%');

        });
}




