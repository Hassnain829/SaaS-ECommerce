(function () {
    const root = document.getElementById('eco-portal-stripe');
    const button = document.getElementById('eco-portal-pay-button');
    const form = document.getElementById('eco-portal-confirm-form');
    const cardMount = document.getElementById('eco-portal-card');
    const errorBox = document.getElementById('eco-portal-card-error');

    if (!root || !button || !form || !cardMount || typeof window.Stripe !== 'function') {
        return;
    }

    const publishableKey = root.getAttribute('data-publishable-key') || '';
    const clientSecret = root.getAttribute('data-client-secret') || '';
    const stripeAccount = root.getAttribute('data-stripe-account') || '';

    if (!publishableKey || !clientSecret) {
        return;
    }

    const stripe = stripeAccount
        ? window.Stripe(publishableKey, { stripeAccount: stripeAccount })
        : window.Stripe(publishableKey);
    const elements = stripe.elements({ clientSecret: clientSecret });
    const paymentElement = elements.create('payment');
    paymentElement.mount('#eco-portal-card');

    const showError = (message) => {
        if (!errorBox) {
            return;
        }
        errorBox.hidden = !message;
        errorBox.textContent = message || '';
    };

    button.addEventListener('click', async () => {
        button.disabled = true;
        showError('');
        try {
            const result = await stripe.confirmPayment({
                elements: elements,
                redirect: 'if_required',
                confirmParams: {
                    return_url: window.location.href,
                },
            });
            if (result.error) {
                showError(result.error.message || 'Payment failed.');
                button.disabled = false;
                return;
            }
            form.submit();
        } catch (error) {
            showError(error && error.message ? error.message : 'Payment failed.');
            button.disabled = false;
        }
    });
})();
