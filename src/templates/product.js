import { html, nothing } from 'lit-html';
import { unsafeHTML } from 'lit-html/directives/unsafe-html';

function num_word(value, words = ['товар', 'товара', 'товаров']){
	value = Math.abs(value) % 100;
	var num = value % 10;
	if(value > 10 && value < 20) return words[2];
	if(num > 1 && num < 5) return words[1];
	if(num == 1) return words[0];
	return words[2];
}

export const productTemplate = (data, options) => (html`
  <div>
       <div
           class="el-item uk-card uk-card-secondary uk-card-body uk-margin-remove-first-child" data-id="${data.id}" data-offerid="${data.offerID}">
           <canvas class="uk-background-cover el-image" width="1661" height="1658"
               style="background-image: url(&quot;${data.image}#thumbnail=%2C&amp;srcset=1&quot;);"></canvas>
           <h3 class="el-title uk-h4 uk-margin-top uk-margin-remove-bottom">${data.name}</h3>
           <div class="el-content uk-panel uk-margin-top">
               <div class="uk-h4">
                   <div><a class="uk-text-warning" href="#modal-001"
                           uk-toggle="">Подробнее</a></div>
 
                   <div class="uk-margin-top"><span
                           class="uk-h5 uk-text-warning ">Цена:</span>
                           ${data.extra && data.extra.oldPrice ? html`<s class="uk-h5">${data.extra.oldPrice}</s>` : nothing} <span class="uk-h5">${data.price} р.</span>
                   </div>
               </div>
               <div class=" uk-text-center">
                   <div class="uk-inline">
                       <a class="uk-padding-small uk-icon uk-position-center-right changeCount plus"
                           uk-icon="icon: plus; ratio: .75"><svg width="15" height="15"
                               viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"
                               data-svg="plus">
                               <rect x="9" y="1" width="1" height="17"></rect>
                               <rect x="1" y="9" width="17" height="1"></rect>
                           </svg></a>
                       <a class="uk-padding-small uk-position-center-left uk-icon changeCount minus"
                           uk-icon="icon: minus; ratio: .75"><svg width="15" height="15"
                               viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"
                               data-svg="minus">
                               <rect height="1" width="18" y="9" x="1"></rect>
                           </svg></a>
                       <input class="uk-input uk-text-center count" type="text" value="${options.count}">
                   </div>
               </div>
               ${data.extra ? html`
                   <div id="modal-001" uk-modal="" class="uk-modal">
                       <div class="uk-modal-dialog uk-modal-body">
                           <h3 class="uk-modal-title">${data.extra.popup.title}</h3>
                           ${html`${unsafeHTML(data.extra.popup.desc)}`}
                           <p class="uk-text-right">
                               <button class="uk-button uk-button-default uk-modal-close"
                                   type="button">Закрыть</button>
                               <button class="uk-button uk-button-default uk-modal-close btnCart ${options.inCart ? 'remove' : 'add'}"
                                   type="button" data-offerid="${data.offerID}" data-product="${data.id}">${options.inCart ? 'Удалить из заказа' : 'Добавить к заказу'}</button>
                           </p>
                       </div>
                   </div>
               `: nothing}
               <a
                   type="button"
                   data-product="${data.id}"
                   data-offerid="${data.offerID}"
                   class="el-content uk-button uk-margin-top uk-button-default uk-align-center btnCart ${options.inCart ? 'remove' : 'add'}"
               >
                   ${options.inCart ? 'Удалить' : 'В корзину'}
               </a>
           </div>
       </div>
  </div>
`);

export const productTotalTemplate = (count, price, sale) => (html`
<p>Выбрано Выбрано  Выбрано Выбрано Выбрано  <span class="totalProd">${count}</span> ${num_word(count)} на сумму <span>${price}</span> рублей
    <br>Скидка: ${sale} рублей
    <br>Итого стоимость товаров: <span class="uk-h4 uk-text-warning"><span>${price - sale}</span>
        рублей</span>
</p>
 `);
