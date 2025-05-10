# Server-Side Webhook Tracking Examples

This document provides examples of how to use the phpAnalytics webhook API from various server-side languages and frameworks.

## PHP Examples

### Basic PHP with cURL

```php
<?php
/**
 * Basic PHP Example - Track a Pageview
 */
function trackPageview($domain, $page, $domainKey = null) {
    $ch = curl_init('https://yourdomain.com/api/webhook');
    
    $data = [
        'domain' => $domain,
        'page' => $page,
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
        'language' => substr($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '', 0, 2),
        'referrer' => $_SERVER['HTTP_REFERER'] ?? null
    ];
    
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    
    // Add domain key header if provided
    if ($domainKey) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'X-Domain-Key: ' . $domainKey
        ]);
    }
    
    $response = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return [
        'status' => $status,
        'response' => json_decode($response, true)
    ];
}

/**
 * Basic PHP Example - Track an Event
 */
function trackEvent($domain, $page, $eventName, $eventValue = null, $eventUnit = null, $domainKey = null) {
    $ch = curl_init('https://yourdomain.com/api/webhook');
    
    $data = [
        'domain' => $domain,
        'page' => $page,
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
        'language' => substr($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '', 0, 2),
        'event' => [
            'name' => $eventName
        ]
    ];
    
    // Add event value and unit if provided
    if ($eventValue !== null) {
        $data['event']['value'] = $eventValue;
    }
    
    if ($eventUnit !== null) {
        $data['event']['unit'] = $eventUnit;
    }
    
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    
    // Add domain key header if provided
    if ($domainKey) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'X-Domain-Key: ' . $domainKey
        ]);
    }
    
    $response = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return [
        'status' => $status,
        'response' => json_decode($response, true)
    ];
}

// Example usage:
// trackPageview('example.com', 'https://example.com/products/123', 'your-domain-key');
// trackEvent('example.com', 'https://example.com/checkout', 'purchase', 99.95, 'USD', 'your-domain-key');
```

### Laravel Example

```php
<?php
// Laravel Example - Using HTTP Client

namespace App\Services;

use Illuminate\Support\Facades\Http;

class AnalyticsService
{
    private $baseUrl;
    private $domain;
    private $domainKey;
    
    public function __construct($domain, $domainKey = null)
    {
        $this->baseUrl = config('services.analytics.url', 'https://yourdomain.com/api/webhook');
        $this->domain = $domain;
        $this->domainKey = $domainKey;
    }
    
    /**
     * Track a pageview
     */
    public function trackPageview($page, $data = [])
    {
        return $this->sendRequest(array_merge([
            'domain' => $this->domain,
            'page' => $page,
        ], $data));
    }
    
    /**
     * Track an event
     */
    public function trackEvent($page, $eventName, $eventValue = null, $eventUnit = null, $data = [])
    {
        $event = ['name' => $eventName];
        
        if ($eventValue !== null) {
            $event['value'] = $eventValue;
        }
        
        if ($eventUnit !== null) {
            $event['unit'] = $eventUnit;
        }
        
        return $this->sendRequest(array_merge([
            'domain' => $this->domain,
            'page' => $page,
            'event' => $event
        ], $data));
    }
    
    /**
     * Send the tracking request
     */
    private function sendRequest($data)
    {
        $request = Http::asForm();
        
        if ($this->domainKey) {
            $request = $request->withHeaders([
                'X-Domain-Key' => $this->domainKey
            ]);
        }
        
        try {
            $response = $request->post($this->baseUrl, $data);
            return $response->successful();
        } catch (\Exception $e) {
            // Log the error, but don't disrupt the application flow
            \Log::error('Analytics tracking failed: ' . $e->getMessage());
            return false;
        }
    }
}

// In your controller:
// $analytics = new AnalyticsService('example.com', 'your-domain-key');
// $analytics->trackPageview('https://example.com/products/123', [
//     'ip' => $request->ip(),
//     'user_agent' => $request->userAgent(),
//     'language' => $request->getPreferredLanguage(),
//     'referrer' => $request->header('referer')
// ]);
```

