# Using Restricted Stripe API Keys

## Overview

When the Restricted Key Linking feature is enabled, you can link your Stripe API keys to specific website domains. This enhances security by ensuring that the API keys can only be used with the domains they're linked to.

## How It Works

1. When Restricted Key Linking is enabled in the admin settings, you can add Stripe API keys to your website domains.
2. The system will enforce that these API keys can only be used with the domains they're linked to.
3. If an attempt is made to use the API key from a different domain, the request is rejected.

## Setting Up

### For Website Owners

1. Navigate to your website settings page
2. Enter your Stripe API keys in the designated fields:
   - **Stripe Restricted API Key** (rk_live_...) - Used for payment processing
   - **Stripe Secret API Key** (sk_live_...) - Your main secret API key

3. Save your settings

### Important Security Notes

- When key restriction is enabled, your Stripe API keys will only work with the domain they're linked to
- This helps prevent unauthorized use of your API keys from other domains
- Always store your secret API keys securely and never expose them in client-side code

## Best Practices

- Use different API keys for different websites
- Regularly rotate your API keys for enhanced security
- Always use HTTPS for all web pages that collect or display sensitive information

## Troubleshooting

If your Stripe integration stops working after enabling key restriction:

1. Make sure you're using the correct API key for the domain
2. Check that the domain making the API request matches exactly (without www. prefix)
3. Verify that your API key is correctly entered in the website settings
4. Check your server logs for any key restriction error messages
