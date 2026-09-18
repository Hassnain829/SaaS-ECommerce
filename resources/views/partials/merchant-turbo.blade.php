<script>
(function () {
    window.__merchantDocBinds = window.__merchantDocBinds || {};
    window.bindMerchantDocOnce = function (key, bind) {
        if (window.__merchantDocBinds[key]) {
            return false;
        }
        window.__merchantDocBinds[key] = true;
        bind();
        return true;
    };
    window.bootMerchantPage = function (key, getRoot, bootFn) {
        var run = function () {
            var root = typeof getRoot === 'function' ? getRoot() : document.querySelector(getRoot);
            if (! root || root.getAttribute('data-turbo-bound') === '1') {
                return;
            }
            root.setAttribute('data-turbo-bound', '1');
            bootFn(root);
        };
        window.bindMerchantDocOnce('boot:' + key, function () {
            document.addEventListener('turbo:load', run);
            document.addEventListener('turbo:render', run);
        });
        run();
    };
})();
</script>
