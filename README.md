# InertiaBundle for Symfony

A Symfony 5.4+ bundle that implements the [Inertia.js v1 server-side protocol](https://inertiajs.com/docs/v1/core-concepts/the-protocol), letting you build modern single-page React / Vue / Svelte apps while keeping classic Symfony routing and controllers — no REST API required.

---

## How Inertia v1 Works (Protocol Summary)

| Scenario | What the server returns |
|---|---|
| First browser visit (no `X-Inertia` header) | Full HTML page with a `<div id="app" data-page="...">` mount point |
| Subsequent XHR navigation (`X-Inertia: true`) | JSON page object (`component`, `props`, `url`, `version`) |
| Asset version mismatch (stale client) | `409 Conflict` with `X-Inertia-Location` header → client does full reload |
| Redirect after PUT/PATCH/DELETE | `302 → 303` conversion so browser uses GET for the redirect |
| Partial reload (`X-Inertia-Partial-Data`) | JSON with only the requested prop keys (plus `errors` always) |

---

## Installation

```bash
composer require unoptimised/inertia-bundle
```

Register the bundle in `config/bundles.php`:

```php
return [
    // ...
    Unoptimised\InertiaBundle\UnoptimisedInertiaBundle::class => ['all' => true],
];
```

---

## Configuration

Create `config/packages/inertia.yaml`:

```yaml
inertia:
    # The Twig template used as the root layout
    root_view: 'base.html.twig'

    # Asset version — change on every deploy to force full reloads on clients
    # Can be a static string, a git SHA, or a file hash
    version: null
```

### Dynamic version from file hash

```yaml
# config/packages/inertia.yaml
inertia:
    version: '%env(resolve:ASSET_VERSION)%'
```

Or set it programmatically in a subscriber/listener:

```php
$inertia->version(md5_file(public_path('build/manifest.json')));
```

---

## Root Layout Template

Copy `vendor/your-vendor/inertia-bundle/templates/base.html.twig` to `templates/base.html.twig` and adjust it to your needs:

```twig
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>My App</title>
    <link rel="stylesheet" href="/build/app.css" />
    <script type="module" src="/build/app.js" defer></script>
</head>
<body>
    {# Renders: <div id="app" data-page="{...json...}"></div> #}
    {{ inertia(page) }}
</body>
</html>
```

The `{{ inertia(page) }}` Twig function is provided by the bundle and outputs the root `<div>` with the JSON-encoded page object in `data-page`.

---

## Usage in Controllers

### Option A — Inject `Inertia` directly (recommended with autowiring)

```php
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Unoptimised\InertiaBundle\Service\Inertia;

class EventController extends AbstractController
{
    public function __construct(private readonly Inertia $inertia) {}

    #[Route('/events/{id}', name: 'events.show')]
    public function show(Event $event): Response
    {
        return $this->inertia->render('Events/Show', [
            'event' => [
                'id'          => $event->getId(),
                'title'       => $event->getTitle(),
                'description' => $event->getDescription(),
            ],
        ]);
    }
}
```

---

## Shared Props

Shared props are merged into every Inertia response. Set them in a kernel event subscriber or middleware:

```php
// src/EventSubscriber/InertiaShareSubscriber.php
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Security;
use Unoptimised\InertiaBundle\Service\Inertia;

class InertiaShareSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly Inertia $inertia,
        private readonly Security $security,
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::REQUEST => 'onRequest'];
    }

    public function onRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        // Plain value
        $this->inertia->share('appName', 'My App');

        // Lazy callable — resolved only when Inertia renders a response
        $this->inertia->share('auth', function () {
            $user = $this->security->getUser();
            return $user ? ['name' => $user->getUserIdentifier()] : null;
        });

        // Multiple keys at once
        $this->inertia->share([
            'flash' => fn () => [], // wire up your flash messages here
        ]);
    }
}
```

---

## Lazy Props

Pass a callable as a prop value so it is only evaluated if not excluded by a partial reload:

```php
return $this->inertia->render('Reports/Show', [
    // Always resolved
    'title' => $report->getTitle(),

    // Only resolved when this prop is included in the response
    'data' => fn () => $this->reportService->computeHeavyData($report),
]);
```

---

## Partial Reloads

The Inertia client sends `X-Inertia-Partial-Data` (comma-separated prop names to include) and/or `X-Inertia-Partial-Except` (names to exclude). The bundle handles this transparently — `errors` is always included regardless.

```
X-Inertia-Partial-Component: Events/Index
X-Inertia-Partial-Data: events          ← only return this prop
X-Inertia-Partial-Except: sidebar       ← return everything except this
```

---

## Validation Errors

Symfony's form validation errors should be placed under the `errors` key in props. A common pattern using Symfony's `ValidatorInterface`:

```php
$errors = [];
$violations = $this->validator->validate($dto);
foreach ($violations as $violation) {
    $field = $violation->getPropertyPath();
    $errors[$field] = $violation->getMessage();
}

return $this->inertia->render('User/Edit', [
    'user'   => $dto,
    'errors' => $errors,
]);
```

The Inertia client automatically makes validation errors available to your form components.

---

## Asset Versioning

Set `inertia.version` in config (or call `$inertia->version(...)` at runtime). On every request the bundle compares the client-sent `X-Inertia-Version` header against the server version. If they differ the bundle returns:

```
HTTP/1.1 409 Conflict
X-Inertia-Location: https://example.com/current-url
```

The Inertia JS client then performs a full page reload to pick up new assets.

---

## Redirect Behaviour

| Method | Server 302 | What Inertia sees |
|---|---|---|
| GET | 302 | 302 (unchanged) |
| PUT / PATCH / DELETE | 302 | **303** (converted by listener) |

The 302→303 conversion is handled automatically by `InertiaListener::onKernelResponse()`.

---

## Running Tests

```bash
composer install
./vendor/bin/phpunit
```
