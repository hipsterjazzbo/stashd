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

## Decision template

Fill the final decision from the actual `test.sh` result. A positive result is
still limited to buggy/moderately malicious plugins on a shared kernel; it is
not a claim against kernel exploits or namespace-escape research.
