# Meta App Review: submission package

App: **Apex Chat Bot**, App ID `1829538868411022`, currently **Unpublished**. Use cases added: **Messenger** ("Engage with customers on Messenger from Meta") and **Instagram** ("Manage messaging & content on Instagram", Instagram API setup).

Placeholders used below:

| Placeholder | Value now (testing) | Replace with |
|---|---|---|
| `https://bot.apexgrowthsolution.com` | `https://myrtle-parsable-amusively.ngrok-free.dev` | your production domain, e.g. `https://bot.apexgrowthsolutions.com` |
| `Apex Growth Systems LLC` | `APP_OPERATOR_NAME` (default *Apex Growth Systems LLC*) | legal/business name exactly as on your Business Verification documents |
| `support@bot.apexgrowthsolution.com` | `APP_CONTACT_EMAIL` | a monitored mailbox on your own domain (not a personal Gmail if you can avoid it) |
| `{PAGE}` | – | the test Facebook Page name + URL, e.g. `facebook.com/YourPage` |
| `{IG}` | – | the test Instagram professional account handle, e.g. `@yourbrand` |

> Meta's dashboard changes often. When a field below is named differently in your dashboard, the dashboard's own **Required actions / Publish** checklist is the source of truth. This guide was checked against developers.facebook.com in September 2026.

---

## 0. Decide which path you need (this determines how much review you do)

Meta has two access levels per permission:

- **Standard Access**: granted automatically, no App Review. Works only for data of people/assets that have a role on the app (or on the Page). Meta's Messenger docs state App Review "is not required if you're only sending and receiving messages for your own Facebook Page", and the Instagram App Review table says *"My app is only for a business I own or manage" → Standard Access → App Review not required*. Receiving messages from members of the public (people with no role on the app) still requires **Business Verification** and the app being **Live**.
- **Advanced Access**: needed when the app serves **other businesses' Pages/Instagram accounts** (you are a *Tech Provider*). Requires **Business Verification** + **App Review** for each permission.

| Your situation | Path | What to do |
|---|---|---|
| You only connect Pages/IG accounts **owned by your own business portfolio** (e.g. Apex Growth Systems LLC' and Digi Risers' own Pages, if both are in *your* Business portfolio and you are admin) | **A: own business** | Complete App settings (section 1), Business Verification (section 2), Data handling (section 3), then **Publish**. Skip section 4. Keep this document ready in case the dashboard still asks for review. |
| You connect **clients' Pages** (agency work, Pages owned by other businesses/people) | **B: Tech Provider** | Everything in this document, including App Review with Advanced Access for each permission (section 4). |

If you are unsure, go with **Path B**: it is the only one that is guaranteed to keep working for client Pages, and the package below is already written for it.

---

## 1. App settings → Basic (dashboard order)

Open **developers.facebook.com → My Apps → Apex Chat Bot → App settings → Basic**.

| Field | Value |
|---|---|
| Display name | `Apex Chat Bot` (must not contain "Facebook", "Messenger", "Instagram", "Meta") |
| App domains | `myrtle-parsable-amusively.ngrok-free.dev` now, your production domain later (no `https://`) |
| Contact email | `support@bot.apexgrowthsolution.com` (Meta sends review results and policy notices here) |
| Privacy Policy URL | `https://bot.apexgrowthsolution.com/privacy` |
| Terms of Service URL | `https://bot.apexgrowthsolution.com/terms` |
| User data deletion | Choose **Data deletion callback URL** → `https://bot.apexgrowthsolution.com/meta/data-deletion`. (Alternative accepted by Meta: *Data deletion instructions URL* → `https://bot.apexgrowthsolution.com/data-deletion`. The callback is preferred; the instructions page is linked from the Privacy Policy either way.) |
| App icon | 1024 × 1024 PNG/JPG, no Meta logos, not a blank/default image |
| Category | **Business and pages** (or *Messaging* if offered) |
| Business use (if asked) | "Support my own business" (Path A) or "Provide services to other businesses" (Path B) |
| Website platform (`+ Add platform → Website`) | Site URL `https://bot.apexgrowthsolution.com/about` |

Click **Save changes**. Meta crawls the Privacy/Terms URLs: they must load publicly over HTTPS with no login, no ngrok interstitial and no 4xx/5xx. Test them in a private browser window first.

