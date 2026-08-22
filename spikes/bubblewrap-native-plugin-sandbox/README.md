# Bubblewrap native-plugin sandbox spike

Status: disposable spike from `b3c393a`.

This experiment asks whether an ordinary PHP plugin can run as a native process
inside bubblewrap from a normal, non-privileged Stashd-like container. It does
not modify the production plugin runtime, WIT, manifests, Dockerfile, or
application.

## Run

```sh
./spikes/bubblewrap-native-plugin-sandbox/test.sh
```

The test builds a PHP CLI image, runs it as UID/GID 1000 with all capabilities
dropped and `no-new-privileges`, then launches bubblewrap inside that container.
The plugin has only a read-only package, writable staging, private `/tmp`, a
minimal runtime view, and a line-oriented broker on stdin/stdout.

## Upstream basis

The run uses bubblewrap 0.11.2 from Debian Bookworm's package. The upstream
documentation/source consulted on 2026-08-22 was:

- https://github.com/containers/bubblewrap/blob/main/README.md
- https://github.com/containers/bubblewrap/blob/main/bubblewrap.c
- https://github.com/containers/bubblewrap/blob/v0.11.2/SECURITY.md

Upstream describes bubblewrap as an unprivileged sandbox-construction toolkit,
not a complete policy. It always creates a mount namespace; the caller chooses
the visible mounts. User, PID, IPC, UTS, and network namespaces are available,
and `--clearenv`, read-only binds, writable binds, and private `/tmp` are used
here. The upstream security guidance also makes clear that anything mounted
into the sandbox becomes part of the policy surface and that kernel/namespace
escape protection is outside this spike.

## Experimental policy

The exact command is assembled in `runner/run.php`:

```text
--die-with-parent --new-session
--unshare-user --unshare-pid --unshare-ipc --unshare-uts --unshare-net
--clearenv
--ro-bind plugin /plugin
--bind staging /staging
--tmpfs /tmp
--dev /dev
--ro-bind /usr /usr --ro-bind /bin /bin
--ro-bind /lib /lib --ro-bind /lib64 /lib64 --ro-bind /sbin /sbin
--ro-bind minimal-etc /etc
```

No Vault, application, database, secrets, outer `/home`, outer `/run`, or
container-runtime socket is mounted. The only network path is the parent-side
broker, which accepts `http://fixture.allowed/*` and rejects other hosts. The
plugin cannot inject broker responses or credentials; this spike has no
credential capability.

The outer rootless Podman environment rejects bubblewrap's attempt to mount a
new `/proc` (`Operation not permitted`) even with seccomp unconfined and all
capabilities dropped. The sandbox therefore omits `/proc` completely rather
than weakening the outer container. PID namespace creation still succeeds and
the plugin's `/proc` access is denied because no proc filesystem is exposed.

## Scope and follow-up assessment

The spike deliberately uses one PHP file and no SDK. A future helper binary
could be mounted read-only under `/plugin/helpers` and launched with the same
namespace/staging policy; this is plausible, but helper execution is not
implemented here. Python, Node, Rust, Go, Swift, Zig, or a self-contained
binary could use the same stdin/stdout protocol because it is not PHP-specific.

The existing/future WIT contract could remain the canonical language-neutral
IDL while native runtimes use generated bindings over a broker transport rather
than the Wasm Component ABI. That would be a later adapter, not a reason to
change WIT in this spike.

A versioned GitHub Release tarball unpacked under
`/plugins/<id>/<version>/` fits the package shape: code can stay read-only,
`plugin.json` can select the runtime, and Git need not exist at runtime. This
spike does not implement distribution or installation.

Bubblewrap is Linux-specific. The package itself is architecture-neutral, but
the bwrap binary and any native helper need amd64/arm64 builds. This host is
Linux x86_64; arm64 requires a corresponding image/package test.

The current Wasmtime path provides a mature component ABI and stronger
language-neutral execution model. Native PHP is easier to author and debug,
but the runner must build and maintain a filesystem policy, broker protocol,
process timeout, runtime packaging, and architecture-specific native runtime.
For a solo maintainer, that is materially more operational surface than the
current Wasmtime host, even though PHP plugin development is simpler.