## Node.js Examples

### Node.js with Axios

```javascript
// Node.js Example - Using Axios

const axios = require('axios');

class AnalyticsTracker {
  constructor(options) {
    this.baseUrl = options.baseUrl || 'https://yourdomain.com/api/webhook';
    this.domain = options.domain;
    this.domainKey = options.domainKey;
  }
  
  /**
   * Track a pageview
   */
  async trackPageview(req, page) {
    const data = {
      domain: this.domain,
      page: page,
      ip: req.ip,
      user_agent: req.headers['user-agent'],
      language: req.headers['accept-language']?.substring(0, 2),
      referrer: req.headers.referer
    };
    
    return this.sendRequest(data);
  }
  
  /**
   * Track an event
   */
  async trackEvent(req, page, eventName, eventValue = null, eventUnit = null) {
    const event = { name: eventName };
    
    if (eventValue !== null) {
      event.value = eventValue;
    }
    
    if (eventUnit !== null) {
      event.unit = eventUnit;
    }
    
    const data = {
      domain: this.domain,
      page: page,
      ip: req.ip,
      user_agent: req.headers['user-agent'],
      language: req.headers['accept-language']?.substring(0, 2),
      event: event
    };
    
    return this.sendRequest(data);
  }
  
  /**
   * Send the tracking request
   */
  async sendRequest(data) {
    try {
      const headers = {};
      
      if (this.domainKey) {
        headers['X-Domain-Key'] = this.domainKey;
      }
      
      const response = await axios.post(this.baseUrl, data, { headers });
      return response.status === 200;
    } catch (error) {
      // Log the error, but don't disrupt the application flow
      console.error('Analytics tracking failed:', error.message);
      return false;
    }
  }
}

// Example usage with Express:
// const tracker = new AnalyticsTracker({
//   domain: 'example.com',
//   domainKey: 'your-domain-key'
// });
// 
// app.get('/product/:id', async (req, res) => {
//   const product = await getProduct(req.params.id);
//   
//   // Track the pageview
//   await tracker.trackPageview(req, `https://example.com/product/${req.params.id}`);
//   
//   res.render('product', { product });
// });
```

### Express.js Middleware Example

```javascript
// Express.js Middleware for Automatic Tracking

const axios = require('axios');

/**
 * Create analytics tracking middleware
 */
function createAnalyticsMiddleware(options) {
  const domain = options.domain;
  const domainKey = options.domainKey;
  const analyticsUrl = options.url || 'https://yourdomain.com/api/webhook';
  const excludePaths = options.excludePaths || ['/health', '/api', '/static', '/assets'];
  
  return async function analyticsMiddleware(req, res, next) {
    // Only track GET requests
    if (req.method !== 'GET') {
      return next();
    }
    
    // Skip excluded paths
    for (const path of excludePaths) {
      if (req.path.startsWith(path)) {
        return next();
      }
    }
    
    // Prepare tracking data
    const trackingData = {
      domain: domain,
      page: `${options.protocol || 'https'}://${domain}${req.originalUrl}`,
      ip: req.ip,
      user_agent: req.headers['user-agent'],
      language: req.headers['accept-language']?.substring(0, 2),
      referrer: req.headers.referer
    };
    
    // Track the pageview in the background without waiting for the response
    const headers = domainKey ? { 'X-Domain-Key': domainKey } : {};
    
    axios.post(analyticsUrl, trackingData, { headers })
      .catch(error => {
        console.error('Analytics tracking failed:', error.message);
      });
    
    // Continue with the request
    next();
  };
}

// Usage in Express app:
// const analyticsMiddleware = createAnalyticsMiddleware({
//   domain: 'example.com',
//   domainKey: 'your-domain-key',
//   protocol: 'https'
// });
// 
// app.use(analyticsMiddleware);
```

## Python Examples

### Python with Requests

```python
# Python Example - Using Requests

