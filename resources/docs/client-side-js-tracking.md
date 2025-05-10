# Client-Side JavaScript Tracking API

## Overview

The Client-Side JavaScript Tracking API provides an easy way to track both pageviews and custom events in your web applications. This API is automatically included when you add the phpAnalytics tracking code to your website, and it gives you more control over what data to track and when to track it.

## Global Object

The tracking script creates a global object `pa` that you can use to interact with the phpAnalytics tracking system:

```javascript
window.pa
```

## Tracking Methods

### Basic Event Tracking

```javascript
window.pa.track(eventObject, referrer, options);
```

#### Parameters:

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| eventObject | Object | No | Event tracking information |
| referrer | String | No | The referring URL (automatically set if not provided) |
| options | Object | No | Additional options for the tracking request |

#### Example - Track a Purchase Event:

```javascript
window.pa.track({
    name: 'purchase',
    value: 99.95,
    unit: 'USD'
});
```

#### Example - Track with Additional Options:

```javascript
window.pa.track({
    name: 'download',
    value: 1,
    unit: 'file'
}, null, {
    product_id: '12345',
    category: 'documents'
});
```

### Webhook Tracking (Server-Compatible API)

In addition to the standard tracking method, the enhanced API provides a webhook-compatible method that uses the same format as the server-side webhook API:

```javascript
window.pa.webhook(eventObject, options);
```

#### Parameters:

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| eventObject | Object | No | Event tracking information |
| options | Object | No | Additional options for the tracking request |

#### Example - Track a Purchase Event via Webhook API:

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

## Promise-Like Response Handling

Both tracking methods return a promise-like object with `then` and `catch` methods that you can use to handle the response:

```javascript
window.pa.track({
    name: 'signup',
    value: 1
})
.then(function(response) {
    console.log('Tracking successful:', response.status);
})
.catch(function(error) {
    console.error('Tracking failed:', error);
});
```

## Common Use Cases

### Track a Simple Event

```javascript
// Track a form submission
document.getElementById('contactForm').addEventListener('submit', function() {
    window.pa.track({
        name: 'form_submit',
        value: 1,
        unit: 'contact'
    });
});
```

### Track E-commerce Purchases

```javascript
// After a successful purchase
function onPurchaseComplete(orderData) {
    window.pa.track({
        name: 'purchase',
        value: orderData.total,
        unit: orderData.currency
    }, null, {
        order_id: orderData.id,
        products: orderData.items.length
    });
}
```

### Track with the Webhook-Compatible API

```javascript
// Track a user sign-up
window.pa.webhook({
    name: 'user_signup',
    value: 1
}, {
    user_type: 'free',
    referral_source: 'blog'
})
.then(function(response) {
    if (response.status === 200) {
        console.log('Signup tracking successful');
    }
});
```

### Track Page Time

```javascript
// Track how long a user stays on a page
var startTime = Date.now();

window.addEventListener('beforeunload', function() {
    var timeSpent = Math.round((Date.now() - startTime) / 1000);
    
    window.pa.track({
        name: 'page_time',
        value: timeSpent,
        unit: 'seconds'
    });
});
```

## Best Practices

1. **Use descriptive event names**: Choose clear and consistent event names that make your analytics data easy to understand.

2. **Be selective**: Don't track everything; focus on the most important user actions and business metrics.

3. **Add meaningful values**: When appropriate, include numeric values to quantify the events (e.g., purchase amounts, time spent, quantity).

4. **Group related events**: Use consistent naming patterns to group related events (e.g., `product_view`, `product_add_to_cart`, `product_purchase`).

5. **Handle errors gracefully**: Use the `.catch()` method to handle potential tracking failures without disrupting the user experience.

6. **Respect user privacy**: Be transparent about what you're tracking and respect Do Not Track settings (handled automatically by the tracking script).

## Event Naming Conventions

For consistency in your analytics data, we recommend following these naming conventions:

- Use lowercase characters
- Use underscores instead of spaces
- Use specific, descriptive names
- Be consistent with event categories

Examples of good event names:
- `page_view`
- `button_click`
- `form_submit` 
- `purchase_complete`
- `video_play`
- `video_complete`

