{{-- The animated tornado logo: @include('logo', ['class' => 'size-9']). The loop (public/images/tornado.webm)
     plays where transparent video works; Safari, and people who ask for less motion, see a still frame. --}}
<span class="relative inline-block shrink-0 {{ $class ?? 'size-10' }}" aria-hidden="true"
      x-data="{ moving: false }"
      x-init="
          const video = $refs.video;
          moving = ! window.matchMedia('(prefers-reduced-motion: reduce)').matches
              && ! /^((?!chrome|android).)*safari/i.test(navigator.userAgent)
              && video.canPlayType('video/webm; codecs=&quot;vp09.00.10.08&quot;') !== '';
          if (moving) video.play().catch(() => moving = false);
      ">
    <img src="{{ asset('images/tornado.png') }}" alt="" class="absolute inset-0 size-full" :class="moving && 'invisible'">
    <video x-ref="video" class="absolute inset-0 size-full" :class="moving || 'invisible'" muted loop playsinline preload="none" disablepictureinpicture>
        <source src="{{ asset('images/tornado.webm') }}" type="video/webm">
    </video>
</span>