import requests

class AnalyticsTracker:
    def __init__(self, base_url, domain, domain_key=None):
        self.base_url = base_url
        self.domain = domain
        self.domain_key = domain_key
    
    def track_pageview(self, page, ip=None, user_agent=None, language=None, referrer=None):
        """Track a pageview"""
        data = {
            'domain': self.domain,
            'page': page,
        }
        
        # Add optional data if provided
        if ip:
            data['ip'] = ip
        if user_agent:
            data['user_agent'] = user_agent
        if language:
            data['language'] = language[:2] if language else None
        if referrer:
            data['referrer'] = referrer
        
        return self._send_request(data)
    
    def track_event(self, page, event_name, event_value=None, event_unit=None, 
                   ip=None, user_agent=None, language=None):
        """Track an event"""
        event = {'name': event_name}
        
        if event_value is not None:
            event['value'] = event_value
        
        if event_unit is not None:
            event['unit'] = event_unit
        
        data = {
            'domain': self.domain,
            'page': page,
            'event': event
        }
        
        # Add optional data if provided
        if ip:
            data['ip'] = ip
        if user_agent:
            data['user_agent'] = user_agent
        if language:
            data['language'] = language[:2] if language else None
        
        return self._send_request(data)
    
    def _send_request(self, data):
        """Send the tracking request"""
        headers = {}
        
        if self.domain_key:
            headers['X-Domain-Key'] = self.domain_key
        
        try:
            response = requests.post(self.base_url, json=data, headers=headers)
            return response.status_code == 200
        except Exception as e:
            # Log the error, but don't disrupt the application flow
            print(f"Analytics tracking failed: {e}")
            return False

# Example usage
# tracker = AnalyticsTracker(
#     base_url='https://yourdomain.com/api/webhook',
#     domain='example.com',
#     domain_key='your-domain-key'
# )
# 
# # Track a pageview
# tracker.track_pageview(
#     page='https://example.com/products/123',
#     ip=request.remote_addr,
#     user_agent=request.user_agent.string,
#     language=request.accept_languages.best,
#     referrer=request.referrer
# )
```

### Django Middleware Example

```python
# Django Middleware Example

import requests
from django.conf import settings

class AnalyticsMiddleware:
    def __init__(self, get_response):
        self.get_response = get_response
        self.analytics_url = getattr(settings, 'ANALYTICS_URL', 'https://yourdomain.com/api/webhook')
        self.domain = getattr(settings, 'ANALYTICS_DOMAIN', 'example.com')
        self.domain_key = getattr(settings, 'ANALYTICS_DOMAIN_KEY', None)
        self.exclude_paths = getattr(settings, 'ANALYTICS_EXCLUDE_PATHS', ['/admin/', '/static/', '/media/'])
    
    def __call__(self, request):
        # Process the request first
        response = self.get_response(request)
        
        # Only track GET requests
        if request.method != 'GET':
            return response
        
        # Skip excluded paths
        path = request.path_info
        if any(path.startswith(exclude) for exclude in self.exclude_paths):
            return response
        
        # Skip AJAX requests
        if request.headers.get('X-Requested-With') == 'XMLHttpRequest':
            return response
        
        # Track the pageview in the background
        self.track_pageview(request)
        
        return response
    
    def track_pageview(self, request):
        """Track a pageview in the background"""
        try:
            protocol = 'https' if request.is_secure() else 'http'
            domain = self.domain
            path = request.get_full_path()
            
            data = {
                'domain': domain,
                'page': f"{protocol}://{domain}{path}",
                'ip': self.get_client_ip(request),
                'user_agent': request.META.get('HTTP_USER_AGENT', ''),
                'language': request.META.get('HTTP_ACCEPT_LANGUAGE', '')[:2],
                'referrer': request.META.get('HTTP_REFERER', '')
            }
            
            headers = {}
            if self.domain_key:
                headers['X-Domain-Key'] = self.domain_key
            
            # Send the request asynchronously to avoid blocking the response
            # In a production environment, consider using a task queue like Celery
            import threading
            thread = threading.Thread(target=self._send_request, args=(data, headers))
            thread.daemon = True
            thread.start()
        
        except Exception as e:
            # Log the error but don't disrupt the application flow
            import logging
            logging.error(f"Analytics tracking failed: {e}")
    
    def _send_request(self, data, headers):
        """Send the tracking request"""
        try:
            requests.post(self.analytics_url, json=data, headers=headers, timeout=2)
        except Exception as e:
            import logging
            logging.error(f"Analytics tracking request failed: {e}")
    
    def get_client_ip(self, request):
        """Get the client IP address"""
        x_forwarded_for = request.META.get('HTTP_X_FORWARDED_FOR')
        if x_forwarded_for:
            ip = x_forwarded_for.split(',')[0].strip()
        else:
            ip = request.META.get('REMOTE_ADDR', '')
        return ip

