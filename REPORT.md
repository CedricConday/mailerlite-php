# Issue report — mailerlite/mailerlite-php

Worked against `main` at `ff657cc`. PHP 8.2.33 in a container for the main loop,
with PHP 7.4 and 8.4 containers used to check the ends of the CI matrix.

There is **no `CONTRIBUTING.md`** in this repository or in `.github/`. Branch
names follow `<type>/issue-<n>/<slug>`, after the existing
`feature/issue-13/params-on-group-subscribers-call`; commit subjects follow the
conventional-commit style already in the history.

`composer.json` declares `"php": "^7.4|^8.0"` and `test.yml` runs 7.4 through
8.4. Everything below is 7.4-compatible.

**Nothing has been pushed.** No fork, no branches published, no PRs opened.
Five branches are ready locally, in the intended PR order.

There are **five** open issues, not four: #38 was missing from the original
brief. One open PR exists on the repo, from Renovate.

---

## Dropped since the previous run: #19 MailerLite PHP SDK Adding HTML Content

Branch deleted, no PR. The previous run classified it (c), an API-side behaviour
the SDK cannot change: the campaigns API states the body must contain an
unsubscribe link, account name, address and country or the default footer is
appended, and no request parameter suppresses it. The proposed deliverable was a
README paragraph restating that rule.

That is the wrong place for it. The rule belongs to the API and the API can
change it, at which point the SDK's README is quietly wrong and nobody notices.
The accompanying test could not fail either — it asserted that content the SDK
never touches arrives untouched. The finding stands and the diagnosis was right;
the PR was not worth opening.

---

## Rename ImportTest.php.php so PHPUnit collects it

Not an open issue. Found while reporting on the suite in the previous run.

- **Symptom:** none reported by anyone. `tests/Endpoints/ImportTest.php.php`
  has a doubled extension.
- **Reproduced: yes.** `phpunit --list-tests | grep -i import` returns nothing
  on `main`. PHPUnit's default suffix is `Test.php`, and the filename ends
  `.php.php`, so the file is never collected and
  `ImportTest::test_orders_import_ok` has never executed.
- **Classification: (a) SDK bug**, in the test suite rather than in `src`.
- **Cause:** the filename. Nothing else.
- **Fix:** `git mv` to `ImportTest.php`. Rename only — verified byte-identical,
  `sha256 da68a74f…` before and after, and git records it as a 100% rename with
  zero insertions and deletions.
- **Test:** the file's own `test_orders_import_ok`, which asserts a 202 status
  code and `imp_1` as the returned `import_id`. It catches a regression in the
  e-commerce Import endpoint, which currently has no executing coverage at all.
- **Breaking: no.**
- **Verified across the CI matrix**, because this PR turns a test *on* in CI and
  passing locally on one version is not enough: collected and green on PHP 7.4
  (63 tests, 129 assertions), 8.2 (63/129) and 8.4 (63/129).
- **PR:** branch `fix/import-test-never-collected`, commit `6a4b7db`. Not pushed.
- **Not done:** **two more files have the same defect** — see the suite section
  below. I renamed only the file named in the brief. Rolling all three into this
  PR is probably the better change, but it is a scope decision, not mine to
  take unilaterally.

---

## #34 Forget a subscriber

- **Reported symptom:** "the official MailerLite API documentation lists a
  'forget' endpoint, which does not exist in this package… the 'forget' endpoint
  is required to be GDPR compliant when removing contacts."
- **Reproduced: yes.**
  ```
  method_exists(Subscriber::class, 'forget') = false
  Error: Call to undefined method MailerLite\Endpoints\Subscriber::forget()
  public methods: create, find, get, update, delete, __construct
  ```
  The API documents `POST https://connect.mailerlite.com/api/subscribers/:id/forget`.
- **Classification: (b) missing SDK feature the API supports.**
- **Cause:** `src/Endpoints/Subscriber.php` implements five verbs and stops. No
  cause beyond the endpoint never having been added.
- **Fix:** `forget(string $subscriberId)` posting to `{endpoint}/{id}/forget`,
  shaped like its neighbours, plus a README section. The docblock is `@return`
  only, matching every other method in `src/Endpoints/` — none carries a prose
  description. The warning that it is irreversible lives in the README, which is
  where a user reads before calling it.
- **Test:** `test_forget` in `tests/Endpoints/SubscriberTest.php`. Fails on main
  with `Error: Call to undefined method`. Asserts `POST` and the exact path
  `/api/subscribers/123/forget`. It catches the method existing but pointing at
  the wrong verb or path — a live risk here, because `delete()` in this same
  class builds its URI through a different call shape than `find()` and
  `update()` do, so there are two patterns to copy from and one of them puts the
  id inside `buildUri()`.
