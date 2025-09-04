# Google Apps Script Expense Tracker Deployer (PHP)

This is a complete system that registers users, gets Google OAuth consent, creates an Apps Script project **in the user's Drive**, uploads your template code, **deploys a Web App**, and emails the user their personal URL.

## 1) Prereqs

- PHP 7.4+ with `curl`, `pdo_mysql`
- MySQL 5.7+ / MariaDB 10.4+
- HTTPS (OAuth requires https)
- Composer

## 2) Google Cloud Console

1. Create a project, enable **Apps Script API**, **Drive API**, **Sheets API**.
2. OAuth consent screen → External → add scopes; publish.
3. Create OAuth credentials (Web application). Authorized redirect: `https://YOUR_DOMAIN/oauth_callback.php`.
4. Put the Client ID/Secret into `.env`.

Docs: Apps Script `updateContent`, versions and deployments. 

## 3) Install

```bash
composer install
cp .env.example .env
# edit .env
mysql -u root -p < sql/schema.sql
```

Serve the `public/` directory (Apache/Nginx).

## 4) How it works (Flow)

1. **/index.php** – collect name/email → start OAuth.
2. **/oauth_callback.php** – exchange code, store tokens.
3. **/deploy.php** – call Apps Script API:
   - `projects.create`
   - `projects.updateContent` (push Code.gs + index.html + manifest)
   - `projects.versions.create`
   - `projects.deployments.create` (Web App: run as *user accessing*, access *anyone*)
4. Save deployment, email the **web app URL** to the user.

## 5) Templates

Place your `Code.gs` and `index.html` in `/templates`. The installer already includes the ones you uploaded.

## 6) Admin & Health

- `/admin.php?key=ADMIN_API_KEY` – quick dashboard.
- `/cron/health_check.php` – JSON status endpoint for uptime monitoring.

## 7) Security Notes

- Store `.env` **outside** web root if possible.
- Restrict `/admin.php` behind your own auth/WAF.
- Consider OAuth verified production publishing before scale.

---

Happy shipping!
