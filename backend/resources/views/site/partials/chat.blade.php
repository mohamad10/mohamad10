<div class="chat" id="chat" data-api="{{ url('/api/chat') }}" data-locale="{{ app()->getLocale() }}"
     data-error="{{ __('site.chat.error') }}" data-limit="{{ __('site.chat.limit') }}" data-lead="{{ __('site.chat.lead') }}">
    <button class="fab chat-fab" id="chatFab" aria-expanded="false" aria-controls="chatPanel" aria-label="{{ __('site.chat.open') }}">
        <x-icon name="message-circle" class="ic open-ic"/><x-icon name="x" class="ic close-ic"/>
    </button>
    <section class="chat-panel" id="chatPanel" role="dialog" aria-label="{{ $chat['name'] }}" hidden>
        <header class="chat-head">
            <span class="chat-avatar"><x-icon name="sparkles"/></span>
            <div><b>{{ $chat['name'] }}</b><small><i class="pulse"></i>{{ __('site.chat.online') }}</small></div>
            <button class="icon-btn" id="chatReset" title="{{ __('site.chat.reset') }}" aria-label="{{ __('site.chat.reset') }}"><x-icon name="rotate-ccw"/></button>
        </header>
        <div class="chat-log" id="chatLog" aria-live="polite">
            <div class="bubble bot">{{ $chat['greeting'] }}</div>
        </div>
        <div class="chat-suggest" id="chatSuggest">
            @foreach (__('site.chat.suggestions') as $q)<button class="chip">{{ $q }}</button>@endforeach
        </div>
        <form class="chat-form" id="chatForm">
            <textarea id="chatInput" rows="1" maxlength="1000" placeholder="{{ __('site.chat.placeholder') }}" aria-label="{{ __('site.chat.placeholder') }}"></textarea>
            <button class="icon-btn send" aria-label="{{ __('site.chat.send') }}"><x-icon name="send" class="ic flip"/></button>
        </form>
        <p class="chat-note">{{ __('site.chat.ai_note') }}</p>
    </section>
</div>
