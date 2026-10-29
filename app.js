let csrfToken = '';
let currentUser = null;

const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (char) => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
}[char]));
const money = (value) => `${Number(value).toLocaleString('fr-FR')} DH`;

async function api(path, options = {}) {
    const headers = { Accept: 'application/json', ...(options.headers || {}) };
    if (options.body && typeof options.body !== 'string') {
        headers['Content-Type'] = 'application/json';
        options.body = JSON.stringify(options.body);
    }
    if (options.method && options.method !== 'GET') headers['X-CSRF-Token'] = csrfToken;
    const response = await fetch(`api/${path}`, { cache: 'no-store', credentials: 'same-origin', ...options, headers });
    const responseText = await response.text();
    let result;
    try {
        result = responseText ? JSON.parse(responseText) : {};
    } catch {
        throw new Error(`Réponse serveur invalide (${response.status}). Consultez les journaux Vercel.`);
    }
    if (!response.ok || !result.success) throw new Error(result.message || 'Request failed.');
    return result;
}

function toast(message) {
    const element = document.getElementById('toast');
    if (!element) return;
    element.textContent = message;
    element.classList.remove('hidden');
    setTimeout(() => element.classList.add('hidden'), 2600);
}

function listingCard(item, isFavorite = false) {
    const image = item.image
        ? `<img src="${escapeHtml(item.image)}" alt="${escapeHtml(item.title)}" loading="lazy">`
        : '📦';
    return `<article class="card">
        <a href="detail.html?id=${Number(item.id)}"><div class="photo">${image}<span class="tag">${escapeHtml(item.category)}</span></div></a>
        <div class="card-body"><h3><a href="detail.html?id=${Number(item.id)}">${escapeHtml(item.title)}</a></h3>
        <div class="price">${money(item.price)}</div><p class="meta">📍 ${escapeHtml(item.city)} · ${escapeHtml(item.date || '')}</p>
        <div class="card-actions"><a class="btn outline" href="detail.html?id=${Number(item.id)}">Voir détails</a>
        <button class="primary" type="button" onclick="toggleFavorite(${Number(item.id)},${isFavorite})">${isFavorite ? 'Retirer' : '♡ Favori'}</button></div></div></article>`;
}

function renderListings(targetId, listings, emptyText = 'Aucune annonce trouvée.', isFavorite = false) {
    const target = document.getElementById(targetId);
    if (!target) return;
    target.innerHTML = listings.length ? listings.map((item) => listingCard(item, isFavorite)).join('') : `<p class="empty">${escapeHtml(emptyText)}</p>`;
}

function renderAuthNav() {
    const actions = document.querySelector('.nav-actions');
    if (!actions) return;
    if (!currentUser) return;
    actions.innerHTML = `<span class="small">Bonjour ${escapeHtml(currentUser.name)}</span>
        <button class="btn outline" type="button" id="logout-button">Se déconnecter</button>
        <a class="btn primary" href="publier.html">Déposer une annonce</a>`;
    document.getElementById('logout-button').addEventListener('click', async () => {
        try { await api('logout.php', { method: 'POST', body: {} }); location.href = 'index.html'; }
        catch (error) { toast(error.message); }
    });
}

async function loadTaxonomies() {
    const result = await api('categories.php');
    for (const [id, values, allLabel] of [
        ['category', result.categories, 'Choisir une catégorie'],
        ['city', result.cities, 'Choisir une ville'],
        ['categorie', result.categories, 'Toutes'],
        ['ville', result.cities, 'Toutes les villes'],
    ]) {
        const select = document.getElementById(id);
        if (!select) continue;
        const selected = select.value;
        select.innerHTML = `<option value="">${allLabel}</option>` + values.map((item) =>
            `<option value="${escapeHtml(item.name)}">${escapeHtml(item.name)}</option>`).join('');
        select.value = selected;
    }
}

