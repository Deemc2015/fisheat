
/*Слайдер акций на главной — scroll-snap + точки + автопрокрутка (без slick).
  Инициализируется независимо от jQuery/slick (на DOMContentLoaded),
  чтобы ошибка в других скриптах не «убивала» слайдер.*/
(function () {
    function initSliders() {
        var tracks = document.querySelectorAll('[data-slider-track]');
        if (!tracks.length) {
            return;
        }

        var AUTOPLAY_DELAY = 5000;

        function build(track) {
            var root = track.parentNode;
            var dotsWrap = track.nextElementSibling;
            if (!dotsWrap || !dotsWrap.hasAttribute('data-slider-dots')) {
                dotsWrap = root ? root.querySelector('[data-slider-dots]') : null;
            }

            var items = Array.prototype.slice.call(track.children);
            if (!items.length) {
                return;
            }

            var timer = null;
            var dotTimer = null;
            var resizeTimer = null;

            function perView() {
                var itemWidth = items[0] ? items[0].getBoundingClientRect().width : 0;
                if (itemWidth <= 0) {
                    return 1;
                }
                var gap = gapSize();
                var count = Math.round((track.clientWidth + gap) / (itemWidth + gap));
                return Math.max(1, Math.min(count, items.length));
            }

            function pageCount() {
                return Math.max(1, Math.ceil(items.length / perView()));
            }

            function gapSize() {
                var styles = window.getComputedStyle(track);
                var gap = parseFloat(styles.columnGap || styles.gap);
                return isNaN(gap) ? 0 : gap;
            }

            function currentPage() {
                var step = items[0].getBoundingClientRect().width + gapSize();
                if (step <= 0) {
                    return 0;
                }
                return Math.round(track.scrollLeft / (step * perView()));
            }

            function renderDots() {
                if (!dotsWrap) {
                    return;
                }
                var pages = pageCount();
                dotsWrap.innerHTML = '';
                for (var i = 0; i < pages; i++) {
                    var dot = document.createElement('button');
                    dot.type = 'button';
                    dot.className = 'slider-dot';
                    dot.setAttribute('aria-label', 'Слайд ' + (i + 1));
                    dot.addEventListener('click', function (index) {
                        return function () {
                            goToPage(index, true);
                        };
                    }(i));
                    dotsWrap.appendChild(dot);
                }
                syncDots();
            }

            function syncDots() {
                if (!dotsWrap) {
                    return;
                }
                var active = currentPage();
                var dots = dotsWrap.children;
                for (var i = 0; i < dots.length; i++) {
                    dots[i].classList.toggle('is-active', i === active);
                }
            }

            function goToPage(page, userAction) {
                var pages = pageCount();
                var target = Math.max(0, Math.min(page, pages - 1));
                var index = Math.min(target * perView(), items.length - 1);
                var delta = items[index].getBoundingClientRect().left - track.getBoundingClientRect().left;

                if ('scrollBehavior' in document.documentElement.style) {
                    track.scrollBy({ left: delta, behavior: 'smooth' });
                } else {
                    track.scrollLeft += delta;
                }

                if (userAction) {
                    restartAuto();
                }
            }

            function nextPage() {
                var pages = pageCount();
                var next = currentPage() + 1;
                goToPage(next >= pages ? 0 : next, false);
            }

            function stopAuto() {
                if (timer) {
                    clearInterval(timer);
                    timer = null;
                }
            }

            function startAuto() {
                stopAuto();
                // Автопрокрутка только у слайдеров с точками (акции)
                if (dotsWrap && pageCount() > 1) {
                    timer = setInterval(nextPage, AUTOPLAY_DELAY);
                }
            }

            function restartAuto() {
                stopAuto();
                startAuto();
            }

            track.addEventListener('scroll', function () {
                window.clearTimeout(dotTimer);
                dotTimer = window.setTimeout(syncDots, 80);
            });

            root.addEventListener('mouseenter', stopAuto);
            root.addEventListener('mouseleave', startAuto);
            track.addEventListener('touchstart', stopAuto, { passive: true });
            track.addEventListener('touchend', restartAuto, { passive: true });

            /*Перетаскивание мышью — для треков с data-slider-drag (товары).
              Колесо мыши и вертикальный жест НЕ перехватываем, чтобы не ломать скролл страницы.*/
            if (track.hasAttribute('data-slider-drag')) {
                (function enableDragScroll() {
                    var pointerId = null;
                    var dragging = false;
                    var moved = false;
                    var startX = 0;
                    var startY = 0;
                    var startScroll = 0;

                    function release() {
                        if (pointerId !== null && track.releasePointerCapture) {
                            try { track.releasePointerCapture(pointerId); } catch (err) {}
                        }
                        pointerId = null;
                        dragging = false;
                        track.classList.remove('is-grabbing');
                        track.classList.remove('is-dragging');
                    }

                    // Нативный drag ссылок/картинок мешает: mouseup не приходит и слайдер «залипает»
                    track.addEventListener('dragstart', function (e) {
                        e.preventDefault();
                    });

                    track.addEventListener('pointerdown', function (e) {
                        // Только мышь, только ЛКМ; тач и перо обрабатываются нативно
                        if (e.pointerType !== 'mouse' || e.button !== 0) {
                            return;
                        }
                        // Захват указателя здесь не берём: иначе click уйдёт на трек и ссылка не откроется
                        pointerId = e.pointerId;
                        dragging = false;
                        moved = false;
                        startX = e.clientX;
                        startY = e.clientY;
                        startScroll = track.scrollLeft;
                        track.classList.add('is-grabbing');
                        stopAuto();
                    });

                    window.addEventListener('pointermove', function (e) {
                        if (pointerId === null || e.pointerId !== pointerId) {
                            return;
                        }
                        var dx = e.clientX - startX;
                        var dy = e.clientY - startY;

                        if (!dragging) {
                            // Жест вверх/вниз — это скролл страницы: слайдер не трогаем и не мешаем
                            if (Math.abs(dy) > Math.abs(dx)) {
                                release();
                                return;
                            }
                            // Пока сдвиг мал — считаем это кликом, а не перетаскиванием
                            if (Math.abs(dx) < 5) {
                                return;
                            }
                            dragging = true;
                            moved = true;
                            track.classList.add('is-dragging');
                            if (track.setPointerCapture) {
                                try { track.setPointerCapture(e.pointerId); } catch (err) {}
                            }
                        }

                        track.scrollLeft = startScroll - dx;
                    });

                    window.addEventListener('pointerup', function (e) {
                        if (pointerId !== null && e.pointerId === pointerId) {
                            track.scrollLeft = track.scrollLeft; // пересчёт scroll-snap
                            release();
                            startAuto();
                        }
                    });
                    window.addEventListener('pointercancel', function (e) {
                        if (pointerId !== null && e.pointerId === pointerId) {
                            release();
                        }
                    });
                    track.addEventListener('lostpointercapture', release);
                    window.addEventListener('blur', release);

                    // После перетаскивания подавляем click по ссылке; обычный клик проходит
                    track.addEventListener('click', function (e) {
                        if (moved) {
                            e.preventDefault();
                            e.stopPropagation();
                            moved = false;
                        }
                    }, true);
                })();
            }

            window.addEventListener('resize', function () {
                window.clearTimeout(resizeTimer);
                resizeTimer = window.setTimeout(function () {
                    renderDots();
                    startAuto();
                }, 200);
            });

            renderDots();
            startAuto();
        }

        for (var i = 0; i < tracks.length; i++) {
            build(tracks[i]);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initSliders);
    } else {
        initSliders();
    }
})();

