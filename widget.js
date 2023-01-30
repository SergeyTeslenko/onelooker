function addWidgetScript(id, src) {
    return new Promise((resolve, reject) => {
        const s = document.createElement('script');

        s.setAttribute('src', src);
        s.addEventListener('load', resolve);
        s.addEventListener('error', reject);

        document.getElementById(id).append(s);
        setTimeout(function(){document.getElementById(id).style.display = 'block';}, 1000);
    });
}

const loadHtml = function(parentElementId, filePath) {
    const init = {
        method : 'GET',
        headers : {
            'Content-Type': 'text/html',
            'Access-Control-Allow-Origin': '*'
        },
        mode : 'cors',
        cache : 'default'
    };

    const req = new Request(filePath, init);

    fetch(req).then(function(response) {
        return response.text();
    }).then(function(body) {
        document.getElementById(parentElementId).innerHTML = body;
        addWidgetScript(parentElementId,'https://on-looker.ru/widget/shop/dist/uikit.min.js');
        addWidgetScript(parentElementId,'https://on-looker.ru/widget/shop/dist/steps.js');
    });
};

loadHtml('shopwidget', 'https://on-looker.ru/widget/widget.html');