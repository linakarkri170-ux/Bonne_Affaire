# BONNE AFFAIRE

BONNE AFFAIRE is a French-language classifieds site. Its pages now use PHP API functions and MySQL instead of browser-only `localStorage` for accounts, listings, favorites, messages, and administration.

## Database setup

1. Open the Aiven SQL editor for `defaultdb`. On a fresh database, run `database.sql` once; it creates the website tables and initial categories and cities. If you already ran the earlier BONNE AFFAIRE schema shared in this chat, run `database-migration.sql` instead to add persistent sessions and seed any missing categories/cities. Do not rerun the full create script over existing tables.
2. Download the CA certificate from the Aiven service page and save it as `certs/ca.pem` in this project. The certificate is public; keep database passwords private.
3. Copy `.env.example` to `.env` for local work. Fill in `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, and `DB_PASSWORD` from Aiven. Add the same values as environment variables in Vercel.
4. Install the PHP dependency with `composer install`. PHP must have PDO MySQL enabled.
5. Deploy the repository to Vercel. `vercel.json` maps `api/*.php` to the PHP runtime. Use the deployed site's URL; opening HTML files directly with `file://` cannot run the API.

## First administrator

Create an account through `inscription.html`, then promote that account in Aiven's SQL editor by replacing the email below:

```sql
UPDATE users SET role = 'admin' WHERE email = 'your-email@example.com';
```

The login page does not allow users to choose their role. Administrator access is checked by the server.

## Main pages

- `index.html`, `recherche.html`, `detail.html`: browse approved listings
- `inscription.html`, `connexion.html`: create an account and sign in
- `publier.html`: submit a listing for admin approval
- `favoris.html`: save and view favorite listings
- `messagerie.html`: message a listing owner
- `admin.html`: moderate listings and manage categories, cities, and members

New listing photos are currently entered as image URLs. The database stores the URL in `listings.image_url`.
