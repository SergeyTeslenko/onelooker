import { html } from 'lit-html';

export const paymentTemplate = (data, first = false, selected) => (html`
<div class="${!first ? 'uk-margin-top' : ''}">
    <label>
        <input type="radio" name="payment" value="${data.code}" ?checked=${selected && data.code === selected}>&nbsp;&nbsp;${data.name}
    </label>
</div>
`);