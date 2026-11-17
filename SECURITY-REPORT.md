# MG Membership – Security Report

_17 November 2026 · Plugin version 1.0.0 · Tested on the local site `backup-plugin.local` (WordPress 7.1.4, PHP 8.3)_

## Summary

| | |
|---|---|
| **Method** | 1) Ran 32 real attacks against the site over HTTP, before any fixes. 2) An independent code review, done without seeing my own findings. 3) Fixed everything. 4) Re-ran all attacks, plus 12 new ones written for the review's findings. |
| **Before** | **10 of 32 attacks succeeded**. The review found **14 more issues** (2 High, 4 Medium, 8 Low). |
| **After** | **44 of 44 attacks blocked.** All other test suites still pass (530 checks in total). |
| **Most serious fixed** | Getting paid access free with a forged Stripe webhook; Authors creating 100%-off coupons or giving themselves the Editor role; PayPal underpayment; password-hash leak through a shortcode. |

## How attackers go after a membership site

Membership plugins hold money, personal data and paid content, so attackers usually try one of these:

| Goal | Typical technique | What we tested |
|---|---|---|
| **Get paid content free** | Fake "payment succeeded" notifications, edit prices/IDs in forms, under-pay, refund but keep access, reuse trials, guess coupons | Forged Stripe webhooks, PayPal underpayment and wrong currency, draft-plan purchase, price fields, trial reuse, refunds, coupon brute force |
| **See other people's data** | Change an ID in a URL (IDOR), read through other routes (REST API, feeds, comments) | Others' receipts and subscriptions, REST posts/pages/comments, URL-rule bypass, Gutenberg blocks over REST |
| **Take over accounts** | Password guessing, stolen session cookies, password-reset abuse | Login brute force, email change without password, account enumeration, magic-link forgery |
| **Get more power** | A low-privilege role (Author) abusing plugin screens to get Editor/Admin | Author creating plans/coupons/rules, membership granting Editor, forged saves |
| **Inject code** | Cross-site scripting (XSS), SQL injection, CSV formulas, CSRF | Payloads in names, bios, event registrations, search boxes, coupon codes, exports; forged forms |
| **Leak secrets** | Shortcodes/fields exposing internals, direct file access, guessable URLs | `[mgm_member_info field="user_pass"]`, direct PHP access, certificate URL guessing |

## Results: before and after

Status shows what each attack achieved **before** the fixes. Every attack below is **blocked after**.

### Found by live attacks (before → after)

| # | Attack | Before | Severity | Fix |
|---|---|---|---|---|
| 1 | Forged Stripe webhook marks an unpaid order as paid (no signing secret set) | **Worked** | Critical | Unsigned webhooks are always refused. Amount and currency are double-checked against the order. |
| 2 | Buy an unpublished (draft) membership by changing the ID in the form | **Worked** | High | Only published plans can be bought (checkout and REST). |
| 3 | PayPal: a genuine $0.01 subscription unlocks a $20 plan | **Worked** | High | Amounts checked on every PayPal payment type (`web_accept`, `subscr_signup`, `subscr_payment`, renewals). |
| 4 | PayPal: 50 JPY accepted for a 50 USD order | **Worked** | High | Currency must match the shop currency. |
| 5 | `[mgm_member_info field="user_pass"]` shows the viewer's password hash (any post author could add it) | **Worked** | High | The shortcode only prints an allow-list of safe fields. |
| 6 | Unlimited password guessing on the login form | **Worked** | Medium | 5 wrong passwords per account per network → 15-minute lock; 30 per network → network lock. |
| 7 | Unlimited coupon-code guessing | **Worked** | Medium | 10 coupon checks per 10 minutes per network (REST and checkout). |
| 8 | Stolen session changes the account email (→ password reset → takeover) | **Worked** | Medium | Changing email or password needs the current password. WordPress also emails the old address. |
| 9 | Price tampering (`price=0` in the form) | Blocked | – | Prices were already calculated on the server. |
| 10 | Others' receipts / subscriptions (IDOR) | Blocked | – | Ownership checks were already in place. |
| 11 | CSRF (profile, event, cancel) | Blocked | – | Nonces were already in place. |
| 12 | XSS (names, bios, attendee names, search, redirect) | Blocked | – | Output was already escaped. |
| 13 | SQL injection (admin search, coupon, directory) | Blocked | – | All queries are parameterised. |
| 14 | Subscriber calling admin actions; REST `/me`; AJAX lesson creation; path traversal; open redirect; direct PHP access | Blocked | – | Capability and nonce checks, `absint`, `wp_safe_redirect`, `ABSPATH` guards. |

### Found by the independent code review (fixed, then confirmed with new attacks)

