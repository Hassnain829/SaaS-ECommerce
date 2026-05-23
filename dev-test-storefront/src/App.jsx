import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { loadStripe } from '@stripe/stripe-js';

const defaultApiBase = '/api/developer-storefront';

function apiBase() {
  return (import.meta.env.VITE_API_BASE || defaultApiBase).replace(/\/$/, '');
}

function externalApiBase(catalogBase) {
  const configured = (import.meta.env.VITE_EXTERNAL_API_BASE || '').trim();
  if (configured) return configured.replace(/\/$/, '');

  if (catalogBase.endsWith('/api/developer-storefront')) {
    return catalogBase.replace('/api/developer-storefront', '/api/v1/external');
  }

  if (catalogBase.endsWith('/developer-storefront')) {
    return catalogBase.replace('/developer-storefront', '/v1/external');
  }

  return '/api/v1/external';
}

function checkoutApiBase(catalogBase) {
  const configured = (import.meta.env.VITE_CHECKOUT_API_BASE || '').trim();
  if (configured) return configured.replace(/\/$/, '');

  if (catalogBase.endsWith('/api/developer-storefront')) {
    return catalogBase.replace('/api/developer-storefront', '/api/v1/checkout');
  }

  if (catalogBase.endsWith('/developer-storefront')) {
    return catalogBase.replace('/developer-storefront', '/v1/checkout');
  }

  return '/api/v1/checkout';
}

function authHeaders() {
  const token = (import.meta.env.VITE_STOREFRONT_TOKEN || '').trim();
  if (!token) return {};
  return { Authorization: `Bearer ${token}` };
}

function money(value) {
  return Number(value || 0).toFixed(2);
}