> **ngrok warning page:** free ngrok domains show a "You are about to visit…" interstitial to browsers. Meta's crawler and reviewers may see that page instead of your policy, which is a common rejection cause ("Privacy policy URL is invalid"). Deploy to a real domain before submitting (section 7), or at minimum use a paid ngrok plan without the interstitial.

### Facebook Login for Business settings (Deauthorize callback)

If **Facebook Login for Business** (or *Facebook Login*) appears under **Use cases / Products → Settings**:

| Field | Value |
|---|---|
| Deauthorize callback URL | `https://bot.apexgrowthsolution.com/meta/deauthorize` |
| Data Deletion Request URL (if shown here too) | `https://bot.apexgrowthsolution.com/meta/data-deletion` |
| Valid OAuth Redirect URIs | only needed if you later add a "Continue with Facebook" button |

If the product isn't there, skip this; the Basic-settings data deletion field is what's required.

### What the callbacks do (for your own understanding)

- `POST https://bot.apexgrowthsolution.com/meta/data-deletion`: Meta sends `signed_request` (HMAC-SHA256 with your App Secret). We verify it, delete conversations/messages/raw webhook events whose ID matches the `user_id`, store an audit row (`data_deletion_requests`), and return `{"url": "https://bot.apexgrowthsolution.com/meta/data-deletion/<CODE>", "confirmation_code": "<CODE>"}`. The URL is a public status page.
- `POST https://bot.apexgrowthsolution.com/meta/deauthorize`: verifies `signed_request`, logs it, deactivates any connected account whose `settings` record that user's ID, and stores an audit row.
- **Limitation (documented on the public pages too):** the `user_id` Meta sends is the *app-scoped user ID* of the Facebook user who logged in to our app (in practice: the Page admin). It is **not** the PSID/IGSID of the customers who DM your Page, and Meta provides no mapping between them. DM customers therefore request deletion by messaging "Delete my data" or by email, and a team member deletes the conversation. Until the dashboard has a delete button, you can do it with:
  ```
  php artisan tinker
  >>> $c = App\Models\Conversation::where('external_user_id', '<PSID or IGSID>')->first(); $c->messages()->delete(); $c->delete();
  ```
- Requires `META_APP_SECRET` in `.env` and the `data_deletion_requests` table: run `php artisan migrate` once on each environment.

Quick self-test (after deploying, replace the secret):
```
php artisan tinker
>>> (new App\Services\Meta\SignedRequest())->make(['user_id' => '123'])
# then: curl -X POST https://bot.apexgrowthsolution.com/meta/data-deletion -d "signed_request=<value>"
```

---

## 2. Business Verification

Needed for Path B (Advanced Access) and, per Meta's Messenger docs, to receive messages from people who don't have a role on the app.

1. **App settings → Basic → Business portfolio / "Verification"**: connect the app to your Business portfolio (business.facebook.com) for `Apex Growth Systems LLC`.
2. In **Meta Business Suite → Settings → Business info → Business verification**: *Start verification*.
3. Provide: legal business name (must match documents exactly), address, phone, website (a real website on a domain you control, whose footer shows the same business name; the `https://bot.apexgrowthsolution.com/about` page helps if it's on your domain), and one official document (business registration/incorporation certificate, tax registration, or utility bill in the business's name).
4. Verify by email on the website's domain, phone, or domain DNS/meta-tag. Verification typically takes from a few hours to a few days.
5. If the dashboard prompts **"Become a Tech Provider"** (Path B), follow it: it is a short form under **App Review → Requests / Tech Provider verification** that confirms the business will serve other businesses.

---

## 3. Data handling questions ("Data Use Checkup" / "Data handling")

Shown under **App Review → Requests** or the **Publish** checklist, and yearly afterwards as **Data Use Checkup**. Suggested answers:

| Question | Answer |
|---|---|
| Do you have data processors or service providers with access to Platform Data? | **Yes** |
| List them | `OpenAI, L.L.C. (AI reply generation)`; `Anthropic, PBC (AI reply generation)`; `<your hosting provider, e.g. Hetzner / DigitalOcean / AWS> (hosting & database)` |
| Who is the responsible entity for Platform Data? | `Apex Growth Systems LLC` |
| Country of the responsible entity | your country of registration |
| Have you provided personal data to public authorities in response to national security requests in the past 12 months? | **No** (answer truthfully) |
| Policies/processes for such requests | Tick: *Required review of the legality of these requests*, *Provisions for challenging these requests*, *Data minimization policy*, *Documentation of these requests* (only tick what you actually commit to) |
| Data Use Checkup certification | Confirm each permission is used only as described below and data is handled per the Platform Terms and Developer Policies |

