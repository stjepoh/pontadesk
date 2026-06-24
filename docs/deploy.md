# PontaDesk Deploy

## GitHub to cPanel flow

1. Push the project to GitHub.
2. Create a cPanel Git Version Control repository or pull the GitHub repo into your hosting account.
3. Point the web root to `public/` if the host supports it.
4. If the host requires `public_html`, copy the `public/` contents there and keep the rest of the app outside the web root.
5. Copy `.env.example` to `.env` on the server and fill in:
   - `APP_URL`
   - `DB_HOST`
   - `DB_DATABASE`
   - `DB_USERNAME`
   - `DB_PASSWORD`
6. Import `database/schema.sql` into MySQL.
7. Import production data with:
   - `php scripts/import-export.php --export="C:\putanja\do\exporta.json" --map="data\import-map.json" --reset`
8. Fill `data/import-map.json` with old Base44 `client_id` values mapped to client names from the export. This is required for ugovori i radovi where the export does not contain the original client IDs in the `Client` records.
9. If you want local JSON preview data, copy the export file and set `PONTADESK_EXPORT_JSON`.

## Notes

- Do not commit `.env` or secrets to GitHub.
- Keep `data/import-map.json` in the repo if you want to preserve the Base44 ID mapping.
- The app can run without the JSON export once the database is live.
