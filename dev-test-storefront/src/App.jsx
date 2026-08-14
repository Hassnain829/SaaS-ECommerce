import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { loadStripe } from '@stripe/stripe-js';

const defaultApiBase = '/api/developer-storefront';

function apiBase() {
  return (import.meta.env.VITE_API_BASE || defaultApiBase).replace(/\/$/, '');
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
  const checkoutMode = 'platform';
  const [customerName, setCustomerName] = useState('Dev Customer');
  const [customerEmail, setCustomerEmail] = useState('dev.customer@example.test');
  const [customerPhone, setCustomerPhone] = useState('+1 555-0198');
  const [addressLine1, setAddressLine1] = useState('123 Developer Way');
  const [city, setCity] = useState('San Francisco');
  const [stateRegion, setStateRegion] = useState('CA');
  const [postalCode, setPostalCode] = useState('94105');
  const [country, setCountry] = useState('US');
  const [orderResult, setOrderResult] = useState(null);
  const [platformCouponCode, setPlatformCouponCode] = useState('');
  const [platformCheckoutDraft, setPlatformCheckoutDraft] = useState(null);
  const [deliveryOptions, setDeliveryOptions] = useState([]);
  const [deliveryOptionsWarning, setDeliveryOptionsWarning] = useState('');
  const [selectedDeliveryOptionId, setSelectedDeliveryOptionId] = useState('');
  const [selectedPickupLocationId, setSelectedPickupLocationId] = useState('');
  const [platformPayment, setPlatformPayment] = useState(null);
  const [stripeFormReady, setStripeFormReady] = useState(false);
  const [stripePaymentProcessing, setStripePaymentProcessing] = useState(false);
  const [stripeCardMessage, setStripeCardMessage] = useState('');
  const stripeRef = useRef(null);
  const cardElementRef = useRef(null);
  const cardContainerRef = useRef(null);

  const base = useMemo(() => apiBase(), []);
  const checkoutBase = useMemo(() => checkoutApiBase(base), [base]);
  const cartTotal = cart.reduce((sum, line) => sum + Number(line.unit_price || 0) * Number(line.quantity || 1), 0);
  const selectedDeliveryOption = useMemo(
    () => deliveryOptions.find((option) => String(option.id) === String(selectedDeliveryOptionId)) || null,
    [deliveryOptions, selectedDeliveryOptionId]
  );
  const finalPaymentCheckout = platformPayment?.checkout ?? null;
  const checkoutDraft = platformCheckoutDraft ?? null;
  const isPlatformFinalReady = checkoutMode === 'platform' && Boolean(finalPaymentCheckout);
  const isPlatformDeliverySelecting = checkoutMode === 'platform' && Boolean(checkoutDraft) && !finalPaymentCheckout;
  const platformCheckoutStep =
    checkoutMode === 'platform'
      ? isPlatformFinalReady
        ? 3
        : isPlatformDeliverySelecting
          ? 2
          : 1
      : 0;
  const platformCurrency =
    finalPaymentCheckout?.currency_code || checkoutDraft?.currency_code || catalog?.store?.currency || 'USD';

  const resetPlatformCheckout = () => {
    setPlatformPayment(null);
    setPlatformCheckoutDraft(null);
    setDeliveryOptions([]);
    setDeliveryOptionsWarning('');
    setSelectedDeliveryOptionId('');
    setSelectedPickupLocationId('');
  };

  useEffect(() => {
    if (!selectedDeliveryOption?.pickup_required) {
      setSelectedPickupLocationId('');
      return;
    }

    if (selectedDeliveryOption.pickup_locations?.length === 1) {
      setSelectedPickupLocationId(String(selectedDeliveryOption.pickup_locations[0].id));
      return;
    }

    if (!selectedDeliveryOption.pickup_locations?.some((location) => String(location.id) === String(selectedPickupLocationId))) {
      setSelectedPickupLocationId('');
    }
  }, [selectedDeliveryOption, selectedPickupLocationId]);

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

  const shippingAddressPayload = () => ({
    name: customerName.trim(),
    address_line1: addressLine1.trim(),
    city: city.trim(),
    state: stateRegion.trim(),
    postal_code: postalCode.trim(),
    country: country.trim(),
    phone: customerPhone.trim() || null,
  });

  const applyDeliveryOptionsResponse = async (checkout, optionsRes, optionsRaw) => {
    let optionsData = {};
    try {
      optionsData = optionsRaw ? JSON.parse(optionsRaw) : {};
    } catch {
      optionsData = {};
    }

    if (!optionsRes.ok) {
      throw new Error(
        optionsData.message ||
          (optionsData.errors && JSON.stringify(optionsData.errors)) ||
          (optionsRaw.startsWith('<') ? `HTTP ${optionsRes.status}: server returned HTML.` : optionsRes.statusText) ||
          'Could not load delivery options.'
      );
    }

    if (Array.isArray(optionsData.delivery_options) && optionsData.delivery_options.length) {
      setPlatformCheckoutDraft(checkout);
      setDeliveryOptions(optionsData.delivery_options);
      setDeliveryOptionsWarning('');
      setSelectedDeliveryOptionId(String(optionsData.delivery_options[0].id));
      setPlatformPayment(null);
      await loadCatalog({ quiet: true });
      return true;
    }

    if (Array.isArray(optionsData.delivery_options) && optionsData.delivery_options.length === 0) {
      setPlatformCheckoutDraft(checkout);
      setDeliveryOptions([]);
      setDeliveryOptionsWarning(
        'No delivery methods matched this address or cart total. In the merchant dashboard, check: shipping zone (state/postal), method min/max order amount, and Checkout enabled. Shipping stays $0 until you pick a delivery option.'
      );
      setPlatformPayment(null);
      await loadCatalog({ quiet: true });
      return true;
    }

    return false;
  };

  const platformPayload = () => ({
    source_channel: 'dev_storefront',
    currency_code: catalog?.store?.currency || 'USD',
    coupon_code: platformCouponCode.trim() || null,
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
      setError('Customer email is required.');
      return;
    }

    setLoading(true);
    try {
      if (platformCheckoutDraft && deliveryOptionsWarning && !deliveryOptions.length) {
        const optionsRes = await fetch(`${checkoutBase}/${platformCheckoutDraft.id}/delivery-options`, {
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
        await applyDeliveryOptionsResponse(platformCheckoutDraft, optionsRes, optionsRaw);
        return;
      }
      if (platformCheckoutDraft && deliveryOptions.length) {
        if (!selectedDeliveryOptionId) {
          throw new Error('Choose a delivery option before showing the Stripe payment form.');
        }
        if (selectedDeliveryOption?.pickup_required && selectedDeliveryOption.pickup_locations?.length > 1 && !selectedPickupLocationId) {
          throw new Error('Choose a pickup location before showing the Stripe payment form.');
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
            pickup_location_id: selectedDeliveryOption?.pickup_required ? selectedPickupLocationId || null : null,
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
          throw new Error(selectData.message || 'Platform checkout is not enabled for this store. Connect Stripe in Payments.');
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

      const res = await fetch(checkoutBase, {
        method: 'POST',
        headers: {
          Accept: 'application/json',
          'Content-Type': 'application/json',
          ...authHeaders(),
        },
        body: JSON.stringify(platformPayload()),
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
        const handled = await applyDeliveryOptionsResponse(data.checkout, optionsRes, optionsRaw);
        if (handled) {
          return;
        }

        const payment = data.payment || {};
        if (!payment.publishable_key || !payment.client_secret) {
          throw new Error(data.message || 'Platform checkout is not enabled for this store. Connect Stripe in Payments.');
        }
        setPlatformPayment({
          checkout: data.checkout,
          payment,
        });
        await loadCatalog({ quiet: true });

    } catch (e) {
      const msg =
        e instanceof TypeError && String(e.message).toLowerCase().includes('fetch')
          ? 'Could not reach the API. Check Laravel, Vite proxy, and VITE_API_BASE.'
          : e.message || 'Order failed';
      setError(msg);
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
          Local simulator for testing product fetches and platform checkout against the SaaS dashboard.
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
          <strong>Platform checkout started.</strong>
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
          {checkoutMode === 'platform' && cart.length > 0 && (
            <div
              style={{
                marginBottom: '0.75rem',
                padding: '0.65rem 0.75rem',
                borderRadius: 8,
                background: '#eff6ff',
                border: '1px solid #bfdbfe',
                fontSize: '0.78rem',
                color: '#1e3a8a',
                lineHeight: 1.5,
              }}
            >
              <strong style={{ display: 'block', marginBottom: 4 }}>Platform checkout steps</strong>
              <span style={{ opacity: platformCheckoutStep === 1 ? 1 : 0.65 }}>1. Cart + address</span>
              {' → '}
              <span style={{ opacity: platformCheckoutStep === 2 ? 1 : 0.65 }}>2. Choose delivery</span>
              {' → '}
              <span style={{ opacity: platformCheckoutStep === 3 ? 1 : 0.65 }}>3. Pay with Stripe</span>
            </div>
          )}
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
            {isPlatformFinalReady ? (
              <>
                <div style={{ display: 'flex', justifyContent: 'space-between', fontSize: '0.86rem', color: '#475569' }}>
                  <span>Subtotal</span>
                  <span>{money(finalPaymentCheckout.subtotal)} {platformCurrency}</span>
                </div>
                <div style={{ display: 'flex', justifyContent: 'space-between', marginTop: 4, fontSize: '0.86rem', color: '#475569' }}>
                  <span>Shipping</span>
                  <span>{money(finalPaymentCheckout.shipping_total)} {platformCurrency}</span>
                </div>
                <div style={{ display: 'flex', justifyContent: 'space-between', marginTop: 4, fontSize: '0.86rem', color: '#475569' }}>
                  <span>Tax</span>
                  <span>{money(finalPaymentCheckout.tax_total)} {platformCurrency}</span>
                </div>
                {Number(finalPaymentCheckout.discount_total || 0) > 0 && (
                  <div style={{ display: 'flex', justifyContent: 'space-between', marginTop: 4, fontSize: '0.86rem', color: '#475569' }}>
                    <span>Discount</span>
                    <span>-{money(finalPaymentCheckout.discount_total)} {platformCurrency}</span>
                  </div>
                )}
                <div style={{ display: 'flex', justifyContent: 'space-between', marginTop: 8, fontWeight: 700 }}>
                  <span>Total</span>
                  <span>{money(finalPaymentCheckout.grand_total)} {platformCurrency}</span>
                </div>
              </>
            ) : isPlatformDeliverySelecting ? (
              <>
                <div style={{ display: 'flex', justifyContent: 'space-between', fontSize: '0.86rem', color: '#475569' }}>
                  <span>Subtotal</span>
                  <span>{money(checkoutDraft.subtotal)} {platformCurrency}</span>
                </div>
                <div style={{ display: 'flex', justifyContent: 'space-between', marginTop: 4, fontSize: '0.86rem', color: '#475569' }}>
                  <span>Current shipping</span>
                  <span>{money(checkoutDraft.shipping_total)} {platformCurrency}</span>
                </div>
                <div style={{ display: 'flex', justifyContent: 'space-between', marginTop: 4, fontSize: '0.86rem', color: '#475569' }}>
                  <span>Current tax</span>
                  <span>{money(checkoutDraft.tax_total)} {platformCurrency}</span>
                </div>
                {Number(checkoutDraft.discount_total || 0) > 0 && (
                  <div style={{ display: 'flex', justifyContent: 'space-between', marginTop: 4, fontSize: '0.86rem', color: '#475569' }}>
                    <span>Current discount</span>
                    <span>-{money(checkoutDraft.discount_total)} {platformCurrency}</span>
                  </div>
                )}
                <div style={{ display: 'flex', justifyContent: 'space-between', marginTop: 8, fontWeight: 600, color: '#334155' }}>
                  <span>Current checkout total</span>
                  <span>{money(checkoutDraft.grand_total)} {platformCurrency}</span>
                </div>
                {selectedDeliveryOption && (
                  <div style={{ display: 'flex', justifyContent: 'space-between', marginTop: 8, fontSize: '0.86rem', color: '#64748b' }}>
                    <span>Selected delivery estimate ({selectedDeliveryOption.name})</span>
                    <span>
                      {money(selectedDeliveryOption.amount)}{' '}
                      {selectedDeliveryOption.currency_code || platformCurrency}
                    </span>
                  </div>
                )}
                <p style={{ margin: '0.5rem 0 0', fontSize: '0.76rem', color: '#64748b', lineHeight: 1.45 }}>
                  Final shipping, tax, and payable total will be confirmed by the server after this delivery option is applied.
                </p>
              </>
            ) : (
              <>
                <div style={{ display: 'flex', justifyContent: 'space-between', fontSize: '0.86rem', color: '#475569' }}>
                  <span>Estimated subtotal</span>
                  <span>{money(cartTotal)} {catalog?.store?.currency || 'USD'}</span>
                </div>
                <p style={{ margin: '0.5rem 0 0', fontSize: '0.76rem', color: '#64748b', lineHeight: 1.45 }}>
                  Tax and final total are calculated by the platform checkout server.
                </p>
              </>
            )}
          </div>

          <div style={{ marginTop: '1rem', display: 'grid', gap: '0.5rem' }}>
            <h3 style={{ margin: 0, fontSize: '0.95rem', color: '#0f172a' }}>Developer payload simulator</h3>
            <p style={{ margin: 0, color: '#64748b', fontSize: '0.78rem', lineHeight: 1.55 }}>
              This screen is only for local testing. Shoppers pay through platform checkout. Website payment sync is no longer available.
            </p>
            <p style={{ margin: 0, color: '#64748b', fontSize: '0.78rem', lineHeight: 1.5 }}>
              This simulator sends platform checkout payloads only.
            </p>
            <p style={{ margin: 0, color: '#334155', fontSize: '0.85rem', fontWeight: 600 }}>
              Platform checkout
            </p>
            <p style={{ margin: '-0.15rem 0 0', color: '#64748b', fontSize: '0.78rem', lineHeight: 1.5 }}>
              Simulates a storefront using the platform checkout flow. Payment is confirmed before the order is created.
            </p>

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

            {checkoutMode === 'platform' && (
              <div style={{ borderTop: '1px solid #e2e8f0', paddingTop: '0.75rem' }}>
                <h4 style={{ margin: '0 0 0.35rem', fontSize: '0.85rem', color: '#334155' }}>B. Coupon code (optional)</h4>
                <p style={{ margin: '0 0 0.65rem', color: '#64748b', fontSize: '0.76rem', lineHeight: 1.5 }}>
                  Enter a coupon created in Dashboard → Settings → Discounts. The platform validates and calculates the discount.
                </p>
                <label style={{ fontSize: '0.8rem' }}>
                  Coupon code
                  <input
                    value={platformCouponCode}
                    onChange={(e) => setPlatformCouponCode(e.target.value.toUpperCase())}
                    placeholder="WELCOME10"
                    maxLength={100}
                    style={{ display: 'block', width: '100%', marginTop: 4, padding: '0.35rem 0.5rem', borderRadius: 6, border: '1px solid #cbd5e1' }}
                  />
                </label>
                {platformCheckoutDraft?.id ? (
                  <div style={{ display: 'flex', gap: '0.5rem', marginTop: '0.65rem', flexWrap: 'wrap' }}>
                    <button
                      type="button"
                      disabled={loading || !platformCouponCode.trim()}
                      onClick={async () => {
                        setError('');
                        setLoading(true);
                        try {
                          const res = await fetch(`${checkoutBase}/${platformCheckoutDraft.id}/coupon`, {
                            method: 'POST',
                            headers: {
                              Accept: 'application/json',
                              'Content-Type': 'application/json',
                              ...authHeaders(),
                            },
                            body: JSON.stringify({ coupon_code: platformCouponCode.trim() }),
                          });
                          const raw = await res.text();
                          const data = raw ? JSON.parse(raw) : {};
                          if (!res.ok) {
                            throw new Error(data.message || data.errors?.coupon_code?.[0] || `Coupon apply failed (${res.status})`);
                          }
                          setPlatformCheckoutDraft(data.checkout);
                          setPlatformPayment(data.payment || null);
                        } catch (err) {
                          setError(err.message || String(err));
                        } finally {
                          setLoading(false);
                        }
                      }}
                      style={{ padding: '0.4rem 0.75rem', borderRadius: 6, border: '1px solid #4f46e5', background: '#4f46e5', color: '#fff', fontSize: '0.8rem' }}
                    >
                      Apply to checkout
                    </button>
                    <button
                      type="button"
                      disabled={loading || !platformCheckoutDraft?.coupon}
                      onClick={async () => {
                        setError('');
                        setLoading(true);
                        try {
                          const res = await fetch(`${checkoutBase}/${platformCheckoutDraft.id}/coupon`, {
                            method: 'DELETE',
                            headers: {
                              Accept: 'application/json',
                              ...authHeaders(),
                            },
                          });
                          const raw = await res.text();
                          const data = raw ? JSON.parse(raw) : {};
                          if (!res.ok) {
                            throw new Error(data.message || `Coupon remove failed (${res.status})`);
                          }
                          setPlatformCouponCode('');
                          setPlatformCheckoutDraft(data.checkout);
                          setPlatformPayment(data.payment || null);
                        } catch (err) {
                          setError(err.message || String(err));
                        } finally {
                          setLoading(false);
                        }
                      }}
                      style={{ padding: '0.4rem 0.75rem', borderRadius: 6, border: '1px solid #cbd5e1', background: '#fff', color: '#334155', fontSize: '0.8rem' }}
                    >
                      Remove coupon
                    </button>
                  </div>
                ) : (
                  <p style={{ margin: '0.5rem 0 0', color: '#64748b', fontSize: '0.76rem' }}>
                    Or include the code when creating checkout. After a draft exists, use Apply / Remove above.
                  </p>
                )}
              </div>
            )}

            {checkoutMode === 'platform' && platformCheckoutDraft && deliveryOptionsWarning && (
              <div
                style={{
                  marginTop: '0.5rem',
                  border: '1px solid #fecaca',
                  borderRadius: 10,
                  padding: '0.75rem',
                  background: '#fef2f2',
                  color: '#991b1b',
                  fontSize: '0.82rem',
                  lineHeight: 1.55,
                }}
              >
                <strong style={{ display: 'block', marginBottom: 4 }}>No delivery options</strong>
                {deliveryOptionsWarning}
              </div>
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
                        {option.fulfillment_origin?.name && (
                          <span style={{ display: 'block', marginTop: 4, color: '#475569', fontSize: '0.74rem' }}>
                            Fulfillment origin: {option.fulfillment_origin.name}
                          </span>
                        )}
                        {option.pickup_required && option.pickup_locations?.length > 0 && String(option.id) === String(selectedDeliveryOptionId) && (
                          <span style={{ display: 'block', marginTop: 8 }}>
                            <span style={{ display: 'block', marginBottom: 4, color: '#475569', fontSize: '0.74rem' }}>Pickup location</span>
                            <select
                              value={selectedPickupLocationId}
                              onChange={(event) => setSelectedPickupLocationId(event.target.value)}
                              style={{ width: '100%', padding: '0.35rem 0.45rem', borderRadius: 6, border: '1px solid #cbd5e1' }}
                            >
                              <option value="">Choose pickup location</option>
                              {option.pickup_locations.map((location) => (
                                <option key={location.id} value={location.id}>
                                  {[location.name, location.city, location.state, location.postal_code].filter(Boolean).join(' - ')}
                                </option>
                              ))}
                            </select>
                          </span>
                        )}
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
              {platformPayment
                ? 'Step 3: use Pay button below'
                : platformCheckoutDraft && deliveryOptions.length
                  ? 'Step 2: Continue to payment'
                  : platformCheckoutDraft && deliveryOptionsWarning
                    ? 'Retry delivery options'
                    : 'Step 1: Continue to delivery options'}
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
                <p style={{ margin: '0.35rem 0 0', fontSize: '0.78rem', color: '#64748b' }}>
                  Payment mode:{' '}
                  <strong>{platformPayment.payment?.payment_mode === 'live' ? 'Live' : 'Test'}</strong>
                  {platformPayment.payment?.payment_mode !== 'live' && ' — sandbox only, no real charges.'}
                </p>
                <p style={{ margin: '0.35rem 0 0', fontSize: '0.78rem', color: '#64748b' }}>
                  Amount due:{' '}
                  <strong>
                    {finalPaymentCheckout?.currency_code || platformCurrency} {money(finalPaymentCheckout?.grand_total)}
                  </strong>
                </p>
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
                  {stripePaymentProcessing ? 'Confirming payment...' : `Pay ${finalPaymentCheckout?.currency_code || platformCurrency} ${money(finalPaymentCheckout?.grand_total)}`}
                </button>
              </div>
            )}
          </div>
        </aside>
      </div>
    </div>
  );
}
