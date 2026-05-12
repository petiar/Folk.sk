# folk.sk — Drupal 6 → Drupal 11 migrácia

## Infraštruktúra

- **Lando**: `folksk` (recipe: drupal11), PHP 8.5, Apache 2.4, MySQL 8.0
- **Webroot**: `drupal/web`
- **URL**: https://folksk.lndo.site
- **Drush**: `lando ssh -c "cd /app/drupal/web && php ../vendor/drush/drush/drush.php <príkaz>"`
  - `lando drush` nefunguje (drush nie je v PATH kontajnera)

## Databázy

### Cieľová (D11 — Lando)
- host: `database`, port: `3306`
- db/user/pass: `drupal11` / `drupal11` / `drupal11`
- externý port: `57208`

### Zdrojová (D6 — živý server)
- host: `db.r5.websupport.sk`, port: `3317`
- db/user: `drupal_folk_sk`
- Kľúč v settings.php: `migrate`

## Nainštalované moduly (D11)

```
drush/drush:^13
drupal/migrate_plus
drupal/migrate_tools
```

Povolené Drupal moduly: `migrate`, `migrate_drupal`, `migrate_plus`, `migrate_tools`

## Obsah D6 webu

| Content type | Počet nodov |
|---|---|
| clanok | 1 661 |
| akcia | 985 |
| video | 219 |
| blog | 137 |
| forum | 63 |
| pozvanka | 54 |
| katalog | 48 |
| sprava | 40 |
| poll | 14 |
| televizia | 15 |
| redakcny_stlpcek | 5 |
| simplenews | 4 |
| book | 3 |
| profil | 2 |
| page | 2 |
| pesnicka | 0 |
| profil_blog | 0 |

| Entita | Počet |
|---|---|
| Používatelia | 1 308 |
| Komentáre | 5 377 |
| Súbory | 833 |
| Taxonomické termíny | 1 645 |
| Taxonomické slovníky | 12 |
| URL aliasy | 16 600 |
| Bloky | 228 |
| Menu links | 44 |

## Kľúčové D6 moduly a ich D11 ekvivalenty

| D6 modul | D11 ekvivalent | Poznámka |
|---|---|---|
| content (CCK) | Field API | core, automaticky |
| views | Views | core, automaticky |
| filefield / imagefield | File / Image field | core |
| nodereference / userreference | Entity Reference | core |
| date / date_api | datetime / daterange | core |
| taxonomy | Taxonomy | core |
| upload | File | core |
| pathauto + token | drupal/pathauto + drupal/token | nainštalovať |
| location / location_cck | drupal/geofield alebo adresné pole | rozhodnúť |
| emvideo / emfield | Media + oEmbed | core + media modul |
| fivestar / votingapi | drupal/fivestar | kontribovaný |
| privatemsg | drupal/private_message | kontribovaný |
| scheduler | drupal/scheduler | kontribovaný |
| captcha / image_captcha | drupal/captcha | kontribovaný |
| fckeditor | CKEditor 5 | core |
| gmap / gmap_* | vypustiť alebo nahradiť | rozhodnúť |
| lightbox2 | drupal/photoswipe alebo podobné | rozhodnúť |
| fieldgroup | drupal/field_group | kontribovaný |
| link | Link field | core |

## Odporúčané poradie migrácií

1. Konfigurácia (filter formáty, role, nastavenia)
2. Používatelia (`d6_user_role`, `d6_user`)
3. Súbory (`d6_file`, `d6_user_picture_file`)
4. Taxonómia (`d6_taxonomy_vocabulary`, `d6_taxonomy_term`)
5. Polia (`d6_field`, `d6_field_instance`, ...)
6. Nody (`d6_node:*`)
7. Komentáre (`d6_comment`)
8. URL aliasy (`d6_url_alias`)
9. Bloky, menu, ostatné

## Spustenie migrácií

Skript `migrate.sh` v koreňi projektu rieši správne poradie:

```bash
bash migrate.sh           # všetko naraz
bash migrate.sh users     # len používatelia
bash migrate.sh taxonomy  # len taxonómia
bash migrate.sh files     # len súbory
bash migrate.sh content   # len nody + komentáre
bash migrate.sh aliases   # len URL aliasy
```

**Na čistom serveri treba pred spustením:**
1. `lando start` + `lando ssh -c "cd /app/drupal/web && php ../vendor/drush/drush/drush.php site:install ..."` (alebo import DB dumpu)
2. Skontrolovať `drupal/web/sites/default/settings.php` — musí obsahovať blok `$databases['migrate']['default']` s D6 credentials
3. `bash migrate.sh` spustí zvyšok vrátane povolenia modulov

**Dôležité gotchas:**
- `d6_user_picture_file` musí bežať PRED `d6_user`, inak treba `migrate:import d6_user --update`
- Avatáre sa sťahujú cez HTTP z `http://folk.sk` (SSL certifikát expirovaný) — config override v `folk_migrate` module
- `lando drush` nefunguje, treba `lando ssh -c "cd /app/drupal/web && php ../vendor/drush/drush/drush.php ..."`

## Stav migrácie

- [x] D11 nainštalovaný
- [x] Migračné moduly povolené
- [x] Zdrojová DB nakonfigurovaná v settings.php
- [x] D6 migrácie detekované (`migrate:status` funguje)
- [x] Používatelia zmigrovní (1308 userov, 90 avatárov, 697 profil hodnôt)
- [x] `migrate.sh` skript s correct poradím
- [x] Taxonómia (1645 termínov, 12 slovníkov)
- [x] Súbory — metadata bez kopírovania (833 súborov, rsync pri go-live)
- [x] CCK polia (25 fields, 26 instances; 6 ignorovaných: emvideo, computed, content_taxonomy)
- [x] Nody (3312 nodov všetkých typov)
- [x] Komentáre (5331 do jedného typu 'comment')
- [x] URL aliasy (16600)
- [x] Pathauto + token (nainštalovaný, patterns nakonfigurované podľa D6)
- [ ] Dizajn a zobrazenie polí
- ~~Bloky a menu~~ — zámerne vynechané, nastaví sa nanovo pri dizajne