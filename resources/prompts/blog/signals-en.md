# Signs counted by the writing signals check (English). Same sources as writing-voice-en.md.
# A plain line is an expression, matched as whole words, case ignored.
# A line starting with "~" is a pattern: a regular expression (case ignored), then " => " and its name.
# A line starting with "~+" is a pattern tolerated once per text: it is flagged from its second sentence on.
# Patterns are tested sentence by sentence, so "^" is the start of a sentence.

# Words and expressions models over-use
delve
tapestry
landscape
realm
seamless
seamlessly
robust
pivotal
crucial
vital
essential
comprehensive
game-changer
leverage
unlock
empower
elevate
harness
foster
streamline
underscore
underscores
showcase
showcasing
embark
enhance
ensure
ensures
highlight
highlights
highlighting
align with
valuable
intricate
multifaceted
dependable
quietly
universally
prioritize
matters
testament
in today's fast-paced
ever-evolving
plays a crucial role
plays a vital role
plays a key role
stands as a testament
serves as a reminder
a wide range of
when it comes to
at the end of the day
it's important to note
it is important to note
it's worth noting
it is worth noting
let's dive in
the good news is
rest assured
in conclusion
to sum up
in summary
ultimately

# Patterns
~ \bnot (?:just|only|merely)\b => not just X but also Y
~ \bnot\b[^.!?]{1,80}?,?\s+but\b => not X but Y
~ ,\s+not\s+(?:a|an|the|just|only)\b => X, not Y
~ \brather than\b => Y rather than X
~ \bshould not be confused with\b => unrequested correction
~ \bdoes not (?:by itself |alone |necessarily )?(?:establish|mean|imply|guarantee)\b => unrequested correction
~ \b(?:pages?|sources?|documents?|materials?)\s+(?:reviewed|consulted|used|cited|available)\s+(?:here|for this)\b => remark on the sources
~ \bbased on (?:the )?available (?:information|sources)\b => remark on the sources
~ \b(?:the sources?|the pages?|the research)\s+(?:do|does|did)\s+not\s+(?:say|specify|describe|confirm|mention|cover)\b => remark on the sources
~ \b(?:this|our) (?:guide|article|post)\b => text talking about itself
~ \bas (?:mentioned|noted|discussed|explained) (?:above|earlier|below)\b => text talking about itself
~ \bthere are (?:two|three|four|five|six|seven) (?:main |key |simple )?(?:things|steps|places|ways|points|questions|options|reasons|fields|actions)\b => list announced by its count
~ \b(?:two|three|four|five|six|seven) (?:\w+ ){0,2}(?:things|steps|places|ways|points|entries|fields|actions|requirements|changes|items)\b[^.:!?]{0,40}: => list announced by its count
~ ^what\s+(?:\S+\s+){1,5}is\b => staged "what X is"
~ ^here(?:'s|’s| is| are) (?:what|the|how|why)\b => announcement
~ ^(?:so|in short|overall|in other words),?\s => opener that restates
~ ,\s+(?:ensuring|making|highlighting|underscoring|reflecting|emphasizing|allowing|enabling|helping|leaving|creating|adding|giving|meaning)\b => participle tail
~ ^(?:having|looking at|considering|building on)\b => participle opener
~ \b(?:the|a) (?:key|real|main|important) (?:point|question|takeaway|thing|difference) (?:is|here)\b => staged key point
~ \b(?:serves|stands|functions|acts) as\b => copula avoidance
~ \b(?:experts|observers|industry reports|many sellers|most sellers)\s+(?:say|argue|agree|note|find|believe)\b => vague authority
~+ \b(?:etsy|amazon|ebay|shopify|tiktok shop|the (?:european )?commission|the regulation|the gpsr)(?:'s|’s)?\s+(?:(?:help (?:page|centre|center)|seller handbook|handbook|guidance|wording|guide|page|instruction)s?\s+)?(?:(?:also|only|now|still|clearly|explicitly|simply)\s+)?(?:says?|states?|describes?|explains?|notes?|treats?|uses?|covers?|directs?|suggests?|mentions?|specifies|defines?|lists?|frames?)\b => repeated attribution
~+ \baccording to\b => repeated attribution
~ \b(?:may|might|could) (?:potentially|possibly)\b => stacked hedge
