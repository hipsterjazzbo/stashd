# Hermes Agent + Remote Ollama Setup for Stashd

This file is for using **Nous Research Hermes Agent** with a remote Ollama server running on Hazel's headless RTX 3060 12 GB machine.

## Hardware split

```text
MacBook Pro 16,1
  RAM: 64 GB
  Role: PhpStorm, git, tests, Docker/dev tools, Hermes Agent/Aider orchestration

Headless PC
  GPU: RTX 3060 12 GB
  RAM: 16 GB
  Role: Ollama inference server only
```

Do not run PhpStorm, Docker tests, and Ollama-heavy generation on the 3060 box at the same time. With only 16 GB RAM, treat it as an inference appliance.

## Model recommendation

For the current 3060 12 GB setup:

```bash
ollama pull qwen2.5-coder:14b
```

Recommended aliases:

```text
qwen-coder-14b-fast = 4096 context, safer/faster default
qwen-coder-14b-work = 8192 context, slower but better for selected-file review
qwen2.5-coder:7b   = fast fallback, useful if Hermes needs more context headroom
```

Why this model:

- Qwen2.5-Coder is code-specific.
- The family includes 7B/14B sizes that are practical on consumer GPUs.
- It is better suited to PHP/code review/test generation than a general chat model on the same hardware.

Important caveat:

Hermes Agent proper is more context-hungry than simple chat. On this hardware, do not expect a local 14B model to behave like Codex/Claude/Cursor in full autonomous mode. Use it for constrained work.

## Ollama server setup

On the 3060 box:

```bash
OLLAMA_HOST=0.0.0.0:11434 ollama serve
```

Pull models:

```bash
ollama pull qwen2.5-coder:14b
ollama pull qwen2.5-coder:7b
```

Create `Modelfile.qwen14b-fast`:

```text
FROM qwen2.5-coder:14b

PARAMETER temperature 0.2
PARAMETER top_p 0.9
PARAMETER num_ctx 4096
```

Create it:

```bash
ollama create qwen-coder-14b-fast -f Modelfile.qwen14b-fast
```

Create `Modelfile.qwen14b-work`:

```text
FROM qwen2.5-coder:14b

PARAMETER temperature 0.2
PARAMETER top_p 0.9
PARAMETER num_ctx 8192
```

Create it:

```bash
ollama create qwen-coder-14b-work -f Modelfile.qwen14b-work
```

Check models:

```bash
ollama list
```

From the MacBook, test the plain Ollama API:

```bash
curl http://<ollama-box-ip>:11434/api/tags
```

## Hermes Agent endpoint

Hermes Agent should be configured to use Ollama's OpenAI-compatible endpoint:

```text
http://<ollama-box-ip>:11434/v1
```

Do not use this for Hermes:

```text
http://<ollama-box-ip>:11434
```

That plain endpoint is for Ollama-native clients.

## Hermes first-run provider setup

In Hermes Agent provider setup:

```text
Provider: Custom endpoint / OpenAI-compatible endpoint
API base URL: http://<ollama-box-ip>:11434/v1
API key: leave blank unless your network/proxy requires one
Model: qwen-coder-14b-work or qwen-coder-14b-fast
```

If Hermes cannot list models, confirm the endpoint from the same machine/container where Hermes runs:

```bash
curl http://<ollama-box-ip>:11434/v1/models
```

If Hermes runs inside Docker/WSL/a VM, `localhost` is probably wrong. Use the LAN IP or host gateway reachable from inside that environment.

## Suggested model choice by task

Use `qwen-coder-14b-work` for:

- selected-file review
- class docblocks
- small tests
- small DTO/resource patches
- explaining code

Use `qwen-coder-14b-fast` for:

- short chat
- summarizing one file
- low-risk edits

Use `qwen2.5-coder:7b` for:

- faster iterations
- tasks needing more context headroom
- when 14B is too slow or unstable

## Hermes usage guidance on this hardware

Good Hermes tasks:

```text
Read these 2 files and suggest test gaps.
Add purpose-focused class docblocks to this folder only.
Write unit tests for this pure helper.
Review this diff for token leaks.
Explain how this lifecycle service fits together.
```

Bad Hermes tasks on this hardware:

```text
Implement Phase 5C Slice 3 end-to-end.
Refactor the entire API resource layer.
Touch routes, controllers, token lookup, tests, docs, and state transitions in one pass.
```

For broad/security-sensitive tasks, ask Hermes for an audit/plan, then move the actual implementation to Codex/Cursor or a frontier model.

## Optional: Aider endpoint for comparison

Aider uses the plain Ollama endpoint via `OLLAMA_API_BASE`, not `/v1`:

```bash
export OLLAMA_API_BASE=http://<ollama-box-ip>:11434
cd /path/to/stashd
aider --model ollama/qwen-coder-14b-work --no-auto-commits
```

Use Aider for narrow patch tasks if Hermes Agent is too context-hungry.

## Troubleshooting

### Hermes connects but tool use is weak or unreliable

Likely cause: local model/context too small for full agent mode.

Mitigations:

- use smaller tasks
- use `qwen2.5-coder:7b` with a larger context if stable
- ask for plans/audits instead of edits
- use Aider for narrow repo patches

### Hermes times out or Ollama gets slow

Mitigations:

- use `qwen-coder-14b-fast`
- reduce concurrent requests
- stop PhpStorm AI Assistant/other clients from hitting Ollama at the same time
- upgrade the 3060 box from 16 GB RAM to 64 GB if possible

### Hermes uses the wrong endpoint

Make sure the Hermes endpoint includes `/v1`:

```text
http://<ollama-box-ip>:11434/v1
```

Make sure Aider/PhpStorm native Ollama integrations do not use `/v1` unless their docs require it.
