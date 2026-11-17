=== MG Membership ===
Requires at least: 6.0
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later

Membership + LMS + Seminars plugin with page builder support.

== Getting started ==
1. Activate. Pages are created automatically: Account, Login, Thank You, Plans, Courses, Seminars & Events.
2. MG Membership → Settings → Payments: enable Offline, Stripe (secret key + webhook secret) and/or PayPal.
   Webhook URLs: /wp-json/mgm/v1/webhook/stripe  and  /wp-json/mgm/v1/webhook/paypal
3. MG Membership → Memberships → Add New (one-time, recurring, free, trials, expirations, upgrade groups, roles).
4. MG Membership → Rules → protect all content, posts, pages, categories, tags, post types, child pages or URLs.
   Or lock a single post from its "Membership Access" box.
5. MG Membership → Add-ons: switch Coupons, Drip, Courses, Certificates, Seminars & Events, Reports,
   Page Builder Integrations and Corporate/Group accounts on or off.

== Shortcodes ==
[mgm_pricing ids="1,2" columns="3" style="cards|minimal|bold"]
[mgm_checkout id="ID"]   [mgm_buy_button id="ID" text="Join"]   [mgm_membership_price id="ID"]
[mgm_account]   [mgm_login redirect=""]   [mgm_thankyou]   [mgm_member_info field="first_name"]
[mgm_protect if="member|nonmember|logged_in|guest" memberships="1,2" message="show|hide"]…[/mgm_protect]
[mgm_hide memberships="1"]…[/mgm_hide]
Courses: [mgm_courses category="" columns="3"] [mgm_my_courses] [mgm_course_curriculum id=""] [mgm_course_progress id=""] [mgm_course_button id=""]
Events:  [mgm_events show="upcoming|past|all" type="" layout="list|grid"] [mgm_event_register id=""] [mgm_event_details id=""] [mgm_event_countdown id=""] [mgm_my_events]
Certificates: [mgm_certificate_verify]

== Page builders ==
* Gutenberg: "MG Membership" block category + "Members-only content" container block + a
  "Membership visibility" panel on EVERY block.
* Elementor: "MG Membership" widget category + "MG Membership Visibility" (Advanced tab) on every
  section, container, column and widget.
* Divi: "MG – …" modules. Visibility on any section/row/module via CSS class:
  mgm-members-only, mgm-members-{ID}, mgm-nonmembers-only, mgm-logged-in-only, mgm-guests-only
* WPBakery: elements under "MG Membership".
* Beaver Builder: "MG Membership Element" module (+ the same CSS-class visibility as Divi).
* Any other builder (Bricks, Oxygen, Breakdance, Brizy, Thrive, SiteOrigin…): use the shortcodes.

== Course quiz format ==
? Question text
- wrong answer
* correct answer   (several * = multiple-choice)

== Theme overrides ==
Copy any file from /templates/ to yourtheme/mg-membership/ and edit it.

== Developer hooks (selection) ==
mgm_transaction_completed, mgm_membership_granted, mgm_membership_revoked, mgm_subscription_status_changed,
mgm_new_member_signup, mgm_course_completed, mgm_lesson_completed, mgm_event_registered,
mgm_can_access_post (filter), mgm_shortcodes_registry (filter – new elements appear in all builders),
mgm_gateways (filter – add payment gateways), mgm_addons_registry (filter – add your own add-ons).
