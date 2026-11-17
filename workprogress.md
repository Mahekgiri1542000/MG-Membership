# MG Membership – Work Progress

_Last updated: 17 November 2026 · Plugin version 1.0.0 · Tested on: WordPress 7.1.4, PHP 8.3, Elementor 4.4.0, theme Twenty Twenty-Five (Local site `backup-plugin.local`)_

## Summary

| | |
|---|---|
| **Overall status** | Core plugin and all **19 add-ons** built and tested end-to-end. 11 add-ons added on 17 Nov to cover every core feature of the 15 plugins in the Hostinger comparison (see [FEATURE-COMPARISON.md](FEATURE-COMPARISON.md)). Not yet tested with live payments or with Divi / WPBakery / Beaver Builder installed. |
| **Automated checks** | **530 passed, 0 failing**, across 8 test suites, including **44 simulated attacks, all blocked** (see [Test runs](#test-runs)) |
| **Security** | Full audit: live attack testing + independent code review. 8 vulnerabilities found by attacks and 14 by review, **all fixed**. See [SECURITY-REPORT.md](SECURITY-REPORT.md). |
| **Bugs found by testing** | 10 — all fixed and re-tested (see [Bugs found and fixed](#bugs-found-and-fixed)) |
| **Test data** | Every run creates its own `MGMTEST…` data and deletes it afterwards. Site settings and add-on choices are backed up and restored exactly. No tables are dropped. |

## Feature status

✅ = tested end-to-end and passing · ⚠️ = built, partly tested · ⏳ = not tested yet

| Area | Status | Checks | What was verified |
|---|---|---|---|
| Content protection (rules) | ✅ | 18 | Category, tag, child-page, URL-contains, URL-regex and per-post rules; rule exclusions; custom locked messages; safe teaser; comments hidden; RSS feed and REST API don't leak; `[mgm_protect]`, `[mgm_hide]`, `[mgm_member_info]` |
| Memberships and checkout | ✅ | 14 | Signup with new account; tax (10%); coupon at checkout; offline payment stays pending until approved; thank-you page and receipt; admin approval grants access |
| Coupons | ✅ | 5 | Percentage and fixed codes; case-insensitive; restricted to a plan; expired; unknown; usage counter |
| Purchase rules, free plan, drip | ✅ | 4 | "New customers only" plans; free plan with instant access; drip shows "unlocks on <date>" and unlocks after the delay |
| Recurring plans | ✅ | 8 | Free trial → "Trial" status; upgrade group replaces the old plan; member cancels from account and keeps access until the period ends; payment history |
| Member account | ✅ | 6 | Login by email; wrong password; redirect back to the requested page; profile update; password and email-conflict validation; lost-password message doesn't reveal accounts |
| Courses (LMS) | ✅ | 96 | Separate course suite — see [Test runs](#test-runs) |
| Seminars and events | ✅ | 16 | Speakers, agenda, hybrid venue, seats; guest registration; capacity → waitlist; members-only event; join link only for registered members; cancellation; past events closed; paid ticket auto-registers; calendar (.ics) download; My Events |
| Corporate / group accounts | ✅ | 5 | Team tab with seat count; invite by email creates the account and passes on access; removal takes access away |
| Stripe webhooks | ✅ | 10 | Signature check (bad signature and old timestamp rejected); checkout completed; renewal recorded once even when retried; failed payment → paused; resumed; cancelled; one-time payment; refund |
| Scheduled jobs (cron) | ✅ | 4 | Expiry reminder sent once; offline subscription renewal invoice; cancelled subscription → expired; event reminder 24 hours before |
| Admin screens and actions | ✅ | 17 | Check-in by ticket code; add attendee manually; settings save (and bad request blocked); add-on dependency; grant / revoke; manual transaction creating a user; Reports; Dashboard; CSV exports (members, transactions, subscriptions, attendees) |
| Emails | ✅ | 7 | Welcome, receipt, event confirmation, waitlist, subscription cancelled, password reset and team invitation all arrived in Mailpit |
| Page builders – Gutenberg | ✅ | 4 | Blocks render; "Members-only content" block; visibility setting on normal blocks |
| Page builders – Elementor | ✅ | 6 | Widgets render; member / guest visibility on containers, including with Elementor's element caching |
| Page builders – Divi, WPBakery, Beaver Builder | ⚠️ | 0 | Code written, but these builders aren't installed on this site, so it has never run |
| **New:** Custom registration & profile fields | ✅ | 9 | Fields show on checkout and profile; required, URL, date and dropdown validation; saved on member; public fields on profiles; included in CSV export |
| **New:** Member approval | ✅ | 6 | "Awaiting approval" on the thank-you page and account; no access until approved; approve grants access; member and admin emails |
| **New:** Membership automation | ✅ | 4 | Every new user gets a plan; Editor role gets the Staff plan; removed when the role changes, granted again when restored |
| **New:** Webhooks (Zapier / Make / n8n) | ✅ | 9 | Events delivered to a real local receiver; valid HMAC signature; payload format; delivery log |
| **New:** Member directory, profiles & badges | ✅ | 11 | Opt-in only; search; badge color; public profile shows only public fields; non-opted-in members hidden |
| **New:** Pay-per-post | ✅ | 7 | "Buy this post" on locked content; hidden from pricing; buyer is returned to the article and can read it; others still can't |
| **New:** Protected downloads | ✅ | 8 | Guest → login; non-member → 403; member gets the file with the right name; counter; account Downloads tab; webhook |
| **New:** Menu visibility | ✅ | 3 | Guests, members and specific-plan members see the right menu items; children hidden with a hidden parent |
| **New:** Scheduled release (drip on a date) | ✅ | 2 | "Unlocks on <date>" before; content released after |
| **New:** Account sharing & privacy | ✅ | 5 | Max 2 logins: oldest browser signed out, newest kept; locked posts hidden from search results |
| **New:** Activity log, import, bulk actions | ✅ | 14 | Activity types recorded; CSV import (create, skip, lifetime/dated expiry, custom field, line-numbered errors); bulk grant/revoke; re-importable export |
| **New:** Extras | ✅ | 3 | Course-completed email with certificate link; course webhook; Print receipt button |
| **New:** Magic-link login | ✅ | 7 | Login link emailed (Mailpit); logs in; single-use; forged token refused; same message for unknown emails |
| **New:** Login lockout & rate limits | ✅ | 3 | 5 wrong passwords lock the account for 15 min on that network; other accounts still work |
| **New:** Current password for email/password change | ✅ | 3 | Wrong password refused; name-only change needs none; correct password changes email |
| **New:** Privacy / GDPR | ✅ | 6 | Account Privacy tab; WordPress confirmation email; export covers payments, subscriptions, profile, events; erase keeps payment records |
| **New:** Admin notes & renewal reminders | ✅ | 2 | Private notes (escaped); reminder emailed once 3 days before a charge |
| New add-on admin screens | ✅ | 16 | Every new admin page and editor box renders without PHP warnings |
| Admin dashboard and UI redesign | ✅ | 25 | Every admin screen renders without PHP warnings; screenshots reviewed at desktop and phone width |
| Stripe / PayPal real payments | ⏳ | – | Needs Stripe test keys and a PayPal sandbox account. PayPal IPN was not tested because it calls PayPal's servers. |

## Bugs found and fixed

| # | Severity | Problem | Fix | File |
|---|---|---|---|---|
| 1 | **High (security)** | Locked content leaked to guests through the "excerpt" teaser. Anything under 55 words was shown in full. | Lessons get no automatic teaser; other posts show at most 40% (max 55 words), and nothing if very short; a hand-written excerpt is still used | `includes/class-mgm-access.php` |
| 2 | **High (security)** | Comments on protected posts were visible in block themes, which use the Comments block | Comments are filtered at query level (`comments_pre_query`), which also covers REST and widgets | `includes/class-mgm-access.php` |
| 3 | **High** | Elementor element caching served the first visitor's version of a section to everyone, so members-only sections could show the wrong content | Elements with MG visibility are marked dynamic so they render live; one-time purge of old caches | `addons/page-builders/elementor.php` |
| 4 | **Medium** | URL rules matched the query string, which locked the login page when its return link contained a protected URL | URL rules match the path only; plugin pages (login, account, thank-you, pricing) are never locked by URL rules | `includes/class-mgm-access.php` |
| 5 | **Medium** | Post authors could always see posts they owned, even when locked | Only users who can actually edit the post get the bypass | `includes/class-mgm-access.php` |
| 6 | Medium | Certificates printed "certificate.php" instead of the student's name | Template loader no longer clashes with a template value called `name` | `includes/functions.php` |
| 7 | Medium | Certificate date showed 1 Jan 1970 after a progress reset | Certificates store their own issue date | `addons/certificates/certificates.php` |
| 8 | Low | Report chart date labels overlapped | Shows about 10 evenly spaced labels | `addons/reports/reports.php` |
| 9 | Medium | Website fields in Custom Fields used a DNS-checking function, so valid URLs were rejected whenever the domain didn't resolve, and every signup made a DNS lookup | Plain format check | `addons/custom-fields/custom-fields.php` |
| 10 | Medium | The Members CSV export named custom-field columns `field_company`, but the import expects `company`, so an export couldn't be re-imported | Export uses the plain field key; core column names are reserved | `addons/custom-fields/custom-fields.php` |

## Test runs

All eight suites were run again after the last fix (17 Nov 2026):

| Suite | What it covers | Result |
|---|---|---|
| **Attack suite** (real HTTP attacks + server-side payment forgeries) | Payment abuse, access control, CSRF, XSS/SQL/CSV injection, brute force, privilege escalation, info disclosure | **44 / 44 blocked** (before fixes: 10 of 32 succeeded) |
| Security-features suite (Mailpit, real login links) | Magic link, lockout, current password, privacy, notes, renewal reminders | **20 / 20** |
| New-features suite (real HTTP, local webhook receiver, Mailpit) | The 11 new add-ons + extras | **78 / 78** |
| New admin screens | Every new admin page and editor box | **16 / 16** |
| Feature suite (real HTTP + server checks) | Everything in the feature table except courses and the new add-ons | **127 / 127** |
| Course suite (admin authoring, student journey over HTTP, admin checks) | Courses, lessons, quizzes, certificates, Students screen, access types, course page-builder elements | **97 / 97** |
| General smoke suite | Data model, rules, drip, subscriptions, groups, courses, events, every shortcode as member and guest, Gutenberg, Elementor | **123 / 123** |
| Admin screens suite | Every admin page and metabox renders; save handlers | **25 / 25** |

> The test scripts are currently in Claude's temporary scratchpad folder, not in this project, so they will be lost when that folder is cleared. To keep them, copy them into a `tests/` folder here.

## Left on the site by testing

Test data is always removed, but switching add-ons on during tests creates some infrastructure. It is harmless and is reused when you enable those add-ons:

- table `wp_mgm_activity` (Activity Log add-on)
- folder `wp-content/uploads/mgm-protected/<random>/` with deny rules (Protected Downloads; now empty)
- options `mgm_protected_dir_token`, `mgm_activity_db`, `mgm_elementor_cache_purged`

Your add-on list is unchanged (the 11 new add-ons are **off**). Turn them on under **MG Membership → Add-ons**.

## Behaviour changes from the security work

- Stripe **webhooks are refused until the signing secret is set** (Settings → Payments). One-time Stripe payments still complete through the return page; renewals need the secret.
- Only **administrators** can create or edit memberships, rules, coupons and downloads. **Editors and above** can manage courses, lessons and events (`mgm_content_capability` filter).
- A membership can no longer grant **Editor or Administrator-level** roles.
- Members must enter their **current password** to change their email or password.
- A member gets **one free trial per plan**.
- Quiz answer keys appear only after passing (or when out of attempts).
- Adding an **existing** account to a team now sends an invitation they must accept.

## Known limitations

- A coupon on a recurring plan discounts **every** payment, not only the first.
- PayPal Standard can't cancel a subscription through its API, so members must also cancel in PayPal (the plugin tells them).
- There's no proration when switching plans in an upgrade group: the old plan simply ends.
- Certificates are "print / save as PDF" pages, not generated PDF files.
- If the "excerpt before locked content" setting is on, a protected post still shows a short teaser of its **opening words**. This is intended; turn the setting off under Settings → Content Protection to show nothing.

- Protected-download files are only fully blocked from direct URLs on Apache/IIS (deny rules). On nginx (like this Local site) they rely on an unguessable folder name. For strict blocking on nginx, add a `location ~ /mgm-protected/ { deny all; }` rule.
- Custom Fields are edited in a table, not with a drag-and-drop form builder.
- No social login, affiliate program, WooCommerce integration, proration, Braintree or LMS assignments (see FEATURE-COMPARISON.md).

## Next steps

1. Install **Divi**, **WPBakery** and **Beaver Builder** on a test site and run the page-builder checks there.
2. Test real payments: Stripe in test mode (keys + webhook), and a PayPal sandbox purchase, renewal and cancellation.
3. Test the Curriculum Builder drag-and-drop in a real browser (the saved data is tested; the dragging isn't).
4. Move the test scripts into a `tests/` folder so they can be re-run before each release.
5. Before going live: review email wording, add translations (`/languages`), and do a security review of the payment and webhook code.
