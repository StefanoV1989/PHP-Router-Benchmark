# Elaborated benchmark results

> This report compares only measurements from the same run. It does not calculate a universal winner by averaging unrelated latency, lifecycle, cache and memory metrics.

Source run: `20260722-101702-full-opcache`; PHP `8.4.1`; runtime `opcache`; execution `native_host`; project commit `33d495004ffc188cd8cc46a9d07624852928359b`; dirty `no`; dataset seed `19890717`.

The aggregate score is the geometric mean of each router's latency ratio to the fastest router in every comparable cohort. It is then normalized so the best aggregate is 1.00x. Lower is better. This avoids allowing a single very large timing to dominate an arithmetic mean. Only routers present in every cohort of a comparison are ranked.

## Router versions

| Router | Package | Version | Commit |
|---|---|---:|---|
| Ariel Radix Router | `stefanov1989/ariel-radix-router` | `v1.0.2` | `dacbe9ec2769e1702264c1f4e766d088c2261c0f` |
| Illuminate Routing | `illuminate/routing` | `v13.21.1` | `ead1511bfebcb8540c751e73b1321ffaa582e668` |
| Bramus Router | `bramus/router` | `1.6.1` | `55657b76da8a0a509250fb55b9dd24e1aa237eba` |
| AltoRouter | `altorouter/altorouter` | `2.0.3` | `9931b976423f7334c94f7b5b348be8ab1da3415d` |
| Symfony Routing | `symfony/routing` | `v8.1.0` | `fe0bfec72c8a806109fb9c3a5f2b898fe0c76eb3` |
| FastRoute | `nikic/fast-route` | `v1.3.0` | `181d480e08d9476e61381e04a71b34dc0432e812` |
| Simple PHP Router | `pecee/simple-router` | `5.4.1.7` | `a2843d5b1e037f8b61cc99f27eab52a28bf41dfd` |

## Practical reading

For normalized full dispatch, **FastRoute** leads the small 10/100-route group, while **Ariel Radix Router** leads the 1,000/10,000-route group. This is the clearest indication in this run that the preferred router can change with route-table size.

Handler-free matching, native full dispatch, registration, compilation, cache lifecycle and memory are reported separately below because they answer different questions. Bramus Router and Simple PHP Router are not included in handler-free matching rankings because that operation is unsupported.

## Normalized full dispatch

This is the common adapter path including parameter extraction and equivalent handler invocation. For AltoRouter, Symfony Routing and FastRoute, invocation is adapter-managed after native matching.

| Routes | Winner | Mean ns/op | Requests/s | Relative |
|---:|---|---:|---:|---:|
| 10 | **FastRoute** | 1772.4 | 564193 | 1.00x |
| 100 | **Symfony Routing** | 2180.9 | 458529 | 1.00x |
| 1,000 | **Symfony Routing** | 2508.0 | 398724 | 1.00x |
| 10,000 | **Ariel Radix Router** | 2543.6 | 393150 | 1.00x |

### Average across 10, 100, 1,000, 10,000 routes

| Rank | Router | Geometric-mean relative latency | Cohorts |
|---:|---|---:|---:|
| 1 | **Ariel Radix Router** | 1.00x | 4 |
| 2 | Symfony Routing | 1.10x | 4 |
| 3 | FastRoute | 2.80x | 4 |
| 4 | Illuminate Routing | 18.89x | 4 |
| 5 | Bramus Router | 42.64x | 4 |
| 6 | AltoRouter | 51.20x | 4 |
| 7 | Simple PHP Router | 150.68x | 4 |

### Small route tables: 10 and 100 routes

| Rank | Router | Geometric-mean relative latency | Cohorts |
|---:|---|---:|---:|
| 1 | **FastRoute** | 1.00x | 2 |
| 2 | Symfony Routing | 1.02x | 2 |
| 3 | Ariel Radix Router | 1.23x | 2 |
| 4 | Bramus Router | 4.23x | 2 |
| 5 | AltoRouter | 7.22x | 2 |
| 6 | Simple PHP Router | 16.80x | 2 |
| 7 | Illuminate Routing | 22.63x | 2 |

### Heavy route tables: 1,000 and 10,000 routes

| Rank | Router | Geometric-mean relative latency | Cohorts |
|---:|---|---:|---:|
| 1 | **Ariel Radix Router** | 1.00x | 2 |
| 2 | Symfony Routing | 1.46x | 2 |
| 3 | FastRoute | 9.61x | 2 |
| 4 | Illuminate Routing | 19.35x | 2 |
| 5 | AltoRouter | 445.81x | 2 |
| 6 | Bramus Router | 527.89x | 2 |
| 7 | Simple PHP Router | 1659.04x | 2 |

## Scenario leaders by route-table size

