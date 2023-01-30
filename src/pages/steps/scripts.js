/* eslint-disable */
import $ from 'jquery';
import { html, render } from 'lit-html';
import ymaps from 'ymaps';

import {
    productTemplate,
    deliveryTemplate,
    totalDeliveryTemplate,
    paymentTemplate,
    productTotalTemplate
} from '../../templates';

window.$ = $;
window.jQuery = $;

const ELEM_NEXT_STEP = '.nextStep';
const ELEM_PREV_STEP = '.prevStep';

function num_word(value, words){  
	value = Math.abs(value) % 100; 
	var num = value % 10;
	if(value > 10 && value < 20) return words[2]; 
	if(num > 1 && num < 5) return words[1];
	if(num == 1) return words[0]; 
	return words[2];
}

class Shop {
    init = false;
    cart = [];
    products = [];
    sales = [];
    availibleSales = {
        active: [],
        total: 0
    };
    totalPrice = 0;
    changeShop = new Event('shop:changeShop');

    STEPS = new Steps();
    DELEVERY = new Delivery();
    PAYMENT = new Payment();

    constructor(localData) {
        if (localData.cart) {
            this.cart = localData.cart;
        }
    }

    initProducts = (data) => {
        this.products = data.products;
        this.sales = data.sales;
        this.drawProducts();
        this.recalculateCart();
        this.drawTotal();

        this.init = true;
        document.dispatchEvent(this.changeShop);
    }

    changeCartEvent = () => {
        localStorage.setItem('cart', JSON.stringify(this.cart));

        this.drawTotal();

        if (this.STEPS.step === 3) {
            $('#productCount').text(`${this.cart.length} ${num_word(this.cart.length, ['товар', 'товара', 'товаров'])}`);
            $('#productCost').text(this.totalPrice);
        }
        
        document.dispatchEvent(this.changeShop);
    }

    drawProducts = () => {
        const productsTemplates = (products) => (
            html`${products.map((product) => {
                let options = {
                    inCart: false,
                    count: 1
                };
                
                if (this.existInCart(product.id)) {
                    options = {
                        ...options,
                        inCart: true,
                        count: this.cart.find((item) => +item.id === +product.id).count
                    };
                }

                return productTemplate(product, options);
            })}`
        );
        render(productsTemplates(this.products), $('#products')[0]);
    }

    existInCart = (id) => {
        return this.cart.filter((item) => +item.id === +id).length > 0 ? true : false;
    }

    recalculateCart = () => {
        let total = 0;

        this.cart.forEach((cartItem) => {
            this.products.forEach((product) => {
                if (+cartItem.id === +product.id) {
                    total += cartItem.count * product.price;
                }
            });
        });

        this.recalculateSales(total);
        this.totalPrice = total;

        this.changeCartEvent();
    }

