VK (ВКонтакте) plugin for Kanboard
===============================

Receive Kanboard notifications on VK (VKontakte) social network.

This plugin is a VK adaptation of the
[Telegram plugin for Kanboard](https://github.com/manuvarkey/kanboard-plugin-telegram)
and follows exactly the same architecture:

| Telegram plugin                    | This VK plugin                          |
|------------------------------------|-----------------------------------------|
| Bot API token (`telegram_apikey`)  | Community access token (`vk_access_token`) |
| `getUpdates` + secret message      | `messages.get` + secret code            |
| chat id                            | peer id                                 |
| `sendMessage` (HTML)               | `messages.send` (plain text, VK has no HTML) |
| sendPhoto / sendDocument           | docs.getMessagesUploadServer + docs.save |
| User & project notification types  | User & project notification types       |

Author
------

- Kanboard VK Plugin
- License MIT

Requirements
------------

- Kanboard >= 1.2.22
- PHP with the `curl` extension (no external composer packages needed)
- A VK community (group) **or** a VK app (for the OAuth "Connect VK" flow)

Installation
------------

You have the choice between 2 methods:

1. Download the zip file and decompress everything under the directory `plugins/VK`
2. Clone this repository into the directory `plugins/VK`

Note: Plugin folder is case-sensitive.

Configuration
-------------

### Option A — Community token (recommended, simplest)

1. Open your VK community **Manage → Messages** and enable "Community messages".
2. Go to **Settings → Extension capabilities** and enable "API requests from
   the community", then create a community access token with the `messages`
   permission.
3. In Kanboard go to **Settings > Integrations > VK** and fill the form:
   - **VK access token**: community access token with the "messages" permission
   - (Optional) **VK App ID / VK App Secret**: only needed for the OAuth flow below
4. Users/projects obtain their **peer id** automatically by sending a one-time
   secret code to the community dialog (see below).

### Option B — OAuth ("Connect VK")

If you also fill **VK App ID** and **VK App Secret** (create an app at
<https://dev.vk.com/> and set the redirect URI to
`https://your-kanboard/callback/vk/`), every manager can press
**Connect VK** on **My profile → Integrations**. The plugin then:

- runs the standard VK authorization-code flow;
- saves the community token (if any) globally;
- exchanges the short-lived user token for a **long-lived user token**
  (`auth.exchangeLongLivedToken`, valid ~6 months) and stores it in the
  `users.vk_long_token` column (created automatically by the plugin).

### Receive individual user notifications

- Go to your user profile and choose **Integrations > VK**
- Open a dialog with your bot/community in VK
- Send the unique message (secret code) as displayed on the page
- Click on **Get peer id** and confirm
- Enable VK notifications in your profile: **Notifications > Select VK**

#### Manual

- Determine your `peer_id` (e.g. via `messages.get` with a token, or any VK
  API console)
- Go to your user profile → **Integrations > VK** and enter the peer id
- Enable VK notifications in your profile

### Receive project notifications

- Go to **Project settings > Integrations > VK**
- Send the secret code to the community (or to the conversation you want to use)
- Click on **Get peer id** and confirm
- Enable VK webhooks/notification events for the project

Notes about VK API specifics
----------------------------

- VK does not support HTML markup in messages, so notifications are sent as
  plain text with links written out explicitly.
- Personal outgoing messages from a *user* token are limited by VK anti-spam
  rules; using a community token is recommended.
- Attachments (task files) are forwarded as VK documents when
  "Send attachments along with notification" is enabled.

Development
-----------

Run the unit tests inside a Kanboard checkout:

```
make test plugin=VK
```

License
-------

MIT
