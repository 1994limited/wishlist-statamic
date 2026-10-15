@extends('blade-site')
@section('title', new \Illuminate\Support\HtmlString($title))
@section('content')
<h1>{!! $title !!}</h1>
<p>{{ $count }} items</p>
<ul>@foreach ($items as $item)<li><a href="{{ $item['url'] }}">{{ $item['title'] }}</a> {!! $item['wishlist_item']['notes'] ?? '' !!}</li>@endforeach</ul>
@endsection