    recalculateSales = (total = this.totalPrice) => {
        // sale cart_20000
        if (total >= 20000) {
            const sale = this.sales.find((item) => item.code === 'cart_20000');

            if (this.availibleSales.active.filter((code) => code === 'cart_20000').length === 0) {
                this.availibleSales = {
                    ...this.availibleSales,
                    active: [...this.availibleSales.active, sale.code],
                    total: this.availibleSales.total + sale.cost
                };
            }
        } else if (total < 20000) {
            const sale = this.sales.find((item) => item.code === 'cart_20000');
            
            if (this.availibleSales.active.filter((code) => code === 'cart_20000').length !== 0) {
                this.availibleSales = {
                    ...this.availibleSales,
                    active: this.availibleSales.active.filter((code) => code !== 'cart_20000'),
                    total: this.availibleSales.total - sale.cost
                };
            }
        }

        // sale prepayment_card_100
        if (this.PAYMENT.selected && this.PAYMENT.selected.code === 'bank-transfer') {
            const sale = this.sales.find((item) => item.code === 'prepayment_card_100');

            if (this.availibleSales.active.filter((code) => code === 'prepayment_card_100').length === 0) {
                this.availibleSales = {
                    ...this.availibleSales,
                    active: [...this.availibleSales.active, sale.code],
                    total: this.availibleSales.total + sale.cost
                };
            }
        } else {
            if (this.availibleSales.active.filter((code) => code === 'prepayment_card_100').length !== 0) {
                const sale = this.sales.find((item) => item.code === 'prepayment_card_100');

                this.availibleSales = {
                    ...this.availibleSales,
                    active: this.availibleSales.active.filter((code) => code !== 'prepayment_card_100'),
                    total: this.availibleSales.total - sale.cost
                };
            }
        }

        // sale payment_card_online
        if (this.PAYMENT.selected && this.PAYMENT.selected.code === 'payment_card_online') {
            const sale = this.sales.find((item) => item.code === 'payment_card_online');

            if (this.availibleSales.active.filter((code) => code === 'payment_card_online').length === 0) {
                this.availibleSales = {
                    ...this.availibleSales,
                    active: [...this.availibleSales.active, sale.code],
                    total: this.availibleSales.total + sale.cost
                };
            }
        } else {
            if (this.availibleSales.active.filter((code) => code === 'payment_card_online').length !== 0) {
                const sale = this.sales.find((item) => item.code === 'payment_card_online');

                this.availibleSales = {
                    ...this.availibleSales,
                    active: this.availibleSales.active.filter((code) => code !== 'payment_card_online'),
                    total: this.availibleSales.total - sale.cost
                };
            }
        }
    }

    addProduct = (id, count, offerID) => {
        if (this.existInCart(id)) {
            this.cart = this.cart.map((item) => {
                if (+item.id === +id) {
                    return {
                        ...item,
                        count: item.count + count
                    }
                }
                return item;
            });
        } else {
            this.cart = [...this.cart, {
                id, count, offerID
            }]
        }

        this.recalculateCart();
        this.changeCartEvent();
    }

    removeProduct = (id) => {
        this.cart = this.cart.filter((item) => +item.id !== +id);
        
        this.recalculateCart();
        this.changeCartEvent();
    }

    changeCount = (id, count) => {
        this.cart = this.cart.map((item) => {
            if (+id === +item.id) {
                return {
                    ...item,
                    count
                };
            }
            return item;
        });
        
        this.recalculateCart();
        this.changeCartEvent();
    }

    drawTotal = () => {
        render(productTotalTemplate(this.cart.length, this.totalPrice, this.availibleSales.total), $('#totalProducts')[0]);
        $('#totalProducts').show();
    }

    drawOrder = () => {
        let typeDeliveryName = '';
        let providerName = '';
        switch(this.DELEVERY.service) {
            case 'toPoint':
                typeDeliveryName = 'Самовывоз';
                break;
            case 'toDoor':
                typeDeliveryName = 'Курьер до адреса';
                break;
        }
        switch(this.DELEVERY.provider) {
            case 'cdek':
                providerName = 'CDEK';
                break;
            case 'dpd':
                providerName = 'DPD';
                break;
            case 'rupost':
                providerName = 'Почта России';
                break;
        }

        this.contacts = localStorage.getItem('contacts');

        if (localStorage.getItem('contacts')) {
            const contacts = JSON.parse(localStorage.getItem('contacts'));
            $('#input-lastName').val(contacts.lastName);
            $('#input-firstName').val(contacts.firstName);
            $('#input-phone').val(contacts.phone);
        }

        $('#productCount').text(`${this.cart.length} ${num_word(this.cart.length, ['товар', 'товара', 'товаров'])}`);
        $('#productCost').text(this.totalPrice);
        $('#deliveryCity').text(this.DELEVERY.city.value);
        $('#deliveryProvider').text(providerName);
        $('#deliveryService').text(typeDeliveryName);
        if (this.DELEVERY.service === 'toPoint') {
            $('#deliveryAddress').text(this.DELEVERY.address.address);
        } else {
            $('#deliveryAddress').text(this.DELEVERY.address.value);
        }
        $('#deliveryDays').text(`${this.DELEVERY.deliveryType.daysMax} ${num_word(this.DELEVERY.deliveryType.daysMax, ['день', 'дня', 'дней'])}`);
        $('#deliveryCost').text(this.DELEVERY.deliveryType.deliveryCost);
        $('#paymentType').text(this.PAYMENT.selected.name);
    }
}

