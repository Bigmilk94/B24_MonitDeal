# MonitDeal

Operacyjny dashboard do monitorowania deali sprzedażowych i powiązanych z nimi
aktywności (e-maile, zadania, telefony, spotkania, notatki, komentarze, zmiany
etapu). Pozwala w kilka sekund odpowiedzieć na pytania: które deale żyją,
które stoją w miejscu, co zostało zrobione i co jeszcze czeka.

Wersja demonstracyjna działa na wygenerowanych, deterministycznych danych —
architektura jest jednak od początku zaprojektowana pod podłączenie
prawdziwego CRM (np. Bitrix24) bez przepisywania frontendu czy logiki
biznesowej.

## Stack technologiczny

- **Symfony 6.4 LTS** (`framework-bundle`, `runtime`, `console`, `clock`,
  `dotenv`, `yaml`) — standardowy szkielet aplikacji: `Kernel` z
  `MicroKernelTrait`, routing przez atrybuty `#[Route]`, autowiring/DI
  przez `config/services.yaml`.
- **Własna, lekka warstwa `View`** (`App\Support\View`) zamiast Twiga —
  zwykłe pliki `.php` renderowane przez `require` + buforowanie wyjścia.
  Kontrolery zwracają `Symfony\Component\HttpFoundation\Response`
  zbudowany z HTML-a, który zwróci `View`. Symfony daje framework
  (routing, DI, HTTP, konsolę), a widoki zostają czystym PHP.
- **PHP 8.1+** (celowo — działa też na tańszym hostingu bez najnowszego
  PHP), ścisłe typy, `readonly` value objects, `enum`. Dane demo generuje
  własny, zależny-od-niczego `SeededRandom` (xorshift32), a nie PHP 8.2+
  `Random\Randomizer` — żeby nie podnosić wymagania wersji PHP bez potrzeby.
