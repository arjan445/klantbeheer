# Klantbeheer

Webapp voor het eigen bedrijf, gebouwd op dezelfde basis als het LAT-platform (CodeIgniter 4 + Shield):

- **Klanten en locaties**, met een koppeling naar het Moneybird-contact.
- **Diensten**: de catalogus, bijvoorbeeld Microsoft 365 per gebruiker, webhosting per domein, domeinnaam, Elementor Pro of andere plugins. Per dienst leg je de verkoopprijs, inkoopprijs, leverancier en periode vast.
- **Abonnementen**: welke klant welke dienst afneemt, hoeveel, voor welk domein of welke tenant, en wanneer de volgende factuur moet komen. Per abonnement kun je een afwijkende prijs instellen.
- **Domeinen**: per domein zie je of het bij jou geregistreerd is, of het bij jou gehost wordt en wat er nog meer aan hangt, zoals plugins. Je kunt filteren op *Alleen registratie*, *Registratie + hosting*, *Hosting, domein extern* en *Niets actief*. Abonnementen koppel je aan een domein; tik je een nieuw domein in, dan wordt het automatisch aangemaakt. Met **Snel invoeren** zet je veel domeinen in één keer neer: per regel een domein en een klant, met vinkjes voor registratie, hosting en een extra dienst. Je kunt ook een hele lijst domeinen plakken, of de complete tabel uit het domeinoverzicht van je registrar (bijv. Vimexx). De domeinnamen worden er dan uit gehaald, en een datum op dezelfde regel wordt de factuurdatum.
- **Eigen gebruik**: zet bij een klant (bijv. je eigen bedrijf) het vinkje *Eigen gebruik*. Domeinen en abonnementen daarvan worden bijgehouden, maar nooit gefactureerd en niet als omzet geteld. Ze tellen wel mee bij de verdeling van de kostprijs, want ze draaien op hetzelfde platform. Hun kosten worden op het dashboard van de marge afgetrokken.
- **Licenties (DSD Europe)**: haalt je actieve contracten op bij DSD, met per eindklant het product, het aantal, de inkoopprijs en de verlengdatum. Daarnaast zie je wat je zelf factureert. Verschillen worden gemarkeerd, bijvoorbeeld "bij DSD 7 gebruikers, gefactureerd 5", en met één klik zet je het aantal in het abonnement gelijk aan DSD. De DSD-inkoopprijs wordt automatisch de inkoopprijs van het gekoppelde abonnement. Eindklanten worden op naam aan klanten gekoppeld, of je koppelt ze met de hand. De verlengdatum bij DSD is alleen ter info; je eigen factuurdatums staan daar los van.
- **Facturatie**: een overzicht van wat de komende *x* dagen verlengd wordt, gegroepeerd per klant. Met één klik maak je een **conceptfactuur in Moneybird**, met per regel de periode erbij. Daarna schuift het abonnement automatisch een periode op. Je kunt ook handmatig als gefactureerd markeren.
- **Kostprijzen**: per kostenpool (bijv. Hostingplatform) vul je de vaste kosten per leverancier in, per maand of per jaar (Hetzner VPS, Plesk-licentie, Imunify…). De jaarkosten worden verdeeld over de actieve abonnementen van de gekoppelde diensten, naar aantal × gewicht, en worden automatisch de inkoopprijs van die diensten.
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

1. Ga in Moneybird naar **Instellingen › Ontwikkelaars › Persoonlijke API-token**. Geef het token de rechten *Verkoopfacturen*, *Contacten* en *Instellingen*.
2. Zet in `.env` de waarden `moneybird.token` en `moneybird.administrationId`. Het administratie-ID is het getal in de URL: `moneybird.com/<ID>/…`.
3. Open in de app **Beheer › Moneybird**:
   - Je ziet de btw-tarieven. Zet het ID van 21% in `moneybird.taxRateId`.
   - Met **Contacten ophalen & koppelen** worden je Moneybird-contacten aan klanten gekoppeld, op ID en anders op exacte naam. Als je dat aanvinkt, worden ontbrekende klanten ook aangemaakt.

Facturen worden altijd als **concept** aangemaakt. Je controleert en verstuurt ze zelf in Moneybird.

### DSD Europe (optioneel)

Maak in DSD onder **Instellingen › API Keys** een sleutel aan en zet die in `.env` als `dsd.apiKey`. Klik daarna in de app op **Licenties › Ophalen uit DSD**.

### Herinnering per mail (optioneel)

Vul de `email.*`-instellingen in `.env` in en voeg een cronjob toe:

```
0 7 * * 1  cd /pad/naar/klantbeheer && php spark klantbeheer:herinnering
```

Elke maandag krijgen de admins dan een mail met wat er binnenkort gefactureerd moet worden.

## Hoe kostprijzen werken

1. Maak een kostenpool aan, bijvoorbeeld **Hostingplatform**, en vul de kostenposten in: leverancier, omschrijving, bedrag excl. btw, en of het bedrag per maand of per jaar is.
2. Vink de diensten aan die deze kosten dragen. Een dienst met gewicht 2, bijvoorbeeld *Webhosting Pro*, draagt per stuk dubbel zoveel als een dienst met gewicht 1.
3. Kostprijs per stuk = jaarkosten ÷ (aantal actieve abonnementen × gewicht). Dat bedrag wordt automatisch de inkoopprijs van de dienst. Komen er klanten bij, dan daalt de kostprijs per stuk vanzelf.

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