/*Иконка поиска в мобильном футере.
  На главной — фокус на поле поиска на странице;
  на остальных страницах — показ поля поиска под шапкой (повторный клик/клик вне — скрыть).*/
(function () {
    function initFooterSearch() {
        var link = document.querySelector('.search-footer-link');
        if (!link) {
            return;
        }
        var headerSearch = document.querySelector('.search-mobile-header');
        var isHome = window.location.pathname === '/' || window.location.pathname === '/index.php';

        var headerEl = document.querySelector('header');

        function positionHeaderSearch() {
            if (!headerEl || !headerSearch) {
                return;
            }
            var rect = headerEl.getBoundingClientRect();
            headerSearch.style.top = (rect.bottom + (window.pageYOffset || 0)) + 'px';
        }

        // Фокус ставим после скролла к шапке, preventScroll — чтобы не было второго прыжка
        function focusField(input) {
            if (!input) {
                return;
            }
            window.setTimeout(function () {
                try {
                    input.focus({ preventScroll: true });
                } catch (err) {
                    input.focus();
                }
            }, 350);
        }

        // Скролл к шапке сайта
        function scrollToHeader() {
            try {
                window.scrollTo({ top: 0, behavior: 'smooth' });
            } catch (err) {
                window.scrollTo(0, 0);
            }
        }

        function openHeaderSearch() {
            if (!headerSearch) {
                return;
            }
            positionHeaderSearch();
            headerSearch.classList.add('open');
            scrollToHeader();
            focusField(headerSearch.querySelector('input'));
        }

        function closeHeaderSearch() {
            if (headerSearch) {
                headerSearch.classList.remove('open');
            }
        }

        link.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();

            if (isHome) {
                // На главной поле поиска уже есть на странице: скролл к шапке + фокус
                scrollToHeader();
                var homeInput = document.getElementById('title-search-input');
                if (homeInput) {
                    var wrap = homeInput.closest('.search-mobile');
                    if (wrap) {
                        wrap.classList.add('open');
                    }
                    focusField(homeInput);
                }
                return;
            }

            if (!headerSearch) {
                return;
            }
            if (headerSearch.classList.contains('open')) {
                // Повторный клик — скрыть
                closeHeaderSearch();
            } else {
                openHeaderSearch();
            }
        });

        // Клик вне поля поиска — скрыть
        document.addEventListener('click', function (e) {
            if (!headerSearch || !headerSearch.classList.contains('open')) {
                return;
            }
            if (headerSearch.contains(e.target)) {
                return;
            }
            if (e.target.closest && e.target.closest('.search-footer-link')) {
                return;
            }
            closeHeaderSearch();
        });

        window.addEventListener('scroll', positionHeaderSearch, { passive: true });
        window.addEventListener('resize', positionHeaderSearch);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initFooterSearch);
    } else {
        initFooterSearch();
    }
})();