async function loadHome() {
    const target = document.getElementById('home-list');
    if (!target) return;
    try { renderListings('home-list', (await api('listings.php?limit=1')).listings); }
    catch (error) { target.innerHTML = `<p class="empty">${escapeHtml(error.message)}</p>`; }
}

async function loadSearch() {
    const form = document.getElementById('filter-form');
    if (!form) return;
    const data = new FormData(form);
    const params = new URLSearchParams();
    for (const key of ['q', 'categorie', 'ville', 'min', 'max', 'date']) {
        const value = String(data.get(key) || '').trim();
        if (value) params.set(key === 'categorie' ? 'category' : key === 'ville' ? 'city' : key, value);
    }
    params.set('sort', document.getElementById('sort')?.value || 'recent');
    try {
        const listings = (await api(`listings.php?${params}`)).listings;
        document.getElementById('count').textContent = `${listings.length} annonce(s)`;
        renderListings('search-list', listings);
    } catch (error) { document.getElementById('search-list').innerHTML = `<p class="empty">${escapeHtml(error.message)}</p>`; }
}

async function loadDetail() {
    const target = document.getElementById('detail');
    if (!target) return;
    const id = Number(new URLSearchParams(location.search).get('id'));
    if (!id) { target.textContent = 'Annonce introuvable.'; return; }
    try {
        const result = await api(`listings.php?id=${id}`);
        const item = result.listings[0];
        if (!item) { target.textContent = 'Annonce introuvable.'; return; }
        const photo = item.image ? `<img src="${escapeHtml(item.image)}" alt="${escapeHtml(item.title)}">` : '📦';
        const contactAction = Number(item.seller_id) === Number(currentUser?.id)
            ? '<p class="notice">C’est votre annonce.</p>'
            : `<button class="primary" onclick="location.href='messagerie.html?listing_id=${Number(item.id)}'">Envoyer un message</button>`;
        target.innerHTML = `<div class="detail-grid"><div><div class="detail-photo">${photo}</div>
            <div class="panel"><h2>Description</h2><p>${escapeHtml(item.description)}</p></div></div>
            <aside class="panel"><p class="eyebrow">${escapeHtml(item.category)}</p><h2>${escapeHtml(item.title)}</h2>
            <p class="price">${money(item.price)}</p><p>📍 ${escapeHtml(item.city)}</p><hr><h3>Annonceur</h3>
            <p>${escapeHtml(item.seller)}</p>${contactAction}
            <button class="outline" onclick="toggleFavorite(${Number(item.id)})">♡ Favori</button></aside></div>`;
    } catch (error) { target.textContent = error.message; }
}

async function toggleFavorite(id, remove = false) {
    if (!currentUser) { location.href = 'connexion.html'; return; }
    try {
        await api('favorites.php', { method: 'POST', body: { listing_id: id, remove } });
        toast(remove ? 'Favori retiré.' : 'Annonce ajoutée aux favoris.');
        if (document.getElementById('fav-list')) loadFavorites();
    } catch (error) { toast(error.message); }
}

async function loadFavorites() {
    const target = document.getElementById('fav-list');
    if (!target) return;
    if (!currentUser) { target.innerHTML = '<p class="empty">Connectez-vous pour consulter vos favoris. <a href="connexion.html">Se connecter</a></p>'; return; }
    try {
        const listings = (await api('favorites.php')).listings;
        renderListings('fav-list', listings, 'Pas encore de favoris.', true);
        document.getElementById('fav-empty')?.classList.toggle('hidden', listings.length > 0);
    } catch (error) { target.innerHTML = `<p class="empty">${escapeHtml(error.message)}</p>`; }
}