class Steps {
    step = 1;
    countSteps = 3;
    changeStepEvent = new Event('shop:changeStep');

    constructor() {
        const localStep = +localStorage.getItem('step');
        if (localStep) {
            this.step = localStep;
        }
        this.setStep(this.step);
        localStorage.setItem('step', this.step);
    }

    setStep = (step) => {
        if (step >= 1 && step <= 3) {
            $('.step').hide();
            $(`#step-${step}`).show();
            localStorage.setItem('step', step);
            document.dispatchEvent(this.changeStepEvent);
        }
    }

    next = () => {
        this.step++;
        this.setStep(this.step);
    }

    prev = () => {
        this.step--;
        this.setStep(this.step);
    }
}

class Delivery {
    init = false;
    country = null;
    city = null;
    provider = null;
    service = null;
    address = null;
    cost = 0;
    deliveryType = null;
    changeDelivery = new Event('shop:changeDelivery');
    availible = false;

    countries = [];
    cities = [];
    pvz = [];
    addresses = [];
    deliveryTypes = [];


    constructor() {
        const localData = localStorage.getItem('delivery') ? JSON.parse(localStorage.getItem('delivery')) : null;
        if (localData) {
            this.country = localData.country;
            this.city = localData.city;
            this.provider = localData.provider;
            this.service = localData.service;
            this.address = localData.address;
            this.cost = localData.cost;
            this.deliveryType = localData.deliveryType;
        } else {
            localStorage.setItem('delivery', JSON.stringify({
                country: this.country,
                city: this.city,
                provider: this.provider,
                service: this.service,
                address: this.address,
                cost: this.cost,
                deliveryType: this.deliveryType
            }));
        }
    }

    changeDeliveryEvent = () => {
        localStorage.setItem('delivery', JSON.stringify({
            country: this.country,
            city: this.city,
            provider: this.provider,
            service: this.service,
            address: this.address,
            cost: this.cost,
            deliveryType: this.deliveryType
        }));

        document.dispatchEvent(this.changeDelivery);

        if (this.address === null) {
            $('#totalDelivery').hide();
        }
    }

    initDelivery = () => {
        if (this.country === null) {
            $('#selectCity, #selectTypeDelivery, #selectMapPoint, #selectCurier, #totalDelivery').hide();
            $.get('/widget/api/address.php?type=country_ip', (data) => {
                if (data && data.length) {
                    this.country = data[0];
                    $('#country').val(this.country.value);

                    $("#selectCity").show();
                    this.changeDeliveryEvent();
                }
            });
        } else if (this.city === null) {
            $('#country').val(this.country.value);
            $('#selectTypeDelivery, #selectMapPoint, #selectCurier, #totalDelivery').hide();
        } else {
            $('#country').val(this.country.value);
            $('#city').val(this.city.value);
            
            $('#selectMapPoint, #selectCurier, #totalDelivery').hide();
            this.drawDeliveries();

            if (this.address) {
                this.drawTotalDelivery();
            } else {
                $('#totalDelivery').hide();
            }
        }

        if (this.country === null || this.city === null || this.address === null || this.deliveryType === null) {
            $(ELEM_NEXT_STEP).hide();
        }
        
        this.init = true;
        document.dispatchEvent(this.changeDelivery);
    }

    searchCountry = (query) => {
        if (query.length >= 3) {
            $.get(`/widget/api/address.php?type=country&query=${query}`, (data) => {
                this.countries = data;
                const listItems = (result) => (
                    html`${result.map((item, i) => (html`
                        <li><button type="button" data-index="${i}" data-value="${item.value}">${item.value}</button></li>
                    `))}`
                );
                render(listItems(data), $('#contriesList')[0]);
                $('#contriesList').show();
            });
        }
    }

    selectCountry = (index) => {
        this.country = this.countries[index];
        $('#country').val(this.country.value);

        this.city = null;
        this.service = null;
        this.provider = null;
        this.deliveryType = null;
        $("#city").val('');
        $("#selectTypeDelivery, #selectMapPoint, #selectCurier, #totalDelivery").hide();
        $("#selectCity").show();
        this.changeDeliveryEvent();
    }

