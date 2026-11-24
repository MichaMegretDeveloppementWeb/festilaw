<x-mail.layout>
    <x-mail.heading>Génération d'articles terminée</x-mail.heading>

    @if ($scheduled)
        <x-mail.text>La génération automatique vient de se terminer.</x-mail.text>
    @else
        <x-mail.text>La génération lancée depuis le back-office vient de se terminer.</x-mail.text>
    @endif

    @if (count($rows) > 1)
        <x-mail.text><strong>{{ $summary }}</strong></x-mail.text>
    @endif

    @foreach ($rows as $row)
        <x-mail.panel>
            <div style="font-family:-apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;">
                <strong style="color:#0B1E45;">{{ $row['subject'] }}</strong><br>
                <span style="color:{{ $row['succeeded'] ? '#1c7a4a' : '#b4532a' }};">{{ $row['outcome'] }}</span>
                <span style="color:#8a8f9c;"> · {{ $row['cost'] }}</span><br>
                @if ($row['excerpt'] ?? null)
                    <span style="color:#4a5568;">{{ $row['excerpt'] }}</span><br>
                @endif
                @if ($row['sources'] ?? null)
                    <span style="color:#8a8f9c;">{{ trans_choice('{1} Une source citée.|[2,*] :count sources citées.', $row['sources'], ['count' => $row['sources']]) }}</span><br>
                @endif
                @if ($row['newCategory'] ?? null)
                    <span style="color:#4a5568;">{{ __('Nouvelle catégorie « :name », créée pour cet article. Vous pouvez la renommer dans les catégories du blog.', ['name' => $row['newCategory']]) }}</span><br>
                @endif
                @if ($row['draftUrl'])
                    <x-mail.link-button :url="$row['draftUrl']">Ouvrir le brouillon</x-mail.link-button>
                @endif
                <x-mail.link-button :url="$row['url']">Voir cette génération</x-mail.link-button>
            </div>
        </x-mail.panel>
    @endforeach

    <x-mail.text>Ce lancement a coûté <strong>{{ $totalCost }}</strong> au total.</x-mail.text>

    @if ($failed > 0)
        <x-mail.text>Une génération en échec se relance depuis sa page. Elle reprend alors à l'étape qui a échoué, sans refaire ni repayer les étapes terminées. Si l'échec se répète, contactez l'assistance technique.</x-mail.text>
    @endif

    @if ($succeeded > 0)
        <x-mail.button :url="$postsUrl">Ouvrir les articles</x-mail.button>
        <div style="margin:-12px 0 22px;"><x-mail.link-button :url="$generationsUrl">Ouvrir le journal des générations</x-mail.link-button></div>
    @else
        <x-mail.button :url="$generationsUrl">Ouvrir les générations</x-mail.button>
    @endif

    <x-mail.text :muted="true" size="13.5px">Un article généré reste un brouillon, et rien n'est publié sans votre validation.</x-mail.text>
</x-mail.layout>
