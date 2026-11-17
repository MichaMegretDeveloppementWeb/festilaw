# Senales contadas por el control de senales (espanol). Mismas fuentes que writing-voice-es.md.
# Una linea simple es una expresion, reconocida como palabra entera, sin distinguir mayusculas.
# Una linea que empieza por "~" es un giro : una expresion regular (sin mayusculas), luego " => " y su nombre.
# Una linea que empieza por "~+" es un giro tolerado una vez por texto, senalado desde su segunda frase.
# Los giros se prueban frase por frase : "^" es el comienzo de una frase.

# Palabras y expresiones que abusan los modelos
crucial
fundamental
esencial
robusto
integral
innovador
óptimo
panorama
ecosistema
palanca
desafío
desempeña un papel
desempeñar un papel
pone de relieve
poner de relieve
cabe destacar
cabe señalar
es importante señalar
es importante destacar
esto es lo que debe saber
la buena noticia es
en conclusión
en resumen
en definitiva
para concluir
a la hora de
de cara a
a nivel de
no dude en

# Giros
~ \bno solo\b => no solo X, sino tambien Y
~ \bno se trata de\b[^.!?]{1,80}?,?\s+sino\b => no se trata de X, sino de Y
~ ,\s+no\s+(?:un|una|de|del|el|la|los|las)\b => X, no Y
~ \ben lugar de\b => Y en lugar de X
~ \bno debe confundirse con\b => correccion no pedida
~ \b(?:las |los )?(?:páginas?|fuentes?|documentos?)\s+(?:consultad[oa]s?|examinad[oa]s?|utilizad[oa]s?|disponibles?)\s+(?:aquí|para este artículo)\b => comentario sobre las fuentes
~ \bsegún la información disponible\b => comentario sobre las fuentes
~ \blas (?:fuentes|páginas)\s+no\s+(?:precisan|dicen|describen|confirman|mencionan)\b => comentario sobre las fuentes
~ \b(?:este|esta) (?:artículo|guía)\b => texto que habla de si mismo
~ \bcomo (?:se ha )?(?:indicado|mencionado|visto|explicado) (?:más arriba|anteriormente|más abajo)\b => texto que habla de si mismo
~ \bhay (?:dos|tres|cuatro|cinco|seis|siete) (?:cosas|pasos|lugares|formas|puntos|preguntas|opciones|razones|campos|acciones)\b => lista anunciada por su numero
~ \b(?:dos|tres|cuatro|cinco|seis|siete) (?:\S+ ){0,2}(?:cosas|pasos|lugares|formas|puntos|campos|acciones|elementos|datos)\b[^.:!?]{0,40}: => lista anunciada por su numero
~ ^lo que (?:\S+\s+){1,5}es\b => puesta en escena « lo que X es »
~ ^(?:así que|de hecho|por lo tanto|en resumen),?\s => apertura que repite
~ ,\s+(?:garantizando|permitiendo|asegurando|subrayando|reflejando|ofreciendo|creando|añadiendo|dejando|haciendo|facilitando)\b => gerundio colgado
~ ^(?:habiendo|consultando|teniendo en cuenta)\b => apertura con gerundio
~ \b(?:el|un) (?:punto|elemento) (?:clave|esencial|importante) (?:es|aquí)\b => punto clave en escena
~ \b(?:constituye|representa|resulta)\b => verbo en lugar de « es »
~ \b(?:los expertos|muchos vendedores|numerosos estudios|la mayoría de los vendedores)\s+(?:coinciden|estiman|comprueban|afirman)\b => autoridad vaga
~ \b(?:podría|podrían) (?:potencialmente|eventualmente)\b => precauciones apiladas
~+ \b(?:etsy|amazon|ebay|shopify|tiktok shop|la comisión(?: europea)?|el reglamento|el gpsr)\s+(?:(?:también|además|claramente|solo)\s+)?(?:indica|precisa|explica|describe|menciona|señala|subraya|afirma|considera|define)\b => atribucion repetida
~+ \bsegún (?:etsy|amazon|ebay|shopify|la comisión|el reglamento|el centro de ayuda)\b => atribucion repetida
