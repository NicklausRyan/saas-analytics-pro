<?php

namespace App\Traits;

use App\Models\WebhookEvent;
use App\Models\Website;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

trait WebhookLogTrait
{
    /**
     * Log a webhook event.
     *
     * @param Request $request The incoming request
     * @param string $processor The payment processor name (stripe, paypal, etc.)
     * @param string $eventType The type of event
     * @param string|null $eventId The ID of the event from the provider
     * @param bool $isValidSignature Whether the signature was valid
     * @param string $status Status of processing (processed, failed)
     * @param string|null $errorMessage Any error message if failed
     * @param int|null $websiteId Associated website ID if applicable
     * @param int|null $processingTime Processing time in milliseconds
     * @return WebhookEvent
     */
    protected function logWebhookEvent(
        Request $request, 
        string $processor, 
        string $eventType,
        ?string $eventId = null,
        bool $isValidSignature = true,
        string $status = 'processed',
        ?string $errorMessage = null,
        ?int $websiteId = null,
        ?int $processingTime = null
    ) {
        // Capture headers
        $headers = [];
        foreach ($request->headers->all() as $key => $value) {
            // Filter out sensitive headers like authorization if needed
            if (!in_array(strtolower($key), ['authorization', 'cookie'])) {
                $headers[$key] = $value;
            }
        }

        // Create webhook event log
        try {
            return WebhookEvent::create([
                'website_id' => $websiteId,
                'processor' => $processor,
                'event_type' => $eventType,
                'event_id' => $eventId,
                'payload' => $request->getContent() ? json_decode($request->getContent()) : null,
                'headers' => $headers,
                'is_valid_signature' => $isValidSignature,
                'ip_address' => $request->ip(),
                'status' => $status,
                'error_message' => $errorMessage,
                'processing_time' => $processingTime
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to log webhook event: ' . $e->getMessage());
            // If logging fails, we don't want to disrupt the webhook processing
            return null;
        }
    }

    /**
     * Find the website ID from webhook payload data.
     *
     * @param mixed $metadata The metadata from webhook payload
     * @return int|null The website ID if found, null otherwise
     */
    protected function findWebsiteIdFromMetadata($metadata)
    {
        if (isset($metadata->website_id)) {
            return $metadata->website_id;
        }
        
        if (isset($metadata['website_id'])) {
            return $metadata['website_id'];
        }

        // If stripe_api_key is provided in metadata, try to find website by API key
        if (isset($metadata->stripe_api_key)) {
            $website = Website::where('stripe_api_key', $metadata->stripe_api_key)->first();
            if ($website) {
                return $website->id;
            }
        }

        if (isset($metadata['stripe_api_key'])) {
            $website = Website::where('stripe_api_key', $metadata['stripe_api_key'])->first();
            if ($website) {
                return $website->id;
            }
        }

        // If domain is provided, try to find website by domain
        if (isset($metadata->domain)) {
            $website = Website::where('domain', $metadata->domain)->first();
            if ($website) {
                return $website->id;
            }
        }

        if (isset($metadata['domain'])) {
            $website = Website::where('domain', $metadata['domain'])->first();
            if ($website) {
                return $website->id;
            }
        }

        return null;
    }
}
