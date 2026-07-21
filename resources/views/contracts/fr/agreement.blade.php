<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    @include('contracts._styles')
</head>
<body>
    <table class="doc-head"><tr>
        @if ($logo)<td width="46"><img class="brand-logo" src="{{ $logo }}" alt=""></td>@endif
        <td><span class="eyebrow">Festilaw &middot; Conformité à la sécurité des produits en Europe</span></td>
    </tr></table>
    <div class="head-rule"></div>
    <h1>Contrat de service de Personne Responsable</h1>
    <div class="pack">Pack {{ $pack }}</div>
    <div class="meta">Référence : {{ $reference }} &middot; Date : {{ $date }}</div>

    <h2>1. Parties</h2>
    <p><strong>Prestataire de services :</strong> Festilaw B.V., société constituée selon le droit néerlandais, agissant en qualité de « Personne Responsable » au sens de la réglementation de l'UE relative à la sécurité des produits.</p>
    <p><strong>Client :</strong> {!! $company !!}, société fondée à {!! $place !!} en {!! $year !!} et spécialisée dans {!! $activity !!}.</p>
    <p>Désignées ensemble les « Parties ». Le présent Contrat prend effet à la date de la dernière signature apposée ci-dessous.</p>

    <h2>2. Désignation et étendue des services</h2>
    <p><strong>Désignation de la Personne Responsable :</strong> {!! $company !!} désigne Festilaw comme sa Personne Responsable pour l'Union européenne à l'égard des produits de consommation vendus dans l'UE, ainsi que l'exige le Règlement (UE) 2023/988 relatif à la sécurité générale des produits (GPSR). Festilaw accepte cette désignation. Le présent contrat vaut mandat écrit autorisant Festilaw à agir en qualité de représentant autorisé de {!! $company !!} dans l'UE, conformément à l'article 4 du Règlement (UE) 2019/1020 (pour les produits harmonisés) et au GPSR pour les produits de consommation non harmonisés.</p>

    <h2>3. Honoraires, paiement et services complémentaires</h2>
    <p><strong>3.1</strong>&nbsp;&nbsp;Honoraires annuels : {!! $company !!} versera des honoraires annuels de EUR {{ $fee }} ({{ $feeWords }}) au titre des services de Personne Responsable, selon les modalités suivantes :</p>
    <p>(a) Année de service. Aux fins du présent Contrat, une année de service court du 1er janvier au 31 décembre de chaque année civile. (b) Première année, au prorata. Pour l'année civile au cours de laquelle le présent Contrat est signé, les honoraires annuels seront calculés au prorata, de la date de signature au 31 décembre de cette année. Le montant au prorata sera facturé par Festilaw à la signature du présent Contrat et est exigible et payable dans les trente (30) jours suivant la réception de la facture. (c) Deuxième année et années suivantes. À compter du 1er janvier de l'année suivant la signature, les honoraires annuels complets de EUR {{ $fee }} s'appliqueront pour chaque année civile. Festilaw émettra une facture en janvier de chaque année de service. Le paiement est exigible dans les trente (30) jours suivant la réception de la facture. (d) Étendue. Les honoraires annuels couvrent tous les éléments essentiels du rôle de Personne Responsable tel que décrit à la section 2.2 des Conditions Générales. (e) Conservation des documents. Festilaw conservera l'ensemble de la documentation technique et des dossiers de conformité relatifs aux produits de {!! $company !!} pendant une durée de dix (10) ans suivant la résiliation ou l'expiration du présent Contrat, conformément à la réglementation de l'UE applicable en matière de sécurité des produits et aux obligations de Festilaw en tant que Personne Responsable dans l'UE.</p>
    <p><strong>3.2</strong>&nbsp;&nbsp;Services complémentaires : tout service complémentaire sera facturé conformément aux Conditions Générales, Section 3.2.</p>

    <h2>4. Adresse de la Personne Responsable dans l'UE &amp; obligations d'affichage</h2>
    <p><strong>4.1</strong>&nbsp;&nbsp;Communication de l'adresse. L'adresse de Personne Responsable dans l'UE et les coordonnées de contact de Festilaw (« l'Adresse PR ») ne seront communiquées à {!! $company !!} par écrit qu'une fois que Festilaw aura déclaré achevé l'examen initial de conformité. Préalablement à cette communication, Festilaw se réserve le droit de demander tout document, certificat ou dossier technique supplémentaire jugé nécessaire pour vérifier la manière dont {!! $company !!} s'est conformée à la réglementation de l'UE applicable, y compris, sans s'y limiter, la Déclaration de Conformité UE (DoC), les évaluations des risques ou les rapports d'essais de sécurité. {!! $company !!} s'engage à fournir promptement l'ensemble de la documentation ainsi demandée. Préalablement à la confirmation écrite par Festilaw de l'achèvement de cet examen, le Client ne devra ni afficher, ni publier, ni communiquer les coordonnées de Festilaw sous quelque forme et sur quelque support que ce soit.</p>
    <p><strong>4.2</strong>&nbsp;&nbsp;Obligation d'affichage. {!! $company !!} reconnaît et confirme avoir compris que, en application de l'article 9(7) du Règlement (UE) 2023/988 (GPSR), le nom, la raison sociale ou la marque déposée, l'adresse postale et l'adresse électronique de la Personne Responsable dans l'UE doivent figurer sur le produit ou sur son emballage, son colis ou un document d'accompagnement. {!! $company !!} s'engage à veiller à ce que l'Adresse PR soit affichée sur l'emballage extérieur de tous les produits vendus ou mis à disposition sur le marché de l'UE, de manière clairement lisible et immédiatement accessible aux autorités douanières et aux autorités de surveillance du marché sans qu'il soit nécessaire d'ouvrir l'emballage du produit.</p>
    <p><strong>4.3</strong>&nbsp;&nbsp;Formulation. Lors de la communication de l'Adresse PR, Festilaw fournira également à {!! $company !!} la formulation exacte de Personne Responsable à utiliser sur l'emballage, les notices produit, le site internet et les pages de présentation des produits, dans la forme requise par le GPSR. {!! $company !!} s'engage à utiliser cette formulation sans modification.</p>
    <p><strong>4.4</strong>&nbsp;&nbsp;Exception pour les petits produits. Lorsque la taille ou la nature d'un produit rend physiquement impossible ou impraticable l'affichage de l'Adresse PR sur l'emballage extérieur selon les modalités décrites à l'article 4.2 (par exemple pour de très petits produits ou des produits dépourvus d'emballage extérieur individuel), l'alternative d'affichage applicable sera déterminée conjointement par Festilaw et {!! $company !!}, préalablement à la mise sur le marché de l'UE de ces produits, conformément aux dérogations et aux méthodes d'affichage alternatives permises par le GPSR. {!! $company !!} ne décidera pas unilatéralement d'omettre l'Adresse PR sans l'accord écrit préalable de Festilaw sur l'alternative applicable.</p>

    <h2>5. Acceptation des Conditions Générales</h2>
    <p><strong>5.1</strong>&nbsp;&nbsp;{!! $company !!} a lu et reconnu les conditions générales annexées au présent contrat.</p>
    <p><strong>5.2</strong>&nbsp;&nbsp;Toute modification du présent Contrat doit être faite par écrit et signée par les deux Parties, conformément à la section 8.2 des Conditions Générales.</p>

    <h2>6. Signature</h2>
    <p>En signant ci-dessous, chaque Partie confirme son accord aux conditions du présent Contrat de service de Personne Responsable.</p>
    <div class="sign-zone">
        <div class="sign-party">
            <p><span class="sign-name">Festilaw B.V.</span> <span class="muted">(Prestataire du service de Personne Responsable)</span></p>
            <p class="sign-row">Nom : ______________________</p>
            <table class="sign-fields"><tr>
                <td>Signature : ______________________</td>
                <td>Date : ______________________</td>
            </tr></table>
        </div>
        <div class="sign-party">
            <p><span class="sign-name">{!! $company !!}</span> <span class="muted">(Client)</span></p>
            <p class="sign-row">Nom : {{ $signer }}</p>
            <table class="sign-fields"><tr>
                <td>Signature : <span class="sign-tag">@{{signature:1:y}}</span></td>
                <td>Date : <span class="sign-tag">@{{date:1:y}}</span></td>
            </tr></table>
        </div>
    </div>
    <p class="muted">La signature du Client est recueillie par voie électronique via le partenaire de signature de Festilaw, avec une piste d'audit infalsifiable. Festilaw contre-signe le mandat pour le finaliser.</p>

    <div class="page-break"></div>
    @include('contracts.fr.general-terms')
</body>
</html>
