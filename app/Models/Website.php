<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

/**
 * Class Website
 *
 * @mixin Builder
 * @package App
 */
class Website extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'domain',
        'user_id',
        'privacy',
        'password',
        'email',
        'exclude_bots',
        'exclude_params',
        'exclude_ips',
        'tracking_code',
        'domain_key',
        'stripe_api_key'
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */    protected $hidden = [
        'password',
        'stripe_api_key',
    ];
      /* The stripe_api_key accessors and mutators are defined below */
    
    /**
     * @param Builder $query
     * @param $value
     * @return Builder
     */
    public function scopeSearchDomain(Builder $query, $value)
    {
        return $query->where('domain', 'like', '%' . $value . '%');
    }

    /**
     * Get the user that owns the website.
     */
    public function user()
    {
        return $this->belongsTo('App\Models\User')->withTrashed();
    }

    /**
     * @param Builder $query
     * @param $value
     * @return Builder
     */
    public function scopeOfUser(Builder $query, $value)
    {
        return $query->where('user_id', '=', $value);
    }

    /**
     * Get the visitors count for a specific date range.
     *
     * @return Model|\Illuminate\Database\Eloquent\Relations\HasMany|object|null
     */
    public function visitors()
    {
        return $this->hasMany('App\Models\Stat', 'website_id', 'id')
            ->where('name', '=', 'visitors');
    }

    /**
     * Get the pageviews count for a specific date range.
     *
     * @return Model|\Illuminate\Database\Eloquent\Relations\HasMany|object|null
     */
    public function pageviews()
    {
        return $this->hasMany('App\Models\Stat', 'website_id', 'id')
            ->where('name', '=', 'pageviews');
    }

    /**
     * Get the website's stats.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function stats()
    {
        return $this->hasMany('App\Models\Stat')->where('website_id', $this->id);
    }

    /**
     * Get the website's recent stats.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function recents()
    {
        return $this->hasMany('App\Models\Recent')->where('website_id', $this->id);
    }

    /**
     * Encrypt the website's password.
     *
     * @param $value
     */
    public function setPasswordAttribute($value)
    {
        $this->attributes['password'] = Crypt::encryptString($value);
    }

    /**
     * Decrypt the website's password.
     *
     * @param $value
     * @return string
     */
    public function getPasswordAttribute($value)
    {
        try {
            return Crypt::decryptString($value);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Encrypt the Stripe API key before storing.
     *
     * @param  string|null  $value
     * @return void
     */
    public function setStripeApiKeyAttribute($value)
    {
        if (!empty($value)) {
            $this->attributes['stripe_api_key'] = Crypt::encryptString($value);
        } elseif ($value === '') {
            // If an empty string is passed, remove the API key
            $this->attributes['stripe_api_key'] = null;
        }
    }

    /**
     * Decrypt the Stripe API key when accessed.
     *
     * @param  string|null  $value
     * @return string|null
     */
    public function getStripeApiKeyAttribute($value)
    {
        if (!empty($value)) {
            try {
                return Crypt::decryptString($value);
            } catch (\Exception $e) {
                return null;
            }
        }
        return null;
    }

    /**
     * Get the payments for this website.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function payments()
    {
        return $this->hasMany('App\Models\WebsitePayment');
    }
}
