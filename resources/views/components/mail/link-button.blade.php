@props(['url'])
{{-- Petit bouton secondaire : un lien qui se voit cliquable sur telephone, sans prendre la place du bouton
     principal (demande de Micha du 24/11/2026). Son libelle ne se coupe jamais sur deux lignes ; deux boutons
     voisins passent l'un sous l'autre quand la place manque. --}}
<a href="{{ $url }}" style="display:inline-block; margin:10px 6px 0 0; padding:7px 12px; border:1px solid #cfd4de; border-radius:8px; background-color:#ffffff; font-family:-apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif; font-size:13px; font-weight:600; line-height:1.2; color:#0B1E45; text-decoration:none; white-space:nowrap;">{{ $slot }}</a>
