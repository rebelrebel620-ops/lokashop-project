@extends('layout')

@section('body-class', 'landing-page')

@section('page-styles')
<link rel="stylesheet" href="{{ asset('css/landing-background.css') }}">
<link rel="stylesheet" href="{{ asset('css/landing-responsive.css') }}">

<style>
body.landing-page {
    background: #eff4e9;
    position: relative;
    isolation: isolate;
}

body.landing-page::before {
    content: "";
    position: fixed;
    inset: 0;
    z-index: -1;
    pointer-events: none;
    background: url("{{ asset('images/account-background.webp') }}")
        center / cover no-repeat;
}
</style>
@endsection

@section('hero')
<section class="hero photo-hero">
    <div class="hero-in">
        <div>
            <h1>Your everyday finds, delivered with care.</h1>

            <p>
                Shop trusted local sellers and track every order
                from checkout to your doorstep.
            </p>

            <form
                action="/shop"
                method="get"
                style="display:flex;gap:10px;max-width:520px;margin:22px 0 0"
            >
                <input
                    name="q"
                    placeholder="Search products, e.g. reusable bag"
                    aria-label="Search products"
                    style="margin:0;border:0;padding:14px 16px"
                >

                <button
                    type="submit"
                    style="margin:0;background:var(--lime);color:var(--dark)"
                >
                    Search
                </button>
            </form>

            <div class="btnrow">
                <a class="btn lg" href="/shop">
                    Shop now &rarr;
                </a>

                <a class="btn lg alt" href="/register">
                    Become a seller
                </a>
            </div>

            <div class="stats">
                <div>
                    <b>{{ $stats['products'] }}</b>
                    <span>Products</span>
                </div>

                <div>
                    <b>{{ $stats['sellers'] }}</b>
                    <span>Verified sellers</span>
                </div>

                <div>
                    <b>{{ $stats['orders'] }}</b>
                    <span>Orders delivered</span>
                </div>
            </div>
        </div>
    </div>
</section>
@endsection

@section('content')
@if($notice)
    <div class="banner">
        <strong>{{ $notice->title }}:</strong>
        {{ $notice->body }}
    </div>
@endif

<section
    class="featbar"
    style="margin:-32px -20px 40px;border-radius:16px"
>
    <div class="in">
        <div class="f">
            <div class="ic">&#9989;</div>
            <div>
                <b>Verified sellers</b>
                <small>Trusted local shops</small>
            </div>
        </div>

        <div class="f">
            <div class="ic">&#128274;</div>
            <div>
                <b>Secure checkout</b>
                <small>Multiple payment methods</small>
            </div>
        </div>

        <div class="f">
            <div class="ic">&#128666;</div>
            <div>
                <b>Live order tracking</b>
                <small>Real-time updates</small>
            </div>
        </div>

        <div class="f">
            <div class="ic">&#128205;</div>
            <div>
                <b>Local delivery</b>
                <small>Supporting Philippine communities</small>
            </div>
        </div>
    </div>
</section>

<section class="sec">
    <h2>Shop by category</h2>

    @php
        $categoryIcons = [
            'pet-supplies' => '🐾',
            'electronics-and-gadgets' => '💻',
            'womens-apparel' => '👗',
            'mens-apparel' => '👕',
            'kids-and-baby' => '🧸',
            'home-and-garden' => '🪴',
            'sports-and-outdoors' => '🏀',
            'health-and-beauty' => '💄',
            'books-and-media' => '📚',
            'food-and-gourmet' => '🍽️',
            'furniture-and-office-equipment' => '🪑',
            'jewelry-and-watches' => '💍',
        ];
    @endphp

    <div class="cat-grid">
        @foreach($categories as $c)
            @php
                $categorySlug = \Illuminate\Support\Str::slug(
                    str_replace(["’", "'"], '', $c->name)
                );

                $categoryImage = 'images/categories/'.$categorySlug.'.jpg';
            @endphp

            <a
                class="cat-card category-photo-card"
                href="{{ url('/shop').'?'.http_build_query(['main_category' => $c->name]) }}"
            >
                @if(is_file(public_path($categoryImage)))
                    <img
                        class="category-photo"
                        src="{{ asset($categoryImage) }}"
                        alt="{{ $c->name }}"
                        loading="lazy"
                        width="480"
                        height="360"
                    >
                @endif

                <div class="category-caption">
                    <div class="ic" aria-hidden="true">
                        {{ $categoryIcons[$categorySlug] ?? '🏷️' }}
                    </div>

                    <span>{{ $c->name }}</span>

                    <span class="category-arrow" aria-hidden="true">
                        ›
                    </span>
                </div>
            </a>
        @endforeach

        <a class="category-all-link" href="/shop">
            View all products &rarr;
        </a>
    </div>
</section>

<section class="sec">
    <h2>Trending picks</h2>

    <p class="sub">
        Fresh from local sellers &middot; approved products, updated live.
    </p>

    <div class="grid">
        @forelse($featured as $p)
            <div class="card">
                @include('product-photo', ['photoProduct' => $p])

                <span class="tag">{{ $p->category }}</span>

                @if($p->discount_percent > 0)
                    <span class="tag off">
                        {{ rtrim(rtrim(number_format($p->discount_percent, 1), '0'), '.') }}% OFF
                    </span>
                @endif

                <h3>{{ $p->name }}</h3>

                <p class="price">
                    ₱{{ number_format($p->price * (1 - $p->discount_percent / 100), 2) }}

                    @if($p->discount_percent > 0)
                        <span class="price strike">
                            ₱{{ number_format($p->price, 2) }}
                        </span>
                    @endif
                </p>

                <small>{{ $p->stock }} in stock</small>
                <br>

                <a class="btn" href="/product/{{ $p->id }}">
                    View product
                </a>
            </div>
        @empty
            <p>No products yet. Check back soon.</p>
        @endforelse
    </div>
</section>

<section class="sec">
    <h2>From cart to doorstep</h2>

    <p class="sub">
        A simple and secure shopping experience, made for every Juan.
    </p>

    <div class="steps-row">
        <div class="st">
            <div class="ic">&#128722;</div>
            <div>
                <b>1. Checkout</b>
                <small>Choose your items and secure payment</small>
            </div>
        </div>

        <div class="st">
            <div class="ic">&#128230;</div>
            <div>
                <b>2. Seller prepares</b>
                <small>Your order is packed with care</small>
            </div>
        </div>

        <div class="st">
            <div class="ic">&#128666;</div>
            <div>
                <b>3. Out for delivery</b>
                <small>Our local riders bring it to you</small>
            </div>
        </div>

        <div class="st">
            <div class="ic">&#128205;</div>
            <div>
                <b>4. Track your order</b>
                <small>Get real-time updates to your doorstep</small>
            </div>
        </div>
    </div>
</section>

<section class="band">
    <h2>Sell your products on LokaShop</h2>

    <p>
        Register your business, list products and reach local buyers.
    </p>

    <a class="btn lime lg" href="/register">
        Become a seller
    </a>
</section>
@endsection