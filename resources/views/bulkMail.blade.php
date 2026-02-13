@component('mail::message')
# {{ $messageGroup->count() }} {{ Str::plural('Message', $messageGroup->count()) }}

@foreach($messageGroup as $message)
- {{ $message->mailHandler->buildDataBulkMail() }} — {{ $message->dateFormated }}
@endforeach

@if($url)
@component('mail::button', ['url' => $url])
View Details
@endcomponent
@endif

Thanks,<br>
{{ config('app.name') }}
@endcomponent