---

## 4. App Review → Permissions and Features (Path B)

Go to **App Review → Permissions and Features** (or **Use cases → Customize → Permissions**) and click **Request advanced access** for each item below, then **App Review → Requests → Edit / Continue the request**. For each permission you'll get the same 3 fields: *Usage description*, *Screencast*, and a compliance checkbox.

**Before requesting**, each permission must show **at least one successful API call** in the last 30 days (the dashboard shows "0 API calls" otherwise and blocks the request). Do this with the app in development mode:

1. Connect the Page (Admin → Meta accounts → Connect via token; see `docs/META_SETUP.md`). That calls `me/accounts` (`pages_show_list`), `{page}/subscribed_apps` (`pages_manage_metadata`), Page fields (`pages_read_engagement`) and `instagram_business_account` (`instagram_basic`).
2. From a personal account that has a role on the app, DM the Page and the IG account and let the bot reply (`pages_messaging`, `instagram_manage_messages`).
3. Wait up to 24 h for the counters to update.

### Which permissions to request

| Permission | Request? | Why |
|---|---|---|
| `pages_show_list` | **Yes** | Required dependency; `me/accounts` lists the Pages to connect |
| `pages_manage_metadata` | **Yes** | Subscribe the Page to webhooks (`subscribed_apps`); Messenger profile (greeting / ice breakers) |
| `pages_read_engagement` | **Yes** | Read Page name/metadata and customer name/profile picture; dependency of several others |
| `pages_messaging` | **Yes** | Core: receive & send Messenger messages |
| `instagram_basic` | **Yes** (Facebook Login path) | Get the IG professional account ID/username linked to the Page |
| `instagram_manage_messages` | **Yes** (Facebook Login path) | Core: receive & send Instagram DMs |
| `business_management` | **Only if needed** (see below) | |
| `instagram_business_basic`, `instagram_business_manage_messages` | **Only if** you connect IG accounts via *Instagram Login* (`auth_type = instagram_login`) | Different login product; see below |
| Human Agent | **Not now** (see below) | |

**Which login path does this app use?** The primary path is **Facebook Login (for Business) with Page access tokens**: the Page token talks to `graph.facebook.com` for both Messenger and the Page's linked Instagram professional account. That path uses `instagram_basic` + `instagram_manage_messages`. The app also supports the *Instagram API with Instagram Login* (`graph.instagram.com`, `auth_type=instagram_login`), which uses `instagram_business_basic` + `instagram_business_manage_messages` instead. **Request only the pair for the path you actually use**. If every IG account you manage is linked to a Facebook Page, use the Facebook Login path and don't request the `instagram_business_*` pair. Requesting permissions you don't demonstrate is a top rejection reason.

**`business_management`: try to drop it.** Our code doesn't call Business Manager APIs. It's only needed when `me/accounts` returns an empty list because your Pages are owned by a Business portfolio and your personal access comes only through that portfolio. Test: in Graph API Explorer generate a user token **without** `business_management`, call `GET /me/accounts?fields=id,name,instagram_business_account`. If your Pages appear, drop it (less review burden). If the list is empty, add it back and use the text below.

**Human Agent.** This feature lets a *human* send a reply up to **7 days** after the customer's last message, using the `HUMAN_AGENT` message tag (Messenger and Instagram). Our app sends every message (bot and human dashboard replies) inside Meta's standard **24-hour window** and uses no tags, so Human Agent is **not needed** now. Request it later, only if your team needs to answer after 24 h (weekends, long-running cases), and only after adding the `HUMAN_AGENT` tag to dashboard-sent human replies. Its use-case text would be: "Human support agents reply manually from our dashboard to customer questions that could not be resolved within 24 hours (e.g. weekends); the tag is never used for automated or promotional messages."

### Paste-ready "How will your app use this permission?" texts

Use these as-is (replace `Apex Growth Systems LLC`), and in the *"Please provide step-by-step instructions"* field paste the **Reviewer test instructions** from section 5.

