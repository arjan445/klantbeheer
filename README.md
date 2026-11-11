# Klantbeheer

Webapp voor het eigen bedrijf, gebouwd op dezelfde basis als het LAT-platform (CodeIgniter 4 + Shield):

- **Klanten en locaties**, met een koppeling naar het Moneybird-contact.
- **Diensten**: de catalogus, bijvoorbeeld Microsoft 365 per gebruiker, webhosting per domein, domeinnaam, Elementor Pro of andere plugins. Per dienst leg je de verkoopprijs, inkoopprijs, leverancier en periode vast.
- **Abonnementen**: welke klant welke dienst afneemt, hoeveel, voor welk domein of welke tenant, en wanneer de volgende factuur moet komen. Per abonnement kun je een afwijkende prijs instellen.
- **Facturatie**: een overzicht van wat de komende *x* dagen verlengd wordt, gegroepeerd per klant. Met één klik maak je een **conceptfactuur in Moneybird**, met per regel de periode erbij. Daarna schuift het abonnement automatisch een periode op. Je kunt ook handmatig als gefactureerd markeren.
- **Kostprijzen**: kostenpools tellen je inkoopfacturen en bonnetjes uit Moneybird op, per categorie (bijv. Plesk-licentie + VPS + Imunify), over de laatste 12 maanden. Dat bedrag wordt verdeeld over de actieve abonnementen van de gekoppelde diensten, naar aantal × gewicht. De uitkomst wordt automatisch de inkoopprijs van die diensten.
- **Dashboard**: omzet en marge per jaar, uitgesplitst per soort dienst, plus de verlengingen van de komende 60 dagen.
- **Kluis**: apparaten (camera's, recorders, switches, AP's, routers), IP-adressen, MAC- en serienummers en **wachtwoorden per klant en locatie**.
  - Wachtwoorden en geheime notities (zoals de Hik-Connect-verificatiecode of de wifi-sleutel) worden **versleuteld** opgeslagen met AES-256 en HMAC.
  - Je ziet een wachtwoord pas na een klik op het oogje. Na 30 seconden wordt het weer verborgen. Elke keer dat iemand een wachtwoord bekijkt of wijzigt, komt dat in het **kluislogboek**.
  - Met **"Opslaan & volgende"** maak je snel een reeks camera's aan. De gegevens van het vorige apparaat worden overgenomen en het IP-adres gaat er één omhoog.
- **Zoeken** op klant, plaats, IP-adres, serienummer, MAC-adres of domeinnaam.
- **Rollen**: *Admin* mag alles. *Administratie* doet klanten, abonnementen en facturatie, maar heeft geen toegang tot de kluis. *Monteur* doet klanten, apparaten en wachtwoorden, maar ziet geen bedragen.

## Installatie

Vereist: PHP 8.2+ (met intl, mbstring, curl en openssl), MySQL/MariaDB en Composer.

```bash
git clone https://github.com/arjan445/klantbeheer.git
cd klantbeheer
composer install --no-dev
cp env .env                 # en vul database, baseURL en Moneybird in
php spark key:generate      # sleutel voor de kluis, zie hieronder!
php spark migrate --all     # Shield-tabellen + Klantbeheer-tabellen
php spark klantbeheer:admin # eerste admin-gebruiker
```

Zet de webroot van de (sub)domein op de map `public/`.

### ⚠️ De versleutelingssleutel

`php spark key:generate` zet `encryption.key` in `.env`. **Bewaar die sleutel ook ergens anders**, bijvoorbeeld in je eigen wachtwoordmanager. Ben je hem kwijt, dan zijn alle opgeslagen wachtwoorden niet meer te lezen, ook niet uit een back-up van de database. Andersom geldt hetzelfde: met alleen een gelekte database kan niemand de wachtwoorden lezen.

### Moneybird

