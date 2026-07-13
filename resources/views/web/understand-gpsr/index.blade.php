@extends('layouts.web')

@section('title', 'Understand the GPSR · Festilaw')
@section('meta_description', 'What the EU General Product Safety Regulation (GPSR) is, its three core pillars, who it applies to, and which products need specialized services.')

@php
    $breadcrumbs = [
        ['name' => 'Home', 'url' => route('home')],
        ['name' => 'Understand GPSR', 'url' => route('understand-gpsr')],
    ];
    $faqItems = [
        ['q' => 'What is the GPSR?', 'a' => 'The General Product Safety Regulation (Regulation (EU) 2023/988) is the EU law on consumer product safety. It has applied since 13 December 2024 and replaces the former General Product Safety Directive. It requires, among other things, that an economic operator established in the EU be responsible for products sold to EU consumers.'],
        ['q' => 'Do I need a GPSR Responsible Person?', 'a' => 'If your business is established outside the EU and you sell products to consumers in the EU, including through marketplaces, then yes: EU law requires an economic operator established in the EU to be responsible for your products. Festilaw acts as that Responsible Person.'],
        ['q' => 'What does a Responsible Person actually do?', 'a' => 'The Responsible Person keeps your declaration of conformity and technical documentation available for the authorities, cooperates with them, informs them of any risk, and helps take corrective action if needed. It is a legal role with real obligations, not just a mailbox.'],
        ['q' => "Isn't an EU email address enough?", 'a' => 'No. You need an economic operator physically established in the EU with a verifiable EU address, not just a contact email.'],
        ['q' => "What happens if I don't comply?", 'a' => 'Your products can be stopped or destroyed at EU customs, you may face national penalties, and marketplaces such as Amazon, Etsy and Shopify can remove your listings or suspend your EU sales.'],
        ['q' => 'Does this apply to small sellers and Etsy shops?', 'a' => 'Yes. The trigger is selling to EU consumers, not your size or volume. Small creators and Etsy sellers are concerned as soon as they target the EU market.'],
        ['q' => "Responsible Person or Authorised Representative, what's the difference?", 'a' => 'Both are EU-based intermediaries, but they rest on different legal bases: the Authorised Representative applies to CE-marked and harmonised products, while the GPSR Responsible Person covers general consumer products under the GPSR. Festilaw helps you determine and cover what you need.'],
        ['q' => 'Which products does Festilaw not cover?', 'a' => 'We currently do not handle cosmetics, food and drinks, medical devices, chemicals, or tobacco. If your products fall into these categories, please get in touch.'],
    ];
    $jsonLdNodes = [
        [
            '@type' => 'WebPage',
            'name' => 'Understand the GPSR',
            'url' => route('understand-gpsr'),
            'description' => 'What the EU General Product Safety Regulation (GPSR) is, its three core pillars, who it applies to, and which products need specialized services.',
        ],
        [
            '@type' => 'FAQPage',
            'mainEntity' => array_map(fn ($f) => [
                '@type' => 'Question',
                'name' => $f['q'],
                'acceptedAnswer' => ['@type' => 'Answer', 'text' => $f['a']],
            ], $faqItems),
        ],
    ];
@endphp

@push('styles')
    @vite('resources/css/web/understand-gpsr/index.css')
@endpush

