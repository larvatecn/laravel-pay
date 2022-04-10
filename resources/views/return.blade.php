@extends('layouts.app')

@section('title', __('Payment Return'))

@section('content')
    <div class="container">
        <div class="row">
            <div class="col-12">
                @if($charge->paid)
                    支付成功！
                @endif
            </div>
        </div>
    </div>
@endsection