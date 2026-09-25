# Meta setup & go-live checklist

App: **Custom Bot Integration** (App ID `1829538868411022`), Graph API **v26.0**.

## 1. Local services (each in its own terminal)

```
php artisan serve --port=8000
ngrok http 8000                  # public URL: https://myrtle-parsable-amusively.ngrok-free.dev
php artisan queue:work --tries=3 --timeout=90
php artisan schedule:work        # optional locally: daily webhook-event pruning
```

## 2. `.env`

| Key | Where to get it |
|---|---|
| `META_APP_ID` | `1829538868411022` |
| `META_APP_SECRET` | App settings → Basic → App secret (required: webhook signatures are rejected without it) |
| `META_VERIFY_TOKEN` | Already generated. Paste the same value into Meta's webhook "Verify token" field |
| `OPENAI_API_KEY` | platform.openai.com → API keys |

After editing: `php artisan config:clear` and restart `queue:work`.

## 3. Admin user

```
php artisan admin:create you@example.com --name="Your Name"
```
Log in at `/login` → `/admin`.

## 4. Meta dashboard

1. **Revoke the exposed token.** Any Graph Explorer token that appeared in a screenshot must be treated as leaked. Generate a fresh one; never paste tokens into chat, code or tickets.
2. **Messenger use case → Customize ("Engage with customers on Messenger from Meta")**
   - Webhooks: Callback URL `https://<tunnel-or-domain>/webhooks/meta`, Verify token = `META_VERIFY_TOKEN` → *Verify and save*.
   - Subscribe the fields `messages`, `messaging_postbacks`, `message_echoes`.
   - Add/connect your Facebook Page.
3. **Instagram use case (Manage messaging & content)**: configure the same Callback URL + verify token for the `instagram` object and subscribe `messages`, `messaging_postbacks`. The Instagram professional account must be linked to the Page, and in the IG app: Settings → Messages → *Allow access to messages* must be on.
4. **Get a Page token into the app.** In Graph API Explorer, generate a **User token** with:
   `pages_show_list, pages_messaging, pages_manage_metadata, pages_read_engagement, instagram_basic, instagram_manage_messages, business_management`.
   Then either:
   - `php artisan meta:connect --subscribe` (paste the token at the hidden prompt), or
   - Admin → Meta accounts → *Connect via token*.

   This exchanges the token for a long-lived one, stores **non-expiring Page tokens (encrypted)** for the Page and its linked IG account, and subscribes the Page to the webhook fields.
5. Check everything: `php artisan meta:accounts:test`, or the *Test connection* button.

## 5. Testing while the app is Unpublished

- Only people with a role on the app (App roles → Administrators / Developers / Testers) can trigger webhooks. Add test people there, and they must accept the invite.
- Send a DM to the Page / IG account **from a role-holder's personal account**, not from the Page itself.
- Watch `storage/logs/meta.log`, `storage/logs/openai.log`, and Admin → Webhook events / Conversations.
- Reply manually from Page Inbox: the bot pauses for `BOT_HUMAN_TAKEOVER_MINUTES` in that conversation.
- Instagram docs state the app must be **Live** to receive Instagram webhooks, so IG may only fully work after publishing.

## 6. Going live

1. App Review → request **Advanced Access** for `pages_messaging`, `pages_manage_metadata`, `pages_read_engagement`, `pages_show_list`, `instagram_basic`, `instagram_manage_messages` (plus `business_management` if used). You need a screencast of the bot answering a DM and a privacy policy URL.
2. Business verification may be required (the dashboard prompts "Become a Tech Provider" only if you serve other businesses' assets).
3. Deploy to a real HTTPS domain. Set `APP_ENV=production`, `APP_DEBUG=false`, and `TRUSTED_PROXIES` to your proxy IPs. Run `queue:work` under Supervisor/systemd and `schedule:run` via cron. Point the webhook Callback URL to the production domain.
4. Publish the app.

## Behaviour notes

- Replies only within Meta's 24h window after the customer's last message.
- One bot reply per incoming message (DB-enforced), duplicate deliveries ignored.
- A burst of customer messages gets one reply to the latest (debounce). Use `BOT_REPLY_DELAY_SECONDS` (e.g. 5–10) to batch them.
- If OpenAI fails: the fallback message is sent if one is configured (Bot settings / `BOT_FALLBACK_MESSAGE`), otherwise the bot stays silent.
- Known edge case: a human reply sent from Page Inbox in the same ~2 minutes that a bot reply is in flight may be treated as the bot's own echo, so the bot won't auto-pause. Use *Human takeover* in the admin for a guaranteed pause.
