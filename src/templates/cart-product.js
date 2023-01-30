import { html, nothing } from 'lit-html';

export const cartProductTemplate = (data, index) => (html`
<div class="uk-grid-margin ${index === 0 ? 'uk-first-column' : ''}">
    <div
        class="el-item uk-panel uk-margin-remove-first-child">
        <div class="uk-child-width-expand uk-grid"
            uk-grid="">
            <div
                class="uk-width-auto@s uk-first-column">
                <img src="${data.image}"
                    width="100"
                    height="100"
                    class="el-image"
                    alt=""
                    decode="async">
            </div>
            <div
                class="uk-margin-remove-first-child">
                <div class="uk-child-width-expand uk-grid"
                    uk-grid="">
                    <div class="uk-width-1-2 uk-margin-remove-first-child uk-first-column">
                        <h3 class="el-title uk-margin-top uk-margin-remove-bottom">${data.name}</h3>
                        <div class="el-meta uk-text-meta uk-margin-top">${data.count} шт.</div>
                    </div>
                    <div class="uk-margin-remove-first-child">
                        <div
                            class="el-content uk-panel uk-margin-top">${data.price * data.count} руб.</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
`);