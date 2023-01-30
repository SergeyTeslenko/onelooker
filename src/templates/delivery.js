import { html, nothing } from 'lit-html';

function num_word(value, words = ['день', 'дня', 'дней']){  
	value = Math.abs(value) % 100; 
	var num = value % 10;
	if(value > 10 && value < 20) return words[2]; 
	if(num > 1 && num < 5) return words[1];
	if(num == 1) return words[0]; 
	return words[2];
}

export const deliveryTemplate = (data, provider = null, service = null) => {
    return html`
<div>
    <div class="el-item uk-card uk-card-secondary uk-card-body uk-margin-remove-first-child">
        <canvas class="uk-background-cover el-image" width="116.66666666666667"
            height="50"
            style="background-image: url('${data.image}');"></canvas>
        <h3 class="el-title uk-h3 uk-margin-top uk-margin-remove-bottom">${data.name}</h3>
        <div class="el-content uk-panel uk-margin-top">
            <div class="uk-child-width-1-2 uk-grid" uk-grid="">
                ${data.deliveryToPoint ? html`
                <div class="uk-first-column">
                    <p>Самовывоз:
                        <br>${data.deliveryToPoint.deliveryCost} руб., ${data.deliveryToPoint.daysMax} ${num_word(data.deliveryToPoint.daysMax)}
                        <br>Без учёта дня отправки</p>
                    <a type="button"
                        data-service="toPoint" 
                        data-index="${data.index}"
                        data-provider="${data.providerKey}"
                        data-cost="${data.deliveryToPoint.deliveryCost}"
                        class="el-content uk-button uk-margin-top uk-button-default uk-align-center selectDelivery ${provider && provider === data.providerKey && service === 'toPoint' ? 'selected' : ''}"
                    >
                        Выбрать
                    </a>
                </div>
                ` : nothing}
                ${data.deliveryToDoor ? html`
                <div>
                    <p>Курьер:
                        <br>${data.deliveryToDoor.deliveryCost} руб., ${data.deliveryToDoor.daysMax} ${num_word(data.deliveryToDoor.daysMax)}
                        <br>Без учёта дня отправки</p>
                    <a type="button" data-service="toDoor" data-index="${data.index}" data-provider="${data.providerKey}" data-cost="${data.deliveryToDoor.deliveryCost}"
                        class="el-content uk-button uk-margin-top uk-button-default uk-align-center selectDelivery ${provider && provider === data.providerKey && service === 'toDoor' ? 'selected' : ''}">
                        Выбрать
                    </a>
                </div>
                ` : nothing}
            </div>
        </div>
    </div>
</div>    
`};

export const totalDeliveryTemplate = (data) => (html`
<div class="el-item uk-panel uk-margin-remove-first-child">
    <h3 class="el-title uk-margin-top uk-margin-remove-bottom">Доставка: </h3>
    <div class="el-content uk-panel uk-margin-top">
        <p>${data.service} ${data.provider}: ${data.address}
            <br>Стоимость доставки: ${data.cost} рублей
            <br>Итого стоимость товаров с доставкой: <span class="uk-h4 uk-text-warning"><span class="totalPrice"></span> рублей</span></p>
    </div>
</div>
`);