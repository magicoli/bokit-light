---
name: project-channel-manager-strategy
description: "Beds24 kept only as a stopgap (explicitly not competitive, won't be contacted for better pricing); Hostex/Channex are the verified real backends; assistant-mcp-engine integration steps for bokit"
metadata:
  type: project
---

Direct-to-OTA reverse engineering (probable-sniffle PoC, Airbnb) is a confirmed dead end — Datadome-tier anti-bot wall (blocked automated login, session-to-device binding, silent `viewer: null` on well-formed authenticated calls). Not a bug to route around, and not worth revisiting as a channel-manager backend strategy. Booking.com/VRBO expected to be comparably defended, untested but not worth probing.

**Beds24 is explicitly NOT competitive** — not "less competitive," ruled out entirely, and the user will not even ask Beds24 for better/reseller pricing. Reasons: every additional channel adds cost (fees stack per-channel, not flat), the room-count-per-unit requirement is an artificial billing lever that can't be gamed (Booking.com requires accurate room counts in the listing description itself), and the pricing model is perceived as deliberately obscured ("je ne suis pas fan de l'obfuscation commerciale"). Beds24 stays in use only because bokit-light isn't finished yet — it's a stopgap, not a target for negotiation or optimization.

**Verified real backends for an ~8-unit / 4-property portfolio** (direct WebFetch page verification, not WebSearch summaries — those produced wrong numbers earlier, e.g. Aiosell's real Channel Manager API tier is $10/hotel + $5/unit ≈ $80/mo, not the $15/mo figure that came from a different, unrelated product tier): **Hostex** is cheaper below ~15 units, **Channex** is cheaper above that; both confirmed no per-channel surcharge and both bill per rentable listing, not per room. Open to Lodgify/Guesty/etc. on a per-client basis if a client already uses one.

**Why:** bokit-light needs its channel-manager sync layer to be vendor-agnostic and not built around Beds24's assumptions, since Beds24 is being actively planned out, not doubled down on.

**How to apply:** don't propose deeper Beds24 integration, don't suggest asking Beds24 for reseller/volume pricing, and don't treat Beds24's data model (per-room granularity) as the canonical shape for bokit's own sync engine — see the existing `SourceConnector`/`SyncEngine` architecture (`[[project-ical-migration]]`, the Sync Engine Architecture section of this project's own MEMORY.md), which already treats Beds24 as one connector among several.

**Related architecture work** (agreed in the probable-sniffle conversation, full plan in `assistant-mcp-engine`'s planning — see personal-assistant-mcp's memory for the engine-extraction side):
1–2. (Not bokit-light's steps — engine extraction happens in personal-assistant-mcp first.)
3. Integrate `assistant-mcp-engine` into bokit-light once extracted, as a dependency (never depend on `personal-assistant-mcp` directly — pulling the full PAM package in would drag app-specific routes/views/migrations into bokit and cause real Laravel package collisions).
4. Port booking tools into bokit-light, adapted to its own structure: bokit reads its already-synced local records (via the existing `SourceConnector`/`SyncEngine`), it does not re-implement direct channel-manager API calls the way PAM's earlier approach did.
5. Optimize bokit's own channel-manager sync layer, folding in whatever was learned building PAM's booking tools plus the Hostex/Channex integration work above.
