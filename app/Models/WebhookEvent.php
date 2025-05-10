<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WebhookEvent extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'website_id', 'processor', 'event_type', 'event_id', 'payload', 
        'headers', 'is_valid_signature', 'ip_address', 'status', 
        'error_message', 'processing_time'
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'is_valid_signature' => 'boolean',
        'payload' => 'json',
        'headers' => 'json',
        'processing_time' => 'integer',
    ];

    /**
     * Get the website associated with the webhook event.
     */
    public function website()
    {
        return $this->belongsTo(Website::class);
    }

    /**
     * Scope for valid signatures.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeValidSignature($query)
    {
        return $query->where('is_valid_signature', true);
    }

    /**
     * Scope for processed status.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeProcessed($query)
    {
        return $query->where('status', 'processed');
    }

    /**
     * Scope for failed status.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    /**
     * Get formatted payload for display.
     *
     * @return string
     */
    public function getFormattedPayloadAttribute()
    {
        if ($this->payload) {
            return json_encode($this->payload, JSON_PRETTY_PRINT);
        }
        
        return null;
    }
}