$.fn.setCursorPosition = function(pos) {
    var el = this[0];
    if (el) {
        if (el.setSelectionRange) {
            el.focus();
            el.setSelectionRange(pos, pos);
        } else if (el.createTextRange) {
            var range = el.createTextRange();
            range.collapse(true);
            range.moveEnd('character', pos);
            range.moveStart('character', pos);
            range.select();
        }
    }
    return this;
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

    $('.openmodalauth').click(function(e){
        e.preventDefault();
        $('.modal-auth, .wrp').addClass('show');
    })


    $('.not-authorized .cart-page,.not-authorized.personal-link-footer').click(function(e){
        e.preventDefault();
        $('.modal-auth, .wrp').addClass('show');
    })




    $(".mycustom-scroll").mCustomScrollbar();

    // Единый обработчик добавления в корзину (capture-фаза).
    // Регистрируется ОДИН раз и перехватывает клик ДО обработчиков компонентов
    // (JCCatalogItem, listing-catalog и т.п.), поэтому запрос всегда один —
    // даже если компонент выведен на странице несколько раз и/или закэширован.
    if (!window.__ldoAddCartBound) {
        window.__ldoAddCartBound = true;

        document.addEventListener('click', function(e) {
            var btn = e.target && e.target.closest ? e.target.closest('.addCart') : null;
            if (!btn) {
                return;
            }

            // Гасим любые другие обработчики добавления на этой кнопке
            e.preventDefault();
            e.stopImmediatePropagation();

            if (btn.dataset.adding === 'Y') {
                return;
            }
            btn.dataset.adding = 'Y';

            var id = btn.getAttribute('data-id');
            if (!id || typeof BX === 'undefined' || !BX.ajax) {
                btn.dataset.adding = 'N';
                return;
            }

            BX.ajax({
                method: 'POST',
                dataType: 'json',
                url: '/?action=ADD2BASKET&id=' + encodeURIComponent(id),
                data: {
                    ajax_basket: 'Y',
                    sessid: BX.bitrix_sessid()
                },
                onsuccess: function(data) {
                    if (data && data.STATUS === 'OK') {
                        if (typeof BX !== 'undefined' && BX.addClass) {
                            BX.addClass(btn, 'in_cart');
                        }
                        // Обновляем компонент корзины (счётчик/мини-корзина)
                        if (typeof BX !== 'undefined') {
                            BX.onCustomEvent('OnBasketChange');
                        }
                        if (typeof updateBasketCount === 'function') {
                            updateBasketCount();
                        }
                    }
                },
                oncomplete: function() {
                    btn.dataset.adding = 'N';
                }
            });
        }, true);
    }


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
        $('.count-people-block__count').toggleClass('active');
    })

    $('.bread-link .bread-toggle').click(function(){
        $('.bread-link').toggleClass('view');
    })

    $('.bread-link .close').click(function(){
        $('.bread-link').removeClass('view');
    })

    $("#profile-form #phone").click(function(){
        $(this).setCursorPosition(3);
    }).mask("+7 (999) 999-99-99");


    $('.modal-auth #phone-user').click(function(){
        $(this).setCursorPosition(3);
    }).mask("+7 (999) 999-99-99");


    $("#profile-form #dateB").click(function(){
        $(this).setCursorPosition(0);
    }).mask("99.99.9999");

    $(document).mouseup(function(e) {
        // Закрываем любое модальное окно при клике вне его
        if ($(e.target).closest('.modal-auth, .modal-auth-step, .modal-delete').length === 0) {
            $('.modal-auth, .modal-auth-step, .modal-delete, .wrp').removeClass('show');
            $('.modal-auth form, .modal-auth-step form, .modal-delete form').trigger('reset');
        }
    });

    if ($.fn.slick) $('.gifts-list').slick({
        slidesToShow: 7,
        slidesToScroll: 1,
        arrows:false,
        dots:true,
        responsive: [
            {
                breakpoint: 1024,
                settings: {
                    slidesToShow: 7,
                    slidesToScroll: 1,
                    infinite: true,
                    dots: true
                }
            },
            {
                breakpoint: 768,
                settings: {
                    slidesToShow: 4,
                    slidesToScroll: 1
                }
            },
            {
                breakpoint: 576,
                settings: {
                    slidesToShow: 3,
                    slidesToScroll: 1
                }
            }
        ],
    })



    $(document).on('click', '.product-item-container .wish-add', function(e) {
        e.preventDefault();
        var id = $(this).attr('data-id');
        $(this).toggleClass('active');

        var remove = false;

        if(!$(this).hasClass('active')){
            var remove = true;
        }
        // Отправляем AJAX-запрос
        $.ajax({
            url: '/local/ajax/wishlist.php', // Путь к обработчику
            type: 'GET',
            data: { id: id },
            success: function(response) {

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
    $('.mobile-menu').click(function(){
        $('.menu-view-mobile').toggleClass('open');
    })

    $('.menu-view-mobile__close').click(function(){
        $('.menu-view-mobile').removeClass('open');
    })


    $('.burger').click(function(){
        $('.mobile-main-menu ').toggleClass('open');
    })
    $('.mobile-main-menu .close-menu').click(function(){
        $('.mobile-main-menu').removeClass('open');
    })




    window.LDOInitBasket = function(options) {
        BX.ready(function() {
            BX.LDO.CustomBasket.init(options);
        });
    };
})