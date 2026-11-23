{{--
    FAQ visível. A resposta sai de App\Support\Seo\Faq::html, o mesmo texto do
    JSON-LD FAQPage.
--}}
@props(['items'])
<div {{ $attributes->class('mt-10 space-y-3') }}>
    @foreach($items as $item)
        <x-landing.faq-item :question="$item['question']">{!! \App\Support\Seo\Faq::html($item['parts']) !!}</x-landing.faq-item>
    @endforeach
</div>