## Measured result

Run on 2026-08-22:

- host: Linux 7.1.8, x86_64; bubblewrap 0.11.2; Podman 6.1.0;
- image: PHP 8.5.9 CLI;
- outer command: `podman run --rm --user 1000:1000 --cap-drop=ALL
  --security-opt=no-new-privileges ...`;
- no `--privileged`, `CAP_SYS_ADMIN`, runtime socket, or seccomp relaxation;
- automated `test.sh`: PASS;
- native PHP ran as the non-root plugin user;
- random outer Vault canary, outer application/data files, secrets, and outer
  environment values were not visible;
- `/plugin` rejected mutation, `/staging` and private `/tmp` accepted writes;
- direct network failed; the allowed broker request returned 200 and the
  undeclared destination returned 403;
- the staging result was observed and validated by the outer runner;
- PID namespace creation worked, but the outer rootless Podman environment
  rejected mounting a new `/proc`. The sandbox omits `/proc` rather than
  weakening the container. This is the sole observed deployment caveat.

The spike is therefore **PROMISING WITH CAVEAT** for this environment. It is
not evidence that bubblewrap protects against kernel vulnerabilities or
namespace-escape research. It is evidence that the requested ordinary
filesystem/network boundary can work without privileged execution, with the
noted `/proc` mount limitation.

## Jellyfin comparison result

`test-jellyfin.sh` is the complete comparison exercise. It runs a native PHP
Jellyfin lifecycle through the same sandbox policy and covers discovery,
generic choices, publication descriptors, host materialization, finalization,
`POST /Library/Refresh`, invalid credentials, refresh failure, unauthorized
destinations, direct network denial, environment isolation, and mount
invariants. It passed from a clean no-cache image build.

The native plugin is 106 source lines, the Jellyfin-specific runner/RPC bridge
is 99 lines, and the test is 45 lines. The production Rust Jellyfin source is
209 lines before generated WIT bindings. The native plugin is shorter and more
recognizable PHP, while the runner remains one-time infrastructure rather than
per-plugin code.

The natural PHP surface was small:

```text
RpcHttp::request(method, url, credential-name)
JellyfinBroadcast::discoverLibraries()
JellyfinBroadcast::publish()
JellyfinBroadcast::finalize(publication)
```

The plugin never sees the raw credential. It sends the opaque credential name;
the outer broker validates the destination and uses its private fixture token
when deciding the response. The plugin receives only status/body. The runner
materializes the publication before sending the `materialized` lifecycle event,
so the plugin's POST refresh cannot occur first.

The current WIT concepts map cleanly to this small RPC: operation, choices,
publish files, finalize, HTTP request/response, credential name, generic
errors, and staging/progress. Records, lists, optionals, variants, and result
errors are straightforward JSON/NDJSON values. Resource handles and callbacks
would need SDK conventions, but no fundamental Component-ABI dependency was
found. WIT could remain the canonical IDL with generated language bindings over
native RPC later.

For development, the native loop is effectively edit PHP → run
`test-jellyfin.sh`, with ordinary PHP syntax errors and stack traces. The Rust
loop requires Rust compilation, Component packaging, host loading, and then
the lifecycle test. Native packaging would need PHP plus declared extensions
and bundled `vendor/` dependencies; the package can remain read-only. A future
release tarball can contain `plugin.json`, PHP, `vendor/`, and optional helpers.

Native runtime ownership would include bubblewrap policy, supported runtime
images/extensions, process supervision/timeouts, RPC/SDK compatibility,
package/version handling, and native-helper architecture builds. Wasmtime
ownership includes the Rust host, WIT bindings, Component toolchains, Wasmtime,
WASI constraints, and Component packaging. Native wins on plugin authoring and
debugging; Wasmtime remains lower-dependency and more uniform for the runtime
operator. No meaningful startup benchmark was collected because this is not a
hot-path workload.

## Decision template

Fill the final decision from the actual `test.sh` result. A positive result is
still limited to buggy/moderately malicious plugins on a shared kernel; it is
not a claim against kernel exploits or namespace-escape research.