# Usage in Django settings.py
# MIDDLEWARE = [
#     # ...other middleware
#     'path.to.AnalyticsMiddleware',
# ]
# 
# ANALYTICS_URL = 'https://yourdomain.com/api/webhook'
# ANALYTICS_DOMAIN = 'example.com'
# ANALYTICS_DOMAIN_KEY = 'your-domain-key'
# ANALYTICS_EXCLUDE_PATHS = ['/admin/', '/static/', '/media/', '/api/']
```

## Ruby Examples

### Ruby with Net::HTTP

```ruby
# Ruby Example - Using Net::HTTP

require 'net/http'
require 'uri'
require 'json'

class AnalyticsTracker
  def initialize(options = {})
    @base_url = options[:base_url] || 'https://yourdomain.com/api/webhook'
    @domain = options[:domain]
    @domain_key = options[:domain_key]
  end
  
  # Track a pageview
  def track_pageview(page, options = {})
    data = {
      domain: @domain,
      page: page,
      ip: options[:ip],
      user_agent: options[:user_agent],
      language: options[:language]&.slice(0, 2),
      referrer: options[:referrer]
    }.compact
    
    send_request(data)
  end
  
  # Track an event
  def track_event(page, event_name, event_value = nil, event_unit = nil, options = {})
    event = { name: event_name }
    event[:value] = event_value if event_value
    event[:unit] = event_unit if event_unit
    
    data = {
      domain: @domain,
      page: page,
      event: event,
      ip: options[:ip],
      user_agent: options[:user_agent],
      language: options[:language]&.slice(0, 2),
    }.compact
    
    send_request(data)
  end
  
  private
  
  # Send the tracking request
  def send_request(data)
    uri = URI.parse(@base_url)
    
    begin
      http = Net::HTTP.new(uri.host, uri.port)
      http.use_ssl = uri.scheme == 'https'
      
      request = Net::HTTP::Post.new(uri.path)
      request.body = data.to_json
      request.content_type = 'application/json'
      
      # Add domain key if provided
      request['X-Domain-Key'] = @domain_key if @domain_key
      
      response = http.request(request)
      return response.code == '200'
    rescue => e
      # Log the error but don't disrupt the application flow
      puts "Analytics tracking failed: #{e.message}"
      return false
    end
  end
end

# Example usage
# tracker = AnalyticsTracker.new(
#   domain: 'example.com',
#   domain_key: 'your-domain-key'
# )
# 
# # Track a pageview
# tracker.track_pageview(
#   'https://example.com/products/123',
#   ip: request.remote_ip,
#   user_agent: request.user_agent,
#   language: request.headers['Accept-Language'],
#   referrer: request.referer
# )
```

### Rails Middleware Example

```ruby
# Rails Middleware Example

