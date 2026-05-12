import { useCallback, useMemo, useState } from 'react';

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

  const base = useMemo(() => apiBase(), []);
  const externalBase = useMemo(() => externalApiBase(base), [base]);
  const cartTotal = cart.reduce((sum, line) => sum + Number(line.unit_price || 0) * Number(line.quantity || 1), 0);

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
    setCart((prev) => prev.map((line) => (`${line.product_id}-${line.variant_id}` === key ? { ...line, quantity } : line)));
  };

  const removeLine = (key) => {
    setCart((prev) => prev.filter((line) => `${line.product_id}-${line.variant_id}` !== key));
  };

  const externalPayload = () => {
    const stamp = Date.now();

    return {
      external_order_number: `WEB-${stamp}`,
      external_checkout_reference: `checkout-${stamp}`,
      payment_status: 'paid',
      payment_gateway: 'external_test',
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
  };

  const legacyPayload = () => ({
    customer_name: customerName.trim(),
    customer_email: customerEmail.trim(),
    customer_phone: customerPhone.trim() || null,
    shipping_address: {
      address_line1: addressLine1.trim(),
      city: city.trim(),
      state: stateRegion.trim(),
      postal_code: postalCode.trim(),
      country: country.trim(),
      phone: customerPhone.trim() || null,
    },
    items: cart.map(({ product_id, variant_id, quantity }) => ({
      product_id,
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
      const endpoint = external ? `${externalBase}/orders` : `${base}/orders`;
      const res = await fetch(endpoint, {
        method: 'POST',
        headers: {
          Accept: 'application/json',
          'Content-Type': 'application/json',
          ...authHeaders(),
        },
        body: JSON.stringify(external ? externalPayload() : legacyPayload()),
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
      setOrderResult({ ...data.order, externalMode: external });
      setCart([]);
      await loadCatalog({ quiet: true });
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

  const tokenConfigured = Boolean((import.meta.env.VITE_STOREFRONT_TOKEN || '').trim());

  return (
    <div style={{ maxWidth: 960, margin: '0 auto', padding: '1.5rem' }}>
      <header style={{ marginBottom: '1.5rem' }}>
        <h1 style={{ margin: '0 0 0.35rem', fontSize: '1.5rem' }}>Developer test storefront</h1>
        <p style={{ margin: 0, color: '#64748b', fontSize: '0.9rem' }}>
          Fetches catalog from the SaaS API and can sync an already-paid external checkout order back into the merchant dashboard.
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
          <strong>{orderResult.externalMode ? 'External paid order synced to SaaS dashboard.' : 'Legacy dev order placed.'}</strong>
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

          <div style={{ borderTop: '1px solid #f1f5f9', paddingTop: '0.75rem', marginTop: '0.5rem', fontWeight: 700 }}>
            Total {money(cartTotal)} {catalog?.store?.currency || 'USD'}
          </div>

          <div style={{ marginTop: '1rem', display: 'grid', gap: '0.5rem' }}>
            <h3 style={{ margin: '0.5rem 0 0', fontSize: '0.9rem', color: '#334155' }}>Order mode</h3>
            <label style={{ display: 'flex', gap: 8, alignItems: 'center', fontSize: '0.85rem' }}>
              <input type="radio" checked={checkoutMode === 'external'} onChange={() => setCheckoutMode('external')} />
              External paid order sync
            </label>
            <label style={{ display: 'flex', gap: 8, alignItems: 'center', fontSize: '0.85rem' }}>
              <input type="radio" checked={checkoutMode === 'legacy'} onChange={() => setCheckoutMode('legacy')} />
              Legacy direct dev order
            </label>
            <label style={{ display: 'flex', gap: 8, alignItems: 'center', fontSize: '0.85rem', color: '#94a3b8' }}>
              <input type="radio" disabled />
              Platform checkout disabled for this phase
            </label>

            <h3 style={{ margin: '0.5rem 0 0', fontSize: '0.9rem', color: '#334155' }}>Customer details</h3>
            <label style={{ fontSize: '0.8rem' }}>
              Customer name
              <input
                value={customerName}
                onChange={(e) => setCustomerName(e.target.value)}
                style={{ display: 'block', width: '100%', marginTop: 4, padding: '0.35rem 0.5rem', borderRadius: 6, border: '1px solid #cbd5e1' }}
              />
            </label>
            <div style={{ display: 'flex', gap: '0.5rem' }}>
              <label style={{ fontSize: '0.8rem', flex: 1 }}>
                Email
                <input
                  type="email"
                  value={customerEmail}
                  onChange={(e) => setCustomerEmail(e.target.value)}
                  style={{ display: 'block', width: '100%', marginTop: 4, padding: '0.35rem 0.5rem', borderRadius: 6, border: '1px solid #cbd5e1' }}
                />
              </label>
              <label style={{ fontSize: '0.8rem', flex: 1 }}>
                Phone
                <input
                  type="tel"
                  value={customerPhone}
                  onChange={(e) => setCustomerPhone(e.target.value)}
                  style={{ display: 'block', width: '100%', marginTop: 4, padding: '0.35rem 0.5rem', borderRadius: 6, border: '1px solid #cbd5e1' }}
                />
              </label>
            </div>

            <h3 style={{ margin: '0.5rem 0 0', fontSize: '0.9rem', color: '#334155' }}>Shipping address</h3>
            <label style={{ fontSize: '0.8rem' }}>
              Address line 1
              <input
                value={addressLine1}
                onChange={(e) => setAddressLine1(e.target.value)}
                style={{ display: 'block', width: '100%', marginTop: 4, padding: '0.35rem 0.5rem', borderRadius: 6, border: '1px solid #cbd5e1' }}
              />
            </label>
            <div style={{ display: 'flex', gap: '0.5rem' }}>
              <label style={{ fontSize: '0.8rem', flex: 1 }}>
                City
                <input
                  value={city}
                  onChange={(e) => setCity(e.target.value)}
                  style={{ display: 'block', width: '100%', marginTop: 4, padding: '0.35rem 0.5rem', borderRadius: 6, border: '1px solid #cbd5e1' }}
                />
              </label>
              <label style={{ fontSize: '0.8rem', flex: 1 }}>
                State/region
                <input
                  value={stateRegion}
                  onChange={(e) => setStateRegion(e.target.value)}
                  style={{ display: 'block', width: '100%', marginTop: 4, padding: '0.35rem 0.5rem', borderRadius: 6, border: '1px solid #cbd5e1' }}
                />
              </label>
            </div>
            <div style={{ display: 'flex', gap: '0.5rem' }}>
              <label style={{ fontSize: '0.8rem', flex: 1 }}>
                Postal code
                <input
                  value={postalCode}
                  onChange={(e) => setPostalCode(e.target.value)}
                  style={{ display: 'block', width: '100%', marginTop: 4, padding: '0.35rem 0.5rem', borderRadius: 6, border: '1px solid #cbd5e1' }}
                />
              </label>
              <label style={{ fontSize: '0.8rem', flex: 1 }}>
                Country
                <input
                  value={country}
                  onChange={(e) => setCountry(e.target.value)}
                  style={{ display: 'block', width: '100%', marginTop: 4, padding: '0.35rem 0.5rem', borderRadius: 6, border: '1px solid #cbd5e1' }}
                />
              </label>
            </div>

            <button
              type="button"
              onClick={placeOrder}
              disabled={loading || !cart.length}
              style={{
                marginTop: '0.5rem',
                padding: '0.5rem 1rem',
                borderRadius: 8,
                border: 'none',
                background: cart.length ? '#0f172a' : '#94a3b8',
                color: '#fff',
                fontWeight: 600,
              }}
            >
              {checkoutMode === 'external' ? 'Sync external paid order' : 'Place legacy test order'}
            </button>
          </div>
        </aside>
      </div>
    </div>
  );
}
