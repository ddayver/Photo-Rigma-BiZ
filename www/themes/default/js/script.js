/*
 * Авторские права (c) 2025.
 *
 * Данная лицензия разрешает лицам, получившим копию данного программного обеспечения и связанных с ним файлов документации
 * (далее — «Программное Обеспечение»), безвозмездно использовать Программное Обеспечение без ограничений, включая право
 * на использование, копирование, изменение, слияние, публикацию, распространение, сублицензирование и/или продажу копий
 * Программного Обеспечения, а также разрешает лицам, которым предоставляется данное Программное Обеспечение, делать это при
 * следующих условиях:
 *
 * - Указанное выше уведомление об авторских правах и настоящее разрешение должны быть включены во все копии или значимые части
 *   данного Программного Обеспечения.
 *
 * - ДАННОЕ ПРОГРАММНОЕ ОБЕСПЕЧЕНИЕ ПРЕДОСТАВЛЯЕТСЯ «КАК ЕСТЬ», БЕЗ КАКИХ-ЛИБО ГАРАНТИЙ, ЯВНЫХ ИЛИ ПОДРАЗУМЕВАЕМЫХ, ВКЛЮЧАЯ, НО НЕ
 *   ОГРАНИЧИВАЯСЬ, ГАРАНТИЯМИ ТОВАРНОЙ ПРИГОДНОСТИ, СООТВЕТСТВИЯ ОПРЕДЕЛЁННОЙ ЦЕЛИ И ОТСУТСТВИЯ НАРУШЕНИЙ. НИ ПРИ КАКИХ
 *   ОБСТОЯТЕЛЬСТВАХ АВТОРЫ ИЛИ ДЕРЖАТЕЛИ АВТОРСКИХ ПРАВ НЕ НЕСУТ ОТВЕТСТВЕННОСТИ ЗА ЛЮБЫЕ ПРЕТЕНЗИИ, УЩЕРБ ИЛИ ИНЫЕ ОБЯЗАТЕЛЬСТВА,
 *   ВОЗНИКАЮЩИЕ В РЕЗУЛЬТАТЕ ИСПОЛЬЗОВАНИЯ ПРОГРАММНОГО ОБЕСПЕЧЕНИЯ ИЛИ НЕВОЗМОЖНОСТИ ЕГО ИСПОЛЬЗОВАНИЯ, ВКЛЮЧАЯ, НО НЕ
 *   ОГРАНИЧИВАЯСЬ, ПОТЕРЮ ДАННЫХ, ПРИБЫЛИ ИЛИ ПРЕРЫВАНИЕМ ДЕЯТЕЛЬНОСТИ.
 */

$(document).ready(function () {
    // Инициализация стандартныx компонентов Fomantic-UI
    $('.ui.checkbox').checkbox();
    $('.ui.icon.button').popup();
    $('.ui.dropdown').dropdown();
    $('.ui.sticky').sticky({ context: '#short_menu' });
    console.log('Initializing readonly ratings...');

    function initializeReadOnlyRating() {
        $('.readonly-rating').each(function () {
            const ratingContainer = $(this);
            const maxRating = parseFloat(ratingContainer.attr('data-max-rating')) || 1;
            const ratingValue = parseFloat(ratingContainer.attr('data-rating')) || 0;

            console.log(`Processing rating: value=${ratingValue}, max=${maxRating}`);

            const totalIcons = 2 * maxRating + 1;

            ratingContainer.empty();
            for (let i = 0; i < totalIcons; i++) {
                ratingContainer.append('<i class="icon"></i>');
            }

            ratingContainer.rating({
                initialRating: Math.abs(ratingValue),
                maxRating: totalIcons,
                interactive: false,
                fireOnInit: true,
                onRate: function () {
                    return false;
                }
            });

            ratingContainer.find('.icon').each(function (index) {
                const icon = $(this);
                const midpoint = maxRating;

                if (index < midpoint) {
                    icon.addClass('red');
                } else if (index === midpoint) {
                    icon.addClass('grey');
                } else {
                    icon.addClass('green');
                }
            });

            const fractionalPart = Math.abs(ratingValue) % 1;
            if (fractionalPart > 0) {
                const activeIconIndex = Math.floor(Math.abs(ratingValue));
                const partialIcon = ratingContainer.find('.icon').eq(activeIconIndex);
                partialIcon.addClass('partial');

                const fillPercent = `${fractionalPart * 100}%`;
                partialIcon.css('--fill-percent', fillPercent);
                console.log(`Set --fill-percent to ${fillPercent}`);
            }
        });
    }

    initializeReadOnlyRating();

    $(document).on('click', '.delete-button', function () {
        const modalSelector = $(this).data('target');
        const $modal = $(modalSelector);

        $modal.modal({
            closable: false,
            transition: 'fly left',
            duration: 500,
            onHide: function () {
                $modal.modal('setting', 'transition', 'fly right');
            },
            onApprove: function () {
                const url = $modal.data('url');
                window.location.href = url;
            }
        }).modal('show');
    });
});
