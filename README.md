# MG Membership

A complete membership platform for WordPress: paid memberships and subscriptions, content protection, a built-in course system (LMS), seminars and events, and support for every major page builder.

![Version](https://img.shields.io/badge/version-1.0.0-blue) ![WordPress](https://img.shields.io/badge/WordPress-6.0%2B-21759b) ![PHP](https://img.shields.io/badge/PHP-7.4%2B-777bb4) ![License](https://img.shields.io/badge/license-GPL--2.0--or--later-green)

---

## Contents

- [Features](#features)
- [Add-ons](#add-ons)
- [Page builder support](#page-builder-support)
- [Requirements](#requirements)
- [Installation](#installation)
- [Getting started](#getting-started)
- [Shortcodes](#shortcodes)
- [Security](#security)
- [For developers](#for-developers)
- [Project documents](#project-documents)
- [License](#license)

---

## Features

### Memberships and payments
- One-time, recurring, free and lifetime plans, with free trials (one per member per plan), expiry dates and upgrade groups.
- Payment gateways: **Stripe Checkout**, **PayPal** and **offline payments** (bank transfer, cash and similar).
- Every membership can give the member a WordPress role. Roles that could take over the site are blocked.
- Transactions, subscriptions, refunds and chargebacks are tracked automatically.
- 15 editable emails: welcome, receipt, cancellation, expiring soon, payment failed, renewal reminder and more.

### Content protection
- Rules to protect all content, single posts or pages, categories, tags, post types, child pages or URLs.
- A "Membership Access" box on every post to lock it on its own.
- Teasers (a short preview), custom "locked" messages, and redirects for visitors without access.
- Protection also covers the REST API, RSS feeds, comments and search.

### Member experience
- Account area with dashboard, profile, subscriptions, payment history, courses, events and a privacy tab.
- Login page with **magic-link login**: members can sign in from a link sent by email instead of a password.
- **GDPR tools**: members can download or delete their personal data; it also works with the WordPress privacy tools.
- Members can cancel their own subscriptions; renewal reminders are sent 3 days before each charge.

### Admin dashboard
- Overview of revenue, members, signups and activity.
- Member list with status tabs, search, bulk grant or revoke, private admin notes and CSV export.
- Setup checklist for new sites.

---

## Add-ons

Turn each add-on on or off in **MG Membership → Add-ons**.

| Add-on | What it does |
|---|---|
| **Courses (LMS)** | Courses, sections, lessons, quizzes, progress tracking and enrolment through memberships. Lessons can unlock in order, and some can be free previews. |
| **Certificates** | Printable course-completion certificates with a verification code. Needs Courses. |
| **Seminars & Events** | Seminars, webinars, workshops and live events with capacity limits, members-only sign-up, tickets, check-in, calendar export and reminders. |
| **Coupons** | Percentage or fixed discount codes with usage limits, expiry dates and per-membership rules. |
| **Drip Content** | Release content a set number of days after a member joins, or on a set date. |
| **Corporate / Group Accounts** | One account buys seats and invites team members, who receive the same membership. |
| **Custom Fields** | Your own sign-up and profile fields: text, choices, dates, phone and more. |
| **Member Approval** | Hold new sign-ups for chosen memberships until an admin approves them. |
| **Menu Visibility** | Show or hide menu items for members, non-members, guests or specific memberships. |
| **Protected Downloads** | Members-only files with checked download links and download counts. |
| **Pay-Per-Post** | Sell access to a single post, page or lesson without a full membership. |
| **Member Directory & Profiles** | Searchable member directory, public profiles and badges. Members choose whether to appear. |
| **Membership Automation** | Give memberships automatically to new users or to users with chosen roles. |
| **Webhooks & Integrations** | Send signed events to Zapier, Make, n8n, Mailchimp or your own app. |
| **Activity Log** | A timeline of logins, payments, cancellations, lessons, downloads and more. |
| **Import Members** | Create accounts and give memberships from a CSV file. |
| **Reports** | Revenue, new members, cancellations, course completions and event sign-ups, with CSV export. |
| **Account Sharing & Privacy** | Limit how many devices a member can be logged in on at once, and hide locked content from search. |
| **Page Builder Integrations** | Blocks, widgets and modules for the page builders below. |

---

## Page builder support

| Builder | What you get |
|---|---|
| **Gutenberg** | An "MG Membership" block category, a "Members-only content" block, and a visibility panel on every block. |
| **Elementor** | An "MG Membership" widget category and visibility settings (Advanced tab) on every section, container, column and widget. |
| **Divi** | "MG –" modules. Hide any section, row or module with CSS classes: `mgm-members-only`, `mgm-members-{ID}`, `mgm-nonmembers-only`, `mgm-logged-in-only`, `mgm-guests-only`. |
| **WPBakery** | Elements under "MG Membership". |
| **Beaver Builder** | An "MG Membership Element" module, plus the same CSS classes as Divi. |
| **Any other builder** | Bricks, Oxygen, Breakdance, Brizy, Thrive, SiteOrigin and others work through the shortcodes. |

---

## Requirements

- WordPress 6.0 or newer
- PHP 7.4 or newer (tested on PHP 8.3)
- HTTPS for live payments

---

## Installation

1. Download this repository as a ZIP file (**Code → Download ZIP**), or clone it:
   ```bash
   git clone https://github.com/Mahekgiri1542000/MG-Membership.git mg_membership
   ```
2. Put the `mg_membership` folder in `wp-content/plugins/`.
3. In WordPress, go to **Plugins** and activate **MG Membership**.

Activating creates these pages automatically: Account, Login, Thank You, Plans, Courses, and Seminars & Events.

---

## Getting started

1. **Payments:** go to **MG Membership → Settings → Payments** and turn on Offline, Stripe and/or PayPal.
   - Stripe needs your secret key **and the webhook signing secret**. Without the signing secret, Stripe renewals are not recorded.
   - Webhook URLs:
     - Stripe: `https://your-site.com/wp-json/mgm/v1/webhook/stripe`
     - PayPal: `https://your-site.com/wp-json/mgm/v1/webhook/paypal`
2. **Memberships:** go to **MG Membership → Memberships → Add New** and set the price, billing period, trial and role.
3. **Protection:** go to **MG Membership → Rules** to choose what each membership unlocks, or lock a single post from its "Membership Access" box.
4. **Add-ons:** go to **MG Membership → Add-ons** and turn on the features you need.
5. **Emails:** edit the email texts in **MG Membership → Settings → Emails**.

### Course quiz format

Write quiz questions in the lesson editor like this:
```
? Which tag makes a link?
- <div>
* <a>
- <p>
```
`?` starts a question, `-` is a wrong answer, `*` is a correct answer. Use several `*` lines for a question with more than one correct answer.

### Changing the templates

Copy any file from `/templates/` to `your-theme/mg-membership/` and edit it there. Your copy is used instead of the plugin's, and plugin updates won't overwrite it.

---

## Shortcodes

**Memberships and accounts**
```
[mgm_pricing ids="1,2" columns="3" style="cards|minimal|bold"]
[mgm_checkout id="ID"]
[mgm_buy_button id="ID" text="Join"]
[mgm_membership_price id="ID"]
[mgm_account]
[mgm_login redirect=""]
[mgm_thankyou]
[mgm_member_info field="first_name"]
```

**Showing or hiding content**
```
[mgm_protect if="member|nonmember|logged_in|guest" memberships="1,2" message="show|hide"] ... [/mgm_protect]
[mgm_hide memberships="1"] ... [/mgm_hide]
```

**Courses**
```
[mgm_courses category="" columns="3"]
[mgm_my_courses]
[mgm_course_curriculum id=""]
[mgm_course_progress id=""]
[mgm_course_button id=""]
```

**Events**
```
[mgm_events show="upcoming|past|all" type="" layout="list|grid"]
[mgm_event_register id=""]
[mgm_event_details id=""]
[mgm_event_countdown id=""]
[mgm_my_events]
```

**Certificates**
```
[mgm_certificate_verify]
```

---

## Security

The plugin was tested with 44 real attacks (forged payments, under-payment, privilege escalation, data leaks, password guessing, XSS, SQL injection, CSRF and more). All 44 are blocked. The details are in [SECURITY-REPORT.md](SECURITY-REPORT.md).

Built-in protection:
- Verified Stripe and PayPal payments: signatures, amounts and currency are all checked.
- Login lockout after 5 wrong passwords, and rate limits on coupons, password resets, sign-ups and magic links.
- The current password is needed to change an email address or password.
- Only admins can manage plans, rules, coupons and downloads.
- CSV exports are protected against spreadsheet formula injection.

**Before going live:** use HTTPS, set the Stripe webhook signing secret, and use two-factor login for staff accounts. On nginx, add this rule to block direct access to protected files:
```nginx
location ~ /mgm-protected/ { deny all; }
```

---

## For developers

Some of the 85+ hooks:

| Hook | Type | When it runs |
|---|---|---|
| `mgm_transaction_completed` | action | A payment is completed |
| `mgm_membership_granted` / `mgm_membership_revoked` | action | A member gains or loses a membership |
| `mgm_subscription_status_changed` | action | A subscription changes status |
| `mgm_new_member_signup` | action | A new member signs up |
| `mgm_course_completed` / `mgm_lesson_completed` | action | Course progress |
| `mgm_event_registered` | action | Someone registers for an event |
| `mgm_can_access_post` | filter | Change whether a user can see a post |
| `mgm_shortcodes_registry` | filter | Add elements; they appear in every page builder |
| `mgm_gateways` | filter | Add a payment gateway |
| `mgm_addons_registry` | filter | Add your own add-on |
| `mgm_rate_limits` | filter | Change the rate limits |
| `mgm_client_ip` | filter | Return the real visitor IP behind Cloudflare or a proxy |

### Folder structure

```
mg_membership/
├── mg-membership.php   Main plugin file
├── includes/           Core classes (access, checkout, members, emails, REST API, security, privacy)
│   ├── admin/          Admin screens and dashboard
│   └── gateways/       Stripe, PayPal and offline payments
├── addons/             Optional add-ons (courses, events, coupons, page builders and more)
├── templates/          Front-end templates (can be copied into your theme)
├── assets/             CSS and JavaScript
└── languages/          Translation files
```

---

## Project documents

| File | Contents |
|---|---|
| [FEATURE-COMPARISON.md](FEATURE-COMPARISON.md) | Comparison with 15 popular WordPress membership plugins |
| [SECURITY-REPORT.md](SECURITY-REPORT.md) | Security tests, fixes and go-live checklist |
| [workprogress.md](workprogress.md) | Test results and project progress |

---

## License

GPL-2.0-or-later. See the [GNU General Public License](https://www.gnu.org/licenses/gpl-2.0.html).
