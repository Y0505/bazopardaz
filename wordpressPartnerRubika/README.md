# WordPressPartner — Rubika adapter

A separate native-PHP Rubika bot endpoint matching the current Bale demo flow. It does not modify the Bale bot.

## Current demo behavior
- `/start` welcome menu
- Product details and size selection
- Order review and confirmation
- Manual/demo receipt submission
- Saves user/order state in `data.json`
- Optional admin notification and forwarding of the submitted receipt

**Important:** The current Bale implementation uses a hard-coded demo product. This Rubika adapter intentionally mirrors that behavior; it is not a live WooCommerce product synchronizer and does not process real online payments.

## Files and deployment
Upload this folder as:
`public_html/wordpressPartnerRubika/`

Keep the config outside the public web root:
`/home/USERNAME/bot/wordpressPartnerRubika/config.php`

The private config needs:
- `bot_token`: the Rubika bot token
- `admin_chat_id`: optional admin chat ID

The webhook URL is:
`https://YOUR-DOMAIN/wordpressPartnerRubika/webhook.php`

No webhook secret query parameter is required in this version.

Ensure PHP cURL and JSON extensions are enabled and the folder is writable by PHP so it can create `data.json` and `webhook-debug.log`.

## Register the webhook
Rubika Bot API endpoint:
`POST https://botapi.rubika.ir/v3/{TOKEN}/updateBotEndpoints`

JSON body:
```json
{
  "url": "https://YOUR-DOMAIN/wordpressPartnerRubika/webhook.php",
  "type": "ReceiveUpdate"
}
```

The API call must be sent as POST with a JSON body. Never put the bot token in a public URL, source file, screenshot, or chat message.

## Diagnostics
The webhook writes structured diagnostic events to `webhook-debug.log`, including request arrival, config loading, JSON parsing, update extraction, command detection, and Rubika API sendMessage results. The log is denied from public HTTP access by `.htaccess`.

After testing, inspect the log through cPanel File Manager. Do not share bot tokens or full private configuration.