    searchCity = (query) => {
        if (query.length >= 3) {
            if (this.country.data.alfa2 !== 'RU') {
                $.get(`/widget/api/address.php?type=city&country=${this.country.data.alfa2}&query=${query}`, (data) => {
                    this.cities = data;
                    const listItems = (result) => (
                        html`${result.map((item, i) => (html`
                            <li><button type="button" data-index="${i}" data-value="${item.value}">${item.value}</button></li>
                        `))}`
                    );
                    render(listItems(data), $('#citiesList')[0]);
                    $('#citiesList').show();
                });
            } else {
                $.get(`/widget/api/address.php?type=city&query=${query}`, (data) => {
                    this.cities = data;
                    const listItems = (result) => (
                        html`${result.map((item, i) => (html`
                            <li><button type="button" data-index="${i}" data-value="${item.value}">${item.value}</button></li>
                        `))}`
                    );
                    render(listItems(data), $('#citiesList')[0]);
                    $('#citiesList').show();
                });
            }
        }
    }

    selectCity = (index) => {
        this.city = this.cities[index];
        $('#city').val(this.city.value);

        this.service = null;
        this.provider = null;
        this.deliveryType = null;

        if (this.country.data.alfa2 !== 'RU') {
            $.get(`/widget/api/address.php?type=city_geo&city=${this.city.data.name}&country=${this.country.data.alfa2}`, (response) => {
                this.city.data.geo_lat = response.lat;
                this.city.data.geo_lon = response.lon;
                this.changeDeliveryEvent();
            });
        }

        this.drawDeliveries();
        this.changeDeliveryEvent();
    }

    drawDeliveries = () => {
        const city = this.country.data.alfa2 === 'RU' ? this.city.data.city_fias_id : this.city.data.name;
        const cityType = this.country.data.alfa2 === 'RU' ? 'fias' : 'name';
        $.get(`/widget/api/delivery.php?type=cost&cityType=${cityType}&city=${city}&country=${this.country.data.alfa2}`, (data) => {
            this.deliveryTypes = data;

            const deliveryTemplates = (deliveries) => {
                let availible = 0;

                deliveries.forEach((delivery) => {
                    if (delivery.deliveryToDoor !== null) {
                        availible += 1;
                    }
                    if (delivery.deliveryToPoint !== null) {
                        availible += 1;
                    }
                });

                if (availible) {
                    this.availible = true;

                    if (this.service === 'toPoint') {
                        this.drawMapPvz();
                        $('#selectMapPoint').show();
                        $('#selectCurier').hide();
                    } else if (this.service === 'toDoor') {
                        if (this.address) {
                            $('#addressCurier').val(this.address.value);
                        }
                        $('#selectCurier').show();
                        $('#selectMapPoint').hide();
                    }

                    return html`${deliveries.map((delivery, index) => {
                        let image = '';
                        let name = '';

                        switch(delivery.providerKey) {
                            case 'dpd':
                                image = 'https://on-looker.ru/widget/src/img/cdek.png/';
                                name = 'DPD - Курьерская служба';
                                break;
                            case 'cdek':
                                image = 'https://api.eshoplogistic.ru/assets/images/sdek.png#thumbnail=%2C50&srcset=1';
                                name = 'СДЭК - Курьерская служба';
                                break;
                            case 'rupost':
                                image = 'https://api.eshoplogistic.ru/assets/images/postrf.png#thumbnail=%2C50&srcset=1';
                                name = 'EMS Почта России - Курьерская служба';
                                break;
                        }
                        if (delivery.deliveryToDoor === null && delivery.deliveryToPoint === null) {
                            return '';
                        }
                        return deliveryTemplate({ ...delivery, name, image, index }, this.provider, this.service);
                    })}`;
                } else {
                    this.availible = false;
                    return 'Доставка для данного региона недоступна';
                }
            };

            render(deliveryTemplates(data), $('#selectTypeDelivery > .uk-grid')[0]);
            $('#selectTypeDelivery').show();
        });
    }

