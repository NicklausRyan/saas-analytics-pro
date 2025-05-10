# Server-Side Webhook Tracking API

## Overview

The Server-Side Webhook Tracking API allows website owners to send tracking data from their servers rather than relying solely on client-side scripts. This approach has several advantages:

- **Better reliability**: Not affected by ad blockers or users disabling JavaScript
- **Improved accuracy**: Server-side events are guaranteed to be tracked
- **Enhanced security**: Sensitive tracking operations can be performed on the server
- **Additional control**: Complete control over what data is sent and when

## API Endpoint

```
POST /api/webhook
```

## Authentication

When key restriction is enabled in the admin settings, you must include the website's domain key in the request headers:

```
X-Domain-Key: your_domain_key
```

You can find or regenerate your domain key in the website settings page of your phpAnalytics dashboard.

## Request Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| domain | string | Yes | The domain of your website (e.g., example.com) |
| page | string | Yes | The URL of the page being tracked |
| event | object | No | Event tracking information (see below) |
| referrer | string | No | The referring URL |
| user_agent | string | No | User agent string of the visitor |
| ip | string | No | IP address of the visitor |
| language | string | No | Language code (2 characters) |
| screen_resolution | string | No | Screen resolution (e.g., 1920x1080) |

### Event Object (Only required for event tracking)

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| name | string | Yes | Name of the event |
| value | number | No | Numeric value associated with the event |
| unit | string | No | Unit for the value (max 32 chars) |

## Response

### Success Response

```json
{
  "status": "success"
}
```

Status Code: 200

### Error Responses

**Website not found or tracking disabled:**
```json
{
  "error": "Website not found or tracking disabled"
}
```
Status Code: 404

**Invalid domain key (when key restriction is enabled):**
```json
{
  "error": "Invalid domain key"
}
```
Status Code: 403

**IP address excluded:**
```json
{
  "error": "IP address excluded"
}
```
Status Code: 403

**Bot traffic excluded:**
```json
{
  "error": "Bot traffic excluded"
}
```
Status Code: 403

## Examples

### Tracking a Pageview

```php
<?php
// PHP Example
$ch = curl_init('https://yourdomain.com/api/webhook');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, [
    'domain' => 'example.com',
    'page' => 'https://example.com/products/123',
    'user_agent' => $_SERVER['HTTP_USER_AGENT'],
    'ip' => $_SERVER['REMOTE_ADDR'],
    'language' => substr($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '', 0, 2),
]);

// Include domain key if key restriction is enabled
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'X-Domain-Key: YOUR_DOMAIN_KEY',
]);

$response = curl_exec($ch);
curl_close($ch);
```

### Tracking an Event

```php
<?php
// PHP Example - Track Purchase Event
$ch = curl_init('https://yourdomain.com/api/webhook');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, [
    'domain' => 'example.com',
    'page' => 'https://example.com/checkout/success',
    'user_agent' => $_SERVER['HTTP_USER_AGENT'],
    'ip' => $_SERVER['REMOTE_ADDR'],
    'event' => [
        'name' => 'purchase',
        'value' => 99.95,
        'unit' => 'USD',
    ],
]);

// Include domain key if key restriction is enabled
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'X-Domain-Key: YOUR_DOMAIN_KEY',
]);

$response = curl_exec($ch);
curl_close($ch);
```

### Node.js Example

```javascript
// Node.js Example
const axios = require('axios');

async function trackPageview() {
  try {
    const response = await axios.post('https://yourdomain.com/api/webhook', {
      domain: 'example.com',
      page: 'https://example.com/blog/article-1',
      user_agent: req.headers['user-agent'],
      ip: req.ip,
      language: req.headers['accept-language']?.substring(0, 2),
    }, {
      headers: {
        'X-Domain-Key': 'YOUR_DOMAIN_KEY',
      }
    });
    
    console.log('Tracking successful:', response.data);
  } catch (error) {
    console.error('Tracking error:', error.response?.data || error.message);
  }
}
```

## Best Practices

1. **Always validate responses** to ensure tracking data was accepted.
2. **Keep your domain key secure** when key restriction is enabled.
3. **Don't overload the API** with too many requests in a short period.
4. **Use batch processing** for high-traffic websites to send multiple events at once when possible.
5. **Include as much information as possible** to get more detailed analytics.
6. **Handle errors gracefully** to ensure your application continues to function if tracking fails.

## Rate Limiting

To prevent abuse, the webhook API may implement rate limiting. If you exceed the rate limit, you'll receive a 429 Too Many Requests response. Implement exponential backoff in your clients to handle these situations.

## Comparison with Client-Side Tracking

| Feature | Server-Side Webhook | Client-Side Script |
|---------|-------------------|------------------|
| Ad Blocker Resistance | ✅ Not affected | ❌ Can be blocked |
| JavaScript Required | ❌ Not required | ✅ Required |
| User Interaction Data | ❌ Limited | ✅ Comprehensive |
| Implementation Complexity | ⚠️ Medium | ✅ Low |
| Sensitive Data Handling | ✅ More secure | ⚠️ Less secure |
| Real-time User Behavior | ❌ Limited | ✅ Detailed |

Consider using both methods in tandem for the most comprehensive analytics coverage.