#### `pages_messaging`
```
Apex Growth Systems LLC uses Apex Chat Bot to answer customer direct messages sent to Facebook Pages it manages. When a person starts a conversation with the Page in Messenger, our webhook receives the message and our app replies automatically with a short, helpful answer generated from business information the Page owner provides (services, hours, pricing ranges, next steps). All conversations appear in our private team dashboard, where a human agent can take over at any time and reply personally; when a human replies, the automated assistant pauses in that conversation. We only respond to conversations initiated by the user and only within Messenger's 24-hour standard messaging window; we do not send promotional or unsolicited messages and do not use message tags. Message data is used only to reply to the customer and manage their enquiry, and is never sold or used for advertising.
```

#### `pages_manage_metadata`
```
Apex Chat Bot uses pages_manage_metadata to subscribe each connected Facebook Page to our app's webhooks (POST /{page-id}/subscribed_apps with the messages, messaging_postbacks and message_echoes fields). Without this subscription we cannot receive the messages customers send to the Page, so the automated replies and the human-agent inbox would not work. The Page admin connects their Page in our dashboard, and the app subscribes it automatically; the same permission is used to set the Page's Messenger welcome settings (greeting text and ice breakers) that the admin configures in our dashboard. We do not change any other Page settings.
```

#### `pages_read_engagement`
```
Apex Chat Bot uses pages_read_engagement to read basic metadata of the Facebook Pages a business connects (Page name and ID) so the admin can identify each Page in our dashboard, and to read the name and profile picture of people who message the Page so conversations are labelled with the customer's name for the human agents handling them. We do not read or store Page posts, comments, followers lists or insights.
```

#### `pages_show_list`
```
When a business admin connects their account in our dashboard, Apex Chat Bot calls /me/accounts to show the list of Facebook Pages they manage (with each Page's linked Instagram professional account) so they can choose which Pages the messaging assistant should answer. It is also used to verify that the person actually manages the Page before it is connected. The list is shown only to the admin during setup; we store only the Pages the admin selects.
```

#### `instagram_basic`
```
Apex Chat Bot uses instagram_basic to read the ID and username of the Instagram professional account linked to each connected Facebook Page. We need the account ID to match incoming Instagram Direct message webhooks to the correct business, and the username so the admin can see which Instagram account is connected in our dashboard. We do not read or store the account's media, followers or insights.
```

#### `instagram_manage_messages`
```
Apex Growth Systems LLC uses Apex Chat Bot to answer Instagram Direct messages sent to the Instagram professional accounts of the businesses it manages. When a person sends a DM to the business account, our webhook receives it and the app replies automatically with a short answer based on business information provided by the account owner. All conversations are shown in our private team dashboard, where a human agent can take over and reply personally at any time; the automated assistant then pauses. We only reply to conversations the user started and only within Instagram's 24-hour messaging window; we never send unsolicited or promotional messages. Message data is used only to reply to the customer and manage their enquiry, and is never sold or used for advertising.
```

#### `business_management` (only if you keep it)
```
Our Pages and Instagram professional accounts are owned by a Meta Business portfolio, and the admin's access to them is granted through that portfolio. Apex Chat Bot needs business_management only so that /me/accounts returns the portfolio-owned Pages the admin is allowed to manage, allowing them to connect those Pages to the messaging assistant. We do not create, modify or claim any business assets, ad accounts or users, and we do not call any other Business Manager API.
```

#### `instagram_business_basic` / `instagram_business_manage_messages` (Instagram Login path only)
```
instagram_business_basic: Apex Chat Bot uses this permission after the business logs in with Instagram to read the professional account's ID and username, so incoming Direct message webhooks can be matched to the right business and the admin can see which account is connected. We do not read media or insights.

instagram_business_manage_messages: Used to receive Instagram Direct messages sent to the connected professional account and to reply to them, automatically with a short AI-generated answer based on the business's information or manually by a human agent from our dashboard (the assistant pauses when a human replies). We only reply to user-initiated conversations within the 24-hour messaging window and never send unsolicited or promotional messages.
```

---

## 5. Reviewer test instructions (paste into every permission request)