    selectDeliveryType = (provider, service, cost, index) => {
        this.provider = provider;
        this.service = service;
        this.cost = cost;
        this.address = null;
        if (service === 'toDoor') {
            this.deliveryType = this.deliveryTypes[index]['deliveryToDoor'];
        } else if (service === 'toPoint') {
            this.deliveryType = this.deliveryTypes[index]['deliveryToPoint'];
        }
        

        if (service === 'toPoint') {
            $('#selectCurier').hide();
            this.drawMapPvz();
        } else if (service === 'toDoor') {
            $('#selectMapPoint').hide();
            $('#selectCurier').show();
        }
        this.changeDeliveryEvent();
    }

    drawMapPvz = () => {
        const map = $('#mapPvz');

        if (map.length && this.availible) {
            map.html(null);

            const center = [this.city.data.geo_lat, this.city.data.geo_lon];

            const city = this.country.data.alfa2 === 'RU' ? this.city.data.city_fias_id : this.city.data.name;
            const cityType = this.country.data.alfa2 === 'RU' ? 'fias' : 'name';
            $.get(`/widget/api/delivery.php?type=pvz&cityType=${cityType}&city=${city}&country=${this.country.data.alfa2}&provider=${this.provider}`, (points) => {
                this.pvz = points.rows;

                ymaps.load('https://api-maps.yandex.ru/2.1/?lang=ru_RU').then((maps) => {
                    const myMap = new maps.Map('mapPvz', {
                        center: center,
                        zoom: 9,
                        controls: []
                    }, {
                        searchControlProvider: 'yandex#search',
                    });

                    const objectManager = new maps.ObjectManager({
                        clusterize: true,
                        gridSize: 64,
                        clusterDisableClickZoom: true
                    });

                    const objectsPoints = {
                        type: "FeatureCollection",
                        features: []
                    }

                    points.rows.forEach((pvz, i) => {
                        objectsPoints.features.push({
                            type: "Feature",
                            id: i,
                            geometry: {
                                type: "Point",
                                coordinates: [pvz.lat, pvz.lng]
                            },
                            properties: {
                                balloonContent: `
                                    <b>Адрес:</b> ${pvz.address}<br>
                                    <b>Как добраться:</b> ${pvz.description}<br>
                                    <b>Режим работы:</b> ${pvz.timetable}<br>
                                    <button type="button" data-id="${pvz.id}" class="selectPvz">Выбрать</button>
                                `,
                                clusterCaption: pvz.name,
                                hintContent: pvz.address
                            }
                        })
                    });

                    objectManager.add(objectsPoints);
                    myMap.geoObjects.add(objectManager);

                    $('#selectMapPoint').show();
                }).catch(error => console.log('Failed to load Yandex Maps', error));
            });
        } else {
            $('#selectMapPoint').hide();
        }
    }

    selectPvz = (id) => {
        this.address = this.pvz.find((item) => +item.id === id);
        this.changeDeliveryEvent();
        this.drawTotalDelivery();
    }

    searchAddress = (query) => {
        if (this.country.data.alfa2 === 'ru') {
            if (query.length >= 3) {
                $.get(`/widget/api/address.php?query=${query}`, (data) => {
                    this.addresses = data;
                    const listItems = (result) => (
                        html`${result.map((item, i) => (html`
                            <li><button type="button" data-index="${i}" data-value="${item.value}">${item.value}</button></li>
                        `))}`
                    );
                    render(listItems(data), $('#addressList')[0]);
                    $('#addressList').show();
                });
            }
        } else {
            const data = [{ value: query }];
            this.addresses = data;
            const listItems = (result) => (
                html`${result.map((item, i) => (html`
                    <li><button type="button" data-index="${i}" data-value="${item.value}">${item.value}</button></li>
                `))}`
            );
            render(listItems(data), $('#addressList')[0]);
            $('#addressList').show();
        }
    }

    selectAddress = (index) => {
        this.address = this.addresses[index];
        $('#addressCurier').val(this.address.value);
        this.drawTotalDelivery();
        this.changeDeliveryEvent();
    }

