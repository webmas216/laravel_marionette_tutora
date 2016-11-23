@extends('emails._.layouts.default')

@section('heading')
    You've received a new message!
@stop

@section('body')
    {!! str_replace(['</p><p>', '<p>', '</p>'], ['<br><br>', '', ''], $line->getBody()) !!}<br>
    <br>
    @if ($line->getLine()->user)
        ~ {{ $line->getLine()->user->first_name }} 
    @endif
@stop

@section('action')
    <a href="{{ route('message.redirect', [
        'uuid' => $line->getLine()->message->uuid,
    ]) }}" class="btn__anchor" style="color:#ffffff !important; text-decoration: none !important;">Reply to Message</a>
@stop