1. Ga in Moneybird naar **Instellingen › Ontwikkelaars › Persoonlijke API-token**. Geef het token de rechten *Verkoopfacturen*, *Contacten*, *Documenten* (voor de inkoop bij Kostprijzen) en *Instellingen*.
2. Zet in `.env` de waarden `moneybird.token` en `moneybird.administrationId`. Het administratie-ID is het getal in de URL: `moneybird.com/<ID>/…`.
3. Open in de app **Beheer › Moneybird**:
   - Je ziet de btw-tarieven. Zet het ID van 21% in `moneybird.taxRateId`.
   - Met **Contacten ophalen & koppelen** worden je Moneybird-contacten aan klanten gekoppeld, op ID en anders op exacte naam. Als je dat aanvinkt, worden ontbrekende klanten ook aangemaakt.

Facturen worden altijd als **concept** aangemaakt. Je controleert en verstuurt ze zelf in Moneybird.

### Herinnering per mail (optioneel)

Vul de `email.*`-instellingen in `.env` in en voeg een cronjob toe:

```
0 7 * * 1  cd /pad/naar/klantbeheer && php spark klantbeheer:herinnering
```

Elke maandag krijgen de admins dan een mail met wat er binnenkort gefactureerd moet worden.

### Kostprijzen elke nacht bijwerken (optioneel)

```
15 3 * * *  cd /pad/naar/klantbeheer && php spark klantbeheer:kosten
```

Je kunt ook op **Kostprijzen** op *Ophalen uit Moneybird* klikken.

## Hoe kostprijzen werken

1. Je maakt een kostenpool aan, bijvoorbeeld **Hostingplatform**, en vinkt de Moneybird-categorieën aan waarop je de inkoop boekt.
2. De app telt de regels op die op die categorieën geboekt zijn (excl. btw), uit inkoopfacturen en bonnetjes van de laatste *N* maanden. Dat wordt omgerekend naar een jaarbedrag, vermenigvuldigd met het toerekenpercentage, plus eventuele handmatige extra kosten.
3. Die jaarkosten worden verdeeld over de actieve abonnementen van de gekoppelde diensten, naar aantal × gewicht. Als Pro gewicht 2 heeft, draagt een Pro-pakket dubbel zoveel als een Basis-pakket.
4. De kostprijs per stuk wordt opgeslagen als inkoopprijs van de dienst. Komen er klanten bij, dan daalt de kostprijs per stuk vanzelf.

## Hoe facturatie werkt

- Een abonnement wordt **vooraf** gefactureerd. De periode loopt van *volgende factuurdatum* tot en met de dag vóór *volgende factuurdatum + interval*.
- Bij een nieuw abonnement zet je *volgende factuurdatum* op de eerste dag die nog niet gefactureerd is. Bestaande klanten die je al tot bijvoorbeeld 1 maart 2027 hebt gefactureerd, krijgen dus 01-03-2027.
- Bij een **opgezegd** abonnement met een einddatum wordt na die datum niet meer gefactureerd.
- Verandert het aantal O365-gebruikers? Pas het **aantal** aan. De volgende factuur gebruikt het nieuwe aantal.

## Lokaal ontwikkelen

```bash
cp env .env
# in .env: CI_ENVIRONMENT = development, database.default.DBDriver = SQLite3,
#          database.default.database = klantbeheer.db, app.forceGlobalSecureRequests = false,
#          cookie.secure = false
php spark key:generate && php spark migrate --all && php spark klantbeheer:admin
php spark serve
```

## Opbouw

| Map | Wat |
|---|---|
| `app/Controllers` | Customers, Sites, Services, Subscriptions, Billing, Devices, Search, Users, Settings, Account |
| `app/Libraries/Billing.php` | Bepalen wat er gefactureerd moet worden, periodes berekenen, factureren |
| `app/Libraries/Moneybird.php` | API-client: contacten, btw-tarieven, conceptfacturen |
| `app/Libraries/Vault.php` | Versleutelen/ontsleutelen en het kluislogboek |
| `app/Database/Migrations` | Alle tabellen |
| `app/Config/AuthGroups.php` | Rollen en rechten |
| `public/assets` | Bootstrap, Bootstrap Icons, jQuery en DataTables, lokaal (geen CDN nodig) |
