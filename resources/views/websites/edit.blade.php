@section('site_title', formatTitle([__('Edit'), __('Website'), config('settings.title')]))

@include('shared.breadcrumbs', ['breadcrumbs' => [
    ['url' => request()->is('admin/*') ? route('admin.dashboard') : route('dashboard'), 'title' => request()->is('admin/*') ? __('Admin') : __('Home')],
    ['title' => __('Edit')],
]])

<div class="d-flex">
    <h1 class="h2 mb-3 text-break">{{ __('Edit') }}</h1>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-header align-items-center">
        <div class="row">
            <div class="col">
                <div class="font-weight-medium py-1">{{ __('Website') }}</div>
            </div>
            <div class="col-auto">
                <div class="form-row">
                    <div class="col">
                        @include('websites.partials.menu')
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="card-body">
        @include('shared.message')

        <form action="{{ request()->is('admin/*') ? route('admin.websites.edit', $website->id) : route('websites.edit', $website->id) }}" method="post" enctype="multipart/form-data" id="form-website">
            @csrf

            @if(request()->is('admin/*'))
                <input type="hidden" name="user_id" value="{{ $website->user->id }}">
            @endif            <div class="form-group">
                <label for="i-domain">{{ __('Domain') }}</label>
                <div class="input-group mb-3">
                    <div class="input-group-prepend">
                        <span class="input-group-text"><img src="https://icons.duckduckgo.com/ip3/{{ $website->domain }}.ico" rel="noreferrer" class="width-4 height-4"></span>
                    </div>
                    <input type="text" dir="ltr" name="domain" class="form-control{{ $errors->has('domain') ? ' is-invalid' : '' }}" id="i-domain" value="{{ $website->domain }}" placeholder="https://example.com" disabled>
                    @if ($errors->has('domain'))
                        <span class="invalid-feedback" role="alert">
                            <strong>{{ $errors->first('domain') }}</strong>
                        </span>
                    @endif
                </div>
            </div>            @if(config('settings.key_restriction') == 1)
            <div class="form-group">
                <label for="i-domain-key">{{ __('Domain Key') }}</label>
                <div class="input-group">
                    <input type="text" dir="ltr" id="i-domain-key" class="form-control" value="{{ $website->domain_key }}" readonly>
                    <div class="input-group-append">
                        <div class="btn btn-primary" data-tooltip-copy="true" title="{{ __('Copy') }}" data-text-copy="{{ __('Copy') }}" data-text-copied="{{ __('Copied') }}" data-clipboard="true" data-clipboard-target="#i-domain-key">{{ __('Copy') }}</div>
                    </div>
                </div>
                <small class="form-text text-muted">{{ __('Required for API calls when key restriction is enabled.') }}</small>
            </div>

            <div class="form-group">
                <div class="custom-control custom-checkbox">
                    <input type="checkbox" class="custom-control-input" name="regenerate_domain_key" id="i-regenerate-domain-key">
                    <label class="custom-control-label" for="i-regenerate-domain-key">{{ __('Regenerate domain key') }}</label>
                </div>
            </div>
            @endif
            
            <div class="form-group">
                <label for="i-stripe-key">{{ __('Stripe Restricted API Key') }} <span class="optional">({{ __('Optional') }})</span></label>
                <div class="input-group">
                    <input type="text" dir="ltr" name="stripe_key" class="form-control{{ $errors->has('stripe_key') ? ' is-invalid' : '' }}" id="i-stripe-key" value="{{ $website->stripe_key }}" placeholder="rk_live_...">
                    <div class="input-group-append">
                        <span class="input-group-text"><img src="{{ url('/') }}/images/icons/payments/stripe.svg" class="width-4 height-4"></span>
                    </div>
                </div>
                @if ($errors->has('stripe_key'))
                    <span class="invalid-feedback d-block" role="alert">
                        <strong>{{ $errors->first('stripe_key') }}</strong>
                    </span>
                @endif
                <small class="form-text text-muted">{{ __('Enter a Stripe restricted API key to be used for payment processing with this website.') }}</small>
            </div>            <div class="form-group">
                <label for="i-stripe-api-key">{{ __('Stripe Secret API Key') }} <span class="optional">({{ __('Optional') }})</span></label>
                <div class="input-group">
                    <input type="text" dir="ltr" name="stripe_api_key" class="form-control{{ $errors->has('stripe_api_key') ? ' is-invalid' : '' }}" id="i-stripe-api-key" value="{{ $website->stripe_api_key }}" placeholder="sk_live_...">
                    <div class="input-group-append">
                        <span class="input-group-text"><img src="{{ url('/') }}/images/icons/payments/stripe.svg" class="width-4 height-4"></span>
                    </div>
                </div>
                @if ($errors->has('stripe_api_key'))
                    <span class="invalid-feedback d-block" role="alert">
                        <strong>{{ $errors->first('stripe_api_key') }}</strong>
                    </span>
                @endif
                <small class="form-text text-muted">{{ __('Enter a Stripe secret API key that will be linked to this domain. When key restriction is enabled, this key will only work with this domain.') }}</small>
                
                @if(config('settings.key_restriction') == 1 && $website->stripe_api_key)
                <div class="alert alert-success mt-2 mb-0 d-flex align-items-center">
                    @include('icons.lock', ['class' => 'fill-current width-4 height-4 '.(__('lang_dir') == 'rtl' ? 'ml-2' : 'mr-2')])
                    <div>{{ __('This API key is restricted to the :domain domain.', ['domain' => $website->domain]) }}</div>
                </div>
                @endif
            </div>

            <hr>

            <div class="form-group">
                <label>{{ __('Privacy') }}</label>
                <div class="form-group mb-0">
                    <div class="row mx-n2">
                        <div class="col-12 col-lg-4 px-2">
                            <div class="custom-control custom-radio">
                                <input type="radio" id="i-privacy1" name="privacy" class="custom-control-input{{ $errors->has('privacy') ? ' is-invalid' : '' }}" value="1" @if($website->privacy == 1 && old('privacy') == null || old('privacy') == 1) checked @endif>
                                <label class="custom-control-label cursor-pointer w-100 d-flex flex-column" for="i-privacy1">
                                    <span>{{ __('Private') }}</span>
                                    <span class="small text-muted">{{ __('Stats accessible only by you.') }}</span>
                                </label>
                            </div>
                        </div>

                        <div class="col-12 col-lg-4 px-2">
                            <div class="custom-control custom-radio">
                                <input type="radio" id="i-privacy0" name="privacy" class="custom-control-input{{ $errors->has('privacy') ? ' is-invalid' : '' }}" value="0" @if($website->privacy == 0 && old('privacy') == null || old('privacy') == 0 && old('privacy') != null) checked @endif>
                                <label class="custom-control-label cursor-pointer w-100 d-flex flex-column" for="i-privacy0">
                                    <span>{{ __('Public') }}</span>
                                    <span class="small text-muted">{{ __('Stats accessible by anyone.') }}</span>
                                </label>
                            </div>
                        </div>

                        <div class="col-12 col-lg-4 px-2">
                            <div class="custom-control custom-radio">
                                <input type="radio" id="i-privacy2" name="privacy" class="custom-control-input{{ $errors->has('privacy') ? ' is-invalid' : '' }}" value="2" @if($website->privacy == 2 && old('privacy') == null || old('privacy') == 2) checked @endif>
                                <label class="custom-control-label cursor-pointer w-100 d-flex flex-column" for="i-privacy2">
                                    <span>{{ __('Password') }}</span>
                                    <span class="small text-muted">{{ __('Stats accessible by password.') }}</span>
                                </label>

                                <div id="input-password" class="{{ (((old('privacy') == 2) || (old('privacy') == null && $website->privacy == 2)) ? '' : 'd-none')}}">
                                    <div class="input-group mt-2">
                                        <div class="input-group-prepend">
                                            <div class="input-group-text cursor-pointer" data-tooltip="true" data-title="{{ __('Show password') }}" data-password="i-password" data-password-show="{{ __('Show password') }}" data-password-hide="{{ __('Hide password') }}">@include('icons.lock', ['class' => 'width-4 height-4 fill-current text-muted'])</div>
                                        </div>
                                        <input id="i-password" type="password" class="form-control{{ $errors->has('password') ? ' is-invalid' : '' }}" name="password" value="{{ request()->is('admin/*') ? '' : (old('password') ?? $website->password) }}" autocomplete="new-password" @if(request()->is('admin/*')) disabled @endif>
                                    </div>
                                    @if ($errors->has('password'))
                                    <span class="invalid-feedback d-block" role="alert">
                                        <strong>{{ $errors->first('password') }}</strong>
                                    </span>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                    @if ($errors->has('privacy'))
                        <span class="invalid-feedback d-block" role="alert">
                        <strong>{{ $errors->first('privacy') }}</strong>
                    </span>
                    @endif
                </div>
            </div>

            <hr>

            <div class="form-group">
                <div class="row mx-n2">
                    <div class="col-12 col-lg-4 px-2">
                        <div class="form-group mb-0">
                            <div class="row">
                                <div class="col"><label>{{ __('Notifications') }}</label></div>
                                <div class="col-auto">
                                    @cannot('emailReports', ['App\Models\User'])
                                        @if(paymentProcessors())
                                            <a href="{{ route('pricing') }}" data-tooltip="true" title="{{ __('Unlock feature') }}">@include('icons.lock-open', ['class' => 'fill-current text-primary width-4 height-4'])</a>
                                        @endif
                                    @endcannot
                                </div>
                            </div>
                            <div class="custom-control custom-checkbox">
                                <input type="hidden" name="email" value="0">
                                <input type="checkbox" name="email" value="1" class="custom-control-input {{ $errors->has('email') ? ' is-invalid' : '' }}" id="customCheckbox2" @if($website->email && old('email') == null || old('email')) checked @endif @cannot('emailReports', ['App\Models\User']) disabled @endcannot>
                                <label class="custom-control-label cursor-pointer" for="customCheckbox2">
                                    <div>{{ __('Email') }}</div>
                                    <div class="small text-muted">{{ __('Periodic email reports.') }}</div>
                                    @if ($errors->has('email'))
                                        <span class="invalid-feedback d-block" role="alert">
                                            <strong>{{ $errors->first('email') }}</strong>
                                        </span>
                                    @endif
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <hr>

            <div class="row mx-n2">
                <div class="col-12 col-md-6 px-2">
                    <div class="form-group">
                        <label for="i-exclude-ips" class="d-flex align-items-center">{{ __('Exclude IPs') }} <span data-tooltip="true" title="{{ __('To block entire IP classes, use the CIDR notation.') }}" class="d-flex align-items-center {{ (__('lang_dir') == 'rtl' ? 'mr-2' : 'ml-2') }}">@include('icons.info', ['class' => 'fill-current text-muted width-4 height-4'])</span></label>
                        <textarea name="exclude_ips" id="i-exclude-ips" class="form-control{{ $errors->has('exclude_ips') ? ' is-invalid' : '' }}">{{ old('exclude_ips') ?? $website->exclude_ips }}</textarea>
                        @if ($errors->has('exclude_ips'))
                            <span class="invalid-feedback" role="alert">
                                <strong>{{ $errors->first('exclude_ips') }}</strong>
                            </span>
                        @endif
                        <small class="form-text text-muted">{{ __('One per line.') }}</small>
                    </div>
                </div>

                <div class="col-12 col-md-6 px-2">
                    <div class="form-group">
                        <label for="i-exclude-params">{{ __('Exclude URL query parameters') }}</label>
                        <textarea name="exclude_params" id="i-exclude-params" class="form-control{{ $errors->has('exclude_params') ? ' is-invalid' : '' }}">{{ old('exclude_params') ?? $website->exclude_params }}</textarea>
                        @if ($errors->has('exclude_params'))
                            <span class="invalid-feedback" role="alert">
                                <strong>{{ $errors->first('exclude_params') }}</strong>
                            </span>
                        @endif
                        <small class="form-text text-muted">{{ __('One per line.') }}</small>
                    </div>
                </div>
            </div>

            <div class="form-group">
                <div class="custom-control custom-checkbox">
                    <input type="hidden" name="exclude_bots" value="0">
                    <input type="checkbox" name="exclude_bots" value="1" class="custom-control-input {{ $errors->has('exclude_bots') ? ' is-invalid' : '' }}" id="customCheckbox3" @if($website->exclude_bots && old('exclude_bots') == null || old('exclude_bots')) checked @endif>
                    <label class="custom-control-label cursor-pointer" for="customCheckbox3">
                        <div>{{ __('Exclude bots') }}</div>
                        <div class="small text-muted">{{ __('Exclude common bots from being tracked.') }}</div>
                        @if ($errors->has('exclude_bots'))
                            <span class="invalid-feedback d-block" role="alert">
                                <strong>{{ $errors->first('exclude_bots') }}</strong>
                            </span>
                        @endif
                    </label>
                </div>
            </div>

            <hr>            <div class="form-group">
                @include('shared.tracking-code')
            </div>

            <hr>

            <div class="form-group">
                <div class="row">
                    <div class="col"><label for="i-stripe-api-key">{{ __('Stripe API Key (Restricted)') }}</label></div>
                    <div class="col-auto">
                        <span data-tooltip="true" title="{{ __('Restricted Stripe API key used for account linking. No permission changes will be made.') }}" class="d-flex align-items-center">@include('icons.info', ['class' => 'fill-current text-muted width-4 height-4'])</span>
                    </div>
                </div>
                <div class="input-group">
                    <div class="input-group-prepend">
                        <div class="input-group-text cursor-pointer" data-tooltip="true" data-title="{{ __('Show API key') }}" data-password="i-stripe-api-key" data-password-show="{{ __('Show API key') }}" data-password-hide="{{ __('Hide API key') }}">@include('icons.lock', ['class' => 'width-4 height-4 fill-current text-muted'])</div>
                    </div>
                    <input id="i-stripe-api-key" type="password" class="form-control{{ $errors->has('stripe_api_key') ? ' is-invalid' : '' }}" name="stripe_api_key" value="{{ old('stripe_api_key') ?? $website->stripe_api_key }}" placeholder="{{ __('Restricted Stripe API key') }}">
                </div>
                @if ($errors->has('stripe_api_key'))
                    <span class="invalid-feedback d-block" role="alert">
                        <strong>{{ $errors->first('stripe_api_key') }}</strong>
                    </span>
                @endif
                <small class="form-text text-muted">{{ __('Limited API key with restricted permissions from your Stripe dashboard.') }}</small>
            </div>

            <button type="submit" name="submit" class="btn btn-primary">{{ __('Save') }}</button>
        </form>
    </div>
</div>

@if(request()->is('admin/*'))
    @include('admin.users.partials.card', ['user' => $website->user])
@endif
