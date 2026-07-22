# PHP Router Performance Audit

This repository compares standalone PHP routers under shared, inspectable workloads. It measures route registration, compilation or finalization, cold start, handler-free matching, full dispatch, cache lifecycle, and memory. It intentionally does not calculate an overall winner: the scenarios measure different costs and should be interpreted independently.

**[Read the elaborated benchmark results](BENCHMARK_RESULTS.md)** · **[Open the benchmark website](https://stefanov1989.github.io/PHP-Router-Benchmark/)** · [Inspect the official raw run](results/20260722-101702-full-opcache.md)

> **Conflict-of-interest disclosure**
>
> “The benchmark author is also the author of Ariel Radix Router. The benchmark is structured around shared datasets, adapter contract tests and reproducible commands to reduce bias. Readers are encouraged to inspect the adapters and reproduce the results.”

Official benchmark target: **PHP 8.4.1**, OPcache enabled for CLI, JIT disabled. Deprecation notices are excluded with `E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED`; warnings and fatal errors remain visible. JIT and OPcache-disabled profiles are available, but results from different profiles or execution environments must never be combined in one ranking.

## Tested releases

The lock file pins the latest stable releases selected on 2026-07-22. `router-versions.json` is the reviewable identity manifest; `composer.lock` is the installation authority.

| Router | Composer package | Version | Upstream commit |
|---|---|---:|---|
| Ariel Radix Router | `stefanov1989/ariel-radix-router` | v1.0.2 | `dacbe9ec2769e1702264c1f4e766d088c2261c0f` |
| Illuminate Routing | `illuminate/routing` | v13.21.1 | `ead1511bfebcb8540c751e73b1321ffaa582e668` |
| Bramus Router | `bramus/router` | 1.6.1 | `55657b76da8a0a509250fb55b9dd24e1aa237eba` |
| AltoRouter | `altorouter/altorouter` | 2.0.3 | `9931b976423f7334c94f7b5b348be8ab1da3415d` |
| Symfony Routing | `symfony/routing` | v8.1.0 | `fe0bfec72c8a806109fb9c3a5f2b898fe0c76eb3` |
| FastRoute | `nikic/fast-route` | v1.3.0 | `181d480e08d9476e61381e04a71b34dc0432e812` |
| Simple PHP Router | `pecee/simple-router` | 5.4.1.7 | `a2843d5b1e037f8b61cc99f27eab52a28bf41dfd` |

Do not run `composer update` when reproducing an existing result. A release-refresh change must update both locked dependencies and `router-versions.json`, rerun all verification, and be reviewed as a new comparison.

## Native APIs and adapter choices

Adapters use the documented production-oriented API without framework bootstrap code.

| Router | Registration | Matching/finalization | Full dispatch classification |
|---|---|---|---|
| Ariel Radix Router | `ArielRouter::add()` and `where()` | `compile()`, then `engine()->resolve()` | Native: `dispatch(Request)` |
| Illuminate Routing | `Router::addRoute()` and `Route::where()` | route collection `compile()`, `setCompiledRoutes()`, then collection `match(Request)` | Native: `Router::dispatch(Request)` |
| Bramus Router | `Router::match()` | No compilation; no handler-free match API | Native: `Router::run()`; request method/URI are supplied through its required globals |
| AltoRouter | `AltoRouter::map()` | No compilation; `match()` | Adapter-managed handler invocation after native matching |
| Symfony Routing | `RouteCollection::add()` | `CompiledUrlMatcherDumper`, then `CompiledUrlMatcher::match()` | Adapter-managed handler invocation after native matching; Routing is a matcher, not a dispatcher |
| FastRoute | `RouteCollector::addRoute()` | `getData()`, then dispatcher `dispatch()` | Adapter-managed handler invocation after native matching |
| Simple PHP Router | `RouteUrl` plus `Router::addRoute()` | `loadRoutes()` is finalization, not compilation; no handler-free match API | Native: `Router::routeRequest()` |

The normalized mode calls the common adapter contract in `src/Contract/RouterAdapterInterface.php`. It returns normalized route IDs, named string parameters, statuses, and dispatch classifications. Handler-free adapters perform parameter extraction; it is never discarded. Bramus Router and Simple PHP Router are absent from match-only rankings because invoking a handler would change the operation being measured.

The direct-call mode in `benchmarks/Native` removes the normalized result-object layer and uses each library directly. It preserves the same dataset and equivalent trivial handler work. Results from normalized and direct-call scenarios are labeled separately.

### Feature support

| Router | Handler-free match | Native handler dispatch | Constraints | Exact 405 | Compilation | Finalization only | Compiled cache |
|---|:---:|:---:|:---:|:---:|:---:|:---:|:---:|
| Ariel Radix Router | yes | yes | yes | yes | yes | no | yes |
| Illuminate Routing | yes | yes | yes | yes | yes | no | yes |
| Bramus Router | unsupported | yes | yes | unsupported | no | no | no |
| AltoRouter | yes | no, adapter-managed | yes | unsupported | no | no | no |
| Symfony Routing | yes | no, adapter-managed | yes | yes | yes | no | yes |
| FastRoute | yes | no, adapter-managed | yes | yes | yes | no | yes |
| Simple PHP Router | unsupported | yes | yes | unsupported | no | yes (`loadRoutes()`) | no |

“Unsupported” means the scenario is omitted, not approximated. All seven implementations pass the common full-dispatch behavior suite.

## Fairness rules

- Every router receives the same ordered `RouteDataset` and logical target.
- Registration is outside warm matching and dispatch; finalization is explicit and outside warm loops.
- All found dynamic routes extract the same parameters, and all handlers return the same logical value.
- Request representations are constructed before warm timed loops. Architecture-required work performed inside a router remains part of its public dispatch cost and is documented.
- Compilation is reported only for routers with a distinct data-generation/build step. AltoRouter and Bramus Router report not applicable. Simple PHP Router is measured under finalization.
- Adapter-managed dispatch is not described as native dispatch.
- Native caches use the upstream recommended generated-PHP form. Cache generation and cache loading run in separate PHP processes. Cache format and artifact size are reported.
- Router execution order is rotated per run. This reduces, but cannot eliminate, thermal, frequency-scaling, and filesystem-cache bias.
- Absolute time and operations/second are retained. Relative values are calculated only among results with the same scenario, detail, route count, runtime profile, and execution environment; fastest is 1.00x.
- No unrelated metrics are averaged and no overall ranking is generated.

Router-specific syntax translation is limited to expressing the shared logical route: `{id}` is converted to each native placeholder format and the identical constraint is retained. Compiled matchers/caches are used only where they are documented production facilities.

## Installation and verification

Native execution is primary and Docker is optional.

Requirements are PHP 8.4.1 CLI with OPcache, Composer 2, and typical POSIX command-line utilities. Xdebug must be disabled for benchmarking.

```bash
composer install --no-interaction --prefer-dist
composer verify
```

`composer verify` runs formatting in check mode, PHPUnit, PHPStan level 8, validates installed package identities against `router-versions.json`, confirms the PHP/deprecation policy, and checks OPcache availability. The benchmark runner repeats the PHPUnit gate and refuses to run if correctness fails. `ROUTER_BENCH_ALLOW_PHP_MISMATCH=1` permits explicitly non-official development/smoke runs while keeping the actual runtime in result metadata.

Individual checks:

```bash
composer test
composer analyse
composer format:check
composer format
```

## Running benchmarks

The official profile enables OPcache for CLI and disables JIT in every PHPBench worker:

```bash
composer benchmark:quick       # all core scenarios, 100 routes
composer benchmark:full        # 10, 100, 1,000, and 10,000 routes plus isolated memory/cache
composer benchmark:memory      # isolated process for every router and size
composer benchmark:cache       # fresh generation and fresh loading processes
composer benchmark:export      # re-export the newest PHPBench XML
composer benchmark:summary     # rebuild BENCHMARK_RESULTS.md from the newest full run
```

The Composer process timeout is disabled for benchmark scripts because a complete 10,000-route run can legitimately exceed five minutes. The runner prints the run ID and pending XML path before PHPBench starts, uses non-ANSI progress output, and creates Markdown/JSON/CSV after measurement completes. Do not close the terminal or terminate the process before the final export message.

For an end-to-end full-pipeline smoke test without the expensive large datasets, override the sizes explicitly:

```bash
ROUTER_BENCH_SIZES=10 php bin/benchmark full
```

Equivalent `make` targets are `make verify`, `make benchmark-quick`, `make benchmark-full`, `make benchmark-memory`, `make benchmark-cache`, and `make benchmark-summary`.

Runtime profiles must be run and reported separately:

```bash
php bin/benchmark quick --runtime=opcache
php bin/benchmark quick --runtime=opcache-off
php bin/benchmark quick --runtime=jit
```

The `opcache` profile is official: `opcache.enable=1`, `opcache.enable_cli=1`, `opcache.jit=0`, `opcache.jit_buffer_size=0`, `opcache.memory_consumption=128`, and `opcache.validate_timestamps=0`. `opcache-off` disables OPcache. `jit` is optional and uses tracing JIT with a 128 MiB buffer. All profiles preserve warnings/fatals and suppress only deprecations. The full target INI map, all visible PHP INI values, PHP binary/version/SAPI, OS, architecture, CPU, logical CPU count, RAM, project commit/dirty state, package identities, seed, and native/container label are written beside every run.

Avoid unrelated workloads, power-saving mode, and thermal saturation. Docker Desktop, virtual machines, and shared CI runners can add material noise. GitHub Actions runs only a smoke benchmark to catch large regressions; its timing is deliberately discarded and is never an official result.

## Docker reproduction

The image pins `php:8.4.1-cli-bookworm`, installs OPcache, and installs the committed lock file.

```bash
docker build -t php-router-benchmarks .
docker run --rm php-router-benchmarks composer verify
docker run --rm \
  -v "$PWD/results:/app/results" \
  php-router-benchmarks composer benchmark:quick
```

Container runs are automatically labeled `container`. Do not compare or merge them with `native_host` runs. Official performance results should preferably be captured directly on a stable host rather than Docker Desktop or another shared virtualized environment.

## Datasets

`DatasetFactory` is deterministic. The default seed is `19890717`, printed in every report. Supported ranked sizes are 10, 100, 1,000, and 10,000; 100,000 is intentionally excluded from version one.

Each dataset contains exactly 50% static routes, 30% one-parameter routes, 10% multiple-parameter routes, and 10% constrained routes. Integer rounding residue is assigned to constraints. It includes `/users`, `/users/new`, `/products`, administrative/API-style groups, unique seeded suffixes, GET and POST methods, numeric and alphabetic constraints, and intentionally overlapping static/dynamic shapes. Ranked paths contain ASCII only and are collision-free by construction. Tests verify count, distribution, determinism, and uniqueness.

URL decoding and trailing slashes are not included in ranked workloads. They are semantic tests because normalizing either behavior in a timed adapter would conceal a real difference.

## Scenarios and timing boundaries

| Scenario | Included in timed operation | Excluded/prepared beforehand |
|---|---|---|
| Registration | Add every route | Adapter creation, dataset generation, handler closure creation |
| Compilation | Native build/data-generation step | Registration |
| Finalization | Simple PHP Router `loadRoutes()` | Registration |
| Cold start | Instantiate, register, finalize when applicable, resolve and invoke the equivalent trivial handler | Dataset generation |
| Warm static/dynamic/constrained/miss/405 | Native match plus normalized parameter/status result | Registration, finalization, request construction |
| Full dispatch | Public dispatch or explicitly labeled adapter-managed invocation, extraction, trivial handler | Registration, finalization, request construction |
| Native direct call | Lowest practical documented API path | Registration, finalization, request construction |
| Cache generation | Router creation, shared route registration, and native PHP cache generation | Dataset generation |
| Cache load/first dispatch | Fresh process loads artifact, then first dispatch is timed separately | Cache generation occurred in a different process |
| Subsequent cached dispatch | Repeated dispatch on the loaded cache | Load and first dispatch |
| Memory | Empty adapter, registered, finalized, peak, deltas | Every router/size runs in its own PHP process |

PHPBench uses warmups, multiple iterations, and scenario-appropriate revolutions. Standard reports expose mean, minimum/best, mode, and relative standard deviation; exporters calculate median, maximum, standard deviation, operations/second, and within-scenario relative speed from raw iterations. Isolated diagnostics use `hrtime()` only inside one-purpose child processes and never mix process memory.

## Output and interpretation

Each PHPBench run creates:

- `.xml`: raw PHPBench data;
- `.environment.json`: complete environment and identity manifest;
- `.json`: normalized rows plus environment;
- `.csv`: flat rows with runtime metadata on every row;
- `.md`: separate scenario tables with absolute and relative measurements;
- console table: scenario, router, routes, median, operations/second, request rates, and relative value.

Memory and cache commands create their own JSON, CSV, Markdown, and console reports. Markdown keeps registration, compilation, finalization, cold start, warm static match, warm dynamic/multiple match, constrained match, route miss, 405, full dispatch, direct-call, memory, and cache lifecycle separate. Inside every comparable route-count/detail group, rows are ordered by mean time with the 1.00x row first. A later-row value that beats the first row in an individual column is rendered green using inline HTML. Slow or unfavorable rows are not filtered.

A successful full run also rebuilds `BENCHMARK_RESULTS.md` in the repository root. It reports per-size leaders, separate 10/100-route and 1,000/10,000-route readings, and geometric-mean rankings within individual scenario families. Cache and memory summaries remain separate; the tool deliberately does not combine unrelated metrics into a universal winner. Run `composer benchmark:summary` to rebuild it from the newest complete full result.

Time differences smaller than run-to-run noise or with high RSD are not a reliable conclusion. Registration, cold start, matching, dispatch, memory, and caches answer different questions. A claim about one must be scoped to that exact dataset size, route shape, runtime profile, execution environment, and software revision.

Request scenarios report both requests/second and requests/minute, calculated from the mean single-operation time. These are single-process routing-operation estimates, not HTTP-server capacity: they exclude network I/O, worker scheduling, application bootstrap outside the stated boundary, middleware, controllers, and concurrent load. Registration, compilation and finalization retain operations/second but correctly report request rates as not applicable.

### Smoke-output example

The final development smoke run used PHP 8.4.1, native-host execution, the OPcache/JIT settings above, 100 routes, three quick iterations, and completed all 13 PHPBench subjects without errors. This excerpt demonstrates the export shape; it is not an official ranking:

```text
PHP 8.4.1 | runtime=opcache | execution=native_host | opcache.enable=1 | opcache.enable_cli=1 | opcache.jit=0 | ... | error_reporting=8191
Scenario                 | Router                 |  Routes |    Median ns |        Ops/s |   Relative
registration             | [router]               |     100 |        ...   |        ...   |      1.00x
warm_dynamic_match       | [router]               |     100 |        ...   |        ...   |      1.00x
full_dispatch            | [router]               |     100 |        ...   |        ...   |      1.00x
Subjects: 13, Failures: 0, Errors: 0
```

## Known semantic differences

- Bramus Router and Simple PHP Router do not expose handler-free matching; their match-only rows are unsupported.
- AltoRouter, Symfony Routing, and FastRoute expose matching but not equivalent handler dispatch; full dispatch is adapter-managed after native matching.
- AltoRouter and Bramus Router have no separate compilation phase. Simple PHP Router `loadRoutes()` is finalization rather than compilation.
- 405 detection is ranked only for Ariel Radix Router, Illuminate Routing, Symfony Routing, and FastRoute. Others are omitted rather than treating 404 as 405.
- For `/encoded/hello%41`, the tested AltoRouter API preserves `%41`; the other tested dispatch paths produce `helloA`. This difference is asserted but not ranked.
- A registered `/slash` route also accepts `/slash/` in the tested Ariel Radix Router, Illuminate Routing, Bramus Router, and Simple PHP Router paths. AltoRouter, Symfony Routing, and FastRoute reject it. This is asserted but not ranked.
- Duplicate GET paths are rejected by FastRoute during registration and by Ariel Radix Router during compilation. In the tested APIs, Illuminate Routing selects the second registration; Bramus Router, AltoRouter, Symfony Routing, and Simple PHP Router select the first. Duplicates are not generated in ranked data.
- Ranked overlap data registers static routes before dynamic routes. If the dynamic route is registered first, Ariel Radix Router still selects the later static route; Illuminate Routing, Bramus Router, AltoRouter, Symfony Routing, and Simple PHP Router select the earlier dynamic route, while FastRoute rejects the later shadowed static registration. This order-sensitive case is documented rather than normalized.
- Optional parameters are correctness-tested only through each router’s supported syntax and are not a ranked workload.
- Illuminate Routing is used as a standalone component with the minimum container and no-op event dispatcher required by its public Router API. Laravel application bootstrap is excluded.
- Bramus Router reads request method/URI from globals. The adapter prepares those globals before the warm timed loop; the router’s own access remains part of native dispatch.

## Adding a router

1. Review the latest stable tag’s official documentation and source; record package, tag, and commit in `composer.json`, `composer.lock`, and `router-versions.json`.
2. Add a neutrally named adapter implementing `RouterAdapterInterface`; translate only syntax, report every feature accurately, and classify dispatch as native or adapter-managed.
3. Add the identity to `AdapterRegistry` and, if appropriate, native direct-call/cache implementations.
4. Run `composer test` after the adapter is added. Add explicit semantic expectations instead of normalization when behavior differs.
5. Run `composer verify`, an OPcache quick benchmark, memory/cache smoke checks, and inspect generated reports before proposing performance conclusions.

Do not add a special optimization merely because it improves one implementation. It is eligible only when official documentation recommends it for production, and its timing boundary and effect must be documented here.

## Limitations

This suite measures routing in isolation, not full application latency, middleware stacks, dependency injection, controllers, network I/O, or template rendering. The normalized mode includes a small shared adapter/result cost; direct-call mode exposes lower practical overhead but has less uniform return representation. Request architecture cannot be made identical without misrepresenting libraries. PHPBench reduces microbenchmark error but cannot eliminate CPU scheduling, branch-predictor, allocator, filesystem-cache, or thermal effects. Cache generation formats are reported rather than treated as interchangeable abstractions.

The repository records selected releases, but “latest” changes over time; update deliberately and never compare refreshed dependencies against old rows as though only one variable changed. Official raw result files should be committed under `results/` together with their environment file and benchmark project commit. Sample or CI data must be labeled as such.

## Reproducing a published result

Check out the exact benchmark project commit, install from `composer.lock`, confirm a clean `composer verify`, and run the command recorded in the environment JSON on comparable hardware. Confirm PHP is exactly 8.4.1, Xdebug is absent, OPcache/JIT values match, execution labels match, router commits match `router-versions.json`, and the seed is `19890717`. Retain raw XML/JSON plus Markdown and CSV exports so another developer can recompute every displayed statistic.