- **Breaking: no.** Purely additive.
- **PR:** branch `feat/issue-34/forget-subscriber`, commit `1fe1cd8`. Not pushed.
- **Still unsure, and asked in the PR body rather than guessed at:**
  `HttpLayer::post()` defaults `$body` to `[]`, which serialises to the string
  `[]`, so this sends `Content-Type: application/json` with a body of `[]`
  rather than no body. Verified on the wire. Every other POST in the SDK does
  the same, so special-casing one method would be the odd thing in review — but
  I cannot confirm the live API accepts it on this endpoint without a key, and
  if it does not, `forget()` fails in a way no local test catches.
  `HttpLayer::request()` sends no body and is the alternative.

---

## #18 Get subscriber list does not include group information

- **Reported symptom:** "When I call the following: `$response = $mailerLite->subscribers->get();`
  it does not return group information for each subscriber. There is no
  **groups** array included in the response." And: "Do we need to include
  something in the call to ask the api to include group info?"
- **Reproduced: partially.** The observation is correct; the diagnosis is not a
  defect, and the reporter's own guess was right. The API documents an `include`
  parameter ("Additional resources to include in the response. Currently, only
  groups is supported") and the SDK already forwards it:
  ```
  get([])                      -> GET .../api/subscribers
  get(['include'=>'groups'])   -> GET .../api/subscribers?include=groups
  ```
  I could not confirm the *response* then contains groups — that needs a live
  account and key. What is verified is that the request the SDK builds is the
  one the API documents.
- **Classification: (d) user error the docs should have prevented.**
- **Cause:** nothing in `src` is wrong. `Subscriber::get()`
  (`src/Endpoints/Subscriber.php:37`) hands its argument to `BuildUri::execute()`,
  which runs `http_build_query()` (`src/Helpers/BuildUri.php:25`), so arbitrary
  query parameters already work. The cause is `README.md`: the only documented
  call is `get()` with no arguments, which is exactly the call that returns no
  groups.
- **Fix:** README only. Document `include` beside the "All records" example and
  note that `get()` forwards any parameter as a query parameter. No code change
  — adding a `groups` flag or a dedicated method would invent an API the SDK
  does not need.
- **Test:** `test_get_forwards_include_as_a_query_parameter` and
  `test_get_forwards_nested_parameters_as_a_query_string`. **Neither fails on
  main**, stated plainly in the PR body too. They assert `include=groups` and
  `filter[status]=active&limit=50` on the built request. `test_get` today calls
  `get([])` and asserts only method and path, so nothing would notice if
  parameter forwarding broke and silently made the README wrong.
- **Breaking: no.**
- **PR:** branch `docs/issue-18/subscribers-include-groups`, commit `46b810e`.
  Not pushed.
- **Not done:** `get(['include' => ['groups']])` produces
  `include%5B0%5D=groups`, which is unlikely to be what the API wants. I added
  no normalisation — I cannot test what the API accepts, and guessing at
  coercion rules inside an SDK is how a second bug gets written. The README
  shows the scalar form.

---

## #16 Undefined index api_key in HttpLayer

- **Reported symptom:** "If you want to construct your own 'HttpLayer' _(i.e. to
  re-use existing HTTP Client etc)_, you need to explicitly pass in the options
  array including the API key. This is undocumented." Expected: "Not providing a
  valid API Key to `HttpLayer::__construct()` should throw an exception".
  Actual: "PHP Notice: undefined index 'api_key'".
- **Reproduced: yes**, and the report both understates and misstates it.
  ```
  1) new HttpLayer([]):            Undefined array key "api_key"; constructed, no exception
  2) new MailerLite([]):           constructed, NO exception
  3) new MailerLite(['api_key'=>null]): THREW MailerLiteException
  4) header on the wire:           Authorization: 'Bearer'
  ```
  The consequence is not only a warning — an empty `Bearer` goes out, so every
  request fails as *unauthorized* rather than *unconfigured*. And the reporter's
  belief that `MailerLite` "throws an exception if it's not present" is false.
- **Classification: (a) SDK bug.** There is a docs component, but the guard
  meant to prevent this exists and does not work, which makes it a defect.
- **Cause:** `src/Common/HttpLayer.php:157` (on main), in `buildPlugins()`:
  `$apiKey = $this->options['api_key'];` reads a key the constructor never
  required, and `__construct()` defaults `$options` to `[]`. The apparent guard
  at `src/MailerLite.php:84` cannot fire for a missing key, because
  `setOptions()` seeds from `$defaultOptions`, which already contains
  `'api_key' => ''` (`src/MailerLite.php:37`). `array_key_exists()` is therefore
  always true and `is_null()` false unless a caller passes an explicit `null`.