Each cell is the leader after aggregating only the details inside that scenario and route count.

| Scenario | 10 | 100 | 1,000 | 10,000 | Four-size average | Score |
|---|---:|---:|---:|---:|---|---:|
| Warm static match | **FastRoute** | **FastRoute** | **FastRoute** | **FastRoute** | **FastRoute** | 1.00x |
| Warm single-parameter match | **FastRoute** | **FastRoute** | **FastRoute** | **FastRoute** | **FastRoute** | 1.00x |
| Warm multiple-parameter match | **FastRoute** | **Symfony Routing** | **Ariel Radix Router** | **Ariel Radix Router** | **Ariel Radix Router** | 1.00x |
| Constrained match | **FastRoute** | **Symfony Routing** | **Ariel Radix Router** | **Ariel Radix Router** | **Ariel Radix Router** | 1.00x |
| Overlapping static/dynamic match | **FastRoute** | **FastRoute** | **FastRoute** | **FastRoute** | **FastRoute** | 1.00x |
| Route miss | **FastRoute** | **Ariel Radix Router** | **Ariel Radix Router** | **Ariel Radix Router** | **Ariel Radix Router** | 1.00x |
| Method not allowed | **FastRoute** | **FastRoute** | **Ariel Radix Router** | **Ariel Radix Router** | **Ariel Radix Router** | 1.00x |
| Native direct-call match | **FastRoute** | **Symfony Routing** | **Symfony Routing** | **Ariel Radix Router** | **Ariel Radix Router** | 1.00x |
| Native direct-call full dispatch | **FastRoute** | **Symfony Routing** | **Symfony Routing** | **Ariel Radix Router** | **Symfony Routing** | 1.00x |

## Lifecycle averages

Registration, compilation and Simple PHP Router finalization remain separate. A missing router means that the phase is not applicable, not that its cost is zero.

| Phase | Aggregate leader | Aggregate score | Participants |
|---|---|---:|---:|
| Registration | **AltoRouter** | 1.00x | 7 |
| Compilation | **FastRoute** | 1.00x | 4 |
| Finalization | **Simple PHP Router** | 1.00x | 1 |
| Cold Start | **AltoRouter** | 1.00x | 7 |

## Native cache lifecycle averages

Only routers with a documented native compiled-cache lifecycle participate. Cache formats are not assumed to have equivalent generation or loading mechanics.

| Metric | 10 | 100 | 1,000 | 10,000 | Four-size average | Score |
|---|---:|---:|---:|---:|---|---:|
| Cache generation | **FastRoute** | **FastRoute** | **FastRoute** | **FastRoute** | **FastRoute** | 1.00x |
| Cache load | **FastRoute** | **FastRoute** | **FastRoute** | **FastRoute** | **FastRoute** | 1.00x |
| First dispatch after load | **FastRoute** | **FastRoute** | **Ariel Radix Router** | **Ariel Radix Router** | **Symfony Routing** | 1.00x |
| Subsequent cached dispatch | **FastRoute** | **Symfony Routing** | **Symfony Routing** | **Symfony Routing** | **Symfony Routing** | 1.00x |
| Cache file size | **Symfony Routing** | **Symfony Routing** | **Symfony Routing** | **Symfony Routing** | **Symfony Routing** | 1.00x |

## Memory averages

Memory rankings use isolated worker processes. Lower is better. Finalized memory is excluded from this summary because AltoRouter and Bramus Router have no finalization phase.

| Metric | 10 | 100 | 1,000 | 10,000 | Four-size average | Score |
|---|---:|---:|---:|---:|---|---:|
| Empty router memory | **AltoRouter** | **AltoRouter** | **AltoRouter** | **AltoRouter** | **AltoRouter** | 1.00x |
| Registration memory delta | **AltoRouter** | **AltoRouter** | **AltoRouter** | **AltoRouter** | **AltoRouter** | 1.00x |
| Peak memory | **FastRoute** | **FastRoute** | **AltoRouter** | **AltoRouter** | **AltoRouter** | 1.00x |

## Interpretation limits

- Requests per second are single-process routing-operation estimates (`1e9 / mean ns`), not HTTP server capacity. They exclude web-server, networking and application work.
- Aggregate scores compare scaling within one metric family. They must not be combined into a universal score.
- A result near another router's value should be read together with RSD and repeated on the target host.
- Unsupported operations and non-equivalent cache architectures remain excluded rather than approximated.

Raw readable reports: [`20260722-101702-full-opcache.md`](results/20260722-101702-full-opcache.md), [`20260722-101702-full-opcache.cache.md`](results/20260722-101702-full-opcache.cache.md), and [`20260722-101702-full-opcache.memory.md`](results/20260722-101702-full-opcache.memory.md).
