# SSH Egress Probe

**Verdict: SSH EGRESS: BLOCKED**

Outbound TCP/22 does not leave this container. Direct connects to port 22 time out
at 10s with no packet ever reaching the target; the HTTP proxy answers
`CONNECT host:22` with `200 Connection Established` but the tunnel carries no data
and is reset the moment a byte is written. Port 443 is the working control.

---

## Environment

| Field | Value |
| --- | --- |
| Environment name | `Default` ("Default - trusted network access") |
| Environment ID | `env_011B9xyR8Rkq9wEjwa7YKdFZ` |
| Environment kind | `anthropic_cloud` |
| Session ID | `session_01FchS7tsKPVWBTBRTvrNy79` |
| Repo | `bilginkilic/fdartgalleryuk` @ `f69784df` (branch `main`) |
| Container | root, Ubuntu 24.04 (noble), CC 2.1.250 |
| Probe date | 2026-08-28 |

### Tooling setup

`apt-get update` partly failed — the two PPAs are blocked by the egress gateway,
which is itself a data point:

```
W: Failed to fetch https://ppa.launchpadcontent.net/deadsnakes/ppa/ubuntu/dists/noble/InRelease
   Invalid response from proxy: HTTP/1.1 403 Forbidden [IP: 127.0.0.1 34985]
W: Failed to fetch https://ppa.launchpadcontent.net/ondrej/php/ubuntu/dists/noble/InRelease
   Invalid response from proxy: HTTP/1.1 403 Forbidden [IP: 127.0.0.1 34985]
```

The install itself succeeded from the main archive:

```
Setting up openssh-client (1:9.6p1-3ubuntu13.18) ...
EXIT=0
/usr/bin/ssh
```

`netcat-openbsd` was already present (`/usr/bin/nc`).

---

## Test A — direct TCP to port 22 (python3 sockets, 10s timeout)

```
57.129.128.118:22 -> TIMED OUT after 10.0s
github.com:22     -> TIMED OUT after 10.0s
```

No connection, therefore no SSH banner from either host. `nc` agrees — both
invocations hit the 15s `timeout` wrapper without returning (exit 124):

```
=== nc -zv port 22 ===
timeout 15 nc -zv 57.129.128.118 22   -> exit=124   (no output, killed by timeout)
timeout 15 nc -zv github.com 22       -> exit=124   (no output, killed by timeout)
```

## Test B — direct TCP to port 443 (control)

```
57.129.128.118:443 -> CONNECTED in 0.0s; NO BYTES within 10s (no banner)
github.com:443     -> CONNECTED in 0.0s; NO BYTES within 10s (no banner)
```

"No bytes" here is expected — a TLS server waits for the ClientHello — so the
handshake was run to confirm the connection is live:

```
57.129.128.118:443 TLS OK in 0.0s proto=TLSv1.3 cipher=TLS_AES_256_GCM_SHA384
github.com:443     TLS OK in 0.0s proto=TLSv1.3 cipher=TLS_AES_256_GCM_SHA384
```

```
=== nc -zv port 443 (control) ===
Connection to 57.129.128.118 443 port [tcp/https] succeeded!   exit=0
```

**Port 443 connects; port 22 does not.** That is the core contrast.

### Important nuance: 443 terminates at an interception gateway

The 0.0s connect time to an OVH VPS in France is not a real RTT. Certificate
inspection shows every 443 connection is terminated locally by an Anthropic
egress gateway that re-issues certificates:

```
$ openssl s_client -connect 57.129.128.118:443
subject=CN = default.domain
issuer=O = Anthropic, CN = Egress Gateway SDS Issuing CA (production)

$ openssl s_client -connect github.com:443 -servername github.com
subject=CN = github.com
issuer=O = Anthropic, CN = Egress Gateway SDS Issuing CA (production)
```

So port 443 is *proxied*, not routed. And the VPS is not on the allowlist even on
443 — the gateway serves its `default.domain` placeholder cert and then denies:

```
$ curl -sS -k --noproxy '*' -o /dev/null -w 'http=%{http_code} time=%{time_total}\n' https://57.129.128.118/
http=403 time=0.013755

$ curl -sS --noproxy '*' -o /dev/null -w 'http=%{http_code} time=%{time_total}\n' https://github.com/
http=200 time=0.252606
```

13ms and a 403 = the response came from the local gateway, never from OVH.
github.com at 252ms with a 200 is a genuine end-to-end fetch. `git ls-remote
origin HEAD` also works (`f69784df05fe...`), confirming HTTPS egress to GitHub.

## Test C — raw `CONNECT host:22` through `$HTTPS_PROXY`

