# App Review screencast scripts

One video per permission group. You can upload the **same video** to several permissions when it shows all of them (Meta accepts that), but each permission's video must visibly show *that* permission being granted and used. See `docs/APP_REVIEW.md` for the texts to paste next to each video.

| Video | Upload it to | Length |
|---|---|---|
| **A: Connect Page + Messenger bot** | `pages_show_list`, `pages_manage_metadata`, `pages_read_engagement`, `pages_messaging` (+ `business_management` if requested) | 3–4 min |
| **B: Instagram bot** | `instagram_basic`, `instagram_manage_messages` | 2–3 min |
| **C: Instagram Login variant** (only if you request `instagram_business_*`) | `instagram_business_basic`, `instagram_business_manage_messages` | 2–3 min |

## Recording tips (read first)

- **1080p**, MP4, under **5 minutes** each, under ~100 MB. Record the whole browser window (OBS, Loom or Windows `Win+Alt+R` / Snipping Tool video).
- **English everywhere:** Facebook, Instagram and our dashboard in English (US). Browser zoom 110–125% so text is readable.
- **Show the URL bar** at all times; reviewers check that it's the real app on your domain (not localhost).
- **Captions:** add a short on-screen caption for every step (Loom/Clipchamp text overlays, or a sticky note window). Suggested captions are given in *italics* below. Meta says: "Provide captions and tool-tips… explain the meaning of buttons".
- Use **two browsers/profiles** side by side: left = *Page admin* (our dashboard), right = *Customer* (a personal Facebook/Instagram account that is **not** the Page and has a role on the app while the app is unpublished).
- No sound needed; if you narrate, keep it in English and calm. Blur/crop any access token, App Secret, or personal data of real customers. Use test conversations only.
- Slow down: pause ~2 s on each important screen (permission dialog, reply arriving).
- Before recording: server, queue worker (`php artisan queue:work`) and the public URL are running; `BOT_REPLY_DELAY_SECONDS=0`; bot enabled; the test Page is published; old test conversations cleared.
- Do one dry run, then record. Trim dead time, but **don't cut** between "message sent" and "reply received" (it should look real-time).

---

## Video A: Connect Page + Messenger bot

| # | Screen | Action | Caption |
|---|---|---|---|
| 1 | `{BASE}/about` | Show the app description page, scroll to "Meta permissions we use". | *Custom Bot Integration answers Facebook Page and Instagram DMs for {OPERATOR}, with human takeover.* |
| 2 | `{BASE}/login` → dashboard | Log in as the admin. | *Business admin logs in to our dashboard.* |
| 3 | Dashboard → **Meta accounts → Connect via token** | Show the page and its instructions. | *Admin connects a Facebook Page.* |
| 4 | New tab: **Graph API Explorer** (developers.facebook.com/tools/explorer) | Select app **Custom Bot Integration**, *User Token*, tick `pages_show_list`, `pages_manage_metadata`, `pages_read_engagement`, `pages_messaging` (+ IG permissions if recording one combined video). Click **Generate Access Token**. | *Admin grants the permissions to Custom Bot Integration using Facebook Login.* |
| 5 | **Facebook Login dialog** | Pause on the dialog showing the app name and the permission list. Click *Continue*, select the Page (and IG account), *Save*. | *pages_show_list, pages_manage_metadata, pages_read_engagement and pages_messaging are requested here.* |
| 6 | Explorer | Copy the token (**blur it in editing**). | *The user token is pasted into our dashboard (never shared elsewhere).* |
| 7 | Dashboard → Connect via token | Paste, click **Fetch Pages**. The list of managed Pages appears. | ***pages_show_list**: we list the Pages this admin manages.* |
| 8 | Page selection | Tick the test Page, click **Save accounts**. | *Admin chooses which Page the assistant should answer.* |
| 9 | Meta accounts list | Show the Page name and "Webhooks subscribed" / click **Subscribe webhooks** then **Test connection** (green result). | ***pages_manage_metadata**: our app subscribes the Page to message webhooks. **pages_read_engagement**: we read the Page name.* |
| 10 | Right browser: customer on facebook.com/{PAGE} → **Message** | Type "Hi, what services do you offer?" and send. | *A customer starts a conversation with the Page in Messenger.* |
| 11 | Right browser | Wait for the automatic reply to arrive (≈5–10 s). Pause on it. | ***pages_messaging**: our assistant replies automatically within the 24-hour window.* |
| 12 | Left: Dashboard → **Conversations** | Open the new conversation: customer name, both messages, "Bot" label on the reply. | *The conversation appears in our team inbox with the customer's name.* |
| 13 | Conversation page | Type "Hi! This is Sarah from the team, happy to help personally." → **Send reply**. | *A human agent takes over and replies from the dashboard.* |
| 14 | Right browser | Show the human reply arriving in Messenger. | *The customer receives the human reply in Messenger.* |
| 15 | Left: conversation page | Show the "Bot paused until …" / *Human takeover* badge. | *The assistant pauses automatically after a human reply.* |
| 16 | Right browser | Customer sends "Great, thanks!" and **no bot reply** comes. Left: message shows as received, no bot reply. | *While paused, only humans answer.* |
| 17 | Left | Click **Clear pause** (optional) and show bot active again. End on `{BASE}/privacy`. | *Admins control the assistant. Privacy policy: {BASE}/privacy* |