| # | Issue | Severity | Fix |
|---|---|---|---|
| R1 | An **Author** could create memberships, coupons, rules and downloads. Examples: a 100%-off coupon, or a membership that gives the Editor role to every Author, themselves included. | **High** | The plugin's content types have their own permissions. Plans, rules, coupons and downloads are **admin-only**; courses, lessons and events need **Editor**. Saving a membership also requires admin, and a membership can never grant a role that can publish for others, post unfiltered HTML, manage users or change plugins/themes. |
| R2 | Gutenberg "members only" blocks were included in REST API output | **High** | Block visibility applies to REST too; only editors previewing see everything. |
| R3 | Pages protected only by a URL rule were readable via REST, feeds or `?page_id=` | Medium | URL rules now also protect the matching posts on every route. |
| R4 | Saving a course could attach someone else's paid lessons to a free course | Medium | Only lessons (and courses) the editor may edit can be attached. |
| R5 | Free trials could be repeated by cancelling and re-buying | Medium | One trial per member per plan, including trials from coupons. |
| R6 | Refunds and chargebacks on subscription payments didn't remove access | Medium | Stripe refunds/disputes are matched by invoice and PaymentIntent. PayPal Refunded/Reversed is handled for every payment type. |
| R7 | Team members skipped drip schedules | Low–Med | Drip counts from the day the seat joined. |
| R8 | Paid roles could stay after expiry on quiet sites (cron gaps) | Low–Med | Role clean-up runs from the previous cron run, not a fixed 2-hour window. |
| R9 | Beaver Builder ignored two visibility classes | Low | Handles the same classes as Divi. |
| R10 | Team owners could attach any existing account (including staff) | Low | Existing accounts get an **invitation they must accept**; staff accounts can't be added. |
| R11 | Certificate lookup could be guessed with timestamps, revealing names | Low | Strict code format, exact key match, rate-limited. |
| R12 | Event tickets registered buyers before membership approval | Low | Registration waits for approval. |
| R13 | REST comment listing and search could reveal protected content | Low | Comments on protected posts are removed from all lists and REST. "Hide locked content from search" now also covers REST search. |
| R14 | Smaller issues: coupon limit race, quiz answer key with unlimited retakes, clickable links injected into emails via guest names, CSV formula injection | Low | Pending checkouts count against coupon limits. Answers show only after passing or when out of attempts. Only plugin-generated links are clickable. All CSV exports neutralise formulas. |

## Remaining risks we accept (and why)

| Item | Why it stays | What you can do |
|---|---|---|
| Checkout says "this email is already registered" | Members need this to know they should log in. It reveals that an account exists, a common trade-off. | Fine for most sites. |
| Webhook URLs may point to internal addresses | Only admins can set them, and internal tools (e.g. n8n on the same server) need this. | Only add URLs you trust. |
| Repeat trials using a **new email** | Stopping that needs card fingerprinting at Stripe. | Turn on Stripe Radar rules if trial abuse appears. |
| Rate limits use the visitor's IP (`REMOTE_ADDR`) | Behind Cloudflare or a proxy, every visitor can look like the same IP. | Behind a proxy, return the real IP with the `mgm_client_ip` filter. |
| Protected downloads on **nginx** | `.htaccess` deny rules don't work on nginx; files rely on an unguessable folder name. | Add `location ~ /mgm-protected/ { deny all; }` to the nginx config. |
| "Hide locked content from search" is off by default | Some sites want locked titles to appear in search as a teaser. | Turn it on in Settings → Content Protection. |

## Before going live

1. **Use HTTPS** everywhere.
2. **Set the Stripe webhook signing secret**. Without it, Stripe renewals won't be recorded (unsigned events are now refused on purpose).
3. Use **strong admin passwords and two-factor login** for staff (e.g. a 2FA plugin). Magic-link login is disabled for staff accounts on purpose.
4. Keep WordPress, the theme and all plugins **updated**, and keep **backups**.
5. Behind Cloudflare or a load balancer, configure `mgm_client_ip` so rate limits see real visitor IPs.
6. On nginx, add the deny rule for `uploads/mgm-protected`.

## New security features added

| Feature | Where |
|---|---|
| Login brute-force lockout (also protects `wp-login.php`) | Always on |
| Rate limits: coupons, lost password, guest event sign-ups, account sign-ups, magic links, certificate lookups | Always on (`mgm_rate_limits` filter) |
| Current password required to change email or password | Account → Profile |
| Magic-link login (single-use, 15 minutes, not for staff) | Login page; Settings → General to switch off |
| GDPR data export and erasure (WordPress Privacy tools + account Privacy tab) | Account → Privacy; Tools → Export/Erase Personal Data |
| Private admin notes on members | Members → member profile |
| Renewal reminder email 3 days before a charge | Automatic |
| Plugin content types with their own permissions; safe-role allow-list | Automatic |