`HTTPS_PROXY = http://127.0.0.1:34985`

```
=== CONNECT 57.129.128.118:22 via 127.0.0.1:34985 ===
  STATUS LINE: HTTP/1.1 200 Connection Established
  POST-CONNECT: NO BYTES within 10s -> TUNNEL DEAD (no SSH banner)

=== CONNECT github.com:22 via 127.0.0.1:34985 ===
  STATUS LINE: HTTP/1.1 200 Connection Established
  POST-CONNECT: NO BYTES within 10s -> TUNNEL DEAD (no SSH banner)
```

**This is the key result.** The proxy returns `200 Connection Established`
optimistically, before (or without) establishing anything upstream. A real SSH
server sends its `SSH-2.0-...` banner immediately on connect; nothing arrives.

Because "no bytes" alone is also what a TLS port looks like, the tunnel was
probed a second time by writing a client banner into it:

```
--- discriminator 1: CONNECT :22 then send our own SSH client banner ---
57.129.128.118:22 status=HTTP/1.1 200 Connection Established
  after sending client banner: error [Errno 104] Connection reset by peer
github.com:22     status=HTTP/1.1 200 Connection Established
  after sending client banner: error [Errno 104] Connection reset by peer

--- discriminator 2 (control): CONNECT :443 then real TLS handshake ---
github.com:443       status=HTTP/1.1 200 Connection Established
  TLS through tunnel OK: TLSv1.3 TLS_AES_128_GCM_SHA256
57.129.128.118:443   status=HTTP/1.1 403 Forbidden
  TLS through tunnel FAILED: ConnectionResetError: [Errno 104] Connection reset by peer
```

A port-443 CONNECT to an allowed host carries a full TLS handshake. A port-22
CONNECT to either host accepts the write and immediately resets. The `200` on
port 22 is a lie; the tunnel is dead.

Note also that the explicit `CONNECT 57.129.128.118:443` is refused outright with
`403 Forbidden`, while the transparently-intercepted direct socket to the same
host:port was allowed to complete a TLS handshake against the gateway's
placeholder certificate. The two paths apply policy at different points.

## Test D — `ssh -vv`

```
$ timeout 25 ssh -vv -o BatchMode=yes -o ConnectTimeout=10 -o StrictHostKeyChecking=no root@57.129.128.118 true
OpenSSH_9.6p1 Ubuntu-3ubuntu13.18, OpenSSL 3.0.13 30 Jan 2024
debug1: Reading configuration data /etc/ssh/ssh_config
debug1: /etc/ssh/ssh_config line 19: include /etc/ssh/ssh_config.d/*.conf matched no files
debug1: /etc/ssh/ssh_config line 21: Applying options for *
debug2: resolve_canonicalize: hostname 57.129.128.118 is address
debug1: Connecting to 57.129.128.118 [57.129.128.118] port 22.
debug2: fd 3 setting O_NONBLOCK
debug1: connect to address 57.129.128.118 port 22: Connection timed out
ssh: connect to host 57.129.128.118 port 22: Connection timed out
SSH_EXIT=255
```

It never reaches authentication — it never reaches the transport layer at all.
The failure is at TCP connect, not at key exchange or auth. Missing keys are
therefore not the limiting factor.

## Proxy status

