import { useCallback, useMemo, useState } from 'react';

const defaultApiBase = '/api/developer-storefront';

function apiBase() {
  const raw = (import.meta.env.VITE_API_BASE || defaultApiBase).replace(/\/$/, '');
  return raw;
}

function authHeaders() {
  const token = (import.meta.env.VITE_STOREFRONT_TOKEN || '').trim();
  if (!token) return {};
  return { Authorization: `Bearer ${token}` };
}

export default function App() {
  const [catalog, setCatalog] = useState(null);
  const [error, setError] = useState('');
  const [loading, setLoading] = useState(false);
  const [cart, setCart] = useState([]);
  const [customerName, setCustomerName] = useState('Dev Customer');
  const [customerEmail, setCustomerEmail] = useState('');
  const [customerPhone, setCustomerPhone] = useState('+1 555-0198');
  const [addressLine1, setAddressLine1] = useState('123 Developer Way');
  const [city, setCity] = useState('San Francisco');
  const [stateRegion, setStateRegion] = useState('CA');
  const [postalCode, setPostalCode] = useState('94105');
  const [country, setCountry] = useState('US');
  const [orderResult, setOrderResult] = useState(null);

  const base = useMemo(() => apiBase(), []);

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
                'Unauthorized: check VITE_STOREFRONT_TOKEN in dev-test-storefront/.env matches the token from Dashboard → Dev storefront (no quotes). Restart npm run dev after changing .env.'
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
        const msg = e instanceof TypeError && String(e.message).toLowerCase().includes('fetch')
          ? 'Could not reach the API. If VITE_API_BASE is unset, Laravel must run at the proxy target (default http://127.0.0.1:8000) and you must use npm run dev so /api is proxied. Or set VITE_API_BASE to your full API base URL (e.g. http://127.0.0.1:8000/api/developer-storefront).'
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
      const idx = prev.findIndex((l) => `${l.product_id}-${l.variant_id}` === key);
      const line = {
        product_id: product.id,
        variant_id: variant.id,
        quantity: 1,
        label: `${product.name} — ${variant.options?.length ? variant.options.map((o) => `${o.type}: ${o.value}`).join(', ') : variant.sku || 'Default'}`,
        unit_price: variant.price,
      };
      if (idx === -1) return [...prev, line];
      const next = [...prev];
      next[idx] = { ...next[idx], quantity: next[idx].quantity + 1 };
      return next;
    });
  };

  const updateQty = (key, qty) => {
    const q = Math.max(1, Number(qty) || 1);
    setCart((prev) =>
      prev
        .map((l) => (`${l.product_id}-${l.variant_id}` === key ? { ...l, quantity: q } : l))
        .filter((l) => l.quantity > 0)
    );
  };

  const removeLine = (key) => {
    setCart((prev) => prev.filter((l) => `${l.product_id}-${l.variant_id}` !== key));
  };

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
    setLoading(true);
    try {
      const res = await fetch(`${base}/orders`, {
        method: 'POST',
        headers: {
          Accept: 'application/json',
          'Content-Type': 'application/json',
          ...authHeaders(),
        },
        body: JSON.stringify({
          customer_name: customerName.trim(),
          customer_email: customerEmail.trim() || null,
          customer_phone: customerPhone.trim() || null,
          shipping_address: {
            address_line1: addressLine1.trim(),
            city: city.trim(),
            state: stateRegion.trim(),
            postal_code: postalCode.trim(),
            country: country.trim(),
            phone: customerPhone.trim() || null
          },
          items: cart.map(({ product_id, variant_id, quantity }) => ({
            product_id,
            variant_id,
            quantity,
          })),
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
        const msg =
          data.message ||
          (data.errors && JSON.stringify(data.errors)) ||
          (raw.startsWith('<') ? `HTTP ${res.status}: server returned HTML.` : res.statusText) ||
          'Order failed';
        throw new Error(msg);
      }
      setOrderResult(data.order);
      setCart([]);
      await loadCatalog({ quiet: true });
    } catch (e) {
      const msg =
        e instanceof TypeError && String(e.message).toLowerCase().includes('fetch')
          ? 'Could not reach the API (same checks as catalog: Laravel running, proxy or VITE_API_BASE).'
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
          Fetches catalog from the dashboard-backed API. Use this to confirm product, variant, pricing, and inventory
          flow before building a production storefront.
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
          Set <code>VITE_STOREFRONT_TOKEN</code> in <code>dev-test-storefront/.env</code> (token from Dashboard → Dev
          storefront). With the default Vite proxy, <code>VITE_API_BASE</code> can stay unset (uses{' '}
          <code>/api/developer-storefront</code>).
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
          {loading ? 'Loading…' : 'Load catalog'}
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
          <strong>Order placed</strong> — reference <code>{orderResult.reference}</code>, total{' '}
          {orderResult.total} {orderResult.currency}. Check variant stock in the dashboard product list.
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
              {catalog.products?.map((p) => (
                <li
                  key={p.id}
                  style={{
                    border: '1px solid #f1f5f9',
                    borderRadius: 10,
                    padding: '0.75rem',
                    display: 'grid',
                    gridTemplateColumns: p.primary_image_url ? '72px 1fr' : '1fr',
                    gap: '0.75rem',
                  }}
                >
                  {p.primary_image_url && (
                    <img
                      src={p.primary_image_url}
                      alt=""
                      style={{ width: 72, height: 72, objectFit: 'cover', borderRadius: 8 }}
                    />
                  )}
                  <div>
                    <div style={{ fontWeight: 600 }}>{p.name}</div>
                    <div style={{ fontSize: '0.8rem', color: '#64748b' }}>{p.product_type}</div>
                    <div style={{ marginTop: '0.5rem', display: 'flex', flexDirection: 'column', gap: '0.35rem' }}>
                      {p.variants?.map((v) => (
                        <div
                          key={v.id}
                          style={{
                            display: 'flex',
                            alignItems: 'center',
                            justifyContent: 'space-between',
                            gap: '0.5rem',
                            flexWrap: 'wrap',
                          }}
                        >
                          <span style={{ fontSize: '0.85rem' }}>
                            {v.options?.length
                              ? v.options.map((o) => `${o.type}: ${o.value}`).join(' · ')
                              : 'Default'}{' '}
                            — <strong>{v.price}</strong> {catalog.store?.currency} (stock {v.stock})
                          </span>
                          <button
                            type="button"
                            onClick={() => addToCart(p, v)}
                            disabled={v.stock < 1}
                            style={{
                              padding: '0.25rem 0.6rem',
                              borderRadius: 6,
                              border: '1px solid #cbd5e1',
                              background: v.stock < 1 ? '#f1f5f9' : '#fff',
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
            {cart.map((l) => {
              const key = `${l.product_id}-${l.variant_id}`;
              return (
                <li key={key} style={{ marginBottom: '0.65rem', paddingBottom: '0.65rem', borderBottom: '1px solid #f1f5f9' }}>
                  <div>{l.label}</div>
                  <div style={{ marginTop: '0.35rem', display: 'flex', alignItems: 'center', gap: '0.5rem' }}>
                    <label style={{ fontSize: '0.75rem', color: '#64748b' }}>
                      Qty
                      <input
                        type="number"
                        min={1}
                        value={l.quantity}
                        onChange={(e) => updateQty(key, e.target.value)}
                        style={{ width: 56, marginLeft: 4, padding: '0.2rem 0.35rem' }}
                      />
                    </label>
                    <button type="button" onClick={() => removeLine(key)} style={{ fontSize: '0.75rem', color: '#b91c1c' }}>
                      Remove
                    </button>
                  </div>
                </li>
              );
            })}
          </ul>

          <div style={{ marginTop: '1rem', display: 'flex', flexDirection: 'column', gap: '0.5rem' }}>
            <h3 style={{ margin: '0.5rem 0 0', fontSize: '0.9rem', color: '#334155' }}>Customer Details</h3>
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
                Email (optional)
                <input
                  type="email"
                  value={customerEmail}
                  onChange={(e) => setCustomerEmail(e.target.value)}
                  style={{ display: 'block', width: '100%', marginTop: 4, padding: '0.35rem 0.5rem', borderRadius: 6, border: '1px solid #cbd5e1' }}
                />
              </label>
              <label style={{ fontSize: '0.8rem', flex: 1 }}>
                Phone (optional)
                <input
                  type="tel"
                  value={customerPhone}
                  onChange={(e) => setCustomerPhone(e.target.value)}
                  style={{ display: 'block', width: '100%', marginTop: 4, padding: '0.35rem 0.5rem', borderRadius: 6, border: '1px solid #cbd5e1' }}
                />
              </label>
            </div>

            <h3 style={{ margin: '0.5rem 0 0', fontSize: '0.9rem', color: '#334155' }}>Shipping Address</h3>
            <label style={{ fontSize: '0.8rem' }}>
              Address Line 1
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
                State/Region
                <input
                  value={stateRegion}
                  onChange={(e) => setStateRegion(e.target.value)}
                  style={{ display: 'block', width: '100%', marginTop: 4, padding: '0.35rem 0.5rem', borderRadius: 6, border: '1px solid #cbd5e1' }}
                />
              </label>
            </div>
            <div style={{ display: 'flex', gap: '0.5rem' }}>
              <label style={{ fontSize: '0.8rem', flex: 1 }}>
                Postal Code
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
              Place test order
            </button>
          </div>
        </aside>
      </div>
    </div>
  );
}
