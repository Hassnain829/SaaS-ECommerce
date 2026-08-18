(function () {
    const config = window.ecoPortalCheckout || {};
    const paymentRoot = document.getElementById('eco-portal-stripe');
    const statusRoot = document.getElementById('eco-portal-checkout-status');
    const button = document.getElementById('eco-portal-pay-button');
    const retryButton = document.getElementById('eco-portal-status-retry');
    const cardMount = document.getElementById('eco-portal-card');
    const errorBox = document.getElementById('eco-portal-card-error');
    const paymentStatus = document.getElementById('eco-portal-payment-status');
    const statusMessage = document.getElementById('eco-portal-status-message');
    const pollingBudgetMs = 120000;
    let polling = false;
    let completedPending = null;

    if (!config.statusUrl || !config.statusNonce || (!paymentRoot && !statusRoot)) {
        return;
    }

    const showError = (message) => {
        if (!errorBox) {
            return;
        }
        errorBox.hidden = !message;
        errorBox.textContent = message || '';
    };

    const showStatus = (message) => {
        const target = statusMessage || paymentStatus;
        if (!target) {
            return;
        }
        target.hidden = !message;
        target.textContent = message || '';
    };

    const showRetry = (visible) => {
        if (retryButton) {
            retryButton.hidden = !visible;
            retryButton.disabled = false;
        }
    };

    const wait = (milliseconds) => new Promise((resolve) => window.setTimeout(resolve, milliseconds));

    const requestStatus = async (mode) => {
        const body = new URLSearchParams();
        body.set('action', 'eco_portal_checkout_status');
        body.set('nonce', config.statusNonce);
        body.set('mode', mode);

        const response = await window.fetch(config.statusUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
            },
            body: body.toString(),
        });
        const payload = await response.json();
        const data = payload && payload.data ? payload.data : {};

        if (!response.ok || !payload.success) {
            throw new Error(data.message || 'Order status is temporarily unavailable.');
        }

        return data;
    };

    const finishCompletedCheckout = async (data) => {
        completedPending = data;
        showStatus(data.message || 'Your order is confirmed.');
        showRetry(false);

        try {
            const acknowledged = await requestStatus('complete');
            const redirectUrl = acknowledged.redirect_url || data.redirect_url;
            if (!redirectUrl) {
                throw new Error('The order confirmation address was not returned.');
            }
            window.location.assign(redirectUrl);
        } catch (error) {
            showStatus('Your order is confirmed, but the confirmation page could not open yet. Check order status again.');
            showRetry(true);
        }
    };

    const handlePublicState = async (data) => {
        const state = data.state || 'processing';

        if (state === 'completed') {
            await finishCompletedCheckout(data);
            return true;
        }

        if (state === 'failed') {
            showStatus(data.message || 'Stripe reported that this payment did not complete.');
            showRetry(false);
            return true;
        }

        if (state === 'expired') {
            showStatus(data.message || 'This checkout has expired.');
            showRetry(false);
            return true;
        }

        showStatus(data.message || 'Payment confirmation is still processing. Do not pay again.');
        return false;
    };

    const pollCheckoutStatus = async () => {
        if (polling) {
            return;
        }

        polling = true;
        showRetry(false);
        const startedAt = Date.now();
        let delayMs = 1000;

        while (Date.now() - startedAt < pollingBudgetMs) {
            try {
                const data = await requestStatus('poll');
                if (await handlePublicState(data)) {
                    polling = false;
                    return;
                }
                const serverDelayMs = Math.max(1000, Number(data.retry_after_seconds || 1) * 1000);
                delayMs = Math.max(delayMs, serverDelayMs);
            } catch (error) {
                showStatus('Order confirmation is still processing, but status is temporarily unavailable. Do not pay again.');
            }

            await wait(delayMs);
            delayMs = Math.min(10000, Math.ceil(delayMs * 1.6));
        }

        polling = false;
        showStatus('Order confirmation is still processing. Do not pay again. You can safely check the same order status again.');
        showRetry(true);
    };

    if (retryButton) {
        retryButton.addEventListener('click', async () => {
            retryButton.disabled = true;
            if (completedPending) {
                await finishCompletedCheckout(completedPending);
                return;
            }
            await pollCheckoutStatus();
        });
    }

    if (statusRoot) {
        pollCheckoutStatus();
        return;
    }

    if (!paymentRoot || !button || !cardMount || typeof window.Stripe !== 'function') {
        return;
    }

    const publishableKey = paymentRoot.getAttribute('data-publishable-key') || '';
    const clientSecret = paymentRoot.getAttribute('data-client-secret') || '';
    const stripeAccount = paymentRoot.getAttribute('data-stripe-account') || '';

    if (!publishableKey || !clientSecret) {
        return;
    }

    const stripe = stripeAccount
        ? window.Stripe(publishableKey, { stripeAccount: stripeAccount })
        : window.Stripe(publishableKey);
    const elements = stripe.elements({ clientSecret: clientSecret });
    const paymentElement = elements.create('payment');
    paymentElement.mount('#eco-portal-card');

    button.addEventListener('click', async () => {
        button.disabled = true;
        showError('');
        showStatus('');
        let confirmationStarted = false;

        try {
            await requestStatus('begin');
            confirmationStarted = true;
            const result = await stripe.confirmPayment({
                elements: elements,
                redirect: 'if_required',
                confirmParams: {
                    return_url: config.returnUrl,
                },
            });

            if (result.error) {
                const uncertain = ['api_connection_error', 'api_error'].includes(result.error.type || '');
                if (uncertain) {
                    showStatus('Stripe could not return a definite result. Do not pay again while this page checks the existing checkout.');
                    await pollCheckoutStatus();
                    return;
                }
                try {
                    await requestStatus('payment_error');
                } catch (ignored) {
                    // The persisted confirming state remains recoverable through status polling.
                }
                showError(result.error.message || 'Stripe did not complete this payment.');
                button.disabled = false;
                return;
            }

            button.textContent = 'Waiting for order confirmation…';
            showStatus('Stripe accepted the payment confirmation. The merchant portal is finishing the order. Do not pay again.');
            await pollCheckoutStatus();
        } catch (error) {
            if (confirmationStarted) {
                showStatus('Stripe did not return a definite result. Do not pay again while this page checks the existing checkout.');
                await pollCheckoutStatus();
                return;
            }
            showError(error && error.message ? error.message : 'Payment could not be confirmed.');
            button.disabled = false;
        }
    });
})();