```
Apex Chat Bot is a server-side messaging assistant for Facebook Pages and Instagram professional accounts. Reviewers do not need to log in to anything to see it working: the experience happens inside Messenger and Instagram.

TEST THE MESSENGER BOT (pages_messaging, pages_manage_metadata, pages_read_engagement, pages_show_list):
1. Log in to Facebook with your reviewer account and open our test Page: https://www.facebook.com/1176050658933274 (Apex Growth Solutions Page)
2. Click "Message" and send: "Hi, what services do you offer?"
3. Within ~10 seconds you will receive an automatic reply from the Page describing the services.
4. Send: "Can I speak to a person?" The reply confirms a team member will follow up. Our team sees the conversation in our dashboard and can reply manually; the assistant then pauses for that conversation (shown in the screencast).

TEST THE INSTAGRAM BOT (instagram_basic, instagram_manage_messages):
1. Log in to Instagram and open {IG HANDLE}
2. Tap "Message" and send: "Hi, are you open on Saturday?"
3. Within ~10 seconds you will receive an automatic reply from the account.

The screencasts show the admin side: granting the permissions with Facebook Login, selecting the Page in our dashboard, the webhook subscription, the automatic reply, a human agent replying from our dashboard, and the assistant pausing.

If a reply does not arrive, please retry once after a minute; our service is monitored 24/7 during review. Contact: support@bot.apexgrowthsolution.com
Privacy Policy: https://bot.apexgrowthsolution.com/privacy  Terms: https://bot.apexgrowthsolution.com/terms  Data deletion: https://bot.apexgrowthsolution.com/data-deletion
```

### Test credentials: options

- **Default (recommended):** no credentials. Reviewers test by messaging the Page/IG account; the admin-side flow is covered by the screencast. Meta explicitly accepts this for server-to-server messaging apps.
- **If the form insists on dashboard access:** create a dedicated temporary login:
  `php artisan admin:create reviewer@<your-domain> --name="Meta Reviewer"` → paste the URL `https://bot.apexgrowthsolution.com/login`, email and password into *"Test credentials / Additional notes"*. There is no read-only role, so delete the user right after the review.
- Never give reviewers your personal Facebook credentials, and don't ask them to log in to Facebook with a test account you created (against Meta's terms).
- Make sure **the Page is published** (not restricted by country/age) and the IG account is **public professional** with *Allow access to messages* enabled (Instagram app → Settings → Messages and story replies → Message controls / Connected tools).

---

## 6. Common rejection reasons (and how this package avoids them)

| Rejection | Avoid it by |
|---|---|
| Privacy policy URL invalid / not loading / behind a login or interstitial | Real HTTPS domain; `https://bot.apexgrowthsolution.com/privacy` is public, no login, no ngrok warning page |
| Privacy policy doesn't explain Meta data or deletion | Our policy lists every field received, AI sub-processors, retention, and links the deletion page |
| Screencast doesn't show the permission being granted / Login flow | Start each video with the Facebook Login dialog showing **Apex Chat Bot** and the requested permissions (see `docs/APP_REVIEW_SCREENCAST.md`) |
| Screencast doesn't show the end-to-end use | Show Page selection → DM from a separate user account → bot reply → human reply from dashboard |
| Couldn't reproduce / bot didn't answer | Server + queue worker + tunnel running 24/7 during review (usually 1–7 days); webhook subscribed for both Page and IG; app secret set |
| Requested permission not needed / not demonstrated | Request only what the table in section 4 marks "Yes"; drop `business_management` if possible; don't request `instagram_business_*` unless you use Instagram Login |
| 0 API calls for the permission | Make the calls described in section 4 before submitting |
| Use case description vague or copied from docs | Use the specific texts above (they describe *our* flow, the 24 h window, human handover, no promotions) |
| App icon / name uses Meta trademarks | Neutral icon; name without "Facebook/Messenger/Instagram/Meta" |
| Business verification incomplete / name mismatch | Verify first; legal name identical on documents, website footer and `APP_OPERATOR_NAME` |
| Non-English UI in screencast | Set Facebook/Instagram language to English (US) and keep the dashboard in English |

---

## 7. Deploy to a real domain (do this before submitting)

Minimal VPS path (Ubuntu 24.04, 1 vCPU / 2 GB is enough):