- **Fix:** validate in `HttpLayer::__construct()` through `assertApiKey()`,
  throwing the `MailerLiteException` message already used for this. Validating
  where the key is *consumed* covers both construction paths at once; patching
  `MailerLite::setOptions()` would leave the direct `new HttpLayer(...)` path —
  the one the issue is actually about — still broken.
- **Test:** `tests/Common/HttpLayerTest.php`, new file, four tests. Three failed
  before the fix and assert that a missing, empty-string and `null` API key each
  throw `MailerLiteException` with the expected message. The fourth,
  `test_it_sends_the_api_key_as_a_bearer_token`, asserts
  `Authorization: Bearer api-key` on the wire; it covers the happy path with a
  real value rather than a construction that cannot fail. Nothing in the
  existing 62 tests constructs an endpoint without a key or asserts on the
  `Authorization` header, so the empty-Bearer failure was invisible to all of
  them.
- **Breaking: yes, narrowly.** `new MailerLite([])` and `new HttpLayer([])` now
  throw where they used to construct. No documented usage does this: every
  README example passes `['api_key' => 'key']`, including the PSR-transport
  example. The only affected code is code that could already only receive 401s.
  The non-breaking alternative — docblock and README only, leaving the warning
  and the empty Bearer in place — is offered in the PR body, once, with the note
  that the call is the maintainer's.
- **PR:** branch `fix/issue-16/http-layer-requires-api-key`, commit `6699d00`.
  Not pushed.
- **Not done:** the `@param array{api_key: non-empty-string}` shape the reporter
  asked for. Declaring it makes `new HttpLayer($this->options)` in
  `MailerLite::setHttpLayer()` a PHPStan error at level max, since
  `MailerLite::$options` is `array<string, mixed>`. Satisfying it means retyping
  that property and its merge loop — a larger change than this issue, and it
  should be its own.

---

## #38 PHP fatal error sendRequest

- **Reported symptom:** "PHP Fatal error: Declaration of
  `Http\Client\Curl\Client::sendRequest(Psr\Http\Message\RequestInterface $request)`
  must be compatible with
  `Psr\Http\Client\ClientInterface::sendRequest(Psr\Http\Message\RequestInterface $request): Psr\Http\Message\ResponseInterface`
  in `/var/www/html/hydestore/mailerlite/php-http/curl-client/src/Client.php` on line 111"
- **Reproduced: yes, by construction — but not through Composer**, which turns
  out to be the finding.

  Installing the SDK with `php-http/curl-client 2.1.0` on PHP 7.4 works: that
  version declares the PSR-18 return type, implements `ClientInterface`, and the
  SDK issues a real request (a correct 401 on a fake key). The break needs an
  older client, and Composer refuses to build it:

  ```
  - mailerlite/mailerlite-php[v1.0.0..v1.0.5] require php-http/httplug ^2.1
  - php-http/curl-client[v1.7.0, v1.7.1] require php-http/httplug ^1.0
  - You can only install one version of a package
  ```

  Reproducing it therefore required hand-placing a client that implements
  `Http\Client\HttpClient` without the return type, which yields the reporter's
  error verbatim. Their own error path — `.../hydestore/mailerlite/php-http/curl-client/src/`
  rather than `vendor/` — says the libraries were copied in by hand.
- **Classification: (d) user error, a broken installation.** Not an SDK defect
  and not reachable from a Composer install.
- **Cause:** outside this repository. `php-http/httplug ^2.1` defines
  `Http\Client\HttpClient` as extending `Psr\Http\Client\ClientInterface`,
  which declares `sendRequest(): ResponseInterface`. A curl-client written for
  Httplug 1.x declares no return type, and PHP rejects the declaration.
- **Fix: README troubleshooting entry only, and there is no alternative.** I
  checked whether the SDK could at least fail better — it cannot. The error is a
  compile-time fatal raised while PHP loads the third-party class. Verified by
  loading such a class inside a `try/catch`: it was **not** caught and execution
  terminated. `Psr18ClientDiscovery::find()` never gets to run, so no wrapping,
  no clearer exception, no validation is possible from inside the SDK.
- **Test: none, and none is possible.** There is no SDK behaviour to assert —
  the failure happens in a third-party class before SDK code executes. Stated
  here rather than papered over with a test that would prove nothing.
- **Breaking: no.**
- **PR:** branch `docs/issue-38/psr18-client-troubleshooting`, commit `487c86d`.
  Not pushed.
- **Not done, and worth raising separately:** the README's existing
  troubleshooting block tells you to `composer require php-http/curl-client`,
  while `composer.json`'s `suggest` names `guzzlehttp/guzzle`, `nyholm/psr7` and
  `symfony/http-client` — and not curl-client. The project recommends one HTTP
  client in its docs and three different ones in its metadata, and the one in
  the docs is the one in this bug report. Aligning them is a real improvement
  but it changes `composer.json`, which is a wider decision than this issue.

---

## Branch state