```
$ curl -sS "$HTTPS_PROXY/__agentproxy/status"
{
  "enabled": true,
  "port": 34985,
  "caBundlePath": "/root/.ccr/ca-bundle.crt",
  "hasSystemCa": true,
  "noProxy": "localhost,127.0.0.1,::1,127.0.0.0/8,0.0.0.0/8,::,169.254.0.0/16,api.anthropic.com,api-staging.anthropic.com,api-pr-preview.anthropic.com,mcp-proxy.anthropic.com,mcp-proxy-staging.anthropic.com,registry.npmjs.org,jsr.io,npm.jsr.io,pypi.org,files.pythonhosted.org,index.crates.io,proxy.golang.org,host.docker.internal,10.0.0.0/8,172.16.0.0/12,192.168.0.0/16,100.64.0.0/10,.svc.cluster.local,*.svc.cluster.local",
  "selective": false,
  "standalone": false,
  "toolScoped": false,
  "installedProxyPreconfiguredClis": [],
  "javaTrustStorePath": "/root/.ccr/java-truststore.p12",
  "readmePath": "/root/.ccr/README.md",
  "gitConfigInjection": true,
  "gitSshRewrite": true,
  "recentRelayFailures": [
    { "ts": "2026-08-28T13:16:43.652Z", "kind": "connect_rejected", "detail": "gateway answered 403 to CONNECT (policy denial or upstream failure)", "host": "ppa.launchpadcontent.net:443" },
    { "ts": "2026-08-28T13:16:43.920Z", "kind": "connect_rejected", "detail": "gateway answered 403 to CONNECT (policy denial or upstream failure)", "host": "ppa.launchpadcontent.net:443" },
    { "ts": "2026-08-28T13:16:44.876Z", "kind": "connect_rejected", "detail": "gateway answered 403 to CONNECT (policy denial or upstream failure)", "host": "ppa.launchpadcontent.net:443" },
    { "ts": "2026-08-28T13:16:45.191Z", "kind": "connect_rejected", "detail": "gateway answered 403 to CONNECT (policy denial or upstream failure)", "host": "ppa.launchpadcontent.net:443" },
    { "ts": "2026-08-28T13:16:47.098Z", "kind": "connect_rejected", "detail": "gateway answered 403 to CONNECT (policy denial or upstream failure)", "host": "ppa.launchpadcontent.net:443" },
    { "ts": "2026-08-28T13:16:47.422Z", "kind": "connect_rejected", "detail": "gateway answered 403 to CONNECT (policy denial or upstream failure)", "host": "ppa.launchpadcontent.net:443" },
    { "ts": "2026-08-28T13:16:51.312Z", "kind": "connect_rejected", "detail": "gateway answered 403 to CONNECT (policy denial or upstream failure)", "host": "ppa.launchpadcontent.net:443" },
    { "ts": "2026-08-28T13:16:51.715Z", "kind": "connect_rejected", "detail": "gateway answered 403 to CONNECT (policy denial or upstream failure)", "host": "ppa.launchpadcontent.net:443" },
    { "ts": "2026-08-28T13:19:12.096Z", "kind": "connect_rejected", "detail": "gateway answered 403 to CONNECT (policy denial or upstream failure)", "host": "57.129.128.118:443" }
  ],
  "downloadQueuedBytes": 0,
  "downloadQueuedPeakBytes": 0,
  "downloadReceivePauseSupported": true,
  "downloadReceiveGateEnabled": true,
  "uploadPausedClients": 0,
  "uploadPauses": 0,
  "uploadPauseSupported": true,
  "uploadGateEnabled": true
}
```

Note `"gitSshRewrite": true`, and the matching git config that rewrites SSH remotes
to HTTPS — the environment is built on the assumption that SSH is unavailable:

```
command line:	url.https://github.com/.insteadof=git@github.com:
command line:	url.https://github.com/.insteadof=ssh://git@github.com/
```

Also note the last `recentRelayFailures` entry: `57.129.128.118:443` was itself
denied by policy during this probe.

## SSH keys

```
$ ls -la ~/.ssh
total 8
drwx------  2 root root 4096 Mar 31 13:23 .
drwx------ 15 root root 4096 Aug 28 13:16 ..

private keys found: 0
```

The directory exists but is empty — no private key of any kind. (Git's
`user.signingkey` points at `/home/claude/.ssh/commit_signing_key.pub`, a public
key used for commit signing via `/tmp/code-sign`, not an authentication key.)

---

## Verdict

**SSH EGRESS: BLOCKED** — in the `Default` environment
(`env_011B9xyR8Rkq9wEjwa7YKdFZ`), same as the sibling session's environment.

Evidence, in order of weight:

1. Direct TCP to port 22 times out at 10s on **both** an arbitrary VPS and
   github.com (Test A), while port 443 to the same hosts connects immediately
   (Test B) — the block is port-scoped, not host-scoped.
2. `CONNECT host:22` returns a bogus `200 Connection Established` with **zero
   bytes** following and an immediate `ECONNRESET` on first write (Test C),
   whereas `CONNECT github.com:443` carries a complete TLS 1.3 handshake. The
   `200` is optimistic; the tunnel is dead.
3. `ssh -vv` never gets past `connect to address ... port 22: Connection timed
   out` (Test D) — the failure is TCP-level, well before key exchange, so it is
   not an artifact of the missing private key.
4. The environment itself is configured on the premise that SSH does not work:
   `gitSshRewrite: true` plus `url.https://github.com/.insteadOf` for both
   `git@github.com:` and `ssh://git@github.com/`.

Secondary finding, relevant if the goal was reaching the OVH VPS at all: **that
host is not reachable on 443 either.** The explicit proxy path answers
`403 Forbidden` and the transparent path terminates at the egress gateway's
`CN=default.domain` placeholder certificate and returns HTTP 403 in 13ms. Only
allowlisted hosts (e.g. github.com) egress from this environment, on 443 only.
