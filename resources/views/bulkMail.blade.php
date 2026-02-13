@component('mail::message')
# {{ $messages->count() }} {{ Str::plural('Message', $messages->count()) }}

@foreach($messages as $message)
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
