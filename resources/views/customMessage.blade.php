@component('mail::message')
# {{ $subjectLine }}

{!! \Illuminate\Mail\Markdown::parse($markdownBody) !!}

{{ config('app.name') }}
@endcomponent