    drawTotalDelivery = () => {
        if (this.address === null) return;
        
        let dataDelivery = {
            service: null,
            provider: null,
            cost: this.cost,
            address: null
        };

        switch(this.provider) {
            case 'cdek':
                dataDelivery.provider = 'СДЭК';
                break;
            case 'dpd':
                dataDelivery.provider = 'DPD';
                break;
            case 'rupost':
                dataDelivery.provider = 'Почта России';
                break;
        }

        switch(this.service) {
            case 'toPoint':
                dataDelivery.service = 'Адрес пункта самовывоза';
                dataDelivery.address = this.address.address;
                break;
            case 'toDoor':
                dataDelivery.service = 'Адрес доставки';
                dataDelivery.address = this.address.value;
                break;
        }

        render(totalDeliveryTemplate(dataDelivery), $('#totalDelivery-1')[0]);
        $('#totalDelivery').show();
    }
}

class Payment {
    init = false;
    selected = null;

    payments = [];

    constructor() {
        const localData = localStorage.getItem('payment') ? JSON.parse(localStorage.getItem('payment')) : null;
        if (localData) {
            this.selected = localData.selected;
        } else {
            localStorage.setItem('payment', JSON.stringify({
                selected: this.selected
            }));
        }
    }

    changePaymentEvent = () => {
        localStorage.setItem('payment', JSON.stringify({
            selected: this.selected
        }));
    }

    initPayments = (country, service, address) => {
        $.get('/widget/api/payments.php?type=get', (response) => {
            if (!response.error) {
                this.payments = response.data;

                this.drawPayments(country, service, address);

                this.init = true;
            } else {
                alert(response.message);
            }
        });
    }

    drawPayments = (country, service, address) => {
        let availiblePayments = [...this.payments];
        console.log(this.payments);
        if (country === 'RU') {
            availiblePayments = availiblePayments.filter((item) => item.code !== 'e-money');
        }

        if (country !== 'RU') {
            availiblePayments = availiblePayments.filter((item) => item.code !== 'credit');
            availiblePayments = availiblePayments.filter((item) => item.code !== 'bank-transfer');
        }

        if (service === 'toPoint' && address.cod !== 1) {
            availiblePayments = availiblePayments.filter((item) => item.code !== 'imposed');
        } else if (service === 'toPoint' && address.cod) {
            let infoPayment = '';
            if (address.paymentCard === 1 && address.paymentCash === 0) {
                infoPayment = '(только картой)';
            } else if (address.paymentCard === 0 && address.paymentCash === 1) {
                infoPayment = '(только наличные)';
            }

            if (infoPayment.length) {
                availiblePayments = availiblePayments.map((item) => {
                    if (item.code === 'imposed') {
                        return {
                            ...item,
                            name: `${item.name}${infoPayment}`
                        };
                    }
                    return item;
                });
            }
        }

        const paymentsTemplates = (payments) => (
            html`${payments.map((payment, index) => {
                const isFirst = index === 0;
                const selected = this.selected ? this.selected.code : null;
                return paymentTemplate(payment, isFirst, selected);
            })}`
        );
        render(paymentsTemplates(availiblePayments), $('#totalDelivery-2')[0]);
        $('#totalDelivery-2').show();
    }

    selectPayment = (code = null) => {
        if (code === null) {
            this.selected = null;
            this.changePaymentEvent();
            return;
        }
        this.selected = this.payments.find((item) => item.code === code);
        this.changePaymentEvent();
    }
}

