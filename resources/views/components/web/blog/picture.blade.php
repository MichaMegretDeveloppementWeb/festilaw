@props(['picture', 'sizes' => '100vw', 'eager' => false])
{{-- Image du blog adaptative. `picture` est une BlogPictureData (adresses, dimensions, description deja
     resolues). `eager` : image principale d'un article, chargee en priorite ; sinon chargement differe. --}}
<img
    src="{{ $picture->src }}"
    srcset="{{ $picture->srcset }}"
    sizes="{{ $sizes }}"
    width="{{ $picture->width }}"
    height="{{ $picture->height }}"
    alt="{{ $picture->alt }}"
    @if ($eager) fetchpriority="high" @else loading="lazy" @endif
    decoding="async"
    {{ $attributes }}>
