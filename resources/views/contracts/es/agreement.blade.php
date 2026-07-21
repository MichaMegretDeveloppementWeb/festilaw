<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    @include('contracts._styles')
</head>
<body>
    <table class="doc-head"><tr>
        @if ($logo)<td width="46"><img class="brand-logo" src="{{ $logo }}" alt=""></td>@endif
        <td><span class="eyebrow">Festilaw &middot; Cumplimiento de la seguridad de los productos en Europa</span></td>
    </tr></table>
    <div class="head-rule"></div>
    <h1>Contrato de servicio de Persona Responsable</h1>
    <div class="pack">Pack {{ $pack }}</div>
    <div class="meta">Referencia: {{ $reference }} &middot; Fecha: {{ $date }}</div>

    <h2>1. Partes</h2>
    <p><strong>Prestador del servicio:</strong> Festilaw B.V., sociedad constituida conforme al Derecho neerlandés, que actúa como «Persona Responsable» en el sentido de la normativa de la UE en materia de seguridad de los productos.</p>
    <p><strong>Cliente:</strong> {!! $company !!}, sociedad fundada en {!! $place !!} en {!! $year !!} y especializada en {!! $activity !!}.</p>
    <p>Denominadas conjuntamente las «Partes». El presente Contrato entra en vigor en la fecha de la última firma que figura a continuación.</p>

    <h2>2. Designación y alcance de los servicios</h2>
    <p><strong>Designación de la Persona Responsable:</strong> {!! $company !!} designa a Festilaw como su Persona Responsable en la Unión Europea para los productos de consumo vendidos en la UE, conforme a lo exigido por el Reglamento general de seguridad de los productos de la UE (UE) 2023/988 (GPSR). Festilaw acepta esta designación. El presente contrato constituye el mandato escrito en virtud del cual Festilaw actúa como representante autorizado de {!! $company !!} en la UE, de conformidad con el artículo 4 del Reglamento (UE) 2019/1020 (para los productos armonizados) y con el GPSR para los productos de consumo no armonizados.</p>

    <h2>3. Honorarios, pago y servicios adicionales</h2>
    <p><strong>3.1</strong>&nbsp;&nbsp;Honorario anual: {!! $company !!} abonará un honorario anual de EUR {{ $fee }} ({{ $feeWords }}) por los servicios de Persona Responsable, con arreglo a las condiciones siguientes:</p>
    <p>(a) Año de servicio. A los efectos del presente Contrato, un año de servicio comprende del 1 de enero al 31 de diciembre de cada año natural. (b) Primer año, prorrateo. Respecto del año natural en que se firma el presente Contrato, el honorario anual se calculará a prorrata desde la fecha de la firma hasta el 31 de diciembre de ese año. El importe prorrateado será facturado por Festilaw en el momento de la firma del presente Contrato y será exigible y pagadero dentro de los treinta (30) días siguientes a la recepción de la factura. (c) Segundo año y años sucesivos. A partir del 1 de enero del año siguiente al de la firma, se aplicará el honorario anual íntegro de EUR {{ $fee }} por cada año natural. Festilaw emitirá una factura en el mes de enero de cada año de servicio. El pago será exigible dentro de los treinta (30) días siguientes a la recepción de la factura. (d) Alcance. El honorario anual cubre todos los elementos esenciales de la función de Persona Responsable descritos en la sección 2.2 de las Condiciones Generales. (e) Conservación de la documentación. Festilaw conservará toda la documentación técnica y los registros de conformidad relativos a los productos de {!! $company !!} durante un período de diez (10) años a partir de la resolución o expiración del presente Contrato, de conformidad con la normativa de la UE aplicable en materia de seguridad de los productos y con las obligaciones de Festilaw como Persona Responsable en la UE.</p>
    <p><strong>3.2</strong>&nbsp;&nbsp;Servicios adicionales: Cualquier servicio adicional se facturará de conformidad con las Condiciones Generales, sección 3.2.</p>

    <h2>4. Dirección de la Persona Responsable en la UE y obligaciones de indicación</h2>
    <p><strong>4.1</strong>&nbsp;&nbsp;Comunicación de la dirección. La dirección de la Persona Responsable en la UE de Festilaw y sus datos de contacto («la Dirección PR») se comunicarán por escrito a {!! $company !!} únicamente después de que Festilaw haya declarado concluida la revisión inicial de conformidad. Con anterioridad a dicha comunicación, Festilaw se reserva el derecho de solicitar cualesquiera documentos, certificados o expedientes técnicos adicionales que estime necesarios para verificar de qué modo {!! $company !!} ha cumplido la normativa aplicable de la UE, incluidos, entre otros, la Declaración de Conformidad de la UE (DoC), las evaluaciones de riesgos o los informes de ensayos de seguridad. {!! $company !!} se compromete a facilitar sin demora toda la documentación así solicitada. Antes de la confirmación por escrito de Festilaw de que dicha revisión ha concluido, el Cliente no exhibirá, publicará ni comunicará los datos de Festilaw en forma alguna, en ningún soporte.</p>
    <p><strong>4.2</strong>&nbsp;&nbsp;Obligación de indicación. {!! $company !!} reconoce y confirma que ha comprendido que, con arreglo al artículo 9, apartado 7, del Reglamento de la UE 2023/988 (GPSR), el nombre, el nombre comercial registrado o la marca registrada, la dirección postal y la dirección electrónica de la Persona Responsable en la UE deben figurar en el producto o en su embalaje, en el paquete o en un documento que lo acompañe. {!! $company !!} se compromete a garantizar que la Dirección PR figure en el embalaje exterior de todos los productos vendidos o comercializados en el mercado de la UE, de manera claramente legible e inmediatamente accesible para las autoridades aduaneras y las autoridades de vigilancia del mercado sin necesidad de abrir el embalaje del producto.</p>
    <p><strong>4.3</strong>&nbsp;&nbsp;Redacción. Tras la comunicación de la Dirección PR, Festilaw facilitará asimismo a {!! $company !!} la redacción exacta de Persona Responsable que deberá utilizarse en el embalaje, los manuales del producto, el sitio web y las páginas de listado de productos, en la forma exigida por el GPSR. {!! $company !!} se compromete a utilizar dicha redacción sin modificación alguna.</p>
    <p><strong>4.4</strong>&nbsp;&nbsp;Excepción para productos pequeños. Cuando el tamaño o la naturaleza de un producto haga físicamente imposible o impracticable indicar la Dirección PR en el embalaje exterior en la forma descrita en el artículo 4.2 (por ejemplo, productos muy pequeños o productos sin embalaje exterior individual), la alternativa de indicación aplicable será determinada conjuntamente por Festilaw y {!! $company !!}, con anterioridad a la introducción de dichos productos en el mercado de la UE, de conformidad con las excepciones y los métodos de indicación alternativos permitidos por el GPSR. {!! $company !!} no decidirá unilateralmente omitir la Dirección PR sin el acuerdo previo por escrito de Festilaw sobre la alternativa aplicable.</p>

    <h2>5. Aceptación de las Condiciones Generales</h2>
    <p><strong>5.1</strong>&nbsp;&nbsp;{!! $company !!} ha leído y reconocido las condiciones generales adjuntas al presente contrato.</p>
    <p><strong>5.2</strong>&nbsp;&nbsp;Toda modificación del presente Contrato deberá realizarse por escrito y ser firmada por ambas Partes, de conformidad con la sección 8.2 de las Condiciones Generales.</p>

    <h2>6. Firma</h2>
    <p>Al firmar a continuación, cada Parte confirma su conformidad con los términos del presente Contrato de servicio de Persona Responsable.</p>
    <div class="sign-zone">
        <div class="sign-party">
            <p><span class="sign-name">Festilaw B.V.</span> <span class="muted">(Prestador del servicio de Persona Responsable)</span></p>
            <p class="sign-row">Nombre: ______________________</p>
            <table class="sign-fields"><tr>
                <td>Firma: ______________________</td>
                <td>Fecha: ______________________</td>
            </tr></table>
        </div>
        <div class="sign-party">
            <p><span class="sign-name">{!! $company !!}</span> <span class="muted">(Cliente)</span></p>
            <p class="sign-row">Nombre: {{ $signer }}</p>
            <table class="sign-fields"><tr>
                <td>Firma: <span class="sign-tag">@{{signature:1:y}}</span></td>
                <td>Fecha: <span class="sign-tag">@{{date:1:y}}</span></td>
            </tr></table>
        </div>
    </div>
    <p class="muted">La firma del Cliente se recoge electrónicamente a través del socio de firma de Festilaw, con un registro de auditoría con protección contra manipulaciones. Festilaw contrafirma el mandato para completarlo.</p>

    <div class="page-break"></div>
    @include('contracts.es.general-terms')
</body>
</html>