1. DNS: `A` record `bot.<your-domain>` → server IP.
2. Install: `nginx`, `php8.3-fpm` + extensions (`mbstring xml curl mysql sqlite3 zip bcmath intl`), `mariadb-server`, `composer`, `nodejs` 20+, `supervisor`, `certbot python3-certbot-nginx`.
3. Code: `git clone …` (or upload) to `/var/www/chatbot`; `composer install --no-dev --optimize-autoloader`; `npm ci && npm run build`.
4. `.env`: copy yours, then set `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://bot.<your-domain>`, DB credentials, `META_*`, `OPENAI_API_KEY`/`ANTHROPIC_API_KEY`, `APP_OPERATOR_NAME`, `APP_CONTACT_EMAIL`, `TRUSTED_PROXIES` (your proxy IPs, or remove if nginx is the edge). Keep the **same `APP_KEY`** if you migrate the database (tokens are encrypted with it).
5. `php artisan migrate --force && php artisan config:cache && php artisan route:cache && php artisan view:cache`; `chown -R www-data storage bootstrap/cache`.
6. nginx server block with `root /var/www/chatbot/public;` and `try_files $uri $uri/ /index.php?$query_string;`, then `certbot --nginx -d bot.<your-domain>`.
7. Supervisor program: `php /var/www/chatbot/artisan queue:work --tries=3 --timeout=90` (autostart, autorestart).
8. Cron: `* * * * * cd /var/www/chatbot && php artisan schedule:run >> /dev/null 2>&1` (webhook-event pruning).
9. In Meta: update **Webhooks callback URL** for Messenger and Instagram to `https://bot.<your-domain>/webhooks/meta`, then App domains, Privacy/Terms/Data deletion/Deauthorize URLs, and the Website platform URL.
10. Re-run `php artisan meta:accounts:test`, DM the Page, and confirm the reply.

---

## 8. Submit, then go live

1. **App Review → Requests → Submit for review.** Typical turnaround: 1–7 days. Keep the server up and check `support@bot.apexgrowthsolution.com` + the Developer notifications.
2. If rejected: read the reviewer notes per permission, fix only what they point at, re-record the specific screencast, resubmit (approved permissions stay approved).
3. After approval: **App settings → Basic → App Mode: switch to Live** (or **Publish** in the new dashboard). Path A: publish right after sections 1–3.
4. Confirm the access levels show **Advanced Access** for each approved permission.
5. Reconnect each client Page through the dashboard so the stored Page tokens include the approved permissions; click *Subscribe webhooks* / *Test connection*.
6. DM each Page / IG account from an account **without** any role on the app: you should now get bot replies.

### After-approval checklist

- [ ] App mode **Live**; webhook URLs point to the production domain; `ngrok` no longer referenced anywhere in Meta settings.
- [ ] `META_VERIFY_SIGNATURE=true`, `APP_DEBUG=false` in production.
- [ ] Queue worker and scheduler running; monitor `storage/logs/meta.log`.
- [ ] Privacy Policy retention matches reality: the policy says conversations are kept up to `DATA_RETENTION_MONTHS` (12) months; schedule a pruning job for conversations or change that value/wording. Webhook events are already pruned after 30 days (`meta:prune-webhook-events`).
- [ ] A way for staff to delete a customer's conversation on request (dashboard button or the tinker command in section 1).
- [ ] Calendar reminder for the yearly **Data Use Checkup** (Meta emails you; not completing it restricts the app).
- [ ] Review `APP_CONTACT_EMAIL` inbox for deletion requests (respond within 30 days).
- [ ] Don't add new permissions or change the use case without a new review.

---

## Sources

- Data deletion callback: https://developers.facebook.com/docs/development/create-an-app/app-dashboard/data-deletion-callback
- Access levels (Standard vs Advanced): https://developers.facebook.com/docs/graph-api/overview/access-levels
- Permissions reference: https://developers.facebook.com/docs/permissions
- Messenger Platform overview (access, business verification): https://developers.facebook.com/documentation/business-messaging/messenger-platform/overview
- Messenger App Review: https://developers.facebook.com/docs/messenger-platform/app-review
- Instagram Platform App Review: https://developers.facebook.com/docs/instagram-platform/app-review
- Instagram Messaging App Review: https://developers.facebook.com/docs/messenger-platform/instagram/app-review/
- Human Agent feature: https://developers.facebook.com/docs/features-reference/human-agent
- Messenger & IG Messaging policy: https://developers.facebook.com/docs/messenger-platform/policy/policy-overview/