# lib/analytics_middleware.rb
class AnalyticsMiddleware
  def initialize(app)
    @app = app
  end
  
  def call(env)
    # Process the request first
    status, headers, response = @app.call(env)
    
    # Track the pageview in the background if it's a GET request
    if env['REQUEST_METHOD'] == 'GET'
      request = ActionDispatch::Request.new(env)
      path = request.fullpath
      
      # Skip excluded paths
      excluded_paths = ['/admin', '/assets', '/packs', '/rails']
      unless excluded_paths.any? { |excluded| path.start_with?(excluded) }
        track_pageview(request)
      end
    end
    
    [status, headers, response]
  end
  
  private
  
  def track_pageview(request)
    # Track in a background thread to avoid blocking the response
    Thread.new do
      begin
        domain = Rails.configuration.analytics[:domain]
        full_path = "#{request.protocol}#{domain}#{request.fullpath}"
        
        data = {
          domain: domain,
          page: full_path,
          ip: request.remote_ip,
          user_agent: request.user_agent,
          language: request.headers['Accept-Language']&.slice(0, 2),
          referrer: request.referer
        }.compact
        
        uri = URI.parse(Rails.configuration.analytics[:url])
        http = Net::HTTP.new(uri.host, uri.port)
        http.use_ssl = uri.scheme == 'https'
        
        request = Net::HTTP::Post.new(uri.path)
        request.body = data.to_json
        request.content_type = 'application/json'
        
        # Add domain key if configured
        domain_key = Rails.configuration.analytics[:domain_key]
        request['X-Domain-Key'] = domain_key if domain_key
        
        http.request(request)
      rescue => e
        Rails.logger.error("Analytics tracking failed: #{e.message}")
      ensure
        # Ensure the thread terminates
        Thread.exit
      end
    end
  end
end

# In config/application.rb
# config.middleware.use AnalyticsMiddleware
# 
# # In config/environments/production.rb
# config.analytics = {
#   url: 'https://yourdomain.com/api/webhook',
#   domain: 'example.com',
#   domain_key: 'your-domain-key'
# }
```

## Go Examples

```go
// Go Example - Using net/http

package analytics

import (
	"bytes"
	"encoding/json"
	"log"
	"net/http"
	"strings"
	"time"
)

// AnalyticsTracker handles tracking pageviews and events
type AnalyticsTracker struct {
	BaseURL   string
	Domain    string
	DomainKey string
	Client    *http.Client
}

// PageviewData contains data for tracking pageviews
type PageviewData struct {
	Domain    string `json:"domain"`
	Page      string `json:"page"`
	IP        string `json:"ip,omitempty"`
	UserAgent string `json:"user_agent,omitempty"`
	Language  string `json:"language,omitempty"`
	Referrer  string `json:"referrer,omitempty"`
}

// EventData contains data for tracking events
type EventData struct {
	Domain    string      `json:"domain"`
	Page      string      `json:"page"`
	Event     EventDetail `json:"event"`
	IP        string      `json:"ip,omitempty"`
	UserAgent string      `json:"user_agent,omitempty"`
	Language  string      `json:"language,omitempty"`
}

// EventDetail contains details about an event
type EventDetail struct {
	Name  string  `json:"name"`
	Value float64 `json:"value,omitempty"`
	Unit  string  `json:"unit,omitempty"`
}

// NewTracker creates a new analytics tracker
func NewTracker(baseURL, domain, domainKey string) *AnalyticsTracker {
	return &AnalyticsTracker{
		BaseURL:   baseURL,
		Domain:    domain,
		DomainKey: domainKey,
		Client: &http.Client{
			Timeout: 5 * time.Second,
		},
	}
}

// TrackPageview tracks a pageview
func (t *AnalyticsTracker) TrackPageview(page, ip, userAgent, language, referrer string) bool {
	data := PageviewData{
		Domain:    t.Domain,
		Page:      page,
		IP:        ip,
		UserAgent: userAgent,
		Language:  truncateString(language, 2),
		Referrer:  referrer,
	}
	
	return t.sendRequest(data)
}

