@extends('layouts.front')
@section('title', 'Payment Fail')
@section('content')

<section class="bg-img1 txt-center p-lr-15 p-tb-92" style="background-image: url({{asset('assets/frontimages/catagory/SHOP.jpg')}};">
		<h1 class="ltext-105 cl0 txt-center">
			Payment Fail
		</h1>
		<div class="bredcrum">
			<ul>
				<li><a class="text-white" href="{{route('FrontIndex')}}">Home</a></li>
				<li><img src="{{ asset('assets/images/breadcrumb.png') }}" alt=""></li>
				<li>Payment Fail</li>
			</ul>
		</div>
	</section>

    <!-- Shoping Cart -->
    <div class="bg0 p-t-75 p-b-85">
        <div class="container">
            <div class="row">
                <div class="col-md-12" style="text-align: center;">
                    <h1>Opps!</h1>    
                </div>
                <div class="col-md-12" style="text-align: center;">
		            <p><br>Thank you for shopping with us.However,the transaction has been declined.</p>
		        </div>
            </div>
        </div>
    </div>
@endsection

@section('scripts')

@endsection