// steps
$(() => {
    const localShopData = {
        cart: localStorage.getItem('cart') ? JSON.parse(localStorage.getItem('cart')) : null
    };
    const SHOP = new Shop(localShopData);
    const STEPS = SHOP.STEPS;
    const DELEVERY = SHOP.DELEVERY;
    const PAYMENT = SHOP.PAYMENT;

    if (STEPS.step === 2) {
        DELEVERY.initDelivery();

        if (DELEVERY.country && DELEVERY.city && DELEVERY.address && DELEVERY.deliveryType) {
            PAYMENT.initPayments(DELEVERY.city.data.country_iso_code, DELEVERY.service, DELEVERY.address);
        }

        if (DELEVERY.country && DELEVERY.city && DELEVERY.deliveryType && DELEVERY.address && PAYMENT.selected) {
            $(ELEM_NEXT_STEP).show();
        } else {
            $(ELEM_NEXT_STEP).hide();
        }
    } else if (STEPS.step === 3) {
        SHOP.drawOrder();
    }

    document.addEventListener('shop:changeStep', () => {
        if (STEPS.step === 2) {
            if (!DELEVERY.init) {
                DELEVERY.initDelivery();
            }
            if (!PAYMENT.init && DELEVERY.address && DELEVERY.deliveryType) {
                PAYMENT.initPayments(DELEVERY.city.data.country_iso_code, DELEVERY.service, DELEVERY.address);
            }
    
            if (DELEVERY.address && PAYMENT.selected) {
                $(ELEM_NEXT_STEP).show();
            } else {
                $(ELEM_NEXT_STEP).hide();
            }
        } else if(STEPS.step === 3) {
            SHOP.drawOrder();
        }
    });
    document.addEventListener('shop:changeDelivery', () => {
        $(document).find('.totalPrice').text(SHOP.totalPrice - SHOP.availibleSales.total + DELEVERY.cost);
    });
    document.addEventListener('shop:changeShop', () => {
        $(document).find('.totalPrice').text(SHOP.totalPrice - SHOP.availibleSales.total + DELEVERY.cost);
    });

    // Общая сумма
    $(document).find('.totalPrice').text(SHOP.totalPrice + DELEVERY.cost);

    // получение товаров
    $.get(`/widget/widget/api/products.php?ids=${$('#products').data('ids')}`, (response) => {
        if (!response.error) {
            SHOP.initProducts(response.data);
        } else {
            alert(response.message);
        }
    });

    // добавление товара в корзину
    $(document).on('click', '#products a.add', function() {
        const id = +$(this).data('product');
        const offerID = +$(this).data('offerid');
        const count = +$(this).parents('.el-item').find('input.count').val();
        $(this).removeClass('add').addClass('remove').text('Удалить');
        SHOP.addProduct(id, count, offerID);
    });
    $(document).on('click', '#modal-001 .add', function() {
        const id = +$(this).data('product');
        const offerID = +$(this).data('offerid');
        const count = 1;
        $(this).removeClass('add').addClass('remove').text('Удалить из заказа');
        $(document).find(`#products a[data-product=${id}].add`).removeClass('add').addClass('remove').text('Удалить из заказа');
        SHOP.addProduct(id, count, offerID);
    });

    // удаление товара из корзину
    $(document).on('click', '#products a.remove', function() {
        const id = +$(this).data('product');
        $(this).removeClass('remove').addClass('add').text('В корзину');
        $(this).parents('.el-item').find('input.count').val(1);
        SHOP.removeProduct(id);
    });
    $(document).on('click', '#modal-001 .remove', function() {
        const id = +$(this).data('product');
        $(this).removeClass('remove').addClass('add').text('Добавить к заказу');
        $(document).find(`#products a[data-product=${id}].remove`).removeClass('remove').addClass('add').text('В корзину');
        SHOP.removeProduct(id);
    });

    // изменение кол-ва товара
    $(document).on('click', '#products a.changeCount', function() {
        const id = +$(this).parents('.el-item').data('id');
        const count = +$(this).parents('.el-item').find('input.count').val();

        if ($(this).hasClass('plus')) {
            const nextCount = count + 1;

            $(this).parents('.el-item').find('input.count').val(nextCount);

            SHOP.changeCount(id, nextCount);
        } else if ($(this).hasClass('minus')) {
            const nextCount = count - 1;

            if (nextCount === 0) {
                $(this).parents('.el-item').find('.btnCart').removeClass('remove').addClass('add').text('В корзину');
                $(this).parents('.el-item').find('input.count').val(1);

                SHOP.removeProduct(id);
            } else {
                $(this).parents('.el-item').find('input.count').val(nextCount);
                
                SHOP.changeCount(id, nextCount);
            }
        }
    });

    // следующий шаг
    $(document).on('click', ELEM_NEXT_STEP, () => {
        STEPS.next();
    });

    // следующий шаг
    $(document).on('click', ELEM_PREV_STEP, () => {
        STEPS.prev();
    });

    // поиск страны
    $(document).on('keyup', '#country', function() {
        const value = $(this).val();
        DELEVERY.searchCountry(value);
    });

    // выбор страны
    $(document).on('click', '#contriesList button', function() {
        const index = +$(this).data('index');
        $('#contriesList').hide();
        DELEVERY.selectCountry(index);
    });

    // поиск города
    $(document).on('keyup', '#city', function() {
        const value = $(this).val();
        DELEVERY.searchCity(value);
    });

    // выбор города
    $(document).on('click', '#citiesList button', function() {
        const index = +$(this).data('index');
        $('#citiesList').hide();
        DELEVERY.selectCity(index);
    });

    // выбор типа доставки
    $(document).on('click', '.selectDelivery', function() {
        const provider = $(this).data('provider');
        const service = $(this).data('service');
        const cost = $(this).data('cost');
        const index = +$(this).data('index');

        $(document).find('.selectDelivery').removeClass('selected');
        $(this).addClass('selected');

        if (DELEVERY.service !== service) {
            $(document).find('#addressCurier').val('');
        }

        DELEVERY.selectDeliveryType(provider, service, cost, index);
        PAYMENT.selectPayment();

        $(document).find('.totalPrice').text(SHOP.totalPrice + DELEVERY.cost);
        $(ELEM_NEXT_STEP).hide();
    });

    // выбор пвз - TOTAL вывод
    $(document).on('click', '.selectPvz', function() {
        $(this).css({ background: '#00d3a7' });
        DELEVERY.selectPvz(+$(this).data('id'));
        PAYMENT.initPayments(DELEVERY.city.data.country_iso_code, DELEVERY.service, DELEVERY.address);

        $(ELEM_NEXT_STEP).show();
    });

    // поиск адреса
    $(document).on('keyup', '#addressCurier', function() {
        if ($(this).val().length === 1) {
            $(this).val(`${DELEVERY.city.value}, ${$(this).val()}`);
        }
        const value = $(this).val();
        DELEVERY.searchAddress(value);
    });

    // выбор адреса - TOTAL вывод
    $(document).on('click', '#addressList button', function() {
        const index = +$(this).data('index');
        $('#addressList').hide();
        DELEVERY.selectAddress(index);
        PAYMENT.drawPayments(DELEVERY.city.data.country_iso_code, DELEVERY.service, DELEVERY.address);

        $(ELEM_NEXT_STEP).show();
    });

    // выбор способа оплаты
    $(document).on('click', '#totalDelivery-2 input', function() {
        const code = $(this).val();
        PAYMENT.selectPayment(code);
        SHOP.recalculateCart();

        if (DELEVERY.address) {
            $(ELEM_NEXT_STEP).show();
        }
    });
    
    // контактные данные формы
    $(document).on('submit', '#step-3 form', function(e) {
        e.preventDefault();

        const formData = $(this).serializeArray();
        localStorage.setItem('contacts', JSON.stringify({
            firstName: formData[1].value,
            lastName: formData[0].value,
            phone: formData[2].value
        }));
        
        $.post("/api/order.php", {
            type: 'create',
            order: {
                products: SHOP.cart,
                discount: SHOP.availibleSales.total,
                cartCost: SHOP.totalPrice,
                delivery: {
                    cost: DELEVERY.cost,
                    country: DELEVERY.country,
                    city: DELEVERY.city,
                    provider: DELEVERY.provider,
                    service: DELEVERY.service,
                    tariffID: DELEVERY.deliveryType.tariffId,
                    address: DELEVERY.address
                }
            },
            contacts: {
                firstName: formData[1].value,
                lastName: formData[0].value,
                phone: formData[2].value,
                comment: formData[3].value
            },
            payment: {
                code: PAYMENT.selected.code
            }
        }).done((response) => {
            if (!response.error) {
                document.location.href = `${document.location.origin}/order.html?order=${response.data.number}`;
            } else {
                alert(response.message);
            }
        });
        return false;
    });
});