<x-mail.layout>
    <x-mail.heading>Erreurs relevées sur le site</x-mail.heading>

    <x-mail.text>Le journal du serveur compte <strong>{{ $occurrences }} {{ $occurrences > 1 ? 'erreurs' : 'erreur' }}</strong> du {{ $since }} au {{ $until }}, heure de Paris. Les plus fréquentes viennent en premier.</x-mail.text>

    @foreach ($rows as $row)
        <x-mail.panel>
            <div style="font-family:-apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;">
                <strong style="color:#0B1E45;">{{ $row['title'] }}</strong><br>
                <span style="color:#b4532a;">{{ $row['meta'] }}</span><br>
                @if ($row['exception'])
                    <span style="color:#4a4f5e;">{{ $row['exception'] }}</span><br>
                @endif
                @if ($row['location'])
                    <span style="color:#4a4f5e;">Dans {{ $row['location'] }}</span><br>
                @endif
                @if ($row['routes'])
                    <span style="color:#4a4f5e;">Sur la page {{ $row['routes'] }}</span><br>
                @endif
                <span style="color:#8a8f9c;">Fichier {{ $row['file'] }}</span>
            </div>
        </x-mail.panel>
    @endforeach

    @if ($hiddenGroups > 0)
        <x-mail.text>Et {{ $hiddenGroups }} autres sortes d'erreurs, moins fréquentes, à lire dans le journal.</x-mail.text>
    @endif

    @if ($unreadableFiles !== [])
        <x-mail.text>Ces fichiers du journal n'ont pas pu être lus : {{ implode(', ', $unreadableFiles) }}.</x-mail.text>
    @endif

    <x-mail.text :muted="true" size="13.5px">Ce rapport part chaque matin, seulement si le journal contient des erreurs. La trace complète de chaque erreur est dans le fichier indiqué, sous storage/logs.</x-mail.text>
</x-mail.layout>
