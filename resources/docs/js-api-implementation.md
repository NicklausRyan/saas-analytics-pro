# JavaScript API Implementation Guide

This guide will walk you through the process of implementing the phpAnalytics JavaScript tracking API on your website.

## Basic Implementation

### Step 1: Add the Tracking Code

Insert the following tracking code into the `<head>` section of your HTML:

```html
<script id="ZwSg9rf6GA" 
        src="https://yourdomain.com/js/script.js" 
        data-host="https://yourdomain.com" 
        data-key="your-domain-key" 
        data-dnt="true" 
        async>
</script>
```

Replace `https://yourdomain.com` with your actual website domain and `your-domain-key` with your website's domain key (if key restriction is enabled).

### Step 2: Customize the Tracking Code

Here are the attributes you can customize:

- `data-host`: The domain where your phpAnalytics installation is hosted
- `data-key`: Your domain key (only required if key restriction is enabled)
- `data-dnt`: Set to "true" to respect Do Not Track browser settings (recommended)

### Step 3: Verify the Installation

Once installed, the tracking script will automatically track:

1. Pageviews when the page loads
2. Navigation changes in single-page applications (SPAs)
3. Browser history navigation events

To verify the installation, check your phpAnalytics dashboard to see if pageviews are being recorded.

## Tracking Custom Events

### Basic Event Tracking

To track a custom event, use the `track` method with an event object:

```javascript
window.pa.track({
    name: 'event_name',
    value: 123,
    unit: 'unit_name'
});
```

- `name` (required): The name of the event (e.g., 'purchase', 'signup')
- `value` (optional): A numeric value associated with the event
- `unit` (optional): The unit of the value (e.g., 'USD', 'seconds')

### Examples of Custom Event Tracking

#### Track a Button Click

```javascript
document.getElementById('signup-button').addEventListener('click', function() {
    window.pa.track({
        name: 'button_click',
        value: 1,
        unit: 'click'
    }, null, {
        button_id: 'signup-button',
        button_text: 'Sign Up Now'
    });
});
```

#### Track Form Submissions

```javascript
document.getElementById('contact-form').addEventListener('submit', function(e) {
    window.pa.track({
        name: 'form_submit',
        value: 1,
        unit: 'submission'
    }, null, {
        form_id: 'contact-form',
        form_type: 'contact'
    });
});
```

#### Track Video Engagement

```javascript
const video = document.getElementById('product-video');

// Track video start
video.addEventListener('play', function() {
    window.pa.track({
        name: 'video_play',
        value: 1
    }, null, {
        video_id: 'product-video',
        video_title: 'Product Demo'
    });
});

// Track video complete
video.addEventListener('ended', function() {
    window.pa.track({
        name: 'video_complete',
        value: 1
    }, null, {
        video_id: 'product-video',
        video_title: 'Product Demo'
    });
});
```

## Using the Webhook-Compatible API

The webhook API allows you to use the same format as the server-side webhook API:

```javascript
window.pa.webhook({
    name: 'purchase',
    value: 99.95,
    unit: 'USD'
}, {
    product_id: '12345',
    category: 'electronics'
});
```

### Key Differences Between track() and webhook()

1. `track()` sends data to `/api/event` endpoint
2. `webhook()` sends data to `/api/webhook` endpoint
3. `webhook()` automatically includes the domain
4. Both methods respect key restrictions when enabled

## Handling Responses

Both tracking methods return a promise-like object that you can use to handle success and error states:

```javascript
window.pa.track({
    name: 'signup',
    value: 1
})
.then(function(response) {
    console.log('Tracking successful:', response.status);
    
    if (response.status === 200) {
        // Do something when tracking succeeds
    }
})
.catch(function(error) {
    console.error('Tracking failed:', error);
});
```

## Advanced Implementation

### Tracking User Journeys

To track a user's journey through your application:

```javascript
// Step 1: User visits the landing page
window.pa.track({
    name: 'journey_step',
    value: 1,
    unit: 'step'
}, null, {
    journey: 'signup',
    step: 'landing_page_view'
});

// Step 2: User clicks the signup button
window.pa.track({
    name: 'journey_step',
    value: 2,
    unit: 'step'
}, null, {
    journey: 'signup',
    step: 'signup_click'
});

// Step 3: User completes the signup form
window.pa.track({
    name: 'journey_step',
    value: 3,
    unit: 'step'
}, null, {
    journey: 'signup',
    step: 'signup_complete'
});
```

### Tracking E-commerce Interactions

Track the complete e-commerce flow:

