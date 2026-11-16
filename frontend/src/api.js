async function req(url, opts = {}) {
    const res = await fetch(url, {
        ...opts,
        headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
    });
    if (!res.ok) {
        let msg = `HTTP ${res.status}`;
        try { msg = (await res.json()).message || msg; } catch {}
        throw new Error(msg);
    }
    return res.status === 204 ? null : res.json();
}

export const api = {
    status: () => req('/api/status'),
    analyze: (body, signal) => req('/api/analyze', { method: 'POST', body: JSON.stringify(body), signal }),
    history: () => req('/api/history'),
    show: (id) => req(`/api/history/${id}`),
    remove: (id) => req(`/api/history/${id}`, { method: 'DELETE' }),
};
