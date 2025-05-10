# Restricted Key Linking

## Overview

Restricted Key Linking is a security feature that enforces domain verification for API keys. When enabled, API keys must be linked to specific website domains in order to track events.

## How It Works

1. When Restricted Key Linking is enabled in the admin settings, each website will generate a unique domain key.
2. This domain key is included in the tracking script and must be sent with each API call.
3. The API server validates that the domain key matches the domain making the request.
4. If validation fails, the request is rejected with a 403 Forbidden response.

## Setting Up

### For Administrators

1. Navigate to **Admin Settings > Analytics**
2. Find the **Restricted Key Linking** option
3. Select **Enabled** to turn on the feature
4. Save settings

### For Website Owners

1. Navigate to your website settings in the dashboard
2. Under the **Domain Key** section, you'll see your unique domain key
3. Make sure your tracking script includes this key (automatically added when copying the tracking code)
4. If needed, you can regenerate your domain key by checking the **Regenerate domain key** option

## Implementation Details

When Restricted Key Linking is enabled:

1. The tracking script automatically includes the domain key as a `data-key` attribute
2. The JavaScript tracking code sends this key in the `X-Domain-Key` HTTP header
3. The API server validates the domain key against the website's stored domain key

## Best Practices

- Regenerate your domain key if you suspect it has been compromised
- Always use the latest version of the tracking script with key restriction enabled
- If you migrate your website to a new domain, update your website settings and regenerate your domain key

## Troubleshooting

If your tracking is not working with Restricted Key Linking enabled:

1. Verify that your tracking script includes the `data-key` attribute
2. Check that the domain key in your tracking script matches the one in your website settings
3. Make sure your website domain is correctly configured in your account
4. If using custom implementation, ensure the `X-Domain-Key` header is included in all API requests