`business_management` (only if requested): in step 7 add a caption *"Our Pages are owned by a Business portfolio; business_management lets /me/accounts return them"*, and show that the Page listed belongs to the portfolio (Business Suite → Settings → Pages).

---

## Video B: Instagram bot (Facebook Login path)

| # | Screen | Action | Caption |
|---|---|---|---|
| 1 | Graph API Explorer → Facebook Login dialog | Generate a token for **Custom Bot Integration** with `instagram_basic`, `instagram_manage_messages` (+ `pages_show_list`, `pages_manage_metadata`, `pages_read_engagement`). Pause on the dialog; select the Page **and** the Instagram account. | *Admin grants instagram_basic and instagram_manage_messages to Custom Bot Integration.* |
| 2 | Dashboard → Connect via token → **Fetch Pages** | Show the Page row with its linked Instagram username. | ***instagram_basic**: we read the linked Instagram professional account's ID and username.* |
| 3 | **Save accounts** → Meta accounts list | Show the Instagram account row (platform Instagram, @username) and **Test connection**. | *The Instagram account is connected and subscribed to DM webhooks.* |
| 4 | Phone mirror or instagram.com in the right browser as the customer | Open {IG}, tap **Message**, send "Hi, are you open on Saturday?" | *A customer sends a DM to the business on Instagram.* |
| 5 | Right | Automatic reply arrives. Pause. | ***instagram_manage_messages**: the assistant replies automatically.* |
| 6 | Left: Conversations | Open the Instagram conversation (IG badge, username). | *The DM appears in our team inbox.* |
| 7 | Left | Human types a reply → **Send reply**. | *A human agent replies from the dashboard.* |
| 8 | Right | Human reply arrives on Instagram. | *The customer receives the human reply on Instagram.* |
| 9 | Left | Show the paused badge; customer sends another message, no bot reply. | *The assistant pauses after a human replies.* |

Note: Instagram webhooks for people without an app role only arrive once the app is Live with Advanced Access. For the recording, use a customer account that **has a role on the app** (Tester) and has accepted the invite.

---

## Video C: Instagram Login variant (only if requesting `instagram_business_*`)

Same as Video B, but step 1 is the **Instagram** login screen (Business login for Instagram) showing *Custom Bot Integration* and the permissions `instagram_business_basic` and `instagram_business_manage_messages`, and step 2 shows the account connected with auth type *Instagram Login*. Captions name the `instagram_business_*` permissions instead.

---

## Upload checklist

- [ ] Each video starts with the permission grant dialog showing the app name.
- [ ] Each permission you request is named in a caption at the moment it's used.
- [ ] Tokens/App Secret blurred; no real customer data.
- [ ] English UI, URL bar visible, 1080p, < 5 min.
- [ ] The Page/IG account in the video is the one in the reviewer instructions.