async function loadAdmin() {
    if (!document.getElementById('admin-ads')) return;
    try {
        const result = await api('admin.php');
        document.getElementById('total').textContent = result.counts.total || 0;
        document.getElementById('pending').textContent = result.counts.pending || 0;
        document.getElementById('approved').textContent = result.counts.approved || 0;
        document.getElementById('members-count').textContent = result.members.length;
        document.getElementById('admin-ads').innerHTML = `<table><tr><th>Annonce</th><th>Prix</th><th>Ville</th><th>Statut</th><th>Actions</th></tr>${result.listings.map((item) =>
            `<tr><td>${escapeHtml(item.title)}</td><td>${money(item.price)}</td><td>${escapeHtml(item.city)}</td><td>${escapeHtml(item.status)}</td><td>
            ${item.status !== 'approved' ? `<button class="primary" onclick="adminAction('moderate',${Number(item.id)},'approved')">Valider</button>` : ''}
            ${item.status !== 'rejected' ? `<button class="outline" onclick="adminAction('moderate',${Number(item.id)},'rejected')">Refuser</button>` : ''}
            <button class="outline" onclick="adminAction('delete-listing',${Number(item.id)})">Supprimer</button></td></tr>`).join('')}</table>`;
        renderTaxList('cat-list', result.categories, 'delete-category');
        renderTaxList('city-list', result.cities, 'delete-city');
        document.getElementById('member-list').innerHTML = result.members.length
            ? `<table><tr><th>Nom</th><th>E-mail</th><th>Action</th></tr>${result.members.map((member) => `<tr><td>${escapeHtml(member.name)}</td><td>${escapeHtml(member.email)}</td><td><button class="outline" onclick="adminAction('delete-member',${Number(member.id)})">Supprimer</button></td></tr>`).join('')}</table>`
            : '<p class="muted">Aucun membre inscrit.</p>';
    } catch (error) { document.getElementById('admin-ads').textContent = error.message; }
}

function renderTaxList(targetId, items, action) {
    document.getElementById(targetId).innerHTML = items.map((item) =>
        `<span style="display:inline-block;background:#f4f6f8;padding:7px;margin:4px;border-radius:7px">${escapeHtml(item.name)} <button onclick="adminAction('${action}',${Number(item.id)})">×</button></span>`).join('');
}

async function adminAction(action, id, status = '') {
    if (action.startsWith('delete') && !confirm('Confirmer la suppression ?')) return;
    try { await api('admin.php', { method: 'POST', body: { action, id, status } }); await loadAdmin(); }
    catch (error) { toast(error.message); }
}

async function loadMessages() {
    const log = document.getElementById('chat-log');
    if (!log) return;
    if (!currentUser) { log.innerHTML = '<p>Connectez-vous pour utiliser la messagerie. <a href="connexion.html">Se connecter</a></p>'; return; }
    const params = new URLSearchParams(location.search);
    const listingId = Number(params.get('listing_id'));
    if (!listingId) {
        try {
            const conversations = (await api('messages.php')).conversations;
            log.innerHTML = conversations.length ? conversations.map((item) => `<p><a class="btn outline" href="messagerie.html?listing_id=${Number(item.listing_id)}&with_id=${Number(item.with_id)}">${escapeHtml(item.partner)} — ${escapeHtml(item.title)}</a></p>`).join('') : '<p class="muted">Aucune conversation. Contactez un vendeur depuis une annonce pour commencer.</p>';
        } catch (error) { log.textContent = error.message; }
        return;
    }
    try {
        const withId = Number(params.get('with_id'));
        const result = await api(`messages.php?listing_id=${listingId}${withId ? `&with_id=${withId}` : ''}`);
        document.getElementById('chat-title').textContent = `${result.partner} — ${result.title}`;
        log.innerHTML = result.messages.map((message) => `<div class="bubble ${Number(message.sender_id) === Number(currentUser.id) ? 'mine' : ''}">${escapeHtml(message.message)}<small class="small">${escapeHtml(message.date)}</small></div>`).join('') || '<p class="muted">Démarrez la conversation.</p>';
        document.getElementById('message-form').dataset.listingId = listingId;
        document.getElementById('message-form').dataset.withId = result.with_id;
    } catch (error) { log.textContent = error.message; }
}