- **Composer** do zależności i autoloadingu PSR-4 (`App\` → `src/`).
- Czysty CSS (bez frameworków JS/CSS) + kilkanaście linii vanilla JS do
  przełącznika light/dark mode.

## Struktura projektu

```
bin/console              konsola Symfony (cache:clear, debug:router, ...)
config/
  bundles.php            włączone bundle'e (tylko FrameworkBundle — bez Twiga)
  packages/framework.yaml
  routes.yaml             import tras z atrybutów #[Route] w src/Controller
  services.yaml           autowiring/autoconfigure, bind $templatesDir dla
                          View, alias CrmServiceInterface
public/                  punkt wejścia HTTP (front controller) + assets
  index.php              front controller Symfony (symfony/runtime)
  router.php             front controller dla `php -S` (dev server)
  assets/css, assets/js  style i przełącznik motywu

src/
  Kernel.php              App\Kernel (MicroKernelTrait)
  Domain/
    Enum/                DealStage, ActivityType, TaskState, ...
    Model/                Deal, Company, Contact, User, Activity (+ podklasy:
                          EmailActivity, TaskActivity, CallActivity,
                          MeetingActivity, NoteActivity, CommentActivity,
                          StageChangeActivity, OtherActivity), DealMetrics,
                          DealView
  Service/
    Crm/
      CrmServiceInterface.php    ← punkt integracji z prawdziwym CRM
      MockCrmService.php         implementacja demo (dane w pamięci)
      MockCrmServiceFactory.php  łączy MockCrmService z zegarem kontenera
      DemoDataGenerator.php      generator realistycznych danych demo
    DealMetricsCalculator.php    cała logika biznesowa (patrz niżej)
    DealViewFactory.php          składa Deal + relacje + metryki w DealView
    DealQueryService.php         filtrowanie / wyszukiwanie / sortowanie
  Controller/              DashboardController, DealsController,
                           DealController, ActivitiesController,
                           TasksController — kontrolery Symfony z #[Route],
                           renderują przez App\Support\View
  Support/
    View.php               render()/renderPage()/partial() — cała
                           "templatka" bez Twiga
    helpers.php             globalne funkcje e()/money()/qs() używane
                           w szablonach
    DateHelper.php, TimeFormatter.php — framework-agnostic, używane też
                           bezpośrednio przez logikę biznesową

templates/                widoki w czystym PHP (wyłącznie prezentacja)
tests/run-tests.php        lekki, bezzależnościowy zestaw testów
```

### Podział warstw (dlaczego tak)

- **Domain** — surowe, niezmienne rekordy takie, jakie dałby prawdziwy CRM
  (żadnych pól wyliczanych). Nie zależą od Symfony w ogóle.
- **Service/Crm** — jedyne miejsce, które "wie", skąd biorą się dane.
  `CrmServiceInterface` definiuje kontrakt (`getDeals()`, `getDeal($id)`,
  `getDealActivities($id)`, `getDealTasks($id)`, `getDealEmails($id)`,
  `getDealCalls($id)`, `getDealMeetings($id)`, `getDealTimeline($id)`).
  Reszta aplikacji zna tylko ten interfejs — `config/services.yaml` wiąże
  go z konkretną implementacją.
- **Service (bez Crm)** — logika biznesowa: liczenie dni, statystyk,
  wykrywanie flag, filtrowanie. Zwykłe, framework-agnostic klasy PHP —
  Symfony tylko je autowire'uje, nic więcej.
- **Controller/** — cienkie kontrolery Symfony (`AbstractController` +
  `#[Route]`): pobierają dane przez `CrmServiceInterface`/serwisy, oddają
  je do `View::renderPage()` i zwracają wynik jako `Response`. Nie
  zawierają logiki liczenia niczego.
- **Support/View** — malutki, w pełni własny odpowiednik silnika
  szablonów: `render()` włącza plik `.php` przez `require` z buforowaniem
  wyjścia, `renderPage()` owija go w `templates/layout.php`, `partial()`
  to skrót do `templates/partials/*.php`. Zero zależności, zero magii.
- **templates/** — wyłącznie prezentacja (żadnych `if ($count > 7)` — to
  już jest gotowa flaga z `DealMetrics`); korzystają z globalnych funkcji
  `e()` (escapowanie HTML), `money()` (formatowanie kwot) i `qs()`
  (budowanie query stringu z zachowaniem aktualnych filtrów) z
  `Support/helpers.php`.

## Model danych

`Deal` (id, tytuł, companyId, contactId, ownerId, stage, wartość, waluta,
data utworzenia, przewidywana data zamknięcia) + powiązane `Company`,
`Contact`, `User`.

`Activity` (abstrakcyjna baza) i jej podklasy — każda aktywność należy do
jednego deala i wie, czy jest zakończona (`isCompleted()`), kiedy się
wydarzyła (`completedAt()`) i kiedy jest zaplanowana, jeśli jeszcze się nie
wydarzyła (`plannedAt()`):

- `EmailActivity` (kierunek: wysłany/odebrany)
- `TaskActivity` (stan: otwarte/zakończone; `isOverdue()` liczone dynamicznie
  względem "teraz", nigdy nie zapisywane na sztywno)
- `CallActivity`, `MeetingActivity` (stan: wykonane/zaplanowane)
- `CommentActivity`, `NoteActivity`, `StageChangeActivity` (zawsze zakończone)
- `OtherActivity` (generyczna aktywność CRM, może być zaplanowana)

`DealMetrics` — wszystko, co wyliczone: dni od utworzenia, dni na etapie,
ostatnia aktywność (+ opis względny + poziom "uwagi"), statystyki per typ
aktywności, next action, flagi. `DealView` łączy `Deal` + relacje +
`DealMetrics` w jeden obiekt gotowy do wyrenderowania.

## Logika biznesowa (`DealMetricsCalculator`)

- **Dni na etapie** — liczone od ostatniej `StageChangeActivity` prowadzącej
  do bieżącego etapu (a nie od osobnego, sztywnego pola).
- **Ostatnia aktywność** — najnowsza *zakończona* aktywność (e-mail zawsze
  zakończony, zadanie/telefon/spotkanie tylko jeśli faktycznie się odbyły).
- **Następne działanie** — najwcześniejsza *jeszcze niezakończona* aktywność
  z terminem w przyszłości (zadanie otwarte, telefon/spotkanie zaplanowane).
  Zadanie przeterminowane (termin w przeszłości) nigdy nie jest pokazywane
  jako "następne działanie" — ma za to własną flagę.
- **Statystyki** — dla każdej kategorii (e-maile, zadania, telefony,
  spotkania, aktywności CRM) zachodzi `zakończone + otwarte = wszystkie`;
  zweryfikowane testami dla każdego deala w zbiorze demo.
- **Flagi** — wyliczane tylko dla otwartych deali (Wygrany/Przegrany nie
  "wymagają uwagi"): `Brak aktywności od 7 dni`, `Aktywny deal`,
  `Ostatnia aktywność dzisiaj`, `Brak zaplanowanego następnego działania`,
  `Zadanie przeterminowane`, `Duża liczba otwartych zadań` (próg: 3+).

## Filtrowanie, wyszukiwanie, sortowanie

`DealQueryService` przyjmuje surowe parametry z query stringu (`$_GET`) i
zwraca przefiltrowaną, wyszukaną i posortowaną listę `DealView`. Filtry są
w pełni łączalne (etap + osoba + zakres dat + "ma otwarte zadania" itd. na
raz). Szybkie filtry (`Aktywne dzisiaj`, `Brak aktywności 3+/7+ dni`,
`Brak następnego działania`, `Przeterminowane zadania`) to gotowe presety
tego samego mechanizmu. Domyślne sortowanie: ostatnia aktywność (najpierw
najnowsza).

## Dane demonstracyjne

`DemoDataGenerator` tworzy deterministyczny (stały seed), ale w pełni
*liczony* zestaw: 6 opiekunów, 8 firm, 10 kontaktów, 24 deale (≥20 wymagane),
rozłożone na wszystkie 6 etapów. Każdy deal ma zaprojektowany profil
aktywności, tak aby w zbiorze zawsze znalazły się m.in.:

- deale z dużą liczbą aktywności (kilkanaście wpisów),
- deale zupełnie bez aktywności,
- deale z przeterminowanymi zadaniami,
- deale bez zaplanowanego następnego działania,
- deale aktywne dziś, i deale nieaktywne od tygodni.

Dane są generowane na nowo przy każdym żądaniu, ale zawsze te same (ziarno
generatora jest stałe) — dashboard nie "skacze" między odświeżeniami.

## Uruchomienie

Wymagany PHP ≥ 8.1 i Composer.

```bash
composer install
symfony local:server:start   # albo:
php -S localhost:8000 -t public public/router.php
```

Aplikacja będzie dostępna pod `http://localhost:8000`. `public/router.php`
to standardowy skrypt-router Symfony dla wbudowanego serwera PHP (serwuje
pliki z `public/assets/` bezpośrednio, resztę kieruje do `index.php`) —
używany tylko lokalnie; prawdziwy serwer WWW go nie potrzebuje.

Przydatne polecenia `bin/console` (m.in. `cache:clear`, `debug:router`,
`debug:container`) wymagają zmiennych `APP_ENV`/`APP_DEBUG` — domyślnie
biorą się z pliku `.env` (`APP_ENV=dev`). Do wdrożenia produkcyjnego
utwórz `.env.local` z `APP_ENV=prod`, `APP_DEBUG=0` i **nowym**, losowym
`APP_SECRET`, a następnie wykonaj `composer install --no-dev --optimize-autoloader`
i `php bin/console cache:clear --env=prod`.

Wdrożenie na Apache: katalogiem głównym (document root) musi być `public/`;
dołączony `public/.htaccess` (standardowy dla Symfony) przekierowuje
wszystkie żądania do `index.php` (wymaga `mod_rewrite`). Na nginx wystarczy
standardowa konfiguracja Symfony z `try_files $uri /index.php$is_args$args;`
i document rootem w `public/`.

### Testy

```bash
php tests/run-tests.php
```

Lekki, bezzależnościowy runner (nie wymaga PHPUnit) sprawdzający m.in.:
spójność liczb (`zakończone + otwarte = wszystkie` dla każdej kategorii i
każdego deala), poprawność "ostatniej aktywności" i "następnego działania"
(porównanie z brute-force referencyjną implementacją), spójność flag,
chronologię timeline'u, działanie filtrów/wyszukiwania/sortowania oraz
obecność wymaganych scenariuszy w danych demo.

## Aplikacja lokalna Bitrix24

MonitDeal instaluje się jako **aplikacja lokalna** w Twoim Bitrix24 —
prawdziwe deale/aktywności/zadania zamiast danych demo, plus panel
ustawień do wyboru lejków. Architektonicznie:

- `App\Bitrix24\Bitrix24HandshakeSubscriber` — nasłuchuje na
  `kernel.request`. Bitrix24 przy *każdym* otwarciu aplikacji (nie tylko
  przy instalacji) POST-uje świeże `AUTH_ID`/`REFRESH_ID`/`member_id`/
  `DOMAIN` prosto pod zarejestrowany adres aplikacji. Subscriber
  weryfikuje token przez realne wywołanie `profile` w danym portalu (żeby
  odrzucić sfałszowane żądania), zapisuje/aktualizuje rekord portalu i
  uruchamia sesję — po czym request "wygląda" jak zwykłe GET i trafia do
  normalnego routingu.
- `App\Bitrix24\PortalRepository` — jeden plik JSON (`var/storage/portals.json`)
  zamiast bazy danych: tokeny OAuth + konfiguracja per portal.
- `App\Bitrix24\Bitrix24Client` — cienki klient REST (na `symfony/http-client`)
  z automatycznym odświeżaniem tokenu.
- `App\Bitrix24\Bitrix24FunnelService` — czyta lejki (`crm.category.list`)
  i etapy (`crm.status.list`) wraz z semantyką Bitrix24 (w toku/wygrany/
  przegrany).
- `App\Service\Crm\Bitrix24CrmService` — właściwy adapter `CrmServiceInterface`,
  mapuje `crm.deal.list`/`crm.company.get`/`crm.contact.get`/`user.get`/
  `crm.activity.list`/`tasks.task.list` na modele domenowe.
- `App\Service\Crm\PortalAwareCrmService` (to on jest wpięty jako
  `CrmServiceInterface` w `config/services.yaml`) — jeśli bieżący request
  ma aktywną sesję portalu, deleguje do `Bitrix24CrmService`; w
  przeciwnym razie do `MockCrmService` — więc ten sam adres działa
  zarówno jako prawdziwa aplikacja Bitrix24, jak i samodzielne demo.
- `/ustawienia` (`SettingsController`) — panel wyboru lejków + korekty
  semantyki etapów, widoczny tylko w kontekście aktywnego portalu.

### Rejestracja i instalacja

1. W Bitrix24: **Zasoby deweloperskie → Inne → Aplikacja lokalna**.
2. Adres aplikacji (handler URL) = dokładnie adres, pod którym stoi
   dashboard (np. `https://twojadomena.pl/`) — Bitrix24 POST-uje tam przy
   każdym otwarciu, a subscriber nasłuchuje globalnie, więc handler i
   dashboard muszą być tym samym adresem. Uprawnienia: `crm`, `tasks`, `user`.
3. Wpisz `BITRIX24_CLIENT_ID`/`BITRIX24_CLIENT_SECRET` (z rejestracji) w
   `.env.local` na serwerze (nigdy w `.env` commitowanym do repo).
4. Otwórz aplikację z poziomu portalu — pierwsze otwarcie samo
   "instaluje" portal (zapisuje token w `var/storage/portals.json`).
5. Wejdź w **Ustawienia** i wybierz lejki do śledzenia.

### Stan mapowania pól

Pola i kody enumów w `Bitrix24CrmService` są zweryfikowane względem
oficjalnej dokumentacji REST API Bitrix24 (github.com/bitrix24/b24restdocs),
nie zgadywane — m.in.: `crm.activity` `TYPE_ID` (1=spotkanie, 2=telefon,
4=e-mail), `DIRECTION` (1=przychodzący, 2=wychodzący), `OWNER_TYPE_ID=2`
dla deali (filtrowanie przez `BINDINGS`, zgodnie z oficjalnym tutorialem),
semantyka etapu w `crm.status.list` (`SEMANTICS`: `null`=w toku,
`"S"`=wygrany, `"F"`=przegrany), oraz że `tasks.task.list` zwraca pola w
camelCase (`responsibleId`, `closedDate`...), mimo że filtr przyjmuje
UPPER_SNAKE — mapper obsługuje obie konwencje.

Świadome uproszczenia (do rozważenia później, nie błędy):

- Używane jest pojedyncze pole `CONTACT_ID` (Bitrix24 oznacza je jako
  "przestarzałe, zachowane dla kompatybilności" na rzecz wielokrotnego
  `CONTACT_IDS` przez `crm.item.list`) — wystarczające dla typowego
  jednego kontaktu na deal, ale przy wielu kontaktach na deal u pokaże
  tylko główny.
- `crm.company.get`/`crm.contact.get` są podobnie oznaczone jako
  przestarzałe na rzecz uniwersalnego `crm.item.get` — nadal w pełni
  działają, po prostu nie są już rekomendowanym kierunkiem rozwoju API.
- Brak obsługi niestandardowych/dodatkowych integracji telefonii
  (np. VoIP) z innymi kodami `TYPE_ID` niż udokumentowane.

Każdy portal może mieć własne pola niestandardowe lub nietypową
konfigurację lejków, których nie widać w ogólnej dokumentacji — jeśli po
instalacji coś się nie zgadza, daj znać, co dokładnie widzisz źle, a
dopasujemy mapowanie do Twojego portalu.

### Ograniczenia integracji Bitrix24

- Brak obsługi zdarzenia `ONAPPUNINSTALL` — po odinstalowaniu aplikacji w
  Bitrix24 rekord portalu (z nieważnym już tokenem) zostaje w
  `var/storage/portals.json` aż do ręcznego usunięcia.
- Sesja (ciasteczko) wymaga `Secure` + `SameSite=None` (bo appka działa w
  iframe) — więc **wdrożenie musi być pod HTTPS**, inaczej sesja się nie
  utrzyma między kliknięciami w menu.
- Brak własnego cache'owania odpowiedzi REST — każde odświeżenie
  dashboardu odpytuje Bitrix24 na nowo (wystarczające przy rozsądnej
  liczbie deali; przy bardzo dużych portalach warto dodać cache).

## Znane ograniczenia

- Wszystkie deale demo są w PLN — nie ma logiki przeliczania walut w sumie
  "Wartość aktywnych deali" na dashboardzie (jeśli produkcyjny CRM zwróci
  różne waluty, sumowanie na dashboardzie wymaga wcześniej ustalonego kursu
  referencyjnego).
- Filtrowanie i sortowanie działają w pamięci na pełnym zbiorze deali —
  wystarczające dla setek rekordów; dla dużej skali (tysiące deali z
  prawdziwego CRM) sensowne byłoby przeniesienie filtrowania/sortowania/
  paginacji na poziom zapytania do API/bazy danych.
- Brak uwierzytelniania/autoryzacji — to wewnętrzny dashboard operacyjny,
  zakładający, że dostęp do niego jest już ograniczony na innym poziomie
  (np. sieć wewnętrzna, reverse proxy z SSO).
- Lista `Aktywności` na dashboardzie ogranicza się do 150 najnowszych
  wpisów (paginacja nie została zaimplementowana w pierwszej wersji).
- Testy w `tests/run-tests.php` celowo nie korzystają z kontenera Symfony
  (instancjonują `MockCrmService`/`DealMetricsCalculator` bezpośrednio) —
  to zwykłe klasy PHP, więc nie ma takiej potrzeby. Framework testuje się
  sam (routing, DI) — `bin/console debug:router` / `debug:container`
  wystarczą do weryfikacji konfiguracji.