| Branch | `git diff main --stat` | Tests | PHPStan | cs-fixer | PR |
|---|---|---:|---|---|---|
| `fix/import-test-never-collected` | 1 file, rename, 0 / 0 | 63 / 129 | `[OK] No errors` | clean | not pushed |
| `feat/issue-34/forget-subscriber` | 3 files, +39 | 63 / 129 | `[OK] No errors` | clean | not pushed |
| `docs/issue-18/subscribers-include-groups` | 2 files, +40 | 64 / 131 | `[OK] No errors` | clean | not pushed |
| `fix/issue-16/http-layer-requires-api-key` | 2 files, +71 / −1 | 66 / 132 | `[OK] No errors` | clean | not pushed |
| `docs/issue-38/psr18-client-troubleshooting` | 1 file, +24 | 62 / 127 | `[OK] No errors` | clean | not pushed |

Baseline on `main`: 62 tests, 127 assertions. All five branches merged together:
**70 tests, 140 assertions**, PHPStan clean, no conflicts.

PHPStan is level `max` over `src`. php-cs-fixer is `@PSR12`; the pinned
`friendsofphp/php-cs-fixer ^2.18` refuses to run above PHP 8.0 while a vendor
tree installed under 8.2 writes a platform check requiring ≥ 8.1, so the two
cannot both be satisfied from one install. It was run under
`PHP_CS_FIXER_IGNORE_ENV=1` in `--dry-run` mode only — no file was written by a
tool running outside its supported range.

Every assertion added across the four branches is on a value: a header, a
method, a path, a query string, or an exception class and message. No assertion
checks only that something exists.

---

## Green, but not asserting what it names

This section is the input to the next piece of work.

- **Three test files are never collected, not one.** All three contain real,
  passing test classes in files PHPUnit's `*Test.php` suffix does not match:

  | File | Class | Methods |
  |---|---|---:|
  | `tests/Endpoints/ImportTest.php.php` | `ImportTest` | 1 |
  | `tests/Http/GuzzleClientAdapter.php` | `GuzzleClientAdapterTest` | 1 |
  | `tests/Http/Psr17FactoryAggregate.php` | `Psr17FactoryAggregateTest` | 1 |

  `phpunit --list-tests` on `main` matches none of them. Renaming all three
  takes the suite from 62 / 127 to **65 / 135**, all passing. So the PSR-18
  client adapter and the PSR-17 factory aggregate — both introduced with the
  e-commerce and PSR-transport work — have test coverage that has never run,
  alongside the Import endpoint. The branch prepared here renames only
  `ImportTest`, per the brief.
- **`tests/MailerLiteTest.php::test_basic()`** asserts `assertTrue(true)`. It is
  named for the client class and tests nothing about it. `MailerLite` has no
  coverage of option merging, of the `api_key` guard, or of
  `enablePsrTransport()` swapping the transport and rebuilding every endpoint.
- **Every endpoint test asserts only method and path.** The shared response mock
  stubs `getStatusCode()` alone, so `HttpLayer::buildResponse()` — the
  content-type switch, the JSON decode, and the
  `status_code`/`headers`/`body`/`response` shape callers actually consume — is
  unasserted outside the PSR tests. Request bodies were never inspected by any
  test before this run.
- **`HttpLayer::buildResponse()` throws on an empty JSON body.** If a response
  carries `Content-Type: application/json` with an empty body — a 204, or a 202
  with no payload — `json_decode('', ..., JSON_THROW_ON_ERROR)` raises
  `JsonException`. I hit this by accident while writing a reproduction script.
  No test covers it and I have not confirmed the API ever returns such a
  response, so I am flagging it rather than claiming a bug. It is directly
  adjacent to the `forget()` question above.
- **`SubscriberTest::$subscriberId` is declared `int` and assigned `'123'`.**
  No `declare(strict_types=1)` in the file, so PHP coerces it and the tests
  pass; the declared type is simply wrong. `CampaignTest` has the same pattern.
- **`BuildUri` has no test of its own**, though every endpoint URL depends on
  it. The two tests added for #18 are the first assertions anywhere on its
  query-string output.
- **The README recommends an HTTP client that `composer.json` does not.** The
  troubleshooting block says `composer require php-http/curl-client`, while
  `suggest` names `guzzlehttp/guzzle`, `nyholm/psr7` and `symfony/http-client`.
  Two sources of truth about the one dependency that causes issue #38.
- **The cs-fixer toolchain cannot run as configured.** `format.yml` pins PHP
  8.0 and the fixer refuses anything newer, but the lockfree `composer install`
  on 8.1+ writes a platform check that blocks 8.0. Any contributor on a modern
  PHP has to override an environment guard to run the project's own style
  check. Upgrading `friendsofphp/php-cs-fixer` past ^2.18 would fix it and is
  independent of everything above.