document.addEventListener('DOMContentLoaded', async () => {
    try {
        const session = await api('session.php');
        csrfToken = session.csrf;
        currentUser = session.user;
        renderAuthNav();
    } catch (error) { console.error(error); }

    try { await loadTaxonomies(); } catch (error) { console.error(error); }
    loadHome();
    loadDetail();
    loadFavorites();
    loadAdmin();
    loadMessages();

    const filterForm = document.getElementById('filter-form');
    if (filterForm) {
        const params = new URLSearchParams(location.search);
        for (const key of ['q', 'categorie', 'ville']) if (params.has(key) && filterForm.elements[key]) filterForm.elements[key].value = params.get(key);
        filterForm.addEventListener('submit', (event) => { event.preventDefault(); loadSearch(); });
        filterForm.addEventListener('reset', () => setTimeout(loadSearch, 0));
        document.getElementById('sort')?.addEventListener('change', loadSearch);
        loadSearch();
    }

    document.getElementById('register-form')?.addEventListener('submit', async (event) => {
        event.preventDefault();
        const form = event.currentTarget;
        const message = document.getElementById('reg-msg');
        try {
            const result = await api('register.php', { method: 'POST', body: { name: form.elements.name.value.trim(), email: form.elements.email.value.trim(), password: form.elements.password.value } });
            currentUser = result.user; message.textContent = 'Account created. You are now signed in.'; location.href = 'index.html';
        } catch (error) { message.textContent = error.message; }
    });

    document.getElementById('login-form')?.addEventListener('submit', async (event) => {
        event.preventDefault();
        const message = document.getElementById('login-msg');
        try {
            const result = await api('login.php', { method: 'POST', body: { email: document.getElementById('login-email').value.trim(), password: document.getElementById('login-password').value } });
            currentUser = result.user; location.href = currentUser.role === 'admin' ? 'admin.html' : 'index.html';
        } catch (error) { message.textContent = error.message; }
    });

    document.getElementById('publish-form')?.addEventListener('submit', async (event) => {
        event.preventDefault();
        const form = event.currentTarget;
        const message = document.getElementById('pub-msg');
        if (!currentUser) { message.textContent = 'Connectez-vous avant de publier une annonce.'; return; }
        try {
            const result = await api('listings.php', { method: 'POST', body: { title: form.elements.title.value.trim(), category: form.elements.category.value, city: form.elements.city.value, price: form.elements.price.value, image: form.elements.image.value.trim(), description: form.elements.description.value.trim() } });
            message.textContent = result.message; form.reset();
        } catch (error) { message.textContent = error.message; }
    });

    document.getElementById('category-form')?.addEventListener('submit', async (event) => {
        event.preventDefault();
        try { await api('admin.php', { method: 'POST', body: { action: 'add-category', name: document.getElementById('new-category').value.trim() } }); event.currentTarget.reset(); await loadAdmin(); await loadTaxonomies(); }
        catch (error) { toast(error.message); }
    });
    document.getElementById('city-form')?.addEventListener('submit', async (event) => {
        event.preventDefault();
        try { await api('admin.php', { method: 'POST', body: { action: 'add-city', name: document.getElementById('new-city').value.trim() } }); event.currentTarget.reset(); await loadAdmin(); await loadTaxonomies(); }
        catch (error) { toast(error.message); }
    });
    document.getElementById('message-form')?.addEventListener('submit', async (event) => {
        event.preventDefault();
        const form = event.currentTarget;
        const input = document.getElementById('message');
        try { await api('messages.php', { method: 'POST', body: { listing_id: Number(form.dataset.listingId), with_id: Number(form.dataset.withId), message: input.value.trim() } }); input.value = ''; await loadMessages(); }
        catch (error) { toast(error.message); }
    });
});