@section('content')
    <section class="page-hero">
        <x-web.breadcrumb :items="$breadcrumbs" />
        <div class="page-hero__inner">
            <span class="eyebrow page-hero__eyebrow">Understand GPSR</span>
            <h1 class="page-hero__title">Understanding the <span class="page-hero__title-em">GPSR</span></h1>
            <p class="page-hero__lead">Everything you need to know about the EU General Product Safety Regulation, in plain language: what it is, how to comply, and whether it applies to you.</p>
        </div>
    </section>

    <section class="gtabs" x-data="{ tab: 0 }">
        <div class="gtabs__inner">
            <div class="gtabs__nav" role="tablist">
                <button type="button" class="gtabs__tab" :class="{ 'is-active': tab === 0 }" x-on:click="tab = 0">What is the GPSR?</button>
                <button type="button" class="gtabs__tab" :class="{ 'is-active': tab === 1 }" x-on:click="tab = 1">The 3 core pillars</button>
                <button type="button" class="gtabs__tab" :class="{ 'is-active': tab === 2 }" x-on:click="tab = 2">Does it apply to you?</button>
                <button type="button" class="gtabs__tab" :class="{ 'is-active': tab === 3 }" x-on:click="tab = 3">Specialized products</button>
            </div>

            <div class="gtabs__panels">
                <div class="gtabs__panel" x-show="tab === 0" x-cloak>
                    <h2 class="gtabs__panel-title">What is the GPSR?</h2>
                    <p>The General Product Safety Regulation (GPSR) is a major European Union regulation designed to ensure that all consumer products on the EU market are safe, traceable, and well-regulated.</p>
                    <p>Unlike older directives, the GPSR adapts European law to modern commerce, holding manufacturers, importers, and online marketplaces collectively responsible for product safety.</p>
                </div>

                <div class="gtabs__panel" x-show="tab === 1" x-cloak>
                    <h2 class="gtabs__panel-title">The 3 core pillars of GPSR compliance</h2>
                    <p>To keep your products moving freely into the European Union, you must satisfy three fundamental requirements:</p>
                    <div class="gtabs__pillar">
                        <h3 class="gtabs__pillar-title">1. The EU Responsible Person (RP)</h3>
                        <p>You cannot sell to EU consumers from the outside without a local anchor. Any brand based outside the European Union must appoint an official EU Responsible Person. This designated entity acts as the primary liaison, ensuring that technical compliance files are complete and immediately accessible if market authorities come knocking.</p>
                    </div>
                    <div class="gtabs__pillar">
                        <h3 class="gtabs__pillar-title">2. Strict product traceability</h3>
                        <p>Every product entering the EU market must be fully traceable. This means your items and packaging must clearly display:</p>
                        <ul class="gtabs__list">
                            <li>The identity and contact details of both the manufacturer and the EU Responsible Person.</li>
                            <li>Specific product identifiers (such as a batch number, serial number, or type).</li>
                            <li>Clear, legible warnings and safety instructions in the language of the destination country.</li>
                        </ul>
                    </div>
                    <div class="gtabs__pillar">
                        <h3 class="gtabs__pillar-title">3. Continuous risk &amp; safety monitoring</h3>
                        <p>Compliance is a living process. Under the GPSR, businesses must actively monitor product safety. If a product presents a potential hazard, the regulation mandates immediate corrective actions, authority notifications, and close tracking via the EU's official channels, such as the Safety Gate portal.</p>
                    </div>
                </div>

                <div class="gtabs__panel" x-show="tab === 2" x-cloak>
                    <h2 class="gtabs__panel-title">Does this apply to your business?</h2>
                    <p>If you sell non-food consumer products to customers inside the European Union, whether you are an e-commerce brand, an international manufacturer, or a marketplace seller, then yes, the GPSR applies directly to you.</p>
                    <p class="gtabs__good"><strong>The good news:</strong> you don't have to navigate the European legal maze alone. Festilaw is here to assess your products, define your exact documentation blueprint, and handle the authority liaison so you can focus entirely on what you do best: growing your business.</p>
                </div>

                <div class="gtabs__panel" x-show="tab === 3" x-cloak>
                    <h2 class="gtabs__panel-title">Products requiring specialized services</h2>
                    <p>While the GPSR covers the vast majority of non-food consumer goods, certain high-risk categories are governed by separate European frameworks. Festilaw focuses on standard consumer products compliance and does not handle the specialized technical certifications required for:</p>
                    <ul class="gtabs__list">
                        <li>Medical Devices &amp; In Vitro Diagnostics (subject to strict medical certification under MDR/IVDR).</li>
                        <li>Hazardous &amp; Industrial Chemicals (bulk chemical substances, industrial mixtures, pesticides, or raw materials).</li>
                        <li>Aviation, Aerospace &amp; Military Equipment (subject to strict defense and aerospace safety standards).</li>
                        <li>Pharmaceuticals &amp; Human Medicines (requires European Medicines Agency (EMA) approval).</li>
                    </ul>
                    <p>If you are in doubt, <a href="{{ route('contact') }}" class="gtabs__link">send us an email</a>.</p>
                </div>
            </div>
        </div>
    </section>

    <x-web.faq :items="$faqItems" eyebrow="FAQ" title="GPSR questions, answered" />

    @include('web.sections.why-gpsr')
    @include('web.sections.quiz')
@endsection