export default function App() {
  const [catalog, setCatalog] = useState(null);
  const [error, setError] = useState('');
  const [loading, setLoading] = useState(false);
  const [cart, setCart] = useState([]);
  const [checkoutMode, setCheckoutMode] = useState('external');
  const [customerName, setCustomerName] = useState('Dev Customer');
  const [customerEmail, setCustomerEmail] = useState('dev.customer@example.test');
  const [customerPhone, setCustomerPhone] = useState('+1 555-0198');
  const [addressLine1, setAddressLine1] = useState('123 Developer Way');
  const [city, setCity] = useState('San Francisco');
  const [stateRegion, setStateRegion] = useState('CA');
  const [postalCode, setPostalCode] = useState('94105');
  const [country, setCountry] = useState('US');
  const [orderResult, setOrderResult] = useState(null);
  const [externalPaymentStatus, setExternalPaymentStatus] = useState('paid');
  const [externalPaymentGateway, setExternalPaymentGateway] = useState('external_test');
  const [externalShippingMethod, setExternalShippingMethod] = useState('');
  const [externalShippingAmount, setExternalShippingAmount] = useState('0');
  const [externalCarrierName, setExternalCarrierName] = useState('');
  const [externalFulfillmentStatus, setExternalFulfillmentStatus] = useState('');
  const [externalTrackingNumber, setExternalTrackingNumber] = useState('');
  const [externalTrackingUrl, setExternalTrackingUrl] = useState('');
  const [includeShippingSnapshot, setIncludeShippingSnapshot] = useState(false);
  const [includeFulfillmentSnapshot, setIncludeFulfillmentSnapshot] = useState(false);
  const [shipmentSyncMessage, setShipmentSyncMessage] = useState('');
  const [platformCheckoutDraft, setPlatformCheckoutDraft] = useState(null);
  const [deliveryOptions, setDeliveryOptions] = useState([]);
  const [selectedDeliveryOptionId, setSelectedDeliveryOptionId] = useState('');
  const [platformPayment, setPlatformPayment] = useState(null);
  const [stripeFormReady, setStripeFormReady] = useState(false);
  const [stripePaymentProcessing, setStripePaymentProcessing] = useState(false);
  const [stripeCardMessage, setStripeCardMessage] = useState('');
  const stripeRef = useRef(null);
  const cardElementRef = useRef(null);
  const cardContainerRef = useRef(null);

  const base = useMemo(() => apiBase(), []);
  const externalBase = useMemo(() => externalApiBase(base), [base]);
  const checkoutBase = useMemo(() => checkoutApiBase(base), [base]);
  const cartTotal = cart.reduce((sum, line) => sum + Number(line.unit_price || 0) * Number(line.quantity || 1), 0);
  const selectedDeliveryOption = useMemo(
    () => deliveryOptions.find((option) => String(option.id) === String(selectedDeliveryOptionId)) || null,
    [deliveryOptions, selectedDeliveryOptionId]
  );
  const displayedTotal = cartTotal + (selectedDeliveryOption ? Number(selectedDeliveryOption.amount || 0) : 0);

  const resetPlatformCheckout = () => {
    setPlatformPayment(null);
    setPlatformCheckoutDraft(null);
    setDeliveryOptions([]);
    setSelectedDeliveryOptionId('');
  };

  useEffect(() => {
    if (!platformPayment?.payment?.publishable_key || !platformPayment?.payment?.client_secret || !cardContainerRef.current) {
      return undefined;
    }

    let cancelled = false;
    setStripeFormReady(false);
    setStripeCardMessage('');

    const stripeOptions = platformPayment.payment.provider_account_id
      ? { stripeAccount: platformPayment.payment.provider_account_id }
      : undefined;

    loadStripe(platformPayment.payment.publishable_key, stripeOptions)
      .then((stripe) => {
        if (cancelled || !stripe || !cardContainerRef.current) return;

        stripeRef.current = stripe;
        const elements = stripe.elements();
        const card = elements.create('card', {
          hidePostalCode: true,
          style: {
            base: {
              fontSize: '16px',
              color: '#0f172a',
              fontFamily: 'Segoe UI, system-ui, -apple-system, sans-serif',
              '::placeholder': {
                color: '#94a3b8',
              },
            },
            invalid: {
              color: '#b91c1c',
            },
          },
        });

        card.on('ready', () => {
          if (!cancelled) setStripeFormReady(true);
        });
        card.on('change', (event) => {
          if (!cancelled) setStripeCardMessage(event.error?.message || '');
        });
        card.mount(cardContainerRef.current);
        cardElementRef.current = card;
      })
      .catch((e) => {
        if (!cancelled) setStripeCardMessage(e.message || 'Stripe payment form could not load.');
      });

    return () => {
      cancelled = true;
      if (cardElementRef.current) {
        cardElementRef.current.destroy();
        cardElementRef.current = null;
      }
      stripeRef.current = null;
      setStripeFormReady(false);
    };
  }, [platformPayment?.payment?.client_secret, platformPayment?.payment?.publishable_key]);

  const loadCatalog = useCallback(
    async ({ quiet } = {}) => {
      setError('');
      if (!quiet) setLoading(true);
      setOrderResult(null);
      try {
        const res = await fetch(`${base}/catalog`, {
          headers: { Accept: 'application/json', ...authHeaders() },
        });
        const raw = await res.text();
        let data = {};
        try {
          data = raw ? JSON.parse(raw) : {};
        } catch {
          data = {};
        }
        if (!res.ok) {
          if (res.status === 401) {
            throw new Error(
              data.message ||
                'Unauthorized: check VITE_STOREFRONT_TOKEN in dev-test-storefront/.env matches the token from Dashboard > Dev storefront. Restart npm run dev after changing .env.'
            );
          }
          throw new Error(
            data.message ||
              (raw.startsWith('<') ? `HTTP ${res.status}: server returned HTML (wrong URL or Laravel error page).` : res.statusText) ||
              `Request failed (${res.status})`
          );
        }
        setCatalog(data);
      } catch (e) {
        setCatalog(null);
        const msg =
          e instanceof TypeError && String(e.message).toLowerCase().includes('fetch')
            ? 'Could not reach the API. If VITE_API_BASE is unset, Laravel must run at the proxy target and you must use npm run dev so /api is proxied.'
            : e.message || 'Failed to load catalog';
        setError(msg);
      } finally {
        if (!quiet) setLoading(false);
      }
    },
    [base]
  );

  const addToCart = (product, variant) => {
    resetPlatformCheckout();
    setCart((prev) => {
      const key = `${product.id}-${variant.id}`;
      const idx = prev.findIndex((line) => `${line.product_id}-${line.variant_id}` === key);
      const label = `${product.name} - ${
        variant.options?.length ? variant.options.map((option) => `${option.type}: ${option.value}`).join(', ') : variant.sku || 'Default'
      }`;
      const line = {
        product_id: product.id,
        variant_id: variant.id,
        quantity: 1,
        label,
        unit_price: Number(variant.price || product.base_price || 0),
      };

      if (idx === -1) return [...prev, line];

      const next = [...prev];
      next[idx] = { ...next[idx], quantity: next[idx].quantity + 1 };
      return next;
    });
  };

  const updateQty = (key, qty) => {
    const quantity = Math.max(1, Number(qty) || 1);
    resetPlatformCheckout();
    setCart((prev) => prev.map((line) => (`${line.product_id}-${line.variant_id}` === key ? { ...line, quantity } : line)));
  };

  const removeLine = (key) => {
    resetPlatformCheckout();
    setCart((prev) => prev.filter((line) => `${line.product_id}-${line.variant_id}` !== key));
  };

  const externalPayload = () => {
    const stamp = Date.now();
    const shippingAmount = money(externalShippingAmount);

    const payload = {
      external_order_number: `WEB-${stamp}`,
      external_checkout_reference: `checkout-${stamp}`,
      payment_status: externalPaymentStatus,
      payment_gateway: externalPaymentGateway,
      payment_method: 'card',
      payment_reference: `pay-${stamp}`,
      placed_at: new Date().toISOString(),
      currency_code: catalog?.store?.currency || 'USD',
      shipping_total: 0,
      tax_total: 0,
      discount_total: 0,
      customer: {
        full_name: customerName.trim(),
        email: customerEmail.trim(),
        phone: customerPhone.trim() || null,
      },
      shipping_address: {
        name: customerName.trim(),
        address_line1: addressLine1.trim(),
        city: city.trim(),
        state: stateRegion.trim(),
        postal_code: postalCode.trim(),
        country: country.trim(),
        phone: customerPhone.trim() || null,
      },
      billing_address: {
        same_as_shipping: true,
      },
      items: cart.map(({ variant_id, quantity, unit_price }, index) => ({
        variant_id,
        quantity,
        unit_price: money(unit_price),
        external_line_id: `line-${stamp}-${index + 1}`,
      })),
    };

    if (includeShippingSnapshot && (externalShippingMethod.trim() || externalCarrierName.trim() || Number(shippingAmount) > 0)) {
      payload.shipping_total = shippingAmount;
      payload.shipping = {
        source: 'external',
        method_name: externalShippingMethod.trim() || null,
        carrier_name: externalCarrierName.trim() || null,
        amount: shippingAmount,
        currency: catalog?.store?.currency || 'USD',
      };
    }

    if (includeFulfillmentSnapshot && externalFulfillmentStatus.trim()) {
      payload.fulfillment = {
        managed_by: 'external',
        status: externalFulfillmentStatus.trim(),
        external_fulfillment_id: `ful-${stamp}`,
        external_shipment_id: `ship-${stamp}`,
        carrier_name: externalCarrierName.trim() || null,
        tracking_number: externalTrackingNumber.trim() || null,
        tracking_url: externalTrackingUrl.trim() || null,
        shipped_at: externalFulfillmentStatus === 'shipped' || externalFulfillmentStatus === 'delivered' ? new Date().toISOString() : null,
      };
    }

    return payload;
  };

  const shippingAddressPayload = () => ({
    name: customerName.trim(),
    address_line1: addressLine1.trim(),
    city: city.trim(),
    state: stateRegion.trim(),
    postal_code: postalCode.trim(),
    country: country.trim(),
    phone: customerPhone.trim() || null,
  });

  const platformPayload = () => ({
    source_channel: 'dev_storefront',
    currency_code: catalog?.store?.currency || 'USD',
    shipping_total: 0,
    customer: {
      full_name: customerName.trim(),
      email: customerEmail.trim(),
      phone: customerPhone.trim() || null,
    },
    shipping_address: shippingAddressPayload(),
    billing_address: {
      same_as_shipping: true,
    },
    items: cart.map(({ variant_id, quantity }) => ({
      variant_id,
      quantity,
    })),
  });

  const placeOrder = async () => {
    setError('');
    setOrderResult(null);
    if (!cart.length) {
      setError('Cart is empty.');
      return;
    }
    if (!customerName.trim()) {
      setError('Customer name is required.');
      return;
    }
    if (!customerEmail.trim()) {
      setError('Customer email is required for the order sync APIs.');
      return;
    }

    setLoading(true);
    try {
      const external = checkoutMode === 'external';
      const platform = checkoutMode === 'platform';
      if (platform && platformCheckoutDraft && deliveryOptions.length) {
        if (!selectedDeliveryOptionId) {
          throw new Error('Choose a delivery option before showing the Stripe payment form.');
        }

        const selectRes = await fetch(`${checkoutBase}/${platformCheckoutDraft.id}/shipping-method`, {
          method: 'POST',
          headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            ...authHeaders(),
          },
          body: JSON.stringify({
            shipping_method_id: selectedDeliveryOptionId,
            shipping_address: shippingAddressPayload(),
          }),
        });
        const selectRaw = await selectRes.text();
        let selectData = {};
        try {
          selectData = selectRaw ? JSON.parse(selectRaw) : {};
        } catch {
          selectData = {};
        }
        if (!selectRes.ok) {
          throw new Error(
            selectData.message ||
              (selectData.errors && JSON.stringify(selectData.errors)) ||
              (selectRaw.startsWith('<') ? `HTTP ${selectRes.status}: server returned HTML.` : selectRes.statusText) ||
              'Could not save the selected delivery option.'
          );
        }
        const payment = selectData.payment || {};
        if (!payment.publishable_key || !payment.client_secret) {
          throw new Error(selectData.message || 'Platform checkout is not enabled for this store. Connect Stripe in the SaaS dashboard or use External checkout sync.');
        }
        setPlatformPayment({
          checkout: selectData.checkout,
          payment,
        });
        setPlatformCheckoutDraft(null);
        setDeliveryOptions([]);
        setSelectedDeliveryOptionId('');
        await loadCatalog({ quiet: true });
        return;
      }

      const endpoint = platform ? checkoutBase : `${externalBase}/orders`;
      const res = await fetch(endpoint, {
        method: 'POST',
        headers: {
          Accept: 'application/json',
          'Content-Type': 'application/json',
          ...authHeaders(),
        },
        body: JSON.stringify(platform ? platformPayload() : externalPayload()),
      });
      const raw = await res.text();
      let data = {};
      try {
        data = raw ? JSON.parse(raw) : {};
      } catch {
        data = {};
      }
      if (!res.ok) {
        const msg =
          data.message ||
          (data.errors && JSON.stringify(data.errors)) ||
          (raw.startsWith('<') ? `HTTP ${res.status}: server returned HTML.` : res.statusText) ||
          'Order failed';
        throw new Error(msg);
      }
      if (platform) {
        const optionsRes = await fetch(`${checkoutBase}/${data.checkout?.id}/delivery-options`, {
          method: 'POST',
          headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            ...authHeaders(),
          },
          body: JSON.stringify({
            shipping_address: shippingAddressPayload(),
          }),
        });
        const optionsRaw = await optionsRes.text();
        let optionsData = {};
        try {
          optionsData = optionsRaw ? JSON.parse(optionsRaw) : {};
        } catch {
          optionsData = {};
        }
        if (optionsRes.ok && Array.isArray(optionsData.delivery_options) && optionsData.delivery_options.length) {
          setPlatformCheckoutDraft(data.checkout);
          setDeliveryOptions(optionsData.delivery_options);
          setSelectedDeliveryOptionId(String(optionsData.delivery_options[0].id));
          setPlatformPayment(null);
          await loadCatalog({ quiet: true });
          return;
        }

        const payment = data.payment || {};
        if (!payment.publishable_key || !payment.client_secret) {
          throw new Error(data.message || 'Platform checkout is not enabled for this store. Connect Stripe in the SaaS dashboard or use External checkout sync.');
        }
        setPlatformPayment({
          checkout: data.checkout,
          payment,
        });
        await loadCatalog({ quiet: true });
      } else {
        setOrderResult({ ...data.order, externalMode: external });
        setShipmentSyncMessage('');
        resetPlatformCheckout();
        setCart([]);
        await loadCatalog({ quiet: true });
      }
    } catch (e) {
      const msg =
        e instanceof TypeError && String(e.message).toLowerCase().includes('fetch')
          ? 'Could not reach the API. Check Laravel, Vite proxy, VITE_API_BASE, and VITE_EXTERNAL_API_BASE.'
          : e.message || 'Order failed';
      setError(msg);
    } finally {
      setLoading(false);
    }
  };

  const syncExternalShipment = async () => {
    setError('');
    setShipmentSyncMessage('');
    if (!orderResult?.external_order_number) {
      setError('Sync an external order first.');
      return;
    }

    setLoading(true);
    try {
      const stamp = Date.now();
      const res = await fetch(`${externalBase}/shipments`, {
        method: 'POST',
        headers: {
          Accept: 'application/json',
          'Content-Type': 'application/json',
          ...authHeaders(),
        },
        body: JSON.stringify({
          external_order_number: orderResult.external_order_number,
          external_shipment_id: `SHIP-${stamp}`,
          status: externalFulfillmentStatus || 'shipped',
          carrier_name: externalCarrierName.trim(),
          tracking_number: externalTrackingNumber.trim(),
          tracking_url: externalTrackingUrl.trim() || null,
          shipped_at: new Date().toISOString(),
        }),
      });
      const raw = await res.text();
      let data = {};
      try {
        data = raw ? JSON.parse(raw) : {};
      } catch {
        data = {};
      }
      if (!res.ok) {
        throw new Error(data.message || (data.errors && JSON.stringify(data.errors)) || 'Shipment sync failed');
      }
      setShipmentSyncMessage(data.message || 'External shipment synced.');
    } catch (e) {
      setError(e.message || 'Shipment sync failed');
    } finally {
      setLoading(false);
    }
  };

  const confirmPlatformPayment = async () => {
    setError('');
    setStripeCardMessage('');

    if (!platformPayment?.payment?.client_secret || !stripeRef.current || !cardElementRef.current) {
      setStripeCardMessage('Stripe payment form is still loading.');
      return;
    }

    setStripePaymentProcessing(true);
    try {
      const confirmation = await stripeRef.current.confirmCardPayment(platformPayment.payment.client_secret, {
        payment_method: {
          card: cardElementRef.current,
          billing_details: {
            name: customerName.trim(),
            email: customerEmail.trim(),
            phone: customerPhone.trim() || undefined,
            address: {
              line1: addressLine1.trim(),
              city: city.trim(),
              state: stateRegion.trim(),
              postal_code: postalCode.trim(),
              country: country.trim(),
            },
          },
        },
      });

      if (confirmation?.error) {
        setStripeCardMessage(confirmation.error.message || 'Stripe test payment failed.');
        return;
      }

      const confirmRes = await fetch(`${checkoutBase}/${platformPayment.checkout?.id}/confirm`, {
        method: 'POST',
        headers: {
          Accept: 'application/json',
          'Content-Type': 'application/json',
          ...authHeaders(),
        },
      });
      const confirmRaw = await confirmRes.text();
      let confirmData = {};
      try {
        confirmData = confirmRaw ? JSON.parse(confirmRaw) : {};
      } catch {
        confirmData = {};
      }
      if (!confirmRes.ok) {
        throw new Error(
          confirmData.message ||
            (confirmData.errors && JSON.stringify(confirmData.errors)) ||
            (confirmRaw.startsWith('<') ? `HTTP ${confirmRes.status}: server returned HTML.` : confirmRes.statusText) ||
            'Stripe payment was confirmed, but Laravel could not create the order yet.'
        );
      }

      setOrderResult({
        platformMode: true,
        checkout_number: confirmData.checkout?.checkout_number || platformPayment.checkout?.checkout_number,
        order_number: confirmData.order?.order_number,
        total: confirmData.order?.total || platformPayment.checkout?.grand_total,
        currency_code: confirmData.order?.currency_code || platformPayment.checkout?.currency_code,
        payment_reference: platformPayment.payment?.provider_intent_id,
        message: confirmData.order?.order_number
          ? 'Stripe test payment confirmed and the order was created in the SaaS dashboard.'
          : 'Stripe test payment confirmed. Refresh the dashboard orders page.',
      });
      setPlatformPayment(null);
      setCart([]);
      await loadCatalog({ quiet: true });
    } catch (e) {
      setStripeCardMessage(e.message || 'Stripe test payment failed.');
    } finally {
      setStripePaymentProcessing(false);
    }
  };

  const tokenConfigured = Boolean((import.meta.env.VITE_STOREFRONT_TOKEN || '').trim());

  return (
    <div style={{ maxWidth: 960, margin: '0 auto', padding: '1.5rem' }}>
      <header style={{ marginBottom: '1.5rem' }}>
        <h1 style={{ margin: '0 0 0.35rem', fontSize: '1.5rem' }}>Developer test storefront</h1>
        <p style={{ margin: 0, color: '#64748b', fontSize: '0.9rem' }}>
          Local simulator for testing product fetches, external order sync, and platform checkout against the SaaS dashboard.
        </p>
      </header>

      {!tokenConfigured && (
        <div
          style={{
            background: '#fff7ed',
            border: '1px solid #fdba74',
            color: '#9a3412',
            padding: '0.75rem 1rem',
            borderRadius: 8,
            marginBottom: '1rem',
            fontSize: '0.9rem',
          }}
        >
          Set <code>VITE_STOREFRONT_TOKEN</code> in <code>dev-test-storefront/.env</code> using the token from Dashboard &gt; Dev storefront.
        </div>
      )}

      <div style={{ display: 'flex', gap: '0.75rem', flexWrap: 'wrap', marginBottom: '1rem' }}>
        <button
          type="button"
          onClick={loadCatalog}
          disabled={loading}
          style={{
            padding: '0.5rem 1rem',
            borderRadius: 8,
            border: 'none',
            background: '#0052cc',
            color: '#fff',
            fontWeight: 600,
          }}
        >
          {loading ? 'Loading...' : 'Load catalog'}
        </button>
      </div>

      {error && (
        <div
          style={{
            background: '#fef2f2',
            border: '1px solid #fecaca',
            color: '#b91c1c',
            padding: '0.75rem 1rem',
            borderRadius: 8,
            marginBottom: '1rem',
            fontSize: '0.9rem',
          }}
        >
          {error}
        </div>
      )}

      {orderResult && (
        <div
          style={{
            background: '#ecfdf5',
            border: '1px solid #6ee7b7',
            color: '#065f46',
            padding: '1rem',
            borderRadius: 8,
            marginBottom: '1rem',
          }}
        >
          <strong>
            {orderResult.platformMode
              ? 'Platform checkout started.'
              : orderResult.externalMode
                ? 'External checkout order synced to SaaS dashboard.'
                : 'External order synced to SaaS dashboard.'}
          </strong>
          {orderResult.platformMode ? (
            <div style={{ marginTop: 4 }}>
              Checkout <code>{orderResult.checkout_number}</code>
              {orderResult.order_number ? (
                <>
                  {' '}
                  created order <code>{orderResult.order_number}</code>
                </>
              ) : null}
              , payment <code>{orderResult.payment_reference || 'not created'}</code>, total{' '}
              {orderResult.total} {orderResult.currency_code}. {orderResult.message}
            </div>
          ) : (
            <div style={{ marginTop: 4 }}>
              SaaS order <code>{orderResult.order_number}</code>
              {orderResult.external_order_number ? (
                <>
                  {' '}
                  from external order <code>{orderResult.external_order_number}</code>
                </>
              ) : null}
              , total {orderResult.total} {orderResult.currency_code || orderResult.currency}.
            </div>
          )}
          {orderResult.externalMode && orderResult.external_order_number ? (
            <div style={{ marginTop: '0.75rem', display: 'flex', flexWrap: 'wrap', gap: '0.5rem' }}>
              <button
                type="button"
                onClick={syncExternalShipment}
                disabled={loading}
                style={{
                  padding: '0.45rem 0.8rem',
                  borderRadius: 8,
                  border: '1px solid #6ee7b7',
                  background: '#fff',
                  color: '#065f46',
                  fontWeight: 600,
                }}
              >
                Send shipment update
              </button>
              {shipmentSyncMessage ? <span style={{ fontSize: '0.85rem' }}>{shipmentSyncMessage}</span> : null}
            </div>
          ) : null}
        </div>
      )}

      <div className="layout-grid">
        <section
          style={{
            background: '#fff',
            borderRadius: 12,
            border: '1px solid #e2e8f0',
            padding: '1rem',
          }}
        >
          <h2 style={{ margin: '0 0 1rem', fontSize: '1.1rem' }}>Products</h2>
          {!catalog && <p style={{ color: '#64748b', margin: 0 }}>Load catalog to see products.</p>}
          {catalog && (
            <ul style={{ listStyle: 'none', margin: 0, padding: 0, display: 'grid', gap: '1rem' }}>
              {catalog.products?.map((product) => (
                <li
                  key={product.id}
                  style={{
                    border: '1px solid #f1f5f9',
                    borderRadius: 10,
                    padding: '0.75rem',
                    display: 'grid',
                    gridTemplateColumns: product.primary_image_url ? '72px 1fr' : '1fr',
                    gap: '0.75rem',
                  }}
                >
                  {product.primary_image_url && (
                    <img
                      src={product.primary_image_url}
                      alt=""
                      style={{ width: 72, height: 72, objectFit: 'cover', borderRadius: 8 }}
                    />
                  )}
                  <div>
                    <div style={{ fontWeight: 600 }}>{product.name}</div>
                    <div style={{ fontSize: '0.8rem', color: '#64748b' }}>{product.product_type}</div>
                    <div style={{ marginTop: '0.5rem', display: 'flex', flexDirection: 'column', gap: '0.35rem' }}>
                      {product.variants?.map((variant) => (
                        <div
                          key={variant.id}
                          style={{
                            display: 'flex',
                            alignItems: 'center',
                            justifyContent: 'space-between',
                            gap: '0.5rem',
                            flexWrap: 'wrap',
                          }}
                        >
                          <span style={{ fontSize: '0.85rem' }}>
                            {variant.options?.length ? variant.options.map((option) => `${option.type}: ${option.value}`).join(' / ') : 'Default'} -{' '}
                            <strong>{variant.price}</strong> {catalog.store?.currency} (stock {variant.stock})
                          </span>
                          <button
                            type="button"
                            onClick={() => addToCart(product, variant)}
                            disabled={variant.stock < 1}
                            style={{
                              padding: '0.25rem 0.6rem',
                              borderRadius: 6,
                              border: '1px solid #cbd5e1',
                              background: variant.stock < 1 ? '#f1f5f9' : '#fff',
                            }}
                          >
                            Add
                          </button>
                        </div>
                      ))}
                    </div>
                  </div>
                </li>
              ))}
            </ul>
          )}
        </section>

        <aside
          style={{
            background: '#fff',
            borderRadius: 12,
            border: '1px solid #e2e8f0',
            padding: '1rem',
            position: 'sticky',
            top: '1rem',
          }}
        >
          <h2 style={{ margin: '0 0 0.75rem', fontSize: '1.1rem' }}>Cart</h2>
          {!cart.length && <p style={{ color: '#64748b', fontSize: '0.9rem', margin: 0 }}>Empty</p>}
          <ul style={{ listStyle: 'none', margin: 0, padding: 0, fontSize: '0.9rem' }}>
            {cart.map((line) => {
              const key = `${line.product_id}-${line.variant_id}`;
              return (
                <li key={key} style={{ marginBottom: '0.65rem', paddingBottom: '0.65rem', borderBottom: '1px solid #f1f5f9' }}>
                  <div>{line.label}</div>
                  <div style={{ marginTop: '0.35rem', display: 'flex', alignItems: 'center', gap: '0.5rem' }}>
                    <label style={{ fontSize: '0.75rem', color: '#64748b' }}>
                      Qty
                      <input
                        type="number"
                        min={1}
                        value={line.quantity}
                        onChange={(e) => updateQty(key, e.target.value)}
                        style={{ width: 56, marginLeft: 4, padding: '0.2rem 0.35rem' }}
                      />
                    </label>
                    <span style={{ fontSize: '0.8rem', color: '#334155' }}>{money(Number(line.unit_price) * Number(line.quantity))}</span>
                    <button type="button" onClick={() => removeLine(key)} style={{ fontSize: '0.75rem', color: '#b91c1c' }}>
                      Remove
                    </button>
                  </div>
                </li>
              );
            })}
          </ul>

          <div style={{ borderTop: '1px solid #f1f5f9', paddingTop: '0.75rem', marginTop: '0.5rem' }}>
            <div style={{ display: 'flex', justifyContent: 'space-between', fontSize: '0.86rem', color: '#475569' }}>
              <span>Items</span>
              <span>{money(cartTotal)} {catalog?.store?.currency || 'USD'}</span>
            </div>
            {selectedDeliveryOption && (
              <div style={{ display: 'flex', justifyContent: 'space-between', marginTop: 4, fontSize: '0.86rem', color: '#475569' }}>
                <span>{selectedDeliveryOption.name}</span>
                <span>{money(selectedDeliveryOption.amount)} {selectedDeliveryOption.currency_code || catalog?.store?.currency || 'USD'}</span>
              </div>
            )}
            <div style={{ display: 'flex', justifyContent: 'space-between', marginTop: 8, fontWeight: 700 }}>
              <span>Total</span>
              <span>{money(displayedTotal)} {catalog?.store?.currency || 'USD'}</span>
            </div>
          </div>

          <div style={{ marginTop: '1rem', display: 'grid', gap: '0.5rem' }}>
            <h3 style={{ margin: 0, fontSize: '0.95rem', color: '#0f172a' }}>Developer payload simulator</h3>
            <p style={{ margin: 0, color: '#64748b', fontSize: '0.78rem', lineHeight: 1.55 }}>
              This screen is only for local testing. A real external website may collect these values from checkout, payment provider, shipping plugin, fulfillment app, or merchant admin. Customers normally do not enter tracking or fulfillment fields during checkout.
            </p>
            <p style={{ margin: 0, color: '#64748b', fontSize: '0.78rem', lineHeight: 1.5 }}>
              Use the checkout mode selected in the SaaS dashboard on a real storefront. This simulator sends test payloads only.
            </p>
            <label style={{ display: 'flex', gap: 8, alignItems: 'center', fontSize: '0.85rem' }}>
              <input type="radio" checked={checkoutMode === 'external'} onChange={() => { setCheckoutMode('external'); resetPlatformCheckout(); }} />
              External managed order
            </label>
            {checkoutMode === 'external' && (
              <p style={{ margin: '-0.25rem 0 0 1.45rem', color: '#64748b', fontSize: '0.78rem', lineHeight: 1.5 }}>
                Simulates a website that manages checkout, payment, shipping, and fulfillment, then sends snapshots into the dashboard.
              </p>
            )}
            {checkoutMode === 'external' && (
              <p style={{ margin: '-0.25rem 0 0 1.45rem', color: '#64748b', fontSize: '0.78rem', lineHeight: 1.5 }}>
                Inventory behavior: {catalog?.store?.external_checkout?.inventory_owner === 'external' ? 'External inventory' : 'Dashboard inventory'}.
                {catalog?.store?.external_checkout?.inventory_owner === 'external'
                  ? ' External orders are recorded here without changing dashboard stock.'
                  : ' This simulator sends orders that reduce SaaS dashboard stock because the test storefront fetches products from the SaaS catalog.'}
              </p>
            )}
            <label style={{ display: 'flex', gap: 8, alignItems: 'center', fontSize: '0.85rem' }}>
              <input type="radio" checked={checkoutMode === 'platform'} onChange={() => { setCheckoutMode('platform'); resetPlatformCheckout(); }} />
              Platform checkout
            </label>
            {checkoutMode === 'platform' && (
              <p style={{ margin: '-0.25rem 0 0 1.45rem', color: '#64748b', fontSize: '0.78rem', lineHeight: 1.5 }}>
                Simulates a storefront using the platform checkout flow. Payment is confirmed before the order is created.
              </p>
            )}

            <div style={{ borderTop: '1px solid #e2e8f0', paddingTop: '0.75rem' }}>
              <h4 style={{ margin: '0 0 0.35rem', fontSize: '0.85rem', color: '#334155' }}>A. Customer checkout data</h4>
              <p style={{ margin: '0 0 0.65rem', color: '#64748b', fontSize: '0.76rem', lineHeight: 1.5 }}>
                These fields usually come from the customer checkout form.
              </p>
            <label style={{ fontSize: '0.8rem' }}>
              Customer name
              <input
                value={customerName}
                onChange={(e) => { setCustomerName(e.target.value); resetPlatformCheckout(); }}
                style={{ display: 'block', width: '100%', marginTop: 4, padding: '0.35rem 0.5rem', borderRadius: 6, border: '1px solid #cbd5e1' }}
              />
            </label>
            <div style={{ display: 'flex', gap: '0.5rem' }}>
              <label style={{ fontSize: '0.8rem', flex: 1 }}>
                Email
                <input
                  type="email"
                  value={customerEmail}
                  onChange={(e) => { setCustomerEmail(e.target.value); resetPlatformCheckout(); }}
                  style={{ display: 'block', width: '100%', marginTop: 4, padding: '0.35rem 0.5rem', borderRadius: 6, border: '1px solid #cbd5e1' }}
                />
              </label>
              <label style={{ fontSize: '0.8rem', flex: 1 }}>
                Phone
                <input
                  type="tel"
                  value={customerPhone}
                  onChange={(e) => { setCustomerPhone(e.target.value); resetPlatformCheckout(); }}
                  style={{ display: 'block', width: '100%', marginTop: 4, padding: '0.35rem 0.5rem', borderRadius: 6, border: '1px solid #cbd5e1' }}
                />
              </label>
            </div>

            <label style={{ fontSize: '0.8rem', display: 'block', marginTop: '0.5rem' }}>
              Address line 1
              <input
                value={addressLine1}
                onChange={(e) => { setAddressLine1(e.target.value); resetPlatformCheckout(); }}
                style={{ display: 'block', width: '100%', marginTop: 4, padding: '0.35rem 0.5rem', borderRadius: 6, border: '1px solid #cbd5e1' }}
              />
            </label>
            <div style={{ display: 'flex', gap: '0.5rem' }}>
              <label style={{ fontSize: '0.8rem', flex: 1 }}>
                City
                <input
                  value={city}
                  onChange={(e) => { setCity(e.target.value); resetPlatformCheckout(); }}
                  style={{ display: 'block', width: '100%', marginTop: 4, padding: '0.35rem 0.5rem', borderRadius: 6, border: '1px solid #cbd5e1' }}
                />
              </label>
              <label style={{ fontSize: '0.8rem', flex: 1 }}>
                State/region
                <input
                  value={stateRegion}
                  onChange={(e) => { setStateRegion(e.target.value); resetPlatformCheckout(); }}
                  style={{ display: 'block', width: '100%', marginTop: 4, padding: '0.35rem 0.5rem', borderRadius: 6, border: '1px solid #cbd5e1' }}
                />
              </label>
            </div>
            <div style={{ display: 'flex', gap: '0.5rem' }}>
              <label style={{ fontSize: '0.8rem', flex: 1 }}>
                Postal code
                <input
                  value={postalCode}
                  onChange={(e) => { setPostalCode(e.target.value); resetPlatformCheckout(); }}
                  style={{ display: 'block', width: '100%', marginTop: 4, padding: '0.35rem 0.5rem', borderRadius: 6, border: '1px solid #cbd5e1' }}
                />
              </label>
              <label style={{ fontSize: '0.8rem', flex: 1 }}>
                Country
                <input
                  value={country}
                  onChange={(e) => { setCountry(e.target.value); resetPlatformCheckout(); }}
                  style={{ display: 'block', width: '100%', marginTop: 4, padding: '0.35rem 0.5rem', borderRadius: 6, border: '1px solid #cbd5e1' }}
                />
              </label>
            </div>
            </div>

            {checkoutMode === 'external' && (
              <>
                <div style={{ borderTop: '1px solid #e2e8f0', paddingTop: '0.75rem' }}>
                  <h4 style={{ margin: '0 0 0.35rem', fontSize: '0.85rem', color: '#334155' }}>B. External payment snapshot</h4>
                  <p style={{ margin: '0 0 0.65rem', color: '#64748b', fontSize: '0.76rem', lineHeight: 1.5 }}>
                    These values usually come from the external website or payment gateway. This SaaS records them but does not process payment again.
                  </p>
                  <label style={{ fontSize: '0.8rem' }}>
                    Payment status
                    <select
                      value={externalPaymentStatus}
                      onChange={(e) => setExternalPaymentStatus(e.target.value)}
                      style={{ display: 'block', width: '100%', marginTop: 4, padding: '0.35rem 0.5rem', borderRadius: 6, border: '1px solid #cbd5e1' }}
                    >
                      <option value="paid">paid</option>
                      <option value="pending">pending</option>
                      <option value="authorized">authorized</option>
                      <option value="cod_pending">cod_pending</option>
                    </select>
                  </label>
                  <label style={{ fontSize: '0.8rem', display: 'block', marginTop: '0.5rem' }}>
                    Payment gateway
                    <input
                      value={externalPaymentGateway}
                      onChange={(e) => setExternalPaymentGateway(e.target.value)}
                      style={{ display: 'block', width: '100%', marginTop: 4, padding: '0.35rem 0.5rem', borderRadius: 6, border: '1px solid #cbd5e1' }}
                    />
                  </label>
                </div>

                <div style={{ borderTop: '1px solid #e2e8f0', paddingTop: '0.75rem' }}>
                  <label style={{ display: 'flex', gap: 8, alignItems: 'center', fontSize: '0.82rem', fontWeight: 600, color: '#334155' }}>
                    <input type="checkbox" checked={includeShippingSnapshot} onChange={(e) => setIncludeShippingSnapshot(e.target.checked)} />
                    C. Include external shipping snapshot
                  </label>
                  <p style={{ margin: '0.35rem 0 0.65rem', color: '#64748b', fontSize: '0.76rem', lineHeight: 1.5 }}>
                    These values usually come from the external storefront shipping rules or shipping plugin. Optional unless your test scenario needs shipping totals.
                  </p>
                  {includeShippingSnapshot && (
                    <>
                      <label style={{ fontSize: '0.8rem' }}>
                        Shipping method
                        <input
                          value={externalShippingMethod}
                          onChange={(e) => setExternalShippingMethod(e.target.value)}
                          style={{ display: 'block', width: '100%', marginTop: 4, padding: '0.35rem 0.5rem', borderRadius: 6, border: '1px solid #cbd5e1' }}
                        />
                      </label>
                      <label style={{ fontSize: '0.8rem', display: 'block', marginTop: '0.5rem' }}>
                        Shipping amount
                        <input
                          value={externalShippingAmount}
                          onChange={(e) => setExternalShippingAmount(e.target.value)}
                          style={{ display: 'block', width: '100%', marginTop: 4, padding: '0.35rem 0.5rem', borderRadius: 6, border: '1px solid #cbd5e1' }}
                        />
                      </label>
                      <label style={{ fontSize: '0.8rem', display: 'block', marginTop: '0.5rem' }}>
                        Carrier name (optional)
                        <input
                          value={externalCarrierName}
                          onChange={(e) => setExternalCarrierName(e.target.value)}
                          style={{ display: 'block', width: '100%', marginTop: 4, padding: '0.35rem 0.5rem', borderRadius: 6, border: '1px solid #cbd5e1' }}
                        />
                      </label>
                    </>
                  )}
                </div>

                <div style={{ borderTop: '1px solid #e2e8f0', paddingTop: '0.75rem' }}>
                  <label style={{ display: 'flex', gap: 8, alignItems: 'center', fontSize: '0.82rem', fontWeight: 600, color: '#334155' }}>
                    <input type="checkbox" checked={includeFulfillmentSnapshot} onChange={(e) => setIncludeFulfillmentSnapshot(e.target.checked)} />
                    D. Include external fulfillment update
                  </label>
                  <p style={{ margin: '0.35rem 0 0.65rem', color: '#64748b', fontSize: '0.76rem', lineHeight: 1.5 }}>
                    These are post-order fulfillment values. In real integrations they often arrive later through a shipment update, not during checkout.
                  </p>
                  {includeFulfillmentSnapshot && (
                    <>
                      <label style={{ fontSize: '0.8rem' }}>
                        Fulfillment status
                        <select
                          value={externalFulfillmentStatus}
                          onChange={(e) => setExternalFulfillmentStatus(e.target.value)}
                          style={{ display: 'block', width: '100%', marginTop: 4, padding: '0.35rem 0.5rem', borderRadius: 6, border: '1px solid #cbd5e1' }}
                        >
                          <option value="">Select status</option>
                          <option value="pending">pending</option>
                          <option value="processing">processing</option>
                          <option value="shipped">shipped</option>
                          <option value="delivered">delivered</option>
                        </select>
                      </label>
                      <label style={{ fontSize: '0.8rem', display: 'block', marginTop: '0.5rem' }}>
                        Tracking number (optional)
                        <input
                          value={externalTrackingNumber}
                          onChange={(e) => setExternalTrackingNumber(e.target.value)}
                          style={{ display: 'block', width: '100%', marginTop: 4, padding: '0.35rem 0.5rem', borderRadius: 6, border: '1px solid #cbd5e1' }}
                        />
                      </label>
                      <label style={{ fontSize: '0.8rem', display: 'block', marginTop: '0.5rem' }}>
                        Tracking URL (optional)
                        <input
                          value={externalTrackingUrl}
                          onChange={(e) => setExternalTrackingUrl(e.target.value)}
                          style={{ display: 'block', width: '100%', marginTop: 4, padding: '0.35rem 0.5rem', borderRadius: 6, border: '1px solid #cbd5e1' }}
                        />
                      </label>
                    </>
                  )}
                </div>
              </>
            )}

            {checkoutMode === 'platform' && platformCheckoutDraft && deliveryOptions.length > 0 && (
              <div
                style={{
                  marginTop: '0.5rem',
                  border: '1px solid #cbd5e1',
                  borderRadius: 10,
                  padding: '0.75rem',
                  background: '#f8fafc',
                }}
              >
                <h3 style={{ margin: 0, fontSize: '0.9rem', color: '#0f172a' }}>Delivery options</h3>
                <div style={{ marginTop: '0.6rem', display: 'grid', gap: '0.5rem' }}>
                  {deliveryOptions.map((option) => (
                    <label
                      key={option.id}
                      style={{
                        display: 'grid',
                        gridTemplateColumns: 'auto 1fr auto',
                        gap: '0.55rem',
                        alignItems: 'start',
                        padding: '0.65rem',
                        borderRadius: 8,
                        border: String(option.id) === String(selectedDeliveryOptionId) ? '1px solid #0052cc' : '1px solid #e2e8f0',
                        background: '#fff',
                        fontSize: '0.84rem',
                      }}
                    >
                      <input
                        type="radio"
                        checked={String(option.id) === String(selectedDeliveryOptionId)}
                        onChange={() => setSelectedDeliveryOptionId(String(option.id))}
                        style={{ marginTop: 2 }}
                      />
                      <span>
                        <strong style={{ display: 'block', color: '#0f172a' }}>{option.name}</strong>
                        <span style={{ color: '#64748b', fontSize: '0.76rem' }}>
                          {[option.delivery_speed_label, option.description].filter(Boolean).join(' - ') || 'Delivery option'}
                        </span>
                      </span>
                      <strong style={{ color: '#0f172a' }}>{money(option.amount)}</strong>
                    </label>
                  ))}
                </div>
              </div>
            )}

            <button
              type="button"
              onClick={placeOrder}
              disabled={loading || !cart.length || Boolean(platformPayment)}
              style={{
                marginTop: '0.5rem',
                padding: '0.5rem 1rem',
                borderRadius: 8,
                border: 'none',
                background: cart.length && !platformPayment ? '#0f172a' : '#94a3b8',
                color: '#fff',
                fontWeight: 600,
              }}
            >
              {checkoutMode === 'platform'
                ? platformPayment
                  ? 'Stripe form ready'
                  : platformCheckoutDraft && deliveryOptions.length
                    ? 'Use delivery option and show Stripe'
                    : 'Show delivery options'
                : 'Sync external checkout order'}
            </button>

            {checkoutMode === 'platform' && platformPayment && (
              <div
                style={{
                  marginTop: '0.75rem',
                  border: '1px solid #cbd5e1',
                  borderRadius: 10,
                  padding: '0.85rem',
                  background: '#f8fafc',
                }}
              >
                <h3 style={{ margin: 0, fontSize: '0.95rem', color: '#0f172a' }}>Stripe payment</h3>
                <p style={{ margin: '0.35rem 0 0.75rem', fontSize: '0.78rem', color: '#64748b' }}>
                  Enter a Stripe test card. Try <code>4242 4242 4242 4242</code>, any future date, any CVC.
                  {platformPayment.payment?.connection_label ? ` ${platformPayment.payment.connection_label}.` : ''}
                </p>
                <div
                  ref={cardContainerRef}
                  style={{
                    minHeight: 44,
                    border: '1px solid #cbd5e1',
                    background: '#fff',
                    borderRadius: 8,
                    padding: '0.75rem',
                  }}
                />
                {!stripeFormReady && !stripeCardMessage && (
                  <p style={{ margin: '0.5rem 0 0', fontSize: '0.78rem', color: '#64748b' }}>Loading Stripe payment form...</p>
                )}
                {stripeCardMessage && (
                  <p style={{ margin: '0.5rem 0 0', fontSize: '0.78rem', color: '#b91c1c' }}>{stripeCardMessage}</p>
                )}
                <button
                  type="button"
                  onClick={confirmPlatformPayment}
                  disabled={!stripeFormReady || stripePaymentProcessing}
                  style={{
                    width: '100%',
                    marginTop: '0.75rem',
                    padding: '0.5rem 1rem',
                    borderRadius: 8,
                    border: 'none',
                    background: stripeFormReady && !stripePaymentProcessing ? '#0052cc' : '#94a3b8',
                    color: '#fff',
                    fontWeight: 700,
                  }}
                >
                  {stripePaymentProcessing ? 'Confirming payment...' : 'Pay with Stripe'}
                </button>
              </div>
            )}
          </div>
        </aside>
      </div>
    </div>
  );
}
