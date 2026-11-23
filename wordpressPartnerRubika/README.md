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

Keep the secret config outside the public web root:
`/home/USERNAME/bot/wordpressPartnerRubika/config.php`

Copy `config.example.php` to that private path and fill in the Rubika bot token, a long random webhook secret, and optionally the admin chat ID.

The webhook expects:
`https://YOUR-DOMAIN/wordpressPartnerRubika/webhook.php?key=YOUR_WEBHOOK_SECRET`

Ensure PHP cURL and JSON extensions are enabled and the folder is writable by PHP so it can create `data.json` and `webhook-log.php`.

## Register the webhook
Rubika Bot API endpoint:
`POST https://botapi.rubika.ir/v3/{TOKEN}/updateBotEndpoints`

JSON body:
```json
{
  "url": "https://YOUR-DOMAIN/wordpressPartnerRubika/webhook.php?key=YOUR_WEBHOOK_SECRET",
  "type": "ReceiveUpdate"
}
```

The API call can be made from a private PHP setup script or an API client. Never put the bot token in a public URL, source file, screenshot, or chat message.

## Test
1. Open the bot in Rubika and send `/start`.
2. Choose the product, select a size, and confirm.
3. Send a test image as the receipt.
4. Check `data.json` and the optional admin chat.

A successful HTTP response from `updateBotEndpoints` and a real test message are required before considering the webhook active.
