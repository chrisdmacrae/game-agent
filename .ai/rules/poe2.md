---
paths:
  - 'app/Mcp/Tools/Poe2/**'
---

# Poe2

## Third-party OAuth cannot be completed inside MCP
Linking a user's GGG account is a browser flow, always. MCP has no primitive that can host another provider's consent screen, and minting a token without the user seeing pathofexile.com's own screen defeats the point of OAuth. So the MCP side does everything around it and nothing inside it: `connect_poe_account` returns route('settings.poe.redirect'), and the character tools' notLinked() error hands back the same link.

That link is session-authenticated on purpose. If the browser has no session the `auth` middleware sends the user through a magic link and `url.intended` returns them to the redirect automatically. A signed connect link was considered and rejected: it would let anyone holding the URL attach their own GGG account to someone else's account.

Same shape applies to any future third-party account linking — put the handoff in a tool, keep the consent in the browser.
