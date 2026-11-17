{{-- Avertissement du plafond du mois, le meme sur le tableau de bord, les reglages et la fenetre de lancement
     (BlogGenerationPresenter::monthlyWarning). Ce plafond ne bloque rien, d'ou une alerte jaune dans les deux cas. --}}
<x-ui::alert type="warning" :class="$class ?? ''" :title="$warning['title']" data-monthly-warning="{{ $warning['level']->value }}">{{ $warning['text'] }}</x-ui::alert>
