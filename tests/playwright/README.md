## Run

Stack up and seeded (`e2e-status`; `e2e-reset` for a clean provider side), then from the plugin directory:

```bash
flock /tmp/e2e_stack.lock env NODE_PATH=/home/claude/e2e/node_modules \
    /home/claude/e2e/node_modules/.bin/playwright test -c tests/playwright/playwright.config.js
```

`LTI_SEED` (default `/home/claude/e2e/state/seed.json`) names the seed, `E2E_LIB` (default `/home/claude/e2e/lib/e2e`) the
stack helper library (login, psql helpers). `playwright.config.js` bypasses the sandbox proxy for `*.platform-a.test` and
`*.tool-b.test`; the stack CA must be in Chromium's NSS db (done by `e2e-up`). Use the stack's Playwright binary, `npx`
may pick another version. The specs change Site A's tool options (send name / e-mail), the learner's name on A and the
claim policy on B, and restore them in `afterAll`; `identity-claims.spec.js` creates one manual account on B and deletes it.

| Spec | Story |
|---|---|
| `identity-profile.spec.js` | minimal claims -> placeholder on B -> teacher enters the name -> relaunch keeps it -> participants list; learner cannot open the teacher pages |
| `identity-claims.spec.js` | incomplete claims (name without e-mail) -> changing claims -> provenance on B's course profile -> teacher lock -> contradicting claims kept out -> claims withdrawn -> repeated launches write nothing -> existing B account with the same e-mail not linked -> learner sees the provenance on the own profile |

## Status

See `docs/ACCEPTANCE.md` (date, browsers, results). Not part of CI.
