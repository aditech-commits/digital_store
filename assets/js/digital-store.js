/* ChippaPay Digital Store Frontend JS */

var current_product_id = 0;

jQuery(document).ready(function ($) {
    // Dynamic Service Card Injection into ChippaPay SERVICES Grid (Right beside Virtual Number)
    function injectDigitalStoreCard() {
        if ($('#chippapay-ds-icon-card').length) return;

        // Search for element containing "Virtual Number"
        var $vnElement = $('*:contains("Virtual Number")').filter(function () {
            var text = $.trim($(this).text());
            return text === 'Virtual Number' || text.indexOf('Virtual Number') === 0;
        }).last();

        if ($vnElement.length) {
            // Find parent card / column container
            var $cardContainer = $vnElement.closest('a, .col-3, .col-4, .col-md-3, .service-item, .service-card, div[class*="service"]');

            if ($cardContainer.length) {
                var $cloned = $cardContainer.clone();
                $cloned.attr('id', 'chippapay-ds-icon-card');

                // Update Link to Digital Store page
                if ($cloned.is('a')) {
                    $cloned.attr('href', '/digital-store');
                } else {
                    $cloned.find('a').attr('href', '/digital-store');
                }

                // Update text label to "Digital Store"
                $cloned.find('*:contains("Virtual Number")').each(function () {
                    if ($.trim($(this).text()) === 'Virtual Number') {
                        $(this).text('Digital Store');
                    }
                });

                // Update icon to Digital Store shopping cart
                var $img = $cloned.find('img');
                if ($img.length) {
                    $img.attr('src', 'https://img.icons8.com/color/96/online-store.png');
                    $img.attr('alt', 'Digital Store');
                } else {
                    $cloned.find('i, svg, .icon').replaceWith('<span style="font-size:24px;">🛒</span>');
                }

                // Place right beside Virtual Number
                $cardContainer.after($cloned);
            }
        }
    }

    injectDigitalStoreCard();
    setTimeout(injectDigitalStoreCard, 800);
    setTimeout(injectDigitalStoreCard, 2000);

    // Category Filtering
    $('.tab-btn').on('click', function () {
        $('.tab-btn').removeClass('active');
        $(this).addClass('active');

        var category = $(this).data('category');

        if (category === 'all') {
            $('.product-card').show();
        } else {
            $('.product-card').hide();
            $('.product-card[data-category="' + category + '"]').show();
        }
    });
});



function chippapay_open_purchase_modal(id, title, price, deliveryTime) {
    current_product_id = id;
    jQuery('#modal-product-title').text(title);
    jQuery('#modal-product-price').text('₦' + price);
    jQuery('#modal-delivery-time').text(deliveryTime);
    jQuery('#accept-policy-chk').prop('checked', false);
    jQuery('#chippapay-purchase-modal').fadeIn(200);
}

function chippapay_close_modal() {
    jQuery('#chippapay-purchase-modal').fadeOut(200);
}

function chippapay_submit_purchase() {
    var $ = jQuery;
    var accepted = $('#accept-policy-chk').is(':checked') ? 'yes' : 'no';

    if (accepted !== 'yes') {
        alert('Please check the box to agree to the ChippaPay Digital Store Customer Policy before proceeding.');
        return;
    }

    var btn = $('.btn-confirm-purchase');
    btn.prop('disabled', true).text('Processing Payment...');

    $.ajax({
        url: chippapay_ds_ajax.ajax_url,
        type: 'POST',
        data: {
            action: 'chippapay_ds_buy_product',
            nonce: chippapay_ds_ajax.nonce,
            product_id: current_product_id,
            accept_policy: 'yes'
        },
        success: function (res) {
            if (res.success) {
                window.location.href = res.data.redirect_url;
            } else {
                alert(res.data.message);
                btn.prop('disabled', false).text('Confirm & Pay from Wallet');
            }
        },
        error: function () {
            alert('Server error processing purchase. Please try again.');
            btn.prop('disabled', false).text('Confirm & Pay from Wallet');
        }
    });
}

function chippapay_confirm_received(orderId) {
    var $ = jQuery;
    if (!confirm('Have you verified and confirmed full access to your digital account?')) {
        return;
    }

    var btn = $('.btn-confirm-received');
    btn.prop('disabled', true).text('Updating...');

    $.ajax({
        url: chippapay_ds_ajax.ajax_url,
        type: 'POST',
        data: {
            action: 'chippapay_ds_confirm_delivery',
            nonce: chippapay_ds_ajax.nonce,
            order_id: orderId
        },
        success: function (res) {
            if (res.success) {
                alert(res.data.message);
                location.reload();
            } else {
                alert(res.data.message);
                btn.prop('disabled', false).text('I HAVE RECEIVED MY ACCOUNT');
            }
        }
    });
}
