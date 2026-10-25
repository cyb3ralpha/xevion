# Voting and ranking

## Matchups

Every unordered pair of contenders is a possible matchup (3 contenders → 3 pairs, 20 → 190). For each request:

1. Contenders whose photo is removed, rejected or escalated are excluded.
2. Pairs this browser has already voted on are excluded — a browser never sees the same pair twice.
3. If possible, contenders from the previous matchup are skipped so the same face doesn't appear twice in a row.
4. From the remaining pairs, those with the fewest recorded votes (within one of the minimum) are preferred, so data accumulates evenly across all pairs. One is picked at random and the left/right order is randomised.

Odd numbers of contenders need no special handling because pairs are drawn from all combinations, not a bracket. When every pair has been voted on, the browser sees "You've voted on every matchup".

## Recording a vote

The server issues a one-time **pair token** (stored in the session) with each matchup. A vote must present that token, a participant from that exact pair, and a client-generated **idempotency key** (UUID). Inside one database transaction the server locks the competition row, re-checks that voting is still open, inserts the vote and updates the cached counter.

Duplicate protection is enforced by the database, not just the code:

- `UNIQUE (idempotency_key)` — a retried request (e.g. after a network error) returns the original result and never inserts a second row. The browser retries with the same key automatically.
- `UNIQUE (competition_id, voter_hash, pair_low, pair_high)` — one counted vote per browser per pair.

Votes are rejected when the match is closed (including closing mid-decision), suspended, deleted, private without access, when the participant isn't in the served pair, or when the pair token is missing or expired.

## Ranking

Computed from counted vote rows every time, never from client data or the cached counter:

1. **Win rate** = wins ÷ (wins + losses), over votes where both contenders are still in the match.
2. **Tie-break:** more total wins ranks higher.
3. If still equal, contenders **share the rank** and are marked "Tied".

"Share of votes" is a contender's wins as a percentage of all counted votes. With fewer than three comparisons for any contender, the page labels positions as provisional.

A **winner** is only declared after voting closes, and only if exactly one contender holds rank 1 with at least one comparison. A tie for first is announced as a tie. No winner is ever derived from a single matchup.

Votes voided by an administrator stay in the database (with reason, admin and time) but no longer count. Votes involving a removed contender are excluded from rankings.

## Results visibility

Set by the creator, enforced on the results page, in the vote API response (the optional "x% agree with you" split) and everywhere else:

- **Visible during voting**
- **Revealed when voting closes**
- **Only the organiser** (creator link) — and administrators

Hidden results are never rendered into HTML, JavaScript or JSON.

## Limitations — please read

FaceMatch has no accounts, so it **cannot guarantee one person, one vote**. Someone can clear cookies, use a private window, switch devices or networks and vote again. The protections reduce casual and scripted abuse, they don't eliminate it:

- one vote per pair per browser identifier (random cookie, stored hashed);
- per-network rate limit on votes per minute (keyed hash of the IP);
- a per-network daily cap on votes for the same pair (default 8) so a household or office can still vote but a script on one connection can't flood a pair;
- automatic temporary blocks for networks that exceed limits by 5×;
- admin tools that show vote concentration per browser and per network, and let you void a browser's votes with an audited reason.

Results should be presented — as the site does — as entertainment, not as a verified poll.
