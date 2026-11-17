# Signes comptes par le controle des signes (francais). Memes sources que writing-voice-fr.md.
# Une ligne simple est une expression, reconnue en mot entier, sans tenir compte de la casse.
# Une ligne qui commence par "~" est une tournure : une expression reguliere (sans casse), puis " => " et son nom.
# Une ligne qui commence par "~+" est une tournure toleree une fois par texte, relevee des sa deuxieme phrase.
# Les tournures se testent phrase par phrase : "^" est le debut d'une phrase.

# Mots et expressions qu'abusent les modeles
crucial
cruciale
essentiel
essentielle
fondamental
fondamentale
incontournable
robuste
véritable
pleinement
optimal
optimale
au cœur de
s'inscrit dans
s'inscrire dans
témoigne de
met en lumière
mettre en lumière
joue un rôle
jouer un rôle
permet de
permettant de
vise à
offre la possibilité de
paysage
écosystème
levier
enjeu
enjeux
il est important de noter
il convient de noter
il est à noter
à noter
concrètement
en clair
voici ce qu'il faut savoir
bonne nouvelle
en conclusion
pour résumer
en somme
au final
en bref
n'hésitez pas

# Tournures
~ \bnon seulement\b => non seulement X, mais aussi Y
~ \bce n['’]est pas\b[^.!?]{1,80}?,\s*c['’]est\b => ce n'est pas X, c'est Y
~ ,\s+(?:et\s+)?(?:pas|non)\s+(?:un|une|de|des|le|la|les)\b => X, pas Y
~ \bplutôt que\b => Y plutôt que X
~ \bà ne pas confondre avec\b => correction non demandee
~ \bne suffit pas (?:à|a) (?:établir|prouver|garantir)\b => correction non demandee
~ \b(?:les |des )?(?:pages?|sources?|documents?)\s+(?:consultée?s?|examinée?s?|utilisée?s?|disponibles?)\s+(?:ici|pour cet article)\b => remarque sur les sources
~ \bd['’]après les informations disponibles\b => remarque sur les sources
~ \bles (?:sources|pages)\s+ne\s+(?:précisent|disent|décrivent|confirment|mentionnent)\b => remarque sur les sources
~ \b(?:cet|ce) (?:article|guide)\b => texte qui parle de lui-meme
~ \bcomme (?:indiqué|mentionné|vu|expliqué) (?:plus haut|ci-dessus|précédemment|plus bas)\b => texte qui parle de lui-meme
~ \bil (?:y a|existe) (?:deux|trois|quatre|cinq|six|sept) (?:choses|étapes|endroits|façons|points|questions|options|raisons|champs|actions)\b => liste annoncee par son nombre
~ \b(?:deux|trois|quatre|cinq|six|sept) (?:\S+ ){0,2}(?:choses|étapes|endroits|façons|points|champs|actions|éléments|informations)\b[^.:!?]{0,40}: => liste annoncee par son nombre
~ ^ce que (?:\S+\s+){1,5}c['’]est\b => mise en scene « ce que X, c'est »
~ ^voici\b => annonce
~ ^(?:ainsi|en effet|donc|en bref|bref),?\s => ouverture qui redit
~ ,\s+(?:garantissant|permettant|assurant|soulignant|reflétant|offrant|créant|ajoutant|laissant|rendant|facilitant)\b => participe en fin de phrase
~ ,\s+ce qui permet\b => participe en fin de phrase
~ ^(?:ayant|en consultant|en vérifiant|en tenant compte)\b => ouverture par un participe
~ \b(?:le|un) (?:point|élément) (?:clé|essentiel|important) (?:est|ici)\b => point cle mis en scene
~ \b(?:constitue|représente|s['’]avère)\b => verbe a la place de « est »
~ \b(?:les experts|de nombreux vendeurs|de nombreuses études|la plupart des vendeurs)\s+(?:s['’]accordent|estiment|constatent|affirment)\b => autorite vague
~ \b(?:pourrait|pourraient) (?:potentiellement|éventuellement)\b => precautions empilees
~+ \b(?:etsy|amazon|ebay|shopify|tiktok shop|la commission(?: européenne)?|le règlement|le gpsr)\s+(?:(?:aussi|également|encore|clairement|seulement)\s+)?(?:indique|précise|explique|décrit|mentionne|souligne|affirme|considère|estime|définit)\b => attribution repetee
~+ \bselon (?:etsy|amazon|ebay|shopify|la commission|le règlement|le centre d['’]aide)\b => attribution repetee