## Integration with Common Frameworks

### React

```javascript
import React, { useEffect } from 'react';

function ProductPage({ product }) {
    useEffect(() => {
        // Track product view when component mounts
        window.pa.track({
            name: 'product_view',
            value: product.price,
            unit: 'USD'
        }, null, {
            product_id: product.id,
            category: product.category
        });
        
        // Clean up function not needed for tracking
    }, [product]); // Retrigger when product changes
    
    return (
        // Your component JSX
    );
}
```

### Vue.js

```javascript
export default {
    name: 'ProductComponent',
    props: ['product'],
    mounted() {
        // Track when component is mounted
        window.pa.track({
            name: 'product_view',
            value: this.product.price,
            unit: 'USD'
        }, null, {
            product_id: this.product.id,
            category: this.product.category
        });
    },
    methods: {
        addToCart() {
            // Track add to cart action
            window.pa.track({
                name: 'add_to_cart',
                value: this.product.price,
                unit: 'USD'
            }, null, {
                product_id: this.product.id
            });
            
            // Rest of your method logic
        }
    }
}
```

### Angular

```typescript
import { Component, OnInit } from '@angular/core';

declare global {
    interface Window {
        pa: any;
    }
}

@Component({
    selector: 'app-product',
    templateUrl: './product.component.html'
})
export class ProductComponent implements OnInit {
    @Input() product: any;
    
    ngOnInit() {
        // Track when component initializes
        window.pa.track({
            name: 'product_view',
            value: this.product.price,
            unit: 'USD'
        }, null, {
            product_id: this.product.id,
            category: this.product.category
        });
    }
    
    addToCart() {
        // Track add to cart action
        window.pa.track({
            name: 'add_to_cart',
            value: this.product.price,
            unit: 'USD'
        }, null, {
            product_id: this.product.id
        });
        
        // Rest of your method logic
    }
}
```

## Debugging

To debug tracking events in development, you can use the browser's network inspector to see the requests being sent to the phpAnalytics server. Look for requests to `/api/event` or `/api/webhook` endpoints.

## Advanced Usage

### Custom Data Attributes

You can add custom data attributes to your tracking code to include additional information with every tracking request:

```html
<script id="ZwSg9rf6GA" 
        src="/js/script.js" 
        data-host="https://analytics.example.com" 
        data-key="your-domain-key" 
        data-dnt="true" 
        data-custom-user-type="premium">
</script>
```

Then, in your JavaScript:

```javascript
var trackingCode = document.getElementById('ZwSg9rf6GA');
var userType = trackingCode.getAttribute('data-custom-user-type');

window.pa.track({
    name: 'subscription_renewal',
    value: 19.99,
    unit: 'USD'
}, null, {
    user_type: userType  // Will include "premium" from the data attribute
});
```

### Batch Tracking

For high-volume tracking needs, you can batch multiple events and send them together:

```javascript
// Create a batch tracking helper
function batchTrackEvents(events, options) {
    return window.pa.webhook(null, Object.assign({}, options, {
        batch_events: events
    }));
}

// Example usage
var events = [
    { name: 'page_view', page: '/products' },
    { name: 'search', value: 1, metadata: { query: 'shoes' } },
    { name: 'filter_apply', metadata: { category: 'footwear', color: 'blue' } }
];

batchTrackEvents(events, { session_id: 'user123' })
    .then(function(response) {
        console.log('Batch tracking complete');
    });
```

## Comparison with Server-Side API

| Feature | Client-Side API | Server-Side API |
|---------|----------------|-----------------|
| Ease of implementation | ✅ Simple script tag | ⚠️ Requires server code |
| Ad blocker vulnerability | ❌ Can be blocked | ✅ Not affected |
| User privacy (DNT) | ✅ Built-in support | ❌ Manual implementation |
| Data reliability | ⚠️ Client-dependent | ✅ More reliable |
| Real-time user behavior | ✅ Automatic | ⚠️ Limited |
| Sensitive data handling | ❌ Less secure | ✅ More secure |

For the most comprehensive analytics coverage, consider using both client-side and server-side tracking together.