# PontaDesk

Osnovni PHP kostur aplikacije pripremljen za cPanel hosting.

## Start

- Document root usmjeri na `/public`
- Provjeri PHP verziju
- Otvori `/` i `/health`

## Google prijava

Za prijavu preko Google/Gmail računa u `.env` dodaj:

```env
GOOGLE_CLIENT_ID=...
GOOGLE_CLIENT_SECRET=...
GOOGLE_REDIRECT_URI=https://tvoja-domena.hr/login/google/callback
GOOGLE_ALLOWED_EMAILS=ime.prezime@gmail.com
```

U Google Cloud Console treba dodati isti Authorized redirect URI:

```text
https://tvoja-domena.hr/login/google/callback
```

Pristup se dopušta ako je Google email već korisnik u tablici `users` ili je naveden u `GOOGLE_ALLOWED_EMAILS`.
