# Admin guide

Sign in at `/admin/`. What you see depends on your role.

| Role | Can |
|---|---|
| Super Administrator | Everything, including administrators and settings |
| Administrator | Competitions (edit, feature, close/reopen, visibility, delete), moderation, reports, voiding votes, messages, abuse blocks, audit log |
| Moderator | View competitions, approve/reject listings, moderate photos, handle reports, suspend/restore |
| Advertising Manager | Campaigns and their statistics |

Permissions are checked on the server for every page and action; hidden buttons are only a convenience.

## Daily routine

1. **Moderation** — approve or reject public matches awaiting listing, then work through photos: *Approve* (visible), *Reject* (hidden, file kept), *Escalate* (hidden for a senior decision), *Remove now* (file deleted, contender removed from rankings). Escalated items appear first.
2. **Reports** — "under 18" reports are pinned to the top. Mark reports Investigating / Resolved / Dismissed with a note. Resolving a report doesn't change content by itself; use the moderation or competition tools.
3. **Messages** — contact form submissions, including advertising enquiries.

## Competitions

Open any competition to see its standings, reports, audit history and vote concentration. From there you can approve, feature on the homepage, suspend/restore, close/reopen, switch public/private, edit title/description/instructions, add a **sponsor** (shows a "Sponsored by …" label and link — this is how sponsored competitions are sold), remove a contender, recalculate the vote counter, or delete permanently (type the ID to confirm).

**Voiding votes:** if one browser or network clearly manipulated a match, void that browser's votes with a reason. The records stay, marked voided, and rankings update immediately.

## Advertising

1. **Advertising → New campaign.** Choose image or text, placement (top banner or footer banner), dates (UTC), priority 1–10, status.
2. Banners: JPG/PNG/WebP up to 2 MB. Desktop ratio 3:1–12:1 and ≥728 px wide (e.g. 1456×180); optional mobile 2:1–6:1 and ≥320 px wide (e.g. 640×200).
3. The highest-priority running campaign is shown; equal priorities rotate. Campaigns stop automatically at their end time. When nothing runs, visitors see a small "Advertise on FaceMatch" link to the contact form (can be turned off in Settings).
4. Every ad is labelled "Advertisement" and the advertiser's name is shown.
5. Statistics: impressions, clicks, CTR, daily breakdown. Counting rules are explained at the top of the Advertising page. The budget field is a note for your records — it is not a payment and isn't treated as revenue anywhere.

## Settings (Super Administrator)

Participant limits, maximum voting period, upload size, whether public matches need approval, rate limits, report threshold, IP-hash retention, the house ad, and the blocked-terms list.

## Administrators (Super Administrator)

Add administrators (they get a temporary password and must change it at first sign-in), change roles, deactivate (their session ends on the next request), or issue a temporary password. The last active Super Administrator can't be demoted or deactivated.
