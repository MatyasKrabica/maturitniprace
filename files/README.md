# 🏆 Maturitní práce – Fitness Challenge App

Webová aplikace pro sledování fyzické aktivity, plnění výzev a soutěžení s ostatními hráči prostřednictvím žebříčku. Propojená s **Google Fit API** pro automatické načítání dat z telefonu.

> _Toto README.md bylo vytvořeno za pomoci AI._

---

## 📋 Obsah

- [Popis projektu](#-popis-projektu)
- [Funkce](#-funkce)
- [Technologie](#-technologie)
- [Struktura projektu](#-struktura-projektu)
- [Instalace](#-instalace)
- [Konfigurace](#-konfigurace)
- [Google Fit integrace](#-google-fit-integrace)
- [Skóre a levelování](#-skóre-a-levelování)

---

## 📖 Popis projektu

Aplikace umožňuje uživatelům:
- Sledovat svou fyzickou aktivitu (kroky, vzdálenost, kalorie) přes Google Fit
- Plnit výzvy (chůze, běh, turistika) a získávat XP body
- Soupeřit s ostatními na globálním žebříčku
- Levelovat postavu na základě nasbíraného skóre z aktivit

Projekt byl vytvořen jako maturitní práce a zahrnuje kompletní autentizaci uživatelů, admin panel, systém ticketů (podpora) a lokalizaci (čeština / angličtina).

---

## ✨ Funkce

### 👤 Uživatelé
- Registrace a přihlášení s hashovaným heslem
- Profilová stránka (změna hesla, avatar, Google Fit propojení)
- Systém rankování (Uživatel / Tvůrce výzev / Podpora / Administrátor / Majitel)
- Banování s časovým limitem

### 🏅 Výzvy a skóre
- Výzvy s cílovým počtem kroků, XP odměnou a časovým limitem
- Filtrování výzev podle lokace (země, kraj, město, vzdálenost)
- Automatická detekce splnění výzvy při synchronizaci Google Fit
- **Skóre** jako hlavní metrika: běh > turistika > chůze (váhové koeficienty)

### 📊 Žebříček
- Top 100 hráčů podle celkového skóre z aktivity
- Filtry: úroveň, kraj, město, vyhledávání podle jména
- **Sezónní systém**: admin může uzavřít sezónu → zobrazí se podium 🥇🥈🥉 s vítězi

### 🔧 Admin panel
- Správa uživatelů (rank, ban, XP, lokalita)
- Tvorba a editace výzev
- Manuální spuštění Google Fit synchronizace
- Správa sezón žebříčku
- Log akcí

### 🎫 Podpora (Tickety)
- Vytvoření ticketu s kategorií a popisem
- Odpovědi od podpory / admina (rich text editor)
- Uzavírání a mazání ticketů

### 🌐 Lokalizace
- Čeština a angličtina
- Přepínač jazyka v navigaci
- Přes 155 překladových klíčů

---

## 🛠 Technologie

| Vrstva | Technologie |
|--------|-------------|
| Backend | PHP 8+ |
| Databáze | MySQL / MariaDB (MySQLi) |
| Frontend | HTML, Tailwind CSS (CDN), vanilla JS |
| Autentizace | PHP Sessions |
| API | Google Fit REST API (OAuth 2.0) |
| Verzování | Git + GitHub |

---

## 📁 Struktura projektu

```
/
├── index.php                  # Úvodní stránka (landing)
├── dashboard.php              # Dashboard přihlášeného uživatele
├── login.php                  # Přihlášení
├── register.php               # Registrace
├── challenge.php              # Výzvy – seznam, přijetí, průběh
├── leaderboard.php            # Žebříček hráčů
├── ticket.php                 # Systém podpory (tickety)
├── user_profile.php           # Profil uživatele
├── user_management.php        # Správa uživatelů (rank 3+)
├── user_history.php           # Historie výzev konkrétního uživatele
├── admin.php                  # Admin panel
├── git-sync.sh                # Skript pro automatickou synchronizaci na GitHub
│
├── locales/
│   ├── cs.php                 # České překlady
│   └── en.php                 # Anglické překlady
│
└── src/
    ├── ajax/
    │   ├── ajax_countries.php # AJAX – seznam zemí
    │   ├── ajax_regions.php   # AJAX – seznam krajů
    │   └── ajax_cities.php    # AJAX – seznam měst
    ├── classes/
    │   ├── UserManager.php    # Správa uživatelů, autentizace
    │   ├── ChallengeManager.php # Výzvy, XP, žebříček
    │   ├── TicketManager.php  # Podpora – tickety
    │   ├── ActionLogManager.php # Log admin akcí
    │   └── AppSettings.php   # Nastavení aplikace v DB (key/value)
    ├── fit/
    │   ├── fit-auth.php       # Google OAuth – přesměrování na souhlas
    │   ├── fit-callback.php   # Google OAuth – callback, výměna tokenu
    │   ├── sync-tokens.php    # Synchronizace kroků pro všechny uživatele
    │   ├── check_challenge.php # Kontrola splnění aktivní výzvy
    │   └── fit-view.php       # Přehled aktivity uživatele
    ├── php/
    │   ├── Database.php       # Připojení k DB (MySQLi)
    │   ├── config.php         # 🔒 Credentials – GITIGNOROVÁNO
    │   ├── config.example.php # Šablona pro config.php
    │   ├── settings.php       # Konstanty (ranky, levely, skóre prahy)
    │   ├── locales.php        # Pomocné funkce pro překlady
    │   ├── ban_check.php      # Kontrola banu (includováno na stránkách)
    │   ├── logout.php         # Odhlášení
    │   └── upload_avatar.php  # Upload profilového obrázku
    └── templates/
        └── nav.php            # Navigační menu
```

---

## ⚙️ Instalace

### Požadavky
- PHP 8.0+
- MySQL / MariaDB
- Webový server (Apache / Nginx)
- Účet Google Cloud (pro Google Fit API)

### Kroky

1. **Klonuj repozitář:**
   ```bash
   git clone https://github.com/TVOJE_USERNAME/maturita-app.git
   cd maturita-app
   ```

2. **Vytvoř konfigurační soubor:**
   ```bash
   cp src/php/config.example.php src/php/config.php
   ```
   Vyplň hodnoty v `config.php` (DB přihlašovací údaje, Google OAuth).

3. **Importuj databázi:**
   - Vytvoř databázi a importuj SQL dump (kontaktuj autora).

4. **Nastav webový server:**
   - DocumentRoot musí ukazovat na kořen projektu.

---

## 🔧 Konfigurace

Zkopíruj `src/php/config.example.php` jako `src/php/config.php` a vyplň:

```php
define('DB_HOST',     'localhost');
define('DB_USER',     'tvoje_db_uzivatel');
define('DB_PASS',     'tvoje_db_heslo');
define('DB_NAME',     'nazev_databaze');

define('GOOGLE_CLIENT_ID',     'tvoje_google_client_id');
define('GOOGLE_CLIENT_SECRET', 'tvoje_google_client_secret');
```

> ⚠️ Soubor `config.php` je v `.gitignore` a **nikdy** se nenahraje na GitHub.

---

## 🏃 Google Fit integrace

1. Vytvoř projekt v [Google Cloud Console](https://console.cloud.google.com/)
2. Povol **Fitness API**
3. Vytvoř OAuth 2.0 credentials (Web application)
4. Nastav Redirect URI: `https://tvoje-domena.cz/src/fit/fit-callback.php`
5. Vyplň `GOOGLE_CLIENT_ID` a `GOOGLE_CLIENT_SECRET` v `config.php`

### Automatická synchronizace
Přidej do cronu (synchronizace každých 15 minut):
```bash
*/15 * * * * php /var/www/html/src/fit/sync-tokens.php >> /var/log/fit-sync.log 2>&1
```

---

## ⭐ Skóre a levelování

Skóre se počítá z kroků s váhovými koeficienty podle typu aktivity:

| Aktivita | Koeficient | 10 000 kroků = |
|----------|-----------|----------------|
| 🚶 Chůze | 1.0× | 100 skóre |
| 🥾 Turistika | 1.2× | 120 skóre |
| 🏃 Běh | 1.5× | 150 skóre |

Levelování probíhá automaticky po každé synchronizaci s Google Fit. Level se počítá z `total_score`, **ne** z XP (které jsou bonusem za splněné výzvy).

| Level | Potřebné skóre |
|-------|---------------|
| 1 | 0 |
| 2 | 100 |
| 3 | 350 |
| 4 | 800 |
| 5 | 1 800 |
| 6 | 3 500 |
| 7 | 7 000 |
| 8 | 14 000 |
| 9 | 25 000 |
| 10 | 40 000 |
| 11 | 60 000 |

---

## 📄 Licence

Projekt je vytvořen jako školní maturitní práce. Všechna práva vyhrazena.
