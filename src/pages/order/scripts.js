/* eslint-disable */
import $ from 'jquery';
import { html, render } from 'lit-html';

import {
    cartProductTemplate
} from '../../templates';

// order
$(() => {
    localStorage.removeItem('step');
    localStorage.removeItem('delivery');
    localStorage.removeItem('payment');
    localStorage.removeItem('cart');

    var URLParams = new URLSearchParams(window.location.search);
    const orderID = URLParams.get('order');

    if (orderID === null) {
        alert('Неверный идентификатор заказа');
        return;
    }

    $('#order_id').text(orderID);

    $.post(`api/order.php`, { type: 'get', number: orderID }, (response) => {
        if (!response.error) {
            const { data } = response;

            const cartProducts = (products) => (
                html`${products.map((item, i) => (cartProductTemplate(item, i)))}`
            );
            render(cartProducts(data.products), $('#order-products')[0]);

            $('#order-contact').text(data.contacts.payer);
            $('#order-phone').text(data.contacts.phone);
            $('#order-delivery-type').text(data.delivery.type === 'to-point' ? 'Самовывоз' : 'Курьер');
            $('#order-delivery-address').text(data.delivery.address);
            $('#order-delivery-cost').text(data.delivery.cost);
            $('#order-delivery-day').text(data.delivery.days);
            $('#order-payments-type').text(data.payment.typeName);
            $('#order-status').text(data.statusName);
            $('#order-sale').text(data.sale);
            if (data.payment.type === 'imposed') {
                $('#order-summ-1').css({ display: 'inline' });
                $('#order-summ-1 span').text(data.totalSumm);
            } else {
                $('#order-summ-2').css({ display: 'inline' });
                $('#order-summ-2 span').text(data.totalSumm);
            }

            $(`#confirm-type-${data.payment.type}`).show();

            if (data.statusGroup === 'cancel') {
                $('.confirm-type, .order-info-text').hide();
            }

            if (['delivery', 'complete', 'cancel'].includes(data.statusGroup)) {
                $('#order-cancel').hide();
            } else {
                $('#order-cancel').show();

                $('#order-cancel').on('click', (e) => {
                    e.preventDefault();

                    $.post('api/order.php', {
                        type: 'update-status',
                        id: orderID.replace(/\D+/g, ''),
                        status: 'cancel'
                    }, (response) => {
                        if (!response.error) {
                            window.location.reload();
                        } else {
                            alert(response.message);
                        }
                    });
                })
            }
        }
    });
});