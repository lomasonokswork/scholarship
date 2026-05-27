# Stipendiju aprēķina sistēma

Tīmekļa aplikācija stipendiju aprēķināšanai Latvijas profesionālajām skolām. Izstrādāta PHP bez framework'a, izmantojot MySQL un PhpSpreadsheet.

---

## Ātrā palaišana (Laragon)

> **Prasības:** PHP 8.0+, MySQL 5.7+, Composer, Laragon (vai XAMPP/WAMP)

### 1. Nokopē projektu

Nokopē projekta mapi uz Laragon `www` mapi, piemēram:

```
C:\laragon\www\draugiem-konkurss\
```

### 2. Uzstādi datu bāzi

1. Sāc Laragon un atver **phpMyAdmin** → `http://localhost/phpmyadmin`
2. Izvēlies **"New"** → ievadi nosaukumu `draugiem_contest` → nospied **"Create"**
3. Atver tikko izveidoto datubāzi → ej uz cilni **"Import"**
4. Nospied **"Choose File"**, izvēlies `schema.sql` no projekta mapes → nospied **"Go"**

> **Alternatīva (komandrinda):**
> ```bash
> mysql -u root draugiem_contest < schema.sql
> ```

### 3. Pārbaudi datubāzes savienojumu

Atver `db.php` un pārliecinies, ka dati atbilst tavai videi:

```php
$host = 'localhost';
$db   = 'draugiem_contest';
$user = 'root';
$pass = '';          // Laragon pēc noklusējuma nav paroles
```

### 4. Uzstādi Composer atkarības

Projekta mapē atver termināli un palaid:

```bash
composer install
```

Tiks izveidota `vendor/` mape ar PhpSpreadsheet bibliotēku.

### 5. Pārbaudi PHP paplašinājumus

Atver `php.ini` (Laragon: **Menu → PHP → php.ini**) un pārliecinies, ka šīs rindas nav komentētas (noņem `;` no sākuma):

```ini
extension=zip
extension=gd
extension=pdo_mysql
```

Pēc izmaiņām restartē Apache serveri Laragon panelī.

### 6. Atver aplikāciju

Pārlūkā atver:

```
http://localhost/draugiem-konkurss
```

---

## Lietošana

### Solis 1 — Augšupielādē mācību priekšmetus

Sagatavo Excel failu (`.xlsx`) ar šādu struktūru:

| A (Nosaukums)   | B (Tips) |
|-----------------|----------|
| Matemātika      | VIMP     |
| Programmēšana   | PROF     |
| Latviešu valoda | VIMP     |

- Pirmā rinda — virsraksts (tiek izlaista automātiski)
- Kolonna B — tikai `VIMP` vai `PROF`

Augšupielādē sadaļā **"Mācību priekšmeti"**. Ja fails ir derīgs, bet neviens priekšmets netika nolasīts, tiek rādīts brīdinājums — pārbaudi kolonnu secību.

### Solis 2 — Augšupielādē vērtējumus

Eksportē vērtējumu Excel failu tieši no **E-klases** un augšupielādē sadaļā **"Izglītojamo vērtējumi"**. Sistēma automātiski nolasa visas lapas, izglītojamos, priekšmetus un vērtējumu tipus.

### Solis 3 — Konfigurē periodu un aprēķini

1. Izvēlies semestri ar pogu vai ievadi **sākuma** un **beigu datumu** manuāli
2. Ievadi **mēneša budžetu** EUR
3. Pielāgo **stipendiju robežas** (noklusējums jau ir iestatīts)
4. Nospied **"Aprēķināt stipendijas"** — sistēma pāries uz Rezultātu cilni automātiski

### Solis 4 — Rezultāti un eksports

- **Rezultāti** — tabula ar visiem izglītojamajiem, vidējo vērtējumu un piešķirto stipendiju, kā arī kopējās summas un budžeta salīdzinājums
- **Eksportēt Excel** — lejupielādē `.xlsx` failu ar rezultātiem
- **Drukāt / PDF** — atver drukāšanas dialogu; sānjosla un pogas tiek paslēptas automātiski

### Solis 5 — Grupu skats

Cilnē **"Grupu skats"** izvēlies grupu. Tiek rādīta tabula ar visiem izglītojamajiem un katru priekšmetu. Ar izvēles lodziņu var izslēgt konkrētu priekšmetu no aprēķina un nospiest **"Pārrēķināt"**.

### Notīrīt visus datus

Poga **"Notīrīt visus datus"** (augšā pa labi Iestatījumu cilnē) dzēš visus datus no datubāzes — priekšmetus, izglītojamos, vērtējumus un aprēķinu rezultātus. Pirms dzēšanas tiek rādīts apstiprinājuma logs.

---

## Aprēķina loģika

### Vērtējuma atlase

Katram priekšmetam tiek ņemts **VIENS** vērtējums šādā prioritātes secībā:

1. **Galīgais vērtējums priekšmetā** (ja iekrīt izvēlētajā periodā)
2. **II semestra vērtējums** (ja iekrīt izvēlētajā periodā)
3. **I semestra vērtējums** (ja iekrīt izvēlētajā periodā)

Vērtējumi ārpus perioda un "Gada vērtējums" tiek ignorēti.

### Nepietiekamu vērtējumu noteikumi

| Situācija | Stipendija |
|-----------|-----------|
| 0 nepietiekamu vērtējumu | Aprēķina pēc vidējā vērtējuma un robežu tabulas |
| 1 nepietiekams vērtējums | **15,00 EUR** (fiksēts) |
| 2+ nepietiekami vērtējumi | **0,00 EUR** (netiek piešķirta) |

**Nepietiekams** = `< 4,0` VIMP priekšmetam, `< 5,0` PROF priekšmetam, vai vērtējums `nv`.

---

## Failu struktūra

```
draugiem-konkurss/
├── index.php       — Galvenā lietotāja saskarne (3 cilnes)
├── import.php      — Failu apstrāde, aprēķins un datu dzēšana
├── export.php      — Excel eksports
├── api.php         — JSON API rezultātu iegūšanai
├── db.php          — PDO datubāzes savienojums
├── schema.sql      — Datubāzes shēma (izpildi pirmo reizi)
├── composer.json   — Atkarību saraksts
├── vendor/         — Composer paketes (pēc `composer install`)
└── README.md       — Šis fails
```

---

## Biežākās problēmas

**"Database connection failed"**
→ Pārbaudi, vai Laragon MySQL serveris darbojas un `db.php` dati ir pareizi.

**"composer: command not found"**
→ Lejupielādē Composer no [getcomposer.org](https://getcomposer.org) un instalē globāli.

**Augšupielāde nedarbojas (lieli faili)**
→ `php.ini` uzstādi `upload_max_filesize = 32M` un `post_max_size = 64M`, restartē Apache.

**Tukša lapa vai kļūda par `zip` paplašinājumu**
→ Pārbaudi `php.ini`, ka `extension=zip` un `extension=gd` ir aktīvas (bez `;` sākumā).

**"Nav derīgu rindas" pēc priekšmetu augšupielādes**
→ Pārbaudi Excel failu: kolonna A — nosaukums, kolonna B — `VIMP` vai `PROF`. Pirmā rinda tiek izlaista kā virsraksts.

**Rezultātu cilne rāda vecos datus pēc notīrīšanas**
→ Nospied "Notīrīt visus datus" un apstipriniet — lapa automātiski atgriežas uz Iestatījumiem un visas cilnes tiek atiestatītas.

---

**Versija:** 1.0 | **Tehnoloģijas:** PHP 8, MySQL, PhpSpreadsheet, Vanilla JS