// TrackEvent tracks an event
func (t *AnalyticsTracker) TrackEvent(page string, eventName string, eventValue *float64, eventUnit string, ip, userAgent, language string) bool {
	event := EventDetail{
		Name: eventName,
	}
	
	if eventValue != nil {
		event.Value = *eventValue
	}
	
	if eventUnit != "" {
		event.Unit = eventUnit
	}
	
	data := EventData{
		Domain:    t.Domain,
		Page:      page,
		Event:     event,
		IP:        ip,
		UserAgent: userAgent,
		Language:  truncateString(language, 2),
	}
	
	return t.sendRequest(data)
}

// sendRequest sends the tracking request
func (t *AnalyticsTracker) sendRequest(data interface{}) bool {
	jsonData, err := json.Marshal(data)
	if err != nil {
		log.Printf("Analytics tracking failed: %v", err)
		return false
	}
	
	req, err := http.NewRequest("POST", t.BaseURL, bytes.NewBuffer(jsonData))
	if err != nil {
		log.Printf("Analytics tracking failed: %v", err)
		return false
	}
	
	req.Header.Set("Content-Type", "application/json")
	
	if t.DomainKey != "" {
		req.Header.Set("X-Domain-Key", t.DomainKey)
	}
	
	resp, err := t.Client.Do(req)
	if err != nil {
		log.Printf("Analytics tracking failed: %v", err)
		return false
	}
	defer resp.Body.Close()
	
	return resp.StatusCode == 200
}

// truncateString truncates a string to the specified length
func truncateString(s string, maxLen int) string {
	if len(s) <= maxLen {
		return s
	}
	return s[:maxLen]
}

// Middleware for HTTP handlers
func (t *AnalyticsTracker) Middleware(next http.Handler) http.Handler {
	return http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		// Process the request first
		next.ServeHTTP(w, r)
		
		// Only track GET requests
		if r.Method != "GET" {
			return
		}
		
		// Skip excluded paths
		excludedPaths := []string{"/admin", "/static", "/assets"}
		for _, path := range excludedPaths {
			if strings.HasPrefix(r.URL.Path, path) {
				return
			}
		}
		
		// Track the pageview in a goroutine
		go func() {
			defer func() {
				if r := recover(); r != nil {
					log.Printf("Recovered in analytics tracking: %v", r)
				}
			}()
			
			protocol := "https"
			if r.TLS == nil {
				protocol = "http"
			}
			
			page := protocol + "://" + t.Domain + r.URL.String()
			ip := r.RemoteAddr
			if forwardedIP := r.Header.Get("X-Forwarded-For"); forwardedIP != "" {
				ip = strings.Split(forwardedIP, ",")[0]
			}
			
			t.TrackPageview(
				page,
				ip,
				r.UserAgent(),
				r.Header.Get("Accept-Language"),
				r.Referer(),
			)
		}()
	})
}

/* 
// Example usage:
package main

import (
	"net/http"
	"your-package/analytics"
)

func main() {
	tracker := analytics.NewTracker(
		"https://yourdomain.com/api/webhook",
		"example.com",
		"your-domain-key",
	)
	
	// Use as middleware
	mux := http.NewServeMux()
	mux.HandleFunc("/", homeHandler)
	
	// Add the analytics middleware
	handler := tracker.Middleware(mux)
	
	http.ListenAndServe(":8080", handler)
}

func homeHandler(w http.ResponseWriter, r *http.Request) {
	// Your handler logic
	w.Write([]byte("Hello, World!"))
}
*/
```

## Important Notes

1. These examples are provided as a starting point. You should adapt them to fit your specific application requirements.

2. For high-traffic applications, consider implementing rate limiting or batching on your side to avoid overwhelming the tracking API.

3. In production environments, it's recommended to use background processing (job queues, worker threads, etc.) to handle tracking requests asynchronously.

4. Always make sure to handle errors gracefully to prevent tracking issues from affecting your main application functionality.

5. Keep your domain key secure when key restriction is enabled.

6. Consider implementing retry logic for failed requests, especially for important tracking events.
