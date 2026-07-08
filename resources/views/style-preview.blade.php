<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Festilaw · votre nouvelle identité visuelle</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=poiret-one:400|julius-sans-one:400|josefin-sans:300,400,600|lora:400,500,600,400i|pacifico:400|satisfy:400" rel="stylesheet">
    <style>
        :root {
            --blue: #0F1199;
            --coral: #FE776A;
            --salmon: #F08E80;
            --cream: #FCF6E3;
            --beige: #EFE5D0;
            --beige-light: #F4ECDB;
            --ink: #0E1326;
            --ink-soft: #3a4064;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { background: var(--beige); color: var(--ink); font-family: 'Lora', Georgia, serif; line-height: 1.65; }
        .wrap { max-width: 1120px; margin: 0 auto; padding: 40px 30px 90px; }

        .doc-head { border-bottom: 2px solid var(--blue); padding-bottom: 22px; margin-bottom: 44px; }
        .doc-head h1 { font-family: 'Poiret One', sans-serif; font-size: 40px; color: var(--blue); letter-spacing: 0.02em; }
        .doc-head p { color: var(--ink-soft); margin-top: 8px; max-width: 640px; }

        .section { margin-bottom: 58px; }
        .section > .label { font-family: 'Lora', serif; text-transform: uppercase; letter-spacing: 0.22em; font-size: 12px; font-weight: 600; color: var(--coral); margin-bottom: 18px; }
        .hint { font-size: 13px; color: var(--ink-soft); font-style: italic; }
        .reco { display: inline-block; font-family: 'Lora', serif; font-style: normal; font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.1em; color: #1a7f4b; background: #d9f0e2; padding: 2px 9px; border-radius: 999px; margin-left: 10px; vertical-align: middle; }

        /* Palette */
        .swatches { display: flex; flex-wrap: wrap; gap: 16px; }
        .sw { width: 150px; }
        .sw .chip { height: 84px; border-radius: 12px; border: 1px solid rgba(14,19,38,0.12); }
        .sw .name { font-weight: 600; font-size: 14px; margin-top: 9px; }
        .sw .hex { font-size: 12px; color: var(--ink-soft); font-family: ui-monospace, monospace; }
        .sw .use { font-size: 12px; color: var(--ink-soft); margin-top: 2px; }

        /* Type candidates */
        .type-card { background: var(--beige-light); border: 1px solid rgba(14,19,38,0.1); border-radius: 14px; padding: 22px 26px; margin-bottom: 16px; }
        .type-card .fname { font-family: 'Lora', serif; font-size: 13px; font-weight: 600; color: var(--ink-soft); margin-bottom: 14px; }
        .display-line { color: var(--blue); text-transform: uppercase; line-height: 1.05; font-size: clamp(30px, 5vw, 58px); }
        .f-poiret { font-family: 'Poiret One', sans-serif; letter-spacing: 0.04em; }
        .f-josefin { font-family: 'Josefin Sans', sans-serif; font-weight: 300; letter-spacing: 0.04em; }
        .f-julius { font-family: 'Julius Sans One', sans-serif; letter-spacing: 0.06em; }

        .on-blue { background: var(--blue); border-color: transparent; }
        .on-blue .fname { color: rgba(252,246,227,0.65); }
        .on-blue .display-line { color: var(--salmon); }

        /* Script */
        .script-line { font-size: clamp(34px, 5vw, 56px); color: var(--coral); line-height: 1.2; }
        .f-pacifico { font-family: 'Pacifico', cursive; }
        .f-satisfy { font-family: 'Satisfy', cursive; }

        /* Body serif */
        .body-sample { max-width: 640px; font-size: 17px; }
        .body-sample p + p { margin-top: 14px; }

        /* Composed previews */
        .previews { display: grid; gap: 22px; }
        @media (min-width: 820px) { .previews { grid-template-columns: 1fr 1fr; } }
        .card { border-radius: 20px; overflow: hidden; border: 1px solid rgba(14,19,38,0.12); }
        .card-blue { background: var(--blue); padding: 44px 40px; }
        .card-blue .kick { font-family: 'Lora', serif; text-transform: uppercase; letter-spacing: 0.2em; font-size: 12px; font-weight: 600; color: var(--salmon); }
        .card-blue h3 { font-family: 'Poiret One', sans-serif; text-transform: uppercase; color: var(--cream); font-size: 40px; line-height: 1.05; letter-spacing: 0.03em; margin: 14px 0 16px; }
        .card-blue p { color: rgba(252,246,227,0.82); max-width: 30ch; }
        .card-beige { background: var(--beige-light); padding: 44px 40px; }
        .card-beige h3 { font-family: 'Poiret One', sans-serif; text-transform: uppercase; color: var(--blue); font-size: 34px; line-height: 1.08; letter-spacing: 0.03em; margin-bottom: 14px; }
        .card-beige p { color: var(--ink); }
        .accent { font-family: 'Pacifico', cursive; color: var(--coral); font-size: 1.15em; }

        .btn { display: inline-block; margin-top: 24px; font-family: 'Lora', serif; font-weight: 600; font-size: 15px; padding: 13px 26px; border-radius: 10px; }
        .btn-coral { background: var(--coral); color: #3a1512; }
        .btn-outline { background: transparent; color: var(--blue); border: 1.5px solid var(--blue); }
    </style>
</head>
<body>
<div class="wrap">

    <div class="doc-head">
        <h1>Festilaw · votre nouvelle identité visuelle</h1>
        <p>Voici la direction que nous proposons pour votre site : les couleurs et les polices, dans l'esprit de votre logo et d'une image plus sobre, adaptée au sérieux du sujet. Rien n'est encore figé : cette page sert à valider l'ambiance ensemble avant de construire le site.</p>
    </div>

    {{-- PALETTE --}}
    <div class="section">
        <div class="label">Vos couleurs</div>
        <div class="swatches">
            <div class="sw"><div class="chip" style="background:#0F1199"></div><div class="name">Bleu roi</div><div class="hex">#0F1199</div><div class="use">Fond du hero et des sections mises en avant</div></div>
            <div class="sw"><div class="chip" style="background:#FE776A"></div><div class="name">Corail</div><div class="hex">#FE776A</div><div class="use">Aplats, boutons, logo</div></div>
            <div class="sw"><div class="chip" style="background:#F08E80"></div><div class="name">Saumon</div><div class="hex">#F08E80</div><div class="use">Texte sur le bleu</div></div>
            <div class="sw"><div class="chip" style="background:#FCF6E3"></div><div class="name">Crème</div><div class="hex">#FCF6E3</div><div class="use">Texte clair sur le bleu</div></div>
            <div class="sw"><div class="chip" style="background:#EFE5D0"></div><div class="name">Beige</div><div class="hex">#EFE5D0</div><div class="use">Fond des pages</div></div>
            <div class="sw"><div class="chip" style="background:#F4ECDB"></div><div class="name">Beige clair</div><div class="hex">#F4ECDB</div><div class="use">Cartes et nuances</div></div>
            <div class="sw"><div class="chip" style="background:#0E1326"></div><div class="name">Bleu-noir</div><div class="hex">#0E1326</div><div class="use">Texte des paragraphes</div></div>
        </div>
    </div>

    {{-- TITRES --}}
    <div class="section">
        <div class="label">Les titres · quelques pistes de police</div>
        <div class="type-card">
            <div class="fname">Poiret One <span class="reco">recommandée</span></div>
            <div class="display-line f-poiret">Sell safely in Europe</div>
        </div>
        <div class="type-card">
            <div class="fname">Josefin Sans</div>
            <div class="display-line f-josefin">Sell safely in Europe</div>
        </div>
        <div class="type-card">
            <div class="fname">Julius Sans One</div>
            <div class="display-line f-julius">Sell safely in Europe</div>
        </div>
        <div class="type-card on-blue">
            <div class="fname">La même, en saumon sur le bleu (ambiance hero)</div>
            <div class="display-line f-poiret">Your GPSR Responsible Person</div>
        </div>
    </div>

    {{-- ACCENT / SCRIPT --}}
    <div class="section">
        <div class="label">Les mots à mettre en avant · dans l'esprit de votre logo</div>
        <div class="type-card">
            <div class="fname">Pacifico <span class="reco">recommandée</span></div>
            <div class="script-line f-pacifico">Festilaw · from entrepreneurs, for entrepreneurs</div>
        </div>
        <div class="type-card">
            <div class="fname">Satisfy</div>
            <div class="script-line f-satisfy">Festilaw · from entrepreneurs, for entrepreneurs</div>
        </div>
        <p class="hint">À réserver aux mots que vous souhaitez mettre en avant (ni titre, ni texte courant).</p>
    </div>

    {{-- PARAGRAPHE --}}
    <div class="section">
        <div class="label">Les paragraphes · un serif sobre et lisible</div>
        <div class="body-sample">
            <p>The General Product Safety Regulation requires any seller based outside the European Union to appoint an EU Responsible Person before selling to European consumers. Festilaw provides that official representation, with real support from a dedicated team.</p>
            <p>Le règlement impose à tout vendeur établi hors de l'Union européenne de désigner un représentant. Un serif neutre reste lisible et sérieux sur de longs paragraphes.</p>
        </div>
    </div>

    {{-- APERCU COMPOSE --}}
    <div class="section">
        <div class="label">Aperçu · les deux ambiances</div>
        <div class="previews">
            <div class="card card-blue">
                <div class="kick">Your GPSR Responsible Person</div>
                <h3>Sell safely in Europe</h3>
                <p>Festilaw becomes your official EU Responsible Person, ready within 24 hours.</p>
                <a class="btn btn-coral">Get compliant in 24h</a>
            </div>
            <div class="card card-beige">
                <h3>Compliance, made human</h3>
                <p>Real people who reply, not a ticket queue. Built <span class="accent">from entrepreneurs, for entrepreneurs</span>, with the seriousness your paperwork deserves.</p>
                <a class="btn btn-outline">See how it works</a>
            </div>
        </div>
    </div>

</div>
</body>
</html>