```javascript
// Product view
function trackProductView(product) {
    window.pa.track({
        name: 'product_view',
        value: product.price,
        unit: product.currency
    }, null, {
        product_id: product.id,
        product_name: product.name,
        category: product.category
    });
}

// Add to cart
function trackAddToCart(product, quantity) {
    window.pa.track({
        name: 'add_to_cart',
        value: product.price * quantity,
        unit: product.currency
    }, null, {
        product_id: product.id,
        product_name: product.name,
        quantity: quantity
    });
}

// Checkout step
function trackCheckoutStep(step, value, products) {
    window.pa.track({
        name: 'checkout_step',
        value: step,
        unit: 'step'
    }, null, {
        total_value: value,
        products: products.length,
        step_name: ['cart', 'information', 'shipping', 'payment', 'review'][step - 1]
    });
}

// Purchase complete
function trackPurchase(order) {
    window.pa.track({
        name: 'purchase',
        value: order.total,
        unit: order.currency
    }, null, {
        order_id: order.id,
        products: order.items.length,
        tax: order.tax,
        shipping: order.shipping
    });
}
```

### Single Page Application (SPA) Implementation

For SPAs, the tracking script automatically tracks page changes when using the browser's History API. However, if you're using a custom router or want more control, you can manually track page changes:

```javascript
// React Router example
import { useEffect } from 'react';
import { useLocation } from 'react-router-dom';

function TrackingComponent() {
    const location = useLocation();
    
    useEffect(() => {
        // Track page view when the location changes
        const previousUrl = window.location.href;
        
        // Simulate a page view with the current URL
        window.pa.track(null, previousUrl);
    }, [location]);
    
    return null; // This component doesn't render anything
}

// Include this component in your app
function App() {
    return (
        <>
            <TrackingComponent />
            {/* Your app components */}
        </>
    );
}
```

## Performance Optimization

### Loading the Script Asynchronously

The tracking script is designed to load asynchronously and won't block page rendering. Always include the `async` attribute in the script tag:

```html
<script id="ZwSg9rf6GA" src="https://yourdomain.com/js/script.js" data-host="https://yourdomain.com" data-key="your-domain-key" async></script>
```

### Minimizing Impact on Page Speed

1. The tracking script is already minified for optimal loading speed.
2. Events are sent asynchronously and don't block the main thread.
3. The script includes automatic throttling for high-frequency events.

## Troubleshooting

### Common Issues

1. **Tracking Not Working**:
   - Check that the script is correctly installed in the `<head>` section
   - Verify the `data-host` attribute points to your phpAnalytics installation
   - Check browser console for any JavaScript errors

2. **Ad Blockers Interfering**:
   - Consider implementing the server-side webhook tracking as a fallback

3. **Data Not Showing in Dashboard**:
   - Verify that your website domain is correctly configured in phpAnalytics
   - If key restriction is enabled, ensure the `data-key` attribute matches your domain key

### Debugging Events

Add this code to see tracking events in the console:

```javascript
// Add this after the tracking script
<script>
(function() {
    var originalTrack = window.pa.track;
    window.pa.track = function(event, referrer, options) {
        console.log('Tracking:', { event, referrer, options });
        return originalTrack.apply(this, arguments);
    };
    
    var originalWebhook = window.pa.webhook;
    window.pa.webhook = function(event, options) {
        console.log('Webhook:', { event, options });
        return originalWebhook.apply(this, arguments);
    };
})();
</script>
```

## Privacy Considerations

### Respecting Do Not Track

The tracking script automatically respects the browser's Do Not Track setting when `data-dnt="true"` is included:

```html
<script id="ZwSg9rf6GA" src="https://yourdomain.com/js/script.js" data-host="https://yourdomain.com" data-dnt="true" async></script>
```

### GDPR and CCPA Compliance

To comply with privacy regulations:

1. Add a cookie consent banner to your site
2. Only load the tracking script after obtaining consent:

```javascript
function initializeTracking() {
    // Create and append the tracking script only after consent
    var script = document.createElement('script');
    script.id = 'ZwSg9rf6GA';
    script.src = 'https://yourdomain.com/js/script.js';
    script.setAttribute('data-host', 'https://yourdomain.com');
    script.setAttribute('data-key', 'your-domain-key');
    script.setAttribute('data-dnt', 'true');
    script.async = true;
    document.head.appendChild(script);
}

// Call this function after user provides consent
document.getElementById('accept-cookies').addEventListener('click', function() {
    initializeTracking();
    // Save consent in a cookie or local storage
    localStorage.setItem('analytics_consent', 'granted');
});

// Check for existing consent on page load
if (localStorage.getItem('analytics_consent') === 'granted') {
    initializeTracking();
}
```

## Further Resources

- [Client-Side JavaScript Tracking API Documentation](client-side-js-tracking.md)
- [Server-Side Webhook Tracking Documentation](server-side-webhook-tracking.md)
- [Interactive API Example](js-api-example.html)