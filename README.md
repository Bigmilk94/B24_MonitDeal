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

- **Symfony 8** (`framework-bundle`, `twig-bundle`, `runtime`, `console`,
  `clock`, `dotenv`, `yaml`) — standardowy szkielet aplikacji: `Kernel`
  z `MicroKernelTrait`, routing przez atrybuty `#[Route]`, autowiring/DI
  przez `config/services.yaml`, widoki w Twig.
- **PHP 8.4+**, ścisłe typy, `readonly` value objects, `enum`.
- **Composer** do zależności i autoloadingu PSR-4 (`App\` → `src/`).
- Czysty CSS (bez frameworków JS/CSS) + kilkanaście linii vanilla JS do
  przełącznika light/dark mode.

## Struktura projektu

```
bin/console              konsola Symfony (cache:clear, debug:router, ...)
config/
  bundles.php            włączone bundle'e (FrameworkBundle, TwigBundle)
  packages/              framework.yaml, twig.yaml
  routes.yaml             import tras z atrybutów #[Route] w src/Controller
  services.yaml           autowiring/autoconfigure + alias CrmServiceInterface
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
                           TasksController — kontrolery Symfony z #[Route]
  Twig/AppExtension.php    filtry `money`/`date_pl`/`datetime_pl` i funkcja
                           `merge_query()` używane w szablonach
  Support/                 DateHelper, TimeFormatter (framework-agnostic,
                           używane też bezpośrednio przez logikę biznesową)

templates/                widoki Twig (wyłącznie prezentacja)
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
  `#[Route]`): pobierają dane przez `CrmServiceInterface`/serwisy i
  renderują widok Twig. Nie zawierają logiki liczenia niczego.
- **templates/** — wyłącznie prezentacja (żadnych `if ($count > 7)` — to
  już jest gotowa flaga z `DealMetrics`).

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

Wymagany PHP ≥ 8.4 i Composer.

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

## Podłączenie prawdziwego CRM (np. Bitrix24)

1. Utwórz nową klasę, np. `src/Service/Crm/Bitrix24CrmService.php`,
   implementującą `CrmServiceInterface`.
2. Zmapuj odpowiedzi API Bitrix24 (`crm.deal.list`, `crm.activity.list`,
   `crm.company.get`, `crm.contact.get`, `user.get` itd.) na istniejące
   modele domenowe (`Deal`, `Activity` + podklasy, `Company`, `Contact`,
   `User`).
3. W `config/services.yaml` podmień jeden alias:
   ```yaml
   # było:
   App\Service\Crm\CrmServiceInterface: '@App\Service\Crm\MockCrmService'
   # →
   App\Service\Crm\CrmServiceInterface: '@App\Service\Crm\Bitrix24CrmService'

   App\Service\Crm\Bitrix24CrmService:
       arguments:
           $webhookUrl: '%env(BITRIX24_WEBHOOK_URL)%'
   ```
   (parametr `BITRIX24_WEBHOOK_URL` dodaj w `.env`/`.env.local` — sekrety
   nigdy w `.env` commitowanym do repo).
4. Nic więcej się nie zmienia — kontrolery, `DealMetricsCalculator`,
   `DealQueryService` i wszystkie widoki pracują wyłącznie na interfejsie
   `CrmServiceInterface` i modelach domenowych, nie na konkretnej
   implementacji ani na Symfony.

Warto dodać cache (np. plikowy/APCu) wokół wywołań realnego API w nowym
adapterze — dziś `MockCrmService` generuje dane w pamięci przy każdym
żądaniu, co dla prawdziwego, wolniejszego API nie byłoby pożądane.

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
